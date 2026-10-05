<?php declare(strict_types=1);
# Geteilte Variablen aus der Instanz defensiv einlesen (Muster aus launcher-v2).
$backgroundremover_theme=(isset($backgroundremover_theme)&&in_array($backgroundremover_theme,['light','dark'],true))?$backgroundremover_theme:'auto';
$backgroundremover_title=(isset($backgroundremover_title)&&is_string($backgroundremover_title)&&$backgroundremover_title!=='')?$backgroundremover_title:'Background Remover';
$backgroundremover_icon=(isset($backgroundremover_icon)&&is_string($backgroundremover_icon)&&$backgroundremover_icon!=='')?$backgroundremover_icon:'https://raw.githubusercontent.com/florianthepro/pages/main/content/media/background-remover/index.svg';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
# Theme nur bei fixem light/dark als data-theme setzen, sonst auto (System folgt).
$dt=$backgroundremover_theme==='light'?' data-theme="light"':($backgroundremover_theme==='dark'?' data-theme="dark"':'');
# Rein clientseitiges Tool: Chroma-Key im Canvas (Farbe picken, Toleranz,
# Kantenglaettung, PNG-Download). Alles laeuft im Browser - keine Serverseite noetig.
?>
<!DOCTYPE html>
<html lang="de"<?= $dt ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($backgroundremover_title) ?></title>
<link rel="icon" href="<?= h($backgroundremover_icon) ?>">
<style>
/* Original war dunkel (Slate); hier als Theme-Variablen, damit light/dark/auto
   der Instanz respektiert werden. Dunkel entspricht dem Original. */
