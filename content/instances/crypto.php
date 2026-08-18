<?php
declare(strict_types=1);
///////////////////////
$crypto_title='Crypto Toolkit';
$crypto_theme='auto'; #'light' (fix hell) | 'dark' (fix dunkel) | 'auto' (dynamisch, folgt System)
$crypto_icon='https://raw.githubusercontent.com/florianthepro/pages/main/content/media/crypto/index.svg';

$sharedVars=get_defined_vars();

$yaml=<<<'YAML'
license: "https://raw.githubusercontent.com/florianthepro/pages/main/LICENSE"
blocked: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/blocked.html"
index: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/crypto/index.php"
api: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/crypto/api.php"
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
