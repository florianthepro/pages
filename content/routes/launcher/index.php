<?php
/* Launcher – generische Engine. Enthaelt KEINE Link-/Seiten-Daten:
   die Kachel-Liste, der Icon-Ordner und das Theme kommen aus der Instanz
   (via $sharedVars). Die Default-Seite wird aus $launcher_links gebaut;
   Nutzer koennen sie danach im Browser (localStorage) frei umsortieren/
   bearbeiten. Icons werden per Name zugeordnet: Dateiname = Kachel-Titel. */
$launcher_theme = (isset($launcher_theme) && in_array($launcher_theme, ['light','dark'], true)) ? $launcher_theme : 'auto';
$launcher_title = (isset($launcher_title) && $launcher_title !== '') ? (string)$launcher_title : 'Launcher';
$launcher_iconbase = isset($launcher_iconbase) ? rtrim((string)$launcher_iconbase, '/') . '/' : '';
$launcher_iconext = (isset($launcher_iconext) && $launcher_iconext !== '') ? (string)$launcher_iconext : '.ico';
$__llinks = (isset($launcher_links) && is_array($launcher_links)) ? array_values($launcher_links) : [];
$__links = [];
foreach ($__llinks as $l) {
    if (!is_array($l)) continue;
    $u = trim((string)($l['url'] ?? ''));
    if ($u === '') continue;
    $__links[] = [
        'group' => trim((string)($l['group'] ?? 'general')) ?: 'general',
        'title' => trim((string)($l['title'] ?? $u)) ?: $u,
        'url'   => $u,
        'icon'  => trim((string)($l['icon'] ?? '')),
    ];
}
$__jflags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$links_json = json_encode($__links, $__jflags);
$cfg_json = json_encode(['iconbase' => $launcher_iconbase, 'iconext' => $launcher_iconext], $__jflags);
$theme_json = json_encode($launcher_theme, $__jflags);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$htmlClass = $launcher_theme === 'dark' ? ' class="dark-mode-forced"' : ($launcher_theme === 'light' ? ' class="light-mode-forced"' : '');
?>
<!doctype html>
<html lang="de"<?= $htmlClass ?>>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?= h($launcher_title) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect x='8' y='8' width='20' height='20' rx='5' fill='%230ea5a4'/%3E%3Crect x='36' y='8' width='20' height='20' rx='5' fill='%230ea5a4'/%3E%3Crect x='8' y='36' width='20' height='20' rx='5' fill='%230ea5a4'/%3E%3Crect x='36' y='36' width='20' height='20' rx='5' fill='%230ea5a4'/%3E%3C/svg%3E" />
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
  .header{position:fixed;top:0;right:0;z-index:1000;padding:16px;display:flex;gap:12px}
  .settings-btn{
    background:var(--panel);border:1px solid rgba(127,127,127,0.25);color:var(--text);
    padding:10px 12px;border-radius:10px;cursor:pointer;font-size:18px;display:flex;
    align-items:center;justify-content:center;
    transition:transform .14s ease, box-shadow .14s ease, background 0.3s ease, color 0.3s ease;
    box-shadow:var(--shadow)
  }
  .settings-btn:hover{transform:translateY(-2px);box-shadow:0 16px 40px rgba(0,0,0,0.3)}
  .settings-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:none;z-index:999}
  .settings-overlay.active{display:block}
  .settings-panel{
    position:fixed;top:0;right:0;height:100vh;width:300px;background:var(--panel);
    box-shadow:-4px 0 20px rgba(0,0,0,0.3);transform:translateX(100%);transition:transform .3s ease;
    z-index:1001;display:flex;flex-direction:column;padding:20px;color:var(--text);
  }
  .settings-panel.active{transform:translateX(0)}
  .settings-panel h2{margin:0 0 20px 0;font-size:18px;color:var(--text);transition: color 0.3s ease}
  .settings-option{
    background:rgba(127,127,127,0.10);border:1px solid rgba(127,127,127,0.20);color:var(--text);
    padding:12px 16px;border-radius:8px;cursor:pointer;font-size:14px;margin-bottom:10px;
    transition:background .2s ease, color 0.3s ease;text-align:left
  }
  .settings-option:hover{background:rgba(127,127,127,0.20)}
  .settings-option.danger{color:#ff6b6b}
  .settings-close{
    background:transparent;border:0;color:var(--text);cursor:pointer;margin-top:auto;
    padding:10px;font-size:14px;opacity:0.7;transition:opacity .2s, color 0.3s ease
  }
  .settings-close:hover{opacity:1}
  .wrap{
    min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;align-items:center;
    justify-content:center;padding:clamp(16px, 4vw, 32px);
    padding-top:calc(60px + clamp(8px, 2vh, 24px));padding-bottom:clamp(16px, 4vh, 40px);
  }
  .card{
    width:100%;max-width:var(--max-width);
    background:linear-gradient(180deg, rgba(127,127,127,0.04), rgba(0,0,0,0.06));
    border-radius:16px;padding:28px;box-shadow:var(--shadow);display:flex;flex-direction:column;
    align-items:center;gap:28px;border:1px solid rgba(127,127,127,0.12);backdrop-filter: blur(6px);
    transition: background 0.3s ease, box-shadow 0.3s ease;
  }
  .title{font-size:16px;font-weight:600;letter-spacing:0.2px;color:var(--text);margin:0;display:block;transition: color 0.3s ease}
  #sections-root{width:100%;display:flex;flex-direction:column;gap:28px}
  .section{width:100%;display:flex;flex-direction:column;gap:12px}
  .section-header{
    display:flex;align-items:center;gap:10px;justify-content:space-between;padding-bottom:8px;
    border-bottom:1px solid rgba(127,127,127,0.18);transition: border-color 0.3s ease;
  }
  .section h4{margin:0;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--muted);flex:1;transition: color 0.3s ease}
  .section-add-btn{
    background:transparent;border:0;color:var(--text);font-size:20px;line-height:1;cursor:pointer;
    display:none;padding:0;width:24px;height:24px;flex-shrink:0;transition:transform .2s ease, color 0.3s ease;
  }
  .editor-mode .section-add-btn{display:block}
  .section-add-btn:hover{transform:scale(1.1)}
  .grid{
    width:100%;display:grid;grid-template-columns:repeat(auto-fit, minmax(84px, 1fr));gap:var(--gap);
    justify-items:center;align-items:start;padding:8px 6px;position:relative
  }
  .tile{display:block;width:100%;max-width:120px;text-align:center;text-decoration:none;outline:none;-webkit-tap-highlight-color: transparent;user-select:none}
  .icon{
    width:var(--tile-size);height:var(--tile-size);border-radius:var(--tile-radius);
    background:linear-gradient(180deg, rgba(127,127,127,0.06), rgba(0,0,0,0.06));
    display:flex;align-items:center;justify-content:center;overflow:hidden;
    border:1px solid rgba(127,127,127,0.12);transition:transform .14s ease, box-shadow .14s ease;
    box-shadow:var(--shadow);position:relative
  }
  .tile:focus .icon,.tile:hover .icon{transform:translateY(-6px) scale(1.02);box-shadow:0 26px 60px rgba(2,6,23,0.6)}
  .icon img{width:64px;height:64px;object-fit:cover;border-radius:14px;display:block;pointer-events:none;background:transparent}
  .tile-title{display:block;margin-top:8px;font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:color 0.3s ease}
  .tile-actions{position:absolute;top:4px;right:4px;display:none;gap:4px;z-index:100}
  .editor-mode .tile-actions{display:flex}
  .sr-only{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
  .dragging{opacity:0.6;transform:scale(1.02) rotate(-2deg);box-shadow:0 40px 80px rgba(0,0,0,0.45)}
  .placeholder{width:var(--tile-size);height:var(--tile-size);border-radius:var(--tile-radius);border:2px dashed rgba(127,127,127,0.25);background:transparent;box-sizing:border-box}
  .ctx-menu{
    position:fixed;z-index:9999;background:var(--panel);color:var(--text);border:1px solid rgba(127,127,127,0.20);
    border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,0.5);padding:6px;display:none;min-width:140px;font-size:14px;
    transition: background 0.3s ease, color 0.3s ease;
  }
  .ctx-menu button{display:block;width:100%;text-align:left;background:transparent;border:0;color:var(--text);padding:8px 10px;cursor:pointer;border-radius:6px;transition: color 0.3s ease}
  .ctx-menu button:hover{background:rgba(127,127,127,0.12)}
  .empty-hint{color:var(--muted);font-size:13px;text-align:center;padding:24px 8px}
  .editor-indicator{
    position:fixed;bottom:20px;left:20px;background:var(--accent);color:#fff;padding:10px 16px;
    border-radius:999px;font-size:12px;font-weight:600;display:none;box-shadow:var(--shadow);z-index:500
  }
  .editor-mode .editor-indicator{display:block}
  @media (max-width: 768px){
    .wrap{justify-content:flex-start;align-items:stretch;padding-top:80px;padding-left:16px;padding-right:16px}
    .card{max-width:100%;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.35);padding:20px}
  }
  @media (max-width:480px){
    :root{--tile-size:80px;--tile-radius:18px}
    .card{padding:18px;box-shadow:0 4px 14px rgba(0,0,0,0.3)}
    .settings-panel{width:100%}
  }
