<?php declare(strict_types=1);
// Geteilte Instanz-Variablen defensiv einlesen (Muster aus launcher-v2/index.php).
$securemessaging_theme=(isset($securemessaging_theme)&&in_array($securemessaging_theme,['light','dark'],true))?$securemessaging_theme:'auto';
$securemessaging_title=(isset($securemessaging_title)&&is_string($securemessaging_title)&&$securemessaging_title!=='')?$securemessaging_title:'Secure Messenger';
$securemessaging_icon=(isset($securemessaging_icon)&&is_string($securemessaging_icon)&&$securemessaging_icon!=='')?$securemessaging_icon:'';
if(!function_exists('h')){function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
// data-theme nur bei fixem Hell/Dunkel setzen, bei 'auto' weglassen.
$dt=$securemessaging_theme==='light'?' data-theme="light"':($securemessaging_theme==='dark'?' data-theme="dark"':'');
// Favicon-Fallback (Sprechblase mit Schloss), falls keine Icon-URL gesetzt ist.
$securemessaging_favfallback='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NCA2NCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSIjNThhNmZmIiBzdHJva2Utd2lkdGg9IjMiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+CjxwYXRoIGQ9Ik0xNSAxNEg0OWE1IDUgMCAwIDEgNSA1VjMzYTUgNSAwIDAgMS01IDVIMjhsLTggOFYzOEgxNWE1IDUgMCAwIDEtNS01VjE5YTUgNSAwIDAgMSA1LTVaIi8+CjxyZWN0IHg9IjI2IiB5PSIyNiIgd2lkdGg9IjEyIiBoZWlnaHQ9IjgiIHJ4PSIyIi8+CjxwYXRoIGQ9Ik0yOSAyNnYtM2EzIDMgMCAwIDEgNiAwdjMiLz4KPC9zdmc+Cg==';
$securemessaging_favhref=$securemessaging_icon!==''?$securemessaging_icon:$securemessaging_favfallback;
?>
<!doctype html>
<html lang="de"<?= $dt ?>>
<head>
<meta charset="utf-8">
<title><?= h($securemessaging_title) ?></title>
<link rel="icon" href="<?= h($securemessaging_favhref) ?>">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<style>
 /* Basis-Palette = Original (dunkel). Bleibt Standard bei 'auto' auf dunklem System. */
 :root {
  --bg: #05070b;
  --bg-soft: #0e1218;
  --fg: #e6eef8;
  --muted: #9fb0c8;
  --accent: #58a6ff;
  --danger: #d9534f;
  --border-soft: rgba(255,255,255,0.06);
  --surface: rgba(255,255,255,0.03);
  --surface-2: rgba(255,255,255,0.05);
  --surface-3: rgba(255,255,255,0.06);
  --own-bubble: rgba(88,166,255,0.18);
  --field-bg: #05070b;
 }
 /* Helle Palette: bei fix 'light' und bei 'auto' auf hellem System. */
 @media (prefers-color-scheme: light) {
  :root:not([data-theme="dark"]) {
   --bg: #ffffff;
   --bg-soft: #eef1f6;
   --fg: #0e1218;
   --muted: #586274;
   --accent: #1f6feb;
   --danger: #c9302c;
   --border-soft: rgba(0,0,0,0.10);
   --surface: rgba(0,0,0,0.03);
   --surface-2: rgba(0,0,0,0.05);
   --surface-3: rgba(0,0,0,0.07);
   --own-bubble: rgba(31,111,235,0.16);
   --field-bg: #ffffff;
  }
 }
 :root[data-theme="light"] {
  --bg: #ffffff;
  --bg-soft: #eef1f6;
  --fg: #0e1218;
  --muted: #586274;
  --accent: #1f6feb;
  --danger: #c9302c;
  --border-soft: rgba(0,0,0,0.10);
  --surface: rgba(0,0,0,0.03);
  --surface-2: rgba(0,0,0,0.05);
  --surface-3: rgba(0,0,0,0.07);
  --own-bubble: rgba(31,111,235,0.16);
  --field-bg: #ffffff;
 }
 * {
  box-sizing: border-box;
 }
 html, body {
  height: 100%;
  margin: 0;
  padding: 0;
  background: var(--bg-soft);
  color: var(--fg);
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  -webkit-font-smoothing: antialiased;
  -webkit-text-size-adjust: 100%;
 }
 body {
  padding-top: env(safe-area-inset-top);
  padding-bottom: env(safe-area-inset-bottom);
  padding-left: env(safe-area-inset-left);
  padding-right: env(safe-area-inset-right);
  display: flex;
  align-items: stretch;
  justify-content: stretch;
 }
 .app {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 100vh;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  background: var(--bg-soft);
 }
 .page {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  display: flex;
  flex-direction: column;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.25s ease;
  padding: 12px;
 }
 .page-active {
  opacity: 1;
  pointer-events: auto;
 }
 @media (min-width: 768px) {
  .page {
   padding: 18px 22px;
  }
 }
 h1 {
  margin: 0 0 4px;
 }
 .card {
  background: var(--surface);
  border-radius: 14px;
  padding: 12px;
  margin-bottom: 12px;
 }
 @media (min-width: 768px) {
  #page-start {
   display: flex;
   flex-direction: column;
   align-items: center;
   justify-content: center;
  }
  #page-start > div:first-child {
   width: 100%;
   max-width: 520px;
  }
  #page-start .card {
   width: 100%;
   max-width: 520px;
   padding: 16px 18px;
   margin-bottom: 16px;
  }
 }
 .card-title {
  font-size: 18px;
  margin-bottom: 8px;
 }
 .label {
  font-size: 13px;
  color: var(--muted);
  margin-bottom: 4px;
 }
 input, textarea, button {
  width: 100%;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--border-soft);
  background: var(--field-bg);
  color: var(--fg);
  font-size: 16px;
 }
 input:focus, textarea:focus {
  outline: 2px solid var(--accent);
  outline-offset: 0;
 }
 button {
  background: var(--accent);
  border: none;
  cursor: pointer;
 }
 button.text-btn {
  background: transparent;
  border: none;
  padding: 0 4px;
  color: var(--accent);
  font-weight: 500;
  width: auto;
 }
 button.icon-btn {
  background: var(--surface-2);
  border-radius: 999px;
  width: 36px;
  height: 36px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
 }
 button.danger {
  color: var(--danger);
 }
 .btn-primary {
  margin-top: 8px;
 }
 .text-small {
  font-size: 12px;
  color: var(--muted);
 }
 .spacer {
  height: 12px;
 }
 .topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 4px;
  border-bottom: 1px solid var(--border-soft);
 }
 .topbar-title {
  font-weight: 600;
  font-size: 16px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  padding: 0 8px;
 }
 .search-container {
  padding: 6px 4px 4px;
 }
 .list {
  flex: 1;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  padding: 4px;
 }
 .contact-item {
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--surface);
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  justify-content: space-between;
 }
 .contact-item:hover {
  background: var(--surface-3);
 }
 .contact-name {
  font-size: 15px;
 }
 .contact-meta {
  font-size: 12px;
  color: var(--muted);
 }
 .contact-item.contact-unknown .contact-meta {
  color: var(--danger);
 }
 .contact-item.contact-unread .contact-name {
  font-weight: 600;
 }
 .contact-item.contact-unread .contact-meta {
  color: var(--accent);
 }
 .chat-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
 }
 .chat-messages {
  flex: 1;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  padding: 8px 4px 8px;
  overscroll-behavior: contain;
  display: flex;
  flex-direction: column;
  gap: 6px;
 }
 .message-item {
  padding: 8px 10px;
  border-radius: 12px;
  background: var(--surface);
  max-width: 85%;
 }
 .message-own {
  background: var(--own-bubble);
  align-self: flex-end;
 }
 .message-foreign {
  background: var(--surface-2);
  align-self: flex-start;
 }
 .message-header {
  font-size: 11px;
  color: var(--muted);
  margin-bottom: 2px;
 }
 .message-text {
  font-size: 14px;
  white-space: pre-wrap;
  word-break: break-word;
 }
 .chat-input-bar {
  position: sticky;
  bottom: 0;
  left: 0;
  right: 0;
  background: var(--bg-soft);
  border-top: 1px solid var(--border-soft);
  padding: 6px 4px 8px;
  display: flex;
  align-items: flex-end;
  gap: 6px;
  z-index: 30;
 }
 .chat-input {
  flex: 1;
  resize: none;
  min-height: 38px;
  max-height: 120px;
  font-size: 16px;
  line-height: 1.4;
  overflow-y: auto;
 }
 .btn-send {
  width: auto;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--accent);
  color: #fff;
  white-space: nowrap;
  flex: 0 0 auto;
 }
 @media (max-width: 640px) {
  .btn-send {
   font-size: 14px;
   padding: 8px 10px;
  }
 }
 .debug {
  font-size: 11px;
  color: #f55;
  margin-top: 4px;
  white-space: pre-wrap;
  word-break: break-word;
 }
 #contact-suggestions {
  background: var(--surface);
  border-radius: 12px;
  margin: 6px 4px 4px;
  max-height: 180px;
  overflow-y: auto;
 }
 #contact-suggestions > div {
  padding: 10px 12px;
  font-size: 15px;
  border-bottom: 1px solid var(--border-soft);
 }
 #contact-suggestions > div:last-child {
  border-bottom: none;
 }
 #contact-suggestions > div:hover {
  background: var(--surface-3);
 }
 #login-username,
 #hidden-send-btn,
 #hidden-add-btn {
  display: none !important;
 }
