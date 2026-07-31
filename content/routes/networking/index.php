<?php
if(!preg_match('~^([/\\\\]|[A-Za-z]:)~',(string)$networking_jsondir)){
$__base=isset($appBaseDir)&&is_string($appBaseDir)&&$appBaseDir!==''?$appBaseDir:((string)($_SERVER['SCRIPT_FILENAME']??'')!==''?dirname((string)$_SERVER['SCRIPT_FILENAME']):(string)getcwd());
$networking_jsondir=rtrim($__base,'/\\').DIRECTORY_SEPARATOR.$networking_jsondir;
}
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function defaultConfig(){
return[
'typeIcons'=>[],
'serviceLabels'=>[
'BGP'=>'BGP Routing',
'OSPF'=>'OSPF Routing',
'Static-Routing'=>'Statische Route',
'IPsec-VPN'=>'VPN (IPsec)',
'VPN'=>'VPN',
'NTP'=>'Zeitdienst (NTP)',
'SYSLOG'=>'Syslog',
'SNMP'=>'Monitoring (SNMP)',
'HTTPS'=>'HTTPS',
'HTTPS-Admin'=>'HTTPS (Admin)',
'HTTP'=>'HTTP',
'HTTP-Proxy'=>'HTTP Proxy',
'SSH'=>'SSH',
'DNS'=>'DNS',
'SMTP'=>'SMTP',
'RDP'=>'RDP',
'iSCSI'=>'Storage (iSCSI)',
'NFS'=>'Storage (NFS)',
'Backup-Proxy'=>'Backup Proxy',
'Backup-Agent'=>'Backup Agent',
'LDAP'=>'LDAP/AD',
'RADIUS'=>'RADIUS/MFA',
'MSSQL'=>'SQL Server',
'ORACLE-DB'=>'Oracle Datenbank',
'SMB'=>'Dateifreigaben (SMB)',
'CAPWAP'=>'CAPWAP/WLAN',
'DEFAULT'=>'Verbindung'
],
'connectionStyles'=>[
'DEFAULT'=>['color'=>'#666666']
],
'vlanGroups'=>[],
'groupStyles'=>[
'Unbekannt'=>['fill'=>'#ffffff','stroke'=>'#d1d5db']
],
'typeStyles'=>[],
'devices'=>[]
];
}
function normalizeConfig($cfg){
$base=defaultConfig();
if(isset($cfg['typeIcons'])&&is_array($cfg['typeIcons']))$base['typeIcons']=array_merge($base['typeIcons'],$cfg['typeIcons']);
if(isset($cfg['serviceLabels'])&&is_array($cfg['serviceLabels']))$base['serviceLabels']=array_merge($base['serviceLabels'],$cfg['serviceLabels']);
if(isset($cfg['connectionStyles'])&&is_array($cfg['connectionStyles']))$base['connectionStyles']=array_merge($base['connectionStyles'],$cfg['connectionStyles']);
if(isset($cfg['vlanGroups'])&&is_array($cfg['vlanGroups']))$base['vlanGroups']=array_merge($base['vlanGroups'],$cfg['vlanGroups']);
if(isset($cfg['groupStyles'])&&is_array($cfg['groupStyles']))$base['groupStyles']=array_merge($base['groupStyles'],$cfg['groupStyles']);
if(isset($cfg['typeStyles'])&&is_array($cfg['typeStyles']))$base['typeStyles']=array_merge($base['typeStyles'],$cfg['typeStyles']);
if(isset($cfg['devices'])&&is_array($cfg['devices']))$base['devices']=array_values($cfg['devices']);
if(!isset($base['connectionStyles']['DEFAULT']['color']))$base['connectionStyles']['DEFAULT']=['color'=>'#666666'];
if(!isset($base['groupStyles']['Unbekannt'])||!is_array($base['groupStyles']['Unbekannt']))$base['groupStyles']['Unbekannt']=['fill'=>'#ffffff','stroke'=>'#d1d5db'];
return $base;
}
function nw_ip_in_cidr($ip,$cidr){
$p=explode('/',(string)$cidr);
if(count($p)!==2)return false;
$bits=(int)$p[1];
$base=ip2long($p[0]);
$val=ip2long((string)$ip);
if($base===false||$val===false||$bits<0||$bits>32)return false;
if($bits===0)return true;
$mask=$bits===32?0xFFFFFFFF:(~((1<<(32-$bits))-1))&0xFFFFFFFF;
return(($base&$mask)===($val&$mask));
}
function nw_ip_covered($ip,$vlanGroups){
foreach($vlanGroups as $k=>$label){
$k=(string)$k;
if($k==='')continue;
if(strpos($k,'/')!==false){if(nw_ip_in_cidr($ip,$k))return true;}
elseif(strpos($ip,$k)===0)return true;
}
return false;
}
function applyDevicesDerivedDefaults(&$cfg){
foreach($cfg['devices'] as $item){
if(!is_array($item))continue;
$ip=(string)($item['IP']??$item['ip']??'');
if($ip!==''){
$parts=explode('.',$ip);
if(count($parts)===4){
$valid=true;
for($i=0;$i<4;$i++){if(!ctype_digit($parts[$i])||(int)$parts[$i]>255){$valid=false;break;}}
if($valid&&!nw_ip_covered($ip,$cfg['vlanGroups'])){
$prefix=$parts[0].'.'.$parts[1].'.'.$parts[2].'.';
if(!array_key_exists($prefix,$cfg['vlanGroups']))$cfg['vlanGroups'][$prefix]=$prefix.'0/24';
}
}
}
}
foreach($cfg['vlanGroups'] as $prefix=>$label){
if(!array_key_exists($label,$cfg['groupStyles']))$cfg['groupStyles'][$label]=['fill'=>'#f3f4f6','stroke'=>'#d1d5db'];
}
}
function load_config($path){
if(is_readable($path)){
$raw=@file_get_contents($path);
$tmp=@json_decode((string)$raw,true);
if(is_array($tmp)){$cfg=normalizeConfig($tmp);applyDevicesDerivedDefaults($cfg);return $cfg;}
}
$cfg=defaultConfig();
applyDevicesDerivedDefaults($cfg);
return $cfg;
}
function save_config($path,$cfg){
$cfg=normalizeConfig($cfg);
applyDevicesDerivedDefaults($cfg);
foreach($cfg['devices'] as &$d){
if(!is_array($d))continue;
if(isset($d['Connections'])&&is_array($d['Connections'])){
foreach($d['Connections'] as &$c){if(is_array($c))unset($c['direction']);}
unset($c);
}
}
unset($d);
$json=json_encode($cfg,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
if($json===false)return false;
$dir=dirname($path);
if(!is_dir($dir))@mkdir($dir,0775,true);
if(is_readable($path))@copy($path,$path.'.bak');
return @file_put_contents($path,$json,LOCK_EX)!==false;
}
session_start();
if(empty($_SESSION['networking_csrf']))$_SESSION['networking_csrf']=bin2hex(random_bytes(16));
$csrfToken=$_SESSION['networking_csrf'];
$save_msg='';
$save_err='';
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='save'){
$token=$_POST['csrf']??'';
if(!hash_equals($csrfToken,(string)$token))$save_err='Ungültiges Sicherheits-Token.';
else{
$decoded=json_decode((string)($_POST['config_json']??''),true);
if(!is_array($decoded))$save_err='Übergebene Konfiguration ist kein gültiges JSON-Objekt.';
else{
if(save_config($networking_jsondir,$decoded))$save_msg='Gespeichert.';
else $save_err='Fehler beim Speichern: '.$networking_jsondir;
}
}
}
$config=load_config($networking_jsondir);
$configError=null;
if(!is_readable($networking_jsondir))$configError='Keine config.json gefunden – Standard geladen. Mit „+ Gerät" starten und speichern.';
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="color-scheme" content="only light">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?=$networking_icon?>">
<title><?=$networking_title?></title>
<style>
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#eef1f5;color:#111827;overflow:hidden}
#appShell{display:flex;flex-direction:column;height:100vh;width:100vw}
#topBar{flex:0 0 auto;display:flex;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid #d1d5db;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.06);min-height:36px;flex-wrap:wrap}
#topLinks{display:flex;gap:10px;font-size:12px}
#topLinks a{color:#6b7280;text-decoration:none}
#topLinks a:hover{color:#0070ff;text-decoration:underline}
button{font-family:inherit}
.btnPrimary{border-radius:6px;border:1px solid #0070ff;background:#0070ff;color:#fff;font-size:13px;padding:7px 14px;cursor:pointer;white-space:nowrap}
.btnPrimary:hover{background:#0050c0}
.btnPrimary.dirtyState{background:#c2410c;border-color:#c2410c}
.btnGhost{border-radius:6px;border:1px solid #c6cbd3;background:#fff;color:#111827;font-size:13px;padding:6px 12px;cursor:pointer;white-space:nowrap}
.btnGhost:hover{background:#eef1f5;border-color:#9ca3af}
.btnDanger{border-radius:6px;border:1px solid #dc2626;background:#fff;color:#dc2626;font-size:13px;padding:6px 12px;cursor:pointer;white-space:nowrap}
.btnDanger:hover{background:#fee2e2}
.btnSmall{font-size:11px;padding:4px 8px}
.notice{position:fixed;top:60px;left:50%;transform:translateX(-50%);z-index:40;font-size:13px;padding:8px 14px;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.notice.ok{background:#dcfce7;border:1px solid #16a34a;color:#14532d}
.notice.err{background:#fee2e2;border:1px solid #dc2626;color:#7f1d1d}
#connectBanner{position:fixed;top:60px;left:50%;transform:translateX(-50%);z-index:40;display:none;align-items:center;gap:10px;font-size:13px;font-weight:bold;color:#7c2d12;background:#ffedd5;border:1px solid #ea580c;padding:8px 14px;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.modalBackdrop{position:fixed;inset:0;background:rgba(17,24,39,.55);display:none;align-items:center;justify-content:center;z-index:1000;padding:12px;box-sizing:border-box}
.modalPanel{background:#fff;border-radius:10px;border:1px solid #d1d5db;box-shadow:0 12px 40px rgba(0,0,0,.25);max-width:640px;width:100%;max-height:92vh;display:flex;flex-direction:column}
.modalHeader{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid #e5e7eb}
.modalTitle{font-size:16px;font-weight:bold}
.modalBody{padding:12px 14px;overflow:auto}
.modalFooter{display:flex;justify-content:space-between;gap:8px;padding:12px 14px;border-top:1px solid #e5e7eb}
.formRow{display:flex;gap:10px;margin-bottom:10px}
.formCol{flex:1}
label{display:block;font-size:12px;font-weight:bold;margin-bottom:4px;color:#374151}
input[type="text"],input[type="number"],textarea,select{width:100%;box-sizing:border-box;border-radius:6px;border:1px solid #c6cbd3;font-size:13px;padding:7px 8px;font-family:inherit;background:#fff;color:#111827}
input[type="text"]:focus,input[type="number"]:focus,textarea:focus,select:focus{border-color:#0070ff;outline:none;box-shadow:0 0 0 2px rgba(0,112,255,.15)}
textarea{min-height:64px;resize:vertical}
.typeRow{display:flex;gap:8px;align-items:center}
.typeRow select{flex:1}
#devTypeIcon{width:30px;height:30px;border-radius:6px;border:1px solid #e5e7eb;background:#f9fafb;flex:0 0 auto}
#connModalInfo{font-size:13px;color:#374151;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;margin-bottom:10px}
#overlayActions{display:flex;gap:6px;margin:6px 0}
#topBarTitle{font-size:15px;font-weight:bold;white-space:nowrap}
#searchWrapper{position:relative;flex:1;max-width:520px}
#searchInput{width:100%;box-sizing:border-box;border-radius:999px;border:1px solid #c6cbd3;background:#f9fafb;color:#111827;font-size:13px;padding:6px 28px 6px 28px;outline:none}
#searchInput:focus{border-color:#0070ff;background:#fff;box-shadow:0 0 0 2px rgba(0,112,255,.15)}
#searchIcon{position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:12px;color:#6b7280}
#searchMeta{font-size:12px;color:#4b5563;white-space:nowrap}
#main{flex:1 1 auto;display:flex;min-height:0;min-width:0}
#mapArea{flex:1 1 auto;position:relative;min-width:0;min-height:0}
#mapShell{position:absolute;inset:0}
#mapSvg{width:100%;height:100%;display:block;background-color:#f6f8fb;background-image:radial-gradient(#d8dee9 1px,transparent 1px);background-size:22px 22px;cursor:grab}
#mapSvg:active{cursor:grabbing}
.error{position:fixed;left:60px;top:60px;z-index:30;color:#900;font-weight:bold;background:#fff;border:1px solid #dc2626;border-radius:6px;padding:8px 10px;font-size:12px;box-shadow:0 2px 8px rgba(0,0,0,.12)}
#detailOverlay{position:fixed;top:62px;right:10px;width:340px;max-height:64vh;z-index:20;background:#fff;border-radius:8px;border:1px solid #d1d5db;box-shadow:0 8px 24px rgba(0,0,0,.18);padding:8px 10px;font-size:11px;color:#111827;display:none;overflow:auto}
#detailOverlayHeader{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px}
#detailOverlayTitle{font-weight:bold;font-size:12px}
#detailOverlaySubtitle{font-size:10px;color:#4b5563;margin-top:1px}
#detailOverlayClose{border:none;background:none;color:#6b7280;font-size:16px;cursor:pointer;padding:0 4px}
#detailBadges{display:flex;flex-wrap:wrap;gap:4px;margin:4px 0}
.badge{display:inline-flex;align-items:center;gap:4px;border-radius:3px;padding:1px 6px;font-size:10px;border:1px solid #d1d5db;color:#111827;background:#fff}
.badgeDot{width:7px;height:7px;border-radius:999px;background:#6b7280}
.sectionTitle{font-size:11px;font-weight:bold;text-transform:uppercase;letter-spacing:.06em;color:#4b5563;margin:6px 0 4px 0}
.infoRow{display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px}
.infoRow span:nth-child(1){color:#4b5563;margin-right:8px;white-space:nowrap}
.infoRow span:nth-child(2){color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.noteBox{font-size:11px;color:#111827;background:#f9fafb;border-radius:3px;border:1px solid #d1d5db;padding:5px 6px;margin-top:4px;white-space:pre-wrap;word-break:break-word}
.noteTitle{font-size:11px;font-weight:bold;margin-bottom:3px}
.connectionItem{border-radius:3px;border:1px solid #d1d5db;padding:5px 6px;margin-bottom:4px;cursor:pointer;background:#fff;display:flex;flex-direction:column;gap:3px}
.connectionItem:hover{background:#e5e7eb}
.connectionHeader{display:flex;align-items:center;justify-content:space-between;gap:6px}
.connectionType{font-size:11px;font-weight:bold;color:#111827}
.connectionTarget{font-size:11px;color:#4b5563}
.connectionMeta{font-size:10px;color:#4b5563}
.connectionBadge{display:inline-flex;align-items:center;gap:4px;font-size:10px;border-radius:3px;padding:1px 5px;border:1px solid #d1d5db;background:#fff}
.connectionColorDot{width:7px;height:7px;border-radius:999px;background:#6b7280}
.deviceIconWrapper{width:26px;height:26px;border-radius:3px;background:#f9fafb;border:1px solid #d1d5db;display:flex;align-items:center;justify-content:center;overflow:hidden;margin-right:8px}
.deviceIcon{max-width:22px;max-height:22px;display:block}
#deviceHeaderRow{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.deviceHostname{font-size:13px;font-weight:bold}
.deviceIp{font-size:11px;color:#4b5563}
.deviceType{font-size:11px;color:#6b7280}
.nodeLabel{font-size:10px;font-family:Arial,Helvetica,sans-serif;font-weight:bold;fill:#111827;paint-order:stroke;stroke:#f6f8fb;stroke-width:3px;stroke-linejoin:round}
.nodeLabelIp{font-size:8.5px;fill:#4b5563;paint-order:stroke;stroke:#f6f8fb;stroke-width:3px;stroke-linejoin:round}
.nodeGroupLabel{font-size:11px;font-weight:bold;fill:#ffffff}
.nodeCircle{cursor:pointer}
.nodeCircle:hover{stroke:#0070ff;stroke-width:1.6}
.groupRect{fill:#fff;stroke:#d1d5db;stroke-width:1}
@media(max-width:720px){#detailOverlay{left:8px;right:8px;width:auto;max-height:60vh}}
</style>
</head>
<body>
<div id="appShell">
<div id="topBar">
<div id="topBarTitle"><?=$networking_heading?></div>
<div id="searchWrapper">
<div id="searchIcon">🔍</div>
<input id="searchInput" type="search" autocomplete="off" placeholder="Suche: Hostname, IP, Typ, VLAN, Ziel, Notiz">
</div>
<div id="searchMeta"><span id="searchCountDevices">0</span> Geräte · <span id="searchCountConns">0</span> Verbindungen</div>
<button type="button" class="btnGhost" id="btnAddDevice">+ Gerät</button>
<button type="button" class="btnPrimary" id="btnSave">Speichern</button>
<div id="topLinks">
<a href="?_page=raw" target="_blank" rel="noopener noreferrer">config.json</a>
<a href="?_page=license" target="_blank" rel="noopener noreferrer">LICENSE</a>
</div>
</div>
<div id="main">
<div id="mapArea">
<div id="mapShell">
<svg id="mapSvg"><g id="mapContent"></g></svg>
</div>
</div>
</div>
</div>
<?php if($configError):?><div class="error"><?php echo h($configError);?></div><?php endif;?>
<?php if($save_msg!==''):?><div class="notice ok" id="saveNotice"><?php echo h($save_msg);?></div><?php endif;?>
<?php if($save_err!==''):?><div class="notice err" id="saveNotice"><?php echo h($save_err);?></div><?php endif;?>
<div id="connectBanner">Verbindungsmodus: Ziel-Gerät anklicken <button type="button" class="btnGhost" id="btnConnectCancel">Abbrechen (ESC)</button></div>
<form id="saveForm" method="post" autocomplete="off">
<input type="hidden" name="action" value="save">
<input type="hidden" name="csrf" value="<?php echo h($csrfToken);?>">
<input type="hidden" name="config_json" id="config_json">
</form>
<div class="modalBackdrop" id="deviceModal">
<div class="modalPanel">
<div class="modalHeader">
<div class="modalTitle" id="deviceModalTitle"></div>
<button type="button" class="btnGhost" id="btnDeviceClose">Schließen</button>
</div>
<div class="modalBody">
<div class="formRow">
<div class="formCol">
<label for="devHostname">Hostname</label>
<input type="text" id="devHostname">
</div>
<div class="formCol">
<label for="devIP">IP-Adresse</label>
<input type="text" id="devIP" placeholder="z.B. 10.0.1.10">
</div>
</div>
<div class="formRow">
<div class="formCol">
<label for="devType">Typ</label>
<div class="typeRow">
<img id="devTypeIcon" alt="" src="">
<select id="devType"></select>
</div>
</div>
<div class="formCol">
<label for="devKind">Art</label>
<select id="devKind">
<option value="Physisch">Physisch</option>
<option value="VM">VM</option>
<option value="Extern">Extern</option>
</select>
</div>
</div>
<div class="formRow">
<div class="formCol">
<label for="devNotes">Notizen</label>
<textarea id="devNotes"></textarea>
</div>
</div>
</div>
<div class="modalFooter">
<button type="button" class="btnDanger" id="btnDeviceDelete">Gerät löschen</button>
<button type="button" class="btnPrimary" id="btnDeviceApply">Übernehmen</button>
</div>
</div>
</div>
<div class="modalBackdrop" id="connModal">
<div class="modalPanel">
<div class="modalHeader">
<div class="modalTitle">Verbindung erstellen</div>
<button type="button" class="btnGhost" id="btnConnClose">Schließen</button>
</div>
<div class="modalBody">
<div id="connModalInfo"></div>
<div class="formRow">
<div class="formCol">
<label for="connService">Dienst</label>
<select id="connService"></select>
</div>
<div class="formCol">
<label for="connPort">Port (optional)</label>
<input type="number" id="connPort" min="1" max="65535" placeholder="443">
</div>
</div>
<div class="formRow">
<div class="formCol">
<label for="connNotes">Notizen (optional)</label>
<input type="text" id="connNotes">
</div>
</div>
</div>
<div class="modalFooter">
<button type="button" class="btnGhost" id="btnConnCancel">Abbrechen</button>
<button type="button" class="btnPrimary" id="btnConnApply">Erstellen</button>
</div>
</div>
</div>
<div id="detailOverlay">
<div id="detailOverlayHeader">
<div>
<div id="detailOverlayTitle"></div>
<div id="detailOverlaySubtitle"></div>
</div>
<button id="detailOverlayClose" type="button">×</button>
</div>
<div id="detailBadges"></div>
<div id="detailOverlayBody"></div>
</div>
<script>
const appConfig=<?php echo json_encode($config,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);?>;
const iconBase=<?php echo json_encode($networking_iconbase,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);?>;
const deviceData=Array.isArray(appConfig.devices)?appConfig.devices:[];
appConfig.devices=deviceData;
</script>
<script>
(function(){
'use strict';
let deviceIndexByHostname={};
let deviceIndexByIp={};
let nodePositions=[];
let edges=[];
let visibleDevice=[];
let selectedDeviceIndex=null;
let mapScale=1;
let mapOffsetX=0;
let mapOffsetY=0;
let isPanning=false;
let lastPanX=0;
let lastPanY=0;
let dirty=false;
let editingIndex=null;
let connectSourceIndex=null;
let pendingConn=null;
function tt(v){if(v===null||v===undefined)return'';return String(v);}
function getVlanKey(ip){
if(typeof ip!=='string')return'Unbekannt';
const p=ip.split('.');
if(p.length!==4)return'Unbekannt';
for(let i=0;i<4;i++){const n=Number(p[i]);if(!Number.isInteger(n)||n<0||n>255)return'Unbekannt';}
return p[0]+'.'+p[1]+'.'+p[2]+'.0/24';
}
function ipToInt(ip){
const p=String(ip).split('.');
if(p.length!==4)return null;
let v=0;
for(let i=0;i<4;i++){const n=Number(p[i]);if(!Number.isInteger(n)||n<0||n>255)return null;v=v*256+n;}
return v;
}
function ipInCidr(ip,cidr){
const m=String(cidr).split('/');
if(m.length!==2)return false;
const bits=Number(m[1]);
const base=ipToInt(m[0]);
const val=ipToInt(ip);
if(base===null||val===null||!Number.isInteger(bits)||bits<0||bits>32)return false;
if(bits===0)return true;
const mask=bits===32?0xFFFFFFFF:(~((1<<(32-bits))-1))>>>0;
return((base&mask)>>>0)===((val&mask)>>>0);
}
function getGroupForDevice(d){
const ip=tt(d.IP||d.ip||'');
if(appConfig&&appConfig.vlanGroups){
for(const pref in appConfig.vlanGroups){
if(!Object.prototype.hasOwnProperty.call(appConfig.vlanGroups,pref))continue;
if(pref.indexOf('/')!==-1){if(ipInCidr(ip,pref))return String(appConfig.vlanGroups[pref]);}
else if(pref!==''&&ip.startsWith(pref))return String(appConfig.vlanGroups[pref]);
}
}
return getVlanKey(ip);
}
const typeCatalog={
'Firewall':{icon:'firewall.svg',color:'#d9480f'},
'Router':{icon:'router.svg',color:'#1c64d9'},
'Switch':{icon:'switch.svg',color:'#0e94d4'},
'Access Point':{icon:'wifi.svg',color:'#7c3aed'},
'Server':{icon:'server.svg',color:'#334155'},
'Windows-Server':{icon:'windows.svg',color:'#0078d4'},
'Linux-Server':{icon:'linux.svg',color:'#1f2937'},
'Domain-Controller':{icon:'ad.svg',color:'#1d4ed8'},
'DNS-Server':{icon:'dns.svg',color:'#b45309'},
'DHCP-Server':{icon:'dhcp.svg',color:'#0d9488'},
'Hypervisor':{icon:'hypervisor.svg',color:'#15803d'},
'Storage/NAS':{icon:'storage.svg',color:'#a16207'},
'Backup':{icon:'backup.svg',color:'#0f766e'},
'Datenbank':{icon:'database.svg',color:'#7e22ce'},
'Mail-Server':{icon:'mail.svg',color:'#b91c1c'},
'Web-Server':{icon:'web.svg',color:'#0284c7'},
'Proxy':{icon:'proxy.svg',color:'#4b5563'},
'VPN-Gateway':{icon:'vpn.svg',color:'#047857'},
'RDP/Terminal-Server':{icon:'rdp.svg',color:'#0369a1'},
'Monitoring':{icon:'monitoring.svg',color:'#374151'},
'Drucker':{icon:'printer.svg',color:'#475569'},
'Kamera':{icon:'camera.svg',color:'#6d28d9'},
'VoIP-Telefon':{icon:'phone.svg',color:'#0891b2'},
'USV':{icon:'ups.svg',color:'#65a30d'},
'Cloud-Dienst':{icon:'cloud.svg',color:'#2563eb'},
'IoT-Gerät':{icon:'iot.svg',color:'#9333ea'},
'PC':{icon:'pc.svg',color:'#4338ca'},
'Laptop':{icon:'laptop.svg',color:'#57534e'},
'Smartphone':{icon:'smartphone.svg',color:'#db2777'},
'Tablet':{icon:'tablet.svg',color:'#be123c'},
'Hotspot':{icon:'hotspot.svg',color:'#ea580c'},
'Internet/Provider':{icon:'internet.svg',color:'#075985'},
'Gerät (Sonstiges)':{icon:'device.svg',color:'#6b7280'}
};
const typeIconDefaults=[
{file:'firewall.svg',keys:['firewall','fortigate','fortinet','paloalto','palo-alto','sophos','pfsense','opnsense','checkpoint','watchguard','asa','fw']},
{file:'vpn.svg',keys:['vpn','ipsec']},
{file:'internet.svg',keys:['internet','provider','isp','wan','uplink','glasfaser','dsl']},
{file:'hotspot.svg',keys:['hotspot','mifi','tethering']},
{file:'smartphone.svg',keys:['smartphone','handy','iphone','android','mobil']},
{file:'tablet.svg',keys:['tablet','ipad']},
{file:'router.svg',keys:['router','mikrotik','edgerouter','fritz','tplink','tp-link','gateway','gw']},
{file:'switch.svg',keys:['switch','catalyst','procurve','nexus','cisco','sw']},
{file:'wifi.svg',keys:['wifi','wlan','accesspoint','access-point','access point','access','unifi','capwap','ap']},
{file:'hypervisor.svg',keys:['esxi','vmware','vcenter','proxmox','hyperv','hyper-v','xen','kvm','hypervisor']},
{file:'ad.svg',keys:['active-directory','activedirectory','domaincontroller','domain-controller','domain','ldap','ad','dc']},
{file:'dns.svg',keys:['dns']},
{file:'dhcp.svg',keys:['dhcp']},
{file:'mail.svg',keys:['mail','smtp','exchange','imap','pop3']},
{file:'proxy.svg',keys:['proxy']},
{file:'vpn.svg',keys:['vpn','ipsec']},
{file:'rdp.svg',keys:['rdp','terminalserver','terminal-server','terminal','ts']},
{file:'database.svg',keys:['database','datenbank','mssql','oracle','mysql','postgres','mariadb','sql','db']},
{file:'storage.svg',keys:['storage','netapp','synology','qnap','iscsi','nas','san']},
{file:'backup.svg',keys:['backup','veeam']},
{file:'monitoring.svg',keys:['monitoring','zabbix','prtg','nagios','grafana','snmp','syslog']},
{file:'printer.svg',keys:['printer','drucker','print','mfp']},
{file:'camera.svg',keys:['camera','kamera','cctv','nvr']},
{file:'phone.svg',keys:['voip','telefon','phone','pbx']},
{file:'ups.svg',keys:['usv','ups']},
{file:'cloud.svg',keys:['cloud','azure','aws']},
{file:'iot.svg',keys:['iot','sensor']},
{file:'laptop.svg',keys:['laptop','notebook']},
{file:'pc.svg',keys:['workstation','desktop','client','pc']},
{file:'web.svg',keys:['webserver','web-server','nginx','apache','iis','web','www']},
{file:'windows.svg',keys:['windows','win']},
{file:'linux.svg',keys:['linux','ubuntu','debian','centos','redhat','suse','nfs']},
{file:'server.svg',keys:['server','host','srv','vm']}
];
function getDefaultTypeIcon(type){
const low=type.toLowerCase();
const tokens=low.split(/[^a-z0-9]+/).filter(Boolean);
for(const e of typeIconDefaults){
for(const k of e.keys){
if(k.length<=3){if(tokens.indexOf(k)!==-1)return e.file;}
else if(low.indexOf(k)!==-1)return e.file;
}
}
return'device.svg';
}
function getTypeIconPath(type){
const typ=tt(type);
let file='';
if(appConfig&&appConfig.typeIcons&&appConfig.typeIcons[typ])file=String(appConfig.typeIcons[typ]);
if(file===''&&Object.prototype.hasOwnProperty.call(typeCatalog,typ))file=typeCatalog[typ].icon;
if(file===''){
if(typ==='')return'';
file=getDefaultTypeIcon(typ);
}
if(/^https?:\/\//i.test(file))return file;
return iconBase+file;
}
function getTypeColor(type){
const typ=tt(type);
if(appConfig&&appConfig.typeStyles&&appConfig.typeStyles[typ]&&appConfig.typeStyles[typ].color)return String(appConfig.typeStyles[typ].color);
if(Object.prototype.hasOwnProperty.call(typeCatalog,typ))return typeCatalog[typ].color;
const low=typ.toLowerCase();
if(low.indexOf('fortigate')!==-1)return'#cc6600';
if(low.indexOf('cisco')!==-1)return'#2563eb';
if(low.indexOf('esxi')!==-1||low.indexOf('hyper-v')!==-1||low.indexOf('proxmox')!==-1)return'#15803d';
if(low.indexOf('netapp')!==-1||low.indexOf('storage')!==-1)return'#b38f00';
if(low.indexOf('windows')!==-1||low.indexOf('ubuntu')!==-1||low.indexOf('linux')!==-1)return'#4b5563';
return'#6b7280';
}
function resolveServiceLabel(name){
const s=tt(name);
if(appConfig&&appConfig.serviceLabels&&appConfig.serviceLabels[s])return String(appConfig.serviceLabels[s]);
return s;
}
function getConnectionColor(connType){
const typ=tt(connType);
if(appConfig&&appConfig.connectionStyles&&appConfig.connectionStyles[typ]&&appConfig.connectionStyles[typ].color)return String(appConfig.connectionStyles[typ].color);
const low=typ.toLowerCase();
if(low.indexOf('vpn')!==-1||low.indexOf('ipsec')!==-1)return'#008000';
if(low.indexOf('rdp')!==-1)return'#cc0000';
if(low.indexOf('https')!==-1||low.indexOf('http')!==-1)return'#2563eb';
if(low.indexOf('ssh')!==-1)return'#b30059';
if(low.indexOf('dns')!==-1)return'#b38f00';
if(low.indexOf('smtp')!==-1||low.indexOf('mail')!==-1)return'#cc6600';
if(appConfig&&appConfig.connectionStyles&&appConfig.connectionStyles.DEFAULT&&appConfig.connectionStyles.DEFAULT.color)return String(appConfig.connectionStyles.DEFAULT.color);
return'#666666';
}
function buildIndexes(){
deviceIndexByHostname={};
deviceIndexByIp={};
for(let i=0;i<deviceData.length;i++){
const d=deviceData[i]||{};
const hn=tt(d.Hostname||d.hostname||'').toLowerCase();
if(hn)deviceIndexByHostname[hn]=i;
const ip=tt(d.IP||d.ip||'');
if(ip)deviceIndexByIp[ip]=i;
}
}
function resolveTargetIndex(ttg,tg){
if(!tg)return null;
const tgLow=tg.toLowerCase();
if(ttg==='hostname'&&Object.prototype.hasOwnProperty.call(deviceIndexByHostname,tgLow))return deviceIndexByHostname[tgLow];
if(ttg==='ip'&&Object.prototype.hasOwnProperty.call(deviceIndexByIp,tg))return deviceIndexByIp[tg];
if(Object.prototype.hasOwnProperty.call(deviceIndexByIp,tg))return deviceIndexByIp[tg];
if(Object.prototype.hasOwnProperty.call(deviceIndexByHostname,tgLow))return deviceIndexByHostname[tgLow];
return null;
}
function buildLayout(){
const groups={};
for(let i=0;i<deviceData.length;i++){
const d=deviceData[i]||{};
const g=getGroupForDevice(d);
if(!groups[g])groups[g]=[];
groups[g].push(i);
}
const groupKeys=Object.keys(groups);
groupKeys.sort((a,b)=>{if(a==='Unbekannt')return 1;if(b==='Unbekannt')return-1;return a.localeCompare(b,undefined,{numeric:true});});
const svg=document.getElementById('mapSvg');
const parent=svg.parentElement;
const w=svg.clientWidth||parent.clientWidth||1200;
const h=svg.clientHeight||parent.clientHeight||700;
const colCount=Math.max(groupKeys.length,1);
const colWidth=w/colCount;
const topMargin=100;
const bottomMargin=60;
const availableHeight=Math.max(h-topMargin-bottomMargin,120);
const minSpacing=40;
const nodeRadius=16;
nodePositions=new Array(deviceData.length);
for(let gi=0;gi<groupKeys.length;gi++){
const g=groupKeys[gi];
const indices=groups[g];
const cx=colWidth*gi+colWidth/2;
const count=indices.length;
const spacing=Math.max(availableHeight/Math.max(count,1),minSpacing);
let startY=topMargin+nodeRadius;
if(count>1){
const span=spacing*(count-1);
startY=topMargin+(availableHeight-span)/2;
}
for(let k=0;k<indices.length;k++){
const di=indices[k];
const y=startY+k*spacing;
nodePositions[di]={x:cx,y:y,group:g};
}
}
edges=[];
for(let i=0;i<deviceData.length;i++){
const d=deviceData[i]||{};
const conns=Array.isArray(d.Connections)?d.Connections:[];
for(let ci=0;ci<conns.length;ci++){
const c=conns[ci]||{};
const ttg=tt(c.targetType||'');
const tg=tt(c.target||'');
const ct=tt(c.connType||c.service||'Unbekannt');
const tgtIndex=resolveTargetIndex(ttg,tg);
if(tgtIndex!==null&&nodePositions[i]&&nodePositions[tgtIndex]){
edges.push({src:i,tgt:tgtIndex,connIndex:ci,connType:ct});
}
}
}
renderMap();
}
function renderMap(){
const svg=document.getElementById('mapSvg');
const content=document.getElementById('mapContent');
while(content.firstChild)content.removeChild(content.firstChild);
const parent=svg.parentElement;
const w=svg.clientWidth||parent.clientWidth||1200;
const h=svg.clientHeight||parent.clientHeight||700;
svg.setAttribute('width',w);
svg.setAttribute('height',h);
const rectLayer=document.createElementNS('http://www.w3.org/2000/svg','g');
const groupLabelLayer=document.createElementNS('http://www.w3.org/2000/svg','g');
const edgesLayer=document.createElementNS('http://www.w3.org/2000/svg','g');
const nodesLayer=document.createElementNS('http://www.w3.org/2000/svg','g');
const defs=document.createElementNS('http://www.w3.org/2000/svg','defs');
content.appendChild(defs);
const markerIds={};
function markerFor(color){
if(markerIds[color])return markerIds[color];
const id='arrow-'+Object.keys(markerIds).length;
const marker=document.createElementNS('http://www.w3.org/2000/svg','marker');
marker.setAttribute('id',id);
marker.setAttribute('markerWidth','7');
marker.setAttribute('markerHeight','7');
marker.setAttribute('refX','5.5');
marker.setAttribute('refY','3');
marker.setAttribute('orient','auto-start-reverse');
marker.setAttribute('markerUnits','userSpaceOnUse');
const mp=document.createElementNS('http://www.w3.org/2000/svg','path');
mp.setAttribute('d','M0 0 L6 3 L0 6 Z');
mp.setAttribute('fill',color);
marker.appendChild(mp);
defs.appendChild(marker);
markerIds[color]=id;
return id;
}
content.appendChild(rectLayer);
content.appendChild(edgesLayer);
content.appendChild(nodesLayer);
content.appendChild(groupLabelLayer);
const groupBBox={};
for(let i=0;i<nodePositions.length;i++){
const pos=nodePositions[i];
if(!pos)continue;
if(!visibleDevice[i])continue;
const gKey=pos.group||'';
if(!groupBBox[gKey])groupBBox[gKey]={minX:pos.x,maxX:pos.x,minY:pos.y,maxY:pos.y};
else{
if(pos.x<groupBBox[gKey].minX)groupBBox[gKey].minX=pos.x;
if(pos.x>groupBBox[gKey].maxX)groupBBox[gKey].maxX=pos.x;
if(pos.y<groupBBox[gKey].minY)groupBBox[gKey].minY=pos.y;
if(pos.y>groupBBox[gKey].maxY)groupBBox[gKey].maxY=pos.y;
}
}
for(const gKey in groupBBox){
if(!Object.prototype.hasOwnProperty.call(groupBBox,gKey))continue;
const b=groupBBox[gKey];
const marginX=40;
const marginY=40;
let width=(b.maxX-b.minX)+2*marginX;
let height=(b.maxY-b.minY)+2*marginY;
if(width<80)width=80;
if(height<90)height=90;
const labelWidth=gKey.length*6.8+20;
if(width<labelWidth)width=labelWidth;
let x=(b.minX+b.maxX)/2-width/2;
let y=b.minY-marginY;
let gFill='#ffffff';
let gStroke='#cbd5e1';
if(appConfig&&appConfig.groupStyles&&appConfig.groupStyles[gKey]){
const st=appConfig.groupStyles[gKey];
if(st.fill)gFill=String(st.fill);
if(st.stroke)gStroke=String(st.stroke);
}
const rect=document.createElementNS('http://www.w3.org/2000/svg','rect');
rect.setAttribute('x',String(x));
rect.setAttribute('y',String(y));
rect.setAttribute('width',String(width));
rect.setAttribute('height',String(height));
rect.setAttribute('rx','10');
rect.setAttribute('class','groupRect');
rect.setAttribute('fill',gFill);
rect.setAttribute('fill-opacity','0.75');
rect.setAttribute('stroke',gStroke);
rectLayer.appendChild(rect);
const band=document.createElementNS('http://www.w3.org/2000/svg','rect');
band.setAttribute('x',String(x));
band.setAttribute('y',String(y));
band.setAttribute('width',String(width));
band.setAttribute('height','22');
band.setAttribute('rx','10');
band.setAttribute('fill',gStroke==='#d1d5db'?'#94a3b8':gStroke);
rectLayer.appendChild(band);
const bandFix=document.createElementNS('http://www.w3.org/2000/svg','rect');
bandFix.setAttribute('x',String(x));
bandFix.setAttribute('y',String(y+12));
bandFix.setAttribute('width',String(width));
bandFix.setAttribute('height','10');
bandFix.setAttribute('fill',gStroke==='#d1d5db'?'#94a3b8':gStroke);
rectLayer.appendChild(bandFix);
const gl=document.createElementNS('http://www.w3.org/2000/svg','text');
gl.setAttribute('x',String(x+9));
gl.setAttribute('y',String(y+15));
gl.setAttribute('text-anchor','start');
gl.setAttribute('class','nodeGroupLabel');
gl.textContent=gKey;
groupLabelLayer.appendChild(gl);
}
const edgesFromSrc={};
for(let i=0;i<edges.length;i++){
const e=edges[i];
const s=e.src;
if(!edgesFromSrc[s])edgesFromSrc[s]=[];
edgesFromSrc[s].push(i);
}
for(let ei=0;ei<edges.length;ei++){
const e=edges[ei];
const s=e.src;
const tIdx=e.tgt;
if(!visibleDevice[s]||!visibleDevice[tIdx])continue;
const p1=nodePositions[s];
const p2=nodePositions[tIdx];
if(!p1||!p2)continue;
const group=edgesFromSrc[s]||[ei];
const count=group.length;
let x1=p1.x;
let y1=p1.y;
let x2=p2.x;
let y2=p2.y;
let dx=x2-x1;
let dy=y2-y1;
if(dx===0&&dy===0){dy=1;}
const len=Math.sqrt(dx*dx+dy*dy)||1;
const nx=-dy/len;
const ny=dx/len;
let offset=0;
if(count>1){
const idx=group.indexOf(ei);
if(idx!==-1){
const step=28;
offset=(idx-(count-1)/2)*step;
}
}
const mx=(x1+x2)/2;
const my=(y1+y2)/2;
const cx=mx+nx*offset;
const cy=my+ny*offset;
const gap=20;
let d1x=cx-x1,d1y=cy-y1;
let l1=Math.sqrt(d1x*d1x+d1y*d1y)||1;
x1=x1+d1x/l1*gap;
y1=y1+d1y/l1*gap;
let d2x=cx-x2,d2y=cy-y2;
let l2=Math.sqrt(d2x*d2x+d2y*d2y)||1;
x2=x2+d2x/l2*gap;
y2=y2+d2y/l2*gap;
const dStr='M '+x1+' '+y1+' Q '+cx+' '+cy+' '+x2+' '+y2;
const hit=document.createElementNS('http://www.w3.org/2000/svg','path');
hit.setAttribute('d',dStr);
hit.setAttribute('stroke','transparent');
hit.setAttribute('stroke-width','14');
hit.setAttribute('fill','none');
hit.setAttribute('stroke-opacity','0');
hit.dataset.edgeIndex=String(ei);
hit.style.cursor='pointer';
hit.addEventListener('click',function(ev){ev.stopPropagation();selectConnectionByEdge(ei);});
edgesLayer.appendChild(hit);
const path=document.createElementNS('http://www.w3.org/2000/svg','path');
path.setAttribute('d',dStr);
const edgeColor=getConnectionColor(e.connType);
path.setAttribute('stroke',edgeColor);
path.setAttribute('stroke-width','1.4');
path.setAttribute('stroke-opacity','0.9');
path.setAttribute('fill','none');
path.style.pointerEvents='none';
path.setAttribute('marker-end','url(#'+markerFor(edgeColor)+')');
edgesLayer.appendChild(path);
}
for(let i=0;i<deviceData.length;i++){
if(!visibleDevice[i])continue;
const d=deviceData[i]||{};
const pos=nodePositions[i];
if(!pos)continue;
const g=document.createElementNS('http://www.w3.org/2000/svg','g');
g.setAttribute('data-index',String(i));
const r=document.createElementNS('http://www.w3.org/2000/svg','circle');
r.setAttribute('cx',String(pos.x));
r.setAttribute('cy',String(pos.y));
r.setAttribute('r','16');
r.setAttribute('fill','#f9fafb');
r.setAttribute('stroke',getTypeColor(d.Type||d.type||''));
r.setAttribute('stroke-width',selectedDeviceIndex===i?'2.0':'1.2');
const kind=tt(d.Kind||d.kind||'');
if(kind==='VM')r.setAttribute('stroke-dasharray','4 2');
else if(kind==='Extern')r.setAttribute('stroke-dasharray','2 2');
r.setAttribute('class','nodeCircle');
g.appendChild(r);
const iconHref=getTypeIconPath(d.Type||d.type||'');
if(iconHref){
const img=document.createElementNS('http://www.w3.org/2000/svg','image');
img.setAttribute('href',iconHref);
img.setAttribute('x',String(pos.x-10));
img.setAttribute('y',String(pos.y-10));
img.setAttribute('width','20');
img.setAttribute('height','20');
img.addEventListener('error',function(){img.remove();});
g.appendChild(img);
}
const hn=tt(d.Hostname||d.hostname||'');
const ip=tt(d.IP||d.ip||'');
const label=document.createElementNS('http://www.w3.org/2000/svg','text');
label.setAttribute('x',String(pos.x));
label.setAttribute('y',String(pos.y+24));
label.setAttribute('text-anchor','middle');
label.setAttribute('class','nodeLabel');
label.textContent=hn;
g.appendChild(label);
const labelIp=document.createElementNS('http://www.w3.org/2000/svg','text');
labelIp.setAttribute('x',String(pos.x));
labelIp.setAttribute('y',String(pos.y+34));
labelIp.setAttribute('text-anchor','middle');
labelIp.setAttribute('class','nodeLabelIp');
labelIp.textContent=ip;
g.appendChild(labelIp);
g.addEventListener('click',function(ev){ev.stopPropagation();handleNodeClick(i);});
nodesLayer.appendChild(g);
}
updateMapTransform();
}
function updateMapTransform(){
const content=document.getElementById('mapContent');
content.setAttribute('transform','translate('+mapOffsetX+','+mapOffsetY+') scale('+mapScale+')');
}
function applyFilterFromSearch(){
const input=document.getElementById('searchInput');
const value=(input.value||'').toLowerCase().trim();
visibleDevice=new Array(deviceData.length);
let devCount=0;
let connCount=0;
let terms=[];
if(value)terms=value.split(/\s+/).filter(Boolean);
for(let i=0;i<deviceData.length;i++){
const d=deviceData[i]||{};
let vis=true;
if(terms.length>0){
let hay='';
const hn=tt(d.Hostname||d.hostname||'');
const ip=tt(d.IP||d.ip||'');
const type=tt(d.Type||d.type||'');
const kindHay=tt(d.Kind||d.kind||'');
const vlan=getVlanKey(ip);
hay=(hn+' '+ip+' '+type+' '+kindHay+' '+vlan).toLowerCase();
const devNotes=tt(d.Notes||d.notes||'');
if(devNotes)hay+=' '+devNotes.toLowerCase();
const conns=Array.isArray(d.Connections)?d.Connections:[];
for(const c of conns){
const ct=tt(c.connType||c.service||'');
const tgt=tt(c.target||'');
hay+=' '+ct.toLowerCase()+' '+tgt.toLowerCase();
const connNotes=tt(c.Notes||c.notes||'');
if(connNotes)hay+=' '+connNotes.toLowerCase();
}
for(const tTerm of terms){
if(hay.indexOf(tTerm)===-1){vis=false;break;}
}
}
visibleDevice[i]=vis;
if(vis){
devCount++;
const conns=Array.isArray(d.Connections)?d.Connections:[];
connCount+=conns.length;
}
}
document.getElementById('searchCountDevices').textContent=String(devCount);
document.getElementById('searchCountConns').textContent=String(connCount);
}
function renderDeviceOverlay(deviceIndex){
const d=deviceData[deviceIndex]||{};
const overlay=document.getElementById('detailOverlay');
const title=document.getElementById('detailOverlayTitle');
const subtitle=document.getElementById('detailOverlaySubtitle');
const badges=document.getElementById('detailBadges');
const body=document.getElementById('detailOverlayBody');
const hnName=tt(d.Hostname||d.hostname||'Unbekannt');
const ip=tt(d.IP||d.ip||'0.0.0.0');
title.textContent=hnName;
subtitle.textContent='Gerät · IP '+ip;
badges.innerHTML='';
const vlanBadge=document.createElement('div');
vlanBadge.className='badge';
const dot1=document.createElement('div');
dot1.className='badgeDot';
vlanBadge.appendChild(dot1);
const lbl1=document.createElement('span');
lbl1.textContent=getVlanKey(ip);
vlanBadge.appendChild(lbl1);
badges.appendChild(vlanBadge);
const conns=Array.isArray(d.Connections)?d.Connections:[];
const connBadge=document.createElement('div');
connBadge.className='badge';
const dot2=document.createElement('div');
dot2.className='badgeDot';
dot2.style.backgroundColor=conns.length>0?'#15803d':'#6b7280';
connBadge.appendChild(dot2);
const lbl2=document.createElement('span');
lbl2.textContent=conns.length+' Verbindungen';
connBadge.appendChild(lbl2);
badges.appendChild(connBadge);
const group=getGroupForDevice(d);
const groupBadge=document.createElement('div');
groupBadge.className='badge';
const dot3=document.createElement('div');
dot3.className='badgeDot';
dot3.style.backgroundColor='#2563eb';
groupBadge.appendChild(dot3);
const lbl3=document.createElement('span');
lbl3.textContent=group;
groupBadge.appendChild(lbl3);
badges.appendChild(groupBadge);
body.innerHTML='';
const header=document.createElement('div');
header.id='deviceHeaderRow';
const iconHref=getTypeIconPath(d.Type||d.type||'');
if(iconHref){
const iconWrapper=document.createElement('div');
iconWrapper.className='deviceIconWrapper';
const img=document.createElement('img');
img.className='deviceIcon';
img.alt=tt(d.Type||d.type||'');
img.src=iconHref;
img.addEventListener('error',function(){iconWrapper.remove();});
iconWrapper.appendChild(img);
header.appendChild(iconWrapper);
}
const tWrap=document.createElement('div');
const hnEl=document.createElement('div');
hnEl.className='deviceHostname';
hnEl.textContent=hnName;
const ipEl=document.createElement('div');
ipEl.className='deviceIp';
ipEl.textContent=ip;
const typeEl=document.createElement('div');
typeEl.className='deviceType';
typeEl.textContent=tt(d.Type||d.type||'');
tWrap.appendChild(hnEl);
tWrap.appendChild(ipEl);
tWrap.appendChild(typeEl);
header.appendChild(tWrap);
body.appendChild(header);
const actions=document.createElement('div');
actions.id='overlayActions';
function mkActBtn(label,cls,fn){
const b=document.createElement('button');
b.type='button';
b.className=cls+' btnSmall';
b.textContent=label;
b.addEventListener('click',fn);
actions.appendChild(b);
}
mkActBtn('Bearbeiten','btnGhost',function(){openDeviceModal(deviceIndex);});
mkActBtn('Verbinden','btnGhost',function(){startConnectMode(deviceIndex);});
mkActBtn('Löschen','btnDanger',function(){deleteDevice(deviceIndex);});
body.appendChild(actions);
const stInfo=document.createElement('div');
stInfo.className='sectionTitle';
stInfo.textContent='Geräteinformationen';
body.appendChild(stInfo);
const info=document.createElement('div');
function addRow(l,v){
const row=document.createElement('div');
row.className='infoRow';
const sl=document.createElement('span');
sl.textContent=l;
const sv=document.createElement('span');
sv.textContent=v;
row.appendChild(sl);
row.appendChild(sv);
info.appendChild(row);
}
addRow('Hostname',hnName);
addRow('IP-Adresse',ip);
addRow('Typ',tt(d.Type||d.type||''));
const kindRow=tt(d.Kind||d.kind||'');
if(kindRow!=='')addRow('Art',kindRow);
addRow('VLAN / Netz',getVlanKey(ip));
addRow('Gruppe',group);
body.appendChild(info);
const devNotes=tt(d.Notes||d.notes||'').trim();
if(devNotes!==''){
const nb=document.createElement('div');
nb.className='noteBox';
const nt=document.createElement('div');
nt.className='noteTitle';
nt.textContent='Notizen';
const nc=document.createElement('div');
nc.textContent=devNotes;
nb.appendChild(nt);
nb.appendChild(nc);
body.appendChild(nb);
}
const incomingConns=[];
for(let sIndex=0;sIndex<deviceData.length;sIndex++){
if(sIndex===deviceIndex)continue;
const sd=deviceData[sIndex]||{};
const sh=tt(sd.Hostname||sd.hostname||'');
const sConns=Array.isArray(sd.Connections)?sd.Connections:[];
for(let ci=0;ci<sConns.length;ci++){
const c2=sConns[ci]||{};
if(resolveTargetIndex(tt(c2.targetType||''),tt(c2.target||''))===deviceIndex)incomingConns.push({sourceIndex:sIndex,connIndex:ci,conn:c2,sourceHostname:sh});
}
}
if(conns.length>0){
const stConn=document.createElement('div');
stConn.className='sectionTitle';
stConn.textContent='Verbindungen';
body.appendChild(stConn);
for(let i=0;i<conns.length;i++){
const c=conns[i]||{};
const item=document.createElement('div');
item.className='connectionItem';
const head=document.createElement('div');
head.className='connectionHeader';
const left=document.createElement('div');
const connType=tt(c.connType||c.service||'Unbekannt');
const typeLbl=document.createElement('div');
typeLbl.className='connectionType';
typeLbl.textContent=resolveServiceLabel(connType);
const tgtType=tt(c.targetType||'');
const tgt=tt(c.target||'');
const tgtEl=document.createElement('div');
tgtEl.className='connectionTarget';
if(tgt){
if(resolveTargetIndex(tgtType,tgt)!==null)tgtEl.textContent=tgt+' · Zielgerät vorhanden';
else tgtEl.textContent=tgt;
}else tgtEl.textContent='Ziel unbekannt';
left.appendChild(typeLbl);
left.appendChild(tgtEl);
const right=document.createElement('div');
const badge=document.createElement('div');
badge.className='connectionBadge';
const dot=document.createElement('div');
dot.className='connectionColorDot';
dot.style.backgroundColor=getConnectionColor(connType);
badge.appendChild(dot);
const portB=Number(c.port||0);
if(portB>0){
const pb=document.createElement('span');
pb.textContent='Port '+portB;
badge.appendChild(pb);
}
right.appendChild(badge);
head.appendChild(left);
head.appendChild(right);
item.appendChild(head);
const meta=document.createElement('div');
meta.className='connectionMeta';
const parts=[];
parts.push('Typ: '+connType);
const portNum=Number(c.port||0);
if(portNum>0)parts.push('Port: '+portNum);
if(tgtType)parts.push('Zieltyp: '+tgtType);
meta.textContent=parts.join(' · ');
item.appendChild(meta);
const connNotes=tt(c.Notes||c.notes||'').trim();
if(connNotes!==''){
const metaBox=document.createElement('div');
metaBox.className='connectionMeta';
metaBox.textContent=connNotes;
item.appendChild(metaBox);
}
item.addEventListener('click',function(){renderConnectionOverlay(deviceIndex,i);});
body.appendChild(item);
}
}
if(incomingConns.length>0){
const stIn=document.createElement('div');
stIn.className='sectionTitle';
stIn.textContent='Eingehende Verbindungen';
body.appendChild(stIn);
for(let i=0;i<incomingConns.length;i++){
const entry=incomingConns[i];
const c=entry.conn;
const item=document.createElement('div');
item.className='connectionItem';
const head=document.createElement('div');
head.className='connectionHeader';
const left=document.createElement('div');
const connType=tt(c.connType||c.service||'Unbekannt');
const typeLbl=document.createElement('div');
typeLbl.className='connectionType';
typeLbl.textContent=resolveServiceLabel(connType);
const tgtEl=document.createElement('div');
tgtEl.className='connectionTarget';
if(entry.sourceHostname)tgtEl.textContent='Quelle: '+entry.sourceHostname;
else tgtEl.textContent='Quelle unbekannt';
left.appendChild(typeLbl);
left.appendChild(tgtEl);
const right=document.createElement('div');
const badge=document.createElement('div');
badge.className='connectionBadge';
const dot=document.createElement('div');
dot.className='connectionColorDot';
dot.style.backgroundColor=getConnectionColor(connType);
badge.appendChild(dot);
const portB=Number(c.port||0);
if(portB>0){
const pb=document.createElement('span');
pb.textContent='Port '+portB;
badge.appendChild(pb);
}
right.appendChild(badge);
head.appendChild(left);
head.appendChild(right);
item.appendChild(head);
const meta=document.createElement('div');
meta.className='connectionMeta';
const parts=[];
parts.push('Typ: '+connType);
const portNum=Number(c.port||0);
if(portNum>0)parts.push('Port: '+portNum);
if(entry.sourceHostname)parts.push('Quelle: '+entry.sourceHostname);
meta.textContent=parts.join(' · ');
item.appendChild(meta);
const connNotes=tt(c.Notes||c.notes||'').trim();
if(connNotes!==''){
const metaBox=document.createElement('div');
metaBox.className='connectionMeta';
metaBox.textContent=connNotes;
item.appendChild(metaBox);
}
item.addEventListener('click',function(){renderConnectionOverlay(entry.sourceIndex,entry.connIndex);});
body.appendChild(item);
}
}
overlay.style.display='block';
}
function renderConnectionOverlay(deviceIndex,connIndex){
const d=deviceData[deviceIndex]||{};
const conns=Array.isArray(d.Connections)?d.Connections:[];
const c=conns[connIndex]||{};
const overlay=document.getElementById('detailOverlay');
const title=document.getElementById('detailOverlayTitle');
const subtitle=document.getElementById('detailOverlaySubtitle');
const badges=document.getElementById('detailBadges');
const body=document.getElementById('detailOverlayBody');
const hnName=tt(d.Hostname||d.hostname||'Unbekannt');
const ip=tt(d.IP||d.ip||'0.0.0.0');
const connType=tt(c.connType||c.service||'Unbekannt');
title.textContent=resolveServiceLabel(connType);
subtitle.textContent='Verbindung · Quelle '+hnName;
badges.innerHTML='';
const typeBadge=document.createElement('div');
typeBadge.className='badge';
const dot1=document.createElement('div');
dot1.className='badgeDot';
dot1.style.backgroundColor=getConnectionColor(connType);
typeBadge.appendChild(dot1);
const lbl1=document.createElement('span');
lbl1.textContent=connType;
typeBadge.appendChild(lbl1);
badges.appendChild(typeBadge);
const portBadgeNum=Number(c.port||0);
if(portBadgeNum>0){
const portBadge=document.createElement('div');
portBadge.className='badge';
const lbl2=document.createElement('span');
lbl2.textContent='Port '+portBadgeNum;
portBadge.appendChild(lbl2);
badges.appendChild(portBadge);
}
body.innerHTML='';
const stInfo=document.createElement('div');
stInfo.className='sectionTitle';
stInfo.textContent='Verbindungsdetails';
body.appendChild(stInfo);
function addRow(l,v){
const row=document.createElement('div');
row.className='infoRow';
const sl=document.createElement('span');
sl.textContent=l;
const sv=document.createElement('span');
sv.textContent=v;
row.appendChild(sl);
row.appendChild(sv);
body.appendChild(row);
}
addRow('Quelle',hnName+' ('+ip+')');
const tgtType=tt(c.targetType||'');
const tgt=tt(c.target||'');
if(tgt){
let info=tgt;
if(tgtType==='hostname'&&Object.prototype.hasOwnProperty.call(deviceIndexByHostname,tgt))info+=' (Gerät vorhanden)';
else if((tgtType==='ip'||!tgtType)&&Object.prototype.hasOwnProperty.call(deviceIndexByIp,tgt))info+=' (Gerät per IP vorhanden)';
addRow('Ziel',info);
}else addRow('Ziel','Unbekannt');
addRow('Zieltyp',tgtType||'-');
const portNum=Number(c.port||0);
if(portNum>0)addRow('Port',String(portNum));
const connNotes=tt(c.Notes||c.notes||'').trim();
if(connNotes!==''){
const nb=document.createElement('div');
nb.className='noteBox';
const nt=document.createElement('div');
nt.className='noteTitle';
nt.textContent='Notizen';
const nc=document.createElement('div');
nc.textContent=connNotes;
nb.appendChild(nt);
nb.appendChild(nc);
body.appendChild(nb);
}
const actions=document.createElement('div');
actions.id='overlayActions';
const delBtn=document.createElement('button');
delBtn.type='button';
delBtn.className='btnDanger btnSmall';
delBtn.textContent='Verbindung löschen';
delBtn.addEventListener('click',function(){deleteConnection(deviceIndex,connIndex);});
actions.appendChild(delBtn);
body.appendChild(actions);
overlay.style.display='block';
}
function hideOverlay(){
document.getElementById('detailOverlay').style.display='none';
}
function selectDevice(index){
const i=Number(index);
if(!Number.isInteger(i)||i<0||i>=deviceData.length)return;
selectedDeviceIndex=i;
renderDeviceOverlay(i);
buildLayout();
}
function selectConnectionByEdge(edgeIndex){
const e=edges[edgeIndex];
if(!e)return;
const srcIdx=e.src;
selectedDeviceIndex=srcIdx;
renderConnectionOverlay(srcIdx,e.connIndex);
buildLayout();
}
function markDirty(){
dirty=true;
const b=document.getElementById('btnSave');
b.classList.add('dirtyState');
b.textContent='Speichern *';
}
function rebuildAll(){
buildIndexes();
applyFilterFromSearch();
buildLayout();
}
function serviceKeys(){
const keys=[];
const labels=appConfig.serviceLabels&&typeof appConfig.serviceLabels==='object'?appConfig.serviceLabels:{};
for(const k in labels){
if(!Object.prototype.hasOwnProperty.call(labels,k))continue;
if(k==='DEFAULT')continue;
keys.push(k);
}
keys.sort();
return keys;
}
function fillServiceSelect(){
const sel=document.getElementById('connService');
sel.innerHTML='';
const first=document.createElement('option');
first.value='';
first.textContent='– Dienst –';
sel.appendChild(first);
for(const k of serviceKeys()){
const o=document.createElement('option');
o.value=k;
const lbl=resolveServiceLabel(k);
o.textContent=lbl!==k?k+' · '+lbl:k;
sel.appendChild(o);
}
}
function updateTypeIcon(){
document.getElementById('devTypeIcon').src=getTypeIconPath(document.getElementById('devType').value);
}
function fillTypeSelect(current){
const sel=document.getElementById('devType');
sel.innerHTML='';
let found=false;
for(const k in typeCatalog){
if(!Object.prototype.hasOwnProperty.call(typeCatalog,k))continue;
const o=document.createElement('option');
o.value=k;
o.textContent=k;
if(k===current){o.selected=true;found=true;}
sel.appendChild(o);
}
if(current&&!found){
const o=document.createElement('option');
o.value=current;
o.textContent=current+' (bestehend)';
o.selected=true;
sel.appendChild(o);
}
updateTypeIcon();
}
function openDeviceModal(index){
const isNew=index===null||index===undefined||index<0;
editingIndex=isNew?null:index;
const title=document.getElementById('deviceModalTitle');
if(isNew){
title.textContent='Neues Gerät';
document.getElementById('devHostname').value='';
document.getElementById('devIP').value='';
fillTypeSelect('Gerät (Sonstiges)');
document.getElementById('devKind').value='Physisch';
document.getElementById('devNotes').value='';
}else{
const d=deviceData[index]||{};
title.textContent='Gerät bearbeiten';
document.getElementById('devHostname').value=tt(d.Hostname||d.hostname||'');
document.getElementById('devIP').value=tt(d.IP||d.ip||'');
fillTypeSelect(tt(d.Type||d.type||''));
const kind=tt(d.Kind||d.kind||'');
document.getElementById('devKind').value=kind==='VM'||kind==='Extern'?kind:'Physisch';
document.getElementById('devNotes').value=tt(d.Notes||d.notes||'');
}
hideOverlay();
document.getElementById('deviceModal').style.display='flex';
}
function closeDeviceModal(){
document.getElementById('deviceModal').style.display='none';
editingIndex=null;
}
function applyDeviceModal(){
const hn=document.getElementById('devHostname').value.trim();
const ip=document.getElementById('devIP').value.trim();
if(hn===''&&ip===''){alert('Mindestens Hostname oder IP-Adresse muss gesetzt sein.');return;}
const dev=editingIndex!==null&&deviceData[editingIndex]?deviceData[editingIndex]:{};
if(hn!=='')dev.Hostname=hn;else delete dev.Hostname;
if(ip!=='')dev.IP=ip;else delete dev.IP;
dev.Type=document.getElementById('devType').value;
dev.Kind=document.getElementById('devKind').value;
const notes=document.getElementById('devNotes').value.trim();
if(notes!=='')dev.Notes=notes;else delete dev.Notes;
if(!Array.isArray(dev.Connections))dev.Connections=[];
if(editingIndex===null)deviceData.push(dev);
closeDeviceModal();
markDirty();
rebuildAll();
}
function deleteDevice(index){
const d=deviceData[index]||{};
const hn=tt(d.Hostname||d.hostname||'');
const ip=tt(d.IP||d.ip||'');
if(!confirm('Gerät „'+(hn||ip)+'" wirklich löschen? Verbindungen von und zu diesem Gerät werden entfernt.'))return;
deviceData.splice(index,1);
for(const other of deviceData){
if(!other||!Array.isArray(other.Connections))continue;
other.Connections=other.Connections.filter(function(c){
const tg=tt((c||{}).target||'');
return!(tg!==''&&((hn!==''&&tg.toLowerCase()===hn.toLowerCase())||(ip!==''&&tg===ip)));
});
}
selectedDeviceIndex=null;
hideOverlay();
markDirty();
rebuildAll();
}
function startConnectMode(srcIndex){
connectSourceIndex=srcIndex;
hideOverlay();
document.getElementById('connectBanner').style.display='flex';
}
function cancelConnectMode(){
connectSourceIndex=null;
document.getElementById('connectBanner').style.display='none';
}
function handleNodeClick(i){
if(connectSourceIndex!==null){
if(i===connectSourceIndex)return;
openConnModal(connectSourceIndex,i);
return;
}
selectDevice(i);
}
function openConnModal(src,tgt){
pendingConn={src:src,tgt:tgt};
const s=deviceData[src]||{};
const t=deviceData[tgt]||{};
const sName=tt(s.Hostname||s.hostname||s.IP||s.ip||'?');
const tName=tt(t.Hostname||t.hostname||t.IP||t.ip||'?');
document.getElementById('connModalInfo').textContent=sName+' → '+tName;
fillServiceSelect();
document.getElementById('connPort').value='';
document.getElementById('connNotes').value='';
document.getElementById('connModal').style.display='flex';
}
function closeConnModal(){
document.getElementById('connModal').style.display='none';
pendingConn=null;
}
function applyConnModal(){
if(!pendingConn)return;
const d=deviceData[pendingConn.src];
if(!d)return;
if(!Array.isArray(d.Connections))d.Connections=[];
const t=deviceData[pendingConn.tgt]||{};
const hn=tt(t.Hostname||t.hostname||'');
const ip=tt(t.IP||t.ip||'');
const obj={};
const svc=document.getElementById('connService').value;
if(svc!=='')obj.connType=svc;
obj.target=hn||ip;
obj.targetType=hn!==''?'hostname':'ip';
const portStr=document.getElementById('connPort').value.trim();
if(portStr!==''){
const n=Number(portStr);
if(!Number.isNaN(n)&&n>0)obj.port=n;
}
const notes=document.getElementById('connNotes').value.trim();
if(notes!=='')obj.Notes=notes;
d.Connections.push(obj);
const srcIdx=pendingConn.src;
closeConnModal();
cancelConnectMode();
markDirty();
rebuildAll();
selectDevice(srcIdx);
}
function deleteConnection(devIndex,connIndex){
const d=deviceData[devIndex];
if(!d||!Array.isArray(d.Connections))return;
if(!confirm('Verbindung wirklich löschen?'))return;
d.Connections.splice(connIndex,1);
markDirty();
rebuildAll();
selectDevice(devIndex);
}
function saveConfigNow(){
document.getElementById('config_json').value=JSON.stringify(appConfig);
dirty=false;
document.getElementById('saveForm').submit();
}
function setupManage(){
document.getElementById('btnAddDevice').addEventListener('click',function(){openDeviceModal(null);});
document.getElementById('btnSave').addEventListener('click',saveConfigNow);
document.getElementById('btnDeviceClose').addEventListener('click',closeDeviceModal);
document.getElementById('btnDeviceApply').addEventListener('click',applyDeviceModal);
document.getElementById('btnDeviceDelete').addEventListener('click',function(){
if(editingIndex===null){closeDeviceModal();return;}
const idx=editingIndex;
closeDeviceModal();
deleteDevice(idx);
});
document.getElementById('devType').addEventListener('change',updateTypeIcon);
document.getElementById('btnConnClose').addEventListener('click',closeConnModal);
document.getElementById('btnConnCancel').addEventListener('click',closeConnModal);
document.getElementById('btnConnApply').addEventListener('click',applyConnModal);
document.getElementById('btnConnectCancel').addEventListener('click',cancelConnectMode);
document.getElementById('deviceModal').addEventListener('click',function(e){if(e.target===this)closeDeviceModal();});
document.getElementById('connModal').addEventListener('click',function(e){if(e.target===this)closeConnModal();});
document.addEventListener('keydown',function(e){
if(e.key!=='Escape')return;
closeDeviceModal();
closeConnModal();
cancelConnectMode();
});
window.addEventListener('beforeunload',function(e){
if(!dirty)return;
e.preventDefault();
e.returnValue='';
});
const notice=document.getElementById('saveNotice');
if(notice)setTimeout(function(){notice.style.display='none';},2500);
}
function setupSearch(){
const input=document.getElementById('searchInput');
input.addEventListener('input',function(){
applyFilterFromSearch();
buildLayout();
hideOverlay();
});
}
function setupOverlay(){
document.getElementById('detailOverlayClose').addEventListener('click',function(){hideOverlay();});
document.addEventListener('keydown',function(ev){if(ev.key==='Escape')hideOverlay();});
}
function setupPanZoom(){
const svg=document.getElementById('mapSvg');
svg.addEventListener('wheel',function(ev){
ev.preventDefault();
const rect=svg.getBoundingClientRect();
const cx=ev.clientX-rect.left;
const cy=ev.clientY-rect.top;
const delta=ev.deltaY;
const factor=delta>0?0.9:1.1;
let newScale=mapScale*factor;
if(newScale<0.4)newScale=0.4;
if(newScale>2.5)newScale=2.5;
const ratio=newScale/mapScale;
mapOffsetX=cx-(cx-mapOffsetX)*ratio;
mapOffsetY=cy-(cy-mapOffsetY)*ratio;
mapScale=newScale;
updateMapTransform();
},{passive:false});
svg.addEventListener('mousedown',function(ev){
isPanning=true;
lastPanX=ev.clientX;
lastPanY=ev.clientY;
});
window.addEventListener('mousemove',function(ev){
if(!isPanning)return;
const dx=ev.clientX-lastPanX;
const dy=ev.clientY-lastPanY;
lastPanX=ev.clientX;
lastPanY=ev.clientY;
mapOffsetX+=dx;
mapOffsetY+=dy;
updateMapTransform();
});
window.addEventListener('mouseup',function(){isPanning=false;});
svg.addEventListener('click',function(){hideOverlay();});
}
function setupResize(){
if(typeof ResizeObserver==='undefined')return;
const shell=document.getElementById('mapShell');
const obs=new ResizeObserver(function(){buildLayout();});
obs.observe(shell);
}
function init(){
visibleDevice=new Array(deviceData.length);
for(let i=0;i<visibleDevice.length;i++)visibleDevice[i]=true;
buildIndexes();
applyFilterFromSearch();
buildLayout();
setupSearch();
setupOverlay();
setupPanZoom();
setupResize();
setupManage();
}
document.addEventListener('DOMContentLoaded',init);
})();
</script>
</body>
</html>
