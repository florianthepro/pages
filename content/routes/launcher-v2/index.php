<?php
declare(strict_types=1);
$launcherv2_theme=(isset($launcherv2_theme)&&in_array($launcherv2_theme,['light','dark'],true))?$launcherv2_theme:'auto';
$launcherv2_title=(isset($launcherv2_title)&&$launcherv2_title!=='')?(string)$launcherv2_title:'Launcher';
$launcherv2_icon=(isset($launcherv2_icon)&&is_string($launcherv2_icon)&&$launcherv2_icon!=='')?$launcherv2_icon:'https://raw.githubusercontent.com/florianthepro/pages/main/content/media/launcher-v2/index.svg';
$launcherv2_src=isset($launcherv2_links)&&is_string($launcherv2_links)?$launcherv2_links:'';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

# Kachel-Quelle parsen: Gruppen-Header ("name:") + eingerueckte "Name: URL"-Zeilen.
# Icons kommen zur Laufzeit automatisch von der Zieladresse - hier keine Icon-Logik.
function launcherv2_parse(string $text): array{
$groups=[];$order=[];$current=null;
foreach(preg_split("/\r\n|\n|\r/",$text) as $raw){
$line=rtrim($raw);
$trim=trim($line);
if($trim===''||str_starts_with($trim,'#'))continue;
if(preg_match('/^([^\s#][^:]*):\s*$/u',$line,$m)){
$g=trim($m[1]);
if($g==='')$g='general';
if(!isset($groups[$g])){$groups[$g]=[];$order[]=$g;}
$current=$g;
continue;
}
if(preg_match('/^\s*(.+?)\s*:\s*(\S.*)$/u',$line,$m)){
$title=trim($m[1]);
$url=trim($m[2]);
if($url==='')continue;
if(!preg_match('#^[a-z][a-z0-9+.\-]*://#i',$url)&&$url[0]!=='/')$url='https://'.$url;
$g=$current??'general';
if(!isset($groups[$g])){$groups[$g]=[];$order[]=$g;}
$groups[$g][]=['title'=>$title!==''?$title:$url,'url'=>$url];
}
}
$out=[];
foreach($order as $g)$out[]=['name'=>$g,'links'=>$groups[$g]];
return $out;
}
$launcherv2_groups=launcherv2_parse($launcherv2_src);
$launcherv2_json=json_encode($launcherv2_groups,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
if(!is_string($launcherv2_json))$launcherv2_json='[]';
$dt=$launcherv2_theme==='light'?' data-theme="light"':($launcherv2_theme==='dark'?' data-theme="dark"':'');
?>
<!doctype html>
<html lang="de"<?= $dt ?>>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="default" />
<title><?= h($launcherv2_title) ?></title>
<link rel="icon" href="<?= h($launcherv2_icon) ?>" />
<link rel="apple-touch-icon" href="<?= h($launcherv2_icon) ?>" />
<style>
:root{
--wall-1:#f2f3f7;
--wall-2:#e7e9f0;
--tint-a:rgba(120,150,255,0.10);
--tint-b:rgba(190,140,255,0.10);
--label:#1c1c1e;
--label2:rgba(60,60,67,0.6);
--label3:rgba(60,60,67,0.32);
--card:rgba(255,255,255,0.72);
--sheet:rgba(244,245,248,0.72);
--separator:rgba(60,60,67,0.16);
--fill:rgba(118,118,128,0.12);
--fill2:rgba(118,118,128,0.2);
--tintc:#007aff;
--green:#34c759;
--red:#ff3b30;
--icon-bg:#ffffff;
--icon-inner:rgba(0,0,0,0.06);
--seg-bg:rgba(118,118,128,0.12);
--seg-active:#ffffff;
--switch-off:rgba(120,120,128,0.3);
--badge-bg:#e7e7ec;
--badge-fg:#3a3a3c;
--badge-border:rgba(0,0,0,0.08);
--shadow-icon:0 6px 16px rgba(0,0,0,0.12);
--shadow-float:0 8px 30px rgba(0,0,0,0.14);
--shadow-sheet:-1px 0 40px rgba(0,0,0,0.16);
--blur:24px;
--icon:60px;
--radius:23%;
}
@media (prefers-color-scheme:dark){
:root:not([data-theme="light"]){
--wall-1:#000000;
--wall-2:#0b0b12;
--tint-a:rgba(90,120,255,0.16);
--tint-b:rgba(150,90,255,0.14);
--label:#ffffff;
--label2:rgba(235,235,245,0.6);
--label3:rgba(235,235,245,0.3);
--card:rgba(44,44,46,0.6);
--sheet:rgba(30,30,34,0.72);
--separator:rgba(84,84,88,0.6);
--fill:rgba(118,118,128,0.24);
--fill2:rgba(118,118,128,0.36);
--tintc:#0a84ff;
--green:#30d158;
--red:#ff453a;
--icon-bg:#2c2c2e;
--icon-inner:rgba(255,255,255,0.08);
--seg-bg:rgba(118,118,128,0.24);
--seg-active:#636366;
--switch-off:rgba(120,120,128,0.32);
--badge-bg:#48484a;
--badge-fg:#ffffff;
--badge-border:rgba(255,255,255,0.12);
--shadow-icon:0 8px 18px rgba(0,0,0,0.45);
--shadow-float:0 8px 30px rgba(0,0,0,0.5);
--shadow-sheet:-1px 0 50px rgba(0,0,0,0.6);
}
}
:root[data-theme="dark"]{
--wall-1:#000000;
--wall-2:#0b0b12;
--tint-a:rgba(90,120,255,0.16);
--tint-b:rgba(150,90,255,0.14);
--label:#ffffff;
--label2:rgba(235,235,245,0.6);
--label3:rgba(235,235,245,0.3);
--card:rgba(44,44,46,0.6);
--sheet:rgba(30,30,34,0.72);
--separator:rgba(84,84,88,0.6);
--fill:rgba(118,118,128,0.24);
--fill2:rgba(118,118,128,0.36);
--tintc:#0a84ff;
--green:#30d158;
--red:#ff453a;
--icon-bg:#2c2c2e;
--icon-inner:rgba(255,255,255,0.08);
--seg-bg:rgba(118,118,128,0.24);
--seg-active:#636366;
--switch-off:rgba(120,120,128,0.32);
--badge-bg:#48484a;
--badge-fg:#ffffff;
--badge-border:rgba(255,255,255,0.12);
--shadow-icon:0 8px 18px rgba(0,0,0,0.45);
--shadow-float:0 8px 30px rgba(0,0,0,0.5);
--shadow-sheet:-1px 0 50px rgba(0,0,0,0.6);
}
*{box-sizing:border-box}
html{height:100%;overflow-x:hidden}
body{
min-height:100dvh;
margin:0;
overflow-x:hidden;
display:flex;
flex-direction:column;
font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","SF Pro Display","Segoe UI",Roboto,Helvetica,Arial,sans-serif;
color:var(--label);
background:linear-gradient(180deg,var(--wall-1),var(--wall-2)) fixed;
-webkit-font-smoothing:antialiased;
text-rendering:optimizeLegibility;
transition:background .4s ease,color .3s ease;
}
body::before{
content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;
background:
radial-gradient(60% 45% at 18% 8%,var(--tint-a),transparent 60%),
radial-gradient(55% 45% at 88% 22%,var(--tint-b),transparent 60%);
}
/* ---------- Topbar: Zahnrad / Fertig ---------- */
.topbar{position:fixed;top:0;right:0;z-index:60;padding:max(14px,env(safe-area-inset-top)) max(16px,env(safe-area-inset-right)) 0 0;display:flex;gap:10px;align-items:center}
.icon-btn{
width:38px;height:38px;border-radius:50%;
background:var(--card);border:0.5px solid var(--separator);color:var(--label);
display:flex;align-items:center;justify-content:center;cursor:pointer;
-webkit-backdrop-filter:blur(var(--blur));backdrop-filter:blur(var(--blur));
box-shadow:var(--shadow-float);transition:transform .15s ease,opacity .2s ease;
}
.icon-btn:hover{transform:scale(1.05)}
.icon-btn:active{transform:scale(0.92)}
.icon-btn svg{width:20px;height:20px;display:block}
.done-btn{
border:0;background:transparent;color:var(--tintc);cursor:pointer;
font-size:17px;font-weight:600;padding:8px 6px;letter-spacing:-0.01em;
}
.done-btn:active{opacity:.6}
[hidden]{display:none!important}
/* ---------- Layout ---------- */
.wrap{width:100%;max-width:720px;margin:auto;padding:clamp(28px,5vw,52px) clamp(18px,4vw,28px) 64px}
.page-head{display:flex;align-items:center;justify-content:center;gap:12px;margin:2px 2px 32px}
.page-logo{width:30px;height:30px;border-radius:8px;object-fit:cover;box-shadow:var(--shadow-icon)}
.page-title{margin:0;font-size:clamp(26px,6vw,34px);font-weight:700;letter-spacing:-0.02em;line-height:1.1}
.sections{display:flex;flex-direction:column;gap:36px}
.section-head{display:flex;align-items:center;justify-content:center;gap:10px;margin:0 6px 14px}
.section-head h2{margin:0;font-size:14px;font-weight:600;letter-spacing:0.02em;color:var(--label2);text-transform:uppercase}
.section-add{display:none;width:26px;height:26px;border-radius:50%;border:0;background:var(--fill);color:var(--tintc);font-size:19px;line-height:1;cursor:pointer;align-items:center;justify-content:center;flex:0 0 auto}
.editor-mode .section-add{display:flex}
.section-add:active{transform:scale(.9)}
/* ---------- Icon-Raster ---------- */
.grid{display:flex;flex-wrap:wrap;justify-content:center;gap:26px 20px}
.tile{
display:flex;flex-direction:column;align-items:center;gap:7px;width:72px;
text-decoration:none;color:inherit;outline:none;-webkit-tap-highlight-color:transparent;user-select:none;
}
.iconwrap{position:relative;width:var(--icon);height:var(--icon)}
.icon{
width:100%;height:100%;border-radius:var(--radius);background:var(--icon-bg);
display:flex;align-items:center;justify-content:center;overflow:hidden;
box-shadow:var(--shadow-icon);position:relative;
transition:transform .16s cubic-bezier(.2,.7,.3,1);
}
.icon::after{content:"";position:absolute;inset:0;border-radius:inherit;box-shadow:inset 0 0 0 0.5px var(--icon-inner);pointer-events:none}
.icon img{width:60%;height:60%;object-fit:contain;display:block;pointer-events:none}
.icon img.fill{width:100%;height:100%;object-fit:cover}
.tile:hover .icon{transform:translateY(-2px) scale(1.03)}
.tile:active .icon{transform:scale(.9)}
.tile:focus-visible .icon{box-shadow:var(--shadow-icon),0 0 0 4px color-mix(in srgb,var(--tintc) 55%,transparent)}
.label{font-size:12.5px;line-height:1.25;font-weight:500;letter-spacing:-0.01em;color:var(--label);max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:center}
/* ---------- Editiermodus: Wackeln + Minus-Badge ---------- */
@keyframes jiggle{0%{transform:rotate(-1.1deg)}25%{transform:rotate(1deg)}50%{transform:rotate(-1deg)}75%{transform:rotate(1.1deg)}100%{transform:rotate(-1.1deg)}}
.editor-mode .iconwrap{animation:jiggle .32s infinite ease-in-out}
.editor-mode .tile:nth-child(2n) .iconwrap{animation-duration:.36s;animation-delay:-.1s}
.editor-mode .tile:nth-child(3n) .iconwrap{animation-duration:.3s;animation-delay:-.05s}
.badge{
position:absolute;top:-7px;left:-7px;width:23px;height:23px;border-radius:50%;
background:var(--badge-bg);color:var(--badge-fg);border:0.5px solid var(--badge-border);
display:none;align-items:center;justify-content:center;cursor:pointer;padding:0;z-index:6;
box-shadow:0 1px 4px rgba(0,0,0,0.28);
}
.badge svg{width:13px;height:13px;display:block}
.editor-mode .badge{display:flex}
.dragging .icon{transform:scale(1.08);box-shadow:var(--shadow-float)}
.dragging{opacity:.75}
.placeholder{width:var(--icon);height:var(--icon);border-radius:var(--radius);background:var(--fill);box-sizing:border-box}
/* ---------- Neue Gruppe ---------- */
.add-group{display:none;margin:6px auto 0;background:transparent;border:0;color:var(--tintc);font-size:15px;font-weight:500;cursor:pointer;padding:10px 14px;border-radius:12px}
.add-group:hover{background:var(--fill)}
.editor-mode .add-group{display:inline-flex;align-items:center;gap:6px}
.empty-hint{color:var(--label2);font-size:14px;text-align:center;padding:24px 8px}
/* ---------- Einstellungen-Sheet ---------- */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,0.28);opacity:0;visibility:hidden;transition:opacity .3s ease,visibility .3s ease;z-index:70}
.overlay.active{opacity:1;visibility:visible}
.sheet{
position:fixed;top:0;right:0;height:100dvh;width:min(390px,100%);z-index:80;
background:var(--sheet);-webkit-backdrop-filter:blur(30px) saturate(1.6);backdrop-filter:blur(30px) saturate(1.6);
border-left:0.5px solid var(--separator);box-shadow:var(--shadow-sheet);
transform:translateX(100%);transition:transform .34s cubic-bezier(.32,.72,.24,1);
display:flex;flex-direction:column;padding:max(14px,env(safe-area-inset-top)) 18px calc(18px + env(safe-area-inset-bottom));
}
.sheet.active{transform:translateX(0)}
.sheet-head{display:flex;align-items:center;justify-content:space-between;margin:6px 4px 20px}
.sheet-head h2{margin:0;font-size:22px;font-weight:700;letter-spacing:-0.02em}
.sheet-close{width:30px;height:30px;border-radius:50%;border:0;background:var(--fill);color:var(--label2);display:flex;align-items:center;justify-content:center;cursor:pointer}
.sheet-close svg{width:14px;height:14px}
.sheet-close:active{transform:scale(.9)}
.group-title{font-size:12.5px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;color:var(--label2);margin:22px 16px 7px}
.group-title:first-of-type{margin-top:0}
.group{background:var(--card);border:0.5px solid var(--separator);border-radius:14px;overflow:hidden}
.row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 16px;min-height:48px;width:100%;background:transparent;border:0;text-align:left;color:var(--label);font-size:16px;font-family:inherit}
.row+.row{border-top:0.5px solid var(--separator)}
.row-btn{cursor:pointer}
.row-btn:active{background:var(--fill)}
.row-label{font-size:16px;letter-spacing:-0.01em}
.row.danger .row-label{color:var(--red)}
/* Segmented control */
.segmented{display:flex;background:var(--seg-bg);border-radius:9px;padding:2px;gap:2px}
.segmented button{flex:1;border:0;background:transparent;color:var(--label);font-family:inherit;font-size:13px;font-weight:600;letter-spacing:-0.01em;padding:7px 8px;border-radius:7px;cursor:pointer;transition:background .2s ease,box-shadow .2s ease}
.segmented button[aria-pressed="true"]{background:var(--seg-active);box-shadow:0 1px 3px rgba(0,0,0,0.18),0 0 0 0.5px rgba(0,0,0,0.04)}
/* iOS-Schalter */
.switch{position:relative;display:inline-block;width:51px;height:31px;flex:0 0 auto}
.switch input{position:absolute;opacity:0;width:100%;height:100%;margin:0;cursor:pointer;z-index:1}
.slider{position:absolute;inset:0;border-radius:999px;background:var(--switch-off);transition:background .25s ease}
.slider::before{content:"";position:absolute;top:2px;left:2px;width:27px;height:27px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,0.28);transition:transform .25s cubic-bezier(.32,.72,.24,1)}
.switch input:checked+.slider{background:var(--green)}
.switch input:checked+.slider::before{transform:translateX(20px)}
.sheet-hint{color:var(--label2);font-size:12.5px;line-height:1.45;margin:20px 8px 0}
/* ---------- Kontextmenue ---------- */
.ctx-menu{position:fixed;z-index:9999;min-width:180px;background:var(--sheet);-webkit-backdrop-filter:blur(26px) saturate(1.6);backdrop-filter:blur(26px) saturate(1.6);border:0.5px solid var(--separator);border-radius:14px;box-shadow:var(--shadow-float);padding:6px;display:none;overflow:hidden}
.ctx-menu button{display:flex;align-items:center;justify-content:space-between;gap:14px;width:100%;text-align:left;background:transparent;border:0;color:var(--label);padding:11px 14px;cursor:pointer;border-radius:9px;font-family:inherit;font-size:15px}
.ctx-menu button:hover{background:var(--fill)}
.ctx-menu button.danger{color:var(--red)}
.ctx-menu svg{width:17px;height:17px;flex:0 0 auto;opacity:.9}
@media (prefers-reduced-motion:reduce){.editor-mode .iconwrap{animation:none}*{transition-duration:.001ms!important}}
@media (max-width:480px){:root{--icon:56px}.grid{gap:22px 16px}.tile{width:68px}}
</style>
</head>
<body>
<div class="topbar">
<button id="doneBtn" class="done-btn" type="button" hidden>Fertig</button>
<button id="settingsBtn" class="icon-btn" aria-label="Einstellungen">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3.2"/><path d="M19.4 13a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V19a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7 17.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.7 7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9.4a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9.4a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/></svg>
</button>
</div>
<div class="wrap">
<header class="page-head">
<img class="page-logo" src="<?= h($launcherv2_icon) ?>" alt="" aria-hidden="true" onerror="this.style.display='none'" />
<h1 class="page-title"><?= h($launcherv2_title) ?></h1>
</header>
<main class="sections" id="sections" role="main"></main>
<button id="addGroupBtn" class="add-group" type="button">
<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
Neue Gruppe
</button>
</div>
<noscript>
<div style="max-width:940px;margin:0 auto;padding:20px">
<?php foreach($launcherv2_groups as $g): ?>
<h2><?= h($g['name']) ?></h2>
<ul>
<?php foreach($g['links'] as $l): ?>
<li><a href="<?= h($l['url']) ?>" rel="noopener noreferrer"><?= h($l['title']) ?></a></li>
<?php endforeach; ?>
</ul>
<?php endforeach; ?>
</div>
</noscript>
<div id="settingsOverlay" class="overlay"></div>
<aside id="settingsPanel" class="sheet" role="dialog" aria-label="Einstellungen" aria-modal="true">
<div class="sheet-head">
<h2>Einstellungen</h2>
<button id="closeSettings" class="sheet-close" aria-label="Schlie&szlig;en">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
</button>
</div>
<div class="group-title">Darstellung</div>
<div class="segmented" id="appearanceSeg" role="group" aria-label="Design">
<button type="button" data-appearance="auto">Auto</button>
<button type="button" data-appearance="light">Hell</button>
<button type="button" data-appearance="dark">Dunkel</button>
</div>
<div class="group-title">Startbildschirm</div>
<div class="group">
<label class="row" for="editorSwitch">
<span class="row-label">Bearbeiten</span>
<span class="switch"><input type="checkbox" id="editorSwitch"><span class="slider"></span></span>
</label>
</div>
<div class="group" style="margin-top:22px">
<button class="row row-btn danger" id="resetBtn" type="button">
<span class="row-label">Zur&uuml;cksetzen</span>
</button>
</div>
<p class="sheet-hint">Icons kommen automatisch von der Zieladresse. Im Bearbeiten-Modus: tippen zum &Auml;ndern, &minus; zum Entfernen, ziehen zum Sortieren.</p>
</aside>
<div id="ctxMenu" class="ctx-menu" role="menu" aria-hidden="true"></div>
<script type="application/json" id="launcher-defaults"><?= $launcherv2_json ?></script>
<script>
"use strict";
var DATA_KEY='launcherv2_data';
var THEME_KEY='launcherv2_theme';
var FAVICON=function(url){return 'https://www.google.com/s2/favicons?sz=128&domain_url='+encodeURIComponent(url);};

