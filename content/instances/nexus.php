<?php
declare(strict_types=1);
if(PHP_VERSION_ID<70400){http_response_code(500);header('Content-Type: text/plain; charset=utf-8');exit('nexus benoetigt PHP 7.4 oder neuer (aktuell: '.PHP_VERSION.').');}
///////////////////////
$nexus_title='Nexus';
$nexus_theme='auto'; #'light' (fix hell) | 'dark' (fix dunkel) | 'auto' (dynamisch, folgt System)
$nexus_datadir='nexus-data'; #Datenordner: name relativ zur Instanz, absoluter Pfad auch moeglich
$nexus_icon='https://raw.githubusercontent.com/florianthepro/pages/main/content/media/nexus/index.svg';
$nexus_basedir=dirname((string)($_SERVER['SCRIPT_FILENAME']??__FILE__));
if(!preg_match('~^([/\\\\]|[A-Za-z]:)~',$nexus_datadir))$nexus_datadir=$nexus_basedir.DIRECTORY_SEPARATOR.$nexus_datadir;

$sharedVars=get_defined_vars();

$yaml=<<<'YAML'
license: "https://raw.githubusercontent.com/florianthepro/pages/main/LICENSE"
blocked: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/blocked.html"
index: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/nexus/index.php"
YAML;
///////////////////////
if(!is_dir($nexus_datadir))@mkdir($nexus_datadir,0770,true);
if(is_dir($nexus_datadir)){
if(!is_file($nexus_datadir.DIRECTORY_SEPARATOR.'.htaccess'))@file_put_contents($nexus_datadir.DIRECTORY_SEPARATOR.'.htaccess',"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
if(!is_file($nexus_datadir.DIRECTORY_SEPARATOR.'index.php'))@file_put_contents($nexus_datadir.DIRECTORY_SEPARATOR.'index.php',"<?php http_response_code(403); exit;");
}
$__loaderUrl='https://raw.githubusercontent.com/florianthepro/pages/main/content/loader/loader.php';
$__loaderFile=rtrim(sys_get_temp_dir(),'/').'/pages_loader_'.substr(sha1(__DIR__),0,10).'.php';
if(!is_file($__loaderFile)||time()-(int)@filemtime($__loaderFile)>86400){
$__loaderCode=@file_get_contents($__loaderUrl);
if($__loaderCode===false&&function_exists('curl_init')){$__ch=curl_init($__loaderUrl);curl_setopt_array($__ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20]);$__loaderCode=curl_exec($__ch);curl_close($__ch);}
if(is_string($__loaderCode)&&strpos($__loaderCode,'app_run_remote_script')!==false){@file_put_contents($__loaderFile,$__loaderCode,LOCK_EX);@chmod($__loaderFile,0600);}
}
if(!is_file($__loaderFile)){http_response_code(500);exit('Loader konnte nicht geladen/gespeichert werden.');}
require $__loaderFile;
