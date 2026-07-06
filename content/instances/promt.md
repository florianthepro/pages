acktuelle index.php=
```
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Tanum Launcher</title>
<link rel="icon" href="files/ico/v2-tanum-launcher.ico" />
<style>
  :root{
    --bg: #0b1220;
    --panel: #0f1724;
    --muted: #9fb0c8;
    --text: #e6eef8;
    --tile-size: 96px;
    --tile-radius: 22px;
    --gap: 20px;
    --max-width: 1100px;
    --shadow: 0 12px 36px rgba(2,6,23,0.6);
    --accent: #0ea5a4;
  }
  @media (prefers-color-scheme: light) {
    :root:not(.light-mode-forced):not(.dark-mode-forced){
      --bg: #008080;
      --panel: #c0c0c0;
      --muted: #000080;
      --text: #000000;
      --shadow:
        2px 2px 0 #000000,
        -1px -1px 0 #ffffff;
      --accent: #000080;
    }
  }
  :root.light-mode-forced {
    --bg: #008080;
    --panel: #c0c0c0;
    --muted: #000080;
    --text: #000000;
    --shadow:
      2px 2px 0 #000000,
      -1px -1px 0 #ffffff;
    --accent: #000080;
  }
  :root.dark-mode-forced {
    --bg: #0b1220;
    --panel: #0f1724;
    --muted: #9fb0c8;
    --text: #e6eef8;
    --shadow: 0 12px 36px rgba(2, 6, 23, 0.6);
    --accent: #0ea5a4;
  }
  *{box-sizing:border-box}
  html,body{
    height:100%;
    margin:0;
    font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial;
    background:var(--bg);
    color:var(--text);
    -webkit-font-smoothing:antialiased;
    transition: background 0.3s ease, color 0.3s ease;
  }
  .header{
    position:fixed;
    top:0;
    right:0;
    z-index:1000;
    padding:16px;
    display:flex;
    gap:12px
  }
  .settings-btn{
    background:var(--panel);
    border:1px solid rgba(255,255,255,0.06);
    color:var(--text);
    padding:10px 12px;
    border-radius:10px;
    cursor:pointer;
    font-size:18px;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:transform .14s ease, box-shadow .14s ease, background 0.3s ease, color 0.3s ease;
    box-shadow:var(--shadow)
  }
  .settings-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 16px 40px rgba(0,0,0,0.3)
  }
  .settings-overlay{
    position:fixed;
    top:0;
    left:0;
    right:0;
    bottom:0;
    background:rgba(0,0,0,0.5);
    display:none;
    z-index:999
  }
  .settings-overlay.active{
    display:block
  }
  .settings-panel{
    position:fixed;
    top:0;
    right:0;
    height:100vh;
    width:300px;
    background:var(--panel);
    box-shadow:-4px 0 20px rgba(0,0,0,0.3);
    transform:translateX(100%);
    transition:transform .3s ease;
    z-index:1001;
    display:flex;
    flex-direction:column;
    padding:20px;
    color:var(--text);
  }
  .settings-panel.active{
    transform:translateX(0)
  }
  .settings-panel h2{
    margin:0 0 20px 0;
    font-size:18px;
    color:var(--text);
    transition: color 0.3s ease;
  }
  .settings-option{
    background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.06);
    color:var(--text);
    padding:12px 16px;
    border-radius:8px;
    cursor:pointer;
    font-size:14px;
    margin-bottom:10px;
    transition:background .2s ease, color 0.3s ease;
    text-align:left
  }
  .settings-option:hover{
    background:rgba(255,255,255,0.08)
  }
  .settings-option.danger{
    color:#ff6b6b
  }
  .settings-close{
    background:transparent;
    border:0;
    color:var(--text);
    cursor:pointer;
    margin-top:auto;
    padding:10px;
    font-size:14px;
    opacity:0.7;
    transition:opacity .2s, color 0.3s ease
  }
  .settings-close:hover{
    opacity:1
  }
  .wrap{
    min-height:100vh;
    min-height:100dvh;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    padding:clamp(16px, 4vw, 32px);
    padding-top:calc(60px + clamp(8px, 2vh, 24px));
    padding-bottom:clamp(16px, 4vh, 40px);
  }
  .card{
    width:100%;
    max-width:var(--max-width);
    background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.06));
    border-radius:16px;
    padding:28px;
    box-shadow:var(--shadow);
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:28px;
    border:1px solid rgba(255,255,255,0.03);
    backdrop-filter: blur(6px);
    transition: background 0.3s ease, box-shadow 0.3s ease;
  }
  .title{
    font-size:16px;
    font-weight:600;
    letter-spacing:0.2px;
    color:var(--text);
    margin:0;
    display:block;
    transition: color 0.3s ease;
  }
  .section{
    width:100%;
    display:flex;
    flex-direction:column;
    gap:12px
  }
  .section-header{
    display:flex;
    align-items:center;
    gap:10px;
    justify-content:space-between;
    padding-bottom:8px;
    border-bottom:1px solid rgba(255,255,255,0.04);
    transition: border-color 0.3s ease;
  }
  .section h4{
    margin:0;
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.8px;
    color:var(--muted);
    flex:1;
    transition: color 0.3s ease;
  }
  .section-add-btn{
    background:transparent;
    border:0;
    color:var(--text);
    font-size:18px;
    cursor:pointer;
    display:none;
    padding:0;
    width:24px;
    height:24px;
    flex-shrink:0;
    transition:transform .2s ease, color 0.3s ease;
  }
  .editor-mode .section-add-btn{
    display:block
  }
  .section-add-btn:hover{
    transform:scale(1.1)
  }
  .grid{
    width:100%;
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(84px, 1fr));
    gap:var(--gap);
    justify-items:center;
    align-items:start;
    padding:8px 6px;
    position:relative
  }
  .tile{
    display:block;
    width:100%;
    max-width:120px;
    text-align:center;
    text-decoration:none;
    outline:none;
    -webkit-tap-highlight-color: transparent;
    user-select:none
  }
  .icon{
    width:var(--tile-size);
    height:var(--tile-size);
    border-radius:var(--tile-radius);
    background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.06));
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
    border:1px solid rgba(255,255,255,0.03);
    transition:transform .14s ease, box-shadow .14s ease;
    box-shadow:var(--shadow);
    position:relative
  }
  .tile:focus .icon,
  .tile:hover .icon{
    transform:translateY(-6px) scale(1.02);
    box-shadow:0 26px 60px rgba(2,6,23,0.6)
  }
  .icon img{
    width:64px;
    height:64px;
    object-fit:cover;
    border-radius:14px;
    display:block;
    pointer-events:none;
    background:transparent
  }
  .tile-actions{
    position:absolute;
    top:4px;
    right:4px;
    display:none;
    gap:4px;
    z-index:100
  }
  .editor-mode .tile-actions{
    display:flex
  }
  .tile-action-btn{
    background:rgba(0,0,0,0.6);
    border:0;
    color:#fff;
    width:28px;
    height:28px;
    border-radius:6px;
    cursor:pointer;
    font-size:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:background .2s;
    padding:0
  }
  .tile-action-btn:hover{
    background:rgba(0,0,0,0.8)
  }
  .sr-only{
    position:absolute!important;
    width:1px;
    height:1px;
    padding:0;
    margin:-1px;
    overflow:hidden;
    clip:rect(0,0,0,0);
    white-space:nowrap;
    border:0
  }
  .dragging{
    opacity:0.6;
    transform:scale(1.02) rotate(-2deg);
    box-shadow:0 40px 80px rgba(0,0,0,0.45)
  }
  .placeholder{
    width:var(--tile-size);
    height:var(--tile-size);
    border-radius:var(--tile-radius);
    border:2px dashed rgba(255,255,255,0.06);
    background:transparent;
    box-sizing:border-box
  }
  .ctx-menu{
    position:fixed;
    z-index:9999;
    background:var(--panel);
    color:var(--text);
    border:1px solid rgba(255,255,255,0.04);
    border-radius:8px;
    box-shadow:0 8px 30px rgba(0,0,0,0.5);
    padding:6px;
    display:none;
    min-width:140px;
    font-size:14px;
    transition: background 0.3s ease, color 0.3s ease;
  }
  .ctx-menu button{
    display:block;
    width:100%;
    text-align:left;
    background:transparent;
    border:0;
    color:var(--text);
    padding:8px 10px;
    cursor:pointer;
    border-radius:6px;
    transition: color 0.3s ease;
  }
  .ctx-menu button:hover{
    background:rgba(255,255,255,0.02)
  }
  .editor-indicator{
    position:fixed;
    bottom:20px;
    left:20px;
    background:var(--accent);
    color:#fff;
    padding:10px 16px;
    border-radius:999px;
    font-size:12px;
    font-weight:600;
    display:none;
    box-shadow:var(--shadow);
    z-index:500
  }
  .editor-mode .editor-indicator{
    display:block
  }
  @media (max-width: 768px){
    .wrap{
      justify-content:flex-start;
      align-items:stretch;
      padding-top:80px;
      padding-left:16px;
      padding-right:16px;
    }
    .card{
      max-width:100%;
      border-radius:12px;
      box-shadow:0 8px 20px rgba(0,0,0,0.35);
      padding:20px;
    }
  }
  @media (max-width:480px){
    :root{
      --tile-size:80px;
      --tile-radius:18px
    }
    .card{
      padding:18px;
      box-shadow:0 4px 14px rgba(0,0,0,0.3);
    }
    .settings-panel{
      width:100%
    }
  }
</style>
</head>
<body>
<?php include'header.html'; ?>
<div class="header">
<button id="settingsBtn" class="settings-btn" aria-label="Einstellungen">⚙️</button>
</div>
<div id="settingsOverlay" class="settings-overlay"></div>
<div id="settingsPanel" class="settings-panel" role="dialog" aria-label="Einstellungen">
<h2>Einstellungen</h2>
<button id="themeToggle" class="settings-option">🌙 Dark Mode</button>
<button id="editorToggle" class="settings-option">✏️ Editor-Modus</button>
<button id="resetBtn" class="settings-option danger">🔄 Zurücksetzen</button>
<button id="downloadToggle" class="settings-option danger"><a href="/download/Tanum Launcher.zip" download>Herunterladen</a></button>
<button id="closeSettings" class="settings-close">Schließen</button>
</div>
<div class="editor-indicator">✏️ Editor-Modus aktiv</div>
<div class="wrap">
<main class="card" role="main" aria-label="Icon Launcher">
<h1 class="title" aria-hidden="true"><img src="files/ico/tanum-consult.ico" alt="Tanum Logo" style="width:20px;height:20px;vertical-align:middle;border-radius:4px;margin-right:10px;display:inline-block;" />Tanum - Launcher</h1>
<!-- ----- -->
<div class="section">
<div class="section-header">
<h4>general</h4>
<button class="section-add-btn" data-section="general" aria-label="App zu general hinzufügen">
<img src="files/ico/plus.ico" alt="Add" style="width:100%;height:100%;object-fit:contain;">
</button>
</div>
<nav class="grid" id="grid-general" aria-label="general Apps"></nav>
<ul id="links-general" style="display:none">
    <!-- ----- -->
<li data-title="Books" data-url="https://books.tcsoc.net/books/tco-tanum-consult" data-icon="files/ico/books.tcsoc.ico"></li>
<li data-title="OTRS - Znuny" data-url="https://servicedesk.tcsoc.net/otrs/index.pl" data-icon="files/ico/servicedesk.tcsoc.ico"></li>
<li data-title="ScreenConnect" data-url="https://help.tcsoc.net/Host#Access/" data-icon="files/ico/help.tcsoc.ico"></li>
<li data-title="Puplic Map" data-url="https://monitoring.tcsoc.net/public/mapshow.htm?ids=15901:4C3A7306-367D-40E8-8CD6-A9EC9936D655,16089:577EA9F7-7D38-45C5-A52B-159D3800C66C,14848:6AD7B66E-A56E-4694-8296-D0A24FAC3AED" data-icon="files/ico/monitoring.tcsoc.ico"></li>
<li data-title="1 Password" data-url="https://tanum.1password.com/" data-icon="files/ico/tanum.1password.ico"></li>
<li data-title="Okta" data-url="https://tanum.okta.com/" data-icon="files/ico/okta.ico"></li>
<li data-title="Rapid 7" data-url="https://insight.rapid7.com/login?sso=true" data-icon="files/ico/insight.rapid7.ico"></li>
<li data-title="Bitdefender" data-url="https://cloudgz.gravityzone.bitdefender.com/" data-icon="files/ico/gravityzone.bitdefender.ico"></li>
<li data-title="Zeiterfassung" data-url="https://gamov.tanum.de/projectile/start" data-icon="files/ico/gamov.tanum.ico"></li>
<li data-title="OTRS Reporting" data-url="http://minkowski.tcsoc.net/reporting/" data-icon="files/ico/minkowski.tcsoc.ico"></li>
<li data-title="Mylunch" data-url="https://mylunch.apetito.de/" data-icon="files/ico/mylunch.apetito.ico"></li>
<li data-title="Einsatzplanung" data-url="https://tanum.sharepoint.com/:x:/r/sites/tanum/_layouts/15/Doc.aspx?sourcedoc=%7B31FFB742-7B78-4058-93B4-278C470592DC%7D&file=Einsatzplanung%202026.xlsx" data-icon="files/ico/einsatzplanung.sharepoint.ico"></li>
<li data-title="tco-zeiterfassung" data-url="https://tanum.sharepoint.com/:x:/r/sites/tanum/_layouts/15/Doc.aspx?sourcedoc=%7B5E3A1CAD-BFB0-4B8A-80CE-8F8DEE86E723%7D&file=TCO_Zeiterfassung.xlsx" data-icon="files/ico/zeiterfassung.sharepoint.ico"></li>
<li data-title="Mein" data-url="https://mein.apetito.de/" data-icon="files/ico/mein.apetito.ico"></li>
<li data-title="CDC - Share Point" data-url="https://cdcit.sharepoint.com/sites/chiccoDokumente/chiccodicaff/Forms/AllItems.aspx" data-icon="files/ico/cdc-sharepoint.ico"></li>
<li data-title="ScreenConnect - Clients" data-url="https://books.tcsoc.net/books/remote-help-access/page/operation" data-icon="files/ico/screenconnect-clients.books.ico"></li>
<li data-title="Datev Dash" data-url="https://apps.datev.de/ano/de/" data-icon="files/ico/apps.datev.ico"></li>
<li data-title="Datev Link" data-url="https://apps.datev.de/ano/dashboard/" data-icon="files/ico/v2.link.datev.ico"></li>
<li data-title="Organisationshandbuch" data-url="https://tanum.sharepoint.com/sites/Organisationshandbuch/" data-icon="files/ico/organisationshandbuch.ico"></li>
<li data-title="OTRS" data-url="/otrs/" data-icon="https://raw.githubusercontent.com/florianthepro/pages/main/content/media/csv-reporting/index.svg"></li>
    <!-- ----- -->
</div>
<!-- ----- -->
<div class="section">
<div class="section-header">
<h4>m365</h4>
<button class="section-add-btn" data-section="m365" aria-label="App zu m365 hinzufügen">
<img src="files/ico/plus.ico" alt="Add" style="width:100%;height:100%;object-fit:contain;">
</button>
</div>
<nav class="grid" id="grid-m365" aria-label="m365 Apps"></nav>
<ul id="links-m365" style="display:none">
    <!-- ----- -->
<li data-title="All MS Portals" data-url="https://msportals.io/" data-icon="files/ico/msportals.ico"></li>
<li data-title="Admin" data-url="https://admin.cloud.microsoft/" data-icon="files/ico/admin.microsoft.ico"></li>
<li data-title="Entra" data-url="https://entra.microsoft.com/" data-icon="files/ico/entra.microsoft.ico"></li>
<li data-title="Intunes" data-url="https://intune.microsoft.com/" data-icon="files/ico/intune.microsoft.ico"></li>
<li data-title="Share Point" data-url="https://tanum.sharepoint.com/sites/tanum/SitePages/CollabHome.aspx" data-icon="files/ico/sharepoint.ico"></li>
<li data-title="Settings" data-url="https://myaccount.microsoft.com/" data-icon="files/ico/settings.m365.ico"></li>
<li data-title="Apps" data-url="https://myapplications.microsoft.com/" data-icon="files/ico/apps.m365.ico"></li>
<li data-title="One Drive" data-url="https://tanum-my.sharepoint.com/" data-icon="files/ico/onedrive.ico"></li>
<li data-title="Outlook" data-url="https://outlook.office365.com/" data-icon="files/ico/outlook.ico"></li>
<li data-title="Planner" data-url="https://planner.cloud.microsoft/webui/" data-icon="files/ico/planner.ico"></li>
    <!-- ----- -->
</div>
<!-- ----- -->
<div class="section">
<div class="section-header">
<h4>operation</h4>
<button class="section-add-btn" data-section="operation" aria-label="App zu operation hinzufügen">
<img src="files/ico/plus.ico" alt="Add" style="width:100%;height:100%;object-fit:contain;">
</button>
</div>
<nav class="grid" id="grid-operation" aria-label="operation Apps"></nav>
<ul id="links-operation" style="display:none">
    <!-- ----- -->
<li data-title="Tools" data-url="https://tools.xo.je" data-icon="files/ico/tools-launcher.ico"></li>
<li data-title="DHL" data-url="https://dhl.de/" data-icon="files/ico/dhl.ico"></li>
<li data-title="Servicedesk Rules" data-url="https://books.tcsoc.net/books/service-desk-handbook/page/servicedesk-rules" data-icon="files/ico/servicdesk-rules.ico"></li>
<li data-title="NTP" data-url="https://www.zeitserver.de/deutschland/ptb-zeitserver-in-braunschweig/" data-icon="files/ico/ntp.ico"></li>
<li data-title="Wareneingang" data-url="https://books.tcsoc.net/books/ticket-system/page/wareneingang-cdc" data-icon="files/ico/wareneingang.ico"></li>
<li data-title="tco-grundsysteme-overview" data-url="https://books.tcsoc.net/books/tco-tanum-consult/page/tco-grundsysteme-overview" data-icon="files/ico/v5-!.ico"></li>
<li data-title="Redirect" data-url="files/redirect.html" data-icon="files/ico/arrow.ico"></li>
<li data-title="hamburgerei" data-url="https://wolt.com/de/deu/munich/search?q=hamburgerei" data-icon="files/ico/hamburgerei.ico"></li>
<li data-title="Riemarcaden" data-url="https://www.riemarcaden.de/" data-icon="files/ico/riem-arcaden.ico"></li>
    <!-- ----- -->
</div>
<!-- ----- -->
<span class="sr-only" id="instructions">Navigiere mit Tab zu einem Icon und drücke Enter, um die Seite in einem neuen Tab zu öffnen. Einstellungen oben rechts: Editor-Modus, Theme, Zurücksetzen.</span>
</main>
</div>
<div id="ctxMenu" class="ctx-menu" role="menu" aria-hidden="true"></div>
<script>
const COOKIE_NAME = 'icon_launcher_order';
const THEME_COOKIE = 'launcher_theme';
const COOKIE_DAYS = 365;
const FAVICON = url =>
  'https://www.google.com/s2/favicons?sz=128&domain_url=' + encodeURIComponent(url);
const sections = [
  { id: 'general',   gridId: 'grid-general',   linksId: 'links-general' },
  { id: 'm365',      gridId: 'grid-m365',      linksId: 'links-m365' },
  { id: 'operation', gridId: 'grid-operation', linksId: 'links-operation' }
];
function setCookie(name, value, days) {
  localStorage.setItem(name, value);
}
function getCookie(name) {
  return localStorage.getItem(name);
}
function eraseCookie(name) {
  localStorage.removeItem(name);
}
function readDefaultLinks() {
  const result = {};
  sections.forEach(section => {
    const nodes = document.querySelectorAll(`#${section.linksId} li`);
    const arr = [];
    nodes.forEach(n => {
      const url = n.dataset.url;
      if (!url) return;
      const title = n.dataset.title || (new URL(url)).hostname;
      const icon = n.dataset.icon || null;
      arr.push({ title, url, iconUrl: icon });
    });
    result[section.id] = arr;
  });
  return result;
}
function loadLinks() {
  const raw = getCookie(COOKIE_NAME);
  if (raw) {
    try {
      const parsed = JSON.parse(raw);
      if (parsed && typeof parsed === 'object') return parsed;
    } catch (e) {}
  }
  return readDefaultLinks();
}
function saveLinks(links) {
  try {
    const json = JSON.stringify(links);
    setCookie(COOKIE_NAME, json, COOKIE_DAYS);
  } catch (e) {
    console.error('Speichern fehlgeschlagen', e);
  }
}
let allLinks = loadLinks();
let editorMode = false;
const grids = {};
const ctxMenu = document.getElementById('ctxMenu');
const settingsBtn = document.getElementById('settingsBtn');
const settingsPanel = document.getElementById('settingsPanel');
const settingsOverlay = document.getElementById('settingsOverlay');
const themeToggle = document.getElementById('themeToggle');
const editorToggle = document.getElementById('editorToggle');
const downloadToggle = document.getElementById('downloadToggle');
const resetBtn = document.getElementById('resetBtn');
const closeSettings = document.getElementById('closeSettings');
sections.forEach(section => {
  grids[section.id] = document.getElementById(section.gridId);
});
document.querySelectorAll('.section-add-btn').forEach(btn => {
  btn.addEventListener('click', e => {
    e.preventDefault();
    const sectionId = btn.dataset.section;
    handleAdd(sectionId);
  });
});
function initializeTheme() {
  const savedTheme = getCookie(THEME_COOKIE);
  if (savedTheme === 'light') {
    document.documentElement.classList.remove('dark-mode-forced');
    document.documentElement.classList.add('light-mode-forced');
    themeToggle.textContent = '🌙 Dark Mode';
  } else {
    document.documentElement.classList.remove('light-mode-forced');
    document.documentElement.classList.add('dark-mode-forced');
    themeToggle.textContent = '📟 Retro Mode';
    if (!savedTheme) {
      setCookie(THEME_COOKIE, 'dark', COOKIE_DAYS);
    }
  }
}
function toggleTheme() {
  const current = getCookie(THEME_COOKIE);
  if (current === 'light') {
    document.documentElement.classList.remove('light-mode-forced');
    document.documentElement.classList.add('dark-mode-forced');
    setCookie(THEME_COOKIE, 'dark', COOKIE_DAYS);
    themeToggle.textContent = '📟 Retro Mode';
  } else {
    document.documentElement.classList.remove('dark-mode-forced');
    document.documentElement.classList.add('light-mode-forced');
    setCookie(THEME_COOKIE, 'light', COOKIE_DAYS);
    themeToggle.textContent = '🌙 Dark Mode';
  }
}
function toggleEditorMode() {
  editorMode = !editorMode;
  if (editorMode) {
    document.body.classList.add('editor-mode');
    editorToggle.textContent = '✏️ Editor-Modus beenden';
  } else {
    document.body.classList.remove('editor-mode');
    editorToggle.textContent = '✏️ Editor-Modus';
    closeContextMenu();
  }
  buildAllGrids();
}
function resetLauncher() {
  if (!confirm('Alle Einstellungen und Ordnung zurücksetzen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
    return;
  }
  eraseCookie(COOKIE_NAME);
  eraseCookie(THEME_COOKIE);
  allLinks = readDefaultLinks();
  document.documentElement.classList.remove('light-mode-forced');
  document.documentElement.classList.add('dark-mode-forced');
  editorMode = false;
  document.body.classList.remove('editor-mode');
  initializeTheme();
  themeToggle.textContent = '📟 Retro Mode';
  editorToggle.textContent = '✏️ Editor-Modus';
  closeSettingsPanel();
  closeContextMenu();
  buildAllGrids();
}
function openSettingsPanel() {
  settingsPanel.classList.add('active');
  settingsOverlay.classList.add('active');
}
function closeSettingsPanel() {
  settingsPanel.classList.remove('active');
  settingsOverlay.classList.remove('active');
}
settingsBtn.addEventListener('click', openSettingsPanel);
closeSettings.addEventListener('click', closeSettingsPanel);
settingsOverlay.addEventListener('click', closeSettingsPanel);
themeToggle.addEventListener('click', toggleTheme);
editorToggle.addEventListener('click', toggleEditorMode);
resetBtn.addEventListener('click', resetLauncher);
function buildAllGrids() {
  sections.forEach(section => {
    buildGrid(section);
  });
}
function buildGrid(section) {
  const grid = grids[section.id];
  const links = allLinks[section.id] || [];
  grid.innerHTML = '';
  links.forEach((item, idx) => {
    const a = document.createElement('a');
    a.className = 'tile';
    a.href = item.url;
    a.target = '_blank';
if (a.href === 'https://tools.xo.je/') a.target = '_self';
    a.rel = 'noopener noreferrer';
    a.setAttribute('aria-label', item.title + ' in neuem Tab öffnen');
    a.dataset.index = String(idx);
    a.dataset.section = section.id;
    a.draggable = editorMode;

if (section.id === 'selfmade') {
      a.target = '_self'; // könnte man auch einfach weglassen, ist Default
      a.removeAttribute('rel');
      a.setAttribute('aria-label', item.title + ' öffnen');
    }

    const icon = document.createElement('div');
    icon.className = 'icon';
    const img = document.createElement('img');
    img.alt = item.title + ' icon';
    img.src = item.iconUrl || FAVICON(item.url);
    img.onerror = () => {
      img.src = initialsDataUrl(item.title, 128, colorFromHost(item.url));
    };
    icon.appendChild(img);
    a.appendChild(icon);
    a.addEventListener('contextmenu', e => {
      if (!editorMode) return;
      e.preventDefault();
      e.stopPropagation();
      openContextMenu(e.clientX, e.clientY, section.id, idx);
    });
    a.addEventListener('click', e => {
      if (editorMode) {
        e.preventDefault();
        e.stopImmediatePropagation();
      }
    });
    a.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (!editorMode) window.open(item.url, '_blank', 'noopener');
      }
    });
    if (editorMode) {
      a.addEventListener('dragstart', e => onDragStart(e, section.id, idx));
      a.addEventListener('dragend', onDragEnd);
      a.addEventListener('dragover', onTileDragOver);
      a.addEventListener('drop', e => onTileDrop(e, section.id));
    } else {
      a.addEventListener('dragstart', e => e.preventDefault());
    }
    grid.appendChild(a);
  });
}
function openContextMenu(x, y, sectionId, idx) {
  ctxMenu.innerHTML = '';
  const editBtn = document.createElement('button');
  editBtn.type = 'button';
  editBtn.textContent = '✏️ Bearbeiten';
  editBtn.addEventListener('click', e => {
    e.preventDefault();
    e.stopPropagation();
    closeContextMenu();
    editLink(sectionId, idx);
  });
  ctxMenu.appendChild(editBtn);
  const delBtn = document.createElement('button');
  delBtn.type = 'button';
  delBtn.textContent = '🗑️ Entfernen';
  delBtn.addEventListener('click', e => {
    e.preventDefault();
    e.stopPropagation();
    closeContextMenu();
    deleteLink(sectionId, idx);
  });
  ctxMenu.appendChild(delBtn);
  ctxMenu.style.left = x + 'px';
  ctxMenu.style.top = y + 'px';
  ctxMenu.style.display = 'block';
  ctxMenu.setAttribute('aria-hidden', 'false');
}
function closeContextMenu() {
  ctxMenu.style.display = 'none';
  ctxMenu.setAttribute('aria-hidden', 'true');
}
document.addEventListener('click', () => {
  closeContextMenu();
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeContextMenu();
  }
});
function editLink(sectionId, idx) {
  const item = allLinks[sectionId][idx];
  const newTitle = prompt('App-Name:', item.title);
  if (newTitle === null) return;
  let newUrl = prompt('URL:', item.url);
  if (newUrl === null) return;
  try {
    if (!/^https?:\/\//i.test(newUrl)) newUrl = 'https://' + newUrl;
    new URL(newUrl);
  } catch (e) {
    alert('Ungültige URL');
    return;
  }
  let newIconUrl = prompt('Icon-URL (optional):', item.iconUrl || '');
  if (newIconUrl !== null) {
    newIconUrl = newIconUrl.trim();
    if (newIconUrl && !/^https?:\/\//i.test(newIconUrl)) {
      newIconUrl = 'https://' + newIconUrl;
    }
  } else {
    newIconUrl = item.iconUrl;
  }
  allLinks[sectionId][idx] = {
    title: newTitle.trim(),
    url: newUrl.trim(),
    iconUrl: newIconUrl || null
  };
  saveLinks(allLinks);
  buildAllGrids();
}
function deleteLink(sectionId, idx) {
  const item = allLinks[sectionId][idx];
  if (!confirm(`"${item.title}" wirklich entfernen?`)) return;
  allLinks[sectionId].splice(idx, 1);
  saveLinks(allLinks);
  buildAllGrids();
}
function handleAdd(sectionId) {
  const title = prompt('App-Name (z.B. GitHub):');
  if (!title) return;
  let url = prompt('URL (mit https://):');
  if (!url) return;
  try {
    if (!/^https?:\/\//i.test(url)) url = 'https://' + url;
    new URL(url);
  } catch (e) {
    alert('Ungültige URL');
    return;
  }
  let iconUrl = prompt('Icon-URL (optional):');
  if (iconUrl) {
    iconUrl = iconUrl.trim();
    if (iconUrl && !/^https?:\/\//i.test(iconUrl)) {
      iconUrl = 'https://' + iconUrl;
    }
  } else {
    iconUrl = null;
  }
  if (!allLinks[sectionId]) allLinks[sectionId] = [];
  allLinks[sectionId].push({
    title: title.trim(),
    url: url.trim(),
    iconUrl
  });
  saveLinks(allLinks);
  buildAllGrids();
}
let dragState = null;
let placeholderEl = null;
function createPlaceholder() {
  const el = document.createElement('div');
  el.className = 'placeholder';
  return el;
}
function onDragStart(e, sectionId, idx) {
  if (!editorMode) return;
  dragState = { fromSection: sectionId, fromIndex: idx };
  e.currentTarget.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
  const ghost = document.createElement('canvas');
  ghost.width = 1;
  ghost.height = 1;
  e.dataTransfer.setDragImage(ghost, 0, 0);
}
function onDragEnd(e) {
  e.currentTarget.classList.remove('dragging');
  dragState = null;
  if (placeholderEl && placeholderEl.parentElement) {
    placeholderEl.parentElement.removeChild(placeholderEl);
  }
  placeholderEl = null;
}
function onTileDragOver(e) {
  if (!editorMode || !dragState) return;
  e.preventDefault();
  const tile = e.currentTarget;
  const grid = tile.parentElement;

  if (!placeholderEl) {
    placeholderEl = createPlaceholder();
  }
  if (!grid.contains(placeholderEl)) {
    grid.insertBefore(placeholderEl, tile);
  } else {
    const rect = tile.getBoundingClientRect();
    const offset = e.clientY - rect.top;
    if (offset > rect.height / 2) {
      grid.insertBefore(placeholderEl, tile.nextSibling);
    } else {
      grid.insertBefore(placeholderEl, tile);
    }
  }
}
function onTileDrop(e, targetSectionId) {
  if (!editorMode || !dragState) return;
  e.preventDefault();
  applyReorder(targetSectionId);
}
Object.keys(grids).forEach(sectionId => {
  const grid = grids[sectionId];
  grid.addEventListener('dragover', e => onGridDragOver(e, sectionId));
  grid.addEventListener('drop', e => onGridDrop(e, sectionId));
});
function onGridDragOver(e, targetSectionId) {
  if (!editorMode || !dragState) return;
  e.preventDefault();
  const grid = grids[targetSectionId];
  if (!placeholderEl) {
    placeholderEl = createPlaceholder();
  }
  if (!grid.contains(placeholderEl)) {
    grid.appendChild(placeholderEl);
  }
}
function onGridDrop(e, targetSectionId) {
  if (!editorMode || !dragState) return;
  e.preventDefault();
  applyReorder(targetSectionId);
}
function applyReorder(targetSectionId) {
  if (!dragState) return;
  const fromSection = dragState.fromSection;
  const fromIndex = dragState.fromIndex;
  const fromArray = allLinks[fromSection];
  if (!fromArray || fromIndex < 0 || fromIndex >= fromArray.length) {
    dragState = null;
    return;
  }
  const targetGrid = grids[targetSectionId];
  const children = Array.from(targetGrid.children);
  let targetIndex = placeholderEl ? children.indexOf(placeholderEl) : children.length;
  if (targetIndex === -1) targetIndex = children.length;
  const item = fromArray.splice(fromIndex, 1)[0];
  if (fromSection === targetSectionId && fromIndex < targetIndex) {
    targetIndex--;
  }
  if (!allLinks[targetSectionId]) {
    allLinks[targetSectionId] = [];
  }
  allLinks[targetSectionId].splice(targetIndex, 0, item);
  saveLinks(allLinks);
  dragState = null;
  if (placeholderEl && placeholderEl.parentElement) {
    placeholderEl.parentElement.removeChild(placeholderEl);
  }
  placeholderEl = null;
  buildAllGrids();
}
function initialsDataUrl(text, size = 128, bg = '#2b6cb0') {
  const initials = (text || '')
    .split(/\s+/)
    .slice(0, 2)
    .map(s => s[0])
    .join('')
    .toUpperCase() || '?';
  const canvas = document.createElement('canvas');
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext('2d');
  const r = Math.floor(size * 0.18);
  ctx.fillStyle = bg;
  roundRect(ctx, 0, 0, size, size, r);
  ctx.fill();
  ctx.fillStyle = 'rgba(255,255,255,0.06)';
  roundRect(ctx, 0, 0, size, size, r);
  ctx.fill();
  ctx.fillStyle = '#fff';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.font = `bold ${Math.floor(size * 0.42)}px system-ui, -apple-system, "Segoe UI", Roboto, Arial`;
  ctx.fillText(initials, size / 2, size / 2 + Math.floor(size * 0.02));
  return canvas.toDataURL('image/png');
}
function roundRect(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}
function colorFromHost(host) {
  let h = 0;
  const str = host || '';
  for (let i = 0; i < str.length; i++) {
    h = (h << 5) - h + str.charCodeAt(i);
    h |= 0;
  }
  return `hsl(${Math.abs(h) % 360} 60% 45%)`;
}
initializeTheme();
buildAllGrids();
window.Launcher = {
  add(title, url, sectionId = 'selfmade') {
    if (!allLinks[sectionId]) allLinks[sectionId] = [];
    allLinks[sectionId].push({ title, url, iconUrl: null });
    saveLinks(allLinks);
    buildAllGrids();
  },
  reset() {
    resetLauncher();
  },
  getOrder() {
    return JSON.parse(JSON.stringify(allLinks));
  },
  toggleEditorMode(on) {
    if (typeof on === 'boolean') {
      if (on !== editorMode) toggleEditorMode();
    } else {
      toggleEditorMode();
    }
  }
};
</script>
</body>
</html>
```
änder
1. herunterladen link entfernen
2. links sollen via php variable
format:
```
$links=<<<'YAML'
general:
  - {title:"Example",url:"https://example.com",icon:""}
YAML;
```
genutzwerden
mache keine weiteren änderungen und gebe mir die neue index.php
die yaml variable 1:1 wie in meinem beispiel