function readDefaults(){
  try{return JSON.parse(document.getElementById('launcher-defaults').textContent||'[]');}catch(e){return [];}
}
function clone(v){return JSON.parse(JSON.stringify(v));}
function normalize(model){
  if(!Array.isArray(model))return [];
  var out=[];
  model.forEach(function(g){
    if(!g||typeof g!=='object')return;
    var name=typeof g.name==='string'?g.name:'';
    if(name==='')return;
    var links=Array.isArray(g.links)?g.links:[];
    var clean=[];
    links.forEach(function(l){
      if(!l||typeof l!=='object')return;
      var url=typeof l.url==='string'?l.url:'';
      if(!url)return;
      var title=typeof l.title==='string'&&l.title?l.title:url;
      clean.push({title:title,url:url});
    });
    out.push({name:name,links:clean});
  });
  return out;
}
function loadModel(){
  var raw=null;
  try{raw=localStorage.getItem(DATA_KEY);}catch(e){}
  if(raw){
    try{var parsed=normalize(JSON.parse(raw));if(parsed.length||raw==='[]')return parsed;}catch(e){}
  }
  return normalize(readDefaults());
}
function saveModel(){
  try{localStorage.setItem(DATA_KEY,JSON.stringify(state));}catch(e){}
}

var state=loadModel();
var editorMode=false;

