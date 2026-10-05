<?php declare(strict_types=1);

// Geteilte Instanz-Variablen defensiv einlesen (Muster wie launcher-v2/index.php).
$dwl_theme=(isset($dwl_theme)&&in_array($dwl_theme,['light','dark'],true))?$dwl_theme:'auto';
$dwl_title=(isset($dwl_title)&&is_string($dwl_title)&&$dwl_title!=='')?$dwl_title:'Flipper Lab — Extra Apps';
$dwl_icon=(isset($dwl_icon)&&is_string($dwl_icon)&&$dwl_icon!=='')?$dwl_icon:'https://raw.githubusercontent.com/florianthepro/pages/main/content/media/dwl/index.svg';

if(!function_exists('h')){function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}

// Kurzer, echter Server-Check gegen die GitHub-Rate-Limit-API (wie im Original).
// Scheitert der Abruf, liefern wir einen neutralen Default zurueck.
function dwl_github_rate(string $token): array{
  $default=['ok'=>true,'limit'=>60,'remaining'=>60,'reset'=>time()+3600];
  $url='https://api.github.com/rate_limit';
  $headers=['User-Agent: flipper-lab-pages','Accept: application/vnd.github+json'];
  if($token!==''){$headers[]='Authorization: Bearer '.$token;}
  $body=false;
  if(function_exists('curl_init')){
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>true]);
    $body=curl_exec($ch);
    curl_close($ch);
  }
  if(($body===false||$body==='')&&ini_get('allow_url_fopen')){
    $ctx=stream_context_create(['http'=>['header'=>implode("\r\n",$headers),'timeout'=>8]]);
    $body=@file_get_contents($url,false,$ctx);
  }
  if(!is_string($body)||$body===''){return $default;}
  $j=json_decode($body,true);
  $core=$j['resources']['core']??($j['rate']??null);
  if(!is_array($core)){return $default;}
  return [
    'ok'=>true,
    'limit'=>(int)($core['limit']??60),
    'remaining'=>(int)($core['remaining']??60),
    'reset'=>(int)($core['reset']??(time()+3600)),
  ];
}

// --- Server-API (wie im Original holt der Browser Daten per ?api=...) ---
// Im Original erzeugt ein PHP-Backend auf xo.je den Extra-Apps-Katalog aus
// mehreren GitHub-Firmware-Repos und proxyt die .fap-Downloads. Dieser
// Katalog-/Proxy-Teil ist NICHT Teil der mitgeschnittenen Quelle und wird hier
// bewusst nicht erfunden. Reproduziert wird nur der echte, unkritische Teil:
// der GitHub-Rate-Check.
$dwl_api=(isset($_GET['api'])&&is_string($_GET['api']))?$_GET['api']:'';
if($dwl_api!==''){
  header('Content-Type: application/json; charset=utf-8');
  $dwl_token=(isset($_GET['token'])&&is_string($_GET['token']))?trim($_GET['token']):'';
  if($dwl_api==='rate'){
    echo json_encode(dwl_github_rate($dwl_token));
    exit;
  }
  // Katalog, Manifest-Details und .fap-Download brauchen das serverseitige
  // GitHub-Backend von xo.je, das hier fehlt -> ehrliche Fehlermeldung statt
  // erfundener Daten. Das Frontend faengt das sauber ab (Toast/Karte).
  http_response_code(501);
  echo json_encode([
    'ok'=>false,
    'message'=>'Katalog-/Download-Backend nicht verfuegbar: Der serverseitige GitHub-Extra-Apps-Dienst (Katalog, Manifest, .fap-Proxy) ist in dieser Reproduktion nicht enthalten.',
  ]);
  exit;
}

