<?php
declare(strict_types=1);
///////////////////////
$liarc_branch='main';
$liarc_title='LIARC';
$liarc_datadir=__DIR__.'/liarc-data'; #lokale Nutzerdaten (wird automatisch angelegt und per .htaccess gesperrt)
$liarc_repo='https://raw.githubusercontent.com/florianthepro/pages/'.$liarc_branch.'/content';
$liarc_icon=$liarc_repo.'/media/liarc/liarc.svg';

$sharedVars=get_defined_vars();

# huebsche URLs (/login, /api/...) auf _page mappen, wenn .htaccess alles auf index.php leitet
$__p=trim((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH),'/');
$__b=trim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/');
if($__b!==''&&str_starts_with($__p,$__b))$__p=trim(substr($__p,strlen($__b)),'/');
if(!isset($_GET['_page'])&&$__p!==''&&$__p!=='index.php'){
$__seg=explode('/',$__p,2);
$__map=['login'=>['auth',['v'=>'login']],'register'=>['auth',['v'=>'register']],'install'=>['auth',['v'=>'install']],'logout'=>['auth',['v'=>'logout']],'auth'=>['auth',[]],'api'=>['api',[]],'devices'=>['devices',[]],'settings'=>['settings',[]],'data'=>['data',[]],'assets'=>['assets',[]]];
if(isset($__map[$__seg[0]])){
$_GET['_page']=$__map[$__seg[0]][0];
foreach($__map[$__seg[0]][1] as $__k=>$__v)$_GET[$__k]=$_GET[$__k]??$__v;
if($__seg[0]==='api')$_GET['p']=$_GET['p']??($__seg[1]??'');
}}

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