var sectionsEl=document.getElementById('sections');
var addGroupBtn=document.getElementById('addGroupBtn');
var ctxMenu=document.getElementById('ctxMenu');
var settingsBtn=document.getElementById('settingsBtn');
var doneBtn=document.getElementById('doneBtn');
var settingsPanel=document.getElementById('settingsPanel');
var settingsOverlay=document.getElementById('settingsOverlay');
var appearanceSeg=document.getElementById('appearanceSeg');
var editorSwitch=document.getElementById('editorSwitch');
var resetBtn=document.getElementById('resetBtn');
var closeSettings=document.getElementById('closeSettings');

/* ---------- Darstellung: Auto / Hell / Dunkel (data-theme wie die anderen Seiten) ---------- */
function storedAppearance(){
  var v=null;try{v=localStorage.getItem(THEME_KEY);}catch(e){}
  return (v==='light'||v==='dark')?v:'auto';
}
function applyAppearance(mode){
  if(mode==='light'||mode==='dark'){
    document.documentElement.setAttribute('data-theme',mode);
    try{localStorage.setItem(THEME_KEY,mode);}catch(e){}
  }else{
    document.documentElement.removeAttribute('data-theme');
    try{localStorage.removeItem(THEME_KEY);}catch(e){}
  }
  updateSegUI(mode);
}
function updateSegUI(mode){
  var btns=appearanceSeg.querySelectorAll('button');
  for(var i=0;i<btns.length;i++){
    btns[i].setAttribute('aria-pressed',btns[i].dataset.appearance===mode?'true':'false');
  }
}
appearanceSeg.addEventListener('click',function(e){
  var b=e.target.closest('button');
  if(!b)return;
  applyAppearance(b.dataset.appearance);
});