</style>
</head>
<body>
<div class="app">
<section id="page-start" class="page page-active">
<div style="padding:8px 4px 12px">
<h1>Secure Messenger</h1>
<div class="text-small">E2E&#8209;Verschl&uuml;sselung</div>
</div>
<div class="card">
<div class="card-title">Registrieren</div>
<div class="label">Benutzername (a-zA-Z0-9_-)</div>
<input id="reg-username" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="z.B. max-mobil">
<button id="btn-register" class="btn-primary">Schl&uuml;ssel erzeugen &amp; registrieren</button>
<div class="spacer"></div>
<div class="text-small">Es werden nur &ouml;ffentliche Schl&uuml;ssel an den Server gesendet. Deine privaten Schl&uuml;ssel werden als Datei heruntergeladen.</div>
</div>
<div class="card">
<div class="card-title">Login</div>
<div class="label">Private-Keys-Datei ausw&auml;hlen (.json)</div>
<input type="file" id="priv-file" accept=".json,.txt">
<input id="login-username" type="text" autocomplete="off">
<div style="margin-top:8px; display:flex; align-items:center; gap:6px;">
<input type="checkbox" id="remember-login" style="width:auto;">
<label for="remember-login" class="text-small">Login im Browser merken (verschl&uuml;sselt)</label>
</div>
<button id="btn-login" class="btn-primary">Login</button>
<button id="btn-unlock-stored" class="btn-primary" style="margin-top:6px; display:none;">Mit gespeichertem Login entsperren</button>
<div id="login-debug" class="debug" style="display:none"></div>
<div class="spacer"></div>
<div class="text-small">Benutzername wird automatisch aus der Datei &uuml;bernommen.</div>
</div>
</section>
<section id="page-contacts" class="page">
<header class="topbar">
<button id="btn-toggle-search" class="icon-btn" aria-label="Suche"> &#128269; </button>
<div id="header-username" class="topbar-title"></div>
<button id="btn-logout" class="text-btn danger">Logout</button>
</header>
<div id="search-container" class="search-container" style="display:none">
<input id="contact-search" placeholder="Benutzername suchen &amp; per Enter hinzuf&uuml;gen" autocomplete="off" autocapitalize="none" spellcheck="false"></div>
<div id="contact-suggestions" style="display:none"></div>
<div id="contacts" class="list"></div>
<button id="hidden-add-btn" type="button"></button>
</section>
<section id="page-chat" class="page">
<header class="topbar">
<button id="btn-back" class="icon-btn" aria-label="Zur&uuml;ck"> &#8592; </button>
<div id="chat-header-name" class="topbar-title"></div>
</header>
<div class="chat-container">
<div id="chat-messages" class="chat-messages"></div>
<div class="chat-input-bar">
<textarea id="chat-input" class="chat-input" rows="1" placeholder="Nachricht schreiben&#8230;" autocomplete="off" autocapitalize="none" spellcheck="false"></textarea>
<button id="btn-send" class="btn-send" type="button">Senden</button>
</div>
<div id="send-debug" class="debug" style="display:none"></div>
</div>
<button id="hidden-send-btn" type="button"></button>
</section>
</div>
<script>
// Hinweis: Der Original-Server auf xo.je (Registrierung, Nutzerverzeichnis,
// Kontakt-/Nachrichten-Zustellung, Sitzung/CSRF) ist hier NICHT verfuegbar und
// kann von pages auch nicht gehostet werden. Der Transport wird deshalb lokal im
// Browser per localStorage nachgebildet (siehe smLocalServer weiter unten). Die
// Ende-zu-Ende-Kryptografie (Schluesselerzeugung, RSA-OAEP/AES-GCM/ECDSA,
// Signaturen) bleibt unveraendert und echt. Eine echte geraeteuebergreifende
// bzw. Echtzeit-Zustellung wuerde einen Server erfordern.
const API = location.pathname + '?action=';
// Der server-seitige CSRF-Token entfaellt in der lokalen Nachbildung.
let CSRF_TOKEN = '';

