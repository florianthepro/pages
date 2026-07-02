<?php
declare(strict_types=1);
///////////////////////
$csvreporting_dwlextpage = 'http://10.104.17.42/reporting/operationticketquality.php'; #http
$csvreporting_dwltype='php'; #php, csv (csv direkt/php downloader)
$csvreporting_dwlfilters=['state'=>['merged'],];
$csvreporting_projectpath='otrs'; #change to "dir" if working dir is like /var/www/html/"dir"/
$csvreporting_title='Ticket Quality';
$csvreporting_heading='Ticket Quality Assurance Inspection System';
$csvreporting_csvfile='csv.csv';
$csvreporting_jsonfile='data.json';
$csvreporting_jspnbakdir='/var/www/html/'.$csvreporting_projectpath.'/tmp';
$csvreporting_csvdir='/var/www/html/'.$csvreporting_projectpath.'/'.$csvreporting_csvfile;
$csvreporting_jsondir='/var/www/html/'.$csvreporting_projectpath.'/'.$csvreporting_jsonfile;
$csvreporting_editorheading=$csvreporting_heading.' '.$csvreporting_editortitle;
$csvreporting_editortitle='Rules Editor';

$sharedVars=get_defined_vars();

$yaml=<<<'YAML'
license: "https://raw.githubusercontent.com/florianthepro/pages/main/LICENSE"
blocked: "https://raw.githubusercontent.com/florianthepro/pages/main/blocked/index.html"
index: "https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/index.php"
edit: "https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/edit.php"
dwlphp: "https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/dwlphp.php"
dwlcsv: "https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/dwlcsv.php"
map: "https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/map.php"
raw: "https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/raw.php"
YAML;
///////////////////////
$__loaderUrl='https://raw.githubusercontent.com/florianthepro/pages/main/content/loader.php';
$__loaderFile=sys_get_temp_dir().'/florian_pages_loader.php';
$__loaderCode=file_get_contents($__loaderUrl);
if($__loaderCode===false){http_response_code(500);exit('Loader konnte nicht geladen werden.');}
if(file_put_contents($__loaderFile,$__loaderCode,LOCK_EX)===false){http_response_code(500);exit('Loader konnte nicht gespeichert werden.');}
require $__loaderFile;
