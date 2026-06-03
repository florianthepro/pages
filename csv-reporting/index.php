<?php
exec('curl -fsS '.escapeshellarg($webdwlpath).' > /dev/null 2>&1 &');

function h($s){
  return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}

function load_rules($path){
  if(!is_readable($path))return['show_columns'=>[],'rules'=>[],'enable_links'=>false,'column_links'=>[]];
  $json=@file_get_contents($path);
  $data=@json_decode($json,true);
  if(!is_array($data))$data=[];
  if(!isset($data['show_columns'])||!is_array($data['show_columns']))$data['show_columns']=[];
  if(!isset($data['rules'])||!is_array($data['rules']))$data['rules']=[];
  if(!isset($data['enable_links']))$data['enable_links']=false;
  if(!isset($data['column_links'])||!is_array($data['column_links']))$data['column_links']=[];
  return $data;
}

function parse_csv_string($csvContent){
  $lines=preg_split("/\r\n|\n|\r/",$csvContent);
  $rows=[];
  foreach($lines as $line){
    if($line==='')continue;
    $rows[]=str_getcsv($line);
  }
  return $rows;
}

function load_csv($path){
  if(!is_readable($path))return null;
  return parse_csv_string(file_get_contents($path));
}

function col_index($header,$colName){
  foreach($header as $i=>$h){
    if(strcasecmp(trim($h),trim($colName))===0)return $i;
  }
  return null;
}

function timestamp_to_minutes($timestamp){
  $pattern='/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/';
  if(!preg_match($pattern,trim($timestamp)))return null;
  $ts=strtotime(trim($timestamp));
  if($ts===false)return null;
  $now=time();
  $diff=($ts-$now)/60;
  return round($diff,2);
}

function is_timestamp_column($header,$dataRows,$colName){
  $idx=col_index($header,$colName);
  if($idx===null)return false;
  $sampleCount=0;
  foreach($dataRows as $row){
    if(isset($row[$idx])&&$row[$idx]!==''){
      if(timestamp_to_minutes($row[$idx])!==null){
        $sampleCount++;
        if($sampleCount>=3)return true;
      }
    }
  }
  return false;
}

function get_cell_value_for_filter($cellValue,$isTimestamp){
if($isTimestamp){
$minutes=timestamp_to_minutes($cellValue);
if($minutes!==null)return intval($minutes);
return null;
}
$v=trim((string)$cellValue);
if($v==='')return 'n/a';
if(strcasecmp($v,'n/a')===0||strcasecmp($v,'na')===0)return 'n/a';
return $v;
}

function get_all_filter_values($header,$dataRows,$colName,$isTimestamp){
  $idx=col_index($header,$colName);
  if($idx===null)return [];

  $vals=[];

  foreach($dataRows as $row){
    if(isset($row[$idx])){
      $val=get_cell_value_for_filter($row[$idx],$isTimestamp);
      if($val===null)continue;

      if($isTimestamp){
        $display=$val.' min';
      }else{
        $display=$val;
      }

      if(!in_array($display,$vals,true)){
        $vals[]=$display;
      }
    }
  }

  if($isTimestamp){
    usort($vals,function($a,$b){
      $aNum=intval(explode(' ',$a)[0]);
      $bNum=intval(explode(' ',$b)[0]);
      return $aNum<=>$bNum;
    });
  }else{
    sort($vals,SORT_NATURAL|SORT_FLAG_CASE);
  }

  return $vals;
}

function parse_minutes_pattern($pattern){
  $p=trim((string)$pattern);
  $pattern_clean=preg_replace('/\s+/','',strtolower($p));

  if(preg_match('/^>=(-?\d+(?:\.\d+)?)$/',$pattern_clean,$m)){
    return ['type'=>'gte','value'=>floatval($m[1])];
  }
  if(preg_match('/^>(-?\d+(?:\.\d+)?)$/',$pattern_clean,$m)){
    return ['type'=>'gt','value'=>floatval($m[1])];
  }
  if(preg_match('/^<=(-?\d+(?:\.\d+)?)$/',$pattern_clean,$m)){
    return ['type'=>'lte','value'=>floatval($m[1])];
  }
  if(preg_match('/^<(-?\d+(?:\.\d+)?)$/',$pattern_clean,$m)){
    return ['type'=>'lt','value'=>floatval($m[1])];
  }
  if(preg_match('/^(-?\d+(?:\.\d+)?)\.\.(-?\d+(?:\.\d+)?)$/',$pattern_clean,$m)){
    return ['type'=>'range','min'=>floatval($m[1]),'max'=>floatval($m[2])];
  }
  if(preg_match('/^(-?\d+(?:\.\d+)?)$/',$pattern_clean,$m)){
    return ['type'=>'exact','value'=>floatval($m[1])];
  }

  return ['type'=>'invalid'];
}

