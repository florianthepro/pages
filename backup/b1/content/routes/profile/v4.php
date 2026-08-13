<?php
declare(strict_types=1);

function e(string $value): string
{
return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function unquote(string $value): string
{
$value=trim($value);
if($value==='')return'';
if((str_starts_with($value,'"')&&str_ends_with($value,'"'))||(str_starts_with($value,"'")&&str_ends_with($value,"'"))){
return substr($value,1,-1);
}
return $value;
}

function is_https_url(string $value): bool
{
if($value==='')return false;
if(!filter_var($value,FILTER_VALIDATE_URL))return false;
$parts=parse_url($value);
return is_array($parts)&&isset($parts['scheme'])&&strtolower((string)$parts['scheme'])==='https';
}

function is_safe_image_src(string $value): bool
{
if($value==='')return false;
if(str_starts_with($value,'/'))return true;
return is_https_url($value);
}

$profilePicture=$profilePicture??'';
$profileAlt=$profileAlt??'';
$links=is_array($links??null)?$links:[];
$profileAlt=$profileAlt!==''?$profileAlt:($profileName??'');

foreach(preg_split('/\R/',trim((string)$profileyaml)) as $line){
$line=trim($line);
if($line===''||str_starts_with($line,'#'))continue;
if(!preg_match('/^([^:]+):\s*(.+)$/',$line,$match))continue;
$key=trim($match[1]);
$value=trim($match[2]);

if(preg_match('/^\[\s*(["\'])(.*?)\1\s*,\s*(["\'])(.*?)\3\s*\]$/',$value,$arrayMatch)){
$url=trim($arrayMatch[2]);
$icon=trim($arrayMatch[4]);
if(is_https_url($url)&&is_safe_image_src($icon)){
$links[]=[
'title'=>$key,
'url'=>$url,
'icon'=>$icon
];
}
continue;
}

$scalar=unquote($value);

if($key==='profilepicture'){
if(is_safe_image_src($scalar)){
$profilePicture=$scalar;
}
continue;
}

if($key==='profilepicturealt'){
if($scalar!==''){
$profileAlt=$scalar;
}
continue;
}
}
?>
