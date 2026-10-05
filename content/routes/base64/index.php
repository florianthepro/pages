<?php declare(strict_types=1);
// Base64-Werkzeug: reine Client-Seite (Text/Datei <-> Base64, Data-URL-Helfer).
// Kein Backend noetig - alles laeuft im Browser via btoa/atob und FileReader.

// Geteilte Variablen defensiv einlesen (Muster wie launcher-v2/index.php).
$base64_theme=(isset($base64_theme)&&in_array($base64_theme,['light','dark'],true))?$base64_theme:'auto';
$base64_title=(isset($base64_title)&&is_string($base64_title)&&$base64_title!=='')?(string)$base64_title:'Base64';
$base64_icon=(isset($base64_icon)&&is_string($base64_icon)&&$base64_icon!=='')?$base64_icon:'https://raw.githubusercontent.com/florianthepro/pages/main/content/media/base64/index.svg';

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

// data-theme nur bei fixem Hell/Dunkel setzen, sonst 'auto' (folgt System).
$dt=$base64_theme==='light'?' data-theme="light"':($base64_theme==='dark'?' data-theme="dark"':'');
?>
<!DOCTYPE html>
<html lang="de"<?= $dt ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($base64_title) ?></title>
<link rel="icon" href="<?= h($base64_icon) ?>">
<style>
/* Original-Palette (hell) als Variablen; Dunkel-Variante folgt Theme/System. */
:root{
--bg:#f5f5f5;--fg:#333;--section:#fff;--section-shadow:rgba(0,0,0,.1);
--nav-border:#0078d4;--nav-bg:#e5f1fb;--nav-fg:#005a9e;--nav-active-bg:#0078d4;--nav-active-fg:#fff;
--field-bg:#fff;--field-border:#ccc;--action-bg:#0078d4;--action-border:#0078d4;--action-fg:#fff;
--secondary-bg:#eee;--secondary-border:#666;--secondary-fg:#333;
--muted:#666;--hint:#555;--error:#b00020;
}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){
--bg:#161616;--fg:#e2e2e2;--section:#232323;--section-shadow:rgba(0,0,0,.5);
--nav-border:#2a89dc;--nav-bg:#1b2b3a;--nav-fg:#7ec1ff;--nav-active-bg:#0a84ff;--nav-active-fg:#fff;
--field-bg:#1b1b1b;--field-border:#444;--action-bg:#0a84ff;--action-border:#0a84ff;--action-fg:#fff;
--secondary-bg:#333;--secondary-border:#555;--secondary-fg:#ddd;
--muted:#9a9a9a;--hint:#9a9a9a;--error:#ff6b6b;
}}
:root[data-theme="dark"]{
--bg:#161616;--fg:#e2e2e2;--section:#232323;--section-shadow:rgba(0,0,0,.5);
--nav-border:#2a89dc;--nav-bg:#1b2b3a;--nav-fg:#7ec1ff;--nav-active-bg:#0a84ff;--nav-active-fg:#fff;
--field-bg:#1b1b1b;--field-border:#444;--action-bg:#0a84ff;--action-border:#0a84ff;--action-fg:#fff;
--secondary-bg:#333;--secondary-border:#555;--secondary-fg:#ddd;
--muted:#9a9a9a;--hint:#9a9a9a;--error:#ff6b6b;
}
body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:20px;background:var(--bg);color:var(--fg)}
h1{font-size:1.5rem;margin-bottom:.5rem}
h2{font-size:1.2rem;margin-top:0}
.nav{display:flex;gap:8px;margin:10px 0 20px 0;flex-wrap:wrap}
.nav button{padding:6px 12px;border-radius:4px;border:1px solid var(--nav-border);background:var(--nav-bg);color:var(--nav-fg);cursor:pointer;font-size:.9rem}
.nav button.active{background:var(--nav-active-bg);color:var(--nav-active-fg)}
.section{background:var(--section);border-radius:8px;padding:15px;margin-bottom:15px;box-shadow:0 1px 3px var(--section-shadow)}
textarea{width:100%;min-height:120px;font-family:monospace;font-size:.9rem;padding:8px;box-sizing:border-box;border-radius:4px;border:1px solid var(--field-border);background:var(--field-bg);color:var(--fg);resize:vertical}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:8px 0}
button.action{padding:6px 12px;border-radius:4px;border:1px solid var(--action-border);background:var(--action-bg);color:var(--action-fg);cursor:pointer;font-size:.9rem}
button.secondary{padding:6px 10px;border-radius:4px;border:1px solid var(--secondary-border);background:var(--secondary-bg);color:var(--secondary-fg);cursor:pointer;font-size:.85rem}
button:disabled{opacity:.5;cursor:not-allowed}
label{font-size:.9rem;font-weight:600}
input[type="file"],input[type="text"]{font-size:.85rem;padding:4px 6px;border-radius:4px;border:1px solid var(--field-border);background:var(--field-bg);color:var(--fg)}
small{font-size:.8rem;color:var(--muted)}
.output{word-break:break-all}
.error{color:var(--error);font-size:.85rem;margin-top:4px}
.hint{font-size:.8rem;color:var(--hint)}
code{font-family:monospace}
.page{display:none}
.page.active{display:block}
</style>
</head>
<body>
<div class="nav">
<button class="active" data-page="page-text">Text &rarr; Base64</button>
<button data-page="page-file">Datei &rarr; Base64</button>
<button data-page="page-b64file">Base64 &rarr; Datei</button>
</div>
<div class="page active" id="page-text">
<div class="section">
<h2>Text &rarr; Base64</h2>
<label for="plainText">Klartext</label>
<textarea id="plainText" placeholder="Gib hier deinen Text ein..."></textarea>
<div class="row">
<button id="encodeTextBtn" class="action">Text &rarr; Base64</button>
<button id="decodeTextBtn" class="secondary">Base64 &rarr; Text</button>
</div>
<label for="base64Text">Base64</label>
<textarea id="base64Text" class="output" placeholder="Base64-Ausgabe..."></textarea>
<div class="row">
<button id="copyTextBase64Btn" class="secondary">Base64 kopieren</button>
<button id="copyTextDataUrlBtn" class="secondary">als Data-URL kopieren</button>
<button id="downloadTextBase64Btn" class="secondary">Base64 als .txt</button>
<button id="downloadTextDataUrlBtn" class="secondary">Data-URL als .txt</button>
</div>
<div class="hint">Data-URL f&uuml;r Text: <code>data:text/plain;charset=utf-8;base64,...</code></div>
<div id="textError" class="error"></div>
</div>
</div>
<div class="page" id="page-file">
<div class="section">
<h2>Datei &rarr; Base64</h2>
<div class="row">
<label for="fileInput">Datei ausw&auml;hlen:</label>
<input type="file" id="fileInput">
</div>
<div class="row">
<button id="fileToBase64Btn" class="action" disabled="">Datei in Base64 umwandeln</button>
<small id="fileInfo"></small>
</div>
<label for="fileBase64Output">Base64 der Datei</label>
<textarea id="fileBase64Output" class="output" placeholder="Hier erscheint die Base64-Repr&auml;sentation der Datei..."></textarea>
<div class="row">
<button id="copyFileBase64Btn" class="secondary">Base64 kopieren</button>
<button id="copyFileDataUrlBtn" class="secondary">als Data-URL kopieren</button>
<button id="downloadFileBase64Btn" class="secondary">Base64 als .txt</button>
<button id="downloadFileDataUrlBtn" class="secondary">Data-URL als .txt</button>
</div>
<div class="hint">Data-URL nutzt den MIME-Type der Datei, z.B. <code>data:image/png;base64,...</code></div>
<div id="fileError" class="error"></div>
</div>
</div>
<div class="page" id="page-b64file">
<div class="section">
<h2>Base64 &rarr; Datei</h2>
<label for="base64ToFileInput">Base64 eingeben</label>
<textarea id="base64ToFileInput" placeholder="F&uuml;ge hier Base64 ein (optional mit data:*;base64, Pr&auml;fix)..."></textarea>
<div class="row">
<label for="downloadFileName">Dateiname:</label>
<input type="text" id="downloadFileName" value="datei.bin">
</div>
<div class="row">
<button id="base64ToFileBtn" class="action">Base64 als Datei herunterladen</button>
</div>
<div id="b64FileError" class="error"></div>
</div>
</div>
<script>
const navButtons=document.querySelectorAll('.nav button');const pages=document.querySelectorAll('.page');navButtons.forEach(btn=>{btn.addEventListener('click',()=>{const targetId=btn.getAttribute('data-page');navButtons.forEach(b=>b.classList.remove('active'));btn.classList.add('active');pages.forEach(page=>{if(page.id===targetId){page.classList.add('active')}else{page.classList.remove('active')}})})});function utf8ToBase64(str){return btoa(unescape(encodeURIComponent(str)))}function base64ToUtf8(b64){return decodeURIComponent(escape(atob(b64)))}function copyToClipboard(text){if(!text)return;if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text).catch(()=>fallbackCopy(text))}else{fallbackCopy(text)}}function fallbackCopy(text){const ta=document.createElement('textarea');ta.value=text;document.body.appendChild(ta);ta.select();try{document.execCommand('copy')}catch(e){}document.body.removeChild(ta)}function downloadTextFile(filename,content){const blob=new Blob([content],{type:'text/plain;charset=utf-8'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=filename;document.body.appendChild(a);a.click();document.body.removeChild(a);URL.revokeObjectURL(url)}const plainText=document.getElementById('plainText');const base64Text=document.getElementById('base64Text');const encodeTextBtn=document.getElementById('encodeTextBtn');const decodeTextBtn=document.getElementById('decodeTextBtn');const textError=document.getElementById('textError');const copyTextBase64Btn=document.getElementById('copyTextBase64Btn');const copyTextDataUrlBtn=document.getElementById('copyTextDataUrlBtn');const downloadTextBase64Btn=document.getElementById('downloadTextBase64Btn');const downloadTextDataUrlBtn=document.getElementById('downloadTextDataUrlBtn');encodeTextBtn.addEventListener('click',()=>{textError.textContent='';try{const input=plainText.value||'';const encoded=utf8ToBase64(input);base64Text.value=encoded}catch(e){textError.textContent='Fehler beim Kodieren: '+e.message}});decodeTextBtn.addEventListener('click',()=>{textError.textContent='';try{const input=base64Text.value.trim();if(!input){plainText.value='';return}const decoded=base64ToUtf8(input);plainText.value=decoded}catch(e){textError.textContent='Fehler beim Dekodieren: Ist das gültiges Base64?'}});copyTextBase64Btn.addEventListener('click',()=>{const b64=base64Text.value.trim();if(b64)copyToClipboard(b64)});copyTextDataUrlBtn.addEventListener('click',()=>{const b64=base64Text.value.trim();if(!b64)return;copyToClipboard('data:text/plain;charset=utf-8;base64,'+b64)});downloadTextBase64Btn.addEventListener('click',()=>{const b64=base64Text.value.trim();if(!b64)return;downloadTextFile('text-base64.txt',b64)});downloadTextDataUrlBtn.addEventListener('click',()=>{const b64=base64Text.value.trim();if(!b64)return;downloadTextFile('text-data-url.txt','data:text/plain;charset=utf-8;base64,'+b64)});const fileInput=document.getElementById('fileInput');const fileToBase64Btn=document.getElementById('fileToBase64Btn');const fileBase64Output=document.getElementById('fileBase64Output');const fileInfo=document.getElementById('fileInfo');const fileError=document.getElementById('fileError');const copyFileBase64Btn=document.getElementById('copyFileBase64Btn');const copyFileDataUrlBtn=document.getElementById('copyFileDataUrlBtn');const downloadFileBase64Btn=document.getElementById('downloadFileBase64Btn');const downloadFileDataUrlBtn=document.getElementById('downloadFileDataUrlBtn');let selectedFile=null;let lastFileMimeType='application/octet-stream';fileInput.addEventListener('change',e=>{fileError.textContent='';fileBase64Output.value='';selectedFile=e.target.files[0]||null;if(selectedFile){fileToBase64Btn.disabled=false;lastFileMimeType=selectedFile.type||'application/octet-stream';fileInfo.textContent=`${selectedFile.name} (${lastFileMimeType||'unbekannter Typ'}, ${selectedFile.size} Bytes)`}else{fileToBase64Btn.disabled=true;fileInfo.textContent='';lastFileMimeType='application/octet-stream'}});fileToBase64Btn.addEventListener('click',()=>{fileError.textContent='';if(!selectedFile){fileError.textContent='Bitte zuerst eine Datei auswählen.';return}const reader=new FileReader();reader.onload=function(evt){const result=evt.target.result;const base64=result.split(',')[1]||'';fileBase64Output.value=base64};reader.onerror=function(){fileError.textContent='Fehler beim Lesen der Datei.'};reader.readAsDataURL(selectedFile)});function buildFileDataUrl(base64){return`data:${lastFileMimeType};base64,`+base64}copyFileBase64Btn.addEventListener('click',()=>{const b64=fileBase64Output.value.trim();if(b64)copyToClipboard(b64)});copyFileDataUrlBtn.addEventListener('click',()=>{const b64=fileBase64Output.value.trim();if(!b64)return;copyToClipboard(buildFileDataUrl(b64))});downloadFileBase64Btn.addEventListener('click',()=>{const b64=fileBase64Output.value.trim();if(!b64)return;downloadTextFile('file-base64.txt',b64)});downloadFileDataUrlBtn.addEventListener('click',()=>{const b64=fileBase64Output.value.trim();if(!b64)return;downloadTextFile('file-data-url.txt',buildFileDataUrl(b64))});const base64ToFileInput=document.getElementById('base64ToFileInput');const downloadFileName=document.getElementById('downloadFileName');const base64ToFileBtn=document.getElementById('base64ToFileBtn');const b64FileError=document.getElementById('b64FileError');base64ToFileBtn.addEventListener('click',()=>{b64FileError.textContent='';let b64=base64ToFileInput.value.trim();if(!b64){b64FileError.textContent='Bitte Base64 eingeben.';return}const commaIndex=b64.indexOf(',');if(b64.startsWith('data:')&&commaIndex!==-1){b64=b64.substring(commaIndex+1)}try{const byteCharacters=atob(b64);const byteNumbers=new Array(byteCharacters.length);for(let i=0;i<byteCharacters.length;i++){byteNumbers[i]=byteCharacters.charCodeAt(i)}const byteArray=new Uint8Array(byteNumbers);const blob=new Blob([byteArray]);const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=downloadFileName.value||'download.bin';document.body.appendChild(a);a.click();document.body.removeChild(a);URL.revokeObjectURL(url)}catch(e){b64FileError.textContent='Fehler beim Umwandeln: Ist das gültiges Base64?'}})
</script>
</body>
</html>