function check_minutes_condition($cellValue,$pattern){
  $minutes=timestamp_to_minutes($cellValue);
  if($minutes===null)return false;

  $parsed=parse_minutes_pattern($pattern);

  switch($parsed['type']){
    case 'gt':
      return $minutes > $parsed['value'];
    case 'gte':
      return $minutes >= $parsed['value'];
    case 'lt':
      return $minutes < $parsed['value'];
    case 'lte':
      return $minutes <= $parsed['value'];
    case 'range':
      return $minutes >= $parsed['min'] && $minutes <= $parsed['max'];
    case 'exact':
      return $minutes == $parsed['value'];
    default:
      return false;
  }
}

function eval_text_op($value,$op,$cmp){
  $value=(string)$value;
  $cmp=(string)$cmp;

  switch($op){
    case 'wildcard':
      $pattern='/^'.str_replace(['\*','\?'],['.*','.'],preg_quote($cmp,'/')).'$/i';
      return @preg_match($pattern,$value)===1;
    case 'contains':
      return stripos($value,$cmp)!==false;
    case 'equals':
      return strcmp($value,$cmp)===0;
    case 'starts_with':
      return stripos($value,$cmp)===0;
    case 'ends_with':
      $len=strlen($cmp);
      if($len===0)return true;
      return strcasecmp(substr($value,-$len),$cmp)===0;
    case 'regex':
      $ok=@preg_match($cmp,$value);
      return $ok===1;
    case 'empty':
      return trim($value)==='';
    case 'empty_or_na':
      $v=trim($value);
      return $v===''||strcasecmp($v,'N/A')===0;
    default:
      return false;
  }
}

function parse_condition_pattern($pattern){
  $p=trim((string)$pattern);

  if($p==='*')return['op'=>'wildcard','value'=>'*'];
  if(strcasecmp($p,'n/a')===0||strcasecmp($p,'na')===0)return['op'=>'empty_or_na','value'=>''];

  return['op'=>'wildcard','value'=>$p];
}

function eval_condition($cond,$header,$row,$timestampCols){
  $col=isset($cond['column'])?(string)$cond['column']:'';
  $pattern=isset($cond['pattern'])?(string)$cond['pattern']:'';
  if($col===''||$pattern==='')return true;

  $idx=col_index($header,$col);
  $val=($idx!==null&&isset($row[$idx]))?$row[$idx]:'';

  $isTimestampCol=isset($timestampCols[$col]);

  if($isTimestampCol){
    $base=check_minutes_condition($val,$pattern);
  }else{
    $parsed=parse_condition_pattern($pattern);
    $base=eval_text_op($val,$parsed['op'],$parsed['value']);
  }

  if(!empty($cond['negate']))$base=!$base;
  return $base;
}

function rule_matches($rule,$header,$row,$timestampCols){
  $conds=isset($rule['conditions'])&&is_array($rule['conditions'])?$rule['conditions']:[];
  if(count($conds)===0)return false;
  foreach($conds as $c){
    if(!eval_condition($c,$header,$row,$timestampCols))return false;
  }
  return true;
}

function get_matching_rules($header,$row,$rules,$timestampCols){
  $matched=[];
  foreach($rules as $idx=>$rule){
    if(rule_matches($rule,$header,$row,$timestampCols)){
      $matched[]=['idx'=>$idx,'desc'=>$rule['description']??'Rule '.($idx+1)];
    }
  }
  return $matched;
}

function format_cell_value($val){
$minutes=timestamp_to_minutes($val);
if($minutes!==null)return intval($minutes).' min';
$v=trim((string)$val);
if($v===''||strcasecmp($v,'n/a')===0||strcasecmp($v,'na')===0)$v='n/a';
return h($v);
}

function check_filter($colName,$filterValue,$cellValue,$isTimestamp){
  if($filterValue==='')return true;

  $converted=get_cell_value_for_filter($cellValue,$isTimestamp);
  if($converted===null)return false;

  if($isTimestamp){
    $filterMinutes=intval($filterValue);
    return $converted===$filterMinutes;
  }else{
    return $converted===$filterValue;
  }
}

function cell_by_colname($header,$row,$colName){
  $idx=col_index($header,$colName);
  return($idx!==null&&isset($row[$idx]))?$row[$idx]:'';
}