</style>
</head>
<body>
<div class="header">
<button id="settingsBtn" class="settings-btn" aria-label="Einstellungen">&#9881;</button>
</div>
<div id="settingsOverlay" class="settings-overlay"></div>
<div id="settingsPanel" class="settings-panel" role="dialog" aria-label="Einstellungen">
<h2>Einstellungen</h2>
<button id="themeToggle" class="settings-option">Theme</button>
<button id="editorToggle" class="settings-option">&#9998; Editor-Modus</button>
<button id="resetBtn" class="settings-option danger">&#128260; Zur&uuml;cksetzen</button>
<button id="closeSettings" class="settings-close">Schlie&szlig;en</button>
</div>
<div class="editor-indicator">&#9998; Editor-Modus aktiv</div>
<div class="wrap">
<main class="card" role="main" aria-label="Launcher">
<h1 class="title"><?= h($launcher_title) ?></h1>
<div id="sections-root"></div>
<span class="sr-only" id="instructions">Navigiere mit Tab zu einem Icon und dr&uuml;cke Enter, um die Seite in einem neuen Tab zu &ouml;ffnen. Einstellungen oben rechts: Editor-Modus, Theme, Zur&uuml;cksetzen.</span>
</main>
</div>
<div id="ctxMenu" class="ctx-menu" role="menu" aria-hidden="true"></div>
<script>
const COOKIE_NAME = 'launcher_order';
const THEME_COOKIE = 'launcher_theme';
const DEFAULT_LINKS = <?= $links_json ?>;
const LCFG = <?= $cfg_json ?>;
const INSTANCE_THEME = <?= $theme_json ?>;
const FAVICON = url => 'https://www.google.com/s2/favicons?sz=128&domain_url=' + encodeURIComponent(url);

