<?php
header("Content-Type:text/html;charset=utf-8");
$path=__DIR__."/data.json";
$allow_private_fetch=false;
if(($_GET["action"]??"")==="fetchHtml"){
$url=trim($_GET["url"]??"");
if($url===""||!filter_var($url,FILTER_VALIDATE_URL)){http_response_code(400);exit;}
$u=parse_url($url);$scheme=strtolower($u["scheme"]??"");$host=$u["host"]??"";
if(($scheme!=="http"&&$scheme!=="https")||$host===""){http_response_code(400);exit;}
$checkHost=function($h)use($allow_private_fetch){
$ips=@gethostbynamel($h);
if(!$ips)return false;
if($allow_private_fetch)return true;
foreach($ips as $ip){
if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;
}
return true;
};
if(!$checkHost($host)){http_response_code(403);exit;}
$max=2*1024*1024;$buf="";
$ch=curl_init($url);
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>12,CURLOPT_USERAGENT=>"DashboardEditor/1.0",CURLOPT_HTTPHEADER=>["Accept: text/html,application/xhtml+xml"],CURLOPT_WRITEFUNCTION=>function($ch,$data)use(&$buf,$max){$buf.=$data;return strlen($buf)>$max?0:strlen($data);}]);
$ok=curl_exec($ch);
$eff=curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
curl_close($ch);
if(!$ok||$code>=400){http_response_code(502);echo"fetch failed";exit;}
if($eff){
$uu=parse_url($eff);$h=$uu["host"]??"";
if($h!==""&&!$checkHost($h)){http_response_code(403);exit;}
}
$path2=$u["path"]??"/";$port=isset($u["port"])?":".$u["port"]:"";
$base=$scheme."://".$host.$port.rtrim(str_replace("\\","/",dirname($path2)),"/")."/";
if(stripos($buf,"<base")===false){
$baseTag='<base href="'.htmlspecialchars($base,ENT_QUOTES|ENT_SUBSTITUTE,"UTF-8").'">';
if(preg_match('/<head[^>]*>/i',$buf,$m,PREG_OFFSET_CAPTURE)){
$pos=$m[0][1]+strlen($m[0][0]);
$buf=substr($buf,0,$pos).$baseTag.substr($buf,$pos);
}else{$buf='<!doctype html><html><head><meta charset="utf-8">'.$baseTag.'</head><body>'.$buf.'</body></html>';}
}
header("Content-Type:text/html; charset=utf-8");
echo $buf;
exit;
}
function deep_defaults($in,$def){if(!is_array($in))return $def;foreach($def as $k=>$v){if(!array_key_exists($k,$in)){$in[$k]=$v;continue;}if(is_array($v))$in[$k]=deep_defaults($in[$k],$v);}return $in;}
$default=["meta"=>["timezone"=>"Europe/Berlin","autosync_hours"=>6,"globalRotationSpeed"=>1],"pages"=>[["id"=>"p1","name"=>"Page 1","thumb"=>"","settings"=>["w"=>3840,"h"=>2160],"background"=>["fade"=>1,"items"=>[]],"widgets"=>[]]],"schedules"=>[]];
function sanitize($d,$default){
$d=deep_defaults($d,$default);
if(!is_array($d["pages"]))$d["pages"]=$default["pages"];
if(!is_array($d["schedules"]))$d["schedules"]=[];
if(!isset($d["meta"])||!is_array($d["meta"]))$d["meta"]=$default["meta"];
$d["pages"]=array_values(array_filter($d["pages"],fn($p)=>is_array($p)&&isset($p["id"])&&isset($p["name"])));
if(!count($d["pages"]))$d["pages"]=$default["pages"];
foreach($d["pages"] as &$p){
$p=deep_defaults($p,$default["pages"][0]);
if(!is_array($p["widgets"]))$p["widgets"]=[];
$p["widgets"]=array_values(array_filter($p["widgets"],fn($w)=>is_array($w)&&isset($w["id"])&&isset($w["type"])));
foreach($p["widgets"] as &$w){
$w=deep_defaults($w,["id"=>$w["id"]??"w".bin2hex(random_bytes(4)),"type"=>$w["type"]??"text","x"=>40,"y"=>40,"w"=>320,"h"=>180,"z"=>1,"locked"=>false,"hidden"=>false,"contentW"=>3840,"contentH"=>2160,"autoContent"=>false,"lockAspect"=>false,"style"=>["radius"=>0,"shadow"=>false,"bg"=>"","color"=>"","font"=>16],"src"=>"","mode"=>"iframe","text"=>"","format"=>"HH:mm:ss","playlist"=>[],"duration"=>5,"title"=>""]);
if(!isset($w["contentW"])||!is_numeric($w["contentW"]))$w["contentW"]=3840;
if(!isset($w["contentH"])||!is_numeric($w["contentH"]))$w["contentH"]=2160;
if(!is_array($w["style"]))$w["style"]=["radius"=>0,"shadow"=>false,"bg"=>"","color"=>"","font"=>16];
if(!is_array($w["playlist"]))$w["playlist"]=[];
}
}
foreach($d["schedules"] as &$r){
$r=deep_defaults($r,["id"=>$r["id"]??("r".bin2hex(random_bytes(4))),"name"=>$r["name"]??"Rule","enabled"=>$r["enabled"]??true,"from"=>$r["from"]??"08:00","to"=>$r["to"]??"18:00","weekdays"=>$r["weekdays"]??[1,2,3,4,5],"page"=>$r["page"]??"","override"=>$r["override"]??["rotationSpeed"=>null,"background"=>null,"widgets"=>[]]]);
if(!is_array($r["weekdays"]))$r["weekdays"]=[1,2,3,4,5];
if(!is_array($r["override"]))$r["override"]=["rotationSpeed"=>null,"background"=>null,"widgets"=>[]];
if(!is_array($r["override"]["widgets"]??null))$r["override"]["widgets"]=[];
}
return $d;
}
if($_SERVER["REQUEST_METHOD"]==="POST"){
$raw=file_get_contents("php://input");
$j=json_decode($raw,true);
if(!$j){http_response_code(400);echo"invalid json";exit;}
$j=sanitize($j,$default);
$tmp=$path.".tmp";
file_put_contents($tmp,json_encode($j,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
rename($tmp,$path);
echo"ok";
exit;
}
$data=$default;
if(file_exists($path)){
$j=json_decode(file_get_contents($path),true);
if($j)$data=sanitize($j,$default);
}
?>
<!doctype html>
<html>
<head>
<meta charset=utf-8>
<meta name=viewport content="width=device-width,initial-scale=1">
<title>Dashboard Editor</title>

<style>
html,body{margin:0;height:100%;font-family:system-ui,Segoe UI,Arial;background:#e9edf2}
#top{height:46px;background:#1f2328;color:#fff;display:flex;align-items:center;gap:8px;padding:0 10px}
#top .sp{flex:1}
#top button{background:#2d333b;border:1px solid #3a414a;color:#fff;border-radius:6px;padding:7px 10px;cursor:pointer}
#top button:hover{background:#39414c}
#main{display:grid;grid-template-columns:250px 1fr;height:calc(100% - 46px)}
#pages{grid-column:1;background:#111827;color:#e5e7eb;overflow:hidden;border-right:1px solid #0b1220;display:flex;flex-direction:column}
#center{grid-column:2;display:flex;flex-direction:column;min-width:0;background:#0b1220;min-height:0;overflow:hidden}
#pages .bar{display:flex;gap:6px;flex-wrap:wrap;padding:8px;border-bottom:1px solid #0b1220;position:sticky;top:0;background:#111827;z-index:2;flex:0 0 auto}
#pages .bar button{width:auto;flex:0 0 auto;background:#1f2937;border:1px solid #273446;color:#e5e7eb;border-radius:6px;padding:6px 8px;cursor:pointer}
#pageList{flex:1;overflow:auto}
#pages .list{padding:6px}
.pg{display:grid;grid-template-columns:44px 1fr;grid-template-rows:auto auto;gap:8px;align-items:start;background:#0f172a;border:1px solid #1f2a44;border-radius:8px;padding:8px;margin-bottom:8px;cursor:pointer}
.pg.active{outline:2px solid #60a5fa}
.pg .thumb{width:44px;height:44px;border-radius:6px;background:#0b1220 center/cover no-repeat;border:1px solid #1f2a44;grid-row:1 / span 2;grid-column:1}
.pg .meta{grid-row:1;grid-column:2;min-width:0}
.pg .meta .name{font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pg .meta .sub{font-size:12px;color:#9ca3af}
.pg .acts{grid-row:2;grid-column:2;display:flex;gap:4px;justify-self:end}
.pg .acts button{width:22px;height:22px;padding:0;border-radius:4px;background:#111827;border:1px solid #1f2a44;color:#cbd5e1;cursor:pointer;line-height:20px}
.pg .acts button:hover{background:#1f2937}
#pagesPanel{display:flex;flex-direction:column;min-height:0;flex:1}
#editorPanel{display:none;flex-direction:column;min-height:0;flex:1}
body.modePages #pagesPanel{display:flex}
body.modePages #editorPanel{display:none}
body.modeEditor #pagesPanel{display:none}
body.modeEditor #editorPanel{display:flex}
#centerTop{height:34px;background:#f8fafc;border-bottom:1px solid #d1d5db;display:flex;align-items:center;gap:8px;padding:0 10px;flex:0 0 auto}
#centerTop .pill{font-size:12px;color:#111827;background:#e5e7eb;border:1px solid #d1d5db;border-radius:999px;padding:5px 10px}
#canvasWrap{flex:1;min-height:0;min-width:0;position:relative;background:#000;overflow:hidden;display:flex;/*align-items:center;justify-content:center; - old*/align-items:flex-start;justify-content:flex-start;box-sizing:border-box;padding:0 10px 10px 0}
#canvasScale{position:relative}
#canvas{transform-origin:0 0;position:relative;margin:0;background:#111827;border:2px solid #334155;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.35)}
#bgPreview{position:absolute;inset:0;border-radius:10px;overflow:hidden;pointer-events:none;z-index:0}
.bgLayer{position:absolute;inset:0;background:#0b1220 center/cover no-repeat;opacity:0;transition:opacity 1s}
.bgLayer.on{opacity:.75}
.widget{position:absolute;box-sizing:border-box;border:1px solid rgba(96,165,250,.9);background:rgba(255,255,255,.84);color:#111827;overflow:hidden;z-index:1}
.widget.sel{outline:2px solid #ef4444}
.widget.locked{border-style:dashed;opacity:.85}
.handle{position:absolute;right:-10px;bottom:-10px;width:26px;height:26px;background:transparent;cursor:nwse-resize;touch-action:none}
.handle:after{content:"";position:absolute;right:8px;bottom:8px;width:10px;height:10px;background:#60a5fa;border-radius:2px;box-shadow:0 1px 2px rgba(0,0,0,.35)}
.widget.locked .handle{display:none}
.pv{position:absolute;inset:0;pointer-events:none}
.pv iframe,.pv img{width:100%;height:100%;border:0;display:block;pointer-events:none}
.pv img{object-fit:cover}
.pvCenter{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none}
.pvTag{position:absolute;left:4px;top:4px;font-size:11px;background:rgba(0,0,0,.55);color:#fff;padding:2px 6px;border-radius:2px;pointer-events:none}
.pvLayer{position:absolute;right:4px;top:4px;font-size:11px;background:rgba(0,0,0,.55);color:#fff;padding:2px 6px;border-radius:2px;pointer-events:none}
.pvCarousel{position:absolute;inset:0;background:#000}
.pvCarousel .slot{position:absolute;inset:0;opacity:0;pointer-events:none;transition:opacity .12s linear;background:#000}
.pvCarousel .slot.on{opacity:1}
.pvCarousel iframe,.pvCarousel img{width:100%;height:100%;border:0;display:block;background:#000}
#tabs{flex:0 0 auto;display:flex;gap:6px;padding:8px;border-bottom:1px solid #273446;background:#111827;position:static}
#tabs button{flex:1;background:#1f2937;border:1px solid #273446;border-radius:6px;padding:8px 6px;color:#e5e7eb;cursor:pointer;font-weight:600}
#tabs button.active{background:#0b1220;color:#fff;border-color:#0b1220}
#panel{flex:1;overflow:auto;padding:10px;background:#fff}
fieldset{border:1px solid #e5e7eb;border-radius:10px;padding:10px;margin:0 0 10px}
legend{padding:0 6px;color:#111827;font-weight:700}
label{display:block;font-size:12px;color:#374151;margin:8px 0 4px}
input,select,textarea{width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:8px;font:inherit}
textarea{min-height:70px;resize:vertical}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}
.btnRow{display:flex;gap:8px;flex-wrap:wrap}
.btnRow button{flex:1;min-width:88px;background:#111827;color:#fff;border:1px solid #111827;border-radius:8px;padding:8px 10px;cursor:pointer}
.btnRow button.alt{background:#fff;color:#111827;border-color:#d1d5db}
.btnRow button.danger{background:#ef4444;border-color:#ef4444}
.small{font-size:12px;color:#6b7280}
.badge{display:inline-block;font-size:11px;border:1px solid #d1d5db;border-radius:999px;padding:3px 8px;background:#f9fafb;color:#111827}
#pagesModeHint{display:none!important}
#editorPanel .bar{display:flex;gap:6px;flex-wrap:wrap;flex:0 0 auto;padding:8px;border-bottom:1px solid #0b1220;background:#111827}
#editorPanel .bar button{flex:1 1 90px;background:#1f2937;border:1px solid #273446;color:#e5e7eb;border-radius:6px;padding:6px 8px;cursor:pointer}
#editorPanel .bar button:hover{background:#2b3647}
#widgetListLeft{flex:0 0 220px;overflow:auto;padding:6px;border-top:1px solid #0b1220;background:#111827}
.wli{display:flex;align-items:center;gap:8px;background:#0f172a;border:1px solid #1f2a44;border-radius:6px;padding:6px;margin-bottom:6px;cursor:pointer}
.wli.sel{outline:2px solid #ef4444}
.wli .t{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#e5e7eb}
.wli .z{font-size:11px;color:#9ca3af}
.btnRow.tight button{min-width:72px;padding:7px 8px;font-size:12px}
#modalBg{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9998;display:none}
#modal{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:min(520px,92vw);max-height:min(75vh,700px);background:#fff;border:1px solid #d1d5db;border-radius:10px;box-shadow:0 14px 40px rgba(0,0,0,.45);z-index:9999;display:none;flex-direction:column}
#modalHead{padding:10px 12px;border-bottom:1px solid #e5e7eb;font-weight:700}
#modalBody{padding:10px 12px;overflow:auto}
#modalFoot{display:flex;gap:8px;padding:10px 12px;border-top:1px solid #e5e7eb}
#modalFoot button{flex:1;background:#111827;color:#fff;border:1px solid #111827;border-radius:8px;padding:8px 10px;cursor:pointer}
#modalCancel{background:#fff!important;color:#111827!important;border-color:#d1d5db!important}
body.modePages #canvas .widget{pointer-events:none}
body.modePages #canvas .handle{display:none}
body.modePages #canvas{cursor:default}
#top{position:relative}
#top .mid{position:absolute;left:50%;transform:translateX(-50%);display:flex;align-items:center;gap:8px}
#ctxMenu{position:fixed;z-index:10001;display:none;min-width:220px;background:#fff;border:1px solid #d1d5db;border-radius:10px;box-shadow:0 14px 40px rgba(0,0,0,.45);overflow:hidden}
#ctxMenu button{display:flex;width:100%;border:0;background:transparent;padding:10px 12px;text-align:left;cursor:pointer;font:inherit;color:#111827}
#ctxMenu button:hover{background:#f3f4f6}
#ctxMenu .sep{height:1px;background:#e5e7eb;margin:4px 0}
#pagesPanel .bar{flex-wrap:nowrap}
#pagesPanel .bar button{flex:1 1 auto;width:100%}
.pg{user-select:none;cursor:grab}
.pg.dragging{opacity:.55;cursor:grabbing}
.pg.drop{outline:2px dashed #f59e0b}
#editModalBg{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:10010;display:none}
#editModal{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:min(820px,96vw);max-height:min(80vh,860px);background:#fff;border:1px solid #d1d5db;border-radius:10px;box-shadow:0 14px 40px rgba(0,0,0,.45);z-index:10011;display:none;flex-direction:column}
#editModalHead{padding:10px 12px;border-bottom:1px solid #e5e7eb;font-weight:800}
#editModalBody{padding:10px 12px;overflow:auto}
#editModalFoot{display:flex;gap:8px;padding:10px 12px;border-top:1px solid #e5e7eb}
#editModalFoot button{flex:1;background:#111827;color:#fff;border:1px solid #111827;border-radius:8px;padding:8px 10px;cursor:pointer}
body.modeEditor #editorPanel .bar{display:none!important}
body.modeEditor #panel{display:none!important}
body.modeEditor #widgetListLeft{display:none!important}
#wctxMenu{position:fixed;z-index:10020;display:none;min-width:220px;background:#fff;border:1px solid #d1d5db;border-radius:10px;box-shadow:0 14px 40px rgba(0,0,0,.45);overflow:hidden}
#wctxMenu button{display:flex;width:100%;border:0;background:transparent;padding:10px 12px;text-align:left;cursor:pointer;font:inherit;color:#111827}
#wctxMenu button:hover{background:#f3f4f6}
#wctxMenu .sep{height:1px;background:#e5e7eb;margin:4px 0}
#wctxMenu button[disabled]{opacity:.45;cursor:default}
#colorPop{position:fixed;z-index:10021;display:none;background:#fff;border:1px solid #d1d5db;border-radius:10px;box-shadow:0 14px 40px rgba(0,0,0,.45);padding:10px;gap:10px;align-items:center}
#colorPop input[type="color"]{width:52px;height:42px;border:0;padding:0;background:transparent}
#colorPop .lbl{font-size:12px;color:#111827}
#wctxHiddenMenu{position:fixed;z-index:10022;display:none;min-width:260px;background:#fff;border:1px solid #d1d5e1;border-radius:10px;box-shadow:0 14px 40px rgba(0,0,0,.45);overflow:hidden}
#wctxHiddenMenu button{display:flex;width:100%;border:0;background:transparent;padding:10px 12px;text-align:left;cursor:pointer;font:inherit;color:#111827}
#wctxHiddenMenu button:hover{background:#f3f4f6}
#wctxHiddenMenu .empty{padding:10px 12px;color:#6b7280;font-size:12px}
#pagePop{position:fixed;z-index:10003;display:none;width:min(920px,96vw);max-height:min(82vh,900px);background:#fff;border:1px solid #d1d5db;border-radius:10px;box-shadow:0 14px 40px rgba(0,0,0,.45);overflow:hidden}
#pagePopHead{padding:10px 12px;border-bottom:1px solid #e5e7eb;font-weight:800;background:#f9fafb;color:#111827}
#pagePopBody{padding:10px 12px;overflow:auto;max-height:calc(82vh - 96px)}
#pagePopFoot{display:flex;gap:8px;padding:10px 12px;border-top:1px solid #e5e7eb}
#pagePopFoot button{flex:1;background:#111827;color:#fff;border:1px solid #111827;border-radius:8px;padding:8px 10px;cursor:pointer}
#renamePop{position:fixed;z-index:10004;display:none;background:#fff;border:1px solid #d1d5db;border-radius:10px;box-shadow:0 14px 40px rgba(0,0,0,.45);padding:10px;min-width:280px}
#renamePop input{width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:8px;font:inherit}
#renamePop .rpRow{display:flex;gap:8px;margin-top:8px}
#renamePop .rpRow button{flex:1;background:#111827;color:#fff;border:1px solid #111827;border-radius:8px;padding:8px 10px;cursor:pointer}
#wctxAddMenu{position:fixed;z-index:10023;display:none;min-width:220px;background:#fff;border:1px solid #d1d5db;border-radius:10px;box-shadow:0 14px 40px rgba(0,0,0,.45);overflow:hidden}
#wctxAddMenu button{display:flex;width:100%;border:0;background:transparent;padding:10px 12px;text-align:left;cursor:pointer;font:inherit;color:#111827}
#wctxAddMenu button:hover{background:#f3f4f6}
#modePages,#modeEditor{display:none!important}
#pagesPanel{display:flex!important}
#editorPanel{display:none!important}
#newPagePop{position:fixed;z-index:10005;display:none;background:#fff;border:1px solid #d1d5db;border-radius:10px;box-shadow:0 14px 40px rgba(0,0,0,.45);padding:10px;min-width:280px}
#newPagePop input{width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:8px;font:inherit}
#newPagePop .rpRow{display:flex;gap:8px;margin-top:8px}
#newPagePop .rpRow button{flex:1;background:#111827;color:#fff;border:1px solid #111827;border-radius:8px;padding:8px 10px;cursor:pointer}
#weditPop{position:fixed;z-index:10024;display:none;background:#fff;border:1px solid #d1d5db;border-radius:10px;box-shadow:0 14px 40px rgba(0,0,0,.45);padding:10px;min-width:320px;max-width:min(520px,92vw)}
#weditPop .lbl{font-size:12px;color:#111827;font-weight:800;margin-bottom:6px}
#weditPop input,#weditPop select,#weditPop textarea{width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:8px;font:inherit}
#weditPop .row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
#weditPop{max-height:min(80vh,860px);overflow:hidden}
#weditBody{max-height:calc(min(80vh,860px) - 54px);overflow:auto;padding-right:4px}
</style>

</head>
<body>
<div id=top>
<button id=modePages onclick=setMode("pages")>Pages</button>
<button id=modeEditor onclick=setMode("editor")>Editor</button>
<div class=mid>
<button onclick=doSave()>Save</button>
<button onclick=downloadJson()>Export</button>
<button onclick=importJsonPrompt()>Import</button>
</div>
<div class=sp></div>
<span id=status class=badge>Ready</span>
</div>
<div id=main>
<div id=pages>
<div id=pagesPanel>
<div class=bar>
<button onclick=addPage()>+Page</button>
</div>
<div class=list id=pageList></div>
</div>
<div id=editorPanel>
<div class=bar>
<button id=btnAddWidget>Add</button>
<button id=btnFront>Front</button>
<button id=btnBack>Back</button>
<button id=btnDup>Dup</button>
<button id=btnDel>Del</button>
</div>
<div id=tabs style="display:none"></div>
<div id=panel></div>
<div class=list id=widgetListLeft></div>
</div>
</div>
<div id=center>
<div id=pagesModeHint>Pages</div>
<div id=centerTop>
<span class=pill id=canvasInfo></span>
<span class=pill id=selInfo></span>
<div class=sp></div>
</div>
<div id=canvasWrap><div id=canvasScale><div id=canvas><div id=bgPreview><div class=bgLayer id=bgA></div><div class=bgLayer id=bgB></div></div></div></div></div>
</div>
</div>
<div id=modalBg></div><div id=modal><div id=modalHead>Add Widget</div><div id=modalBody></div><div id=modalFoot><button id=modalCancel type=button>Cancel</button><button id=modalOk type=button>OK</button></div></div>
<div id=editModalBg></div><div id=editModal><div id=editModalHead></div><div id=editModalBody></div><div id=editModalFoot><button id=editModalClose type=button>Close</button></div></div>
<div id=ctxMenu>
<button data-act="general">General…</button>
<button data-act="schedule">Schedule…</button>
<div class=sep></div>
<button data-act="rename">Rename…</button>
<button data-act="duplicate">Duplicate</button>
<button data-act="delete">Delete</button>
</div>
<div id=pagePop>
<div id=pagePopHead></div>
<div id=pagePopBody></div>
<div id=pagePopFoot><button id=pagePopClose type=button>Close</button></div>
</div>
<div id=renamePop>
<input id=renameInp type=text>
<div class=rpRow>
<button id=renameCancel type=button>Cancel</button>
<button id=renameOk type=button>OK</button>
</div>
</div>
<div id=newPagePop>
<input id=newPageInp type=text>
<div class=rpRow>
<button id=newPageCancel type=button>Cancel</button>
<button id=newPageOk type=button>OK</button>
</div>
</div>
<div id=wctxMenu>
<button data-act="add">Add Widget…</button>
<div class=sep data-needs="sel"></div>
<button data-act="edit" data-needs="sel">Edit…</button>
<div class=sep data-needs="sel"></div>
<button data-act="dup" data-needs="sel">Duplicate</button>
<button data-act="del" data-needs="sel">Delete</button>
<div class=sep data-needs="sel"></div>
<button data-act="up" data-needs="sel">Ebene höher</button>
<button data-act="down" data-needs="sel">Ebene tiefer</button>
<div class=sep data-needs="sel"></div>
<button data-act="lock" data-needs="sel">Lock</button>
<button data-act="hide" data-needs="sel">Hide</button>
<div class=sep data-needs="sel"></div>
<button data-act="bg" data-needs="sel">Background…</button>
<button data-act="fg" data-needs="sel">Text color…</button>
<div class=sep data-needs="hidden"></div>
<button data-act="hidden" data-needs="hidden">Hidden ▸</button>
</div>
<div id=weditPop>
<div class=lbl id=weditTitle>Widget</div>
<div id=weditBody></div>
<div class=rpRow>
<button id=weditCancel type=button>Close</button>
</div>
</div>
<div id=wctxHiddenMenu></div>
<div id=wctxAddMenu></div>
<div id=colorPop>
<div class=lbl id=colorPopLbl>Color</div>
<input id=colorInp type="color" value="#ffffff">
</div>
<script>
let data=<?php echo json_encode($data,JSON_UNESCAPED_UNICODE); ?>;
let tab="widget";
let pi=0;
let selectedIds=[];
let dragState=null;
let resizeState=null;
let lastMouse={x:0,y:0};
let carState={};
let bgState={sig:"",i:0,nextAt:0,onA:true,pageId:""};
let previewStarted=false;
const elPageList=document.getElementById("pageList");
if(elPageList)elPageList.addEventListener("scroll",()=>ctxHide(),{passive:true});
const elCanvas=document.getElementById("canvas");
const elBgPreview=document.getElementById("bgPreview");
const elBgA=document.getElementById("bgA");
const elBgB=document.getElementById("bgB");
const elCanvasInfo=document.getElementById("canvasInfo");
const elSelInfo=document.getElementById("selInfo");
const elPanel=document.getElementById("panel");
const elStatus=document.getElementById("status");
const uid=()=>Math.random().toString(36).slice(2);
const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
const elModalBg=document.getElementById("modalBg");
const elModal=document.getElementById("modal");
const elModalBody=document.getElementById("modalBody");
const elModalCancel=document.getElementById("modalCancel");
const elModalOk=document.getElementById("modalOk");
const elCtxMenu=document.getElementById("ctxMenu");
const elPagePop=document.getElementById("pagePop");
const elPagePopHead=document.getElementById("pagePopHead");
const elPagePopBody=document.getElementById("pagePopBody");
const elPagePopClose=document.getElementById("pagePopClose");
const elRenamePop=document.getElementById("renamePop");
const elNewPagePop=document.getElementById("newPagePop");
const elNewPageInp=document.getElementById("newPageInp");
const elNewPageOk=document.getElementById("newPageOk");
const elNewPageCancel=document.getElementById("newPageCancel");
const elRenameInp=document.getElementById("renameInp");
const elRenameOk=document.getElementById("renameOk");
const elRenameCancel=document.getElementById("renameCancel");
const elEditModalBg=document.getElementById("editModalBg");
const elEditModal=document.getElementById("editModal");
const elEditModalHead=document.getElementById("editModalHead");
const elEditModalBody=document.getElementById("editModalBody");
const elEditModalClose=document.getElementById("editModalClose");
const elWCtxMenu=document.getElementById("wctxMenu");
const elColorPop=document.getElementById("colorPop");
const elColorInp=document.getElementById("colorInp");
const elColorPopLbl=document.getElementById("colorPopLbl");
const elWctxHiddenMenu=document.getElementById("wctxHiddenMenu");
const elWctxAddMenu=document.getElementById("wctxAddMenu");
const elWeditPop=document.getElementById("weditPop");
const elWeditTitle=document.getElementById("weditTitle");
const elWeditBody=document.getElementById("weditBody");
const elWeditCancel=document.getElementById("weditCancel");
let hiddenHoverTimer=null;
let wctxHasSel=false;
let colorMode="bg";
function editModalHide(){if(!elEditModal)return;elEditModalBg.style.display="none";elEditModal.style.display="none";elEditModalHead.textContent="";elEditModalBody.innerHTML="";}
function editModalShow(title,node){
elEditModalHead.textContent=title||"Edit";
elEditModalBody.innerHTML="";
if(node)elEditModalBody.appendChild(node);
elEditModalBg.style.display="block";
elEditModal.style.display="flex";
}
if(elEditModalBg)elEditModalBg.onclick=()=>editModalHide();
if(elEditModalClose)elEditModalClose.onclick=()=>editModalHide();
function wctxHide(){
if(elWCtxMenu)elWCtxMenu.style.display="none";
if(elColorPop)elColorPop.style.display="none";
if(elWctxHiddenMenu)elWctxHiddenMenu.style.display="none";
if(elWctxAddMenu)elWctxAddMenu.style.display="none";
if(elWeditPop)elWeditPop.style.display="none";
if(hiddenHoverTimer){clearTimeout(hiddenHoverTimer);hiddenHoverTimer=null;}
}
function wctxMenuOnlyHide(){
if(elWCtxMenu)elWCtxMenu.style.display="none";
if(elWctxHiddenMenu)elWctxHiddenMenu.style.display="none";
}
function wctxUpdate(){
if(!elWCtxMenu)return;
let p=getPage();
let hasSel=!!(selectedIds&&selectedIds.length);
wctxHasSel=hasSel;
let hiddenCount=(p.widgets||[]).filter(w=>!!w.hidden).length;
Array.from(elWCtxMenu.querySelectorAll('[data-needs="sel"]')).forEach(n=>n.style.display=hasSel?"block":"none");
Array.from(elWCtxMenu.querySelectorAll('[data-needs="hidden"]')).forEach(n=>n.style.display=hiddenCount?"block":"none");
let bLock=elWCtxMenu.querySelector('button[data-act="lock"]');
if(bLock&&hasSel){
let allLocked=true;
selectedIds.forEach(id=>{let w=p.widgets.find(x=>x.id===id);if(w&&!w.locked)allLocked=false;});
bLock.textContent=allLocked?"Unlock":"Lock";
}
let bHide=elWCtxMenu.querySelector('button[data-act="hide"]');
if(bHide&&hasSel)bHide.textContent="Hide";
let bHidden=elWCtxMenu.querySelector('button[data-act="hidden"]');
if(bHidden&&hiddenCount)bHidden.textContent="Hidden ("+hiddenCount+") ▸";
}
function wctxShowAt(x,y){
wctxUpdate();
elWCtxMenu.style.display="block";
let r=elWCtxMenu.getBoundingClientRect();
let ww=window.innerWidth,hh=window.innerHeight;
x=Math.max(8,Math.min(x,ww-r.width-8));
y=Math.max(8,Math.min(y,hh-r.height-8));
elWCtxMenu.style.left=x+"px";
elWCtxMenu.style.top=y+"px";
}
function wctxShow(e){
e.preventDefault();e.stopPropagation();
ctxHide();
wctxHide();
wctxShowAt(e.clientX,e.clientY);
}
function firstSelWidget(){
let p=getPage();
for(let id of selectedIds){let w=p.widgets.find(x=>x.id===id);if(w)return w;}
return null;
}
function weditShow(anchorBtn){
let w=firstSelWidget();
if(!w||!elWeditPop||!elWeditBody||!elWeditTitle)return;
elWeditTitle.textContent=(w.type||"widget").toUpperCase();
elWeditBody.innerHTML="";
let p=getPage();
function mkLabel(t){let l=document.createElement("label");l.textContent=t;return l;}
function mkInput(val,on){let i=document.createElement("input");i.type="text";i.value=val||"";i.oninput=()=>on(i.value);return i;}
function mkNum(val,on){let i=document.createElement("input");i.type="number";i.value=(val==null?"":val);i.oninput=()=>on(+i.value);return i;}
function mkSel(val,opts,on){let s=document.createElement("select");opts.forEach(o=>{let op=document.createElement("option");op.value=o.v;op.textContent=o.t;s.appendChild(op);});s.value=val||opts[0].v;s.oninput=()=>on(s.value);return s;}
function row2(a,b){let d=document.createElement("div");d.className="row";d.appendChild(a);d.appendChild(b);return d;}

if(w.type==="text"){
elWeditBody.appendChild(mkLabel("Text"));
let ta=document.createElement("textarea");ta.value=w.text||"";ta.oninput=()=>{w.text=ta.value;renderCanvas();};elWeditBody.appendChild(ta);
}
if(w.type==="image"){
elWeditBody.appendChild(mkLabel("Image URL"));
elWeditBody.appendChild(mkInput(w.src||"",v=>{w.src=v;renderCanvas();}));
}
if(w.type==="url"){
elWeditBody.appendChild(mkLabel("URL"));
elWeditBody.appendChild(mkInput(w.src||"",v=>{w.src=v;renderCanvas();}));
elWeditBody.appendChild(mkLabel("Mode"));
elWeditBody.appendChild(mkSel(w.mode||"iframe",[{v:"iframe",t:"iframe"},{v:"fetch",t:"fetch (curl)"}],v=>{w.mode=v;renderCanvas();}));
let a=document.createElement("div");a.appendChild(mkLabel("Content W"));a.appendChild(mkNum(w.contentW||(+p.settings.w||3840),v=>{w.contentW=clamp(+v||3840,320,20000);renderCanvas();}));
let b=document.createElement("div");b.appendChild(mkLabel("Content H"));b.appendChild(mkNum(w.contentH||(+p.settings.h||2160),v=>{w.contentH=clamp(+v||2160,240,20000);renderCanvas();}));
elWeditBody.appendChild(row2(a,b));
}
if(w.type==="clock"){
elWeditBody.appendChild(mkLabel("Format (stored)"));
elWeditBody.appendChild(mkInput(w.format||"HH:mm:ss",v=>{w.format=v;renderCanvas();}));
}
if(w.type==="carousel"){
if(!Array.isArray(w.playlist))w.playlist=[];
let build=()=>{
elWeditBody.innerHTML="";
elWeditBody.appendChild(mkLabel("Items"));
let top=document.createElement("div");top.className="rpRow";
let bAdd=document.createElement("button");bAdd.type="button";bAdd.textContent="+ Item";
let bClr=document.createElement("button");bClr.type="button";bClr.textContent="Clear";
bAdd.onclick=()=>{w.playlist.push({type:"image",src:"",duration:5,title:""});build();renderCanvas();};
bClr.onclick=()=>{w.playlist=[];build();renderCanvas();};
top.appendChild(bAdd);top.appendChild(bClr);
elWeditBody.appendChild(top);
if(!w.playlist.length){let e=document.createElement("div");e.className="small";e.textContent="No items";elWeditBody.appendChild(e);return;}
w.playlist.forEach((it,i)=>{
if(!it||typeof it!=="object")it=w.playlist[i]={type:"image",src:"",duration:5,title:""};
let box=document.createElement("div");box.style.border="1px solid #e5e7eb";box.style.borderRadius="10px";box.style.padding="8px";box.style.marginTop="8px";
let t1=document.createElement("div");t1.appendChild(mkLabel("Type"));t1.appendChild(mkSel(it.type||"image",[{v:"image",t:"image"},{v:"url",t:"url"}],v=>{it.type=v;renderCanvas();}));
let t2=document.createElement("div");t2.appendChild(mkLabel("Duration s"));t2.appendChild(mkNum(it.duration==null?5:it.duration,v=>{it.duration=clamp(+v||5,1,3600);}));
box.appendChild(row2(t1,t2));
box.appendChild(mkLabel("Src"));
box.appendChild(mkInput(it.src||"",v=>{it.src=v;renderCanvas();}));
box.appendChild(mkLabel("Title"));
box.appendChild(mkInput(it.title||"",v=>{it.title=v;}));
let ar=document.createElement("div");ar.className="rpRow";
let bUp=document.createElement("button");bUp.type="button";bUp.textContent="Up";
let bDn=document.createElement("button");bDn.type="button";bDn.textContent="Down";
let bDel=document.createElement("button");bDel.type="button";bDel.textContent="Del";
bUp.onclick=()=>{if(i<=0)return;let a=w.playlist[i-1];w.playlist[i-1]=w.playlist[i];w.playlist[i]=a;build();renderCanvas();};
bDn.onclick=()=>{if(i>=w.playlist.length-1)return;let a=w.playlist[i+1];w.playlist[i+1]=w.playlist[i];w.playlist[i]=a;build();renderCanvas();};
bDel.onclick=()=>{w.playlist.splice(i,1);build();renderCanvas();};
ar.appendChild(bUp);ar.appendChild(bDn);ar.appendChild(bDel);
box.appendChild(ar);
elWeditBody.appendChild(box);
});
};
build();
}

elWeditPop.style.display="block";
let x=120,y=70;
if(anchorBtn){let r=anchorBtn.getBoundingClientRect();x=r.right+8;y=r.top;}
let rr=elWeditPop.getBoundingClientRect();
let ww=window.innerWidth,hh=window.innerHeight;
x=Math.max(8,Math.min(x,ww-rr.width-8));
y=Math.max(8,Math.min(y,hh-rr.height-8));
elWeditPop.style.left=x+"px";
elWeditPop.style.top=y+"px";
}
if(elWeditCancel)elWeditCancel.onclick=()=>{if(elWeditPop)elWeditPop.style.display="none";};
if(elWeditPop)elWeditPop.addEventListener("mousedown",e=>e.stopPropagation(),true);
if(elWeditPop)elWeditPop.addEventListener("contextmenu",e=>e.preventDefault(),true);
function applyToSelected(fn){
let p=getPage();
selectedIds.forEach(id=>{let w=p.widgets.find(x=>x.id===id);if(w)fn(w);});
}
function toggleProp(prop){
let p=getPage();
let any=false;
selectedIds.forEach(id=>{let w=p.widgets.find(x=>x.id===id);if(w&&!w[prop])any=true;});
applyToSelected(w=>{w[prop]=!!any;});
renderCanvas();
wctxUpdate();
renderLeftWidgets();
}
function layerStep(dir){
applyToSelected(w=>{w.z=clamp((w.z||1)+dir,1,99999);});
renderCanvas();
}
function openColorPicker(mode,anchorEl){
colorMode=mode;
if(!elColorPop||!elColorInp)return;
let w=firstSelWidget();
let v="#ffffff";
if(w&&w.style){
let s=mode==="bg"?(w.style.bg||""):(w.style.color||"");
s=String(s||"").trim();
if(/^#([0-9a-f]{6})$/i.test(s))v=s.toUpperCase();
}
elColorInp.value=v;
elColorPopLbl.textContent=mode==="bg"?"Background":"Text color";
elColorPop.style.display="flex";
let mx=0,my=0;
if(anchorEl){
let r=anchorEl.getBoundingClientRect();
mx=r.right+8;my=r.top;
}else{
let mr=elWCtxMenu.getBoundingClientRect();
mx=mr.right+8;my=mr.top;
}
let pr=elColorPop.getBoundingClientRect();
let ww=window.innerWidth,hh=window.innerHeight;
mx=Math.max(8,Math.min(mx,ww-pr.width-8));
my=Math.max(8,Math.min(my,hh-pr.height-8));
elColorPop.style.left=mx+"px";
elColorPop.style.top=my+"px";
}
function hiddenLabel(w){
let s="";
if(w.type==="text")s=(w.text||"").toString().trim();
else if(w.type==="image"||w.type==="url")s=(w.src||"").toString().trim();
else if(w.type==="carousel")s="items:"+((w.playlist||[]).length);
else if(w.type==="clock")s="clock";
s=s.replace(/\s+/g," ").slice(0,48);
return (w.type||"widget")+(s?(" · "+s):"");
}
function getHiddenWidgets(){
let p=getPage();
return (p.widgets||[]).filter(w=>!!w.hidden).map(w=>({id:w.id,label:hiddenLabel(w)}));
}
function buildHiddenMenu(){
if(!elWctxHiddenMenu)return;
elWctxHiddenMenu.innerHTML="";
let list=getHiddenWidgets();
if(!list.length){let d=document.createElement("div");d.className="empty";d.textContent="No hidden widgets";elWctxHiddenMenu.appendChild(d);return;}
list.forEach(it=>{
let b=document.createElement("button");
b.textContent=it.label;
b.onclick=e=>{
e.preventDefault();e.stopPropagation();
let p=getPage();
let w=p.widgets.find(x=>x.id===it.id);
if(w){w.hidden=false;selectedIds=[w.id];}
renderCanvas();
wctxHide();
};
elWctxHiddenMenu.appendChild(b);
});
}
function showHiddenMenu(anchorBtn){
if(!elWCtxMenu||!elWctxHiddenMenu)return;
buildHiddenMenu();
elWctxHiddenMenu.style.display="block";
let r=anchorBtn.getBoundingClientRect();
let x=r.right+8,y=r.top;
let mr=elWctxHiddenMenu.getBoundingClientRect();
let ww=window.innerWidth,hh=window.innerHeight;
x=Math.max(8,Math.min(x,ww-mr.width-8));
y=Math.max(8,Math.min(y,hh-mr.height-8));
elWctxHiddenMenu.style.left=x+"px";
elWctxHiddenMenu.style.top=y+"px";
}
function hideHiddenMenuSoon(){
if(hiddenHoverTimer)clearTimeout(hiddenHoverTimer);
hiddenHoverTimer=setTimeout(()=>{if(elWctxHiddenMenu)elWctxHiddenMenu.style.display="none";},220);
}
function buildAddMenu(){
if(!elWctxAddMenu)return;
elWctxAddMenu.innerHTML="";
[["text","Text"],["image","Image"],["url","URL"],["clock","Clock"],["carousel","Carousel"]].forEach(it=>{
let b=document.createElement("button");
b.textContent=it[1];
b.onclick=e=>{
e.preventDefault();e.stopPropagation();
wctxHide();
addWidget(it[0]);
};
elWctxAddMenu.appendChild(b);
});
}
function showAddMenu(anchorBtn){
if(!elWctxAddMenu)return;
buildAddMenu();
elWctxAddMenu.style.display="block";
let r=anchorBtn.getBoundingClientRect();
let x=r.right+8,y=r.top;
let mr=elWctxAddMenu.getBoundingClientRect();
let ww=window.innerWidth,hh=window.innerHeight;
x=Math.max(8,Math.min(x,ww-mr.width-8));
y=Math.max(8,Math.min(y,hh-mr.height-8));
elWctxAddMenu.style.left=x+"px";
elWctxAddMenu.style.top=y+"px";
}
if(elColorInp)elColorInp.oninput=()=>{
let v=elColorInp.value||"#ffffff";
if(colorMode==="bg")applyToSelected(w=>{w.style=w.style||{};w.style.bg=v;});
else applyToSelected(w=>{w.style=w.style||{};w.style.color=v;});
renderCanvas();
};
document.addEventListener("click",e=>{
if(elWCtxMenu&&elWCtxMenu.style.display==="block"&&elWCtxMenu.contains(e.target))return;
if(elWctxHiddenMenu&&elWctxHiddenMenu.style.display==="block"&&elWctxHiddenMenu.contains(e.target))return;
if(elWctxAddMenu&&elWctxAddMenu.style.display==="block"&&elWctxAddMenu.contains(e.target))return;
if(elWeditPop&&elWeditPop.style.display==="block"&&elWeditPop.contains(e.target))return;
if(elColorPop&&elColorPop.style.display==="flex"&&elColorPop.contains(e.target))return;
wctxHide();
},true);
let wctxMoveT=null;
document.addEventListener("mousemove",e=>{
if(!elWCtxMenu||elWCtxMenu.style.display!=="block")return;
let inside=false;
if(elWCtxMenu.contains(e.target))inside=true;
if(elWctxHiddenMenu&&elWctxHiddenMenu.style.display==="block"&&elWctxHiddenMenu.contains(e.target))inside=true;
if(elWctxAddMenu&&elWctxAddMenu.style.display==="block"&&elWctxAddMenu.contains(e.target))inside=true;
if(elWeditPop&&elWeditPop.style.display==="block"&&elWeditPop.contains(e.target))inside=true;
if(elColorPop&&elColorPop.style.display==="flex"&&elColorPop.contains(e.target))inside=true;
if(inside){if(wctxMoveT){clearTimeout(wctxMoveT);wctxMoveT=null;}return;}
if(wctxMoveT)clearTimeout(wctxMoveT);
wctxMoveT=setTimeout(()=>{wctxHide();},120);
},true);
document.addEventListener("keydown",e=>{if(e.key==="Escape")wctxHide();},true);
window.addEventListener("resize",()=>wctxHide(),true);
function openPageGeneral(i){
pi=i;
let p=data.pages[i];
sanitizeClient();
editModalShow("General · "+(p.name||"Page"),buildGeneralEditor(p));
renderPages();renderCanvas();
}
function openPageSchedule(i){
pi=i;
let p=data.pages[i];
sanitizeClient();
editModalShow("Schedule · "+(p.name||"Page"),buildScheduleEditor(p.id));
}
let ctxPageIndex=null;
let dragPageFrom=null;
function clearPageDragUi(){
document.querySelectorAll(".pg.drop").forEach(n=>n.classList.remove("drop"));
document.querySelectorAll(".pg.dragging").forEach(n=>n.classList.remove("dragging"));
}
function reorderPages(from,to){
if(from==null||to==null)return;
from=+from;to=+to;
if(!Number.isFinite(from)||!Number.isFinite(to))return;
if(from===to)return;
from=Math.max(0,Math.min(from,data.pages.length-1));
to=Math.max(0,Math.min(to,data.pages.length-1));
let moved=data.pages.splice(from,1)[0];
data.pages.splice(to,0,moved);
if(pi===from)pi=to;
else if(from<pi&&pi<=to)pi--;
else if(to<=pi&&pi<from)pi++;
selectedIds=[];
renderAll();
}
function pagePopHide(){if(elPagePop)elPagePop.style.display="none";if(elPagePopHead)elPagePopHead.textContent="";if(elPagePopBody)elPagePopBody.innerHTML="";}
function renamePopHide(){if(elRenamePop)elRenamePop.style.display="none";}
function ctxHide(){if(!elCtxMenu)return;elCtxMenu.style.display="none";ctxPageIndex=null;pagePopHide();renamePopHide();}
function ctxShow(e,i){
e.preventDefault();e.stopPropagation();
ctxPageIndex=i;
pi=i;
selectedIds=[];
renderAll();
elCtxMenu.style.display="block";
let x=e.clientX,y=e.clientY;
let r=elCtxMenu.getBoundingClientRect();
let ww=window.innerWidth,hh=window.innerHeight;
x=Math.max(8,Math.min(x,ww-r.width-8));
y=Math.max(8,Math.min(y,hh-r.height-8));
elCtxMenu.style.left=x+"px";
elCtxMenu.style.top=y+"px";
}
function pagePopShow(title,node){
if(!elPagePop||!elPagePopBody||!elPagePopHead)return;
elPagePopHead.textContent=title||"";
elPagePopBody.innerHTML="";
if(node)elPagePopBody.appendChild(node);
elPagePop.style.display="block";
let mr=elCtxMenu.getBoundingClientRect();
let x=mr.right+8,y=mr.top;
let pr=elPagePop.getBoundingClientRect();
let ww=window.innerWidth,hh=window.innerHeight;
x=Math.max(8,Math.min(x,ww-pr.width-8));
y=Math.max(8,Math.min(y,hh-pr.height-8));
elPagePop.style.left=x+"px";
elPagePop.style.top=y+"px";
}
function renamePopShow(){
if(!elRenamePop||!elRenameInp)return;
let p=data.pages[ctxPageIndex];
elRenameInp.value=(p&&p.name)?String(p.name):"";
elRenamePop.style.display="block";
let mr=elCtxMenu.getBoundingClientRect();
let x=mr.right+8,y=mr.top;
let rr=elRenamePop.getBoundingClientRect();
let ww=window.innerWidth,hh=window.innerHeight;
x=Math.max(8,Math.min(x,ww-rr.width-8));
y=Math.max(8,Math.min(y,hh-rr.height-8));
elRenamePop.style.left=x+"px";
elRenamePop.style.top=y+"px";
elRenameInp.focus();
elRenameInp.select();
}
if(elPagePopClose)elPagePopClose.onclick=()=>pagePopHide();
if(elRenameCancel)elRenameCancel.onclick=()=>renamePopHide();
if(elRenameOk)elRenameOk.onclick=()=>{
let p=data.pages[ctxPageIndex];
if(!p)return;
let v=String(elRenameInp.value||"").trim();
if(v)p.name=v;
renamePopHide();
renderAll();
};
if(elRenameInp)elRenameInp.onkeydown=e=>{
if(e.key==="Enter"){e.preventDefault();if(elRenameOk)elRenameOk.click();}
if(e.key==="Escape"){e.preventDefault();renamePopHide();}
};
document.addEventListener("click",e=>{if(elCtxMenu&&elCtxMenu.style.display==="block"&&elCtxMenu.contains(e.target))return;if(elPagePop&&elPagePop.style.display==="block"&&elPagePop.contains(e.target))return;if(elRenamePop&&elRenamePop.style.display==="block"&&elRenamePop.contains(e.target))return;ctxHide();},true);

document.addEventListener("keydown",e=>{if(e.key==="Escape")ctxHide();},true);
window.addEventListener("resize",()=>ctxHide(),true);
document.addEventListener("scroll",()=>ctxHide(),true);
if(elCtxMenu)elCtxMenu.addEventListener("contextmenu",e=>e.preventDefault());
if(elCtxMenu)elCtxMenu.addEventListener("mousedown",e=>e.stopPropagation(),true);
if(elCtxMenu)elCtxMenu.onclick=e=>{
let b=e.target.closest("button");if(!b||b.disabled)return;
let act=b.dataset.act||"";
let i=ctxPageIndex;
ctxHide();
if(i==null)return;
if(act==="general"){openPageGeneral(i);return;}
if(act==="schedule"){openPageSchedule(i);return;}
if(act==="rename"){ctxPageIndex=i;renamePopShow();return;}
if(act==="duplicate"){dupPage(i);return;}
if(act==="delete"){if(data.pages.length<=1)return;if(confirm("Delete page?"))delPage(i);return;}
};
if(elWCtxMenu)elWCtxMenu.onclick=e=>{
let b=e.target.closest("button");if(!b||b.disabled)return;
let act=b.dataset.act||"";
if(act==="add"){showAddMenu(b);if(elWCtxMenu)elWCtxMenu.style.display="none";return;}
if(act==="edit"){weditShow(b);wctxMenuOnlyHide();return;}
if(!selectedIds.length){wctxHide();return;}
if(act==="dup"){dupSelected();wctxHide();return;}
if(act==="del"){delSelected();wctxHide();return;}
if(act==="up"){layerStep(1);wctxHide();return;}
if(act==="down"){layerStep(-1);wctxHide();return;}
if(act==="lock"){toggleProp("locked");wctxHide();return;}
if(act==="hide"){applyToSelected(w=>{w.hidden=true;});renderCanvas();wctxUpdate();renderLeftWidgets();wctxHide();return;}
if(act==="bg"){openColorPicker("bg",b);wctxMenuOnlyHide();return;}
if(act==="fg"){openColorPicker("fg",b);wctxMenuOnlyHide();return;}
if(act==="hidden"){return;}
};
if(elWCtxMenu){
elWCtxMenu.addEventListener("mousemove",e=>{
let b=e.target.closest('button[data-act="hidden"]');
if(b&&b.style.display!=="none"){showHiddenMenu(b);if(hiddenHoverTimer){clearTimeout(hiddenHoverTimer);hiddenHoverTimer=null;}}
},true);
elWCtxMenu.addEventListener("mouseleave",()=>hideHiddenMenuSoon(),true);
}
if(elWctxHiddenMenu){
elWctxHiddenMenu.addEventListener("mouseenter",()=>{if(hiddenHoverTimer){clearTimeout(hiddenHoverTimer);hiddenHoverTimer=null;}},true);
elWctxHiddenMenu.addEventListener("mouseleave",()=>hideHiddenMenuSoon(),true);
}
let modalType="text";
function modalShow(){elModalBody.innerHTML="";let s=document.createElement("select");[{v:"text",t:"Text"},{v:"image",t:"Image"},{v:"url",t:"URL"},{v:"clock",t:"Clock"},{v:"carousel",t:"Carousel"}].forEach(o=>{let op=document.createElement("option");op.value=o.v;op.textContent=o.t;s.appendChild(op);});s.value=modalType;s.oninput=()=>modalType=s.value;elModalBody.appendChild(label("Type"));elModalBody.appendChild(s);elModalBg.style.display="block";elModal.style.display="flex";}
function modalHide(){elModalBg.style.display="none";elModal.style.display="none";}
elModalBg.onclick=()=>modalHide();
elModalCancel.onclick=()=>modalHide();
elModalOk.onclick=()=>{modalHide();addWidget(modalType);};
document.getElementById("btnAddWidget").onclick=()=>modalShow();
const elWidgetListLeft=document.getElementById("widgetListLeft");
document.getElementById("btnFront").onclick=()=>{selectedIds.forEach(id=>{let w=getWidgetById(id);if(w)w.z=nextZ();});renderAll();};
document.getElementById("btnBack").onclick=()=>{selectedIds.forEach(id=>{let w=getWidgetById(id);if(w)w.z=1;});renderAll();};
document.getElementById("btnDup").onclick=()=>dupSelected();
document.getElementById("btnDel").onclick=()=>delSelected();
function renderLeftWidgets(){
if(!elWidgetListLeft)return;
elWidgetListLeft.innerHTML="";
let p=getPage();
let ws=p.widgets.slice().sort((a,b)=>(a.z||0)-(b.z||0));
ws.forEach(w=>{
let r=document.createElement("div");
r.className="wli"+(selectedIds.includes(w.id)?" sel":"");
r.onclick=()=>{selectedIds=[w.id];setTab("widget");renderAll();};
let t=document.createElement("div");t.className="t";t.textContent=w.type+" · "+(w.text||w.src||w.id).toString().slice(0,40);
let z=document.createElement("div");z.className="z";z.textContent="Z:"+(w.z||1);
r.appendChild(t);r.appendChild(z);
elWidgetListLeft.appendChild(r);
});
}
let viewScale=1;
function applyViewScale(){let p=getPage();let cw=+p.settings.w||3840,ch=+p.settings.h||2160;let wrap=document.getElementById("canvasWrap");let scaleBox=document.getElementById("canvasScale");let padR=10,padB=10;let maxW=Math.max(100,wrap.clientWidth-padR);let maxH=Math.max(100,wrap.clientHeight-padB);let s=Math.min(maxW/cw,maxH/ch);s=Math.max(0.05,Math.min(10,s));scaleBox.style.width=Math.round(cw*s)+"px";scaleBox.style.height=Math.round(ch*s)+"px";elCanvas.style.transform="scale("+s+")";elCanvas.style.transformOrigin="0 0";viewScale=s;}
window.addEventListener("resize",()=>{try{applyViewScale();}catch(e){}});
function getPage(){return data.pages[pi];}
function getWidgetById(id){return getPage().widgets.find(w=>w.id===id)||null;}
function deepClone(o){return JSON.parse(JSON.stringify(o));}
function setTab(t){
tab="widget";
renderSide();
}
function markStatus(t,ok=true){elStatus.textContent=t;elStatus.style.background=ok?"#ecfeff":"#fee2e2";elStatus.style.borderColor=ok?"#a5f3fc":"#fecaca";}
let uiMode="editor";
function setMode(m){
uiMode=m==="pages"?"pages":"editor";
document.body.classList.toggle("modePages",uiMode==="pages");
document.body.classList.toggle("modeEditor",uiMode==="editor");
document.getElementById("modePages").style.opacity=uiMode==="pages"?"1":"0.7";
document.getElementById("modeEditor").style.opacity=uiMode==="editor"?"1":"0.7";
if(uiMode==="pages"){selectedIds=[];ctxHide();}
if(uiMode==="editor"){tab="widget";}
renderAll();
}
function sanitizeClient(){
if(!data.meta)data.meta={timezone:"Europe/Berlin",autosync_hours:6,globalRotationSpeed:1};
if(!Array.isArray(data.pages))data.pages=[];
if(!data.pages.length)data.pages=[{id:"p1",name:"Page 1",thumb:"",settings:{w:3840,h:2160},background:{fade:1,items:[]},widgets:[]}];
if(!Array.isArray(data.schedules))data.schedules=[];
data.pages.forEach(p=>{
if(!p.id)p.id=uid();
if(!p.name)p.name="Page";
if(!p.settings)p.settings={w:3840,h:2160};
if(!p.background)p.background={fade:1,items:[]};
if(!Array.isArray(p.background.items))p.background.items=[];
if(!Array.isArray(p.widgets))p.widgets=[];
p.widgets.forEach(w=>{
if(!w.id)w.id=uid();
if(!w.type)w.type="text";
if(w.x==null)w.x=40;if(w.y==null)w.y=40;if(w.w==null)w.w=240;if(w.h==null)w.h=140;
if(w.z==null)w.z=1;
if(w.contentW==null)w.contentW=(+p.settings.w||3840);
if(w.contentH==null)w.contentH=(+p.settings.h||2160);
if(!w.style)w.style={radius:0,shadow:false,bg:"",color:"",font:16};
if(!Array.isArray(w.playlist))w.playlist=[];
if(w.type==="url"&&(w.mode!=="iframe"&&w.mode!=="fetch"))w.mode="iframe";
if(w.type!=="url"&&w.mode!=null)delete w.mode;
if(w.autoContent==null)w.autoContent=false;
if(w.lockAspect==null)w.lockAspect=false;
});
});
data.schedules.forEach(r=>{
if(!r.id)r.id=uid();
if(r.enabled==null)r.enabled=true;
if(!r.name)r.name="Rule";
if(!r.from)r.from="08:00";
if(!r.to)r.to="18:00";
if(!Array.isArray(r.weekdays))r.weekdays=[1,2,3,4,5];
if(!r.override)r.override={rotationSpeed:null,background:null,widgets:{}};
if(!r.override.widgets||typeof r.override.widgets!=="object")r.override.widgets={};
});
}
function renderPages(){
elPageList.innerHTML="";
data.pages.forEach((p,i)=>{
let row=document.createElement("div");
row.className="pg"+(i===pi?" active":"");
row.onclick=()=>{pi=i;selectedIds=[];renderAll();};
row.oncontextmenu=e=>ctxShow(e,i);
row.draggable=true;
row.ondragstart=e=>{
dragPageFrom=i;
row.classList.add("dragging");
try{e.dataTransfer.effectAllowed="move";e.dataTransfer.setData("text/plain",String(i));}catch(_){}
};
row.ondragend=()=>{
dragPageFrom=null;
clearPageDragUi();
};
row.ondragover=e=>{
e.preventDefault();
row.classList.add("drop");
try{e.dataTransfer.dropEffect="move";}catch(_){}
};
row.ondragleave=()=>row.classList.remove("drop");
row.ondrop=e=>{
e.preventDefault();
row.classList.remove("drop");
let from=dragPageFrom;
if(from==null){try{from=+e.dataTransfer.getData("text/plain");}catch(_){from=null;}}
reorderPages(from,i);
};

let th=document.createElement("div");th.className="thumb";
let thumbSrc=p.thumb||(p.background.items[0]&&p.background.items[0].src)||"";
if(thumbSrc)th.style.backgroundImage="url('"+thumbSrc.replace(/'/g,"%27")+"')";
row.appendChild(th);

let meta=document.createElement("div");meta.className="meta";
let name=document.createElement("div");name.className="name";name.textContent=p.name;
let sub=document.createElement("div");sub.className="sub";sub.textContent=String(p.widgets.length||0);
meta.appendChild(name);meta.appendChild(sub);
row.appendChild(meta);

elPageList.appendChild(row);
});
}
function miniBtn(t,fn){let b=document.createElement("button");b.textContent=t;b.onclick=fn;return b;}
function movePage(i,dir){let j=i+dir;if(j<0||j>=data.pages.length)return;let a=data.pages[i];data.pages.splice(i,1);data.pages.splice(j,0,a);pi=j;renderAll();}
function addPage(){
let btn=document.querySelector('#pagesPanel .bar button');
newPagePopShow(btn);
}
function newPagePopHide(){if(elNewPagePop)elNewPagePop.style.display="none";}
function addPageWithName(name){
name=(name==null?"":String(name)).trim();
if(!name)name="Page "+(data.pages.length+1);
data.pages.push({id:uid(),name:name,thumb:"",settings:{w:3840,h:2160},background:{fade:1,items:[]},widgets:[]});
pi=data.pages.length-1;
selectedIds=[];
renderAll();
}
function newPagePopShow(anchorEl){
if(!elNewPagePop||!elNewPageInp)return;
elNewPageInp.value="Page "+(data.pages.length+1);
elNewPagePop.style.display="block";
let x=120,y=70;
if(anchorEl){
let r=anchorEl.getBoundingClientRect();
x=r.right+8;y=r.top;
}
let rr=elNewPagePop.getBoundingClientRect();
let ww=window.innerWidth,hh=window.innerHeight;
x=Math.max(8,Math.min(x,ww-rr.width-8));
y=Math.max(8,Math.min(y,hh-rr.height-8));
elNewPagePop.style.left=x+"px";
elNewPagePop.style.top=y+"px";
elNewPageInp.focus();
elNewPageInp.select();
}
if(elNewPageCancel)elNewPageCancel.onclick=()=>newPagePopHide();
if(elNewPageOk)elNewPageOk.onclick=()=>{
let v=String(elNewPageInp.value||"").trim();
newPagePopHide();
addPageWithName(v);
};
if(elNewPageInp)elNewPageInp.onkeydown=e=>{
if(e.key==="Enter"){e.preventDefault();if(elNewPageOk)elNewPageOk.click();}
if(e.key==="Escape"){e.preventDefault();newPagePopHide();}
};
function dupPage(i){
let p=deepClone(data.pages[i]);p.id=uid();p.name=p.name+" Copy";p.widgets.forEach(w=>{w.id=uid();w.x+=20;w.y+=20;});data.pages.push(p);pi=data.pages.length-1;selectedIds=[];renderAll();
}
function delPage(i){
if(data.pages.length<=1)return;
data.pages.splice(i,1);
if(pi>=data.pages.length)pi=data.pages.length-1;
selectedIds=[];renderAll();
}
function applyWidgetStyle(el,w){
let s=w.style||{};
el.style.borderRadius=(+s.radius||0)+"px";
el.style.boxShadow=s.shadow?"0 6px 18px rgba(0,0,0,.35)":"";
el.style.background=(s.bg||"rgba(255,255,255,.84)");
el.style.color=(s.color||"#111827");
el.style.fontSize=((+s.font||16))+"px";
el.style.zIndex=(w.z||1)+1;
el.classList.toggle("locked",!!w.locked);
el.style.display=w.hidden?"none":"block";
}
function widgetLabel(w){
if(w.type==="text")return (w.text||"Text").slice(0,40);
if(w.type==="image")return (w.src||"Image").slice(0,40);
if(w.type==="url")return (w.src||"URL").slice(0,40);
if(w.type==="clock")return "Clock";
if(w.type==="carousel")return "Carousel ("+((w.playlist||[]).length)+")";
return w.type;
}
function renderCanvas(){
let p=getPage();
let cw=+p.settings.w||3840,ch=+p.settings.h||2160;
elCanvas.style.width=cw+"px";elCanvas.style.height=ch+"px";
applyViewScale();
elCanvasInfo.textContent="Canvas "+cw+"×"+ch+" · Page: "+p.name;
startBgPreview(p);
Array.from(elCanvas.querySelectorAll(".widget")).forEach(n=>n.remove());
let widgets=p.widgets.slice().sort((a,b)=>(a.z||0)-(b.z||0));
widgets.forEach(w=>{
let d=document.createElement("div");
d.className="widget"+(selectedIds.includes(w.id)?" sel":"");
d.style.left=(w.x||0)+"px";d.style.top=(w.y||0)+"px";d.style.width=(w.w||100)+"px";d.style.height=(w.h||80)+"px";
applyWidgetStyle(d,w);
d.dataset.id=w.id;
d.onmousedown=e=>startDrag(e,w);
d.oncontextmenu=e=>{
if(uiMode==="pages")return;
e.preventDefault();e.stopPropagation();
let multi=e.ctrlKey||e.metaKey||e.shiftKey;
if(multi){if(!selectedIds.includes(w.id))selectedIds=[...selectedIds,w.id];}
else selectedIds=[w.id];
renderCanvas();
wctxShow(e);
};
d.ondblclick=e=>{e.stopPropagation();selectedIds=[w.id];renderCanvas();};
d.dataset.type=w.type;
renderWidgetPreview(d,w);
let zt=document.createElement("div");zt.className="pvLayer";zt.textContent="Z:"+(w.z||1);d.appendChild(zt);
let h=document.createElement("div");h.className="handle";h.onmousedown=e=>startResize(e,w);d.appendChild(h);
elCanvas.appendChild(d);
ensurePreviewLoops();
});
elSelInfo.textContent=selectedIds.length?("Selected: "+selectedIds.length):"Selected: none";
}
function proxyFetchUrl(u){return location.pathname+"?action=fetchHtml&url="+encodeURIComponent(u)+"&ts="+Date.now();}
function ensurePreviewLoops(){if(previewStarted)return;previewStarted=true;setInterval(()=>tickPreview(),250);}
function tickPreview(){let p=getPage();tickBg(p);tickClock();}
function startBgPreview(p){
let items=p.background&&Array.isArray(p.background.items)?p.background.items:[];
let fade=clamp(+((p.background&&p.background.fade)||0),0,20);
elBgA.style.transitionDuration=fade+"s";elBgB.style.transitionDuration=fade+"s";
let sig=items.map(it=>(it.src||"")+"|"+(+it.duration||0)).join("||")+"|f"+fade;
if(bgState.sig!==sig||bgState.pageId!==p.id)bgState={sig:sig,i:0,nextAt:0,onA:true,pageId:p.id};
tickBg(p);
}
function tickBg(p){
let items=p.background&&Array.isArray(p.background.items)?p.background.items:[];
if(!items.length){elBgA.classList.remove("on");elBgB.classList.remove("on");return;}
let fade=clamp(+((p.background&&p.background.fade)||0),0,20);
elBgA.style.transitionDuration=fade+"s";elBgB.style.transitionDuration=fade+"s";
let sig=items.map(it=>(it.src||"")+"|"+(+it.duration||0)).join("||")+"|f"+fade;
if(bgState.sig!==sig||bgState.pageId!==p.id)bgState={sig:sig,i:0,nextAt:0,onA:true,pageId:p.id};
let now=Date.now();
if(bgState.nextAt===0){
let it=items[0];let src=(it.src||"").trim();
let show=bgState.onA?elBgA:elBgB;let hide=bgState.onA?elBgB:elBgA;
if(src)show.style.backgroundImage="url('"+src.replace(/'/g,"%27")+"')";
show.classList.add("on");hide.classList.remove("on");
bgState.nextAt=now+clamp(+it.duration||10,1,3600)*1000;
return;
}
if(now<bgState.nextAt)return;
bgState.i=(bgState.i+1)%items.length;
bgState.onA=!bgState.onA;
let it=items[bgState.i];let src=(it.src||"").trim();
let show=bgState.onA?elBgA:elBgB;let hide=bgState.onA?elBgB:elBgA;
if(src)show.style.backgroundImage="url('"+src.replace(/'/g,"%27")+"')";
show.classList.add("on");hide.classList.remove("on");
bgState.nextAt=now+clamp(+it.duration||10,1,3600)*1000;
}
function tickClock(){
let t=new Date().toLocaleTimeString();
document.querySelectorAll('.widget[data-type="clock"] .pvClock').forEach(n=>n.textContent=t);
}
let carPrevStatic={};
function carFirstSig(w){let list=Array.isArray(w.playlist)?w.playlist:[];if(!list.length)return "";let it=list[0]||{};return String(it.type||"image")+"|"+String(it.src||"")+"|"+String(it.title||"");}
function carEnsureStaticNode(w){let sig=carFirstSig(w);if(!sig)return null;let st=carPrevStatic[w.id];if(st&&st.sig===sig&&st.node)return st;let list=Array.isArray(w.playlist)?w.playlist:[];let it=list[0]||{};let t=(it.type||"image");let node;if(t==="image"){node=document.createElement("img");node.src=String(it.src||"");}else{node=document.createElement("iframe");node.src=String(it.src||"");node.style.border="0";node.style.background="#000";}node.className="slot on";carPrevStatic[w.id]={sig:sig,node:node};return carPrevStatic[w.id];}
function carMountStatic(box,w){let st=carEnsureStaticNode(w);box.innerHTML="";if(!st||!st.node){let c=document.createElement("div");c.className="pvCenter";c.textContent="empty";box.appendChild(c);return;}box.appendChild(st.node);let list=Array.isArray(w.playlist)?w.playlist:[];let it=list[0]||{};if(it.title){let tag=document.createElement("div");tag.className="pvTag";tag.textContent=String(it.title);box.appendChild(tag);}}
function tickCarousel(p){}
function renderCarouselItem(el,w,i){
let st=carState[w.id];
if(!st){carState[w.id]=st={sig:"",i:0,nextAt:0,nodes:[]};}
let list=Array.isArray(w.playlist)?w.playlist:[];
if(!list.length){el.innerHTML="";return;}
i=((i%list.length)+list.length)%list.length;
if(!st.nodes)st.nodes=[];
if(!st.nodes[i]){
let it=list[i]||{};
let t=(it.type||"image");
let node;
if(t==="image"){node=document.createElement("img");node.src=it.src||"";}
else{node=document.createElement("iframe");node.src=it.src||"";node.style.border="0";}
node.dataset.i=String(i);
st.nodes[i]=node;
}
el.innerHTML="";
el.appendChild(st.nodes[i]);
let it=list[i]||{};
if(it.title){let tag=document.createElement("div");tag.className="pvTag";tag.textContent=it.title;el.appendChild(tag);}
}
function cssEsc(s){return (window.CSS&&CSS.escape)?CSS.escape(s):String(s).replace(/[^a-zA-Z0-9_\-]/g,"\\$&");}
function renderWidgetPreview(d,w){
d.innerHTML="";
let wrap=document.createElement("div");
wrap.className="pv";
d.appendChild(wrap);
if(w.type==="text"){
let t=document.createElement("div");
t.style.padding="8px";
t.style.whiteSpace="pre-wrap";
t.textContent=w.text||"";
wrap.appendChild(t);
return;
}
if(w.type==="image"){
let img=document.createElement("img");
img.src=w.src||"";
wrap.appendChild(img);
return;
}
if(w.type==="clock"){
let c=document.createElement("div");
c.className="pvCenter pvClock";
c.textContent=new Date().toLocaleTimeString();
wrap.appendChild(c);
return;
}
if(w.type==="url"){
let url=(w.src||"").trim();
let mode=w.mode||"iframe";
let cw=+w.contentW||3840;
let ch=+w.contentH||2160;
let tag=document.createElement("div");
tag.className="pvTag";
tag.textContent=mode+" · "+cw+"×"+ch;
wrap.appendChild(tag);
if(!url){
let c=document.createElement("div");
c.className="pvCenter";
c.textContent="no url";
wrap.appendChild(c);
return;
}
let sc=Math.min((w.w||1)/cw,(w.h||1)/ch);
sc=clamp(sc,0.02,1);
let inner=document.createElement("div");
inner.style.position="absolute";
inner.style.left="0";
inner.style.top="0";
inner.style.width=cw+"px";
inner.style.height=ch+"px";
inner.style.transformOrigin="0 0";
inner.style.transform="scale("+sc+")";
let fr=document.createElement("iframe");
fr.width=cw;
fr.height=ch;
fr.style.width=cw+"px";
fr.style.height=ch+"px";
fr.style.border="0";
if(mode==="fetch")fr.setAttribute("sandbox","allow-scripts allow-forms allow-popups allow-same-origin allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation");
inner.appendChild(fr);
wrap.appendChild(inner);
fr.src=(mode==="fetch")?proxyFetchUrl(url):url;
return;
}
if(w.type==="carousel"){
let box=document.createElement("div");
box.className="pvCarousel";
box.style.position="absolute";
box.style.inset="0";
wrap.appendChild(box);
carMountStatic(box,w);
return;
}
let c=document.createElement("div");
c.className="pvCenter";
c.textContent=widgetLabel(w);
wrap.appendChild(c);
}
function addWidget(type){
let p=getPage();
let base={id:uid(),type,x:60,y:60,w:320,h:180,z:nextZ(),locked:false,hidden:false,contentW:(+p.settings.w||3840),contentH:(+p.settings.h||2160),style:{radius:2,shadow:false,bg:"rgba(255,255,255,.84)",color:"#111827",font:16},src:"",mode:"iframe",text:"",format:"HH:mm:ss",playlist:[]};
if(type==="text"){base.text="Hello";}
if(type==="clock"){base.w=220;base.h=90;base.style.font=28;base.style.bg="rgba(17,24,39,.65)";base.style.color="#fff";base.style.radius=14;base.style.shadow=true;delete base.mode;}
if(type==="image"){base.src="https://";delete base.mode;}
if(type==="url"){base.src="https://";base.mode="iframe";}
if(type==="carousel"){delete base.mode;base.playlist=[{type:"image",src:"https://",duration:5,title:""}];}
getPage().widgets.push(base);
selectedIds=[base.id];
setTab("widget");
renderAll();
}
function nextZ(){let z=1;getPage().widgets.forEach(w=>{z=Math.max(z,(w.z||1)+1)});return z;}
function startDrag(e,w){
if(e.button!==0)return;
if(uiMode==="pages")return;
let id=w.id;
let multi=e.ctrlKey||e.metaKey||e.shiftKey;
if(multi){if(selectedIds.includes(id))selectedIds=selectedIds.filter(x=>x!==id);else selectedIds=[...selectedIds,id];}
else{if(!selectedIds.includes(id))selectedIds=[id];}
renderCanvas();
if(tab==="widget")renderSide();
if(w.locked)return;
let ids=selectedIds.slice();
let startX=e.clientX,startY=e.clientY;
let p=getPage();
let start=ids.map(i=>{let ww=p.widgets.find(x=>x.id===i);return {id:i,x:ww.x,y:ww.y};});
dragState={ids,startX,startY,start};
document.onmousemove=onMove;
document.onmouseup=onUp;
e.preventDefault();
}
function startResize(e,w){
if(e.button!==0)return;
if(uiMode==="pages")return;
if(!selectedIds.includes(w.id))selectedIds=[w.id];
renderCanvas();
if(tab==="widget")renderSide();
if(w.locked)return;
resizeState={id:w.id,startX:e.clientX,startY:e.clientY,startW:w.w,startH:w.h,startCW:(w.contentW||3840),startCH:(w.contentH||2160),ratio:((w.contentW||w.w||1)/(w.contentH||w.h||1))};
document.onmousemove=onMove;
document.onmouseup=onUp;
e.stopPropagation();e.preventDefault();
}
function onMove(e){
lastMouse={x:e.clientX,y:e.clientY};
if(dragState){
let dx=(e.clientX-dragState.startX)/viewScale,dy=(e.clientY-dragState.startY)/viewScale;
let p=getPage();
dragState.start.forEach(s=>{
let w=p.widgets.find(x=>x.id===s.id);
let cw=+p.settings.w||3840,ch=+p.settings.h||2160;
w.x=Math.round(clamp(s.x+dx,0,Math.max(0,cw-(w.w||0))));
w.y=Math.round(clamp(s.y+dy,0,Math.max(0,ch-(w.h||0))));
});
renderCanvas();
}
if(resizeState){
let p=getPage();let w=p.widgets.find(x=>x.id===resizeState.id);
let dw=(e.clientX-resizeState.startX)/viewScale,dh=(e.clientY-resizeState.startY)/viewScale;
let cw=+p.settings.w||3840,ch=+p.settings.h||2160;
let maxW=Math.max(20,cw-(w.x||0)),maxH=Math.max(20,ch-(w.y||0));
let newW=clamp(resizeState.startW+dw,20,maxW);
let newH=clamp(resizeState.startH+dh,20,maxH);
if(w.lockAspect){
let r=resizeState.ratio||1;
if(Math.abs(dw)>=Math.abs(dh)){newH=clamp(newW/(r||1),20,maxH);}
else{newW=clamp(newH*(r||1),20,maxW);}
}
w.w=Math.round(newW);
w.h=Math.round(newH);
if(w.autoContent){
let fx=(newW/(resizeState.startW||1));
let fy=(newH/(resizeState.startH||1));
if(w.lockAspect){fy=fx;}
w.contentW=Math.round(clamp((resizeState.startCW||3840)*fx,320,20000));
w.contentH=Math.round(clamp((resizeState.startCH||2160)*fy,240,20000));
}
renderCanvas();
}
}
function onUp(){dragState=null;resizeState=null;document.onmousemove=null;document.onmouseup=null;}
elCanvas.onmousedown=e=>{if(e.target===elCanvas||e.target===elBgPreview){selectedIds=[];renderAll();}};
elCanvas.oncontextmenu=e=>{
if(uiMode==="pages")return;
if(e.target!==elCanvas&&e.target!==elBgPreview)return;
selectedIds=[];
renderCanvas();
wctxShow(e);
};
function renderSide(){
if(elPanel)elPanel.innerHTML="";
sanitizeClient();
}
function fieldset(title){let f=document.createElement("fieldset");let l=document.createElement("legend");l.textContent=title;f.appendChild(l);return f;}
function label(t){let l=document.createElement("label");l.textContent=t;return l;}
function input(type,val,on){let i=document.createElement("input");i.type=type;i.value=val??"";i.oninput=()=>on(type==="number"?+i.value:i.value);return i;}
function checkbox(val,on){let i=document.createElement("input");i.type="checkbox";i.checked=!!val;i.oninput=()=>on(i.checked);return i;}
function textarea(val,on){let t=document.createElement("textarea");t.value=val??"";t.oninput=()=>on(t.value);return t;}
function select(val,opts,on){let s=document.createElement("select");opts.forEach(o=>{let op=document.createElement("option");op.value=o.v;op.textContent=o.t;s.appendChild(op);});s.value=val??opts[0].v;s.oninput=()=>on(s.value);return s;}
function btn(t,cls,fn){let b=document.createElement("button");b.textContent=t;b.className=cls||"";b.onclick=fn;return b;}
function buildGeneralEditor(p){
let root=document.createElement("div");
let f=fieldset("Page Settings");
f.appendChild(label("Name"));
f.appendChild(input("text",p.name,v=>{p.name=v;renderPages();renderCanvas();}));
let r=document.createElement("div");r.className="row2";
let cw=document.createElement("div");cw.appendChild(label("Canvas Width"));cw.appendChild(input("number",p.settings.w,v=>{p.settings.w=clamp(+v||3840,320,20000);p.widgets.forEach(w=>{if(w.contentW==null)w.contentW=p.settings.w;});renderCanvas();}));
let ch=document.createElement("div");ch.appendChild(label("Canvas Height"));ch.appendChild(input("number",p.settings.h,v=>{p.settings.h=clamp(+v||2160,240,20000);p.widgets.forEach(w=>{if(w.contentH==null)w.contentH=p.settings.h;});renderCanvas();}));
r.appendChild(cw);r.appendChild(ch);
f.appendChild(r);
f.appendChild(label("Page Thumbnail (optional image URL)"));
f.appendChild(input("text",p.thumb,v=>{p.thumb=v;renderPages();}));
root.appendChild(f);

let fbg=fieldset("Background Playlist (images)");
let rr=document.createElement("div");rr.className="row2";
let fade=document.createElement("div");fade.appendChild(label("Fade seconds"));fade.appendChild(input("number",p.background.fade,v=>{p.background.fade=clamp(+v||0,0,20);renderCanvas();}));
let add=document.createElement("div");add.appendChild(label("Add"));add.appendChild(btn("+ Background Item","",()=>{p.background.items.push({src:"https://",duration:10});editModalShow("General · "+(p.name||"Page"),buildGeneralEditor(p));renderPages();renderCanvas();}));
rr.appendChild(fade);rr.appendChild(add);
fbg.appendChild(rr);

(p.background.items||[]).forEach((it,i)=>{
let row=document.createElement("fieldset");row.style.borderStyle="dashed";
let lg=document.createElement("legend");lg.textContent="Item "+(i+1);row.appendChild(lg);
row.appendChild(label("Image URL"));row.appendChild(input("text",it.src,v=>{it.src=v;renderPages();renderCanvas();}));
row.appendChild(label("Duration seconds"));row.appendChild(input("number",it.duration,v=>{it.duration=clamp(+v||1,1,3600);}));
let br=document.createElement("div");br.className="btnRow";
br.appendChild(btn("Up","alt",()=>{if(i===0)return;let a=p.background.items[i-1];p.background.items[i-1]=p.background.items[i];p.background.items[i]=a;editModalShow("General · "+(p.name||"Page"),buildGeneralEditor(p));renderCanvas();}));
br.appendChild(btn("Down","alt",()=>{if(i===p.background.items.length-1)return;let a=p.background.items[i+1];p.background.items[i+1]=p.background.items[i];p.background.items[i]=a;editModalShow("General · "+(p.name||"Page"),buildGeneralEditor(p));renderCanvas();}));
br.appendChild(btn("Delete","danger",()=>{p.background.items.splice(i,1);editModalShow("General · "+(p.name||"Page"),buildGeneralEditor(p));renderPages();renderCanvas();}));
row.appendChild(br);
fbg.appendChild(row);
});
root.appendChild(fbg);

let note=document.createElement("div");note.className="small";note.textContent="Close saves automatically (changes are live).";
root.appendChild(note);
return root;
}
function buildScheduleEditor(pageId){
let root=document.createElement("div");
let f=fieldset("Schedule");
let br=document.createElement("div");br.className="btnRow";
br.appendChild(btn("+ Rule","",()=>{addRuleForPage(pageId);editModalShow("Schedule",buildScheduleEditor(pageId));}));
f.appendChild(br);

let rules=data.schedules.map((r,idx)=>({r,idx})).filter(x=>(x.r.page||"")===(pageId||""));
if(!rules.length){let t=document.createElement("div");t.className="small";t.textContent="No rules for this page.";f.appendChild(t);root.appendChild(f);return root;}

rules.forEach(x=>{
let r=x.r,idx=x.idx;
let rr=fieldset("");rr.style.borderStyle="dashed";rr.removeChild(rr.firstChild);
let lg=document.createElement("legend");lg.textContent=(r.enabled?"✓ ":"⨯ ")+(r.name||"Rule");rr.appendChild(lg);

let top=document.createElement("div");top.className="row2";
let a=document.createElement("div");a.appendChild(label("Name"));a.appendChild(input("text",r.name||"",v=>{r.name=v;editModalShow("Schedule",buildScheduleEditor(pageId));}));
let b=document.createElement("div");b.appendChild(label("On"));b.appendChild(checkbox(r.enabled,v=>{r.enabled=v;editModalShow("Schedule",buildScheduleEditor(pageId));}));
top.appendChild(a);top.appendChild(b);
rr.appendChild(top);

let tm=document.createElement("div");tm.className="row2";
let fr=document.createElement("div");fr.appendChild(label("From"));fr.appendChild(input("text",r.from||"08:00",v=>{r.from=v;}));
let to=document.createElement("div");to.appendChild(label("To"));to.appendChild(input("text",r.to||"18:00",v=>{r.to=v;}));
tm.appendChild(fr);tm.appendChild(to);
rr.appendChild(tm);

rr.appendChild(label("Days"));
let wd=document.createElement("div");wd.className="row3";
["Mon","Tue","Wed","Thu","Fri","Sat","Sun"].forEach((n,i)=>{
let d=document.createElement("div");
let c=document.createElement("input");c.type="checkbox";c.checked=(r.weekdays||[]).includes(i+1);
c.oninput=()=>{r.weekdays=r.weekdays||[];if(c.checked){if(!r.weekdays.includes(i+1))r.weekdays.push(i+1);}else r.weekdays=r.weekdays.filter(x=>x!==i+1);};
let l=document.createElement("label");l.style.display="flex";l.style.gap="8px";l.style.alignItems="center";l.style.margin="0";
l.appendChild(c);let t=document.createElement("span");t.textContent=n;l.appendChild(t);
d.appendChild(l);wd.appendChild(d);
});
rr.appendChild(wd);

let rb=document.createElement("div");rb.className="btnRow";
rb.appendChild(btn("Up","alt",()=>{moveRule(idx,-1);editModalShow("Schedule",buildScheduleEditor(pageId));}));
rb.appendChild(btn("Down","alt",()=>{moveRule(idx,1);editModalShow("Schedule",buildScheduleEditor(pageId));}));
rb.appendChild(btn("Copy","alt",()=>{let c=deepClone(r);c.id=uid();c.name=(c.name||"Rule")+" Copy";data.schedules.splice(idx+1,0,c);editModalShow("Schedule",buildScheduleEditor(pageId));}));
rb.appendChild(btn("Del","danger",()=>{data.schedules.splice(idx,1);editModalShow("Schedule",buildScheduleEditor(pageId));}));
rr.appendChild(rb);

f.appendChild(rr);
});

root.appendChild(f);
let note=document.createElement("div");note.className="small";note.textContent="Close saves automatically (changes are live).";
root.appendChild(note);
return root;
}

function addRuleForPage(pageId){
data.schedules.push({id:uid(),name:"Rule "+(data.schedules.length+1),enabled:true,from:"08:00",to:"18:00",weekdays:[1,2,3,4,5],page:pageId,override:{rotationSpeed:null,background:null,widgets:{}}});
renderAll();
}
function renderPage(){
let p=getPage();
let f=fieldset("Page Settings");
f.appendChild(label("Name"));
f.appendChild(input("text",p.name,v=>{p.name=v;renderPages();renderCanvas();}));
let r=document.createElement("div");r.className="row2";
let cw=document.createElement("div");cw.appendChild(label("Canvas Width"));cw.appendChild(input("number",p.settings.w,v=>{p.settings.w=clamp(+v||3840,320,20000);renderCanvas();}));
let ch=document.createElement("div");ch.appendChild(label("Canvas Height"));ch.appendChild(input("number",p.settings.h,v=>{p.settings.h=clamp(+v||2160,240,20000);renderCanvas();}));
r.appendChild(cw);r.appendChild(ch);
f.appendChild(r);
f.appendChild(label("Page Thumbnail (optional image URL)"));
f.appendChild(input("text",p.thumb,v=>{p.thumb=v;renderPages();}));
let fbg=fieldset("Background Playlist (images)");
let rr=document.createElement("div");rr.className="row2";
let fade=document.createElement("div");fade.appendChild(label("Fade seconds"));fade.appendChild(input("number",p.background.fade,v=>p.background.fade=clamp(+v||0,0,20)));
let add=document.createElement("div");add.appendChild(label("Add"));add.appendChild(btn("+ Background Item","",()=>{p.background.items.push({src:"https://",duration:10});renderAll();}));
rr.appendChild(fade);rr.appendChild(add);
fbg.appendChild(rr);
p.background.items.forEach((it,i)=>{
let row=document.createElement("fieldset");row.style.borderStyle="dashed";
let lg=document.createElement("legend");lg.textContent="Item "+(i+1);row.appendChild(lg);
row.appendChild(label("Image URL"));row.appendChild(input("text",it.src,v=>{it.src=v;renderPages();renderCanvas();}));
row.appendChild(label("Duration seconds"));row.appendChild(input("number",it.duration,v=>it.duration=clamp(+v||1,1,3600)));
let br=document.createElement("div");br.className="btnRow";
br.appendChild(btn("Up","alt",()=>{if(i===0)return;let a=p.background.items[i-1];p.background.items[i-1]=p.background.items[i];p.background.items[i]=a;renderAll();}));
br.appendChild(btn("Down","alt",()=>{if(i===p.background.items.length-1)return;let a=p.background.items[i+1];p.background.items[i+1]=p.background.items[i];p.background.items[i]=a;renderAll();}));
br.appendChild(btn("Delete","danger",()=>{p.background.items.splice(i,1);renderAll();}));
row.appendChild(br);
fbg.appendChild(row);
});
let fw=fieldset("Page Widgets");
let br=document.createElement("div");br.className="btnRow";
br.appendChild(btn("Bring Front","alt",()=>{selectedIds.forEach(id=>{let w=getWidgetById(id);if(w)w.z=nextZ();});renderCanvas();}));
br.appendChild(btn("Send Back","alt",()=>{selectedIds.forEach(id=>{let w=getWidgetById(id);if(w)w.z=1;});renderCanvas();}));
br.appendChild(btn("Duplicate Sel","alt",()=>{dupSelected();}));
br.appendChild(btn("Delete Sel","danger",()=>{delSelected();}));
fw.appendChild(br);
let lockRow=document.createElement("div");lockRow.className="row2";
let l1=document.createElement("div");l1.appendChild(label("Lock Selected"));let c1=checkbox(false,v=>{selectedIds.forEach(id=>{let w=getWidgetById(id);if(w)w.locked=v;});renderCanvas();});l1.appendChild(c1);
let l2=document.createElement("div");l2.appendChild(label("Hide Selected"));let c2=checkbox(false,v=>{selectedIds.forEach(id=>{let w=getWidgetById(id);if(w)w.hidden=v;});renderCanvas();});l2.appendChild(c2);
lockRow.appendChild(l1);lockRow.appendChild(l2);
fw.appendChild(lockRow);
elPanel.appendChild(f);elPanel.appendChild(fbg);elPanel.appendChild(fw);
}
function dupSelected(){
let p=getPage();
let items=selectedIds.map(id=>p.widgets.find(w=>w.id===id)).filter(Boolean);
items.forEach(w=>{
let n=deepClone(w);n.id=uid();n.x+=20;n.y+=20;n.z=nextZ();p.widgets.push(n);
});
selectedIds=items.length?[p.widgets[p.widgets.length-1].id]:[];
renderAll();
}
function delSelected(){
let p=getPage();
p.widgets=p.widgets.filter(w=>!selectedIds.includes(w.id));
selectedIds=[];renderAll();
}
function renderWidget(){
let p=getPage();
if(!selectedIds.length){let f=fieldset("Widget");let t=document.createElement("div");t.className="small";t.textContent="Select a widget on the canvas (double click opens Widget tab).";f.appendChild(t);elPanel.appendChild(f);return;}
if(selectedIds.length>1){
let f=fieldset("Multiple Widgets");
let t=document.createElement("div");t.className="small";t.textContent="Editing applies to all selected widgets where applicable.";f.appendChild(t);
let st=fieldset("Common Style");
let r=document.createElement("div");r.className="row2";
let a=document.createElement("div");a.appendChild(label("Radius"));a.appendChild(input("number","",v=>{selectedIds.forEach(id=>{let w=getWidgetById(id);if(w){w.style=w.style||{};w.style.radius=+v||0;}});renderCanvas();}));
let b=document.createElement("div");b.appendChild(label("Shadow"));b.appendChild(checkbox(false,v=>{selectedIds.forEach(id=>{let w=getWidgetById(id);if(w){w.style=w.style||{};w.style.shadow=v;}});renderCanvas();}));
r.appendChild(a);r.appendChild(b);st.appendChild(r);
st.appendChild(label("Background"));st.appendChild(input("text","",v=>{selectedIds.forEach(id=>{let w=getWidgetById(id);if(w){w.style=w.style||{};w.style.bg=v;}});renderCanvas();}));
st.appendChild(label("Text Color"));st.appendChild(input("text","",v=>{selectedIds.forEach(id=>{let w=getWidgetById(id);if(w){w.style=w.style||{};w.style.color=v;}});renderCanvas();}));
st.appendChild(label("Font px"));st.appendChild(input("number","",v=>{selectedIds.forEach(id=>{let w=getWidgetById(id);if(w){w.style=w.style||{};w.style.font=+v||16;}});renderCanvas();}));
let acts=fieldset("Actions");
let br=document.createElement("div");br.className="btnRow";
br.appendChild(btn("Bring Front","alt",()=>{selectedIds.forEach(id=>{let w=getWidgetById(id);if(w)w.z=nextZ();});renderCanvas();}));
br.appendChild(btn("Send Back","alt",()=>{selectedIds.forEach(id=>{let w=getWidgetById(id);if(w)w.z=1;});renderCanvas();}));
br.appendChild(btn("Duplicate","alt",()=>dupSelected()));
br.appendChild(btn("Delete","danger",()=>delSelected()));
acts.appendChild(br);
elPanel.appendChild(f);elPanel.appendChild(st);elPanel.appendChild(acts);
return;
}
let w=p.widgets.find(x=>x.id===selectedIds[0]);
if(!w){selectedIds=[];renderAll();return;}
let f=fieldset("Widget");
f.appendChild(label("Type"));let t=document.createElement("div");t.textContent=w.type;f.appendChild(t);
let r=document.createElement("div");r.className="row2";
let z=document.createElement("div");z.appendChild(label("Layer (Z)"));
let zr=document.createElement("div");zr.className="row3";
zr.appendChild(input("number",w.z,v=>{w.z=clamp(+v||1,1,99999);renderCanvas();}));
zr.appendChild(btn("Up","alt",()=>{w.z=clamp((w.z||1)+1,1,99999);renderCanvas();renderSide();}));
zr.appendChild(btn("Down","alt",()=>{w.z=clamp((w.z||1)-1,1,99999);renderCanvas();renderSide();}));
z.appendChild(zr);
let lock=document.createElement("div");lock.appendChild(label("Locked"));lock.appendChild(checkbox(w.locked,v=>{w.locked=v;renderCanvas();}));
r.appendChild(z);r.appendChild(lock);
f.appendChild(r);
let r2=document.createElement("div");r2.className="row2";
let hid=document.createElement("div");hid.appendChild(label("Hidden"));hid.appendChild(checkbox(w.hidden,v=>{w.hidden=v;renderCanvas();}));
let size=document.createElement("div");size.appendChild(label("Size (W×H)"));let s=document.createElement("div");s.className="small";s.textContent=(w.w|0)+"×"+(w.h|0);size.appendChild(s);
r2.appendChild(hid);r2.appendChild(size);
f.appendChild(r2);
let rz=fieldset("Resize");
let rr=document.createElement("div");rr.className="row2";
let ac=document.createElement("div");ac.appendChild(label("Resize scales content"));ac.appendChild(checkbox(!!w.autoContent,v=>{w.autoContent=v;}));
let la=document.createElement("div");la.appendChild(label("Lock aspect"));la.appendChild(checkbox(!!w.lockAspect,v=>{w.lockAspect=v;}));
rr.appendChild(ac);rr.appendChild(la);
rz.appendChild(rr);
elPanel.appendChild(rz);
let ps=fieldset("Presets");
let brp=document.createElement("div");brp.className="btnRow tight";
brp.appendChild(btn("16:9","alt",()=>{w.lockAspect=true;let r=16/9;let nw=w.w||320;w.h=Math.round(nw/r);renderCanvas();renderSide();}));
brp.appendChild(btn("4:3","alt",()=>{w.lockAspect=true;let r=4/3;let nw=w.w||320;w.h=Math.round(nw/r);renderCanvas();renderSide();}));
brp.appendChild(btn("1:1","alt",()=>{w.lockAspect=true;w.h=w.w||320;renderCanvas();renderSide();}));
ps.appendChild(brp);
let brp2=document.createElement("div");brp2.className="btnRow tight";
brp2.appendChild(btn("320×180","alt",()=>{w.w=320;w.h=180;renderCanvas();renderSide();}));
brp2.appendChild(btn("640×360","alt",()=>{w.w=640;w.h=360;renderCanvas();renderSide();}));
brp2.appendChild(btn("1280×720","alt",()=>{w.w=1280;w.h=720;renderCanvas();renderSide();}));
ps.appendChild(brp2);
elPanel.appendChild(ps);
let st=fieldset("Style");
let rs=document.createElement("div");rs.className="row2";
let rad=document.createElement("div");rad.appendChild(label("Radius"));rad.appendChild(input("number",w.style.radius,v=>{w.style.radius=clamp(+v||0,0,200);renderCanvas();}));
let sh=document.createElement("div");sh.appendChild(label("Shadow"));sh.appendChild(checkbox(w.style.shadow,v=>{w.style.shadow=v;renderCanvas();}));
rs.appendChild(rad);rs.appendChild(sh);
st.appendChild(rs);
st.appendChild(label("Background"));st.appendChild(input("text",w.style.bg,v=>{w.style.bg=v;renderCanvas();}));
st.appendChild(label("Text Color"));st.appendChild(input("text",w.style.color,v=>{w.style.color=v;renderCanvas();}));
st.appendChild(label("Font px"));st.appendChild(input("number",w.style.font,v=>{w.style.font=clamp(+v||16,6,200);renderCanvas();}));
let cfg=fieldset("Content");
if(w.type==="text"){cfg.appendChild(label("Text"));cfg.appendChild(textarea(w.text,v=>{w.text=v;renderCanvas();}));}
if(w.type==="image"){cfg.appendChild(label("Image URL"));cfg.appendChild(label("Content size (stored)"));
let cr=document.createElement("div");cr.className="row2";
let cW=document.createElement("div");cW.appendChild(label("Content W"));cW.appendChild(input("number",w.contentW||3840,v=>{w.contentW=clamp(+v||3840,320,20000);}));
let cH=document.createElement("div");cH.appendChild(label("Content H"));cH.appendChild(input("number",w.contentH||2160,v=>{w.contentH=clamp(+v||2160,240,20000);}));
cr.appendChild(cW);cr.appendChild(cH);cfg.appendChild(cr);cfg.appendChild(input("text",w.src,v=>{w.src=v;renderCanvas();}));}
if(w.type==="url"){
cfg.appendChild(label("URL"));
cfg.appendChild(input("text",w.src,v=>{w.src=v;renderCanvas();}));
cfg.appendChild(label("Mode"));
cfg.appendChild(select(w.mode||"iframe",[{v:"iframe",t:"iframe"},{v:"fetch",t:"fetch (curl)"}],v=>{w.mode=v;renderCanvas();}));
cfg.appendChild(label("Content format"));
cfg.appendChild(select((w.contentW||3840)+"×"+(w.contentH||2160),[{v:"3840x2160",t:"4K 3840×2160"},{v:"1920x1080",t:"1080p 1920×1080"},{v:"1280x720",t:"720p 1280×720"},{v:"custom",t:"Custom"}],v=>{if(v==="3840x2160"){w.contentW=3840;w.contentH=2160;}else if(v==="1920x1080"){w.contentW=1920;w.contentH=1080;}else if(v==="1280x720"){w.contentW=1280;w.contentH=720;}renderCanvas();}));
let cr=document.createElement("div");cr.className="row2";
let cW=document.createElement("div");cW.appendChild(label("Content W"));cW.appendChild(input("number",w.contentW||3840,v=>{w.contentW=clamp(+v||3840,320,20000);renderCanvas();}));
let cH=document.createElement("div");cH.appendChild(label("Content H"));cH.appendChild(input("number",w.contentH||2160,v=>{w.contentH=clamp(+v||2160,240,20000);renderCanvas();}));
cr.appendChild(cW);cr.appendChild(cH);cfg.appendChild(cr);
}
if(w.type==="clock"){cfg.appendChild(label("Format (for index.php later)"));cfg.appendChild(input("text",w.format||"HH:mm:ss",v=>{w.format=v;renderCanvas();}));let s=document.createElement("div");s.className="small";s.textContent="index.php currently uses locale time; format is stored for future extension.";cfg.appendChild(s);}
if(w.type==="carousel"){
cfg.appendChild(label("Items"));let br=document.createElement("div");br.className="btnRow";
br.appendChild(btn("+ Item","",()=>{w.playlist=w.playlist||[];w.playlist.push({type:"image",src:"https://",duration:5,title:""});renderAll();}));
br.appendChild(btn("Clear","alt",()=>{w.playlist=[];renderAll();}));
cfg.appendChild(br);
(w.playlist||[]).forEach((it,i)=>{
let fi=document.createElement("fieldset");fi.style.borderStyle="dashed";let lg=document.createElement("legend");lg.textContent="Item "+(i+1);fi.appendChild(lg);
fi.appendChild(label("Type"));fi.appendChild(select(it.type||"image",[{v:"image",t:"image"},{v:"url",t:"url"}],v=>{it.type=v;renderCanvas();}));
fi.appendChild(label("Src"));fi.appendChild(input("text",it.src||"",v=>{it.src=v;renderCanvas();}));
fi.appendChild(label("Title (optional)"));fi.appendChild(input("text",it.title||"",v=>{it.title=v;}));
fi.appendChild(label("Duration seconds"));fi.appendChild(input("number",it.duration||5,v=>{it.duration=clamp(+v||1,1,3600);}));
let ar=document.createElement("div");ar.className="btnRow";
ar.appendChild(btn("Up","alt",()=>{if(i===0)return;let a=w.playlist[i-1];w.playlist[i-1]=w.playlist[i];w.playlist[i]=a;renderAll();}));
ar.appendChild(btn("Down","alt",()=>{if(i===w.playlist.length-1)return;let a=w.playlist[i+1];w.playlist[i+1]=w.playlist[i];w.playlist[i]=a;renderAll();}));
ar.appendChild(btn("Delete","danger",()=>{w.playlist.splice(i,1);renderAll();}));
fi.appendChild(ar);
cfg.appendChild(fi);
});
}
let acts=fieldset("Actions");
let br=document.createElement("div");br.className="btnRow";
br.appendChild(btn("Bring Front","alt",()=>{w.z=nextZ();renderCanvas();}));
br.appendChild(btn("Send Back","alt",()=>{w.z=1;renderCanvas();}));
br.appendChild(btn("Duplicate","alt",()=>{let n=deepClone(w);n.id=uid();n.x+=20;n.y+=20;n.z=nextZ();p.widgets.push(n);selectedIds=[n.id];renderAll();}));
br.appendChild(btn("Delete","danger",()=>{p.widgets=p.widgets.filter(x=>x.id!==w.id);selectedIds=[];renderAll();}));
acts.appendChild(br);
elPanel.appendChild(f);elPanel.appendChild(st);elPanel.appendChild(cfg);elPanel.appendChild(acts);
}
function moveRule(i,dir){let j=i+dir;if(j<0||j>=data.schedules.length)return;let a=data.schedules[i];data.schedules.splice(i,1);data.schedules.splice(j,0,a);renderSide();}
function renderAll(){sanitizeClient();renderPages();renderCanvas();renderSide();renderLeftWidgets();}
async function doSave(){
sanitizeClient();
markStatus("Saving...",true);
try{
let r=await fetch(location.href,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(data)});
let t=await r.text();
if(r.ok){markStatus("Saved",true);}else{markStatus("Save failed",false);}
}catch(e){markStatus("Save error",false);}
}
function downloadJson(){
let s=JSON.stringify(data,null,2);
let blob=new Blob([s],{type:"application/json"});
let a=document.createElement("a");a.href=URL.createObjectURL(blob);a.download="data.json";a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1000);
}
function importJsonPrompt(){
let s=prompt("Paste JSON to import:");
if(!s)return;
try{let j=JSON.parse(s);data=j;sanitizeClient();markStatus("Imported",true);renderAll();}
catch(e){markStatus("Import invalid",false);}
}
renderAll();
if(typeof applyViewScale==="function")applyViewScale();
</script>
</body>
</html>
