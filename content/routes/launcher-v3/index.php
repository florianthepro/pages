<?php
declare(strict_types=1);
$launcherv3_theme=(isset($launcherv3_theme)&&in_array($launcherv3_theme,['light','dark'],true))?$launcherv3_theme:'auto';
$launcherv3_title=(isset($launcherv3_title)&&$launcherv3_title!=='')?(string)$launcherv3_title:'Launcher';
$launcherv3_icon=(isset($launcherv3_icon)&&is_string($launcherv3_icon)&&$launcherv3_icon!=='')?$launcherv3_icon:'https://raw.githubusercontent.com/florianthepro/pages/main/content/media/launcher-v3/index.svg';
$launcherv3_src=isset($launcherv3_links)&&is_string($launcherv3_links)?$launcherv3_links:'';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

# Host aus einer URL ziehen - dient als sauberer Titel, wenn kein Name angegeben ist.
function launcherv3_host(string $url): string{
$host=(string)parse_url($url,PHP_URL_HOST);
if($host!=='')return $host;
$host=(string)preg_replace('#^[a-z][a-z0-9+.\-]*://#i','',$url);
$host=(string)preg_replace('#[/?\#].*$#','',$host);
return trim($host);
}
# Fehlendes Schema ergaenzen (ausser bei site-relativen Pfaden "/...").
function launcherv3_norm_url(string $url): string{
$url=trim($url);
if($url==='')return '';
if(!preg_match('#^[a-z][a-z0-9+.\-]*://#i',$url)&&$url[0]!=='/')$url='https://'.$url;
return $url;
}
# Automatisches Icon (Favicon des Ziels), wenn kein eigenes Icon gesetzt ist.
function launcherv3_favicon(string $url): string{
return 'https://www.google.com/s2/favicons?sz=128&domain_url='.rawurlencode($url);
}
# Kachel-Quelle robust parsen. Feldtrenner ist "|", die Gruppe steht zuerst - "|"
# kollidiert nie mit dem ":" in URLs (Schema, Ports). Gleiche Gruppennamen werden
# automatisch zusammengefasst (Reihenfolge nach erstem Auftreten), egal wie oft und
# in welcher Reihenfolge sie vorkommen -> immer sauber gruppiert:
#   Gruppe | Name | URL
#   Gruppe | Name | URL | Icon   (4. Feld ersetzt das Ziel-Icon; lokal ODER Web)
# Die URL steht FIX im 3. Feld. Fehlt das 3. Feld, entsteht KEIN Link - so wird ein
# "?=" (oder sonstiger Text) im Namen/2. Feld nie als Adresse missverstanden.
# Klassisch (Gruppen-Kopf "Name:" oder nur "Name" + darunter "Name: URL") wird als
# Rueckfall weiterhin verstanden.
function launcherv3_parse(string $text): array{
$groups=[];$order=[];$current=null;
$ensure=static function(string $g)use(&$groups,&$order): string{
$g=trim($g);
if($g==='')$g='general';
if(!isset($groups[$g])){$groups[$g]=[];$order[]=$g;}
return $g;
};
$add=static function(string $g,string $title,string $url,string $icon)use(&$groups,$ensure): void{
$url=launcherv3_norm_url($url);
if($url==='')return;
$g=$ensure($g);
$title=trim($title);
if($title==='')$title=launcherv3_host($url);
if($title==='')$title=$url;
$groups[$g][]=['title'=>$title,'url'=>$url,'icon'=>trim($icon),'note'=>false];
};
# Platzhalter (2 Felder, keine URL): sichtbar als nicht klickbare "ToDo"-Kachel.
$note=static function(string $g,string $title)use(&$groups,$ensure): void{
$title=trim($title);
if($title==='')return;
$g=$ensure($g);
$groups[$g][]=['title'=>$title,'url'=>'','icon'=>'','note'=>true];
};
foreach(preg_split("/\r\n|\n|\r/",$text) as $raw){
$line=trim($raw);
if($line===''||$line[0]==='#')continue;
if(strpos($line,'|')!==false){
$p=array_map('trim',explode('|',$line));
$n=count($p);
if($n>=4){$add($p[0],$p[1],$p[2],$p[3]);}
elseif($n===3){$add($p[0],$p[1],$p[2],'');}
elseif($n===2){$note($p[0],$p[1]);} # 2 Felder = Platzhalter/ToDo (kein Link)
continue;
}
$colon=strpos($line,':');
if($colon!==false){
$left=trim(substr($line,0,$colon));
$right=trim(substr($line,$colon+1));
if($right!==''){$add($current??'',$left,$right,'');continue;}
$current=$ensure($left);continue;
}
$current=$ensure($line); # nackter Gruppen-Kopf ohne Doppelpunkt
}
$out=[];
foreach($order as $g)$out[]=['name'=>$g,'links'=>$groups[$g]];
return $out;
}
$launcherv3_groups=launcherv3_parse($launcherv3_src);
$dt=$launcherv3_theme==='light'?' data-theme="light"':($launcherv3_theme==='dark'?' data-theme="dark"':'');
?>
<!doctype html>
<html lang="de"<?= $dt ?>>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="default" />
<title><?= h($launcherv3_title) ?></title>
<link rel="icon" href="<?= h($launcherv3_icon) ?>" />
<link rel="apple-touch-icon" href="<?= h($launcherv3_icon) ?>" />
<style>
:root{
--wall-1:#f2f3f7;
--wall-2:#e7e9f0;
--tint-a:rgba(120,150,255,0.10);
--tint-b:rgba(190,140,255,0.10);
--label:#1c1c1e;
--label2:rgba(60,60,67,0.6);
--card:rgba(255,255,255,0.72);
--sheet:rgba(244,245,248,0.72);
--separator:rgba(60,60,67,0.16);
--fill:rgba(118,118,128,0.12);
--tintc:#007aff;
--icon-bg:#ffffff;
--icon-inner:rgba(0,0,0,0.06);
--seg-bg:rgba(118,118,128,0.12);
--seg-active:#ffffff;
--shadow-icon:0 6px 16px rgba(0,0,0,0.12);
--shadow-float:0 8px 30px rgba(0,0,0,0.14);
--shadow-sheet:-1px 0 40px rgba(0,0,0,0.16);
--blur:24px;
--col:88px;
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
--card:rgba(44,44,46,0.6);
--sheet:rgba(30,30,34,0.72);
--separator:rgba(84,84,88,0.6);
--fill:rgba(118,118,128,0.24);
--tintc:#0a84ff;
--icon-bg:#2c2c2e;
--icon-inner:rgba(255,255,255,0.08);
--seg-bg:rgba(118,118,128,0.24);
--seg-active:#636366;
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
--card:rgba(44,44,46,0.6);
--sheet:rgba(30,30,34,0.72);
--separator:rgba(84,84,88,0.6);
--fill:rgba(118,118,128,0.24);
--tintc:#0a84ff;
--icon-bg:#2c2c2e;
--icon-inner:rgba(255,255,255,0.08);
--seg-bg:rgba(118,118,128,0.24);
--seg-active:#636366;
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
[hidden]{display:none!important}
/* ---------- Topbar ---------- */
.topbar{position:fixed;top:0;right:0;z-index:60;padding:max(14px,env(safe-area-inset-top)) max(16px,env(safe-area-inset-right)) 0 0}
.icon-btn{
width:38px;height:38px;border-radius:50%;
background:var(--card);border:0.5px solid var(--separator);color:var(--label);
display:flex;align-items:center;justify-content:center;cursor:pointer;
-webkit-backdrop-filter:blur(var(--blur));backdrop-filter:blur(var(--blur));
box-shadow:var(--shadow-float);transition:transform .15s ease;
}
.icon-btn:hover{transform:scale(1.05)}
.icon-btn:active{transform:scale(0.92)}
.icon-btn svg{width:20px;height:20px;display:block}
/* ---------- Layout: zentrierte Spalte, linksbuendig ausgerichtet ---------- */
.wrap{width:100%;max-width:960px;margin:0 auto;padding:clamp(30px,5vw,56px) clamp(18px,4vw,30px) 72px}
.page-head{display:flex;align-items:center;gap:13px;margin:0 2px 34px}
.page-logo{width:34px;height:34px;border-radius:9px;object-fit:cover;box-shadow:var(--shadow-icon)}
.page-title{margin:0;font-size:clamp(24px,5vw,32px);font-weight:700;letter-spacing:-0.02em;line-height:1.1}
.sections{display:flex;flex-direction:column;gap:clamp(30px,4vw,44px)}
.section-head{margin:0 2px 15px}
.section-head h2{margin:0;font-size:13px;font-weight:600;letter-spacing:0.06em;color:var(--label2);text-transform:uppercase}
/* Feste Spaltenbreite + linksbuendig: die Kacheln liegen immer sauber im Raster,
   egal ob 1 oder 40 in einer Gruppe. */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(var(--col),1fr));gap:clamp(20px,2.4vw,28px) 10px}
