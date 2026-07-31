<?php
session_start();
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
function applyDevicesDerivedDefaults(&$cfg){
foreach($cfg['devices'] as $item){
if(!is_array($item))continue;
$type=(string)($item['Type']??$item['type']??'');
if($type!==''&&!array_key_exists($type,$cfg['typeIcons'])){
$slug=strtolower(trim(preg_replace('/[^A-Za-z0-9]+/','-',$type),'-'));
if($slug==='')$slug='typ';
$cfg['typeIcons'][$type]=$slug.'.svg';
}
$ip=(string)($item['IP']??$item['ip']??'');
if($ip!==''){
$parts=explode('.',$ip);
if(count($parts)===4){
$valid=true;
for($i=0;$i<4;$i++){if(!ctype_digit($parts[$i])||(int)$parts[$i]>255){$valid=false;break;}}
if($valid){
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
function save_config($path,$bakdir,$cfg){
$cfg=normalizeConfig($cfg);
applyDevicesDerivedDefaults($cfg);
$json=json_encode($cfg,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
if($json===false)return false;
if(!is_dir($bakdir))@mkdir($bakdir,0775,true);
if(is_readable($path))@copy($path,rtrim($bakdir,'/').'/config.'.date('Ymd_His').'.bak');
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
if(save_config($networking_jsondir,$networking_jsonbakdir,$decoded))$save_msg='Gespeichert.';
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
#shell{max-width:1200px;margin:0 auto;padding:10px 12px}
#headerRow{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
#headerTitle{font-size:18px;font-weight:bold}
#headerLinks{font-size:12px;display:flex;gap:10px;align-items:center}
#headerLinks a{text-decoration:none;color:#0070ff}
#headerLinks a:hover{text-decoration:underline}
#messages{margin-bottom:8px}
.msg{font-size:12px;padding:4px 6px;border-radius:3px;margin-bottom:4px}
.msg.ok{background:#dcfce7;border:1px solid #16a34a;color:#14532d}
.msg.err{background:#fee2e2;border:1px solid #dc2626;color:#7f1d1d}
#deviceBar{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
#deviceCount{font-size:12px;color:#4b5563}
button{font-family:inherit}
.btnPrimary{border-radius:3px;border:1px solid #0070ff;background:#0070ff;color:#fff;font-size:12px;padding:4px 8px;cursor:pointer}
.btnPrimary:hover{background:#0050c0}
.btnGhost{border-radius:3px;border:1px solid #9ca3af;background:#fff;color:#111827;font-size:12px;padding:3px 6px;cursor:pointer}
.btnGhost:hover{background:#e5e7eb}
table{border-collapse:collapse;width:100%;font-size:12px;background:#fff}
th,td{border:1px solid #e5e7eb;padding:4px 6px;text-align:left}
th{background:#f3f4f6;font-weight:bold;font-size:11px}
td.actions{white-space:nowrap}
.badgeSmall{display:inline-block;padding:1px 4px;border-radius:3px;font-size:10px;border:1px solid #d1d5db;background:#f9fafb;color:#4b5563}
#deviceOverlay{position:fixed;inset:0;background:rgba(0,0,0,0.55);display:none;align-items:center;justify-content:center;z-index:1000}
#deviceOverlayPanel{background:#fff;border-radius:4px;border:1px solid #d1d5db;max-width:720px;width:100%;max-height:90vh;display:flex;flex-direction:column}
#deviceOverlayHeader{display:flex;justify-content:space-between;align-items:center;padding:6px 8px;border-bottom:1px solid #e5e7eb}
#deviceOverlayTitle{font-size:14px;font-weight:bold}
#deviceOverlayBody{padding:6px 8px;overflow:auto}
.formRow{display:flex;gap:8px;margin-bottom:4px}
.formCol{flex:1}
label{display:block;font-size:11px;font-weight:bold;margin-bottom:2px}
input[type="text"],input[type="number"],textarea,select{width:100%;box-sizing:border-box;border-radius:3px;border:1px solid #9ca3af;font-size:12px;padding:3px 4px;font-family:inherit}
textarea{min-height:60px;resize:vertical}
#connHeaderRow{display:flex;justify-content:space-between;align-items:center;margin-top:6px;margin-bottom:4px}
#connTable{border-collapse:collapse;width:100%;font-size:11px;background:#fff}
#connTable th,#connTable td{border:1px solid #e5e7eb;padding:3px 4px}
#connTable th{background:#f9fafb;font-weight:bold;font-size:10px}
#deviceOverlayFooter{display:flex;justify-content:flex-end;gap:6px;padding:6px 8px;border-top:1px solid #e5e7eb}
input.small{font-size:11px;padding:2px 3px}
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
<div id="deviceBar">
<div id="deviceCount">Geräte: <span id="deviceCountValue">0</span></div>
<div>
<button type="button" class="btnGhost" id="btnAddDevice">Neues Gerät</button>
<button type="button" class="btnPrimary" id="btnSaveConfig">Konfiguration speichern</button>
</div>
</div>
<table id="deviceTable">
<thead>
<tr>
<th>Hostname</th>
<th>IP-Adresse</th>
<th>Typ</th>
<th>Notizen</th>
<th>Verbindungen</th>
<th class="actions">Aktionen</th>
</tr>
</thead>
<tbody id="deviceTableBody"></tbody>
</table>
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
<input type="text" id="devType">
</div>
<div class="formCol">
<label for="devNotes">Notizen (Gerät)</label>
<textarea id="devNotes"></textarea>
</div>
</div>
<div id="connHeaderRow">
<div>Verbindungen</div>
<button type="button" class="btnGhost" id="btnAddConn">Verbindung hinzufügen</button>
</div>
<table id="connTable">
<thead>
<tr>
<th>Typ</th>
<th>Zieltyp</th>
<th>Ziel</th>
<th>Richtung</th>
<th>Port</th>
<th>Notizen</th>
<th>Aktion</th>
</tr>
</thead>
<tbody id="connTableBody"></tbody>
</table>
</div>
<div id="deviceOverlayFooter">
<button type="button" class="btnGhost" id="btnDeviceDelete">Gerät löschen</button>
<button type="button" class="btnPrimary" id="btnDeviceSave">Speichern</button>
</div>
</div>
</div>
<script>
const appConfigInitial=<?php echo $appConfigJson;?>;
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
const devNotes=document.getElementById('devNotes');
const connTableBody=document.getElementById('connTableBody');
const btnAddConn=document.getElementById('btnAddConn');
function tt(v){if(v===null||v===undefined)return'';return String(v);}
function renderDeviceTable(){
deviceTableBody.innerHTML='';
const devices=appConfig.devices||[];
deviceCountValue.textContent=String(devices.length);
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
devType.value='';
devNotes.value='';
connTableBody.innerHTML='';
}else{
const d=appConfig.devices[index]||{};
overlayTitle.textContent='Gerät bearbeiten';
devHostname.value=tt(d.Hostname||d.hostname||'');
devIP.value=tt(d.IP||d.ip||'');
devType.value=tt(d.Type||d.type||'');
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
function addConnectionRow(conn){
const c=conn||{};
const tr=document.createElement('tr');
function mkInput(initial){
const inp=document.createElement('input');
inp.type='text';
inp.className='small';
inp.value=tt(initial||'');
return inp;
}
const tdType=document.createElement('td');
const inpType=mkInput(c.connType||c.service||'');
tdType.appendChild(inpType);
tr.appendChild(tdType);
const tdTType=document.createElement('td');
const inpTType=mkInput(c.targetType||'');
inpTType.placeholder='hostname/ip';
tdTType.appendChild(inpTType);
tr.appendChild(tdTType);
const tdTarget=document.createElement('td');
const inpTarget=mkInput(c.target||'');
tdTarget.appendChild(inpTarget);
tr.appendChild(tdTarget);
const tdDir=document.createElement('td');
const inpDir=mkInput(c.direction||'');
inpDir.placeholder='bidirectional';
tdDir.appendChild(inpDir);
tr.appendChild(tdDir);
const tdPort=document.createElement('td');
const inpPort=document.createElement('input');
inpPort.type='number';
inpPort.className='small';
if(c.port!==undefined&&c.port!==null&&c.port!==''){
const n=Number(c.port);
if(!Number.isNaN(n))inpPort.value=String(n);
}
tdPort.appendChild(inpPort);
tr.appendChild(tdPort);
const tdNotes=document.createElement('td');
const inpNotes=mkInput(c.Notes||c.notes||'');
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
tr._connInputs={inpType,inpTType,inpTarget,inpDir,inpPort,inpNotes};
connTableBody.appendChild(tr);
}
function collectConnections(){
const rows=Array.from(connTableBody.querySelectorAll('tr'));
const list=[];
for(const tr of rows){
const refs=tr._connInputs;
if(!refs)continue;
const connType=refs.inpType.value.trim();
const targetType=refs.inpTType.value.trim();
const target=refs.inpTarget.value.trim();
const direction=refs.inpDir.value.trim();
const portStr=refs.inpPort.value.trim();
const notes=refs.inpNotes.value.trim();
if(connType===''&&target===''&&notes==='')continue;
const obj={};
if(connType!=='')obj.connType=connType;
if(targetType!=='')obj.targetType=targetType;
if(target!=='')obj.target=target;
if(direction!=='')obj.direction=direction;
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
const type=devType.value.trim();
const notes=devNotes.value.trim();
if(hn===''&&ip===''){alert('Mindestens Hostname oder IP-Adresse muss gesetzt sein.');return;}
const dev={};
if(hn!=='')dev.Hostname=hn;
if(ip!=='')dev.IP=ip;
if(type!=='')dev.Type=type;
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
renderDeviceTable();
})();
</script>
</body>
</html>
