<?php
declare(strict_types=1);
///////////////////////
$networking_datadir='networking'; #daten-ordner: name relativ zur seite, absoluter pfad auch möglich
$networking_title='NETWORKING';
$networking_heading='NETWORKING by Florian';
$networking_iconbase='https://raw.githubusercontent.com/florianthepro/pages/main/content/media/networking/'; #base url for typeIcons values without http
$networking_icon='https://raw.githubusercontent.com/florianthepro/pages/main/content/media/networking/index.svg';
$networking_basedir=dirname((string)($_SERVER['SCRIPT_FILENAME']??__FILE__)); #ort dieser seite
if(!preg_match('~^([/\\\\]|[A-Za-z]:)~',$networking_datadir))$networking_datadir=$networking_basedir.DIRECTORY_SEPARATOR.$networking_datadir;
$networking_jsondir=$networking_datadir.DIRECTORY_SEPARATOR.'config.json';

$sharedVars=get_defined_vars();

$yaml=<<<'YAML'
license: "https://raw.githubusercontent.com/florianthepro/pages/main/LICENSE"
blocked: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/blocked.html"
index: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/networking/index.php"
bsp: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/networking/bsp.php"
raw: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/networking/raw.php"
YAML;
///////////////////////
$__loaderUrl='https://raw.githubusercontent.com/florianthepro/pages/main/content/loader/loader.php';
$__loaderFile=sys_get_temp_dir().'/florian_pages_loader.php';
$__loaderCode=file_get_contents($__loaderUrl);
if($__loaderCode===false){http_response_code(500);exit('Loader konnte nicht geladen werden.');}
if(file_put_contents($__loaderFile,$__loaderCode,LOCK_EX)===false){http_response_code(500);exit('Loader konnte nicht gespeichert werden.');}
require $__loaderFile;
