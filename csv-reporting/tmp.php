<?php
declare(strict_types=1);
///////////////////////

//general
$reporting_dir='otrs';
$CSV_FILE='csv.csv';
$JSON_FILE='data.json';
$title='Ticket Quality';
$heading='Ticket Quality Assurance Inspection System';

//dwl.php
$dwl_type='dwl-php'; #dwl-php, dwl-csv
$remoteUrl = 'http://10.104.17.42/reporting/operationticketquality.php'; #http
$removeFilters=['state'=>['merged'],];

//advanced
$backup_dir='/var/www/html/'.$reporting_dir.'/tmp';
$csvFile='/var/www/html/'.$reporting_dir.'/'.$CSV_FILE;
$rulesFile='/var/www/html/'.$reporting_dir.'/'.$JSON_FILE;
$webdwlpath='http://127.0.0.1/'.$reporting_dir.'/index.php?_page='.$dwl_type;

///////////////////////
$sharedVars=get_defined_vars();
$yaml=<<<'YAML'
index: "https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/index.php"
JNZACXVTeBiAgksrQYGoBHcRtZJfPpLqgaLfoFxMCAGAMEaftGftxJDCDNqCTfEsgGxmmZjxoDAYbTmUtkzEKZZxHpcqaWRGLcEJJNZACXVTeBiAgksrQYGoBHcRtZJfPpLqgaLfoFxMCAGAMEaftGftxJDCDNqCTfEsgGxmmZjxoDAYbTmUtkzEKZZxHpcqaWRGLcEJJNZACXVTeBiAgksrQYGoBHcRtZJfPpLqgaLfoFxMCAGAMEaftGftxJDCDNqCTfEsgGxmmZjxoDAYbTmUtkzEKZZxHpcqaWRGLcEJ: "https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/edit.php"
dwl-php: "https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/dwl-php.php"
dwl-csv: "https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/dwl-csv.php"
map: "https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/map.php"
raw: "https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/raw.php"
edit: "https://raw.githubusercontent.com/florianthepro/pages/main/blocked/index.html"
license: "https://raw.githubusercontent.com/florianthepro/pages/main/LICENSE"
YAML;
