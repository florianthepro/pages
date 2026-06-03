<?php
declare(strict_types=1);
$postField='downloadBtn';
$postValue='1';
$timeout=20;
$ua='Mozilla/5.0 (CSV-Downloader/2.4)';
$dir=dirname($$csvreporting_csvdir);
if(!is_dir($dir)||!is_writable($dir)){http_response_code(500);echo"Fehler: Zielverzeichnis nicht beschreibbar: {$dir}\n";exit;}
$rawTmp=tempnam($dir,'csv_raw_');
$outTmp=tempnam($dir,'csv_out_');
if($rawTmp===false||$outTmp===false){http_response_code(500);echo"Fehler: temporäre Datei konnte nicht erstellt werden\n";exit;}
$rawFp=fopen($rawTmp,'wb');
if($rawFp===false){@unlink($rawTmp);@unlink($outTmp);http_response_code(500);echo"Fehler: temporäre Datei nicht schreibbar\n";exit;}
$ch=curl_init();
curl_setopt($ch,CURLOPT_URL,$csvreporting_dwlextpage);
curl_setopt($ch,CURLOPT_POST,true);
curl_setopt($ch,CURLOPT_POSTFIELDS,[$postField=>$postValue]);
curl_setopt($ch,CURLOPT_FILE,$rawFp);
curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
curl_setopt($ch,CURLOPT_MAXREDIRS,5);
curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
curl_setopt($ch,CURLOPT_TIMEOUT,$timeout);
curl_setopt($ch,CURLOPT_USERAGENT,$ua);
$ok=curl_exec($ch);
$errNo=curl_errno($ch);
$errMsg=curl_error($ch);
$httpCode=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
$contentType=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);
curl_close($ch);
fclose($rawFp);
if($ok===false||$errNo!==0){@unlink($rawTmp);@unlink($outTmp);http_response_code(502);echo"Fehler beim Abrufen: cURL ({$errNo}) {$errMsg}\n";exit;}
if($httpCode<200||$httpCode>=300){@unlink($rawTmp);@unlink($outTmp);http_response_code(502);echo"Fehler: Remote returned HTTP {$httpCode}\n";exit;}
if(!is_file($rawTmp)||filesize($rawTmp)===0){@unlink($rawTmp);@unlink($outTmp);http_response_code(502);echo"Fehler: Leere Antwort vom Remote\n";exit;}
$normalize=function(string $s):string{$s=preg_replace('/^\xEF\xBB\xBF/','',$s);$s=str_replace(["\xC2\xA0","\xE2\x80\x8B","\xE2\x80\x8C","\xE2\x80\x8D","\xEF\xBB\xBF"],' ',$s);$s=preg_replace('/\s+/u',' ',(string)$s);return trim((string)$s);};
$lower=function(string $s) use($normalize):string{$s=$normalize($s);return function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower($s);};
$detectDelimiter=function(string $path):string{$fp=fopen($path,'rb');if(!$fp)return',';$sample='';for($i=0;$i<60&&!feof($fp);$i++){$line=fgets($fp);if($line===false)break;if(trim($line)==='')continue;$sample.=$line;if(strlen($sample)>16384)break;}fclose($fp);$sample=preg_replace('/^\xEF\xBB\xBF/','',(string)$sample);$tab=substr_count($sample,"\t");$semi=substr_count($sample,';');$comma=substr_count($sample,',');if($tab>$semi&&$tab>$comma)return"\t";if($semi>$comma)return';';return',';};
$delimiter=$detectDelimiter($rawTmp);
$in=new SplFileObject($rawTmp,'rb');
$in->setFlags(SplFileObject::READ_CSV|SplFileObject::DROP_NEW_LINE);
$in->setCsvControl($delimiter,'"',"\\");
$out=fopen($outTmp,'wb');
if($out===false){@unlink($rawTmp);@unlink($outTmp);http_response_code(500);echo"Fehler beim Schreiben der temporären Datei\n";exit;}
$header=null;
while(!$in->eof()){
$row=$in->fgetcsv();
if($row===false)break;
if($row===[null]||$row===null)continue;
if(count($row)===1&&$normalize((string)$row[0])==='')continue;
$header=$row;
break;
}
if($header===null){fclose($out);@unlink($rawTmp);@unlink($outTmp);http_response_code(502);echo"Fehler: CSV Header nicht lesbar\n";exit;}
$headerCount=count($header);
$colIndex=[];
for($i=0;$i<$headerCount;$i++){
$name=$lower((string)$header[$i]);
if($name!==''&&!isset($colIndex[$name]))$colIndex[$name]=$i;
}
$rules=[];
foreach($csvreporting_dwlfilters as $col=>$vals){
$colKey=$lower((string)$col);
if($colKey==='')continue;
$idx=$colIndex[$colKey]??null;
$set=[];
foreach((array)$vals as $v){$vk=$lower((string)$v);if($vk!=='')$set[$vk]=true;}
$rules[]=['label'=>(string)$col,'index'=>$idx,'set'=>$set];
}
fputcsv($out,$header,$delimiter,'"',"\\");
$kept=0;
$removed=0;
$removedBy=[];
foreach($rules as $r){$removedBy[$r['label']]=0;}
while(!$in->eof()){
$row=$in->fgetcsv();
if($row===false)break;
if($row===[null]||$row===null)continue;
if(count($row)===1&&$normalize((string)$row[0])==='')continue;
if(count($row)<$headerCount)$row=array_pad($row,$headerCount,'');
$drop=false;
$hit=null;
for($k=0;$k<count($rules);$k++){
$idx=$rules[$k]['index'];
if($idx===null)continue;
$val=$lower((string)($row[$idx]??''));
if($val!==''&&isset($rules[$k]['set'][$val])){$drop=true;$hit=$rules[$k]['label'];break;}
}
if($drop){$removed++;if($hit!==null)$removedBy[$hit]++;continue;}
fputcsv($out,$row,$delimiter,'"',"\\");
$kept++;
}
fclose($out);
$isCsv=false;
if($contentType!==''&&stripos($contentType,'csv')!==false)$isCsv=true;
if(!$isCsv){
$fp=fopen($outTmp,'rb');
$head=$fp?fread($fp,512):'';
if($fp)fclose($fp);
$head=preg_replace('/^\xEF\xBB\xBF/','',(string)$head);
if(preg_match('/^.+([,;\t]).+\1/m',(string)$head))$isCsv=true;
}
if(!$isCsv||filesize($outTmp)===0){@unlink($rawTmp);@unlink($outTmp);http_response_code(502);echo"Fehler: Unerwarteter Inhalt (kein CSV). Content-Type: {$contentType}\n";exit;}
@chmod($outTmp,0644);
@unlink($rawTmp);
if(file_exists($$csvreporting_csvdir))@unlink($$csvreporting_csvdir);
if(!@rename($outTmp,$$csvreporting_csvdir)){
if(!@copy($outTmp,$$csvreporting_csvdir)){@unlink($outTmp);http_response_code(500);echo"Fehler beim Verschieben der Datei nach {$$csvreporting_csvdir}\n";exit;}
@unlink($outTmp);
}
$log=$dir.'/update_log.txt';
$parts=[];
foreach($removedBy as $c=>$n){$parts[]=$c.'='.$n;}
file_put_contents($log,date('Y-m-d H:i:s')." - CSV aktualisiert (entfernt={$removed}, behalten={$kept}, byCol: ".implode(', ',$parts).")\n",FILE_APPEND|LOCK_EX);
echo"<h3>CSV aktualisiert (entfernt: {$removed})</h3>";
