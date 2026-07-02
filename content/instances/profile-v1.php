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
///////////////////////
$__loaderUrl='https://raw.githubusercontent.com/florianthepro/pages/main/content/loader/loader.php';
$__loaderFile=sys_get_temp_dir().'/florian_pages_loader.php';
$__loaderCode=file_get_contents($__loaderUrl);
if($__loaderCode===false){http_response_code(500);exit('Loader konnte nicht geladen werden.');}
if(file_put_contents($__loaderFile,$__loaderCode,LOCK_EX)===false){http_response_code(500);exit('Loader konnte nicht gespeichert werden.');}
require $__loaderFile;
