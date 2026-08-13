<?php
declare(strict_types=1);
if(!preg_match('~^([/\\\\]|[A-Za-z]:)~',(string)$networking_jsondir)){
$__base=isset($appBaseDir)&&is_string($appBaseDir)&&$appBaseDir!==''?$appBaseDir:((string)($_SERVER['SCRIPT_FILENAME']??'')!==''?dirname((string)$_SERVER['SCRIPT_FILENAME']):(string)getcwd());
$networking_jsondir=rtrim($__base,'/\\').DIRECTORY_SEPARATOR.$networking_jsondir;
}
header('Content-Type: application/json; charset=utf-8');
if(is_readable($networking_jsondir)){
$raw=file_get_contents($networking_jsondir);
if($raw!==false){echo $raw;exit;}
}
echo json_encode(['error'=>'config.json nicht gefunden oder nicht lesbar','file'=>$networking_jsondir],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