/* ---------- Sheet ---------- */
function openSettings(){editorSwitch.checked=editorMode;settingsPanel.classList.add('active');settingsOverlay.classList.add('active');}
function closeSettingsPanel(){settingsPanel.classList.remove('active');settingsOverlay.classList.remove('active');}
settingsBtn.addEventListener('click',openSettings);
closeSettings.addEventListener('click',closeSettingsPanel);
settingsOverlay.addEventListener('click',closeSettingsPanel);

/* ---------- Bearbeiten-Modus ---------- */
function setEditor(on){
  editorMode=!!on;
  document.body.classList.toggle('editor-mode',editorMode);
  editorSwitch.checked=editorMode;
  doneBtn.hidden=!editorMode;
  settingsBtn.hidden=editorMode;
  if(!editorMode)closeContextMenu();
  render();
}
editorSwitch.addEventListener('change',function(){setEditor(editorSwitch.checked);});
doneBtn.addEventListener('click',function(){setEditor(false);});

function resetLauncher(){
  if(!confirm('Alle Kacheln, Reihenfolge und Darstellung zurücksetzen? Das kann nicht rückgängig gemacht werden.'))return;
  try{localStorage.removeItem(DATA_KEY);}catch(e){}
  applyAppearance('auto');
  state=normalize(readDefaults());
  setEditor(false);
  closeSettingsPanel();
}
resetBtn.addEventListener('click',resetLauncher);

