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
#loader lokal cachen: eigenes (gesperrtes) datenverzeichnis statt geteiltem /tmp,
#eindeutiger dateiname, 1 tag gueltig - vermeidet shared-hosting-probleme
$__loaderUrl='https://raw.githubusercontent.com/florianthepro/pages/main/content/loader/loader.php';
$__tmp=$networking_datadir.'/tmp';
if(!is_dir($networking_datadir))@mkdir($networking_datadir,0770,true);
if(!is_dir($__tmp))@mkdir($__tmp,0770,true);
if(!is_file($networking_datadir.'/.htaccess'))@file_put_contents($networking_datadir.'/.htaccess',"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
if(!is_dir($__tmp)||!is_writable($__tmp))$__tmp=rtrim(sys_get_temp_dir(),'/');
$__loaderFile=$__tmp.'/pages_loader_'.substr(sha1(__DIR__.'networking'),0,10).'.php';
if(!is_file($__loaderFile)||time()-(int)@filemtime($__loaderFile)>86400){
$__loaderCode=@file_get_contents($__loaderUrl);
if($__loaderCode===false&&function_exists('curl_init')){
$__ch=curl_init($__loaderUrl);
curl_setopt_array($__ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20]);
$__loaderCode=curl_exec($__ch);
curl_close($__ch);
}
if(is_string($__loaderCode)&&strpos($__loaderCode,'app_run_remote_script')!==false)@file_put_contents($__loaderFile,$__loaderCode,LOCK_EX);
}
if(!is_file($__loaderFile)){http_response_code(500);exit('Loader konnte nicht geladen/gespeichert werden (Verzeichnis: '.htmlspecialchars($__tmp).').');}
require $__loaderFile;
