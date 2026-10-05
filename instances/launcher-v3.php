<?php
declare(strict_types=1);
///////////////////////
$launcherv3_title='Launcher';
$launcherv3_theme='auto'; #'light' (fix hell, Retro) | 'dark' (fix dunkel) | 'auto' (dynamisch, folgt System)
$launcherv3_icon='https://raw.githubusercontent.com/florianthepro/pages/main/content/media/launcher-v3/index.svg';
#Kacheln - nur hier pflegen. Eine Kachel pro Zeile, Felder mit "|" getrennt, die
#Gruppe steht immer zuerst. Gleiche Gruppennamen werden automatisch zusammengefasst
#(Auto-Zuordnung) - egal wie oft und in welcher Reihenfolge sie vorkommen, es wird
#immer sauber gruppiert:
#  Gruppe | Name | URL
#  Gruppe | Name | URL | Icon   (4. Feld optional: lokaler Pfad ODER Web-URL zum
#                                Icon; ist es gesetzt, ersetzt es das Ziel-Icon)
#Die URL steht FIX im 3. Feld. Fehlt das 3. Feld, entsteht kein Link - so wird z.B.
#ein "?=" im Namen (2. Feld) nie als Adresse missverstanden.
#Ohne 4. Feld holt sich der Launcher das Icon automatisch vom Ziel (Favicon),
#Fallback sind die Initialen. Die klassische Schreibweise (Gruppen-Kopf "Name:"
#und darunter eingerueckt "Name: URL") wird weiterhin verstanden.
$launcherv3_links=<<<'LINKS'
Sites | Buergerabstimmung | https://buergerabstimmung.org/
Sites | MinecraftWebserver | https://ftpcraft.com/
Sites | MinecraftJava | https://ftpcraft:19132/
Sites | MinecraftBadrock | https://ftpcraft:25565/
Sites | ItSecTools | https://getitsec.com/
Sites | Nightclubmap | https://nightclubmap.com/
ItSecTools | IdrAnalyser | https://idr.getitsec.com/
ItSecTools | Gitea | https://gitea.getitsec.com/
ItSecTools | Mail | https://mail.getitsec.com/
ItSecTools | BsfisiManager | bsfisi.getitsec.com
Internal | Gitea | http://localhost:3000/
Internal | HeidySQL+MariaDB | http://localhost:3306/
Internal | StalwartMailserver | http://localhost:8080/
Internal | MinecraftJava | http://localhost:19132/
Internal | MinecraftBadrock | http://localhost:25565/
ToDo | securemessaging.xo.je
ToDo | tools.xo.je/?=ticketerstellen
ToDo | tools.xo.je/?=timecalculator
ToDo | ?=background-remover
ToDo | ?=base64
ToDo | ?=lieferadresse
ToDo | ?=thinkcentre
ToDo | ?=html-creator
ToDo | loockup.xo.je
ToDo | hub.xo.je
ToDo | managment.xo.je
ToDo | dwl.xo.je
LINKS;

$sharedVars=get_defined_vars();

$yaml=<<<'YAML'
license: "https://raw.githubusercontent.com/florianthepro/pages/main/LICENSE"
blocked: "https://raw.githubusercontent.com/florianthepro/pages/main/content/instances/blocked.html"
index: "https://raw.githubusercontent.com/florianthepro/pages/main/content/routes/launcher-v3/index.php"
YAML;
///////////////////////
$__loaderUrl='https://raw.githubusercontent.com/florianthepro/pages/main/content/loader/loader.php';
#Ablage fuer den zwischengespeicherten Loader. Steht open_basedir, liegt das
#Temp-Verzeichnis ausserhalb - dort scheitert schon is_file(). Darum der Reihe
#nach probieren: Temp, das nicht ausgelieferte data-Verzeichnis der Site, zuletzt
#ein eigener Ordner neben der Instanz (der wird dann gegen Abruf gesperrt).
$__loaderName='pages_loader_'.substr(sha1(__DIR__),0,10).'.php';
$__loaderFile='';
foreach([sys_get_temp_dir(),dirname(__DIR__).'/data',__DIR__.'/.pages-cache'] as $__dir){
if(!is_string($__dir)||$__dir===''){continue;}
$__dir=rtrim($__dir,'/\\');
$__cand=$__dir.DIRECTORY_SEPARATOR.$__loaderName;
if(@is_file($__cand)){$__loaderFile=$__cand;break;}
if(!@is_dir($__dir)&&!@mkdir($__dir,0700,true)){continue;}
if(!@is_writable($__dir)){continue;}
if(strpos($__dir,__DIR__)===0){@file_put_contents($__dir.DIRECTORY_SEPARATOR.'.htaccess',"Require all denied\nDeny from all\n");}
$__loaderFile=$__cand;break;
}
if($__loaderFile===''){http_response_code(500);exit('Kein beschreibbarer Zwischenspeicher fuer den Loader (open_basedir?).');}
if(!is_file($__loaderFile)||time()-(int)@filemtime($__loaderFile)>86400){
$__loaderCode=@file_get_contents($__loaderUrl);
if($__loaderCode===false&&function_exists('curl_init')){$__ch=curl_init($__loaderUrl);curl_setopt_array($__ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20]);$__loaderCode=curl_exec($__ch);curl_close($__ch);}
if(is_string($__loaderCode)&&strpos($__loaderCode,'app_run_remote_script')!==false){@file_put_contents($__loaderFile,$__loaderCode,LOCK_EX);@chmod($__loaderFile,0600);}
}
if(!is_file($__loaderFile)){http_response_code(500);exit('Loader nicht erreichbar: '.$__loaderUrl);}
require $__loaderFile;
