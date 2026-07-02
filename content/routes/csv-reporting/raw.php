<?php
exec('curl -fsS '.escapeshellarg('http://127.0.0.1/'.$csvreporting_projectpath.'/?_page=dwl'.$csvreporting_dwltype).' > /dev/null 2>&1 &');
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function load_config($path){
$out=['show_columns'=>[],'enable_links'=>false,'column_links'=>[]];
if(!is_readable($path))return $out;
$json=@file_get_contents($path);
$data=@json_decode($json,true);
if(!is_array($data))return $out;
if(isset($data['show_columns'])&&is_array($data['show_columns']))$out['show_columns']=$data['show_columns'];
if(isset($data['enable_links']))$out['enable_links']=!empty($data['enable_links']);
if(isset($data['column_links'])&&is_array($data['column_links']))$out['column_links']=$data['column_links'];
return $out;
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
return parse_csv_string((string)file_get_contents($path));
}
function col_index($header,$colName){
foreach($header as $i=>$h){
if(strcasecmp(trim((string)$h),trim((string)$colName))===0)return $i;
}
return null;
}
function timestamp_to_minutes($timestamp){
$pattern='/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/';
$t=trim((string)$timestamp);
if(!preg_match($pattern,$t))return null;
$ts=strtotime($t);
if($ts===false)return null;
$diff=($ts-time())/60;
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
return trim((string)$cellValue);
}
function get_all_filter_values($header,$dataRows,$colName,$isTimestamp){
$idx=col_index($header,$colName);
if($idx===null)return [];
$vals=[];
foreach($dataRows as $row){
if(isset($row[$idx])){
$val=get_cell_value_for_filter($row[$idx],$isTimestamp);
if($val===null)continue;
$display=$isTimestamp?($val.' min'):$val;
if(!in_array($display,$vals,true))$vals[]=$display;
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
function format_cell_value($val){
$minutes=timestamp_to_minutes($val);
if($minutes!==null)return intval($minutes).' min';
return h($val);
}
function check_filter($colName,$filterValue,$cellValue,$isTimestamp){
if($filterValue==='')return true;
$converted=get_cell_value_for_filter($cellValue,$isTimestamp);
if($converted===null)return false;
if($isTimestamp){
$filterMinutes=intval((string)$filterValue);
return $converted===$filterMinutes;
}
return $converted===(string)$filterValue;
}
function cell_by_colname($header,$row,$colName){
$idx=col_index($header,$colName);
return($idx!==null&&isset($row[$idx]))?$row[$idx]:'';
}
function build_cell_link($enableLinks,$columnLinks,$idColumn,$header,$row,$colName){
if(!$enableLinks)return null;
if(!is_array($columnLinks))return null;
if(!array_key_exists($colName,$columnLinks))return null;
$pattern=trim((string)$columnLinks[$colName]);
if($pattern===''||$pattern==='*')return null;
$idVal='';
if($idColumn!==null)$idVal=cell_by_colname($header,$row,$idColumn);else $idVal=cell_by_colname($header,$row,$colName);
$idVal=trim((string)$idVal);
if($idVal==='')return null;
$enc=rawurlencode($idVal);
if(strpos($pattern,'*')!==false)$href=str_replace('*',$enc,$pattern);else $href=$pattern;
return $href;
}
$config=load_config($csvreporting_jsondir);
$showColumns=$config['show_columns']??[];
$enableLinks=!empty($config['enable_links']);
$columnLinks=is_array($config['column_links']??null)?$config['column_links']:[];
$idColumn=null;
foreach($columnLinks as $k=>$v){if(trim((string)$v)==='*'){$idColumn=$k;break;}}
$csvRows=load_csv($csvreporting_csvdir);
$csvError=null;
if($csvRows===null)$csvError='Keine CSV gefunden oder nicht lesbar: '.$csvreporting_csvdir;
$header=[];
$dataRows=[];
if(is_array($csvRows)&&count($csvRows)>0){$header=array_map('trim',$csvRows[0]);$dataRows=array_slice($csvRows,1);}
if(!is_array($showColumns)||count($showColumns)===0)$showColumns=$header;
$timestampCols=[];
foreach($showColumns as $col){if(is_timestamp_column($header,$dataRows,$col))$timestampCols[$col]=true;}
$activeFilters=[];
foreach($showColumns as $col){$activeFilters[$col]=$_GET['filter_'.urlencode($col)]??'';}
$filtered=[];
if(is_array($dataRows)&&count($dataRows)>0){
foreach($dataRows as $row){
$passFilters=true;
foreach($showColumns as $col){
$val=cell_by_colname($header,$row,$col);
$isTS=isset($timestampCols[$col]);
if(!check_filter($col,$activeFilters[$col],$val,$isTS)){$passFilters=false;break;}
}
if($passFilters)$filtered[]=$row;
}
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="color-scheme" content="only light">
<link rel="icon" type="image/svg+xml" href="https://raw.githubusercontent.com/florianthepro/csv-reporting/refs/heads/data/icon.svg">
<title><?=$csvreporting_title?></title>
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
<h2><?=$csvreporting_heading?></h2>
<?php if($csvError):?><div class="error"><?php echo h($csvError);?></div><?php endif;?>
<h3>Ergebnisse (<?php echo count($filtered);?>)</h3>
<div class="filter-toggle" onclick="toggleFilters()"><span>Filter <span class="filter-toggle-icon" id="filterToggleIcon">▼</span></span></div>
<div class="filter-row" id="filterPanel">
<?php foreach($showColumns as $col):$values=get_all_filter_values($header,$dataRows,$col,isset($timestampCols[$col]));if(count($values)===0)continue;?>
<div class="filter-item">
<div class="filter-label"><?php echo h($col);?></div>
<select onchange="document.location=addOrUpdateParam('filter_<?php echo urlencode($col);?>',this.value)">
<option value="">-- Alle --</option>
<?php foreach($values as $v):?>
<option value="<?php echo h($v);?>"<?php if($activeFilters[$col]===$v)echo' selected';?>><?php echo h($v);?></option>
<?php endforeach;?>
</select>
</div>
<?php endforeach;?>
</div>
<table>
<thead>
<tr>
<?php foreach($showColumns as $col):?><th><?php echo h($col);?></th><?php endforeach;?>
</tr>
</thead>
<tbody>
<?php if(count($filtered)===0):?>
<tr><td colspan="<?php echo count($showColumns);?>">Keine Zeilen entsprechen den Filtern.</td></tr>
<?php else:foreach($filtered as $row):?>
<tr>
<?php foreach($showColumns as $col):$cellValue=cell_by_colname($header,$row,$col);$href=build_cell_link($enableLinks,$columnLinks,$idColumn,$header,$row,$col);$txt=format_cell_value($cellValue);?>
<td><?php if($href!==null){echo'<a target="_blank" rel="noopener noreferrer" href="'.h($href).'" style="color:#000;text-decoration:underline;text-underline-offset:2px">'.$txt.'</a>';}else{echo$txt;}?></td>
<?php endforeach;?>
</tr>
<?php endforeach;endif;?>
</tbody>
</table>
<script>
function toggleFilters(){var panel=document.getElementById('filterPanel');var icon=document.getElementById('filterToggleIcon');panel.classList.toggle('visible');icon.textContent=panel.classList.contains('visible')?'▲':'▼';}
function addOrUpdateParam(key,value){var url=new URL(window.location);if(value===''){url.searchParams.delete(key);}else{url.searchParams.set(key,value);}return url.toString();}
</script>
</body>
</html>
