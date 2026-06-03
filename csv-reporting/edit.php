<?php
if(!is_dir($csvreporting_jspnbakdir))@mkdir($csvreporting_jspnbakdir,0775,true);
$default=['header_line'=>'','show_columns'=>[],'rules'=>[],'column_renames'=>[],'enable_links'=>false,'column_links'=>[]];
$load_error='';$save_msg='';
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='save'){
$incoming=$_POST['json']??'';$decoded=json_decode($incoming,true);
if($decoded===null&&trim($incoming)!==''){$save_msg='Fehler: Ungültiges JSON.';$parsed=$default;}
else{if(!is_array($decoded))$decoded=$default;
if(!isset($decoded['rules'])||!is_array($decoded['rules']))$decoded['rules']=[];
if(!isset($decoded['show_columns'])||!is_array($decoded['show_columns']))$decoded['show_columns']=[];
if(!isset($decoded['column_renames'])||!is_array($decoded['column_renames']))$decoded['column_renames']=[];
if(!isset($decoded['column_links'])||!is_array($decoded['column_links']))$decoded['column_links']=[];
$ts=date('Ymd_His');if(is_readable($csvreporting_jsondir))@copy($csvreporting_jsondir,$csvreporting_jspnbakdir.'/rules.'.$ts.'.bak');
$ok=@file_put_contents($csvreporting_jsondir,json_encode($decoded,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
$save_msg=$ok===false?'Fehler beim Speichern.':'Gespeichert.';$parsed=$decoded;}}
if(!isset($parsed)){
$raw=is_readable($csvreporting_jsondir)?@file_get_contents($csvreporting_jsondir):json_encode($default,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$parsed=@json_decode((string)$raw,true);
if(!is_array($parsed)){$parsed=$default;$load_error='rules.json beschädigt – Standard geladen.';}}
$header_line=(string)($parsed['header_line']??'');
$show_columns=is_array($parsed['show_columns']??null)?$parsed['show_columns']:[];
$column_renames=is_array($parsed['column_renames']??null)?$parsed['column_renames']:[];
$enable_links=!empty($parsed['enable_links']);
$column_links=is_array($parsed['column_links']??null)?$parsed['column_links']:[];
$csvCols=[];//$csvreporting_csvdir=__DIR__.'/'.$csvreporting_csvfile;$csvCols=[];
if(is_readable($csvreporting_csvdir)){$fh=@fopen($csvreporting_csvdir,'r');if($fh){$first=@fgetcsv($fh);@fclose($fh);if(is_array($first))$csvCols=array_map('trim',$first);}}
if($header_line===''&&$csvCols)$header_line=implode(',',$csvCols);
if(!$show_columns)$show_columns=$csvCols;
$cols=$csvCols?:array_map('trim',explode(',',$header_line));if(!is_array($cols))$cols=[];
$parsed['header_line']=$header_line;$parsed['show_columns']=$show_columns;$parsed['rules']=is_array($parsed['rules']??null)?$parsed['rules']:[];
$parsed['column_renames']=$column_renames;$parsed['enable_links']=$enable_links;$parsed['column_links']=$column_links;
$data_json=json_encode($parsed,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$columns_json=json_encode(array_values($cols),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="color-scheme" content="only light">
<title><?=$csvreporting_editortitle?></title>
<link rel="icon" href="https://raw.githubusercontent.com/florianthepro/pages/main/csv-reporting/edit.svg"><style>
body{font-family:Arial,Helvetica,sans-serif;margin:18px;background:#fff;color:#000}
h1,h2{margin:0 0 8px 0}
section{margin-top:18px}
textarea,input[type=text],select{padding:4px;border:1px solid #ccc;border-radius:3px;font-size:14px}
textarea{width:100%;min-height:40px}
button{padding:4px 10px;border:1px solid #444;border-radius:3px;background:#eee;cursor:pointer}
button.primary{background:#0070ff;color:#fff;border-color:#0050c0}
button.small{font-size:12px;padding:2px 6px}
table{border-collapse:collapse;width:100%;margin-top:6px}
th,td{border:1px solid #ddd;padding:4px;font-size:13px;vertical-align:top}
th{background:#f6f6f6}
.error{color:#900;margin-top:6px;font-weight:bold}
.info{font-size:12px;margin-top:4px;color:#333}
.condition-row{display:flex;gap:6px;margin-top:4px;align-items:center}
.condition-row select{width:220px}
.condition-row input[type=text]{flex:1}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:999}
.modal{background:#fff;padding:12px;border-radius:4px;min-width:340px;max-width:820px;max-height:90vh;overflow:auto}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.modal-title{font-size:16px;font-weight:bold}
.modal-close{background:none;border:1px solid #bbb;border-radius:3px;font-size:18px;line-height:16px;padding:2px 8px;cursor:pointer}
.fk-menu-btn{position:fixed;top:10px;left:10px;background:#111;color:#fff;border:none;padding:10px;font-size:20px;border-radius:4px;cursor:pointer;z-index:1000}
.fk-menu-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;z-index:999}
.fk-menu-overlay.is-visible{display:block}
.fk-menu-panel{position:absolute;top:0;left:0;width:260px;height:100%;background:#fff;padding:20px;box-sizing:border-box;transform:translateX(-100%);transition:.25s}
.fk-menu-overlay.is-visible .fk-menu-panel{transform:translateX(0)}
.fk-menu-nav{display:flex;flex-direction:column;gap:12px;margin-top:20px}
.fk-menu-link{text-decoration:none;font-size:18px;color:#222}
.fk-menu-link:hover{color:#0070ff}
.group-row{background:#f0f0f0;cursor:pointer;user-select:none}
.group-row:hover{background:#e7e7e7}
.group-title{display:flex;justify-content:space-between;align-items:center}
.group-icon{font-weight:bold;color:#0070ff}
.badge{display:inline-block;font-size:11px;border:1px solid #bbb;border-radius:10px;padding:1px 8px;margin-left:8px;background:#fff}
.badge.on{border-color:#0a0;background:#e8ffe8}
.badge.off{border-color:#a00;background:#ffe8e8}
.badge.info{border-color:#0070ff;background:#e8f1ff;color:#0048b5;cursor:pointer}
.view-toggle{display:inline-flex;align-items:center;gap:6px;margin-left:10px;font-size:13px}
button:disabled{opacity:.45;cursor:not-allowed}
</style>
</head>
<body>
<h1><?=$csvreporting_editorheading?></h1>
<?php if($save_msg){?><div class="info"><?php echo h($save_msg);?></div><?php } ?>
<?php if($load_error){?><div class="error"><?php echo h($load_error);?></div><?php } ?>
<form method="post" id="rules-form" onkeydown="if(event.key==='Enter'&&event.target.tagName==='INPUT')event.preventDefault()">
<input type="hidden" name="action" value="save"><input type="hidden" name="json" id="rules-json">

<section>
<h2>Spalten ausblenden:</h2>
<div style="margin-top:8px"></div><!-- <- --><div style="display:flex;flex-wrap:wrap;gap:8px;"><?php foreach($cols as $c){$hidden=!in_array($c,$show_columns,true);?><label><input type="checkbox" class="show-col-cb" value="<?php echo h($c);?>"<?php if($hidden)echo' checked';?>><?php echo h($column_renames[$c]??$c);?></label><?php } ?></div>
</section>
<section>
<h2>Spalten umbenennen:</h2>
<div class="filter-item"><div class="filter-label"></div><!-- <- --><select id="rename-col-select" onchange="updateRenameInput()"><option value="">-- Spalte wählen --</option><?php foreach($cols as $c): ?><option value="<?php echo h($c);?>"><?php echo h($c);?></option><?php endforeach; ?></select></div><div class="filter-item" style="margin-top:8px"><div class="filter-label">Neuer Anzeigename</div><input type="text" id="rename-col-value"></div>
</section>

<section>
<h2>Generische Links</h2>
<label>
<input type="checkbox" id="enable-links"<?php if($enable_links)echo' checked';?>> aktiv</label><div class="filter-item" style="margin-top:8px"><div class="filter-label">Spalte</div><select id="link-col-select" onchange="updateLinkInput()"><option value="">-- Spalte wählen --</option><?php foreach($cols as $c): ?><option value="<?php echo h($c);?>"><?php echo h($column_renames[$c]??$c);?></option><?php endforeach; ?></select></div><div class="filter-item" style="margin-top:8px"><div class="filter-label">Link mit/ohne `*` oder *</div><input type="text" id="link-col-value" placeholder=""></div></section>
<section>
<h2>Regeln</h2>
<button type="button" class="primary" onclick="openRule(-1)">Regel hinzufügen</button>
<label class="view-toggle"><input type="checkbox" id="view-flat">nur Aktiv/Deaktiv</label>
<table><thead><tr><th style="width:60px">#</th><th>Beschreibung</th><th style="width:280px">Aktionen</th></tr></thead><tbody id="rules-body"></tbody></table>
</section>
<div style="margin-top:14px"><button type="submit" class="primary">Speichern</button><button type="button" onclick="openHelp()" style="margin-left:8px">Hilfe</button></div>
</form>
<div class="modal-backdrop" id="rule-modal"><div class="modal"><div class="modal-header"><div class="modal-title">Regel bearbeiten</div><button type="button" onclick="closeRule()" class="modal-close">×</button></div>
<label>Beschreibung (automatisch)</label><input type="text" id="r-desc" readonly>
<label style="display:block;margin-top:8px">Bedingungen (UND)</label><div id="r-conds"></div><button type="button" class="small" onclick="addCond()" style="margin-top:6px">+ Bedingung</button>
<div style="margin-top:10px;display:flex;justify-content:flex-end;gap:6px"><button type="button" onclick="closeRule()">Abbrechen</button><button type="button" class="primary" onclick="applyRule()">Übernehmen</button></div>
</div></div>
<div class="modal-backdrop" id="info-modal"><div class="modal"><div class="modal-header"><div class="modal-title">Info</div><button type="button" onclick="closeInfo()" class="modal-close">×</button></div>
<textarea id="info-text" style="width:100%;min-height:120px" placeholder="Optionaler Infotext zur Regel..."></textarea>
<div style="margin-top:10px;display:flex;justify-content:flex-end;gap:6px"><button type="button" onclick="closeInfo()">Schließen</button><button type="button" class="primary" onclick="saveInfo()">Speichern</button></div>
</div></div>
<div class="modal-backdrop" id="help-modal"><div class="modal"><div class="modal-header"><div class="modal-title">Regel-Muster Hilfe</div><button type="button" onclick="closeHelp()" class="modal-close">×</button></div>
<ul style="margin:0;padding-left:18px;font-size:13px;line-height:1.4"><li>* → Wildcard</li><li>n/a → leer oder N/A</li><li>&gt;500, &gt;=500, &lt;500, &lt;=500</li><li>100..500, -500..-100, -500..+100</li><li>500 → genau 500</li></ul>
</div></div>
<script>
var DATA=<?php echo $data_json;?>;var COLS=<?php echo $columns_json;?>;
if(!DATA||typeof DATA!=="object")DATA={};if(!Array.isArray(COLS))COLS=[];if(!Array.isArray(DATA.rules))DATA.rules=[];if(!Array.isArray(DATA.show_columns))DATA.show_columns=[];
if(!DATA.column_renames||Array.isArray(DATA.column_renames))DATA.column_renames={};if(!DATA.column_links||Array.isArray(DATA.column_links))DATA.column_links={};DATA.enable_links=!!DATA.enable_links;
var ESC5="$$$$$";
function enc5(s){
s=String(s==null?"":s);
s=s.split(ESC5).join(ESC5+ESC5);
s=s.split("&").join(ESC5+"&"+ESC5);
s=s.split("=").join(ESC5+"="+ESC5);
s=s.split("≠").join(ESC5+"≠"+ESC5);
return s;
}
function dec5(s){
s=String(s==null?"":s);
s=s.split(ESC5+"≠"+ESC5).join("≠");
s=s.split(ESC5+"="+ESC5).join("=");
s=s.split(ESC5+"&"+ESC5).join("&");
s=s.split(ESC5+ESC5).join(ESC5);
return s;
}
function stripInactivePrefix(desc){
desc=String(desc||"").trim();
if(desc.startsWith("!..."))desc=desc.slice(4).trim();
desc=desc.split("...&...").join(" & ").split("...").join(" ").replace(/\s+/g," ").trim();
return desc;
}
function ruleDisplayTitle(r){
if(!r||typeof r!=="object")return "";
var conds=Array.isArray(r.conditions)?r.conditions:[];
if(conds.length>0)return buildAutoDescFromConditions(conds);
return dec5(stripInactivePrefix(r.description||""));
}
var GROUP_OPEN={};try{GROUP_OPEN=JSON.parse(localStorage.getItem("rules_group_open")||"{}")||{};}catch(e){GROUP_OPEN={};}

function persistGroupOpen(){try{localStorage.setItem("rules_group_open",JSON.stringify(GROUP_OPEN));}catch(e){}}
function esc(s){return String(s).replace(/[<>&]/g,m=>({"<":"&lt;",">":"&gt;","&":"&amp;"}[m]));}
function normalizeLegacyDesc(desc){
desc=String(desc||"").trim();
let prefix="";
if(desc.startsWith("!...")){prefix="!...";desc=desc.slice(4).trim();}
desc=desc.split("...&...").join(" & ");
desc=desc.split("...").join(" ");
desc=desc.replace(/\s+/g," ").trim();
return prefix?("!..." + (desc?(" "+desc):"")):desc;
}
function buildAutoDescFromConditions(conds){
if(!Array.isArray(conds)||conds.length===0)return "";
return conds.map(c=>String(c.column||"")+" "+(c.negate?"≠":"=")+" "+String(c.pattern||"")).join(" & ").replace(/\s+/g," ").trim();
}
function parseTitleToConditions(desc){
let t=dec5(stripInactivePrefix(desc));
if(!t)return [];
let parts=t.split(/\s*&\s*/);
let out=[];
for(let p of parts){
p=String(p||"").trim();
if(!p)continue;
let m=p.match(/^(.*?)\s*(≠|=)\s*(.+)$/);
if(!m)continue;
let col=String(m[1]||"").trim();
let op=String(m[2]||"").trim();
let pat=String(m[3]||"").trim();
if(!col||pat==="")continue;
out.push({column:col,pattern:pat,negate:op==="≠"});
}
return out;
}
function updateDescFromDom(){
var rows=document.querySelectorAll("#r-conds .condition-row");var list=[];
rows.forEach(r=>{var c=r.querySelector("select").value.trim();var p=r.querySelector('input[type=text]').value.trim();var n=r.querySelector('input[type=checkbox]').checked;if(c!==""&&p!=="")list.push({column:c,pattern:p,negate:n});});
document.getElementById("r-desc").value=buildAutoDescFromConditions(list)==="!..."?"":buildAutoDescFromConditions(list);
}
function updateRenameInput(){let col=document.getElementById("rename-col-select").value;document.getElementById("rename-col-value").value=(DATA.column_renames&&DATA.column_renames[col])||"";}
document.getElementById("rename-col-value").addEventListener("input",function(){let col=document.getElementById("rename-col-select").value;if(!col)return;let v=this.value.trim();if(v==="")delete DATA.column_renames[col];else DATA.column_renames[col]=v;render();});
function updateLinkInput(){let col=document.getElementById("link-col-select").value;let inp=document.getElementById("link-col-value");if(!col){inp.value="";return;}inp.value=(DATA.column_links&&DATA.column_links[col])||"";}
document.getElementById("link-col-value").addEventListener("input",function(){let col=document.getElementById("link-col-select").value;if(!col)return;if(!DATA.column_links||Array.isArray(DATA.column_links))DATA.column_links={};let v=this.value;if(v==="")delete DATA.column_links[col];else DATA.column_links[col]=v;});
function sync(){
var v=[];document.querySelectorAll(".show-col-cb").forEach(x=>{if(!x.checked)v.push(x.value);});
DATA.show_columns=v;DATA.enable_links=document.getElementById("enable-links").checked;
if(!DATA.column_renames||Array.isArray(DATA.column_renames))DATA.column_renames={};if(!DATA.column_links||Array.isArray(DATA.column_links))DATA.column_links={};
let rc=document.getElementById("rename-col-select").value;let rv=document.getElementById("rename-col-value").value.trim();if(rc){if(rv==="")delete DATA.column_renames[rc];else DATA.column_renames[rc]=rv;}
let lc=document.getElementById("link-col-select").value;let lv=document.getElementById("link-col-value").value;if(lc){if(lv==="")delete DATA.column_links[lc];else DATA.column_links[lc]=lv;}
DATA.rules.forEach(r=>{
if(!r||typeof r!=="object")return;
if(typeof r.info!=="string")r.info=String(r.info||"");
var conds=Array.isArray(r.conditions)?r.conditions:[];
if(conds.length>0){
r.description=buildAutoDescFromConditions(conds);
if(Array.isArray(r.stash_conditions))delete r.stash_conditions;
}else{
let title=dec5(stripInactivePrefix(r.description||""));
r.description="!..."+(title?(" "+enc5(title)):"");
}
});
document.getElementById("rules-json").value=JSON.stringify(DATA);
}
document.getElementById("rules-form").onsubmit=sync;
var VIEW_FLAT=false;
try{VIEW_FLAT=(localStorage.getItem("rules_view_flat")||"")==="1";}catch(e){VIEW_FLAT=false;}
var vb=document.getElementById("view-flat");
if(vb){vb.checked=VIEW_FLAT;vb.addEventListener("change",function(){VIEW_FLAT=!!this.checked;try{localStorage.setItem("rules_view_flat",VIEW_FLAT?"1":"0");}catch(e){}render();});}
function toggleGroup(key){GROUP_OPEN[key]=!GROUP_OPEN[key];persistGroupOpen();applyGroupVisibility();}
function applyGroupVisibility(){
document.querySelectorAll("tr[data-group]").forEach(tr=>{let key=tr.getAttribute("data-group");let open=GROUP_OPEN[key]!==false;let icon=tr.querySelector(".group-icon");if(icon)icon.textContent=open?"▲":"▼";});
document.querySelectorAll("tr[data-group-item]").forEach(tr=>{let key=tr.getAttribute("data-group-item");let open=GROUP_OPEN[key]!==false;tr.style.display=open?"":"none";});
}
function deleteRule(i){if(!confirm("Regel wirklich löschen?"))return;DATA.rules.splice(i,1);render();}
function deactivateRule(i){
var r=DATA.rules[i];if(!r||typeof r!=="object")return;
var conds=Array.isArray(r.conditions)?r.conditions:[];
r.stash_conditions=conds;
r.conditions=[];
var title=buildAutoDescFromConditions(conds);
r.description="!..."+(title?(" "+enc5(title)):"");
render();
}
function activateRule(i){
var r=DATA.rules[i];if(!r||typeof r!=="object")return;
var conds=Array.isArray(r.conditions)?r.conditions:[];
if(conds.length>0)return;
if(Array.isArray(r.stash_conditions)&&r.stash_conditions.length>0){
r.conditions=r.stash_conditions;
delete r.stash_conditions;
r.description=buildAutoDescFromConditions(r.conditions);
render();
return;
}
var parsed=parseTitleToConditions(r.description||"");
if(parsed.length===0){alert("Aktivieren nicht möglich: Titel leer/ungültig (Format: Spalte = Muster & Spalte ≠ Muster).");return;}
r.conditions=parsed;
r.description=buildAutoDescFromConditions(r.conditions);
render();
}
function render(){
let tb=document.getElementById("rules-body");tb.innerHTML="";
let groups={};let inactive=[];let active=[];
DATA.rules.forEach((r,i)=>{
if(!r||typeof r!=="object")return;
let conds=Array.isArray(r.conditions)?r.conditions:[];
if(conds.length===0){inactive.push({i,r});}
else active.push({i,r});
if(!VIEW_FLAT){
let used=[...new Set(conds.map(c=>c.column))];
used.forEach(col=>{if(!groups[col])groups[col]=[];groups[col].push({i,r});});
}
});
function addGroup(key,title,items){
let tr=document.createElement("tr");tr.className="group-row";tr.setAttribute("data-group",key);
let icon=(GROUP_OPEN[key]===false)?"▼":"▲";
tr.innerHTML="<td colspan='3'><div class='group-title'><span>"+esc(title)+"</span><span class='group-icon'>"+icon+"</span></div></td>";
tr.onclick=()=>toggleGroup(key);
tb.appendChild(tr);
items.forEach(x=>{
let r=x.r;let i=x.i;
let conds=Array.isArray(r.conditions)?r.conditions:[];
let enabled=conds.length>0;
if(enabled)r.description=buildAutoDescFromConditions(conds);
else{
let title=dec5(stripInactivePrefix(r.description||""));
r.description="!..."+(title?(" "+enc5(title)):"");
}
let tr2=document.createElement("tr");
tr2.setAttribute("data-group-item",key);
tr2.setAttribute("data-rule-idx",String(i));
tr2.setAttribute("draggable","true");
let hasInfo=!!(r.info&&String(r.info).trim()!=="");
let infoBadge=hasInfo?" <span class='badge info' data-info-idx='"+i+"'>info</span>":"";
tr2.innerHTML="<td>"+(i+1)+"</td><td>"+esc(ruleDisplayTitle(r))+infoBadge+"</td>";
let td=document.createElement("td");td.style.whiteSpace="nowrap";
let b=document.createElement("button");b.type="button";b.textContent="Edit";b.className="small";b.disabled=!enabled;b.onclick=e=>{e.stopPropagation();openRule(i);};
let tog=document.createElement("button");tog.type="button";tog.textContent=enabled?"Deaktivieren":"Aktivieren";tog.className="small";tog.style.marginLeft="6px";tog.onclick=e=>{e.stopPropagation();enabled?deactivateRule(i):activateRule(i);};
let info=document.createElement("button");info.type="button";info.textContent="Info";info.className="small";info.style.marginLeft="6px";info.onclick=e=>{e.stopPropagation();openInfo(i);};
let del=document.createElement("button");del.type="button";del.textContent="X";del.className="small";del.style.marginLeft="6px";del.onclick=e=>{e.stopPropagation();deleteRule(i);};
td.appendChild(b);td.appendChild(tog);td.appendChild(info);td.appendChild(del);
tr2.appendChild(td);
tb.appendChild(tr2);
});
}
if(VIEW_FLAT){
addGroup("__active","Aktiv",active);
addGroup("__inactive","Deaktiv",inactive);
applyGroupVisibility();
attachInfoBadges();
return;
}
if(inactive.length>0)addGroup("__inactive","Inaktiv / Spezial (!...)",inactive);
Object.keys(groups).sort((a,b)=>a.localeCompare(b,undefined,{numeric:true,sensitivity:"base"})).forEach(col=>{
let title=(DATA.column_renames&&DATA.column_renames[col])?DATA.column_renames[col]+" ("+col+")":col;
addGroup(col,title,groups[col]);
});
applyGroupVisibility();
attachGroupDnD();
attachInfoBadges();
}
var DND_STATE=null;
function attachGroupDnD(){
Array.from(document.querySelectorAll("tr[data-group-item]")).forEach(tr=>{
tr.ondragstart=e=>{
let key=tr.getAttribute("data-group-item");
let idx=parseInt(tr.getAttribute("data-rule-idx")||"-1",10);
if(!key||idx<0)return;
DND_STATE={key:key,fromIdx:idx,el:tr};
tr.style.opacity="0.5";
try{e.dataTransfer.setData("text/plain","x");}catch(_){}
e.dataTransfer.effectAllowed="move";
e.stopPropagation();
};
tr.ondragend=()=>{
tr.style.opacity="";
DND_STATE=null;
};
tr.ondragover=e=>{
if(!DND_STATE)return;
let key=tr.getAttribute("data-group-item");
if(key!==DND_STATE.key)return;
e.preventDefault();
e.dataTransfer.dropEffect="move";
let r=tr.getBoundingClientRect();
let before=e.clientY<(r.top+r.height/2);
let parent=tr.parentNode;
if(before)parent.insertBefore(DND_STATE.el,tr);
else parent.insertBefore(DND_STATE.el,tr.nextSibling);
};
tr.ondrop=e=>{
if(!DND_STATE)return;
let key=tr.getAttribute("data-group-item");
if(key!==DND_STATE.key)return;
e.preventDefault();
applyGroupOrderFromDom(DND_STATE.key);
DND_STATE=null;
render();
};
});
}
function applyGroupOrderFromDom(groupKey){
let rows=Array.from(document.querySelectorAll('tr[data-group-item="'+CSS.escape(groupKey)+'"]'));
let domOrder=rows.map(r=>parseInt(r.getAttribute("data-rule-idx")||"-1",10)).filter(n=>Number.isFinite(n)&&n>=0);
let n=DATA.rules.length;
if(domOrder.length===0)return;
let subset=new Set(domOrder);
let orig=DATA.rules.slice();
let p=0;
let out=[];
for(let i=0;i<n;i++){
if(subset.has(i)){out.push(orig[domOrder[p++]]);}
else out.push(orig[i]);
}
DATA.rules=out;
}
let edit=-1;
function openRule(i){
edit=i;
if(i>=0){
let rr=DATA.rules[i];
let cc=(rr&&typeof rr==="object"&&Array.isArray(rr.conditions))?rr.conditions:[];
if(cc.length===0){alert("Diese Regel ist deaktiviert und nicht editierbar. Bitte zuerst aktivieren.");return;}
}
let modal=document.getElementById("rule-modal");
let r=(i>=0&&DATA.rules[i]&&typeof DATA.rules[i]==="object")?DATA.rules[i]:{conditions:[]};
let c=document.getElementById("r-conds");c.innerHTML="";
var conds=Array.isArray(r.conditions)?r.conditions:[];
if(conds.length>0)conds.forEach(x=>addCond(x));else addCond();
updateDescFromDom();
modal.style.display="flex";
}
function closeRule(){document.getElementById("rule-modal").style.display="none";}
function addCond(x){
let d=document.createElement("div");d.className="condition-row";
let s=document.createElement("select");let o=document.createElement("option");o.value="";o.textContent="Spalte";s.appendChild(o);
COLS.forEach(col=>{let op=document.createElement("option");op.value=col;op.textContent=(DATA.column_renames&&DATA.column_renames[col])||col;s.appendChild(op);});
let t=document.createElement("input");t.type="text";t.placeholder="Muster";
let n=document.createElement("input");n.type="checkbox";
let l=document.createElement("label");l.style.fontSize="12px";l.style.display="inline-flex";l.style.alignItems="center";l.style.gap="4px";l.appendChild(n);l.appendChild(document.createTextNode("neg."));
let rm=document.createElement("button");rm.type="button";rm.textContent="×";rm.className="small";rm.onclick=()=>{d.remove();updateDescFromDom();};
if(x){s.value=x.column||"";t.value=x.pattern||"";n.checked=!!x.negate;}
d.appendChild(s);d.appendChild(t);d.appendChild(l);d.appendChild(rm);
document.getElementById("r-conds").appendChild(d);
t.addEventListener("input",updateDescFromDom);s.addEventListener("change",updateDescFromDom);n.addEventListener("change",updateDescFromDom);
}
function applyRule(){
let list=[];
document.querySelectorAll("#r-conds .condition-row").forEach(r=>{
let c=r.querySelector("select").value.trim();
let p=r.querySelector("input[type=text]").value.trim();
let n=r.querySelector("input[type=checkbox]").checked;
if(c!==""&&p!=="")list.push({column:c,pattern:p,negate:n});
});
let rule={conditions:list,description:"!..."};
if(list.length>0)rule.description=buildAutoDescFromConditions(list);
if(edit>=0)DATA.rules[edit]=rule;else DATA.rules.push(rule);
closeRule();render();
}
function openHelp(){document.getElementById("help-modal").style.display="flex";}
function closeHelp(){document.getElementById("help-modal").style.display="none";}
var INFO_EDIT=-1;
function openInfo(i){
INFO_EDIT=i;
let r=DATA.rules[i];
if(!r||typeof r!=="object")return;
if(typeof r.info!=="string")r.info=String(r.info||"");
document.getElementById("info-text").value=r.info||"";
document.getElementById("info-modal").style.display="flex";
}
function closeInfo(){
document.getElementById("info-modal").style.display="none";
INFO_EDIT=-1;
}
function saveInfo(){
if(INFO_EDIT<0)return closeInfo();
let r=DATA.rules[INFO_EDIT];
if(!r||typeof r!=="object")return closeInfo();
r.info=document.getElementById("info-text").value||"";
closeInfo();
render();
}
function attachInfoBadges(){
document.querySelectorAll(".badge.info").forEach(el=>{
el.onclick=function(e){
e.stopPropagation();
let idx=parseInt(el.getAttribute("data-info-idx")||"-1",10);
if(idx>=0)openInfo(idx);
};
});
}
render();
</script>
</body>
</html>