function setCookie(name, value){ try{ localStorage.setItem(name, value); }catch(e){} }
function getCookie(name){ try{ return localStorage.getItem(name); }catch(e){ return null; } }
function eraseCookie(name){ try{ localStorage.removeItem(name); }catch(e){} }

function iconFor(item){
  if (item.iconUrl) {
    const u = String(item.iconUrl);
    if (/^(https?:|data:|\/)/i.test(u)) return u;
    return (LCFG.iconbase || '') + u;
  }
  if (LCFG.iconbase) return LCFG.iconbase + encodeURIComponent(item.title) + (LCFG.iconext || '');
  return FAVICON(item.url);
}
function readDefaultLinks(){
  const result = {};
  DEFAULT_LINKS.forEach(l => {
    const g = (l.group || 'general');
    if (!result[g]) result[g] = [];
    result[g].push({ title: l.title || l.url, url: l.url, iconUrl: l.icon || null });
  });
  return result;
}
function loadLinks(){
  const raw = getCookie(COOKIE_NAME);
  if (raw) { try { const p = JSON.parse(raw); if (p && typeof p === 'object') return p; } catch(e){} }
  return readDefaultLinks();
}
function saveLinks(links){ try { setCookie(COOKIE_NAME, JSON.stringify(links)); } catch(e){ console.error('Speichern fehlgeschlagen', e); } }