/* ---------- Icons: automatisch von der Zieladresse ---------- */
function colorFromHost(host){
  var hh=0,str=host||'';
  for(var i=0;i<str.length;i++){hh=(hh<<5)-hh+str.charCodeAt(i);hh|=0;}
  return 'hsl('+(Math.abs(hh)%360)+' 62% 52%)';
}
function roundRect(ctx,x,y,w,h,r){
  ctx.beginPath();ctx.moveTo(x+r,y);
  ctx.arcTo(x+w,y,x+w,y+h,r);ctx.arcTo(x+w,y+h,x,y+h,r);
  ctx.arcTo(x,y+h,x,y,r);ctx.arcTo(x,y,x+w,y,r);ctx.closePath();
}
function initialsDataUrl(text,size,bg){
  size=size||132;bg=bg||'#3b82f6';
  var initials=(text||'').split(/\s+/).slice(0,2).map(function(s){return s[0];}).join('').toUpperCase()||'?';
  var canvas=document.createElement('canvas');canvas.width=size;canvas.height=size;
  var ctx=canvas.getContext('2d');
  var grad=ctx.createLinearGradient(0,0,0,size);
  grad.addColorStop(0,bg);grad.addColorStop(1,'rgba(0,0,0,0.22)');
  ctx.fillStyle=grad;ctx.fillRect(0,0,size,size);
  ctx.fillStyle='#fff';ctx.textAlign='center';ctx.textBaseline='middle';
  ctx.font='600 '+Math.floor(size*0.4)+'px -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial';
  ctx.fillText(initials,size/2,size/2+Math.floor(size*0.03));
  return canvas.toDataURL('image/png');
}