// data-theme nur bei fixem Light/Dark setzen, bei 'auto' weglassen.
$dt=$dwl_theme==='light'?' data-theme="light"':($dwl_theme==='dark'?' data-theme="dark"':'');
?>
<!doctype html>
<html lang="de"<?= $dt ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="user-scalable=no,initial-scale=1,maximum-scale=1,minimum-scale=1,width=device-width">
<meta name="description" content="Web platform for your Flipper — Extra Apps (Stock compare)">
<title><?= h($dwl_title) ?></title>
<link rel="icon" href="<?= h($dwl_icon) ?>">
<style>
:root{
  --bg:#0b0f14;
  --panel:#0f1622;
  --panel2:#101826;
  --card:#0c131f;
  --line:#1c2a3c;
  --text:#eaf2ff;
  --muted:#9fb0c7;
  --brand:#ff8200;
  --ok:#2ed832;
  --warn:#ffd24a;
  --bad:#ff5a5f;
}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif}
a{color:inherit;text-decoration:none}
code{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
.topbar{
  position:sticky;top:0;z-index:9;
  background:linear-gradient(180deg,rgba(16,24,38,0.95),rgba(12,19,31,0.92));
  border-bottom:1px solid var(--line);
  backdrop-filter: blur(10px);
}
.topbar .inner{
  max-width:1200px;margin:0 auto;
  display:flex;align-items:center;justify-content:space-between;
  padding:12px 16px;gap:12px;flex-wrap:wrap;
}
.brand{display:flex;align-items:center;gap:10px}
.logo{
  width:28px;height:28px;border-radius:10px;
  background: radial-gradient(circle at 30% 30%, #ffb36a, var(--brand));
  box-shadow: 0 0 0 1px rgba(255,130,0,0.25);
}
.brand .t{display:flex;flex-direction:column;gap:2px}
.brand .t b{font-size:13px;letter-spacing:0.2px}
.brand .t span{font-size:11px;color:var(--muted)}
.tabs{display:flex;gap:10px;align-items:center}
.tab{
  font-size:12px;color:var(--muted);
  padding:8px 10px;border-radius:12px;border:1px solid transparent;
}
.tab.active{color:var(--text);border-color:rgba(255,130,0,0.35);background:rgba(255,130,0,0.08)}
.container{max-width:1200px;margin:0 auto;padding:14px 16px}
.grid{display:grid;grid-template-columns:280px 1fr;gap:12px}
@media (max-width: 980px){.grid{grid-template-columns:1fr}}
.panel{
  background:linear-gradient(180deg,var(--panel2),var(--panel));
  border:1px solid var(--line);
  border-radius:18px;
  padding:12px;
}
.row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.input{
  width:100%;
  background:#0b121d;border:1px solid var(--line);
  color:var(--text);padding:10px 12px;border-radius:14px;outline:none;
}
.input:focus{border-color:rgba(255,130,0,0.35)}
.btn{
  background:#0b121d;border:1px solid var(--line);
  color:var(--text);padding:10px 12px;border-radius:14px;cursor:pointer;
}
.btn:hover{border-color:rgba(255,130,0,0.35)}
.btn.primary{background:rgba(255,130,0,0.12);border-color:rgba(255,130,0,0.35)}
.kpis{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}
.kpi{background:rgba(255,255,255,0.02);border:1px solid var(--line);border-radius:14px;padding:10px}
.kpi b{display:block;font-size:12px}
.kpi span{display:block;margin-top:3px;font-size:12px;color:var(--muted)}
.catlist{display:flex;flex-direction:column;gap:6px;max-height:54vh;overflow:auto;padding-right:4px;margin-top:10px}
.cat{padding:9px 10px;border-radius:14px;border:1px solid var(--line);background:rgba(255,255,255,0.01);cursor:pointer;font-size:12px;color:var(--muted)}
.cat:hover{border-color:rgba(255,130,0,0.35);color:var(--text)}
.cat.active{border-color:rgba(255,130,0,0.35);color:var(--text);background:rgba(255,130,0,0.06)}
.mainhead{display:flex;justify-content:space-between;gap:10px;align-items:flex-end;flex-wrap:wrap}
.mainhead h1{margin:0;font-size:18px}
.mainhead p{margin:6px 0 0 0;color:var(--muted);font-size:12px;line-height:1.4;max-width:900px}
.cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}
@media (max-width: 980px){.cards{grid-template-columns:1fr}}
.card{
  background:rgba(255,255,255,0.02);
  border:1px solid var(--line);
  border-radius:18px;
  padding:12px;
}
.card .top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
.title{font-size:14px;margin:0}
.sub{font-size:12px;color:var(--muted);margin-top:3px}
.desc{font-size:12px;line-height:1.4;color:#cfe0ff;margin-top:10px}
.pills{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
.pill{font-size:11px;padding:6px 8px;border-radius:999px;border:1px solid var(--line);background:rgba(255,255,255,0.02);color:#d8e6ff}
.badge{font-size:11px;padding:6px 8px;border-radius:999px;border:1px solid var(--line);display:inline-flex;gap:6px;align-items:center}
.badge.ok{background:rgba(46,216,50,0.12);color:#bff7c6}
.badge.warn{background:rgba(255,210,74,0.12);color:#ffe7a1}
.badge.bad{background:rgba(255,90,95,0.12);color:#ffd0d1}
.details{margin-top:10px}
summary{cursor:pointer;color:var(--muted);font-size:12px}
.smallnote{color:var(--muted);font-size:12px;line-height:1.45;margin-top:10px}
.hr{height:1px;background:var(--line);margin:10px 0}
.toast{
  position:fixed;right:14px;bottom:14px;z-index:99;
  background:rgba(16,24,38,0.95);border:1px solid var(--line);
  border-radius:16px;padding:10px 12px;max-width:420px;display:none
}
.toast b{display:block;font-size:12px}
.toast span{display:block;margin-top:4px;font-size:12px;color:var(--muted)}
.spin{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.18);border-top-color:rgba(255,255,255,0.72);border-radius:999px;animation:spin 0.8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>

<div class="topbar">
  <div class="inner">
    <div class="brand">
      <div class="logo"></div>
      <div class="t">
        <b>Flipper Lab</b>
        <span>Extra Apps · Stock Compare · Install via WebSerial</span>
      </div>
    </div>
    <div class="tabs">
      <div class="tab active">Apps</div>
      <div class="tab">Files (nicht benötigt)</div>
      <div class="tab">CLI (minimal integriert)</div>
      <div class="tab">Settings</div>
    </div>
  </div>
</div>

<div class="container">
  <div class="grid">

    <aside class="panel">
      <div class="row">
        <button class="btn primary" id="btnLoad"><span id="loadIcon">↻</span> Laden</button>
        <button class="btn" id="btnConnect">Connect</button>
        <button class="btn" id="btnDisconnect" disabled="">Disconnect</button>
      </div>

      <div style="margin-top:10px">
        <input class="input" id="search" placeholder="Apps suchen … (Name, ID, Kategorie)">
      </div>

      <div style="margin-top:10px">
        <input class="input" id="token" type="password" placeholder="Optional: GitHub Token (höhere Limits)">
        <div class="smallnote">
          Ohne Token ist das Limit typischerweise niedrig (IP‑basiert). Mit Token höher. 【4-2a970b】【5-3326c3】
        </div>
      </div>

      <div class="kpis">
        <div class="kpi"><b>Status</b><span id="status">Bereit</span></div>
        <div class="kpi"><b>Extra Apps</b><span id="count">—</span></div>
        <div class="kpi"><b>Kategorien</b><span id="catCount">—</span></div>
        <div class="kpi"><b>Rate</b><span id="rate">60/60 · reset 8/29/2026, 1:45:12 PM</span></div>
      </div>

      <div class="hr"></div>

      <div class="row" style="justify-content:space-between">
        <b style="font-size:12px">Kategorien</b>
        <span style="font-size:12px;color:var(--muted)" id="catHint">Alle</span>
      </div>
      <div class="catlist" id="cats">
        <div class="cat active" data-cat="__all">Alle</div>
      </div>

      <div class="hr"></div>
      <div class="smallnote">
        Install‑Pfad (SD): <code>/ext/apps/ExtraApps</code><br>
        Upload nutzt Flipper‑CLI (<code>storage write_chunk</code>) via WebSerial. 【1-ebe0bf】【3-470938】
      </div>
    </aside>

    <main class="panel">
      <div class="mainhead">
        <div>
          <h1>Extra Apps (nicht in Stock)</h1>
          <p>
            Katalog wird server‑seitig aus GitHub Repos erstellt und an den Browser als JSON geliefert.
            Beim Installieren wird nur die benötigte <code>.fap</code> geladen und dann auf den Flipper geschrieben.
          </p>
        </div>
        <div class="row">
          <button class="btn" id="btnOnlyFap">Nur .fap</button>
          <button class="btn" id="btnRefreshRate">Rate Check</button>
        </div>
      </div>

      <div id="cards" class="cards">
        <div class="card">
          <div class="top"><div>
            <p class="title">Bereit.</p>
            <p class="sub">Klicke „Laden“, um den Extra‑Apps‑Katalog zu erzeugen.</p>
          </div></div>
          <div class="desc">
            Hinweis: Viele Repos enthalten Quellcode ohne fertige <code>.fap</code>. Installieren geht nur mit vorhandener <code>.fap</code>.
          </div>
        </div>
      </div>

      <div class="smallnote">
        CLI Zugriff: Offiziell dokumentiert (Baudrate 230400, WebSerial kompatible Browser). 【1-ebe0bf】【2-93f97a】
      </div>
    </main>

  </div>
</div>

<div class="toast" id="toast"><b id="toastT"></b><span id="toastM"></span></div>

<script>
(() => {
  const TOKEN_KEY = "fx_token";
  const state = {
    token: localStorage.getItem(TOKEN_KEY) || "",
    meta: null,
    items: [],
    cats: [],
    selectedCat: "__all",
    search: "",
    onlyFap: false,
    serial: { port:null, reader:null, writer:null, open:false, buf:"" }
  };

  const el = {
    token: document.getElementById("token"),
    btnLoad: document.getElementById("btnLoad"),
    btnConnect: document.getElementById("btnConnect"),
    btnDisconnect: document.getElementById("btnDisconnect"),
    btnOnlyFap: document.getElementById("btnOnlyFap"),
    btnRefreshRate: document.getElementById("btnRefreshRate"),
    loadIcon: document.getElementById("loadIcon"),
    status: document.getElementById("status"),
    count: document.getElementById("count"),
    catCount: document.getElementById("catCount"),
    rate: document.getElementById("rate"),
    cats: document.getElementById("cats"),
    catHint: document.getElementById("catHint"),
    cards: document.getElementById("cards"),
    search: document.getElementById("search"),
    toast: document.getElementById("toast"),
    toastT: document.getElementById("toastT"),
    toastM: document.getElementById("toastM"),
  };

  el.token.value = state.token;
  el.token.addEventListener("change", () => {
    state.token = el.token.value.trim();
    localStorage.setItem(TOKEN_KEY, state.token);
  });

  function toast(title, msg, ms=2600){
    el.toastT.textContent = title;
    el.toastM.textContent = msg;
    el.toast.style.display = "block";
    setTimeout(()=> el.toast.style.display = "none", ms);
  }

  function setStatus(s, busy=false){
    el.status.textContent = s;
    el.loadIcon.innerHTML = busy ? '<span class="spin"></span>' : "↻";
  }

  function qs(obj){
    const p = new URLSearchParams(obj);
    return p.toString();
  }

  async function api(path, params={}){
    const url = `${location.pathname}?${qs({api:path, token: state.token, ...params})}`;
    const r = await fetch(url);
    const j = await r.json().catch(()=>({ok:false}));
    if(!r.ok || !j.ok) throw new Error(j.message || j.error || "API error");
    return j;
  }

  function scoreClass(v){
    if(v >= 85) return "ok";
    if(v >= 55) return "warn";
    return "bad";
  }

  function renderCats(){
    const base = `<div class="cat ${state.selectedCat==="__all"?"active":""}" data-cat="__all">Alle</div>`;
    const html = state.cats.map(c => {
      const active = state.selectedCat === c ? "active" : "";
      return `<div class="cat ${active}" data-cat="${escapeHtml(c)}">${escapeHtml(c)}</div>`;
    }).join("");
    el.cats.innerHTML = base + html;
    el.catCount.textContent = String(state.cats.length);
    el.catHint.textContent = state.selectedCat === "__all" ? "Alle" : state.selectedCat;

    el.cats.querySelectorAll(".cat").forEach(n=>{
      n.addEventListener("click", ()=>{
        state.selectedCat = n.getAttribute("data-cat") || "__all";
        renderCats();
        renderCards();
      });
    });
  }

  function escapeHtml(s){
    return (s ?? "").replace(/[&<>"']/g, m => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
    }[m]));
  }

  function filterItems(){
    const q = state.search.trim().toLowerCase();
    return state.items.filter(it => {
      if(state.onlyFap && !it.hasFap) return false;
      if(state.selectedCat !== "__all" && (it.category || "") !== state.selectedCat) return false;
      if(!q) return true;
      const hay = `${it.name} ${it.appId} ${it.category} ${it.group}`.toLowerCase();
      return hay.includes(q);
    });
  }

  function fwLabel(k){
    return state.meta?.[k]?.label || k;
  }

  function pickInstallSource(it){
    // prefer primary, but must have .fap
    const p = it.primary;
    if(it.sources?.[p]?.hasFap && (it.sources[p].faps||[]).length) return {fw:p, path: it.sources[p].faps[0]};
    // else any other fw with fap
    for(const fw of Object.keys(it.sources||{})){
      const s = it.sources[fw];
      if(s?.hasFap && (s.faps||[]).length) return {fw, path: s.faps[0]};
    }
    return null;
  }

  function renderCards(){
    const items = filterItems();
    el.count.textContent = String(items.length);

    if(!items.length){
      el.cards.innerHTML = `<div class="card"><p class="title">Keine Treffer.</p><p class="sub">Filter/Suche anpassen.</p></div>`;
      return;
    }

    el.cards.innerHTML = items.map(it=>{
      const srcKeys = Object.keys(it.sources||{});
      const srcPills = srcKeys.map(k=>`<span class="pill">${escapeHtml(fwLabel(k))}</span>`).join("");
      const badges = ["roguemaster","unleashed","xtreme"].map(k=>{
        const v = it.scores?.[k] ?? 0;
        return `<span class="badge ${scoreClass(v)}">${escapeHtml(fwLabel(k))}: ${Math.round(v)}</span>`;
      }).join(" ");

      const installSrc = pickInstallSource(it);
      const installState = installSrc ? "" : "disabled";
      const installText = installSrc ? "Install" : "Build needed";
      const fapPill = it.hasFap ? `<span class="pill">.fap</span>` : ``;

      return `
        <div class="card" data-app="${escapeHtml(it.appId)}">
          <div class="top">
            <div>
              <p class="title">${escapeHtml(it.name)}</p>
              <div class="sub">ID: <code>${escapeHtml(it.appId)}</code> · <code>${escapeHtml(it.category||it.group||"—")}</code></div>
            </div>
            <div class="row">
              <button class="btn" data-act="details">Details</button>
              <button class="btn primary" data-act="install" ${installState}>${installText}</button>
            </div>
          </div>

          <div class="desc">${escapeHtml(it.description || "Beschreibung kann via Manifest geladen werden (Details).")}</div>

          <div class="pills">${srcPills}${fapPill}</div>
          <div class="pills">${badges}</div>

          <details class="details">
            <summary>Install / Remove</summary>
            <div class="smallnote">
              Ziel: <code>/ext/apps/ExtraApps/${escapeHtml(it.name)}.fap</code><br>
              <button class="btn" data-act="remove">Remove</button>
              <span style="margin-left:8px;color:var(--muted)">${state.serial.open ? "Flipper verbunden" : "nicht verbunden"}</span>
            </div>
          </details>
        </div>
      `;
    }).join("");

    el.cards.querySelectorAll("button[data-act]").forEach(b=>{
      b.addEventListener("click", async ()=>{
        const card = b.closest(".card");
        const appId = card?.getAttribute("data-app");
        const it = state.items.find(x=>x.appId===appId);
        if(!it) return;

        const act = b.getAttribute("data-act");
        if(act === "details") await showDetails(it, b);
        if(act === "install") await installApp(it, b);
        if(act === "remove") await removeApp(it, b);
      });
    });
  }

  async function refreshRate(){
    try{
      const r = await api("rate");
      if(r.ok){
        const reset = new Date(r.reset*1000).toLocaleString();
        el.rate.textContent = `${r.remaining}/${r.limit} · reset ${reset}`;
      } else {
        el.rate.textContent = "—";
      }
    } catch {
      el.rate.textContent = "—";
    }
  }

  async function loadCatalog(){
    setStatus("Lade…", true);
    try{
      await refreshRate();
      const data = await api("catalog");
      state.meta = data.meta;
      state.items = data.items;
      state.cats = data.categories;
      state.selectedCat = "__all";

      renderCats();
      renderCards();

      const trunc = Object.entries(state.meta).filter(([k,v])=>v.truncated).map(([k,v])=>v.label);
      if(trunc.length) toast("Hinweis", `Tree truncated bei: ${trunc.join(", ")} (evtl. fehlen Apps).`, 4500);

      setStatus("Fertig ✅", false);
      toast("Loaded", `${state.items.length} Extra Apps gefunden.`);
    } catch(e){
      setStatus("Fehler", false);
      el.cards.innerHTML = `<div class="card"><p class="title">Fehler</p><div class="desc">${escapeHtml(String(e.message||e))}</div></div>`;
      toast("Error", String(e.message||e), 4500);
    }
  }

  async function showDetails(it, btn){
    btn.disabled = true;
    btn.textContent = "…";
    try{
      // Load manifest details from primary firmware if present
      const p = it.primary;
      const m = it.sources?.[p]?.manifest;
      if(!m){
        toast("Details", "Kein Manifest gefunden (nur Repo-Struktur).");
        return;
      }
      const d = await api("details", {fw: p, path: m.path, ext: m.ext});
      const det = d.details || {};
      if(det.name) it.name = det.name;
      if(det.category) it.category = det.category;
      if(det.description) it.description = det.description;

      // Update categories list if changed
      const catSet = new Set(state.items.map(x=>x.category || x.group || "Unkategorisiert"));
      state.cats = [...catSet].sort((a,b)=>a.localeCompare(b,"de"));
      renderCats();
      renderCards();
      toast("Details", "Manifest geladen.");
    } catch(e){
      toast("Details Error", String(e.message||e), 4500);
    } finally {
      btn.disabled = false;
      btn.textContent = "Details";
    }
  }

  // ---------------- WebSerial (Flipper CLI) ----------------
  const enc = new TextEncoder();
  const dec = new TextDecoder();

  async function connectFlipper(){
    if(!("serial" in navigator)){
      toast("WebSerial fehlt", "Nutze Chrome/Edge (Chromium).", 5000);
      return;
    }
    try{
      const port = await navigator.serial.requestPort();
      await port.open({ baudRate: 230400 }); // CLI doc describes 230400 【1-ebe0bf】【2-93f97a】
      const writer = port.writable.getWriter();
      const reader = port.readable.getReader();
      state.serial = { port, writer, reader, open:true, buf:"" };

      el.btnDisconnect.disabled = false;
      toast("Connected", "Flipper CLI verbunden.");
      setStatus("Flipper verbunden", false);

      // Small handshake: read some bytes if available, then send help? not necessary
      readLoop();
    } catch(e){
      toast("Connect Error", String(e.message||e), 4500);
    }
  }

  async function disconnectFlipper(){
    try{
      if(state.serial.reader) { try{ await state.serial.reader.cancel(); }catch{} }
      if(state.serial.writer) { try{ state.serial.writer.releaseLock(); }catch{} }
      if(state.serial.port) { try{ await state.serial.port.close(); }catch{} }
    } finally {
      state.serial = { port:null, writer:null, reader:null, open:false, buf:"" };
      el.btnDisconnect.disabled = true;
      setStatus("Bereit", false);
      toast("Disconnected", "Flipper getrennt.");
      renderCards();
    }
  }

  async function readLoop(){
    while(state.serial.open && state.serial.reader){
      const { value, done } = await state.serial.reader.read();
      if(done) break;
      if(value){
        state.serial.buf += dec.decode(value, {stream:true});
        // keep buffer from exploding
        if(state.serial.buf.length > 200000) state.serial.buf = state.serial.buf.slice(-80000);
      }
    }
  }

  async function writeLine(line){
    if(!state.serial.open) throw new Error("Flipper nicht verbunden");
    await state.serial.writer.write(enc.encode(line + "\r\n"));
  }

  function takeUntilPrompt(timeoutMs=5000){
    // CLI prompt often ends with ">:" shown in docs/examples 【1-ebe0bf】【9-84d5bd】
    const prompt = ">:";
    const start = performance.now();
    return new Promise((resolve, reject)=>{
      const tick = () => {
        if(!state.serial.open) return reject(new Error("Disconnected"));
        const idx = state.serial.buf.lastIndexOf(prompt);
        if(idx !== -1){
          const out = state.serial.buf.slice(0, idx);
          state.serial.buf = state.serial.buf.slice(idx + prompt.length);
          return resolve(out);
        }
        if(performance.now() - start > timeoutMs) return reject(new Error("Timeout waiting for prompt"));
        requestAnimationFrame(tick);
      };
      tick();
    });
  }

  function takeUntilEol(timeoutMs=3000){
    const start = performance.now();
    return new Promise((resolve, reject)=>{
      const tick = () => {
        if(!state.serial.open) return reject(new Error("Disconnected"));
        const idx = state.serial.buf.indexOf("\n");
        if(idx !== -1){
          const out = state.serial.buf.slice(0, idx+1);
          state.serial.buf = state.serial.buf.slice(idx+1);
          return resolve(out);
        }
        if(performance.now() - start > timeoutMs) return reject(new Error("Timeout waiting for EOL"));
        requestAnimationFrame(tick);
      };
      tick();
    });
  }

  async function mkdirp(path){
    // Best-effort: create each segment
    const parts = path.split("/").filter(Boolean);
    let cur = "";
    for(const p of parts){
      cur += "/" + p;
      await writeLine(`storage mkdir ${cur}`);
      await takeUntilPrompt(2000).catch(()=>{});
    }
  }

  async function removeFile(path){
    await writeLine(`storage remove ${path}`);
    await takeUntilPrompt(2500).catch(()=>{});
  }

  async function writeChunk(remotePath, chunkU8){
    // storage write_chunk "<path>" <n> then send n bytes then read until prompt
    // Sequence modeled after flipperzero-tools implementation 【3-470938】
    await writeLine(`storage write_chunk "${remotePath}" ${chunkU8.byteLength}`);
    await takeUntilEol(4000); // reads line after command
    await state.serial.writer.write(chunkU8);
    await takeUntilPrompt(6000);
  }

  async function installApp(it, btn){
    const src = pickInstallSource(it);
    if(!src){
      toast("Install", "Keine .fap im Repo gefunden. Build nötig.");
      return;
    }
    if(!state.serial.open){
      toast("Install", "Bitte erst Connect (WebSerial).");
      return;
    }

    btn.disabled = true;
    btn.textContent = "Installing…";
    try{
      const remoteDir = "/ext/apps/ExtraApps";
      await mkdirp(remoteDir);
      const fileName = (it.name || it.appId.replace(":","_")).replace(/[^\w\-. ]+/g,"_") + ".fap";
      const remotePath = `${remoteDir}/${fileName}`;

      await removeFile(remotePath);

      // Download only needed .fap via server proxy (no tree in browser)
      // Hinweis: Dieser Proxy (?api=fap) gehoert zum serverseitigen xo.je-Backend,
      // das hier nicht reproduziert wird -> Download schlaegt sauber fehl.
      const dl = `${location.pathname}?${qs({api:"fap", token:state.token, fw:src.fw, path:src.path})}`;
      toast("Download", "Lade .fap…");
      const resp = await fetch(dl);
      if(!resp.ok) throw new Error("Download failed");
      const buf = await resp.arrayBuffer();
      const u8 = new Uint8Array(buf);

      toast("Upload", "Schreibe auf Flipper…");
      const CHUNK = 1024; // similar to common tooling buffer sizes
      for(let off=0; off<u8.length; off += CHUNK){
        const part = u8.slice(off, Math.min(u8.length, off+CHUNK));
        await writeChunk(remotePath, part);
      }

      toast("Install", `OK: ${remotePath}`);
    } catch(e){
      toast("Install Error", String(e.message||e), 5500);
    } finally {
      btn.disabled = false;
      btn.textContent = "Install";
    }
  }

  async function removeApp(it, btn){
    if(!state.serial.open){
      toast("Remove", "Bitte erst Connect (WebSerial).");
      return;
    }
    btn.disabled = true;
    btn.textContent = "…";
    try{
      const remoteDir = "/ext/apps/ExtraApps";
      const fileName = (it.name || it.appId.replace(":","_")).replace(/[^\w\-. ]+/g,"_") + ".fap";
      const remotePath = `${remoteDir}/${fileName}`;
      await removeFile(remotePath);
      toast("Removed", remotePath);
    } catch(e){
      toast("Remove Error", String(e.message||e), 4500);
    } finally {
      btn.disabled = false;
      btn.textContent = "Remove";
    }
  }

  // ---------------- UI bindings ----------------
  el.search.addEventListener("input", ()=>{
    state.search = el.search.value;
    renderCards();
  });

  el.btnOnlyFap.addEventListener("click", ()=>{
    state.onlyFap = !state.onlyFap;
    el.btnOnlyFap.textContent = state.onlyFap ? "Alle Apps" : "Nur .fap";
    renderCards();
  });

  el.btnRefreshRate.addEventListener("click", refreshRate);
  el.btnLoad.addEventListener("click", loadCatalog);
  el.btnConnect.addEventListener("click", connectFlipper);
  el.btnDisconnect.addEventListener("click", disconnectFlipper);

  setStatus("Bereit", false);
  refreshRate().catch(()=>{});
})();
</script>


</body></html>