let allLinks = loadLinks();
let editorMode = false;
const grids = {};
const sectionsRoot = document.getElementById('sections-root');
const ctxMenu = document.getElementById('ctxMenu');
const settingsBtn = document.getElementById('settingsBtn');
const settingsPanel = document.getElementById('settingsPanel');
const settingsOverlay = document.getElementById('settingsOverlay');
const themeToggle = document.getElementById('themeToggle');
const editorToggle = document.getElementById('editorToggle');
const resetBtn = document.getElementById('resetBtn');
const closeSettings = document.getElementById('closeSettings');

/* ---- theme (instanz-default light/dark/auto, danach vom Nutzer umschaltbar) ---- */
function prefersLight(){ try{ return window.matchMedia('(prefers-color-scheme: light)').matches; }catch(e){ return false; } }
function applyTheme(mode){
  const el = document.documentElement;
  el.classList.remove('light-mode-forced','dark-mode-forced');
  if (mode === 'light') el.classList.add('light-mode-forced');
  else if (mode === 'dark') el.classList.add('dark-mode-forced');
  updateThemeLabel();
}
function isLightNow(){
  const el = document.documentElement;
  if (el.classList.contains('light-mode-forced')) return true;
  if (el.classList.contains('dark-mode-forced')) return false;
  return prefersLight();
}
function updateThemeLabel(){ themeToggle.textContent = isLightNow() ? '🌙 Dark Mode' : '☀️ Light Mode'; }
function initializeTheme(){
  const saved = getCookie(THEME_COOKIE);
  let mode = (saved === 'light' || saved === 'dark') ? saved
           : ((INSTANCE_THEME === 'light' || INSTANCE_THEME === 'dark') ? INSTANCE_THEME : '');
  applyTheme(mode);
}
function toggleTheme(){
  const next = isLightNow() ? 'dark' : 'light';
  applyTheme(next);
  setCookie(THEME_COOKIE, next);
}

function openSettingsPanel(){ settingsPanel.classList.add('active'); settingsOverlay.classList.add('active'); }
function closeSettingsPanel(){ settingsPanel.classList.remove('active'); settingsOverlay.classList.remove('active'); }
settingsBtn.addEventListener('click', openSettingsPanel);
closeSettings.addEventListener('click', closeSettingsPanel);
settingsOverlay.addEventListener('click', closeSettingsPanel);
themeToggle.addEventListener('click', toggleTheme);
editorToggle.addEventListener('click', toggleEditorMode);
resetBtn.addEventListener('click', resetLauncher);

function toggleEditorMode(){
  editorMode = !editorMode;
  if (editorMode) { document.body.classList.add('editor-mode'); editorToggle.textContent = '✏️ Editor-Modus beenden'; }
  else { document.body.classList.remove('editor-mode'); editorToggle.textContent = '✏️ Editor-Modus'; closeContextMenu(); }
  buildAllGrids();
}
function resetLauncher(){
  if (!confirm('Alle Einstellungen und Ordnung zurücksetzen? Diese Aktion kann nicht rückgängig gemacht werden.')) return;
  eraseCookie(COOKIE_NAME); eraseCookie(THEME_COOKIE);
  allLinks = readDefaultLinks();
  editorMode = false; document.body.classList.remove('editor-mode');
  editorToggle.textContent = '✏️ Editor-Modus';
  initializeTheme();
  closeSettingsPanel(); closeContextMenu();
  buildAllGrids();
}

