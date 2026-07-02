<?php
declare(strict_types=1);
///////////////////////
$side = "https://example.com";

$yaml=<<<'YAML'
index: "https://raw.githubusercontent.com/florianthepro/pages/main/iframe-site-viewer/index.php"
YAML;
///////////////////////
$__loaderUrl='https://raw.githubusercontent.com/florianthepro/pages/main/content/loader.php';
$__loaderFile=sys_get_temp_dir().'/florian_pages_loader.php';
$__loaderCode=file_get_contents($__loaderUrl);
if($__loaderCode===false){http_response_code(500);exit('Loader konnte nicht geladen werden.');}
if(file_put_contents($__loaderFile,$__loaderCode,LOCK_EX)===false){http_response_code(500);exit('Loader konnte nicht gespeichert werden.');}
require $__loaderFile;