/* ---------- Rendering ---------- */
function el(tag,cls){var e=document.createElement(tag);if(cls)e.className=cls;return e;}
function svgIcon(paths){
  var s='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'+paths+'</svg>';
  return s;
}

function render(){
  sectionsEl.innerHTML='';
  if(!state.length){
    var hint=el('div','empty-hint');
    hint.textContent=editorMode?'Noch keine Gruppe. Unten „Neue Gruppe“ wählen.':'Keine Einträge.';
    sectionsEl.appendChild(hint);
    return;
  }
  state.forEach(function(group,gi){
    var section=el('section','section');
    var head=el('div','section-head');
    var h2=el('h2');h2.textContent=group.name;
    head.appendChild(h2);
    var addBtn=el('button','section-add');
    addBtn.type='button';addBtn.textContent='+';
    addBtn.setAttribute('aria-label','Zu '+group.name+' hinzufügen');
    addBtn.addEventListener('click',function(ev){ev.preventDefault();ev.stopPropagation();handleAdd(gi);});
    head.appendChild(addBtn);
    section.appendChild(head);

    var grid=el('div','grid');
    grid.dataset.group=String(gi);
    group.links.forEach(function(item,idx){buildTile(grid,gi,item,idx);});
    grid.addEventListener('dragover',function(e){onGridDragOver(e,gi);});
    grid.addEventListener('drop',function(e){onGridDrop(e,gi);});
    section.appendChild(grid);
    sectionsEl.appendChild(section);
  });
}

