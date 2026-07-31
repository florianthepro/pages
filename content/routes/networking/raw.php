<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if(is_readable($networking_jsondir)){
$raw=file_get_contents($networking_jsondir);
if($raw!==false){echo $raw;exit;}
}
echo json_encode(['error'=>'config.json nicht gefunden oder nicht lesbar','file'=>$networking_jsondir],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