/* ---- dynamische Sektionen aus den Gruppen der Link-Liste ---- */
function buildSections(){
  const order = Object.keys(allLinks);
  sectionsRoot.innerHTML = '';
  for (const k in grids) delete grids[k];
  if (order.length === 0) {
    const hint = document.createElement('div');
    hint.className = 'empty-hint';
    hint.textContent = 'Keine Einträge. Editor-Modus aktivieren und Links hinzufügen.';
    sectionsRoot.appendChild(hint);
    return;
  }
  order.forEach(gid => {
    const sec = document.createElement('div'); sec.className = 'section';
    const hdr = document.createElement('div'); hdr.className = 'section-header';
    const h4 = document.createElement('h4'); h4.textContent = gid;
    const add = document.createElement('button'); add.type = 'button'; add.className = 'section-add-btn';
    add.textContent = '+'; add.dataset.section = gid; add.setAttribute('aria-label', 'App zu ' + gid + ' hinzufügen');
    add.addEventListener('click', e => { e.preventDefault(); handleAdd(gid); });
    hdr.appendChild(h4); hdr.appendChild(add);
    const grid = document.createElement('nav'); grid.className = 'grid'; grid.setAttribute('aria-label', gid + ' Apps');
    sec.appendChild(hdr); sec.appendChild(grid);
    sectionsRoot.appendChild(sec);
    grids[gid] = grid;
    grid.addEventListener('dragover', e => onGridDragOver(e, gid));
    grid.addEventListener('drop', e => onGridDrop(e, gid));
  });
}
function buildAllGrids(){ buildSections(); Object.keys(grids).forEach(gid => buildGrid(gid)); }
function buildGrid(gid){
  const grid = grids[gid]; if (!grid) return;
  const links = allLinks[gid] || [];
  grid.innerHTML = '';
  links.forEach((item, idx) => {
    const a = document.createElement('a');
    a.className = 'tile'; a.href = item.url; a.target = '_blank'; a.rel = 'noopener noreferrer';
    a.setAttribute('aria-label', item.title + ' in neuem Tab öffnen');
    a.dataset.index = String(idx); a.dataset.section = gid; a.draggable = editorMode;
    const icon = document.createElement('div'); icon.className = 'icon';
    const img = document.createElement('img'); img.alt = item.title + ' icon'; img.loading = 'lazy';
    let step = 0;
    img.onerror = () => { if (step === 0) { step = 1; img.src = FAVICON(item.url); } else { img.onerror = null; img.src = initialsDataUrl(item.title, 128, colorFromHost(item.url)); } };
    img.src = iconFor(item);
    icon.appendChild(img);
    a.appendChild(icon);
    const t = document.createElement('span'); t.className = 'tile-title'; t.textContent = item.title; a.appendChild(t);
    a.addEventListener('contextmenu', e => { if (!editorMode) return; e.preventDefault(); e.stopPropagation(); openContextMenu(e.clientX, e.clientY, gid, idx); });
    a.addEventListener('click', e => { if (editorMode) { e.preventDefault(); e.stopImmediatePropagation(); } });
    a.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (!editorMode) window.open(item.url, '_blank', 'noopener'); } });
    if (editorMode) {
      a.addEventListener('dragstart', e => onDragStart(e, gid, idx));
      a.addEventListener('dragend', onDragEnd);
      a.addEventListener('dragover', onTileDragOver);
      a.addEventListener('drop', e => onTileDrop(e, gid));
    } else {
      a.addEventListener('dragstart', e => e.preventDefault());
    }
    grid.appendChild(a);
  });
}