:root{
color-scheme:light dark;
--bg:#f1f5f9;--panel:#ffffff;--panel-border:#cbd5e1;--text:#0f172a;
--dash:#94a3b8;--dash-active:#3b82f6;
--btn-bg:#e2e8f0;--btn-border:#94a3b8;--btn-text:#0f172a;
--box-border:#94a3b8;--check-a:#e2e8f0;--check-b:#cbd5e1;
}
:root[data-theme="light"]{color-scheme:light;}
@media (prefers-color-scheme:dark){
:root:not([data-theme="light"]){
color-scheme:dark;
--bg:#0f172a;--panel:#1e293b;--panel-border:#334155;--text:#e5e7eb;
--dash:#475569;--dash-active:#3b82f6;
--btn-bg:#334155;--btn-border:#64748b;--btn-text:#ffffff;
--box-border:#64748b;--check-a:#1e293b;--check-b:#0f172a;
}
}
:root[data-theme="dark"]{
color-scheme:dark;
--bg:#0f172a;--panel:#1e293b;--panel-border:#334155;--text:#e5e7eb;
--dash:#475569;--dash-active:#3b82f6;
--btn-bg:#334155;--btn-border:#64748b;--btn-text:#ffffff;
--box-border:#64748b;--check-a:#1e293b;--check-b:#0f172a;
}
body{margin:0;font-family:sans-serif;background:var(--bg);color:var(--text);display:flex;justify-content:center;padding:30px}
.app{width:100%;max-width:900px;background:var(--panel);padding:20px;border-radius:14px;border:1px solid var(--panel-border)}
h2{margin-top:0;font-size:20px;font-weight:600}
.upload-area{border:2px dashed var(--dash);border-radius:10px;padding:20px;text-align:center;cursor:pointer}
.upload-area.drag-over{border-color:var(--dash-active)}
input[type="file"]{display:none}
.controls{margin-top:15px;display:flex;gap:15px;align-items:center;flex-wrap:wrap}
.controls label{font-size:14px}
.controls .checkbox-label{display:inline-flex;align-items:center;gap:6px;font-size:14px}
.preview{margin-top:20px;display:grid;grid-template-columns:1fr 1fr;gap:20px}
.canvas-box{background:repeating-conic-gradient(var(--check-a) 0% 25%,var(--check-b) 0% 50%);background-size:16px 16px;border-radius:10px;padding:10px;text-align:center}
canvas{width:100%;height:auto;border-radius:6px}
#orig{cursor:crosshair}
button{padding:8px 14px;border-radius:6px;border:1px solid var(--btn-border);background:var(--btn-bg);color:var(--btn-text);cursor:pointer;font-size:14px}
button:disabled{opacity:.4;cursor:not-allowed}
.color-box{width:16px;height:16px;border-radius:4px;border:1px solid var(--box-border);margin-left:6px}
</style>
</head>
<body>
<div class="app">
<h2><?= h($backgroundremover_title) ?></h2>
<div class="upload-area" id="upload-area">
<strong>Klicke hier oder ziehe ein Bild hinein</strong>
<input id="file-input" type="file" accept="image/*">
</div>
<div class="controls">
<label>Toleranz:
<input type="range" id="tolerance" min="1" max="100" value="30">
</label>
<span id="tol-val">30</span>
<div class="color-box" id="color-box"></div>
<label class="checkbox-label">
<input type="checkbox" id="smooth" checked="">
Kantenglättung
</label>
<button id="apply-btn" disabled="">Entfernen</button>
<button id="save-btn" disabled="">Download</button>
</div>
<div class="preview">
<div class="canvas-box">
<small>Original (klick zum Farb-Pick)</small>
<canvas id="orig"></canvas>
</div>
<div class="canvas-box">
<small>Ergebnis</small>
<canvas id="out"></canvas>
</div>
</div>
</div>
<script>
(()=>{
const fi=document.getElementById("file-input");
const up=document.getElementById("upload-area");
const tol=document.getElementById("tolerance");
const tolVal=document.getElementById("tol-val");
const colorBox=document.getElementById("color-box");
const smoothChk=document.getElementById("smooth");
const applyBtn=document.getElementById("apply-btn");
const saveBtn=document.getElementById("save-btn");
const origC=document.getElementById("orig");
const outC=document.getElementById("out");
const octx=origC.getContext("2d");
const rctx=outC.getContext("2d");
let img=null;
let bgColor=null;
["dragenter","dragover"].forEach(ev=>up.addEventListener(ev,e=>{e.preventDefault();up.classList.add("drag-over")}));
["dragleave","drop"].forEach(ev=>up.addEventListener(ev,e=>{e.preventDefault();up.classList.remove("drag-over")}));
up.addEventListener("drop",e=>{fi.files=e.dataTransfer.files;handleFile(fi.files[0])});
up.addEventListener("click",()=>fi.click());
fi.addEventListener("change",()=>handleFile(fi.files[0]));
function handleFile(file){
if(!file)return;
img=new Image();
img.onload=()=>{
const maxW=900,maxH=600;
let w=img.naturalWidth,h=img.naturalHeight;
const r=Math.min(maxW/w,maxH/h,1);
w=Math.round(w*r);h=Math.round(h*r);
origC.width=outC.width=w;
origC.height=outC.height=h;
octx.clearRect(0,0,w,h);
rctx.clearRect(0,0,w,h);
octx.drawImage(img,0,0,w,h);
applyBtn.disabled=true;
saveBtn.disabled=true;
bgColor=null;
colorBox.style.background="transparent";
};
img.src=URL.createObjectURL(file);
}
origC.addEventListener("click",e=>{
if(!img)return;
const r=origC.getBoundingClientRect();
const x=(e.clientX-r.left)*(origC.width/r.width);
const y=(e.clientY-r.top)*(origC.height/r.height);
const px=octx.getImageData(x,y,1,1).data;
bgColor={r:px[0],g:px[1],b:px[2]};
colorBox.style.background=`rgb(${px[0]},${px[1]},${px[2]})`;
applyBtn.disabled=false;
});
tol.addEventListener("input",()=>{tolVal.textContent=tol.value});
function smoothAlpha(imgData,width,height,radius=1){
const src=imgData.data;
const len=width*height;
const alpha=new Uint8ClampedArray(len);
for(let i=0,j=0;i<src.length;i+=4,j++)alpha[j]=src[i+3];
const outAlpha=new Uint8ClampedArray(len);
const r=radius;
for(let y=0;y<height;y++){
for(let x=0;x<width;x++){
let sum=0,count=0;
for(let dy=-r;dy<=r;dy++){
const yy=y+dy;
if(yy<0||yy>=height)continue;
for(let dx=-r;dx<=r;dx++){
const xx=x+dx;
if(xx<0||xx>=width)continue;
const idx=yy*width+xx;
sum+=alpha[idx];
count++;
}}
const idx=y*width+x;
outAlpha[idx]=sum/count;
}}
for(let i=0,j=0;i<src.length;i+=4,j++)src[i+3]=outAlpha[j];
}
applyBtn.addEventListener("click",()=>{
if(!bgColor)return;
const w=origC.width,h=origC.height;
const imgData=octx.getImageData(0,0,w,h);
const d=imgData.data;
const T=tol.value*2.5;
for(let i=0;i<d.length;i+=4){
const dr=d[i]-bgColor.r;
const dg=d[i+1]-bgColor.g;
const db=d[i+2]-bgColor.b;
const dist=Math.sqrt(dr*dr+dg*dg+db*db);
if(dist<T)d[i+3]=0;
}
if(smoothChk.checked)smoothAlpha(imgData,w,h,1);
rctx.clearRect(0,0,w,h);
rctx.putImageData(imgData,0,0);
saveBtn.disabled=false;
});
saveBtn.addEventListener("click",()=>{
outC.toBlob(b=>{
if(!b)return;
const a=document.createElement("a");
a.href=URL.createObjectURL(b);
a.download="freisteller.png";
a.click();
});
});
})();
</script>
</body>
</html>
