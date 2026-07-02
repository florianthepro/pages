<?php
declare(strict_types=1);
///////////////////////
$side = "https://example.com";

$yaml=<<<'YAML'
index: "https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/index.php"
YAML;
///////////////////////
$routeParam='_page';
$refreshParam='_refresh';
$debugParam='_debug';
$defaultPage='index';
$cacheTtl=300;
$allowedHosts=['raw.githubusercontent.com','github.com'];
function app_fail(string $message,array $meta=[]): never{
http_response_code(500);
header('Content-Type: text/plain; charset=utf-8');
echo"Loader-Fehler\n";
echo$message."\n";
foreach($meta as $k=>$v){
echo"\n".$k.":\n".$v."\n";
}
exit;
}
function app_parse_yaml_routes(string $yaml): array{
$routes=[];
$lines=preg_split("/\r\n|\n|\r/",$yaml);
if(!is_array($lines))return$routes;
foreach($lines as $line){
$line=trim($line);
if($line===''||str_starts_with($line,'#'))continue;
if(!preg_match('/^([A-Za-z0-9_-]+)\s*:\s*(.+)$/',$line,$m))app_fail('Ungültige YAML-Zeile.',['line'=>$line]);
$key=$m[1];
$value=trim($m[2]);
if((str_starts_with($value,'"')&&str_ends_with($value,'"'))||(str_starts_with($value,"'")&&str_ends_with($value,"'")))$value=substr($value,1,-1);
if($value==='')app_fail('Leere URL in YAML.',['page'=>$key]);
$routes[$key]=$value;
}
return$routes;
}
function app_is_allowed_host(string $host,array $allowedHosts): bool{
return in_array(strtolower($host),$allowedHosts,true);
}
function app_normalize_github_raw_url(string $url,array $allowedHosts): string{
$url=trim($url);
if($url==='')app_fail('Leere URL.');
$parts=parse_url($url);
if($parts===false||!isset($parts['scheme'],$parts['host'],$parts['path']))app_fail('Ungültige URL.',['url'=>$url]);
$scheme=strtolower((string)$parts['scheme']);
$host=strtolower((string)$parts['host']);
$path=(string)$parts['path'];
if($scheme!=='https')app_fail('Nur HTTPS ist erlaubt.',['url'=>$url]);
if(!app_is_allowed_host($host,$allowedHosts))app_fail('Host nicht erlaubt.',['url'=>$url]);
if($host==='raw.githubusercontent.com')return$url;
if($host==='github.com'){
$segments=array_values(array_filter(explode('/',$path),'strlen'));
if(count($segments)>=5&&($segments[2]==='blob'||$segments[2]==='raw')){
$owner=$segments[0];
$repo=$segments[1];
$ref=$segments[3];
$filePath=implode('/',array_slice($segments,4));
if($owner===''||$repo===''||$ref===''||$filePath==='')app_fail('GitHub-URL unvollständig.',['url'=>$url]);
return'https://raw.githubusercontent.com/'.$owner.'/'.$repo.'/'.$ref.'/'.$filePath;
}
}
app_fail('Nur GitHub-Raw-Datei-URLs werden unterstützt.',['url'=>$url]);
}
function app_fetch_remote_text(string $url,int $timeout=20): string{
if(function_exists('curl_init')){
$ch=curl_init($url);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
curl_setopt($ch,CURLOPT_MAXREDIRS,5);
curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
curl_setopt($ch,CURLOPT_TIMEOUT,$timeout);
curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (PHP GitHub Raw Loader)');
if(defined('CURLPROTO_HTTPS'))curl_setopt($ch,CURLOPT_PROTOCOLS,CURLPROTO_HTTPS);
if(defined('CURLPROTO_HTTPS'))curl_setopt($ch,CURLOPT_REDIR_PROTOCOLS,CURLPROTO_HTTPS);
$data=curl_exec($ch);
$errNo=curl_errno($ch);
$errMsg=curl_error($ch);
$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
curl_close($ch);
if($data===false||$errNo!==0)app_fail('Download fehlgeschlagen.',['url'=>$url,'curl'=>$errMsg.' ('.$errNo.')']);
if($http<200||$http>=300)app_fail('Download fehlgeschlagen.',['url'=>$url,'http'=>(string)$http]);
return(string)$data;
}
$context=stream_context_create([
'http'=>['timeout'=>$timeout,'follow_location'=>1,'max_redirects'=>5,'user_agent'=>'Mozilla/5.0 (PHP GitHub Raw Loader)','ignore_errors'=>true],
'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]
]);
$data=@file_get_contents($url,false,$context);
if($data===false)app_fail('Download fehlgeschlagen.',['url'=>$url]);
return(string)$data;
}
function app_cache_dir(): string{
$dir=rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'gh-raw-loader';
if(!is_dir($dir))@mkdir($dir,0700,true);
return$dir;
}
function app_cached_script_path(string $url): string{
$path=(string)(parse_url($url,PHP_URL_PATH)??'');
$ext=pathinfo($path,PATHINFO_EXTENSION);
$ext=$ext!==''?'.'.$ext:'.php';
return app_cache_dir().DIRECTORY_SEPARATOR.sha1($url).$ext;
}
function app_get_local_script(string $url,bool $refresh,int $cacheTtl): string{
$file=app_cached_script_path($url);
$stale=!is_file($file)||(time()-@filemtime($file)>$cacheTtl);
if($refresh||$stale){
$content=app_fetch_remote_text($url);
$tmp=$file.'.tmp';
if(@file_put_contents($tmp,$content,LOCK_EX)===false)app_fail('Cache-Datei konnte nicht geschrieben werden.',['file'=>$tmp]);
@chmod($tmp,0600);
if(!@rename($tmp,$file)){
@unlink($tmp);
app_fail('Cache-Datei konnte nicht ersetzt werden.',['file'=>$file]);
}
}
return$file;
}
function app_detect_short_open_tags(string $content): bool{
return preg_match('/<\?(?!php|=|xml)/i',$content)===1;
}
function app_lint_file(string $file): array{
if(!defined('PHP_BINARY')||PHP_BINARY==='')return['ok'=>null,'output'=>'PHP_BINARY nicht verfügbar'];
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1',$out,$code);
$output=implode("\n",$out);
if($output==='')$output='Kein Lint-Output';
return['ok'=>$code===0,'output'=>$output];
}
function app_file_excerpt(string $file,int $lines=40): string{
$data=@file($file,FILE_IGNORE_NEW_LINES);
if(!is_array($data))return'Datei konnte nicht gelesen werden.';
$count=count($data);
$start=max(0,$count-$lines);
$chunk=array_slice($data,$start);
$out=[];
foreach($chunk as $i=>$line){
$out[]=str_pad((string)($start+$i+1),5,' ',STR_PAD_LEFT).': '.$line;
}
return implode("\n",$out);
}
function app_run_remote_script(string $file,array $sharedVars,array $reservedKeys): never{
$cwd=getcwd();
$dir=dirname($file);
if($cwd!==false)@chdir($dir);
foreach($reservedKeys as $key){
unset($_GET[$key],$_REQUEST[$key]);
}
(static function(string $__file,array $__sharedVars): void{
extract($__sharedVars,EXTR_SKIP);
require $__file;
})($file,$sharedVars);
if($cwd!==false)@chdir($cwd);
exit;
}
$routes=app_parse_yaml_routes($yaml);
if(!isset($routes[$defaultPage]))app_fail('In YAML fehlt die Standardseite "index".');
$page=$_GET[$routeParam]??$defaultPage;
if(!is_string($page)||!preg_match('/^[A-Za-z0-9_-]+$/',$page)||!isset($routes[$page]))$page=$defaultPage;
$refresh=isset($_GET[$refreshParam])&&$_GET[$refreshParam]==='1';
$debug=isset($_GET[$debugParam])&&$_GET[$debugParam]==='1';
$rawUrl=app_normalize_github_raw_url((string)$routes[$page],$allowedHosts);
$localFile=app_get_local_script($rawUrl,$refresh,$cacheTtl);
$content=@file_get_contents($localFile);
if(!is_string($content))app_fail('Gecachte Datei konnte nicht gelesen werden.',['page'=>$page,'url'=>$rawUrl,'file'=>$localFile]);
if(app_detect_short_open_tags($content))app_fail('Das geladene Script verwendet kurze PHP-Tags "<?". Ersetze sie durch "<?php".',['page'=>$page,'url'=>$rawUrl,'file'=>$localFile,'tail'=>app_file_excerpt($localFile,60)]);
$lint=app_lint_file($localFile);
if($lint['ok']===false)app_fail('Syntaxfehler im geladenen Script.',['page'=>$page,'url'=>$rawUrl,'file'=>$localFile,'lint'=>$lint['output'],'tail'=>app_file_excerpt($localFile,80)]);
if($debug){
header('Content-Type: text/plain; charset=utf-8');
echo"Debug\n";
echo"page: ".$page."\n";
echo"url: ".$rawUrl."\n";
echo"file: ".$localFile."\n";
echo"sharedVars:\n";
var_export($sharedVars);
echo"\n\nlint:\n".$lint['output']."\n";
exit;
}
app_run_remote_script($localFile,$sharedVars,[$routeParam,$refreshParam,$debugParam]);