// --- Lokaler Ersatz-"Server" auf localStorage-Basis ---------------------------
function smLoad(key) {
 try { return JSON.parse(localStorage.getItem(key) || 'null') || {}; }
 catch (e) { return {}; }
}
function smSave(key, val) {
 try { localStorage.setItem(key, JSON.stringify(val)); } catch (e) {}
}
async function smLocalServer(action, opts) {
 const method = (opts.method || 'GET').toUpperCase();
 let body = {};
 if (opts.body) { try { body = JSON.parse(opts.body); } catch (e) { body = {}; } }
 const users = smLoad('sm_users'); // { username: { pubSign, pubEnc } }
 if (action === 'register') {
  if (!body.username || !body.pubSign || !body.pubEnc) return { ok: false, error: 'Ungueltige Daten' };
  if (users[body.username]) return { ok: false, error: 'Benutzername bereits vergeben' };
  users[body.username] = { pubSign: body.pubSign, pubEnc: body.pubEnc };
  smSave('sm_users', users);
  return { ok: true };
 }
 if (action === 'login') {
  const u = users[body.username];
  if (!u) return { ok: false, error: 'Unbekannter Benutzer' };
  if (u.pubSign !== body.pubSign) return { ok: false, error: 'Oeffentlicher Schluessel stimmt nicht ueberein' };
  return { ok: true, user: body.username, csrf: 'local-' + Math.random().toString(36).slice(2) };
 }
 if (action === 'users') {
  const list = Object.keys(users).map(n => ({ username: n, pubSign: users[n].pubSign, pubEnc: users[n].pubEnc }));
  return { ok: true, users: list };
 }
 if (action === 'contacts-list') {
  if (!currentUser) return { ok: false };
  const all = smLoad('sm_contacts');
  return { ok: true, contacts: Array.isArray(all[currentUser]) ? all[currentUser] : [] };
 }
 if (action === 'contacts') {
  if (!currentUser) return { ok: false };
  const all = smLoad('sm_contacts');
  if (!Array.isArray(all[currentUser])) all[currentUser] = [];
  if (body.blob) all[currentUser].push(body.blob);
  smSave('sm_contacts', all);
  return { ok: true };
 }
 if (action === 'messages') {
  if (!currentUser) return { ok: false };
  const all = smLoad('sm_inbox');
  return { ok: true, messages: Array.isArray(all[currentUser]) ? all[currentUser] : [] };
 }
 if (action === 'send') {
  const to = body.to;
  if (!to) return { ok: false };
  const all = smLoad('sm_inbox');
  if (!Array.isArray(all[to])) all[to] = [];
  if (body.blob) all[to].push(body.blob);
  smSave('sm_inbox', all);
  return { ok: true };
 }
 if (action === 'chat-state') {
  const seen = smLoad('sm_seen');
  if (method === 'POST') {
   if (!currentUser) return { ok: false };
   if (!seen[currentUser]) seen[currentUser] = {};
   if (body.contact) seen[currentUser][body.contact] = body.ts;
   smSave('sm_seen', seen);
   return { ok: true };
  }
  return { ok: true, state: { lastSeen: seen[currentUser] || {} } };
 }
 return { ok: false, error: 'Unbekannte Aktion' };
}
// Ersetzt den urspruenglichen fetch()-Aufruf an den xo.je-Server.
const api = (path, opts = {}) => {
 const idx = path.indexOf('action=');
 const action = idx >= 0 ? path.slice(idx + 'action='.length).split('&')[0] : '';
 return Promise.resolve().then(() => smLocalServer(action, opts));
};
// -----------------------------------------------------------------------------