function buildTile(grid,gi,item,idx){
  var a=el('a','tile');
  a.href=item.url;
  a.target='_blank';
  a.rel='noopener noreferrer';
  a.setAttribute('aria-label',item.title+' öffnen');
  a.title=item.title;
  a.dataset.group=String(gi);
  a.dataset.index=String(idx);
  a.draggable=editorMode;

  var wrap=el('div','iconwrap');
  var icon=el('div','icon');
  var img=document.createElement('img');
  img.alt='';
  img.src=FAVICON(item.url);
  img.addEventListener('error',function(){img.classList.add('fill');img.src=initialsDataUrl(item.title,132,colorFromHost(item.url));});
  icon.appendChild(img);
  wrap.appendChild(icon);

  var badge=el('button','badge');
  badge.type='button';
  badge.setAttribute('aria-label',item.title+' entfernen');
  badge.innerHTML=svgIcon('<path d="M6 12h12"/>');
  badge.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();deleteLink(gi,idx);});
  wrap.appendChild(badge);
  a.appendChild(wrap);

  var label=el('span','label');
  label.textContent=item.title;
  a.appendChild(label);

  a.addEventListener('contextmenu',function(e){
    if(!editorMode)return;
    e.preventDefault();e.stopPropagation();
    openContextMenu(e.clientX,e.clientY,gi,idx);
  });
  a.addEventListener('click',function(e){
    if(editorMode){e.preventDefault();e.stopImmediatePropagation();editLink(gi,idx);}
  });
  a.addEventListener('keydown',function(e){
    if(e.key==='Enter'||e.key===' '){
      e.preventDefault();
      if(editorMode)editLink(gi,idx);else window.open(item.url,'_blank','noopener');
    }
  });
  if(editorMode){
    a.addEventListener('dragstart',function(e){onDragStart(e,gi,idx);});
    a.addEventListener('dragend',onDragEnd);
    a.addEventListener('dragover',onTileDragOver);
    a.addEventListener('drop',function(e){onTileDrop(e,gi);});
  }else{
    a.addEventListener('dragstart',function(e){e.preventDefault();});
  }
  grid.appendChild(a);
}

/* ---------- Kontextmenue (Bearbeiten / Entfernen) ---------- */
function openContextMenu(x,y,gi,idx){
  ctxMenu.innerHTML='';
  var editBtn=el('button');editBtn.type='button';
  editBtn.innerHTML='<span>Bearbeiten</span>'+svgIcon('<path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3z"/>');
  editBtn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();closeContextMenu();editLink(gi,idx);});
  ctxMenu.appendChild(editBtn);
  var delBtn=el('button','danger');delBtn.type='button';
  delBtn.innerHTML='<span>Entfernen</span>'+svgIcon('<path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/>');
  delBtn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();closeContextMenu();deleteLink(gi,idx);});
  ctxMenu.appendChild(delBtn);
  ctxMenu.style.display='block';
  var mw=ctxMenu.offsetWidth,mh=ctxMenu.offsetHeight;
  ctxMenu.style.left=Math.min(x,window.innerWidth-mw-8)+'px';
  ctxMenu.style.top=Math.min(y,window.innerHeight-mh-8)+'px';
  ctxMenu.setAttribute('aria-hidden','false');
}
function closeContextMenu(){ctxMenu.style.display='none';ctxMenu.setAttribute('aria-hidden','true');}
document.addEventListener('click',function(){closeContextMenu();});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeContextMenu();closeSettingsPanel();if(editorMode)setEditor(false);}});

