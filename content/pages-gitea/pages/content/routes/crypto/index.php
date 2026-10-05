<?php
declare(strict_types=1);
$crypto_theme=(isset($crypto_theme)&&in_array($crypto_theme,['light','dark'],true))?$crypto_theme:'auto';
$crypto_title=(isset($crypto_title)&&$crypto_title!=='')?(string)$crypto_title:'Crypto Toolkit';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$dt=$crypto_theme==='light'?' data-theme="light"':($crypto_theme==='dark'?' data-theme="dark"':'');
?>
<!doctype html>
<html lang="de"<?= $dt ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($crypto_title) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64' fill='none' stroke='%230a63ff' stroke-width='4' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='14' y='28' width='36' height='24' rx='4'/%3E%3Cpath d='M22 28v-6a10 10 0 0 1 20 0v6'/%3E%3Ccircle cx='32' cy='40' r='3'/%3E%3C/svg%3E">
<style>
:root{--bg:#ffffff;--fg:#141414;--muted:#5a5f66;--border:#d5d7dd;--panel:#f5f6f8;--panel2:#e9ebef;--panelhead:#eef0f3;--accent:#0a63ff;--accent-fg:#ffffff;--link:#0a58ca;--danger:#b3261e;--danger-bg:#fde7e7;--danger-fg:#7f1512;--success:#1a7f37;--success-bg:#e6f4ea;--ctrl-bg:#141414;--ctrl-fg:#ffffff;--shadow:rgba(0,0,0,.14);}
@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){--bg:#0f1113;--fg:#e8e8ea;--muted:#a6adb6;--border:#2b3038;--panel:#17191d;--panel2:#20242a;--panelhead:#20242a;--accent:#5b9bff;--accent-fg:#0a0d12;--link:#7db0ff;--danger:#ff6b6b;--danger-bg:#3f1d1d;--danger-fg:#ffd9d9;--success:#5bd47e;--success-bg:#163a22;--ctrl-bg:#20242a;--ctrl-fg:#e8e8ea;--shadow:rgba(0,0,0,.6);}}
:root[data-theme="dark"]{--bg:#0f1113;--fg:#e8e8ea;--muted:#a6adb6;--border:#2b3038;--panel:#17191d;--panel2:#20242a;--panelhead:#20242a;--accent:#5b9bff;--accent-fg:#0a0d12;--link:#7db0ff;--danger:#ff6b6b;--danger-bg:#3f1d1d;--danger-fg:#ffd9d9;--success:#5bd47e;--success-bg:#163a22;--ctrl-bg:#20242a;--ctrl-fg:#e8e8ea;--shadow:rgba(0,0,0,.6);}
*{box-sizing:border-box}
html,body{margin:0}
body{background:var(--bg);color:var(--fg);font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
code,pre,.mono,textarea,input{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,"DejaVu Sans Mono",monospace}
header{display:flex;align-items:baseline;gap:12px;padding:16px 22px;border-bottom:1px solid var(--border)}
header .brand{font-weight:600;letter-spacing:.02em}
header .sub{color:var(--muted);font-size:12.5px}
.wrap{display:grid;grid-template-columns:290px 1fr;gap:0;min-height:calc(100vh - 58px)}
.side{border-right:1px solid var(--border);padding:14px;overflow:auto}
.tool{display:block;width:100%;text-align:left;background:transparent;border:1px solid transparent;border-radius:8px;padding:9px 11px;margin-bottom:4px;cursor:pointer;color:var(--fg)}
.tool:hover{background:var(--panel)}
.tool.on{background:var(--panel);border-color:var(--border)}
.tool .id{font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:12px}
.tool .alg{color:var(--accent);font-size:11.5px;margin-left:8px}
.tool .inf{color:var(--muted);font-size:12px;margin-top:2px}
.main{padding:22px;overflow:auto}
.opbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
.op{background:var(--panel);border:1px solid var(--border);border-radius:999px;padding:6px 15px;cursor:pointer;color:var(--fg);font-size:13px}
.op.on{background:var(--accent);border-color:var(--accent);color:var(--accent-fg)}
.card{max-width:820px;background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:20px}
.card h2{margin:0 0 4px;font-size:16px}
.card .desc{color:var(--muted);font-size:13px;margin-bottom:16px}
.field{margin-bottom:14px}
.field label{display:block;font-size:12px;color:var(--muted);margin-bottom:5px}
.field .row{display:flex;gap:8px;align-items:flex-start}
textarea,input[type=text]{width:100%;background:var(--bg);color:var(--fg);border:1px solid var(--border);border-radius:8px;padding:9px 11px;font-size:13px;resize:vertical}
textarea{min-height:84px;max-height:40vh;white-space:pre-wrap;word-break:break-word}
input[type=text]{height:38px}
button{font:inherit}
.btn{background:var(--ctrl-bg);color:var(--ctrl-fg);border:1px solid var(--ctrl-bg);border-radius:8px;padding:9px 18px;cursor:pointer;font-size:13px}
.btn.primary{background:var(--accent);border-color:var(--accent);color:var(--accent-fg);font-weight:600}
.btn.ghost{background:transparent;color:var(--fg);border-color:var(--border);padding:8px 12px}
.btn:active{transform:translateY(1px)}
.gen{white-space:nowrap}
.actions{display:flex;gap:10px;align-items:center;margin-top:6px}
.out{margin-top:18px;border-top:1px solid var(--border);padding-top:16px}
.out .o{margin-bottom:12px}
.out .ol{font-size:12px;color:var(--muted);margin-bottom:4px;display:flex;justify-content:space-between;align-items:center}
.out pre{margin:0;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:10px 12px;font-size:12.5px;white-space:pre-wrap;word-break:break-word;overflow-wrap:anywhere}
.verdict{display:inline-block;padding:4px 12px;border-radius:999px;font-weight:600;font-size:13px}
.verdict.ok{background:var(--success-bg);color:var(--success)}
.verdict.no{background:var(--danger-bg);color:var(--danger-fg)}
.err{background:var(--danger-bg);color:var(--danger-fg);border:1px solid var(--danger);border-radius:8px;padding:10px 12px;font-size:13px}
.copy{background:transparent;border:1px solid var(--border);border-radius:6px;color:var(--muted);font-size:11px;padding:2px 9px;cursor:pointer}
.copy:hover{color:var(--fg)}
@media (max-width:760px){.wrap{grid-template-columns:1fr}.side{border-right:0;border-bottom:1px solid var(--border)}}
</style>
</head>
<body>
<header>
  <span class="brand"><?= h($crypto_title) ?></span>
  <span class="sub">9 Verfahren &middot; Verschl&uuml;sselung, Signaturen, Hashing, Schl&uuml;ssel</span>
</header>
<div class="wrap">
  <nav class="side" id="side" aria-label="Verfahren"></nav>
  <main class="main" id="main"><div class="card"><div class="desc">Laden&hellip;</div></div></main>
</div>
<script>
"use strict";
var API=location.pathname+"?_page=api";
var OUTLBL={key:"Schlüssel (Base64)",out:"Ergebnis",pub:"Public Key",sec:"Secret Key",sig:"Signatur (Base64)",valid:"Ergebnis",shared:"Gemeinsames Geheimnis (Base64)",prk:"PRK (Base64)",okm:"OKM (Base64)",tag:"Tag (Hex)",digest:"Hash (Hex)",hash:"Hash"};
var CAT=[],curT=null,curO=null;
var side=document.getElementById("side"),main=document.getElementById("main");
function el(t,c,x){var e=document.createElement(t);if(c)e.className=c;if(x!=null)e.textContent=x;return e;}
function esc(s){return String(s==null?"":s);}

fetch(API).then(function(r){return r.json();}).then(function(d){
  CAT=Array.isArray(d)?d:[];
  buildSide();
  if(CAT.length)selectTool(CAT[0].php);
}).catch(function(e){main.innerHTML='<div class="card"><div class="err">Katalog konnte nicht geladen werden: '+esc(e)+'</div></div>';});

function buildSide(){
  side.innerHTML="";
  CAT.forEach(function(c){
    var b=el("button","tool");b.dataset.t=c.php;
    var top=el("div");top.appendChild(el("span","id",c.php));top.appendChild(el("span","alg",c.alg));
    b.appendChild(top);b.appendChild(el("div","inf",c.inf));
    b.addEventListener("click",function(){selectTool(c.php);});
    side.appendChild(b);
  });
}
function toolByName(n){for(var i=0;i<CAT.length;i++)if(CAT[i].php===n)return CAT[i];return null;}
function selectTool(n){
  curT=toolByName(n);if(!curT)return;
  var ops=Object.keys(curT.ops||{});curO=ops[0]||null;
  Array.prototype.forEach.call(side.children,function(b){b.classList.toggle("on",b.dataset.t===n);});
  renderMain();
}
function selectOp(o){curO=o;renderMain();}
function renderMain(){
  main.innerHTML="";
  if(!curT)return;
  var bar=el("div","opbar");
  Object.keys(curT.ops).forEach(function(o){
    var b=el("button","op"+(o===curO?" on":""),curT.ops[o].label||o);
    b.addEventListener("click",function(){selectOp(o);});
    bar.appendChild(b);
  });
  main.appendChild(bar);

  var op=curT.ops[curO];
  var card=el("div","card");
  card.appendChild(el("h2",null,curT.php.toUpperCase()+" · "+(op.label||curO)));
  card.appendChild(el("div","desc",curT.alg+" — "+curT.inf));

  var form=el("form");form.autocomplete="off";
  (op.in||[]).forEach(function(f){
    var wrap=el("div","field");
    var lab=el("label",null,f.l+(f.opt?" (optional)":""));lab.htmlFor="f_"+f.k;wrap.appendChild(lab);
    var row=el("div","row");
    var input;
    if(f.t==="area"){input=el("textarea");}else{input=el("input");input.type="text";}
    input.id="f_"+f.k;input.name=f.k;if(f.def)input.value=f.def;
    row.appendChild(input);
    if(f.gen==="k32"){
      var g=el("button","btn ghost gen","Erzeugen");g.type="button";
      g.addEventListener("click",function(){var a=new Uint8Array(32);crypto.getRandomValues(a);input.value=b64(a);});
      row.appendChild(g);
    }
    wrap.appendChild(row);form.appendChild(wrap);
  });

  var act=el("div","actions");
  var submit=el("button","btn primary",(op.in&&op.in.length)?(op.label||"Ausführen"):"Erzeugen");submit.type="submit";
  act.appendChild(submit);
  form.appendChild(act);

  var outBox=el("div","out");outBox.style.display="none";
  form.addEventListener("submit",function(ev){ev.preventDefault();run(op,form,outBox,submit);});
  card.appendChild(form);card.appendChild(outBox);
  main.appendChild(card);
}
function b64(a){var s="";for(var i=0;i<a.length;i++)s+=String.fromCharCode(a[i]);return btoa(s);}
function run(op,form,outBox,submit){
  var body=new URLSearchParams();body.set("t",curT.php);body.set("o",curO);
  (op.in||[]).forEach(function(f){body.set(f.k,form.elements[f.k].value);});
  submit.disabled=true;var old=submit.textContent;submit.textContent="…";
  fetch(API,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded;charset=UTF-8"},body:body.toString()})
    .then(function(r){return r.json();})
    .then(function(d){showOut(outBox,op,d);})
    .catch(function(e){showErr(outBox,String(e));})
    .then(function(){submit.disabled=false;submit.textContent=old;});
}
function showErr(box,msg){box.style.display="block";box.innerHTML="";var e=el("div","err","Fehler: "+esc(msg));box.appendChild(e);}
function showOut(box,op,d){
  box.style.display="block";box.innerHTML="";
  if(!d||d.ok!==true){showErr(box,(d&&d.error)?d.error:"unbekannt");return;}
  (op.out||[]).forEach(function(k){
    if(!(k in d))return;
    if(k==="valid"){
      var v=el("div","o");v.appendChild(el("div","ol","Ergebnis"));
      var badge=el("span","verdict "+(d.valid?"ok":"no"),d.valid?"✓ gültig":"✗ ungültig");
      v.appendChild(badge);box.appendChild(v);return;
    }
    var o=el("div","o");
    var ol=el("div","ol");ol.appendChild(el("span",null,OUTLBL[k]||k));
    var cp=el("button","copy","Kopieren");cp.type="button";
    var pre=el("pre",null,esc(d[k]));
    cp.addEventListener("click",function(){navigator.clipboard&&navigator.clipboard.writeText(String(d[k]));cp.textContent="Kopiert";setTimeout(function(){cp.textContent="Kopieren";},1200);});
    ol.appendChild(cp);o.appendChild(ol);o.appendChild(pre);box.appendChild(o);
  });
}
</script>
</body>
</html>