.tile{
position:relative;
display:flex;flex-direction:column;align-items:center;gap:8px;width:100%;min-width:0;
text-decoration:none;color:inherit;outline:none;-webkit-tap-highlight-color:transparent;
}
.iconwrap{width:var(--icon);height:var(--icon)}
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
.tile:active .icon{transform:scale(.92)}
.tile:focus-visible .icon{box-shadow:var(--shadow-icon),0 0 0 4px color-mix(in srgb,var(--tintc) 55%,transparent)}
.label{font-size:12.5px;line-height:1.25;font-weight:500;letter-spacing:-0.01em;color:var(--label);max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:center}
/* Link-Beschriftung nur bei Hover/Fokus einblenden (cleaner, reiner Icon-Look).
   Platzhalter (ToDo) behalten ihren Text, da sie kein eigenes Icon haben. */
a.tile .label{
position:absolute;top:100%;left:50%;transform:translate(-50%,-4px);margin-top:7px;
max-width:160px;padding:3px 9px;border-radius:9px;
background:var(--card);border:0.5px solid var(--separator);
-webkit-backdrop-filter:blur(var(--blur));backdrop-filter:blur(var(--blur));box-shadow:var(--shadow-float);
opacity:0;pointer-events:none;z-index:20;transition:opacity .16s ease,transform .16s ease;
}
a.tile:hover .label,a.tile:focus-visible .label{opacity:1;transform:translate(-50%,0)}
/* Touch-Geraete kennen kein Hover -> dort die Beschriftung normal anzeigen */
@media (hover:none){
a.tile .label{position:static;transform:none;margin-top:0;max-width:100%;padding:0;background:none;border:0;box-shadow:none;-webkit-backdrop-filter:none;backdrop-filter:none;opacity:1;pointer-events:auto}
}
/* Platzhalter/ToDo-Kachel: sichtbar, aber kein Link */
.tile.note{cursor:default}
.icon.note{background:transparent;box-shadow:none;border:1.5px dashed var(--separator)}
.icon.note::after{display:none}
.tile.note .label{color:var(--label2)}
.empty-hint{color:var(--label2);font-size:14px;padding:24px 2px}
/* ---------- Einstellungen-Sheet (nur Darstellung) ---------- */
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
.group-title{font-size:12.5px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;color:var(--label2);margin:0 16px 7px}
.segmented{display:flex;background:var(--seg-bg);border-radius:9px;padding:2px;gap:2px}
.segmented button{flex:1;border:0;background:transparent;color:var(--label);font-family:inherit;font-size:13px;font-weight:600;letter-spacing:-0.01em;padding:7px 8px;border-radius:7px;cursor:pointer;transition:background .2s ease,box-shadow .2s ease}
.segmented button[aria-pressed="true"]{background:var(--seg-active);box-shadow:0 1px 3px rgba(0,0,0,0.18),0 0 0 0.5px rgba(0,0,0,0.04)}
.sheet-hint{color:var(--label2);font-size:12.5px;line-height:1.45;margin:20px 8px 0}
@media (prefers-reduced-motion:reduce){*{transition-duration:.001ms!important}}
@media (max-width:480px){:root{--icon:56px;--col:76px}.grid{gap:20px 8px}}
</style>
</head>
<body>
<div class="topbar">
<button id="settingsBtn" class="icon-btn" type="button" aria-label="Einstellungen">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3.2"/><path d="M19.4 13a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V19a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7 17.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.7 7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9.4a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9.4a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/></svg>
</button>
</div>
<div class="wrap">
<header class="page-head">
<img class="page-logo" src="<?= h($launcherv3_icon) ?>" alt="" aria-hidden="true" onerror="this.style.display='none'" />
<h1 class="page-title"><?= h($launcherv3_title) ?></h1>
</header>
<main class="sections" role="main">
<?php if(!$launcherv3_groups): ?>
<div class="empty-hint">Keine Eintr&auml;ge.</div>
<?php else: foreach($launcherv3_groups as $g): ?>
<section class="section">
<div class="section-head"><h2><?= h($g['name']) ?></h2></div>
<div class="grid">
<?php foreach($g['links'] as $l): ?>
<?php if($l['note']): ?>
<span class="tile note" title="<?= h($l['title']) ?>">
<span class="iconwrap"><span class="icon note"></span></span>
<span class="label"><?= h($l['title']) ?></span>
</span>
<?php else:
$custom=($l['icon']!==''&&!preg_match('#^\s*javascript:#i',$l['icon']))?$l['icon']:'';
$src=$custom!==''?$custom:launcherv3_favicon($l['url']);
?>
<a class="tile" href="<?= h($l['url']) ?>" target="_blank" rel="noopener noreferrer" title="<?= h($l['title']) ?>" aria-label="<?= h($l['title']) ?>">
<span class="iconwrap"><span class="icon"><img class="ico" alt="" loading="lazy" src="<?= h($src) ?>" data-url="<?= h($l['url']) ?>" data-title="<?= h($l['title']) ?>" data-icon="<?= h($custom) ?>" /></span></span>
<span class="label"><?= h($l['title']) ?></span>
</a>
<?php endif; ?>
<?php endforeach; ?>
</div>
</section>
<?php endforeach; endif; ?>
</main>
</div>
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
<p class="sheet-hint">Icons kommen automatisch von der Zieladresse; ein eigenes Icon (lokal oder Web) l&auml;sst sich in der Quelle je Kachel angeben.</p>
</aside>
<script>
"use strict";
var THEME_KEY='launcherv3_theme';
function FAVICON(url){return 'https://www.google.com/s2/favicons?sz=128&domain_url='+encodeURIComponent(url);}

