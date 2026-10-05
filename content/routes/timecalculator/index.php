<?php declare(strict_types=1);
# Geteilte Variablen aus der Instanz defensiv einlesen (Muster aus launcher-v2).
$timecalculator_theme=(isset($timecalculator_theme)&&in_array($timecalculator_theme,['light','dark'],true))?$timecalculator_theme:'auto';
$timecalculator_title=(isset($timecalculator_title)&&is_string($timecalculator_title)&&$timecalculator_title!=='')?$timecalculator_title:'Zeitrechner';
$timecalculator_icon=(isset($timecalculator_icon)&&is_string($timecalculator_icon)&&$timecalculator_icon!=='')?$timecalculator_icon:'https://raw.githubusercontent.com/florianthepro/pages/main/content/media/timecalculator/index.svg';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
# Theme nur bei fixem light/dark als data-theme setzen, sonst auto (System folgt).
$dt=$timecalculator_theme==='light'?' data-theme="light"':($timecalculator_theme==='dark'?' data-theme="dark"':'');
# Rein clientseitiges Tool (reiner Zeit-Rechner) - keine Serverseite noetig.
?>
<!DOCTYPE html>
<html lang="de"<?= $dt ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($timecalculator_title) ?></title>
<link rel="icon" href="<?= h($timecalculator_icon) ?>">
<style>
a:hover{text-decoration:underline;}
.back{margin:10px 10px 20px 0;display:inline-block;}
:root{color-scheme:light dark;}
:root[data-theme="light"]{color-scheme:light;}
:root[data-theme="dark"]{color-scheme:dark;}
body{font-family:system-ui,sans-serif;margin:2rem;line-height:1.5;}
.card{max-width:520px;border:1px solid #ccc;border-radius:12px;padding:1rem 1.2rem;margin-bottom:1rem;}
.row{display:flex;gap:.8rem;align-items:center;flex-wrap:wrap;}
label{font-weight:600;}
input[type="number"]{width:120px;padding:.5rem;border-radius:8px;border:1px solid #bbb;}
button{padding:.5rem .9rem;border-radius:8px;border:1px solid #888;cursor:pointer;}
.result{margin-top:.6rem;font-weight:600;}
small.hint{color:#666;display:block;margin-top:.25rem;}
</style>
</head>
<body>
<h1><?= h($timecalculator_title) ?></h1>
<div class="card" id="minToHM">
<h2>Minuten &rarr; Stunden + Minuten</h2>
<div class="row">
<label for="minutes">Minuten:</label>
<input id="minutes" type="number" min="0" step="1" placeholder="z. B. 135">
<button id="convertToHM">Umrechnen</button>
<button id="clear1" type="button">Leeren</button>
</div>
<small class="hint">Ganzzahl-Minuten eingeben. Negativwerte werden als absolute Dauer behandelt.</small>
<div class="result" id="hmResult"></div>
</div>
<div class="card" id="hmToMin">
<h2>Stunden + Minuten &rarr; Minuten</h2>
<div class="row">
<label for="hours">Stunden:</label>
<input id="hours" type="number" step="1" placeholder="z. B. 2">
<label for="mins">Minuten:</label>
<input id="mins" type="number" step="1" placeholder="z. B. 15">
<button id="convertToMin">Umrechnen</button>
<button id="clear2" type="button">Leeren</button>
</div>
<small class="hint">Minuten k&ouml;nnen &gt;59 sein, werden automatisch auf Stunden umgelegt.</small>
<div class="result" id="minResult"></div>
</div>
<script>
const minutesInput=document.getElementById('minutes');
const hmResult=document.getElementById('hmResult');
document.getElementById('convertToHM').addEventListener('click',()=>{
let m=Number(minutesInput.value);
if(!Number.isFinite(m)){hmResult.textContent='Bitte gültige Minuten eingeben.';return;}
const sign=m<0?-1:1;
m=Math.abs(Math.round(m));
const h=Math.floor(m/60);
const mins=m%60;
const prefix=sign<0?'−':'';
hmResult.textContent=`${prefix}${h} Stunden ${mins} Minuten`;
});
document.getElementById('clear1').addEventListener('click',()=>{
minutesInput.value='';
hmResult.textContent='';
});
const hoursInput=document.getElementById('hours');
const minsInput=document.getElementById('mins');
const minResult=document.getElementById('minResult');
document.getElementById('convertToMin').addEventListener('click',()=>{
let h=Number(hoursInput.value);
let m=Number(minsInput.value);
if(!Number.isFinite(h)||!Number.isFinite(m)){
minResult.textContent='Bitte gültige Zahlen eingeben.';
return;
}
const totalMinutes=Math.round(h*60+m);
const sign=totalMinutes<0?-1:1;
let absMin=Math.abs(totalMinutes);
const normHours=Math.floor(absMin/60);
const normMins=absMin%60;
const displaySign=sign<0?'−':'';
minResult.textContent=`${displaySign}${absMin} Minuten (normiert: ${displaySign}${normHours}h ${normMins}m)`;
});
document.getElementById('clear2').addEventListener('click',()=>{
hoursInput.value='';
minsInput.value='';
minResult.textContent='';
});
</script>
</body>
</html>