function cell_is_trigger($header,$row,$colName,$rules,$timestampCols){
  foreach($rules as $rule){
    if(!rule_matches($rule,$header,$row,$timestampCols))continue;
    $conds=isset($rule['conditions'])&&is_array($rule['conditions'])?$rule['conditions']:[];
    foreach($conds as $c){
      if(!isset($c['column']))continue;
      if(strcasecmp(trim($c['column']),trim($colName))!==0)continue;
      if(eval_condition($c,$header,$row,$timestampCols))return true;
    }
  }
  return false;
}

function build_cell_link($enableLinks,$columnLinks,$idColumn,$header,$row,$colName){
  if(!$enableLinks)return null;
  if(!is_array($columnLinks))return null;
  if(!array_key_exists($colName,$columnLinks))return null;
  $pattern=trim((string)$columnLinks[$colName]);
  if($pattern===''||$pattern==='*')return null;
  $idVal='';
  if($idColumn!==null)$idVal=cell_by_colname($header,$row,$idColumn);
  else $idVal=cell_by_colname($header,$row,$colName);
  $idVal=trim((string)$idVal);
  if($idVal==='')return null;
  $enc=rawurlencode($idVal);
  if(strpos($pattern,'*')!==false)$href=str_replace('*',$enc,$pattern);
  else $href=$pattern;
  return $href;
}

$rulesData=load_rules($rulesFile);
$showColumns=$rulesData['show_columns']??[];
$rules=$rulesData['rules']??[];
$enableLinks=!empty($rulesData['enable_links']);
$columnLinks=is_array($rulesData['column_links']??null)?$rulesData['column_links']:[];
$idColumn=null;
foreach($columnLinks as $k=>$v){
  if(trim((string)$v)==='*'){ $idColumn=$k; break; }
}
$csvRows=load_csv($csvFile);
$csvError=null;

if($csvRows===null)$csvError='Keine CSV gefunden oder nicht lesbar: '.$csvFile;

$header=[];
$dataRows=[];
if(is_array($csvRows)&&count($csvRows)>0){
  $header=array_map('trim',$csvRows[0]);
  $dataRows=array_slice($csvRows,1);
}

if(!is_array($showColumns)||count($showColumns)===0)$showColumns=$header;

$timestampCols=[];
foreach($showColumns as $col){
  if(is_timestamp_column($header,$dataRows,$col)){
    $timestampCols[$col]=true;
  }
}

$ruleBaseRows=[];
if(is_array($dataRows)&&count($dataRows)>0&&is_array($rules)&&count($rules)>0){
foreach($dataRows as $row){
foreach($rules as $r){
if(rule_matches($r,$header,$row,$timestampCols)){$ruleBaseRows[]=$row;break;}
}
}
}

$activeFilters=[];
foreach($showColumns as $col){
$activeFilters[$col]=$_GET['filter_'.urlencode($col)]??'';
}
$activeFilterPairs=[];
foreach($activeFilters as $c=>$v){if($v!=='')$activeFilterPairs[]=$c.': '.$v;}
$activeFilterText=implode(', ',$activeFilterPairs);
$filtered=[];
if(count($ruleBaseRows)>0){
foreach($ruleBaseRows as $row){
$pass=true;
foreach($showColumns as $col){
$val=cell_by_colname($header,$row,$col);
$isTS=isset($timestampCols[$col]);
if(!check_filter($col,$activeFilters[$col]??'',$val,$isTS)){$pass=false;break;}
}
if($pass)$filtered[]=$row;
}
}