/* ---------- Darstellung: Auto / Hell / Dunkel ---------- */
var appearanceSeg=document.getElementById('appearanceSeg');
function storedAppearance(){var v=null;try{v=localStorage.getItem(THEME_KEY);}catch(e){}return (v==='light'||v==='dark')?v:'auto';}
function updateSegUI(mode){
  var btns=appearanceSeg.querySelectorAll('button');
  for(var i=0;i<btns.length;i++){btns[i].setAttribute('aria-pressed',btns[i].dataset.appearance===mode?'true':'false');}
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
appearanceSeg.addEventListener('click',function(e){var b=e.target.closest('button');if(!b)return;applyAppearance(b.dataset.appearance);});

/* ---------- Einstellungen-Sheet ---------- */
var settingsBtn=document.getElementById('settingsBtn');
var settingsPanel=document.getElementById('settingsPanel');
var settingsOverlay=document.getElementById('settingsOverlay');
var closeSettings=document.getElementById('closeSettings');
function openSettings(){settingsPanel.classList.add('active');settingsOverlay.classList.add('active');}
function closeSettingsPanel(){settingsPanel.classList.remove('active');settingsOverlay.classList.remove('active');}
settingsBtn.addEventListener('click',openSettings);
closeSettings.addEventListener('click',closeSettingsPanel);
settingsOverlay.addEventListener('click',closeSettingsPanel);
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeSettingsPanel();});

