<?php declare(strict_types=1);
// Geteilte Variablen defensiv einlesen (Muster wie launcher-v2/index.php).
$ticketerstellen_theme=(isset($ticketerstellen_theme)&&in_array($ticketerstellen_theme,['light','dark'],true))?$ticketerstellen_theme:'auto';
$ticketerstellen_title=(isset($ticketerstellen_title)&&is_string($ticketerstellen_title)&&$ticketerstellen_title!=='')?$ticketerstellen_title:'Ticket erstellen';
$ticketerstellen_icon=(isset($ticketerstellen_icon)&&is_string($ticketerstellen_icon)&&$ticketerstellen_icon!=='')?$ticketerstellen_icon:'';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
// Das Tool nutzt seine eigene Dunkel-Logik ueber body.dark. Bei fixem Theme
// setzen wir die Klasse serverseitig, bei 'auto' entscheidet unten das Skript.
$bodyClass=$ticketerstellen_theme==='dark'?' class="dark"':'';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($ticketerstellen_title) ?></title>
<?php if($ticketerstellen_icon!==''): ?>
<link rel="icon" href="<?= h($ticketerstellen_icon) ?>">
<?php endif; ?>
<style>
a:hover{text-decoration:underline;}
.back{margin:10px 10px 20px 0;display:inline-block;}
</style>
<style>
body{font-family:system-ui,Arial,sans-serif;margin:24px;background:var(--bg);color:var(--fg);transition:background .3s,color .3s;}
:root{--bg:#fafafa;--fg:#222;--card-bg:#fafafa;}
body.dark{--bg:#1e1e1e;--fg:#ddd;--card-bg:#2a2a2a;}
h1{font-size:1.25rem;margin-bottom:16px;}
.grid{display:grid;gap:12px;grid-template-columns:1fr 1fr;align-items:stretch;}
.full{grid-column:1/-1;}
label{font-weight:600;display:block;margin-bottom:6px;}
input,textarea,select{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;font-size:14px;background:var(--bg);color:var(--fg);}
textarea{min-height:90px;resize:vertical;}
.card{border:1px solid #ddd;border-radius:12px;padding:16px;background:var(--card-bg);display:flex;flex-direction:column;}
.actions{display:flex;gap:8px;margin-top:12px;}
button{padding:10px 14px;border:none;border-radius:10px;background:#0066ff;color:#fff;font-weight:600;cursor:pointer;}
button.secondary{background:#555;}
.small{font-size:12px;color:#666;}
#output{flex:1;}
</style>
</head>
<body<?= $bodyClass ?>>
<h1>Ticketerstellen</h1>
<form id="noteForm" class="grid">
<div class="card">
<div class="grid">
<div>
<label for="callerName">Name</label>
<input id="callerName" type="text" placeholder="Vorname Nachname" value="Vorname Nachname">
</div>
<div>
<label for="company">Kunde</label>
<input id="company" type="text" placeholder="Kürzel" value="Kürzel">
</div>
<div>
<label for="phone">Telefonnummer</label>
<input id="phone" type="tel" placeholder="+00 0000-0000-0000" value="+00 0000-0000-0000">
</div>
<div>
<label for="device">Gerät</label>
<input id="device" type="text" placeholder="Device-ID" value="Device-ID">
</div>
<div class="full">
<label for="problems">Probleme</label>
<textarea id="problems">Problembeschreibung</textarea>
</div>
<div class="full">
<label for="findings">Festgestellt</label>
<textarea id="findings">Festgestellt Informationen wärend der Problemlösung</textarea>
</div>
<div class="full">
<label for="solution">Lösung</label>
<textarea id="solution"></textarea>
</div>
<div class="full">
<label for="notes">Notizen</label>
<textarea id="notes"></textarea>
<div class="small">Interne Notiz für Informationen für uns</div>
</div>
</div>
</div>
<div class="card">
<label for="output">Copy &amp; Paste (in "* Text:" von einem Ticket)</label>
<textarea id="output" readonly=""></textarea>
<div class="actions">
<button type="button" id="generateBtn">R</button>
<button type="button" id="copyBtn" class="secondary">Copy</button>
</div>
<div id="status" class="small" aria-live="polite"></div>
</div>
</form>
<script>
const els={
callerName:document.getElementById('callerName'),
company:document.getElementById('company'),
phone:document.getElementById('phone'),
device:document.getElementById('device'),
problems:document.getElementById('problems'),
findings:document.getElementById('findings'),
solution:document.getElementById('solution'),
notes:document.getElementById('notes'),
output:document.getElementById('output'),
status:document.getElementById('status'),
generateBtn:document.getElementById('generateBtn'),
copyBtn:document.getElementById('copyBtn')
};
function toBullets(text){
const lines=text.split(/\r?\n/).map(l=>l.trim()).filter(Boolean);
return lines.length?lines.map(l=>`- ${l}`).join('\n'):'';
}
function generateText(){
const name=els.callerName.value.trim();
const company=els.company.value.trim();
const phone=els.phone.value.trim();
const device=els.device.value.trim();
const headerParts=[];
if(name)headerParts.push(name);
if(company)headerParts.push(company);
const header=headerParts.length?headerParts.join(' '):'';
let text='';
if(header||phone||device){
text+=`Kunde rief an: ${header||''}${phone?' | '+phone:''}${device?' | ('+device+')':''}\n\n`;
}
const problems=toBullets(els.problems.value);
const findings=toBullets(els.findings.value);
const solution=toBullets(els.solution.value);
const notes=toBullets(els.notes.value);
if(problems){text+=`Probleme:\n${problems}\n\n`;}
if(findings){text+=`Festgestellt:\n${findings}\n\n`;}
if(solution){text+=`Lösung:\n${solution}\n\n`;}
if(notes){text+=`Notizen:\n${notes}\n\n`;}
els.output.value=text.trim();
els.status.textContent='';
}
function copyText(){
els.output.select();
els.output.setSelectionRange(0,99999);
try{document.execCommand('copy');}catch{}
if(navigator.clipboard&&window.isSecureContext){
navigator.clipboard.writeText(els.output.value)
.then(()=>els.status.textContent='In Zwischenablage kopiert.')
.catch(()=>els.status.textContent='Kopieren fehlgeschlagen.');
}else{
els.status.textContent='In Zwischenablage kopiert.';
}
}
['input','change'].forEach(evt=>{
Object.values(els).forEach(el=>{
if(el instanceof HTMLElement&&['callerName','company','phone','device','problems','findings','solution','notes'].includes(el.id)){
el.addEventListener(evt,generateText);
}
});
});
els.generateBtn.addEventListener('click',generateText);
els.copyBtn.addEventListener('click',copyText);
generateText();
</script>
<?php if($ticketerstellen_theme==='auto'): ?>
<script>
// Nur im Auto-Modus: gespeicherte Wahl bzw. Systemeinstellung folgen (wie im Original).
document.addEventListener("DOMContentLoaded",()=>{
const savedMode=localStorage.getItem("theme");
if(savedMode){
if(savedMode==="dark"){document.body.classList.add("dark");}
}else{
if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){
document.body.classList.add("dark");
}
}
});
</script>
<?php endif; ?>
</body>
</html>
<?php
// Hinweis: Das Tool ist rein clientseitig. Es sendet nichts an einen Server -
// der Text wird lokal erzeugt und per Zwischenablage kopiert. Kein Backend noetig.