/* ---------- Link-CRUD (nur Name, URL, Gruppe - kein Icon) ---------- */
function normUrl(u){
  u=(u||'').trim();
  if(!u)return '';
  if(!/^[a-z][a-z0-9+.\-]*:\/\//i.test(u)&&u.charAt(0)!=='/')u='https://'+u;
  try{if(u.charAt(0)!=='/')new URL(u);}catch(e){return null;}
  return u;
}
function groupIndexByName(name){
  for(var i=0;i<state.length;i++)if(state[i].name.toLowerCase()===name.toLowerCase())return i;
  return -1;
}
function editLink(gi,idx){
  var item=state[gi].links[idx];
  var newTitle=prompt('Name:',item.title);
  if(newTitle===null)return;
  var newUrl=normUrl(prompt('Adresse (URL):',item.url));
  if(newUrl===null){alert('Ungültige Adresse');return;}
  if(newUrl==='')return;
  var newGroup=prompt('Gruppe:',state[gi].name);
  if(newGroup===null)return;
  newGroup=newGroup.trim()||state[gi].name;
  item.title=(newTitle.trim()||newUrl);
  item.url=newUrl;
  if(newGroup.toLowerCase()!==state[gi].name.toLowerCase()){
    state[gi].links.splice(idx,1);
    var ti=groupIndexByName(newGroup);
    if(ti===-1){state.push({name:newGroup,links:[item]});}
    else{state[ti].links.push(item);}
  }
  saveModel();render();
}
function deleteLink(gi,idx){
  var item=state[gi].links[idx];
  if(!confirm('„'+item.title+'“ entfernen?'))return;
  state[gi].links.splice(idx,1);
  saveModel();render();
}
function handleAdd(gi){
  var title=prompt('Name (z.B. GitHub):');
  if(title===null)return;
  var url=normUrl(prompt('Adresse (URL):'));
  if(url===null){alert('Ungültige Adresse');return;}
  if(url==='')return;
  state[gi].links.push({title:(title.trim()||url),url:url});
  saveModel();render();
}
addGroupBtn.addEventListener('click',function(){
  var name=prompt('Name der neuen Gruppe:');
  if(name===null)return;
  name=name.trim();
  if(!name)return;
  if(groupIndexByName(name)!==-1){alert('Gruppe existiert bereits');return;}
  state.push({name:name,links:[]});
  saveModel();
  if(!editorMode)setEditor(true);else render();
});

/* ---------- Drag & Drop (auch gruppenübergreifend) ---------- */
var dragState=null;
var placeholderEl=null;
function createPlaceholder(){return el('div','placeholder');}
function onDragStart(e,gi,idx){
  if(!editorMode)return;
  dragState={fromGroup:gi,fromIndex:idx};
  e.currentTarget.classList.add('dragging');
  e.dataTransfer.effectAllowed='move';
  var ghost=document.createElement('canvas');ghost.width=1;ghost.height=1;
  e.dataTransfer.setDragImage(ghost,0,0);
}
function onDragEnd(e){
  e.currentTarget.classList.remove('dragging');
  dragState=null;
  if(placeholderEl&&placeholderEl.parentElement)placeholderEl.parentElement.removeChild(placeholderEl);
  placeholderEl=null;
}
function onTileDragOver(e){
  if(!editorMode||!dragState)return;
  e.preventDefault();
  var tile=e.currentTarget;var grid=tile.parentElement;
  if(!placeholderEl)placeholderEl=createPlaceholder();
  var rect=tile.getBoundingClientRect();
  var after=(e.clientX-rect.left)>rect.width/2;
  grid.insertBefore(placeholderEl,after?tile.nextSibling:tile);
}
function onTileDrop(e,gi){if(!editorMode||!dragState)return;e.preventDefault();applyReorder(gi);}
function onGridDragOver(e,gi){
  if(!editorMode||!dragState)return;
  e.preventDefault();
  var grid=e.currentTarget;
  if(!placeholderEl)placeholderEl=createPlaceholder();
  if(!grid.contains(placeholderEl))grid.appendChild(placeholderEl);
}
function onGridDrop(e,gi){if(!editorMode||!dragState)return;e.preventDefault();applyReorder(gi);}
function applyReorder(targetGroup){
  if(!dragState)return;
  var fromArr=state[dragState.fromGroup]?state[dragState.fromGroup].links:null;
  if(!fromArr||dragState.fromIndex<0||dragState.fromIndex>=fromArr.length){dragState=null;return;}
  var targetGrid=null;
  var grids=sectionsEl.querySelectorAll('.grid');
  for(var i=0;i<grids.length;i++){if(String(grids[i].dataset.group)===String(targetGroup)){targetGrid=grids[i];break;}}
  var targetIndex;
  if(targetGrid&&placeholderEl&&targetGrid.contains(placeholderEl)){
    var tiles=Array.prototype.filter.call(targetGrid.childNodes,function(n){return n.classList&&(n.classList.contains('tile')||n.classList.contains('placeholder'));});
    targetIndex=tiles.indexOf(placeholderEl);
    if(targetIndex===-1)targetIndex=state[targetGroup].links.length;
  }else{
    targetIndex=state[targetGroup].links.length;
  }
  var item=fromArr.splice(dragState.fromIndex,1)[0];
  if(dragState.fromGroup===targetGroup&&dragState.fromIndex<targetIndex)targetIndex--;
  if(!state[targetGroup])state[targetGroup]={name:'general',links:[]};
  state[targetGroup].links.splice(targetIndex,0,item);
  saveModel();
  dragState=null;
  if(placeholderEl&&placeholderEl.parentElement)placeholderEl.parentElement.removeChild(placeholderEl);
  placeholderEl=null;
  render();
}

/* ---------- API fuer die Konsole ---------- */
window.Launcher={
  add:function(title,url,group){
    group=group||'general';var u=normUrl(url);if(!u)return;
    var gi=groupIndexByName(group);
    if(gi===-1){state.push({name:group,links:[{title:title||u,url:u}]});}
    else{state[gi].links.push({title:title||u,url:u});}
    saveModel();render();
  },
  reset:function(){resetLauncher();},
  getData:function(){return clone(state);},
  toggleEditorMode:function(on){setEditor(typeof on==='boolean'?on:!editorMode);}
};

/* ---------- Start ---------- */
updateSegUI(storedAppearance());
render();
</script>
</body>
</html>