/* ---------- Icons: eigenes Icon -> Favicon -> Initialen ---------- */
function colorFromHost(host){
  var hh=0,str=host||'';
  for(var i=0;i<str.length;i++){hh=(hh<<5)-hh+str.charCodeAt(i);hh|=0;}
  return 'hsl('+(Math.abs(hh)%360)+' 62% 52%)';
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
function enhanceIcon(img){
  var custom=img.getAttribute('data-icon')||'';
  var url=img.getAttribute('data-url')||'';
  var title=img.getAttribute('data-title')||'';
  img.dataset.stage=custom?'custom':'favicon';
  function onerr(){
    if(img.dataset.stage==='custom'){img.dataset.stage='favicon';img.classList.remove('fill');img.src=FAVICON(url);return;}
    img.dataset.stage='initials';img.classList.add('fill');img.src=initialsDataUrl(title,132,colorFromHost(url));
  }
  img.addEventListener('error',onerr);
  if(img.complete&&img.naturalWidth===0)onerr(); /* war beim Attach schon fehlgeschlagen */
}
var imgs=document.querySelectorAll('img.ico');
for(var i=0;i<imgs.length;i++)enhanceIcon(imgs[i]);

/* ---------- Start ---------- */
updateSegUI(storedAppearance());
</script>
</body>
</html>