function openContextMenu(x, y, gid, idx){
  ctxMenu.innerHTML = '';
  const editBtn = document.createElement('button'); editBtn.type = 'button'; editBtn.textContent = '✏️ Bearbeiten';
  editBtn.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); closeContextMenu(); editLink(gid, idx); });
  ctxMenu.appendChild(editBtn);
  const delBtn = document.createElement('button'); delBtn.type = 'button'; delBtn.textContent = '🗑️ Entfernen';
  delBtn.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); closeContextMenu(); deleteLink(gid, idx); });
  ctxMenu.appendChild(delBtn);
  ctxMenu.style.left = x + 'px'; ctxMenu.style.top = y + 'px'; ctxMenu.style.display = 'block'; ctxMenu.setAttribute('aria-hidden', 'false');
}
function closeContextMenu(){ ctxMenu.style.display = 'none'; ctxMenu.setAttribute('aria-hidden', 'true'); }
document.addEventListener('click', () => closeContextMenu());
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeContextMenu(); });

function editLink(gid, idx){
  const item = allLinks[gid][idx];
  const newTitle = prompt('App-Name:', item.title); if (newTitle === null) return;
  let newUrl = prompt('URL:', item.url); if (newUrl === null) return;
  try { if (!/^https?:\/\//i.test(newUrl)) newUrl = 'https://' + newUrl; new URL(newUrl); } catch(e){ alert('Ungültige URL'); return; }
  let newIconUrl = prompt('Icon (Dateiname im Icon-Ordner oder URL, optional):', item.iconUrl || '');
  if (newIconUrl !== null) { newIconUrl = newIconUrl.trim(); } else { newIconUrl = item.iconUrl; }
  allLinks[gid][idx] = { title: newTitle.trim(), url: newUrl.trim(), iconUrl: newIconUrl || null };
  saveLinks(allLinks); buildAllGrids();
}
function deleteLink(gid, idx){
  const item = allLinks[gid][idx];
  if (!confirm('"' + item.title + '" wirklich entfernen?')) return;
  allLinks[gid].splice(idx, 1);
  saveLinks(allLinks); buildAllGrids();
}
function handleAdd(gid){
  const title = prompt('App-Name (z.B. GitHub):'); if (!title) return;
  let url = prompt('URL (mit https://):'); if (!url) return;
  try { if (!/^https?:\/\//i.test(url)) url = 'https://' + url; new URL(url); } catch(e){ alert('Ungültige URL'); return; }
  let iconUrl = prompt('Icon (Dateiname im Icon-Ordner oder URL, optional):');
  iconUrl = iconUrl ? iconUrl.trim() : null;
  if (!allLinks[gid]) allLinks[gid] = [];
  allLinks[gid].push({ title: title.trim(), url: url.trim(), iconUrl: iconUrl || null });
  saveLinks(allLinks); buildAllGrids();
}

/* ---- drag & drop ---- */
let dragState = null;
let placeholderEl = null;
function createPlaceholder(){ const el = document.createElement('div'); el.className = 'placeholder'; return el; }
function onDragStart(e, gid, idx){
  if (!editorMode) return;
  dragState = { fromSection: gid, fromIndex: idx };
  e.currentTarget.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
  const ghost = document.createElement('canvas'); ghost.width = 1; ghost.height = 1;
  e.dataTransfer.setDragImage(ghost, 0, 0);
}
function onDragEnd(e){
  e.currentTarget.classList.remove('dragging'); dragState = null;
  if (placeholderEl && placeholderEl.parentElement) placeholderEl.parentElement.removeChild(placeholderEl);
  placeholderEl = null;
}
function onTileDragOver(e){
  if (!editorMode || !dragState) return;
  e.preventDefault();
  const tile = e.currentTarget; const grid = tile.parentElement;
  if (!placeholderEl) placeholderEl = createPlaceholder();
  if (!grid.contains(placeholderEl)) { grid.insertBefore(placeholderEl, tile); }
  else {
    const rect = tile.getBoundingClientRect(); const offset = e.clientY - rect.top;
    if (offset > rect.height / 2) grid.insertBefore(placeholderEl, tile.nextSibling);
    else grid.insertBefore(placeholderEl, tile);
  }
}
function onTileDrop(e, targetGid){ if (!editorMode || !dragState) return; e.preventDefault(); applyReorder(targetGid); }
function onGridDragOver(e, targetGid){
  if (!editorMode || !dragState) return;
  e.preventDefault();
  const grid = grids[targetGid];
  if (!placeholderEl) placeholderEl = createPlaceholder();
  if (!grid.contains(placeholderEl)) grid.appendChild(placeholderEl);
}
function onGridDrop(e, targetGid){ if (!editorMode || !dragState) return; e.preventDefault(); applyReorder(targetGid); }
function applyReorder(targetGid){
  if (!dragState) return;
  const fromSection = dragState.fromSection; const fromIndex = dragState.fromIndex;
  const fromArray = allLinks[fromSection];
  if (!fromArray || fromIndex < 0 || fromIndex >= fromArray.length) { dragState = null; return; }
  const targetGrid = grids[targetGid]; const children = Array.from(targetGrid.children);
  let targetIndex = placeholderEl ? children.indexOf(placeholderEl) : children.length;
  if (targetIndex === -1) targetIndex = children.length;
  const item = fromArray.splice(fromIndex, 1)[0];
  if (fromSection === targetGid && fromIndex < targetIndex) targetIndex--;
  if (!allLinks[targetGid]) allLinks[targetGid] = [];
  allLinks[targetGid].splice(targetIndex, 0, item);
  saveLinks(allLinks); dragState = null;
  if (placeholderEl && placeholderEl.parentElement) placeholderEl.parentElement.removeChild(placeholderEl);
  placeholderEl = null;
  buildAllGrids();
}

/* ---- fallback-icons ---- */
function initialsDataUrl(text, size = 128, bg = '#2b6cb0'){
  const initials = (text || '').split(/\s+/).slice(0, 2).map(s => s[0]).join('').toUpperCase() || '?';
  const canvas = document.createElement('canvas'); canvas.width = size; canvas.height = size;
  const ctx = canvas.getContext('2d'); const r = Math.floor(size * 0.18);
  ctx.fillStyle = bg; roundRect(ctx, 0, 0, size, size, r); ctx.fill();
  ctx.fillStyle = 'rgba(255,255,255,0.06)'; roundRect(ctx, 0, 0, size, size, r); ctx.fill();
  ctx.fillStyle = '#fff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
  ctx.font = 'bold ' + Math.floor(size * 0.42) + 'px system-ui, -apple-system, "Segoe UI", Roboto, Arial';
  ctx.fillText(initials, size / 2, size / 2 + Math.floor(size * 0.02));
  return canvas.toDataURL('image/png');
}
function roundRect(ctx, x, y, w, h, r){
  ctx.beginPath(); ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
}
function colorFromHost(host){ let h = 0; const str = String(host || ''); for (let i = 0; i < str.length; i++){ h = (h << 5) - h + str.charCodeAt(i); h |= 0; } return 'hsl(' + (Math.abs(h) % 360) + ' 60% 45%)'; }

initializeTheme();
buildAllGrids();
window.Launcher = {
  add(title, url, gid = 'general'){ if (!allLinks[gid]) allLinks[gid] = []; allLinks[gid].push({ title, url, iconUrl: null }); saveLinks(allLinks); buildAllGrids(); },
  reset(){ resetLauncher(); },
  getOrder(){ return JSON.parse(JSON.stringify(allLinks)); },
  toggleEditorMode(on){ if (typeof on === 'boolean') { if (on !== editorMode) toggleEditorMode(); } else { toggleEditorMode(); } }
};
</script>
</body>
</html>
