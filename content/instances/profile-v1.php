<?php
declare(strict_types=1);
///////////////////////
$yaml = <<<'YAML'
profilepicture: "/florianthepro.jpg"
Signal: ["https://signal.me/#eu/DVG-gGEMF3rI1SSVh_-CiGgwEEtoQALtoGY1EUvLI4nKmoZAScBLP7J7gLT6pLsK", "/icons/signal.png"]
Snapchat: ["https://www.snapchat.com/add/florianthepro", "/icons/snapchat.png"]
TikTok: ["https://www.tiktok.com/@florianthepro", "/icons/tiktok.png"]
Discord: ["https://discord.com/users/1428425408029659197", "/icons/discord.png"]
Steam: ["https://steamcommunity.com/id/florianthepro/", "/icons/steam.png"]
FTPcraft Server: ["https://discord.gg/6unwWTFUME", "/icons/server.png"]
YAML;

$profileName = 'florianthepro';
$description = 'Florian 18 München Straight';
$profilePicture = '';
$profileAlt = '';
$links = [];

$profile_theme='auto'; #'light' (fix hell) | 'dark' (fix dunkel) | 'auto' (dynamisch, folgt System)

$sharedVars=get_defined_vars();

$yaml=<<<'YAML'
index: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/profile/v1.php"
YAML;
///////////////////////
$__loaderUrl='https://raw.githubusercontent.com/florianthepro/pages/main/content/loader/loader.php';
$__loaderFile=rtrim(sys_get_temp_dir(),'/').'/pages_loader_'.substr(sha1(__DIR__),0,10).'.php';
if(!is_file($__loaderFile)||time()-(int)@filemtime($__loaderFile)>86400){
$__loaderCode=@file_get_contents($__loaderUrl);
if($__loaderCode===false&&function_exists('curl_init')){$__ch=curl_init($__loaderUrl);curl_setopt_array($__ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20]);$__loaderCode=curl_exec($__ch);curl_close($__ch);}
if(is_string($__loaderCode)&&strpos($__loaderCode,'app_run_remote_script')!==false){@file_put_contents($__loaderFile,$__loaderCode,LOCK_EX);@chmod($__loaderFile,0600);}
}
if(!is_file($__loaderFile)){http_response_code(500);exit('Loader konnte nicht geladen/gespeichert werden.');}
require $__loaderFile;
