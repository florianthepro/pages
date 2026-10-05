<?php declare(strict_types=1);
// Lieferadresse erstellen: reine Client-Seite. Aus den Formularfeldern wird ein
// Copy-&-Paste-Adressblock erzeugt und optional in die Zwischenablage kopiert.
// Kein Backend noetig - alles laeuft im Browser (Clipboard-API).

// Geteilte Variablen defensiv einlesen (Muster wie launcher-v2/index.php).
$lieferadresse_theme=(isset($lieferadresse_theme)&&in_array($lieferadresse_theme,['light','dark'],true))?$lieferadresse_theme:'auto';
$lieferadresse_title=(isset($lieferadresse_title)&&is_string($lieferadresse_title)&&$lieferadresse_title!=='')?(string)$lieferadresse_title:'Lieferadresse erstellen';
$lieferadresse_icon=(isset($lieferadresse_icon)&&is_string($lieferadresse_icon)&&$lieferadresse_icon!=='')?$lieferadresse_icon:'https://raw.githubusercontent.com/florianthepro/pages/main/content/media/lieferadresse/index.svg';

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

// data-theme nur bei fixem Hell/Dunkel setzen, sonst 'auto' (folgt System).
$dt=$lieferadresse_theme==='light'?' data-theme="light"':($lieferadresse_theme==='dark'?' data-theme="dark"':'');
?>
<!DOCTYPE html>
<html lang="de"<?= $dt ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($lieferadresse_title) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= h($lieferadresse_icon) ?>">
<style>
/* Original-Palette (hell) des Tools als Variablen; Dunkel-Variante folgt Theme/System. */
:root{
--bg:#fafafa;--fg:#222;--card:#fff;--muted:#666;
--card-border:#ddd;--field-bg:#fff;--field-border:#ccc;
--accent:#0066ff;--secondary:#666;--output-bg:#f7f7f7;--output-border:#ddd;
}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){
--bg:#161616;--fg:#e2e2e2;--card:#232323;--muted:#9a9a9a;
--card-border:#3a3a3a;--field-bg:#1b1b1b;--field-border:#444;
--accent:#0a84ff;--secondary:#555;--output-bg:#1b1b1b;--output-border:#444;
}}
:root[data-theme="dark"]{
--bg:#161616;--fg:#e2e2e2;--card:#232323;--muted:#9a9a9a;
--card-border:#3a3a3a;--field-bg:#1b1b1b;--field-border:#444;
--accent:#0a84ff;--secondary:#555;--output-bg:#1b1b1b;--output-border:#444;
}
body{font-family:system-ui,Arial,sans-serif;margin:20px;background:var(--bg);color:var(--fg)}
h1{font-size:1.25rem;margin-bottom:12px}
.grid{display:grid;gap:12px;grid-template-columns:1fr 1fr;align-items:start}
.full{grid-column:1/-1}
.card{background:var(--card);border:1px solid var(--card-border);border-radius:10px;padding:14px}
label{display:block;font-weight:600;margin-bottom:6px}
input,textarea,select{width:100%;padding:10px;border:1px solid var(--field-border);border-radius:8px;font-size:14px;background:var(--field-bg);color:inherit}
textarea{min-height:90px;resize:vertical}
.actions{display:flex;gap:8px;margin-top:10px}
button{padding:10px 14px;border:none;border-radius:8px;background:var(--accent);color:#fff;font-weight:600;cursor:pointer}
button.secondary{background:var(--secondary)}
.small{font-size:12px;color:var(--muted);margin-top:8px}
.output{white-space:pre-wrap;font-family:monospace;background:var(--output-bg);padding:12px;border-radius:8px;border:1px dashed var(--output-border)}
.row{display:flex;gap:8px}
.radio-inline{display:flex;gap:16px;align-items:center}
.radio-inline label{display:flex;align-items:center;gap:6px;font-weight:400;margin:0;cursor:pointer}
.radio-inline input[type="radio"]{margin:0;width:auto}
</style>
</head>
<body>
<h1><?= h($lieferadresse_title) ?></h1>
<form id="shipForm" class="grid" onsubmit="return false;">
<div class="card">
<div class="grid">
<div>
<label for="name">Name</label>
<input id="name" type="text" placeholder="Vorname Nachname" value="Vorname Nachname">
</div>
<div>
<label for="company">Firma</label>
<input id="company" type="text" placeholder="Firmenname / K&uuml;rzel" value="Firma GmbH">
</div>
<div>
<label for="phone">Telefonnummer</label>
<input id="phone" type="tel" placeholder="+49 89 000000" value="+49 89 000000">
</div>
<div>
<label for="ref">Sendungsreferenz (optional)</label>
<input id="ref" type="text" placeholder="z. B. Auftrag 12345">
</div>
<div class="full">
<label for="street">Stra&szlig;e / Hausnummer</label>
<input id="street" type="text" placeholder="Musterstra&szlig;e 1" value="Musterstra&szlig;e 1">
</div>
<div>
<label for="postal">Postleitzahl</label>
<input id="postal" type="text" placeholder="80331" value="80331">
</div>
<div>
<label for="city">Ort</label>
<input id="city" type="text" placeholder="M&uuml;nchen" value="M&uuml;nchen">
</div>
<div>
<label for="country">Land</label>
<select id="country">
<option value="DE" selected>DE</option><option value="AT">AT</option><option value="CH">CH</option><option value="NL">NL</option><option value="BE">BE</option><option value="GB">GB</option><option value="US">US</option><option value="OTHER">ANDERES (manuell)</option>
</select>
</div>
<div class="full">
<label for="addressIso">Adresse</label>
<input id="addressIso" type="text" placeholder="Stra&szlig;e Hausnummer; PLZ; Ort; L&auml;ndercode" value="">
<div class="small">Vorschlag: <code>Musterstra&szlig;e 1; 80331; M&uuml;nchen; DE</code></div>
</div>
<div class="full">
<label>Adressart</label>
<div class="row radio-inline">
<label>
<input type="radio" name="addrType" value="privat" checked> Privatadresse</label>
<label>
<input type="radio" name="addrType" value="firma"> Firmenadresse</label>
</div>
</div>
<div class="full">
<label for="note">Zusatz / Hinweise</label>
<textarea id="note" placeholder="Versandschein
R&uuml;cksendeschein
Versandschein + R&uuml;cksendeschein"></textarea>
</div>
</div>
</div>
<div class="card">
<label for="output">Copy &amp; Paste</label>
<div id="output" class="output" aria-live="polite"></div>
<div class="actions">
<button id="generateBtn" type="button">Generieren</button>
<button id="copyBtn" type="button" class="secondary">Kopieren</button>
</div>
<div id="status" class="small" aria-live="polite"></div>
</div>
</form>
<script>
// Original-Skript des Tools (unveraendert uebernommen) - rein clientseitig.
const els = {
  name: document.getElementById('name'),
  company: document.getElementById('company'),
  phone: document.getElementById('phone'),
  ref: document.getElementById('ref'),
  street: document.getElementById('street'),
  postal: document.getElementById('postal'),
  city: document.getElementById('city'),
  country: document.getElementById('country'),
  addressIso: document.getElementById('addressIso'),
  note: document.getElementById('note'),
  output: document.getElementById('output'),
  status: document.getElementById('status'),
  generateBtn: document.getElementById('generateBtn'),
  copyBtn: document.getElementById('copyBtn')
};
function safeTrim(v){ return (v||'').toString().trim(); }
function formatAddressLines(){
  const name = safeTrim(els.name.value);
  const company = safeTrim(els.company.value);
  const phone = safeTrim(els.phone.value);
  const street = safeTrim(els.street.value);
  const postal = safeTrim(els.postal.value);
  const city = safeTrim(els.city.value);
  let country = safeTrim(els.country.value);
  if(country === 'OTHER') {
    const iso = safeTrim(els.addressIso.value);
    const parts = iso.split(';').map(p=>p.trim()).filter(Boolean);
    country = parts.length ? parts[parts.length-1] : '';
  }
  const lines = [];
  if(name) lines.push(name);
  if(company) lines.push(company);
  if(street) lines.push(street);
  const cityLine = [postal, city].filter(Boolean).join(' ');
  if(cityLine) lines.push(cityLine);
  if(country) lines.push(country);
  if(phone) lines.push('Tel: ' + phone);
  return lines.join('\n');
}
function generateLabel(){
  const addrType = document.querySelector('input[name="addrType"]:checked').value;
  const iso = safeTrim(els.addressIso.value);
  const ref = safeTrim(els.ref.value);
  const note = safeTrim(els.note.value);
  const postalBlock = formatAddressLines();
  let out = '';
  if(ref) out += `Referenz: ${ref}\n`;
  out += postalBlock + '\n\n'; // <-- LEERZEILE wie gewuenscht
  out += (addrType === 'privat' ? 'Privatadresse' : 'Firmenadresse') + '\n';
  const company = safeTrim(els.company.value);
  const name = safeTrim(els.name.value);
  const phone = safeTrim(els.phone.value);
  out += `Firma: ${company || '-'} | Name: ${name || '-'} | Tel: ${phone || '-' }\n\n`;
  if(iso) out += `Adresse: ${iso}\n\n`;
  if(note) out += `Hinweis: ${note}\n`;
  els.output.textContent = out.trim();
  els.status.textContent = '';
}
function copyOutput(){
  const text = els.output.textContent;
  if(!text) { els.status.textContent = 'Keine Daten zum Kopieren.'; return; }
  if(navigator.clipboard && window.isSecureContext){
    navigator.clipboard.writeText(text).then(()=>{
      els.status.textContent = 'In Zwischenablage kopiert.';
    }).catch(()=>{
      els.status.textContent = 'Kopieren fehlgeschlagen.';
    });
  } else {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    try{ document.execCommand('copy'); els.status.textContent = 'In Zwischenablage kopiert.'; }catch{ els.status.textContent = 'Kopieren fehlgeschlagen.'; }
    document.body.removeChild(ta);
  }
}
['input','change'].forEach(evt=>{
  ['name','company','phone','street','postal','city','country','addressIso','note','ref'].forEach(id=>{
    document.getElementById(id).addEventListener(evt, generateLabel);
  });
  document.querySelectorAll('input[name="addrType"]').forEach(r=>r.addEventListener(evt, generateLabel));
});

els.generateBtn.addEventListener('click', generateLabel);
els.copyBtn.addEventListener('click', copyOutput);
generateLabel();
</script>
</body>
</html>
