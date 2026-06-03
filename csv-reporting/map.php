<?php
exec('curl -fsS '.escapeshellarg($webdwlpath).' > /dev/null 2>&1 &');
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function load_rules($path){
if(!is_readable($path))return['show_columns'=>[],'rules'=>[]];
$json=@file_get_contents($path);
$data=@json_decode($json,true);
if(!is_array($data))$data=[];
if(!isset($data['show_columns'])||!is_array($data['show_columns']))$data['show_columns']=[];
if(!isset($data['rules'])||!is_array($data['rules']))$data['rules']=[];
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
function parse_minutes_pattern($pattern){
$p=trim((string)$pattern);
$pattern_clean=preg_replace('/\s+/','',strtolower($p));
if(preg_match('/^>=(-?\d+(?:\.\d+)?)$/',$pattern_clean,$m))return['type'=>'gte','value'=>floatval($m[1])];
if(preg_match('/^>(-?\d+(?:\.\d+)?)$/',$pattern_clean,$m))return['type'=>'gt','value'=>floatval($m[1])];
if(preg_match('/^<=(-?\d+(?:\.\d+)?)$/',$pattern_clean,$m))return['type'=>'lte','value'=>floatval($m[1])];
if(preg_match('/^<(-?\d+(?:\.\d+)?)$/',$pattern_clean,$m))return['type'=>'lt','value'=>floatval($m[1])];
if(preg_match('/^(-?\d+(?:\.\d+)?)\.\.(-?\d+(?:\.\d+)?)$/',$pattern_clean,$m))return['type'=>'range','min'=>floatval($m[1]),'max'=>floatval($m[2])];
if(preg_match('/^(-?\d+(?:\.\d+)?)$/',$pattern_clean,$m))return['type'=>'exact','value'=>floatval($m[1])];
return['type'=>'invalid'];
}
function check_minutes_condition($cellValue,$pattern){
$minutes=timestamp_to_minutes($cellValue);
if($minutes===null)return false;
$parsed=parse_minutes_pattern($pattern);
switch($parsed['type']){
case 'gt':return $minutes>$parsed['value'];
case 'gte':return $minutes>=$parsed['value'];
case 'lt':return $minutes<$parsed['value'];
case 'lte':return $minutes<=$parsed['value'];
case 'range':return $minutes>=$parsed['min']&&$minutes<=$parsed['max'];
case 'exact':return $minutes==$parsed['value'];
default:return false;
}
}
function eval_text_op($value,$op,$cmp){
$value=(string)$value;
$cmp=(string)$cmp;
switch($op){
case 'wildcard':$pattern='/^'.str_replace(['\*','\?'],['.*','.'],preg_quote($cmp,'/')).'$/i';return @preg_match($pattern,$value)===1;
case 'contains':return stripos($value,$cmp)!==false;
case 'equals':return strcmp($value,$cmp)===0;
case 'starts_with':return stripos($value,$cmp)===0;
case 'ends_with':$len=strlen($cmp);if($len===0)return true;return strcasecmp(substr($value,-$len),$cmp)===0;
case 'regex':$ok=@preg_match($cmp,$value);return $ok===1;
case 'empty':return trim($value)==='';
case 'empty_or_na':$v=trim($value);return $v===''||strcasecmp($v,'N/A')===0;
default:return false;
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
if($isTimestampCol)$base=check_minutes_condition($val,$pattern);
else{$parsed=parse_condition_pattern($pattern);$base=eval_text_op($val,$parsed['op'],$parsed['value']);}
if(!empty($cond['negate']))$base=!$base;
return $base;
}
function rule_matches($rule,$header,$row,$timestampCols){
$conds=isset($rule['conditions'])&&is_array($rule['conditions'])?$rule['conditions']:[];
if(count($conds)===0)return false;
foreach($conds as $c){if(!eval_condition($c,$header,$row,$timestampCols))return false;}
return true;
}
function format_cell_value($val){
$minutes=timestamp_to_minutes($val);
if($minutes!==null)return intval($minutes).' min';
return h($val);
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
$rulesData=load_rules($rulesFile);
$showColumns=$rulesData['show_columns']??[];
$rules=$rulesData['rules']??[];
$csvRows=load_csv($csvFile);
$csvError=null;
if($csvRows===null)$csvError='Keine CSV gefunden oder nicht lesbar: '.$csvFile;
$header=[];
$dataRows=[];
if(is_array($csvRows)&&count($csvRows)>0){$header=array_map('trim',$csvRows[0]);$dataRows=array_slice($csvRows,1);}
if(!is_array($showColumns)||count($showColumns)===0)$showColumns=$header;
$timestampCols=[];
foreach($showColumns as $col){if(is_timestamp_column($header,$dataRows,$col))$timestampCols[$col]=true;}
$filtered=[];
if(is_array($dataRows)&&count($dataRows)>0&&is_array($rules)&&count($rules)>0){
foreach($dataRows as $row){
foreach($rules as $r){
if(rule_matches($r,$header,$row,$timestampCols)){$filtered[]=$row;break;}
}
}
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="color-scheme" content="only light">
<link rel="icon" type="image/svg+xml" href="https://raw.githubusercontent.com/florianthepro/public/refs/heads/main/sql-csv-reporting/icon.svg">
<!--<meta http-equiv="refresh" content="1">-->
<title><?=$title?></title>
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:18px;background:#ffffff;color:#000000}
table{border-collapse:collapse;width:100%;background:#ffffff}
th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;background:#ffffff;color:#000000}
th{background:#f6f6f6;color:#000000}
.error{color:#900;font-weight:bold;margin-bottom:8px}
</style>
</head>
<body>
<h2><?=$heading?></h2>
<?php if($csvError):?><div class="error"><?php echo h($csvError);?></div><?php endif;?>
<h3>Ergebnisse (<?php echo count($filtered);?>)</h3>
<table>
<thead>
<tr>
<?php foreach($showColumns as $col):?>
<th><?php echo h($col);?></th>
<?php endforeach;?>
</tr>
</thead>
<tbody>
<?php if(count($filtered)===0):?>
<tr><td colspan="<?php echo count($showColumns);?>">Keine Zeilen entsprechen den Regeln.</td></tr>
<?php else:foreach($filtered as $row):?>
<tr>
<?php foreach($showColumns as $col):$isTrigger=cell_is_trigger($header,$row,$col,$rules,$timestampCols);$cellValue=cell_by_colname($header,$row,$col);?>
<td<?php if($isTrigger)echo' style="background:#ffe5e5"';?>><?php echo format_cell_value($cellValue);?></td>
<?php endforeach;?>
</tr>
<?php endforeach;endif;?>
</tbody>
</table>
</body>
</html>
