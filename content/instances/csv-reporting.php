<?php
declare(strict_types=1);
///////////////////////
$csvreporting_dwlextpage = ''; #http
$csvreporting_dwltype='php'; #php, csv (csv direkt/php downloader)
$csvreporting_dwlfilters=[]; //z.B. ['header'=>['value']] – Zeilen mit diesen Werten werden beim Download entfernt
$csvreporting_projectpath=''; #change to "dir" if working dir is like /var/www/html/"dir"/
$csvreporting_title='CSV-REPORTING';
$csvreporting_heading='CSV-REPORTING by Florian';
$csvreporting_csvfile='csv.csv';
$csvreporting_jsonfile='data.json';
$csvreporting_jspnbakdir='/var/www/html/'.$csvreporting_projectpath.'/tmp';
$csvreporting_csvdir='/var/www/html/'.$csvreporting_projectpath.'/'.$csvreporting_csvfile;
$csvreporting_jsondir='/var/www/html/'.$csvreporting_projectpath.'/'.$csvreporting_jsonfile;
$csvreporting_editortitle='Rules Editor';
$csvreporting_editorheading=$csvreporting_heading.' '.$csvreporting_editortitle;
$csvreporting_icon='https://raw.githubusercontent.com/florianthepro/pages/main/content/media/csv-reporting/index.svg';
$csvreporting_editoricon='https://raw.githubusercontent.com/florianthepro/pages/main/content/media/csv-reporting/edit.svg';

$sharedVars=get_defined_vars();

$yaml=<<<'YAML'
license: "https://raw.githubusercontent.com/florianthepro/pages/main/LICENSE"
blocked: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/blocked.html"
index: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/csv-reporting/index.php"
edit: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/csv-reporting/edit.php"
dwlphp: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/csv-reporting/dwlphp.php"
dwlcsv: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/csv-reporting/dwlcsv.php"
map: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/csv-reporting/map.php"
raw: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/csv-reporting/raw.php"
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
