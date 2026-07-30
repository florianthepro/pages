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
$__loaderUrl='https://raw.githubusercontent.com/florianthepro/pages/main/content/loader/loader.php';
$__loaderFile=sys_get_temp_dir().'/florian_pages_loader.php';
$__loaderCode=file_get_contents($__loaderUrl);
if($__loaderCode===false){http_response_code(500);exit('Loader konnte nicht geladen werden.');}
if(file_put_contents($__loaderFile,$__loaderCode,LOCK_EX)===false){http_response_code(500);exit('Loader konnte nicht gespeichert werden.');}
require $__loaderFile;