function pemFrom(buf, label) {
 const bytes = new Uint8Array(buf);
 let binary = '';
 const chunk = 0x8000;
 for (let i = 0; i < bytes.length; i += chunk) {
  binary += String.fromCharCode.apply(null, Array.from(bytes.slice(i, i + chunk)));
 }
 const b64 = btoa(binary);
 const lines = b64.match(/.{1,64}/g) || [];
 return `-----BEGIN ${label}-----\n${lines.join('\n')}\n-----END ${label}-----\n`;
}
function b64ToArrayBuffer(b64) {
 b64 = b64.replace(/-/g, '+').replace(/_/g, '/');
 while (b64.length % 4) b64 += '=';
 const bin = atob(b64);
 const len = bin.length;
 const bytes = new Uint8Array(len);
 for (let i = 0; i < len; i++) bytes[i] = bin.charCodeAt(i);
 return bytes.buffer;
}
function pemToArrayBuffer(pem) {
 const b64 = pem.replace(/-----.*?-----/g, '').replace(/\s+/g, '');
 return b64ToArrayBuffer(b64);
}
async function genKeys() {
 const signKey = await crypto.subtle.generateKey(
  { name: 'ECDSA', namedCurve: 'P-256' },
  true,
  ['sign', 'verify']
 );
 const encKey = await crypto.subtle.generateKey(
  {
   name: 'RSA-OAEP',
   modulusLength: 4096,
   publicExponent: new Uint8Array([1, 0, 1]),
   hash: 'SHA-256'
  },
  true,
  ['encrypt', 'decrypt']
 );
 const pubSign = await crypto.subtle.exportKey('spki', signKey.publicKey);
 const privSign = await crypto.subtle.exportKey('pkcs8', signKey.privateKey);
 const pubEnc = await crypto.subtle.exportKey('spki', encKey.publicKey);
 const privEnc = await crypto.subtle.exportKey('pkcs8', encKey.privateKey);
 return { pubSign, privSign, pubEnc, privEnc };
}
function downloadFile(filename, content) {
 const a = document.createElement('a');
 const blob = new Blob([content], { type: 'application/octet-stream' });
 a.href = URL.createObjectURL(blob);
 a.download = filename;
 document.body.appendChild(a);
 a.click();
 a.remove();
 setTimeout(() => URL.revokeObjectURL(a.href), 3000);
}
function escapeHtml(s) {
 return String(s).replace(/[&<>\"']/g, c => ({
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;'
 }[c]));
}
async function deriveKeyFromPassphrase(passphrase, salt) {
 const enc = new TextEncoder();
 const baseKey = await crypto.subtle.importKey(
  'raw',
  enc.encode(passphrase),
  { name: 'PBKDF2' },
  false,
  ['deriveKey']
 );
 const key = await crypto.subtle.deriveKey(
  {
   name: 'PBKDF2',
   salt,
   iterations: 250000,
   hash: 'SHA-256'
  },
  baseKey,
  { name: 'AES-GCM', length: 256 },
  false,
  ['encrypt', 'decrypt']
 );
 return key;
}
function bufToB64(buf) {
 const bytes = new Uint8Array(buf);
 let binary = '';
 for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
 return btoa(binary);
}
function b64ToBuf(b64) {
 const bin = atob(b64);
 const len = bin.length;
 const bytes = new Uint8Array(len);
 for (let i = 0; i < len; i++) bytes[i] = bin.charCodeAt(i);
 return bytes.buffer;
}
async function storeKeysInBrowser(privateKeyData) {
 const passphrase = prompt('Passwort zum Verschlüsseln der Schlüssel im Browser (mind. 8 Zeichen):');
 if (!passphrase || passphrase.length < 8) {
  alert('Passwort zu kurz oder abgebrochen.');
  return;
 }
 const enc = new TextEncoder();
 const salt = crypto.getRandomValues(new Uint8Array(16));
 const iv = crypto.getRandomValues(new Uint8Array(12));
 const key = await deriveKeyFromPassphrase(passphrase, salt);
 const plaintext = enc.encode(JSON.stringify(privateKeyData));
 const ciphertext = await crypto.subtle.encrypt(
  { name: 'AES-GCM', iv },
  key,
  plaintext
 );
 const payload = {
  v: 1,
  salt: bufToB64(salt.buffer),
  iv: bufToB64(iv.buffer),
  data: bufToB64(ciphertext)
 };
 localStorage.setItem('secureMessengerStoredKeys', JSON.stringify(payload));
 alert('Login wurde verschlüsselt im Browser gespeichert.');
}
async function loadKeysFromBrowser() {
 const raw = localStorage.getItem('secureMessengerStoredKeys');
 if (!raw) {
  alert('Es sind keine gespeicherten Schlüssel vorhanden.');
  return null;
 }
 let obj;
 try {
  obj = JSON.parse(raw);
 } catch (e) {
  alert('Gespeicherte Daten sind beschädigt.');
  return null;
 }
 if (!obj || obj.v !== 1 || !obj.salt || !obj.iv || !obj.data) {
  alert('Gespeicherte Daten haben ein unbekanntes Format.');
  return null;
 }
 const passphrase = prompt('Passwort zum Entsperren der gespeicherten Schlüssel:');
 if (!passphrase) {
  return null;
 }
 const salt = new Uint8Array(b64ToBuf(obj.salt));
 const iv = new Uint8Array(b64ToBuf(obj.iv));
 const ciphertext = b64ToBuf(obj.data);
 try {
  const key = await deriveKeyFromPassphrase(passphrase, salt);
  const plaintextBuf = await crypto.subtle.decrypt(
   { name: 'AES-GCM', iv },
   key,
   ciphertext
  );
  const dec = new TextDecoder();
  const json = dec.decode(plaintextBuf);
  const data = JSON.parse(json);
  if (!data || !data.username || !data.privSign || !data.privEnc || !data.pubSign) {
   alert('Entschlüsselte Daten sind unvollständig.');
   return null;
  }
  return data;
 } catch (e) {
  alert('Entschlüsselung fehlgeschlagen. Passwort korrekt?');
  return null;
 }
}
let currentUser = null;
let privateKeyData = null;
let rsaPrivKey = null;
let ecdsaPrivSignKey = null;
let selectedContact = null;
let lastMessageBlobs = [];
let pollInterval = null;
const POLL_MS = 5000;
let allUsersCache = null;
let knownContacts = new Set();
let unknownContacts = new Set();
let contactLastSeen = new Map();
let contactLatestTs = new Map();
async function syncChatStateFromServer() {
 const res = await api(API + 'chat-state');
 if (!res.ok || !res.state || !res.state.lastSeen) return;
 const ls = res.state.lastSeen;
 for (const contact in ls) {
  const ts = Number(ls[contact]) || 0;
  if (!ts) continue;
  contactLastSeen.set(contact, ts);
  updateContactUnreadState(contact);
 }
}
async function persistLastSeen(contact) {
 const ts = contactLastSeen.get(contact);
 if (!ts) return;
 try {
  await api(API + 'chat-state', {
   method: 'POST',
   headers: { 'Content-Type': 'application/json' },
   body: JSON.stringify({ csrf: CSRF_TOKEN, contact, ts })
  });
 } catch (e) {}
}
function showPage(name) {
 document.getElementById('page-start').classList.remove('page-active');
 document.getElementById('page-contacts').classList.remove('page-active');
 document.getElementById('page-chat').classList.remove('page-active');
 if (name === 'start') {
  document.getElementById('page-start').classList.add('page-active');
 } else if (name === 'contacts') {
  document.getElementById('page-contacts').classList.add('page-active');
 } else if (name === 'chat') {
  document.getElementById('page-chat').classList.add('page-active');
 }
}
document.getElementById('btn-register').addEventListener('click', async () => {
 const username = document.getElementById('reg-username').value.trim();
 if (!/^[a-zA-Z0-9_\-]{3,64}$/.test(username)) {
  alert('Ungültiger Benutzername');
  return;
 }
 try {
  const k = await genKeys();
  const pubSignPem = pemFrom(k.pubSign, 'PUBLIC KEY');
  const privSignPem = pemFrom(k.privSign, 'PRIVATE KEY');
  const pubEncPem = pemFrom(k.pubEnc, 'PUBLIC KEY');
  const privEncPem = pemFrom(k.privEnc, 'PRIVATE KEY');
  const payload = {
   username,
   pubSign: pubSignPem,
   pubEnc: pubEncPem,
   csrf: CSRF_TOKEN
  };
  const res = await api(API + 'register', {
   method: 'POST',
   headers: { 'Content-Type': 'application/json' },
   body: JSON.stringify(payload)
  });
  if (!res.ok) {
   alert('Registrierung fehlgeschlagen: ' + (res.error || res.raw || 'unbekannter Fehler'));
   return;
  }
  const exportObj = {
   username,
   privSign: privSignPem,
   privEnc: privEncPem,
   pubSign: pubSignPem,
   pubEnc: pubEncPem,
   created: Date.now()
  };
  downloadFile(username + '-private-keys.json', JSON.stringify(exportObj));
  alert('Registrierung erfolgreich. Private Schlüssel wurden heruntergeladen.');
 } catch (e) {
  alert('Fehler beim Erzeugen der Schlüssel: ' + e.message);
 }
});
const privFileInput = document.getElementById('priv-file');
const loginDebug = document.getElementById('login-debug');
privFileInput.addEventListener('change', async () => {
 loginDebug.style.display = 'none';
 loginDebug.textContent = '';
 if (!privFileInput.files || privFileInput.files.length === 0) {
  privateKeyData = null;
  return;
 }
 const f = privFileInput.files[0];
 const txt = await f.text();
 try {
  const obj = JSON.parse(txt);
  if (!obj.username || !obj.privSign || !obj.privEnc || !obj.pubSign) {
   throw new Error('Datei enthält nicht alle benötigten Felder.');
  }
  privateKeyData = obj;
  document.getElementById('login-username').value = obj.username;
  alert('Schlüsseldatei geladen für Benutzer: ' + obj.username);
 } catch (e) {
  privateKeyData = null;
  alert('Ungültige private-keys-Datei: ' + e.message);
 }
});
document.getElementById('btn-login').addEventListener('click', async () => {
 loginDebug.style.display = 'none';
 loginDebug.textContent = '';
 if (!privateKeyData) {
  alert('Bitte zuerst deine private-keys.json auswählen.');
  return;
 }
 const username = privateKeyData.username;
 const pubSignPem = privateKeyData.pubSign;
 try {
  const payload = { username, pubSign: pubSignPem, csrf: CSRF_TOKEN };
  const res = await api(API + 'login', {
   method: 'POST',
   headers: { 'Content-Type': 'application/json' },
   body: JSON.stringify(payload)
  });
  if (!res.ok) {
   loginDebug.style.display = 'block';
   loginDebug.textContent = JSON.stringify(res, null, 2);
   alert('Login fehlgeschlagen — siehe Debug.');
   return;
  }
  currentUser = res.user || username;
  document.getElementById('login-username').value = currentUser;
  document.getElementById('header-username').textContent = currentUser;
  if (res.csrf) {
   CSRF_TOKEN = res.csrf;
  }
  try {
   const privEncBuf = pemToArrayBuffer(privateKeyData.privEnc);
   rsaPrivKey = await crypto.subtle.importKey(
    'pkcs8',
    privEncBuf,
    { name: 'RSA-OAEP', hash: 'SHA-256' },
    false,
    ['decrypt']
   );
   const privSignBuf = pemToArrayBuffer(privateKeyData.privSign);
   ecdsaPrivSignKey = await crypto.subtle.importKey(
    'pkcs8',
    privSignBuf,
    { name: 'ECDSA', namedCurve: 'P-256' },
    false,
    ['sign']
   );
  } catch (e) {
   alert('Fehler beim Import der privaten Schlüssel: ' + e.message);
   return;
  }
  const rememberCheckbox = document.getElementById('remember-login');
  if (rememberCheckbox && rememberCheckbox.checked) {
   try {
    await storeKeysInBrowser(privateKeyData);
   } catch (e) {
    alert('Speichern im Browser fehlgeschlagen: ' + e.message);
   }
  }
  showPage('contacts');
  await ensureUsersList();
  await loadContacts();
  await syncChatStateFromServer();
  startPollingMessages();
  alert('Eingeloggt als ' + currentUser);
 } catch (e) {
  loginDebug.style.display = 'block';
  loginDebug.textContent = e.message;
  alert('Fehler beim Login — siehe Debug.');
 }
});
function doLogout() {
 stopPollingMessages();
 location.reload();
}
document.getElementById('btn-logout').addEventListener('click', doLogout);
const unlockBtn = document.getElementById('btn-unlock-stored');
if (unlockBtn) {
 const stored = localStorage.getItem('secureMessengerStoredKeys');
 if (stored) {
  unlockBtn.style.display = 'block';
 }
 unlockBtn.addEventListener('click', async () => {
  const data = await loadKeysFromBrowser();
  if (!data) return;
  privateKeyData = data;
  document.getElementById('login-username').value = data.username;
  alert('Gespeicherte Schlüssel wurden geladen. Bitte jetzt Login drücken.');
 });
}
async function ensureUsersList() {
 if (allUsersCache && Array.isArray(allUsersCache)) return allUsersCache;
 const res = await api(API + 'users');
 if (res.ok && Array.isArray(res.users)) {
  allUsersCache = res.users;
 } else {
  allUsersCache = [];
 }
 return allUsersCache;
}
async function loadContacts() {
 const contactsDiv = document.getElementById('contacts');
 contactsDiv.innerHTML = '';
 if (!rsaPrivKey || !currentUser) return;
 const contactsRes = await api(API + 'contacts-list');
 if (!contactsRes.ok || !Array.isArray(contactsRes.contacts)) {
  return;
 }
 for (const line of contactsRes.contacts) {
  let parsed;
  try {
   parsed = JSON.parse(line);
  } catch (e) {
   continue;
  }
  if (!parsed || !parsed.contact || !parsed.encKey || !parsed.iv) continue;
  try {
   const encKeyBuf = b64ToArrayBuffer(parsed.encKey);
   const rawKey = await crypto.subtle.decrypt(
    { name: 'RSA-OAEP' },
    rsaPrivKey,
    encKeyBuf
   );
   const key = await crypto.subtle.importKey(
    'raw',
    rawKey,
    { name: 'AES-GCM' },
    false,
    ['decrypt']
   );
   const iv = new Uint8Array(b64ToArrayBuffer(parsed.iv));
   const ct = b64ToArrayBuffer(parsed.contact);
   const plainBuf = await crypto.subtle.decrypt(
    { name: 'AES-GCM', iv },
    key,
    ct
   );
   const decrypted = new TextDecoder().decode(plainBuf);
   const contactObj = JSON.parse(decrypted);
   const username = contactObj.username;
   knownContacts.add(username);
   unknownContacts.delete(username);
   renderContact(username, false);
  } catch (e) {
   continue;
  }
 }
}
function updateContactUnreadState(username) {
 const contactsDiv = document.getElementById('contacts');
 const item = contactsDiv.querySelector(`.contact-item[data-username="${username}"]`);
 if (!item) return;
 const metaEl = item.querySelector('.contact-meta');
 if (!metaEl) return;
 const isUnknown = item.classList.contains('contact-unknown');
 let label = isUnknown ? 'Unbekannter Kontakt' : 'Kontakt';
 const latest = contactLatestTs.get(username) || 0;
 const seen = contactLastSeen.get(username) || 0;
 if (latest > seen) {
  label += ' • Neu';
  item.classList.add('contact-unread');
 } else {
  item.classList.remove('contact-unread');
 }
 metaEl.textContent = label;
}
function renderContact(username, isUnknown = false) {
 const contactsDiv = document.getElementById('contacts');
 let item = contactsDiv.querySelector(`.contact-item[data-username="${username}"]`);
 if (!item) {
  item = document.createElement('div');
  item.className = 'contact-item';
  item.dataset.username = username;
  const left = document.createElement('div');
  left.innerHTML = `
    <div class="contact-name">${escapeHtml(username)}</div>
    <div class="contact-meta"></div>
  `;
  const right = document.createElement('div');
  right.className = 'contact-meta';
  right.textContent = '›';
  item.appendChild(left);
  item.appendChild(right);
  item.addEventListener('click', async () => {
   selectedContact = username;
   document.getElementById('chat-header-name').textContent = username;
   lastMessageBlobs = [];
   document.getElementById('chat-messages').innerHTML = '';
   if (item.classList.contains('contact-unknown')) {
    item.classList.remove('contact-unknown');
    unknownContacts.delete(username);
    knownContacts.add(username);
   }
   const latest = contactLatestTs.get(username) || Date.now();
   contactLastSeen.set(username, latest);
   updateContactUnreadState(username);
   await persistLastSeen(username);
   showPage('chat');
   loadMessagesAndDecrypt().catch(() => {});
  });
  contactsDiv.appendChild(item);
 }
 if (isUnknown) {
  item.classList.add('contact-unknown');
  unknownContacts.add(username);
  knownContacts.delete(username);
 } else {
  item.classList.remove('contact-unknown');
  knownContacts.add(username);
  unknownContacts.delete(username);
 }
 updateContactUnreadState(username);
}
document.getElementById('btn-toggle-search').addEventListener('click', () => {
 const sc = document.getElementById('search-container');
 const input = document.getElementById('contact-search');
 const visible = sc.style.display !== 'none';
 sc.style.display = visible ? 'none' : 'block';
 if (!visible) {
  setTimeout(() => input.focus(), 50);
 }
});
const contactSearchInput = document.getElementById('contact-search');
const suggestionBox = document.getElementById('contact-suggestions');
contactSearchInput.addEventListener('keydown', async (e) => {
 if (e.key === 'Enter') {
  e.preventDefault();
  await handleContactSearch();
 }
});
contactSearchInput.addEventListener('input', async () => {
 const q = contactSearchInput.value.trim().toLowerCase();
 if (!q) {
  suggestionBox.style.display = 'none';
  suggestionBox.innerHTML = '';
  return;
 }
 const users = await ensureUsersList();
 const list = users
  .filter(u => u.username.toLowerCase().includes(q))
  .slice(0, 20);
 if (list.length === 0) {
  suggestionBox.style.display = 'none';
  suggestionBox.innerHTML = '';
  return;
 }
 suggestionBox.innerHTML = '';
 for (const u of list) {
  const item = document.createElement('div');
  item.textContent = u.username;
  item.addEventListener('click', () => {
   contactSearchInput.value = u.username;
   suggestionBox.style.display = 'none';
   handleContactSearch();
  });
  suggestionBox.appendChild(item);
 }
 suggestionBox.style.display = 'block';
});
async function handleContactSearch() {
 const name = contactSearchInput.value.trim();
 if (!/^[a-zA-Z0-9_\-]{3,64}$/.test(name)) {
  alert('Ungültiger Benutzername');
  return;
 }
 const users = await ensureUsersList();
 const target = users.find(u => u.username === name);
 if (!target) {
  alert('Benutzer nicht gefunden');
  return;
 }
 await addContact(target);
}
async function addContact(target) {
 if (!privateKeyData || !ecdsaPrivSignKey || !rsaPrivKey) {
  alert('Private Schlüssel nicht geladen – bitte neu einloggen.');
  return;
 }
 try {
  const contactObj = {
   username: target.username,
   pubSign: target.pubSign,
   pubEnc: target.pubEnc,
   added: Date.now()
  };
  const contactStr = JSON.stringify(contactObj);
  const encKey = await crypto.subtle.generateKey(
   { name: 'AES-GCM', length: 256 },
   true,
   ['encrypt', 'decrypt']
  );
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const encBuf = await crypto.subtle.encrypt(
   { name: 'AES-GCM', iv },
   encKey,
   new TextEncoder().encode(contactStr)
  );
  const rawKey = await crypto.subtle.exportKey('raw', encKey);
  const users = await ensureUsersList();
  const myName = currentUser;
  const myPub = (users || []).find(u => u.username === myName)?.pubEnc;
  if (!myPub) {
   alert('Eigener öffentlicher Schlüssel nicht gefunden');
   return;
  }
  const myPubBuf = pemToArrayBuffer(myPub);
  const myPubKey = await crypto.subtle.importKey(
   'spki',
   myPubBuf,
   { name: 'RSA-OAEP', hash: 'SHA-256' },
   false,
   ['encrypt']
  );
  const encryptedKey = await crypto.subtle.encrypt(
   { name: 'RSA-OAEP' },
   myPubKey,
   rawKey
  );
  const signature = await crypto.subtle.sign(
   { name: 'ECDSA', hash: { name: 'SHA-256' } },
   ecdsaPrivSignKey,
   new TextEncoder().encode(contactStr)
  );
  const blobObj = {
   contact: btoa(String.fromCharCode(...new Uint8Array(encBuf))),
   encKey: btoa(String.fromCharCode(...new Uint8Array(encryptedKey))),
   iv: btoa(String.fromCharCode(...new Uint8Array(iv))),
   signature: btoa(String.fromCharCode(...new Uint8Array(signature)))
  };
  const res = await api(API + 'contacts', {
   method: 'POST',
   headers: { 'Content-Type': 'application/json' },
   body: JSON.stringify({
    blob: JSON.stringify(blobObj),
    csrf: CSRF_TOKEN
   })
  });
  if (!res.ok) {
   alert('Kontakt hinzufügen fehlgeschlagen');
   return;
  }
  knownContacts.add(target.username);
  unknownContacts.delete(target.username);
  renderContact(target.username, false);
  selectedContact = target.username;
  document.getElementById('chat-header-name').textContent = target.username;
  lastMessageBlobs = [];
  document.getElementById('chat-messages').innerHTML = '';
  showPage('chat');
  await loadMessagesAndDecrypt();
  alert('Kontakt hinzugefügt');
 } catch (e) {
  alert('Fehler beim Hinzufügen: ' + e.message);
 }
}
document.getElementById('btn-back').addEventListener('click', () => {
 selectedContact = null;
 showPage('contacts');
});
const chatInput = document.getElementById('chat-input');
const sendBtn = document.getElementById('btn-send');
chatInput.addEventListener('input', () => {
 chatInput.style.height = 'auto';
 chatInput.style.height = chatInput.scrollHeight + 'px';
});
chatInput.addEventListener('keydown', async (e) => {
 if (e.key === 'Enter' && !e.shiftKey) {
  e.preventDefault();
  await sendCurrentMessage();
 }
});
if (sendBtn) {
 sendBtn.addEventListener('click', async () => {
  await sendCurrentMessage();
 });
}
async function sendCurrentMessage() {
 if (!selectedContact) {
  alert('Kein Kontakt ausgewählt');
  return;
 }
 if (!privateKeyData || !ecdsaPrivSignKey) {
  alert('Private Schlüssel nicht geladen – bitte neu einloggen.');
  return;
 }
 const to = selectedContact;
 const text = chatInput.value.trim();
 if (!text) return;
 const from = currentUser;
 const sendDbg = document.getElementById('send-debug');
 sendDbg.style.display = 'none';
 sendDbg.textContent = '';
 try {
  const encKey = await crypto.subtle.generateKey(
   { name: 'AES-GCM', length: 256 },
   true,
   ['encrypt', 'decrypt']
  );
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const rawKey = await crypto.subtle.exportKey('raw', encKey);
  const payloadRecipient = { from, to, ts: Date.now(), text };
  const payloadStrRecipient = JSON.stringify(payloadRecipient);
  const encBufRecipient = await crypto.subtle.encrypt(
   { name: 'AES-GCM', iv },
   encKey,
   new TextEncoder().encode(payloadStrRecipient)
  );
  const users = await ensureUsersList();
  const recipientObj = users.find(u => u.username === to);
  if (!recipientObj) {
   throw new Error('Empfänger nicht gefunden');
  }
  const pubEncBufRecipient = pemToArrayBuffer(recipientObj.pubEnc);
  const pubKeyRecipient = await crypto.subtle.importKey(
   'spki',
   pubEncBufRecipient,
   { name: 'RSA-OAEP', hash: 'SHA-256' },
   false,
   ['encrypt']
  );
  const encryptedKeyRecipient = await crypto.subtle.encrypt(
   { name: 'RSA-OAEP' },
   pubKeyRecipient,
   rawKey
  );
  const signatureRecipient = await crypto.subtle.sign(
   { name: 'ECDSA', hash: { name: 'SHA-256' } },
   ecdsaPrivSignKey,
   new TextEncoder().encode(payloadStrRecipient)
  );
  const blobRecipient = {
   from,
   to,
   ts: payloadRecipient.ts,
   encKey: btoa(String.fromCharCode(...new Uint8Array(encryptedKeyRecipient))),
   iv: btoa(String.fromCharCode(...iv)),
   ciphertext: btoa(String.fromCharCode(...new Uint8Array(encBufRecipient))),
   signature: btoa(String.fromCharCode(...new Uint8Array(signatureRecipient)))
  };
  const prefix = `Gesendet an ${to}\n`;
  const payloadSelf = { from, to: from, ts: Date.now(), text: prefix + text };
  const encKeySelf = await crypto.subtle.generateKey(
   { name: 'AES-GCM', length: 256 },
   true,
   ['encrypt', 'decrypt']
  );
  const ivSelf = crypto.getRandomValues(new Uint8Array(12));
  const rawKeySelf = await crypto.subtle.exportKey('raw', encKeySelf);
  const encBufSelf = await crypto.subtle.encrypt(
   { name: 'AES-GCM', iv: ivSelf },
   encKeySelf,
   new TextEncoder().encode(JSON.stringify(payloadSelf))
  );
  const myPub = users.find(u => u.username === from).pubEnc;
  const myPubBuf = pemToArrayBuffer(myPub);
  const myPubKey = await crypto.subtle.importKey(
   'spki',
   myPubBuf,
   { name: 'RSA-OAEP', hash: 'SHA-256' },
   false,
   ['encrypt']
  );
  const encryptedKeySelf = await crypto.subtle.encrypt(
   { name: 'RSA-OAEP' },
   myPubKey,
   rawKeySelf
  );
  const signatureSelf = await crypto.subtle.sign(
   { name: 'ECDSA', hash: { name: 'SHA-256' } },
   ecdsaPrivSignKey,
   new TextEncoder().encode(JSON.stringify(payloadSelf))
  );
  const blobSelf = {
   from,
   to: from,
   ts: payloadSelf.ts,
   encKey: btoa(String.fromCharCode(...new Uint8Array(encryptedKeySelf))),
   iv: btoa(String.fromCharCode(...new Uint8Array(ivSelf))),
   ciphertext: btoa(String.fromCharCode(...new Uint8Array(encBufSelf))),
   signature: btoa(String.fromCharCode(...new Uint8Array(signatureSelf)))
  };
  await api(API + 'send', {
   method: 'POST',
   headers: { 'Content-Type': 'application/json' },
   body: JSON.stringify({ to, blob: JSON.stringify(blobRecipient), csrf: CSRF_TOKEN })
  });
  await api(API + 'send', {
   method: 'POST',
   headers: { 'Content-Type': 'application/json' },
   body: JSON.stringify({ to: from, blob: JSON.stringify(blobSelf), csrf: CSRF_TOKEN })
  });
  chatInput.value = '';
  chatInput.style.height = 'auto';
  lastMessageBlobs = [];
  await loadMessagesAndDecrypt();
 } catch (e) {
  sendDbg.style.display = 'block';
  sendDbg.textContent = e.message;
  alert('Fehler beim Senden: ' + e.message);
 }
}
function inboxIsAtBottom(container, threshold = 40) {
 return (container.scrollHeight - container.clientHeight - container.scrollTop) <= threshold;
}
function renderMessageElement(parsed, decryptedText, verifyInfo, isOwn) {
 const el = document.createElement('div');
 el.className = 'message-item ' + (isOwn ? 'message-own' : 'message-foreign');
 if (decryptedText !== null) {
  let plainText = decryptedText;
  try {
   const maybe = JSON.parse(decryptedText);
   if (maybe && maybe.text) plainText = maybe.text;
  } catch (e) {}
  const time = new Date(Number(parsed.ts) || Date.now()).toLocaleString();
  const header = document.createElement('div');
  header.className = 'message-header';
  header.textContent = `${parsed.from} → ${parsed.to} • ${time}${verifyInfo}`;
  const body = document.createElement('div');
  body.className = 'message-text';
  body.textContent = plainText;
  el.appendChild(header);
  el.appendChild(body);
 } else {
  const header = document.createElement('div');
  header.className = 'message-header';
  header.textContent = `Verschlüsselter Blob von ${parsed.from || 'unknown'} (Entschlüsselung fehlgeschlagen)`;
  const body = document.createElement('div');
  body.className = 'message-text';
  body.textContent = JSON.stringify(parsed);
  el.appendChild(header);
  el.appendChild(body);
 }
 return el;
}
async function tryRenderParsedMessage(parsed, currentUser, selectedContact) {
 if (!rsaPrivKey) return null;
 if (parsed.to !== currentUser && parsed.from !== currentUser) {
  return null;
 }
 let decrypted = null;
 let verifyInfo = '';
 try {
  const encKeyBuf = b64ToArrayBuffer(parsed.encKey);
  const rawKey = await crypto.subtle.decrypt(
   { name: 'RSA-OAEP' },
   rsaPrivKey,
   encKeyBuf
  );
  const key = await crypto.subtle.importKey(
   'raw',
   rawKey,
   { name: 'AES-GCM' },
   false,
   ['decrypt']
  );
  const iv = new Uint8Array(b64ToArrayBuffer(parsed.iv));
  const ct = b64ToArrayBuffer(parsed.ciphertext);
  const plainBuf = await crypto.subtle.decrypt(
   { name: 'AES-GCM', iv },
   key,
   ct
  );
  decrypted = new TextDecoder().decode(plainBuf);
  const users = await ensureUsersList();
  const senderUser = users.find(u => u.username === parsed.from);
  if (senderUser && senderUser.pubSign) {
   try {
    const pubBuf = pemToArrayBuffer(senderUser.pubSign);
    const pubKey = await crypto.subtle.importKey(
     'spki',
     pubBuf,
     { name: 'ECDSA', namedCurve: 'P-256' },
     false,
     ['verify']
    );
    const sigBuf = b64ToArrayBuffer(parsed.signature);
    const ok = await crypto.subtle.verify(
     { name: 'ECDSA', hash: { name: 'SHA-256' } },
     pubKey,
     sigBuf,
     new TextEncoder().encode(decrypted)
    );
    verifyInfo = ok ? ' (Signatur OK)' : ' (Signatur FEHLER)';
   } catch (e) {
    verifyInfo = ' (Signaturprüfung fehlgeschlagen)';
   }
  }
 } catch (e) {
  decrypted = null;
 }
 const isFromContact = parsed.from === selectedContact;
 const isFromMe = parsed.from === currentUser;
 if (!isFromContact && !isFromMe) return null;
 if (isFromMe && decrypted !== null) {
  let plainText = decrypted;
  let jsonObj = null;
  try {
   jsonObj = JSON.parse(decrypted);
   if (jsonObj && jsonObj.text) {
    plainText = jsonObj.text;
   }
  } catch (e) {}
  let inferredTarget = null;
  if (jsonObj && jsonObj.to && jsonObj.to !== currentUser) {
   inferredTarget = jsonObj.to;
  } else {
   const firstLine = plainText.split(/\r?\n/)[0] || '';
   if (firstLine.startsWith('Gesendet an ')) {
    inferredTarget = firstLine.substring('Gesendet an '.length).trim();
   }
  }
  if (inferredTarget && inferredTarget !== selectedContact) {
   return null;
  }
 }
 const isOwn = parsed.from === currentUser;
 return renderMessageElement(parsed, decrypted, verifyInfo, isOwn);
}
function updateContactActivityFromMessages(blobs) {
 if (!currentUser) return;
 for (const line of blobs) {
  let parsed;
  try {
   parsed = JSON.parse(line);
  } catch (e) {
   continue;
  }
  if (!parsed || !parsed.from || !parsed.to || !parsed.ts) continue;
  const from = parsed.from;
  const to = parsed.to;
  const ts = Number(parsed.ts) || 0;
  if (!ts) continue;
  let contact = null;
  if (from === currentUser && to !== currentUser) {
   contact = to;
  } else if (to === currentUser && from !== currentUser) {
   contact = from;
  } else {
   continue;
  }
  const prev = contactLatestTs.get(contact) || 0;
  if (ts > prev) {
   contactLatestTs.set(contact, ts);
  }
  if (!knownContacts.has(contact) && !unknownContacts.has(contact)) {
   renderContact(contact, true);
  } else {
   updateContactUnreadState(contact);
  }
 }
}
async function loadMessagesAndDecrypt() {
 if (!currentUser) {
  document.getElementById('chat-messages').innerHTML = '';
  lastMessageBlobs = [];
  return;
 }
 const res = await api(API + 'messages');
 if (!res.ok) {
  return;
 }
 const messagesDiv = document.getElementById('chat-messages');
 const wasAtBottom = inboxIsAtBottom(messagesDiv);
 const newBlobs = Array.isArray(res.messages) ? res.messages : [];
 updateContactActivityFromMessages(newBlobs);
 if (!selectedContact) {
  lastMessageBlobs = newBlobs.slice();
  messagesDiv.innerHTML = '';
  return;
 }
 if (lastMessageBlobs.length === 0) {
  messagesDiv.innerHTML = '';
  let lastRendered = null;
  for (const line of newBlobs) {
   let parsed;
   try {
    parsed = JSON.parse(line);
   } catch (e) {
    continue;
   }
   if (!parsed || !parsed.ciphertext || !parsed.encKey) continue;
   const rendered = await tryRenderParsedMessage(parsed, currentUser, selectedContact);
   if (rendered) {
    messagesDiv.appendChild(rendered);
    lastRendered = rendered;
   }
  }
  lastMessageBlobs = newBlobs.slice();
  if (wasAtBottom && lastRendered) {
   setTimeout(() => {
    try { lastRendered.scrollIntoView({ behavior: 'auto', block: 'end' }); } catch (e) {}
   }, 10);
  }
 } else {
  let startIndex = 0;
  while (
   startIndex < lastMessageBlobs.length &&
   startIndex < newBlobs.length &&
   lastMessageBlobs[startIndex] === newBlobs[startIndex]
  ) {
   startIndex++;
  }
  if (startIndex < newBlobs.length) {
   let lastRendered = null;
   for (let i = startIndex; i < newBlobs.length; i++) {
    const line = newBlobs[i];
    let parsed;
    try {
     parsed = JSON.parse(line);
    } catch (e) {
     continue;
    }
    if (!parsed || !parsed.ciphertext || !parsed.encKey) continue;
    const rendered = await tryRenderParsedMessage(parsed, currentUser, selectedContact);
    if (rendered) {
     messagesDiv.appendChild(rendered);
     lastRendered = rendered;
    }
   }
   lastMessageBlobs = newBlobs.slice();
   if (wasAtBottom && lastRendered) {
    setTimeout(() => {
     try { lastRendered.scrollIntoView({ behavior: 'auto', block: 'end' }); } catch (e) {}
    }, 10);
   }
  }
 }
 const latestForCurrent = contactLatestTs.get(selectedContact) || 0;
 if (latestForCurrent) {
  contactLastSeen.set(selectedContact, latestForCurrent);
  updateContactUnreadState(selectedContact);
  await persistLastSeen(selectedContact);
 }
}
function startPollingMessages() {
 if (pollInterval) clearInterval(pollInterval);
 loadMessagesAndDecrypt().catch(() => {});
 pollInterval = setInterval(() => {
  loadMessagesAndDecrypt().catch(() => {});
 }, POLL_MS);
}
function stopPollingMessages() {
 if (pollInterval) {
  clearInterval(pollInterval);
  pollInterval = null;
 }
}
</script>
</body>
</html>
