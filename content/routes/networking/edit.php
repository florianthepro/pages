<?php
session_start();
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
if(empty($_SESSION['networking_csrf']))$_SESSION['networking_csrf']=bin2hex(random_bytes(16));
$csrfToken=$_SESSION['networking_csrf'];
$save_msg='';
$save_err='';
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='save'){
$token=$_POST['csrf']??'';
if(!hash_equals($csrfToken,(string)$token))$save_err='Ungültiges Sicherheits-Token.';
else{
$incoming=$_POST['config_json']??'';
$decoded=json_decode((string)$incoming,true);
if(!is_array($decoded))$save_err='Übergebene Konfiguration ist kein gültiges JSON-Objekt.';
else{
if(save_config($networking_jsondir,$decoded))$save_msg='Gespeichert.';
else $save_err='Fehler beim Speichern: '.$networking_jsondir;
}
}
}
$config=load_config($networking_jsondir);
$appConfigJson=json_encode($config,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
if(!is_string($appConfigJson))$appConfigJson='{}';
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="color-scheme" content="only light">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/svg+xml" href="<?=$networking_editoricon?>">
<title><?=$networking_editortitle?></title>
<style>
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;color:#111827}
#shell{max-width:1100px;margin:0 auto;padding:16px 14px}
#headerRow{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px}
#headerTitle{font-size:20px;font-weight:bold}
#headerLinks{font-size:13px;display:flex;gap:12px;align-items:center}
#headerLinks a{text-decoration:none;color:#0070ff}
#headerLinks a:hover{text-decoration:underline}
#messages{margin-bottom:10px}
.msg{font-size:13px;padding:8px 10px;border-radius:6px;margin-bottom:6px}
.msg.ok{background:#dcfce7;border:1px solid #16a34a;color:#14532d}
.msg.err{background:#fee2e2;border:1px solid #dc2626;color:#7f1d1d}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.06);padding:12px;margin-bottom:14px}
#deviceBar{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px}
#deviceCount{font-size:13px;color:#4b5563}
button{font-family:inherit}
.btnPrimary{border-radius:6px;border:1px solid #0070ff;background:#0070ff;color:#fff;font-size:13px;padding:7px 14px;cursor:pointer}
.btnPrimary:hover{background:#0050c0}
.btnGhost{border-radius:6px;border:1px solid #c6cbd3;background:#fff;color:#111827;font-size:13px;padding:6px 12px;cursor:pointer}
.btnGhost:hover{background:#eef1f5;border-color:#9ca3af}
.btnDanger{border-radius:6px;border:1px solid #dc2626;background:#fff;color:#dc2626;font-size:13px;padding:6px 12px;cursor:pointer}
.btnDanger:hover{background:#fee2e2}
table{border-collapse:collapse;width:100%;font-size:13px;background:#fff}
th,td{border-bottom:1px solid #e5e7eb;padding:8px 10px;text-align:left}
th{background:#f9fafb;font-weight:bold;font-size:12px;color:#4b5563;text-transform:uppercase;letter-spacing:.04em}
tbody tr:hover{background:#f3f7ff}
td.actions{white-space:nowrap;text-align:right}
.emptyHint{padding:18px;text-align:center;color:#6b7280;font-size:13px}
.badgeSmall{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;border:1px solid #d1d5db;background:#f9fafb;color:#4b5563}
#deviceOverlay{position:fixed;inset:0;background:rgba(17,24,39,.55);display:none;align-items:center;justify-content:center;z-index:1000;padding:12px;box-sizing:border-box}
#deviceOverlayPanel{background:#fff;border-radius:10px;border:1px solid #d1d5db;box-shadow:0 12px 40px rgba(0,0,0,.25);max-width:780px;width:100%;max-height:92vh;display:flex;flex-direction:column}
#deviceOverlayHeader{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid #e5e7eb}
#deviceOverlayTitle{font-size:16px;font-weight:bold}
#deviceOverlayBody{padding:12px 14px;overflow:auto}
.formRow{display:flex;gap:10px;margin-bottom:10px}
.formCol{flex:1}
label{display:block;font-size:12px;font-weight:bold;margin-bottom:4px;color:#374151}
input[type="text"],input[type="number"],textarea,select{width:100%;box-sizing:border-box;border-radius:6px;border:1px solid #c6cbd3;font-size:13px;padding:7px 8px;font-family:inherit;background:#fff;color:#111827}
input[type="text"]:focus,input[type="number"]:focus,textarea:focus,select:focus{border-color:#0070ff;outline:none;box-shadow:0 0 0 2px rgba(0,112,255,.15)}
textarea{min-height:64px;resize:vertical}
#connHeaderRow{display:flex;justify-content:space-between;align-items:center;margin-top:14px;margin-bottom:6px}
#connHeaderRow>div{font-size:13px;font-weight:bold;color:#374151}
#connTable{border-collapse:collapse;width:100%;font-size:12px;background:#fff}
#connTable th,#connTable td{border-bottom:1px solid #e5e7eb;padding:5px 4px;text-align:left}
#connTable th{background:#f9fafb;font-weight:bold;font-size:11px;color:#4b5563}
#connTable input,#connTable select{font-size:12px;padding:5px 6px}
.connHint{font-size:11px;font-weight:normal;color:#6b7280;margin-left:6px}
.typeRow{display:flex;gap:8px;align-items:center}
.typeRow select{flex:1}
#devTypeIcon{width:30px;height:30px;border-radius:6px;border:1px solid #e5e7eb;background:#f9fafb;flex:0 0 auto}
.targetCell{display:flex;flex-direction:column;gap:4px}
#deviceOverlayFooter{display:flex;justify-content:space-between;gap:8px;padding:12px 14px;border-top:1px solid #e5e7eb}
@media(max-width:720px){
#deviceOverlayPanel{max-width:96vw}
.formRow{flex-direction:column}
}
</style>
</head>
<body>
<div id="shell">
<div id="headerRow">
<div id="headerTitle"><?=$networking_editorheading?></div>
<div id="headerLinks">
<span>Ansicht:</span>
<a href="?_page=index">Map öffnen</a>
<a href="?_page=raw" target="_blank" rel="noopener noreferrer">config.json</a>
</div>
</div>
<div id="messages">
<?php if($save_msg!==''){echo'<div class="msg ok">'.h($save_msg).'</div>';}?>
<?php if($save_err!==''){echo'<div class="msg err">'.h($save_err).'</div>';}?>
</div>
<div class="card">
<div id="deviceBar">
<div id="deviceCount">Geräte: <span id="deviceCountValue">0</span></div>
<div>
<button type="button" class="btnGhost" id="btnAddDevice">+ Neues Gerät</button>
<button type="button" class="btnPrimary" id="btnSaveConfig">Konfiguration speichern</button>
</div>
</div>
<table id="deviceTable">
<thead>
<tr>
<th>Hostname</th>
<th>IP-Adresse</th>
<th>Typ</th>
<th>Art</th>
<th>Notizen</th>
<th>Verbindungen</th>
<th class="actions">Aktionen</th>
</tr>
</thead>
<tbody id="deviceTableBody"></tbody>
</table>
<div class="emptyHint" id="emptyHint" style="display:none">Noch keine Geräte. Mit „+ Neues Gerät" starten, danach „Konfiguration speichern".</div>
</div>
<form id="saveForm" method="post" autocomplete="off">
<input type="hidden" name="action" value="save">
<input type="hidden" name="csrf" value="<?php echo h($csrfToken);?>">
<input type="hidden" name="config_json" id="config_json">
</form>
</div>
<div id="deviceOverlay">
<div id="deviceOverlayPanel">
<div id="deviceOverlayHeader">
<div id="deviceOverlayTitle"></div>
<button type="button" class="btnGhost" id="btnDeviceClose">Schließen</button>
</div>
<div id="deviceOverlayBody">
<div class="formRow">
<div class="formCol">
<label for="devHostname">Hostname</label>
<input type="text" id="devHostname">
</div>
<div class="formCol">
<label for="devIP">IP-Adresse</label>
<input type="text" id="devIP">
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
<label for="devNotes">Notizen (Gerät)</label>
<textarea id="devNotes"></textarea>
</div>
</div>
<div id="connHeaderRow">
<div>Verbindungen <span class="connHint">immer Quelle → Ziel · Gegenrichtung beim Zielgerät anlegen</span></div>
<button type="button" class="btnGhost" id="btnAddConn">Verbindung hinzufügen</button>
</div>
<table id="connTable">
<thead>
<tr>
<th>Dienst</th>
<th>Ziel</th>
<th style="width:90px">Port</th>
<th>Notizen</th>
<th style="width:44px">Aktion</th>
</tr>
</thead>
<tbody id="connTableBody"></tbody>
</table>
</div>
<div id="deviceOverlayFooter">
<button type="button" class="btnDanger" id="btnDeviceDelete">Gerät löschen</button>
<button type="button" class="btnPrimary" id="btnDeviceSave">Übernehmen</button>
</div>
</div>
</div>
<script>
const appConfigInitial=<?php echo $appConfigJson;?>;
const iconBase=<?php echo json_encode($networking_iconbase,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);?>;
</script>
<script>
(function(){
'use strict';
let appConfig=appConfigInitial&&typeof appConfigInitial==='object'?appConfigInitial:{devices:[]};
if(!Array.isArray(appConfig.devices))appConfig.devices=[];
let editingIndex=null;
const deviceTableBody=document.getElementById('deviceTableBody');
const deviceCountValue=document.getElementById('deviceCountValue');
const btnAddDevice=document.getElementById('btnAddDevice');
const btnSaveConfig=document.getElementById('btnSaveConfig');
const saveForm=document.getElementById('saveForm');
const configJsonInput=document.getElementById('config_json');
const overlay=document.getElementById('deviceOverlay');
const overlayTitle=document.getElementById('deviceOverlayTitle');
const btnDeviceClose=document.getElementById('btnDeviceClose');
const btnDeviceSave=document.getElementById('btnDeviceSave');
const btnDeviceDelete=document.getElementById('btnDeviceDelete');
const devHostname=document.getElementById('devHostname');
const devIP=document.getElementById('devIP');
const devType=document.getElementById('devType');
const devTypeIcon=document.getElementById('devTypeIcon');
const devKind=document.getElementById('devKind');
const devNotes=document.getElementById('devNotes');
const connTableBody=document.getElementById('connTableBody');
const btnAddConn=document.getElementById('btnAddConn');
function tt(v){if(v===null||v===undefined)return'';return String(v);}
const deviceTypes=[
{v:'Firewall',icon:'firewall.svg'},
{v:'Router',icon:'router.svg'},
{v:'Switch',icon:'switch.svg'},
{v:'Access Point',icon:'wifi.svg'},
{v:'Server',icon:'server.svg'},
{v:'Windows-Server',icon:'windows.svg'},
{v:'Linux-Server',icon:'linux.svg'},
{v:'Domain-Controller',icon:'ad.svg'},
{v:'DNS-Server',icon:'dns.svg'},
{v:'DHCP-Server',icon:'dhcp.svg'},
{v:'Hypervisor',icon:'hypervisor.svg'},
{v:'Storage/NAS',icon:'storage.svg'},
{v:'Backup',icon:'backup.svg'},
{v:'Datenbank',icon:'database.svg'},
{v:'Mail-Server',icon:'mail.svg'},
{v:'Web-Server',icon:'web.svg'},
{v:'Proxy',icon:'proxy.svg'},
{v:'VPN-Gateway',icon:'vpn.svg'},
{v:'RDP/Terminal-Server',icon:'rdp.svg'},
{v:'Monitoring',icon:'monitoring.svg'},
{v:'Drucker',icon:'printer.svg'},
{v:'Kamera',icon:'camera.svg'},
{v:'VoIP-Telefon',icon:'phone.svg'},
{v:'USV',icon:'ups.svg'},
{v:'Cloud-Dienst',icon:'cloud.svg'},
{v:'IoT-Gerät',icon:'iot.svg'},
{v:'PC',icon:'pc.svg'},
{v:'Laptop',icon:'laptop.svg'},
{v:'Gerät (Sonstiges)',icon:'device.svg'}
];
function typeIconFor(t){
for(const e of deviceTypes){if(e.v===t)return iconBase+e.icon;}
return iconBase+'device.svg';
}
function updateTypeIcon(){devTypeIcon.src=typeIconFor(devType.value);}
function fillTypeSelect(current){
devType.innerHTML='';
let found=false;
for(const e of deviceTypes){
const o=document.createElement('option');
o.value=e.v;
o.textContent=e.v;
if(e.v===current){o.selected=true;found=true;}
devType.appendChild(o);
}
if(current&&!found){
const o=document.createElement('option');
o.value=current;
o.textContent=current+' (bestehend)';
o.selected=true;
devType.appendChild(o);
}
updateTypeIcon();
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
function deviceTargetOptions(){
const opts=[];
const alias={};
const devices=appConfig.devices||[];
for(let i=0;i<devices.length;i++){
if(editingIndex!==null&&i===editingIndex)continue;
const d=devices[i]||{};
const hn=tt(d.Hostname||d.hostname||'');
const ip=tt(d.IP||d.ip||'');
const val=hn||ip;
if(!val)continue;
opts.push({value:val,label:hn&&ip?hn+' ('+ip+')':val});
if(ip)alias[ip]=val;
if(hn)alias[hn]=val;
}
return{opts:opts,alias:alias};
}
function targetTypeFor(target){
const devices=appConfig.devices||[];
for(const d of devices){
if(tt((d||{}).Hostname||(d||{}).hostname||'')===target)return'hostname';
}
for(const d of devices){
if(tt((d||{}).IP||(d||{}).ip||'')===target)return'ip';
}
return'';
}
function renderDeviceTable(){
deviceTableBody.innerHTML='';
const devices=appConfig.devices||[];
deviceCountValue.textContent=String(devices.length);
document.getElementById('emptyHint').style.display=devices.length===0?'block':'none';
for(let i=0;i<devices.length;i++){
const d=devices[i]||{};
const tr=document.createElement('tr');
const tdHost=document.createElement('td');
tdHost.textContent=tt(d.Hostname||d.hostname||'');
tr.appendChild(tdHost);
const tdIP=document.createElement('td');
tdIP.textContent=tt(d.IP||d.ip||'');
tr.appendChild(tdIP);
const tdType=document.createElement('td');
tdType.textContent=tt(d.Type||d.type||'');
tr.appendChild(tdType);
const tdKind=document.createElement('td');
tdKind.textContent=tt(d.Kind||d.kind||'')||'–';
tr.appendChild(tdKind);
const tdNotes=document.createElement('td');
const notesStr=tt(d.Notes||d.notes||'').trim();
if(notesStr!==''){
const span=document.createElement('span');
span.className='badgeSmall';
span.textContent='Notizen vorhanden';
tdNotes.appendChild(span);
}else tdNotes.textContent='–';
tr.appendChild(tdNotes);
const conns=Array.isArray(d.Connections)?d.Connections:[];
const tdConn=document.createElement('td');
tdConn.textContent=String(conns.length);
tr.appendChild(tdConn);
const tdAct=document.createElement('td');
tdAct.className='actions';
const btnEdit=document.createElement('button');
btnEdit.type='button';
btnEdit.className='btnGhost';
btnEdit.textContent='Bearbeiten';
btnEdit.addEventListener('click',function(){openDeviceEditor(i);});
tdAct.appendChild(btnEdit);
const btnDel=document.createElement('button');
btnDel.type='button';
btnDel.className='btnGhost';
btnDel.style.marginLeft='4px';
btnDel.textContent='Löschen';
btnDel.addEventListener('click',function(){deleteDevice(i);});
tdAct.appendChild(btnDel);
tr.appendChild(tdAct);
deviceTableBody.appendChild(tr);
}
}
function openDeviceEditor(index){
const isNew=index===null||index===undefined||index<0;
editingIndex=isNew?null:index;
if(isNew){
overlayTitle.textContent='Neues Gerät';
devHostname.value='';
devIP.value='';
fillTypeSelect('Gerät (Sonstiges)');
devKind.value='Physisch';
devNotes.value='';
connTableBody.innerHTML='';
}else{
const d=appConfig.devices[index]||{};
overlayTitle.textContent='Gerät bearbeiten';
devHostname.value=tt(d.Hostname||d.hostname||'');
devIP.value=tt(d.IP||d.ip||'');
fillTypeSelect(tt(d.Type||d.type||''));
const kind=tt(d.Kind||d.kind||'');
devKind.value=kind==='VM'||kind==='Extern'?kind:'Physisch';
devNotes.value=tt(d.Notes||d.notes||'');
connTableBody.innerHTML='';
const conns=Array.isArray(d.Connections)?d.Connections:[];
for(let i=0;i<conns.length;i++){
addConnectionRow(conns[i]);
}
}
overlay.style.display='flex';
}
function closeDeviceEditor(){
overlay.style.display='none';
editingIndex=null;
}
function mkSelect(options,initial){
const sel=document.createElement('select');
let found=false;
for(const o of options){
const opt=document.createElement('option');
opt.value=o.value;
opt.textContent=o.label;
if(o.value===tt(initial)){opt.selected=true;found=true;}
sel.appendChild(opt);
}
if(!found&&tt(initial)!==''){
const opt=document.createElement('option');
opt.value=tt(initial);
opt.textContent=tt(initial);
opt.selected=true;
sel.appendChild(opt);
}
return sel;
}
function addConnectionRow(conn){
const c=conn||{};
const tr=document.createElement('tr');
const tdType=document.createElement('td');
const svcOpts=[{value:'',label:'– Dienst –'}];
for(const k of serviceKeys())svcOpts.push({value:k,label:k});
const inpType=mkSelect(svcOpts,tt(c.connType||c.service||''));
tdType.appendChild(inpType);
tr.appendChild(tdType);
const tdTarget=document.createElement('td');
const targetWrap=document.createElement('div');
targetWrap.className='targetCell';
const tOpts=[{value:'',label:'– Ziel wählen –'}];
const devOpts=deviceTargetOptions();
for(const o of devOpts.opts)tOpts.push(o);
tOpts.push({value:'__extern__',label:'Extern / manuell…'});
const curTarget=tt(c.target||'');
let selVal='';
if(curTarget!==''){
if(devOpts.alias[curTarget])selVal=devOpts.alias[curTarget];
else selVal='__extern__';
}
const selTarget=mkSelect(tOpts,selVal);
const inpManual=document.createElement('input');
inpManual.type='text';
inpManual.placeholder='Hostname oder IP (extern)';
inpManual.value=selVal==='__extern__'?curTarget:'';
inpManual.style.display=selVal==='__extern__'?'block':'none';
selTarget.addEventListener('change',function(){inpManual.style.display=selTarget.value==='__extern__'?'block':'none';});
targetWrap.appendChild(selTarget);
targetWrap.appendChild(inpManual);
tdTarget.appendChild(targetWrap);
tr.appendChild(tdTarget);
const tdPort=document.createElement('td');
const inpPort=document.createElement('input');
inpPort.type='number';
inpPort.min='1';
inpPort.max='65535';
inpPort.placeholder='443';
inpPort.style.width='72px';
if(c.port!==undefined&&c.port!==null&&c.port!==''){
const n=Number(c.port);
if(!Number.isNaN(n))inpPort.value=String(n);
}
tdPort.appendChild(inpPort);
tr.appendChild(tdPort);
const tdNotes=document.createElement('td');
const inpNotes=document.createElement('input');
inpNotes.type='text';
inpNotes.value=tt(c.Notes||c.notes||'');
tdNotes.appendChild(inpNotes);
tr.appendChild(tdNotes);
const tdAct=document.createElement('td');
const btnRemove=document.createElement('button');
btnRemove.type='button';
btnRemove.className='btnGhost';
btnRemove.textContent='X';
btnRemove.addEventListener('click',function(){tr.remove();});
tdAct.appendChild(btnRemove);
tr.appendChild(tdAct);
tr._connInputs={inpType,selTarget,inpManual,inpPort,inpNotes};
connTableBody.appendChild(tr);
}
function collectConnections(){
const rows=Array.from(connTableBody.querySelectorAll('tr'));
const list=[];
for(const tr of rows){
const refs=tr._connInputs;
if(!refs)continue;
const connType=refs.inpType.value;
let target=refs.selTarget.value;
if(target==='__extern__')target=refs.inpManual.value.trim();
const portStr=refs.inpPort.value.trim();
const notes=refs.inpNotes.value.trim();
if(connType===''&&target===''&&notes==='')continue;
const obj={};
if(connType!=='')obj.connType=connType;
if(target!==''){
obj.target=target;
const ttg=targetTypeFor(target);
if(ttg!=='')obj.targetType=ttg;
}
if(portStr!==''){
const n=Number(portStr);
if(!Number.isNaN(n)&&n>0)obj.port=n;
}
if(notes!=='')obj.Notes=notes;
list.push(obj);
}
return list;
}
function saveDeviceFromEditor(){
const hn=devHostname.value.trim();
const ip=devIP.value.trim();
const type=devType.value;
const kind=devKind.value;
const notes=devNotes.value.trim();
if(hn===''&&ip===''){alert('Mindestens Hostname oder IP-Adresse muss gesetzt sein.');return;}
const dev={};
if(hn!=='')dev.Hostname=hn;
if(ip!=='')dev.IP=ip;
if(type!=='')dev.Type=type;
dev.Kind=kind;
if(notes!=='')dev.Notes=notes;
dev.Connections=collectConnections();
if(editingIndex===null){
appConfig.devices.push(dev);
}else{
appConfig.devices[editingIndex]=dev;
}
renderDeviceTable();
closeDeviceEditor();
}
function deleteDevice(index){
if(!confirm('Gerät wirklich löschen?'))return;
appConfig.devices.splice(index,1);
renderDeviceTable();
}
function saveConfig(){
configJsonInput.value=JSON.stringify(appConfig);
saveForm.submit();
}
btnAddDevice.addEventListener('click',function(){openDeviceEditor(null);});
btnSaveConfig.addEventListener('click',saveConfig);
btnDeviceClose.addEventListener('click',closeDeviceEditor);
btnDeviceSave.addEventListener('click',saveDeviceFromEditor);
btnDeviceDelete.addEventListener('click',function(){
if(editingIndex===null){closeDeviceEditor();return;}
if(!confirm('Gerät wirklich löschen?'))return;
appConfig.devices.splice(editingIndex,1);
renderDeviceTable();
closeDeviceEditor();
});
btnAddConn.addEventListener('click',function(){addConnectionRow(null);});
overlay.addEventListener('click',function(e){if(e.target===overlay)closeDeviceEditor();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeDeviceEditor();});
devType.addEventListener('change',updateTypeIcon);
renderDeviceTable();
})();
</script>
</body>
</html>
