<?php
declare(strict_types=1);
///////////////////////
$liarc_title='LIARC'; #Seitenname
$liarc_branch='main'; #Repo-Branch
$liarc_datadir=__DIR__.'/liarc-data'; #lokale Nutzerdaten (wird automatisch angelegt und per .htaccess gesperrt)
#Gruppen/Kategorien/Felder aendern: content/routes/liarc/lib.php (liarc_groups / liarc_categories)
#Texte/Sprachen: content/routes/liarc/lang-de|en|th.php
#Design: content/routes/liarc/app.css / Icons: content/media/liarc/ (sprite.svg neu bauen)
$liarc_repo='https://raw.githubusercontent.com/florianthepro/pages/'.$liarc_branch.'/content';
$liarc_icon=$liarc_repo.'/media/liarc/liarc.svg';

$sharedVars=get_defined_vars();

$yaml=<<<YAML
license: "https://raw.githubusercontent.com/florianthepro/pages/main/LICENSE"
index: "$liarc_repo/routes/liarc/index.php"
auth: "$liarc_repo/routes/liarc/auth.php"
data: "$liarc_repo/routes/liarc/data.php"
devices: "$liarc_repo/routes/liarc/devices.php"
settings: "$liarc_repo/routes/liarc/settings.php"
api: "$liarc_repo/routes/liarc/api.php"
assets: "$liarc_repo/routes/liarc/assets.php"
YAML;
///////////////////////
#loader lokal cachen: eigenes (gesperrtes) datenverzeichnis statt geteiltem /tmp,
#eindeutiger dateiname, 1 tag gueltig - vermeidet shared-hosting-probleme
$__loaderUrl='https://raw.githubusercontent.com/florianthepro/pages/main/content/loader/loader.php';
$__tmp=$liarc_datadir.'/tmp';
if(!is_dir($__tmp))@mkdir($__tmp,0770,true);
if(!is_file($liarc_datadir.'/.htaccess'))@file_put_contents($liarc_datadir.'/.htaccess',"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
if(!is_dir($__tmp)||!is_writable($__tmp))$__tmp=rtrim(sys_get_temp_dir(),'/');
$__loaderFile=$__tmp.'/pages_loader_'.substr(sha1(__DIR__),0,10).'.php';
if(!is_file($__loaderFile)||time()-(int)@filemtime($__loaderFile)>86400){
$__loaderCode=@file_get_contents($__loaderUrl);
if($__loaderCode===false&&function_exists('curl_init')){
$__ch=curl_init($__loaderUrl);
curl_setopt_array($__ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20]);
$__loaderCode=curl_exec($__ch);
curl_close($__ch);
}
if(is_string($__loaderCode)&&str_contains($__loaderCode,'app_run_remote_script'))@file_put_contents($__loaderFile,$__loaderCode,LOCK_EX);
}
if(!is_file($__loaderFile)){http_response_code(500);exit('Loader konnte nicht geladen/gespeichert werden (Verzeichnis: '.htmlspecialchars($__tmp).').');}
require $__loaderFile;