?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="color-scheme" content="only light">
<link rel="icon" type="image/svg+xml" href="https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/index.svg">
<title><?=$title?></title>
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:18px;background:#ffffff;color:#000000}
table{border-collapse:collapse;width:100%;background:#ffffff}
th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;background:#ffffff;color:#000000}
th{background:#f6f6f6;color:#000000}
.error{color:#900;font-weight:bold;margin-bottom:8px}
.fk-menu-btn{position:fixed;top:10px;left:10px;z-index:999999;background:#111;color:#fff;border:none;padding:10px 14px;font-size:20px;cursor:pointer;border-radius:4px}
.fk-menu-overlay{position:fixed;inset:0;display:none;z-index:999998}
.fk-menu-overlay.is-visible{display:block}
.fk-menu-backdrop{position:absolute;inset:0;background:rgba(0,0,0,0.55)}
.fk-menu-panel{position:absolute;top:0;left:0;width:260px;height:100%;background:#fff;padding:20px;box-sizing:border-box;transform:translateX(-100%);transition:transform .25s ease-out}
.fk-menu-overlay.is-visible .fk-menu-panel{transform:translateX(0)}
.fk-menu-close{background:none;border:none;font-size:28px;cursor:pointer;margin-left:auto;display:block;color:#000}
.fk-menu-nav{margin-top:20px;display:flex;flex-direction:column;gap:12px}
.fk-menu-link{text-decoration:none;font-size:18px;color:#222}
.fk-menu-link:hover{color:#0070ff}
.info-icon{cursor:pointer;font-weight:bold;color:#0070ff;margin-left:4px;font-size:14px}
.rules-overlay{position:fixed;display:none;background:#fff;border:1px solid #0070ff;border-radius:4px;padding:12px;max-width:400px;box-shadow:0 2px 10px rgba(0,0,0,0.2);z-index:10000;max-height:300px;overflow-y:auto}
.rules-overlay.visible{display:block}
.rules-overlay-title{font-weight:bold;margin-bottom:8px;color:#0070ff;border-bottom:1px solid #0070ff;padding-bottom:6px;font-size:12px}
.rules-overlay-item{padding:6px 0;border-bottom:1px solid #eee;font-size:12px;line-height:1.3;word-break:break-word}
.rules-overlay-item:last-child{border-bottom:none}
.filter-toggle{background:#f0f0f0;border:1px solid #ccc;border-radius:3px;padding:10px;margin-bottom:12px;cursor:pointer;user-select:none;display:flex;justify-content:space-between;align-items:center}
.filter-toggle:hover{background:#e5e5e5}
.filter-toggle-icon{font-weight:bold;color:#0070ff}
.filter-row{background:#f9f9f9;padding:12px;margin-bottom:12px;border-radius:3px;border:1px solid #eee;display:none}
.filter-row.visible{display:block}
.filter-item{margin-bottom:10px}
.filter-item:last-child{margin-bottom:0}
.filter-row select{padding:4px;border:1px solid #ccc;border-radius:3px;font-size:13px;width:100%;box-sizing:border-box}
.filter-label{font-size:12px;font-weight:bold;margin-bottom:4px;color:#333}
</style>
</head>
<body>
<button class="fk-menu-btn" data-fk-menu-btn>☰</button>
<div class="fk-menu-overlay" data-fk-menu-overlay aria-hidden="true">
<div class="fk-menu-backdrop" data-fk-menu-close></div>
<div class="fk-menu-panel">
<button class="fk-menu-close" data-fk-menu-close>×</button>
<nav class="fk-menu-nav">

<a href="" target="_self" class="fk-menu-link">index.php</a>
<a href="?_page=map" target="_blank" class="fk-menu-link">map.php</a>
<a href="?_page=raw" target="_blank" class="fk-menu-link">raw.php</a>
<!--<a href="?_page=raw" target="_blank" class="fk-menu-link">raw.php</a>-->
</br>
<a href="?_page=edit" target="_blank" class="fk-menu-link">edit.php</a>
<a href="data.json" target="_blank" class="fk-menu-link">data.json</a>
</br>
<a href="?_page=<?= $dwl_type ?>" target="_blank" class="fk-menu-link">dwl.php</a>
<a href="<?php echo h($CSV_FILE);?>" target="_blank" class="fk-menu-link"><?php echo h($CSV_FILE);?></a>
</br></br></br></br></br></br></br></br></br></br></br></br></br></br></br></br></br></br></br></br>
<a href="update_log.txt" target="_blank" class="fk-menu-link">update_log.txt</a>
<a href="?_page=license" target="_blank" class="fk-menu-link">LICENSE</a>

</nav>
</div>
</div>
<div class="rules-overlay" id="rulesOverlay">
<div class="rules-overlay-title">Zutreffende Regeln:</div>
<div id="rulesContent"></div>
</div>
<script>
(function(){"use strict";var btn=document.querySelector("[data-fk-menu-btn]");var overlay=document.querySelector("[data-fk-menu-overlay]");var closers=document.querySelectorAll("[data-fk-menu-close]");var isOpen=false;if(!btn||!overlay)return;function openMenu(){if(isOpen)return;isOpen=true;overlay.classList.add("is-visible");overlay.setAttribute("aria-hidden","false");document.documentElement.style.overflow="hidden"}function closeMenu(){if(!isOpen)return;isOpen=false;overlay.classList.remove("is-visible");overlay.setAttribute("aria-hidden","true");document.documentElement.style.overflow=""}btn.addEventListener("click",function(e){e.stopPropagation();isOpen?closeMenu():openMenu()});closers.forEach(function(el){el.addEventListener("click",closeMenu)});overlay.addEventListener("click",function(e){var panel=overlay.querySelector(".fk-menu-panel");if(panel&&!panel.contains(e.target))closeMenu()});document.addEventListener("keydown",function(e){if(e.key==="Escape")closeMenu()})})();
</script>
<h2>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?=$heading?></h2>
<?php if($csvError):?><div class="error"><?php echo h($csvError);?></div><?php endif;?>
<h3>Ergebnisse (<?php echo count($filtered);?>)</h3>
<div class="filter-toggle" onclick="toggleFilters()">
<span>Filter <span class="filter-toggle-icon" id="filterToggleIcon">▼</span></span>
</div>
<div class="filter-row" id="filterPanel">
<?php foreach($showColumns as $col):
$isTS=isset($timestampCols[$col]);
$values=get_all_filter_values($header,$ruleBaseRows,$col,$isTS);
$af=$activeFilters[$col]??'';
if($af!==''&&!in_array($af,$values,true))$values[]=$af;
if(count($values)===0)continue;
?>
<div class="filter-item">
<div class="filter-label"><?php echo h($col);?></div>
<select onchange="document.location=addOrUpdateParam('filter_<?php echo urlencode($col);?>',this.value)">
<option value="">-- Alle --</option>
<?php foreach($values as $v):?>
<option value="<?php echo h($v);?>"<?php
$af=$activeFilters[$col]??'';
if($af!==''){
if(isset($timestampCols[$col])){if(intval($af)===intval($v))echo' selected';}
else{if($af===$v)echo' selected';}
}
?>>
<?php echo h($v);?></option>
<?php endforeach;?>
</select>
</div>
<?php endforeach;?>
</div>
<table>
<thead>
<tr>
<th style="width:30px"></th>
<?php foreach($showColumns as $col):?>
<th><?php echo h($col);?></th>
<?php endforeach;?>
</tr>
</thead>
<tbody>
<?php if(count($filtered)===0):?>
<tr><td colspan="<?php echo count($showColumns)+1;?>">Keine Zeilen entsprechen den Regeln.</td></tr>
<?php else:foreach($filtered as $row):$matchingRules=get_matching_rules($header,$row,$rules,$timestampCols);?>
<tr>
<td style="text-align:center;padding:4px"><span class="info-icon" onclick="showRulesOverlay(event,<?php echo htmlspecialchars(json_encode($matchingRules));?>)">ⓘ</span></td>
<?php foreach($showColumns as $col):$isTrigger=cell_is_trigger($header,$row,$col,$rules,$timestampCols);$cellValue=cell_by_colname($header,$row,$col);$href=build_cell_link($enableLinks,$columnLinks,$idColumn,$header,$row,$col);?>
<td<?php if($isTrigger)echo' style="background:#ffe5e5"';?>><?php
$txt=format_cell_value($cellValue);
if($href!==null){
  echo'<a target="_blank" rel="noopener noreferrer" href="'.h($href).'" style="color:#000;text-decoration:underline;text-underline-offset:2px">'. $txt .'</a>';
}else{
  echo $txt;
}
?></td>
<?php endforeach;?>
</tr>
<?php endforeach;endif;?>
</tbody>
</table>
<script>
function toggleFilters(){
var panel=document.getElementById('filterPanel');
var icon=document.getElementById('filterToggleIcon');
panel.classList.toggle('visible');
icon.textContent=panel.classList.contains('visible')?'▲':'▼';
}
function addOrUpdateParam(key,value){
var url=new URL(window.location);
if(value===''){
url.searchParams.delete(key);
}else{
url.searchParams.set(key,value);
}
return url.toString();
}
function showRulesOverlay(event,rules){
var overlay=document.getElementById('rulesOverlay');
var content=document.getElementById('rulesContent');
content.innerHTML='';
rules.forEach(function(r){
var div=document.createElement('div');
div.className='rules-overlay-item';
var text=r.desc;
if(text.length>60){
text=text.substring(0,57)+'...';
}
div.textContent=text;
content.appendChild(div);
});
var maxX=window.innerWidth-420;
var x=Math.min(event.clientX+10,maxX);
var maxY=window.innerHeight-320;
var y=Math.min(event.clientY+10,maxY);
overlay.style.left=x+'px';
overlay.style.top=y+'px';
overlay.classList.add('visible');
setTimeout(function(){
document.addEventListener('click',function hideOverlay(e){
if(!overlay.contains(e.target)&&e.target!==event.target){
overlay.classList.remove('visible');
document.removeEventListener('click',hideOverlay);
}
});
},0);
}
</script>
</body>
</html>
