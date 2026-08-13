<?php
declare(strict_types=1);
header("Content-Type:text/html; charset=utf-8");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
$allow_private_fetch=false;
function deep_defaults($in,$def){if(!is_array($in))return $def;foreach($def as $k=>$v){if(!array_key_exists($k,$in)){$in[$k]=$v;continue;}if(is_array($v))$in[$k]=deep_defaults($in[$k],$v);}return $in;}
$default=["meta"=>["timezone"=>"Europe/Berlin","autosync_hours"=>6,"autosync_seconds"=>30,"globalRotationSpeed"=>1,"title"=>"","icon"=>"","favicon"=>"","themeColor"=>"","carouselIframeRefreshSeconds"=>0],"pages"=>[["id"=>"p1","name"=>"Page 1","thumb"=>"","settings"=>["w"=>3840,"h"=>2160],"background"=>["fade"=>1,"items"=>[]],"widgets"=>[]]],"schedules"=>[]];
function sanitize_data($d,$default){
$d=deep_defaults($d,$default);
if(!isset($d["meta"])||!is_array($d["meta"]))$d["meta"]=$default["meta"];
if(!is_array($d["pages"]))$d["pages"]=$default["pages"];
if(!is_array($d["schedules"]))$d["schedules"]=[];
$d["pages"]=array_values(array_filter($d["pages"],fn($p)=>is_array($p)&&isset($p["id"])&&isset($p["name"])));
if(!count($d["pages"]))$d["pages"]=$default["pages"];
foreach($d["pages"] as &$p){
$p=deep_defaults($p,$default["pages"][0]);
if(!is_array($p["settings"]))$p["settings"]=["w"=>3840,"h"=>2160];
if(!is_array($p["background"]))$p["background"]=["fade"=>1,"items"=>[]];
if(!is_array($p["background"]["items"]??null))$p["background"]["items"]=[];
if(!is_array($p["widgets"]))$p["widgets"]=[];
$p["widgets"]=array_values(array_filter($p["widgets"],fn($w)=>is_array($w)&&isset($w["id"])&&isset($w["type"])));
foreach($p["widgets"] as &$w){
$w=deep_defaults($w,["id"=>$w["id"]??("w".bin2hex(random_bytes(4))),"type"=>$w["type"]??"text","x"=>40,"y"=>40,"w"=>320,"h"=>180,"z"=>1,"locked"=>false,"hidden"=>false,"contentW"=>3840,"contentH"=>2160,"autoContent"=>false,"lockAspect"=>false,"style"=>["radius"=>0,"shadow"=>false,"bg"=>"","color"=>"","font"=>16],"src"=>"","mode"=>"iframe","text"=>"","format"=>"HH:mm:ss","playlist"=>[],"duration"=>5,"title"=>""]);
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
if(!isset($r["override"]["widgets"])||!(is_array($r["override"]["widgets"])||is_object($r["override"]["widgets"])))$r["override"]["widgets"]=[];
}
return $d;
}
function json_path(){ $p1=__DIR__."/data/data.json"; $p2=__DIR__."/data.json"; if(file_exists($p1))return $p1; return $p2; }
function safe_href($s){$s=trim((string)$s);if($s==="")return "";if(str_starts_with($s,"data:image/"))return $s;if(filter_var($s,FILTER_VALIDATE_URL)){ $u=parse_url($s);$sch=strtolower($u["scheme"]??"");return ($sch==="http"||$sch==="https")?$s:""; }if(preg_match('~^[a-zA-Z][a-zA-Z0-9+\-.]*:~',$s))return "";if(preg_match('~[\x00-\x1F\x7F]~',$s))return "";return $s;}
function checkHostSafe($host,$allow_private_fetch){$ips=@gethostbynamel($host);if(!$ips)return false;if($allow_private_fetch)return true;foreach($ips as $ip){if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;}return true;}
$path=json_path();
$data=$default;
if(file_exists($path)){ $j=json_decode((string)file_get_contents($path),true); if($j)$data=sanitize_data($j,$default); }
if(($_GET["action"]??"")==="getJson"){
$etag='"'.sha1(json_encode($data)).'"';
header("Content-Type:application/json; charset=utf-8");
header("Cache-Control:no-store");
header("ETag: ".$etag);
if(($_SERVER["HTTP_IF_NONE_MATCH"]??"")===$etag){http_response_code(304);exit;}
echo json_encode($data,JSON_UNESCAPED_UNICODE);
exit;
}
if(($_GET["action"]??"")==="fetchHtml"){
$url=trim($_GET["url"]??"");
if($url===""||!filter_var($url,FILTER_VALIDATE_URL)){http_response_code(400);exit;}
$u=parse_url($url);$scheme=strtolower($u["scheme"]??"");$host=$u["host"]??"";
if(($scheme!=="http"&&$scheme!=="https")||$host===""){http_response_code(400);exit;}
if(!checkHostSafe($host,$allow_private_fetch)){http_response_code(403);exit;}
$max=2*1024*1024;$buf="";
$ch=curl_init($url);
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>12,CURLOPT_USERAGENT=>"DashboardPlayer/1.0",CURLOPT_HTTPHEADER=>["Accept: text/html,application/xhtml+xml"],CURLOPT_WRITEFUNCTION=>function($ch,$data)use(&$buf,$max){$buf.=$data;return strlen($buf)>$max?0:strlen($data);}]);
$ok=curl_exec($ch);
$eff=curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
curl_close($ch);
if(!$ok||$code>=400){http_response_code(502);echo"fetch failed";exit;}
if($eff){$uu=parse_url($eff);$h=$uu["host"]??"";if($h!==""&&!checkHostSafe($h,$allow_private_fetch)){http_response_code(403);exit;}}
$path2=$u["path"]??"/";$port=isset($u["port"])?":".$u["port"]:"";
$base=$scheme."://".$host.$port.rtrim(str_replace("\\","/",dirname($path2)),"/")."/";
if(stripos($buf,"<base")===false){
$baseTag='<base href="'.htmlspecialchars($base,ENT_QUOTES|ENT_SUBSTITUTE,"UTF-8").'">';
if(preg_match('/<head[^>]*>/i',$buf,$m,PREG_OFFSET_CAPTURE)){$pos=$m[0][1]+strlen($m[0][0]);$buf=substr($buf,0,$pos).$baseTag.substr($buf,$pos);}else{$buf='<!doctype html><html><head><meta charset="utf-8">'.$baseTag.'</head><body>'.$buf.'</body></html>';}
}
header("Content-Type:text/html; charset=utf-8");
echo $buf;
exit;
}
$meta=$data["meta"]??[];
$theme=trim((string)($meta["themeColor"]??""));
$title0=(string)($data["pages"][0]["name"]??"");
$icon=safe_href(($meta["icon"]??($meta["favicon"]??""))?:($data["pages"][0]["thumb"]??""));
?>
<!doctype html>
<html>
<head>
<meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"><?php if($theme!==""){?><meta name=theme-color content="<?php echo htmlspecialchars($theme,ENT_QUOTES|ENT_SUBSTITUTE,"UTF-8"); ?>"><?php } ?><?php if(trim($title0)!==""){?><title><?php echo htmlspecialchars($title0,ENT_QUOTES|ENT_SUBSTITUTE,"UTF-8"); ?></title><?php } ?><?php if($icon!==""){?><link rel="icon" href="<?php echo htmlspecialchars($icon,ENT_QUOTES|ENT_SUBSTITUTE,"UTF-8"); ?>"><?php } ?>
<style>
html,body{margin:0;height:100%;background:#000;overflow:hidden;font-family:system-ui,Segoe UI,Arial}
#wrap{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:#000}
#scale{position:relative}
.stage{position:absolute;left:0;top:0;transform-origin:0 0;background:#111827}
.bgWrap{position:absolute;inset:0;overflow:hidden}
.bg{position:absolute;inset:0;background:#000 center/cover no-repeat;opacity:0;transition:opacity 1s}
.bg.on{opacity:1}
.layer{position:absolute;inset:0}
.w{position:absolute;box-sizing:border-box;overflow:hidden}
.w.hidden{display:none}
.w .inner{position:absolute;inset:0}
.w.text .inner{white-space:pre-wrap;padding:10px}
.w.clock .inner{display:flex;align-items:center;justify-content:center}
.w.image img{width:100%;height:100%;object-fit:cover;display:block}
.w.url .frameWrap{position:absolute;left:0;top:0;transform-origin:0 0}
.w.url iframe{border:0;display:block;background:#000}
.w.carousel .inner{position:absolute;inset:0;background:#000}
.w.carousel .slot{position:absolute;inset:0;opacity:0;pointer-events:none;transition:opacity .12s linear;background:#000}
.w.carousel .slot.on{opacity:1;pointer-events:auto}
.w.carousel img,.w.carousel iframe{width:100%;height:100%;border:0;display:block;background:#000}
#overlayStage{opacity:0;pointer-events:none;transition:opacity .25s}
#overlayStage.on{opacity:1}
</style>
</head>
<body>
<div id=wrap><div id=scale><div id=baseStage class=stage><div class=bgWrap><div id=baseBgA class=bg></div><div id=baseBgB class=bg></div></div><div id=baseLayer class=layer></div></div><div id=overlayStage class=stage><div class=bgWrap><div id=ovBgA class=bg></div><div id=ovBgB class=bg></div></div><div id=ovLayer class=layer></div></div></div></div><script>
let DATA=<?php echo json_encode($data,JSON_UNESCAPED_UNICODE); ?>;
const elScale=document.getElementById("scale");
const elBaseStage=document.getElementById("baseStage");
const elOverlayStage=document.getElementById("overlayStage");
const elBaseLayer=document.getElementById("baseLayer");
const elOvLayer=document.getElementById("ovLayer");
const baseBgA=document.getElementById("baseBgA");
const baseBgB=document.getElementById("baseBgB");
const ovBgA=document.getElementById("ovBgA");
const ovBgB=document.getElementById("ovBgB");
const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
const deepClone=o=>JSON.parse(JSON.stringify(o));
let stageW=3840,stageH=2160;
let scheduledIds=new Set();
let basePage=null;
let activeRuleByPage=new Map();
let overlayPages=[];
let overlaySig="";
let overlayIdx=0;
let nextOverlayAt=0;
let baseBgState={sig:"",i:0,nextAt:0,onA:true,pageId:""};
let ovBgState={sig:"",i:0,nextAt:0,onA:true,pageId:""};
let baseClockNodes=[];
let ovClockNodes=[];
let baseCars={};
let ovCars={};
let ovPageId="";
function tz(){return (DATA.meta&&DATA.meta.timezone)||"Europe/Berlin";}
function tzNow(){return new Date(new Date().toLocaleString("en-US",{timeZone:tz()}));}
function parseHM(s){s=String(s||"").trim();let m=s.match(/^(\d{1,2}):(\d{2})$/);if(!m)return null;let h=+m[1],mi=+m[2];if(h<0||h>23||mi<0||mi>59)return null;return h*60+mi;}
function inRange(f,t,m){if(f==null||t==null)return true;if(f===t)return true;return f<t?(m>=f&&m<t):(m>=f||m<t);}
function ruleActive(r,now){if(!r||!r.enabled)return false;let wd=now.getDay();wd=wd===0?7:wd;let wds=Array.isArray(r.weekdays)?r.weekdays:[];if(wds.length&&wds.indexOf(wd)===-1)return false;let mins=now.getHours()*60+now.getMinutes();return inRange(parseHM(r.from),parseHM(r.to),mins);}
function buildBasePage(){scheduledIds=new Set((DATA.schedules||[]).map(r=>r&&r.page).filter(Boolean));basePage=null;for(let p of (DATA.pages||[])){if(p&&p.id&&!scheduledIds.has(p.id)){basePage=p;break;}}if(!basePage)basePage=(DATA.pages||[])[0]||null;}
function computeOverlayPages(){activeRuleByPage=new Map();let now=tzNow();let list=[];for(let r of (DATA.schedules||[])){if(!r||!r.page)continue;if(ruleActive(r,now)){if(!activeRuleByPage.has(r.page))activeRuleByPage.set(r.page,r);let p=(DATA.pages||[]).find(x=>x&&x.id===r.page);if(p&&list.findIndex(pp=>pp.id===p.id)===-1)list.push(p);}}overlayPages=list;let sig=list.map(p=>p.id).join("|");if(sig!==overlaySig){overlaySig=sig;overlayIdx=0;nextOverlayAt=0;ovPageId="";}if(overlayIdx>=overlayPages.length)overlayIdx=0;}
function applyView(){let cw=stageW,ch=stageH;let s=Math.min(Math.max(100,innerWidth)/cw,Math.max(100,innerHeight)/ch);s=clamp(s,0.05,10);elScale.style.width=Math.round(cw*s)+"px";elScale.style.height=Math.round(ch*s)+"px";for(let st of [elBaseStage,elOverlayStage]){st.style.width=cw+"px";st.style.height=ch+"px";st.style.transform="scale("+s+")";}}
addEventListener("resize",applyView);
function proxyFetchUrl(u){return location.pathname+"?action=fetchHtml&url="+encodeURIComponent(u)+"&ts="+Date.now();}
function fmtTime(d,fmt){fmt=String(fmt||"HH:mm:ss");let parts=new Intl.DateTimeFormat("en-GB",{timeZone:tz(),hour:"2-digit",minute:"2-digit",second:"2-digit",year:"numeric",month:"2-digit",day:"2-digit"}).formatToParts(d).reduce((a,p)=>(a[p.type]=p.value,a),{});let HH=parts.hour||"00",mm=parts.minute||"00",ss=parts.second||"00",YYYY=parts.year||"0000",MM=parts.month||"00",DD=parts.day||"00";return fmt.replace(/YYYY/g,YYYY).replace(/MM/g,MM).replace(/DD/g,DD).replace(/HH/g,HH).replace(/mm/g,mm).replace(/ss/g,ss);}
function mapOverrides(ov){let m=new Map();if(!ov)return m;if(Array.isArray(ov)){for(let it of ov){if(it&&it.id)m.set(it.id,it);}return m;}if(typeof ov==="object"){for(let k in ov){let it=ov[k];if(it&&typeof it==="object"){if(!it.id)it.id=k;m.set(it.id,it);}}}return m;}
function mergeWidgetOverrides(w,ov){if(!ov)return w;let out=deepClone(w);for(let k in ov){if(k==="style"&&ov.style&&typeof ov.style==="object")out.style=Object.assign({},out.style||{},ov.style);else if(k!=="id")out[k]=ov[k];}return out;}
function effectivePage(p){let out=deepClone(p);let r=activeRuleByPage.get(p.id)||null;if(r&&r.override){let o=r.override||{};if(o.background&&typeof o.background==="object")out.background=deepClone(o.background);let wm=mapOverrides(o.widgets);out.widgets=(out.widgets||[]).map(w=>wm.has(w.id)?mergeWidgetOverrides(w,wm.get(w.id)):w);}return out;}
function bgSig(p){let items=(p.background&&Array.isArray(p.background.items))?p.background.items:[];let fade=clamp(+((p.background&&p.background.fade)||0),0,20);return items.map(it=>(it.src||"")+"|"+(+it.duration||0)).join("||")+"|f"+fade+"|p"+p.id;}
function bgSetup(p,a,b,state){let fade=clamp(+((p.background&&p.background.fade)||0),0,20);a.style.transitionDuration=fade+"s";b.style.transitionDuration=fade+"s";let sig=bgSig(p);if(state.sig!==sig){state.sig=sig;state.i=0;state.nextAt=0;state.onA=true;state.pageId=p.id;}}
function bgTick(p,a,b,state){let items=(p.background&&Array.isArray(p.background.items))?p.background.items:[];if(!items.length){a.classList.remove("on");b.classList.remove("on");return;}let now=Date.now();if(state.nextAt===0){let it=items[0]||{};let src=(it.src||"").trim();let show=state.onA?a:b,hide=state.onA?b:a;if(src)show.style.backgroundImage="url('"+src.replace(/'/g,"%27")+"')";show.classList.add("on");hide.classList.remove("on");state.nextAt=now+clamp(+it.duration||10,1,3600)*1000;return;}if(now<state.nextAt)return;state.i=(state.i+1)%items.length;state.onA=!state.onA;let it=items[state.i]||{};let src=(it.src||"").trim();let show=state.onA?a:b,hide=state.onA?b:a;if(src)show.style.backgroundImage="url('"+src.replace(/'/g,"%27")+"')";show.classList.add("on");hide.classList.remove("on");state.nextAt=now+clamp(+it.duration||10,1,3600)*1000;}
function styleWidget(el,w){let s=w.style||{};el.style.left=(w.x||0)+"px";el.style.top=(w.y||0)+"px";el.style.width=(w.w||100)+"px";el.style.height=(w.h||80)+"px";el.style.zIndex=(w.z||1);el.style.borderRadius=((+s.radius||0))+"px";el.style.boxShadow=s.shadow?"0 6px 18px rgba(0,0,0,.35)":"none";el.style.background=(s.bg||"transparent");el.style.color=(s.color||"#fff");el.style.fontSize=((+s.font||16))+"px";el.classList.toggle("hidden",!!w.hidden);}
function renderUrl(inner,w){let url=(w.src||"").trim();if(!url){inner.textContent="";return;}let mode=(w.mode||"iframe")==="fetch"?"fetch":"iframe";let cw=+w.contentW||stageW;let ch=+w.contentH||stageH;let wrap=document.createElement("div");wrap.className="frameWrap";wrap.style.width=cw+"px";wrap.style.height=ch+"px";let sc=Math.min((w.w||1)/cw,(w.h||1)/ch);sc=clamp(sc,0.01,10);wrap.style.transform="scale("+sc+")";let fr=document.createElement("iframe");fr.width=cw;fr.height=ch;fr.style.width=cw+"px";fr.style.height=ch+"px";fr.style.border="0";fr.style.background="#000";fr.loading="eager";fr.allow="fullscreen";if(mode==="fetch")fr.setAttribute("sandbox","allow-scripts allow-forms allow-popups allow-same-origin allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation");fr.src=(mode==="fetch")?proxyFetchUrl(url):url;wrap.appendChild(fr);inner.appendChild(wrap);}
function withBust(u){u=String(u||"");let s=u.includes("?")?"&":"?";return u+s+"_ts="+Date.now();}
function itemRefreshSeconds(it){let r=it&&((it.refresh??it.reload??it.refreshSeconds??it.refresh_seconds??0));r=+r||0;if(r>0)return r;let g=+((DATA.meta&&DATA.meta.carouselIframeRefreshSeconds)||0)||0;return g>0?g:0;}
function carouselEnsureNode(st,idx){if(st.nodes[idx])return st.nodes[idx];let it=st.list[idx]||{};let t=(it.type||"image");let node;if(t==="image"){node=document.createElement("img");node.src=String(it.src||"");}else{node=document.createElement("iframe");node.src=String(it.src||"");node.loading="eager";node.allow="fullscreen";node.style.border="0";node.style.background="#000";}node.className="slot";node.dataset.src0=String(it.src||"");node.dataset.isIframe=(t!=="image")?"1":"0";node.dataset.refresh=String(itemRefreshSeconds(it)||0);node.dataset.lastReload="0";st.stage.appendChild(node);st.nodes[idx]=node;return node;}
function carouselShow(st,idx){if(!st||!st.list||!st.list.length)return;idx=((idx%st.list.length)+st.list.length)%st.list.length;if(st.activeNode)st.activeNode.classList.remove("on");let n=carouselEnsureNode(st,idx);n.classList.add("on");try{n.focus&&n.focus();}catch(e){}st.activeNode=n;st.i=idx;}
function carouselDurationMs(st){let it=st.list[st.i]||{};let d=+(it.duration||5);d=clamp(d,1,3600);return d*1000;}
function tickActiveIframeRefresh(st){let n=st.activeNode;if(!n||n.dataset.isIframe!=="1")return;let r=+n.dataset.refresh||0;if(r<=0)return;let last=+n.dataset.lastReload||0;let now=Date.now();if(last===0){n.dataset.lastReload=String(now);return;}if(now-last<r*1000)return;n.dataset.lastReload=String(now);let src0=n.dataset.src0||n.src||"";n.src=withBust(src0);}
function renderStage(layerEl,page){layerEl.innerHTML="";let clocks=[];let cars={};for(let w of (page.widgets||[])){let el=document.createElement("div");el.className="w "+String(w.type||"");styleWidget(el,w);let inner=document.createElement("div");inner.className="inner";el.appendChild(inner);if(w.type==="text"){el.classList.add("text");inner.textContent=String(w.text||"");}else if(w.type==="image"){el.classList.add("image");let img=new Image();img.src=String(w.src||"");inner.appendChild(img);}else if(w.type==="clock"){el.classList.add("clock");inner.textContent=fmtTime(tzNow(),w.format||"HH:mm:ss");inner.dataset.fmt=String(w.format||"HH:mm:ss");clocks.push(inner);}else if(w.type==="url"){el.classList.add("url");renderUrl(inner,w);}else if(w.type==="carousel"){el.classList.add("carousel");inner.style.position="absolute";inner.style.inset="0";inner.style.background="#000";let list=Array.isArray(w.playlist)?w.playlist:[];if(!list.length){inner.textContent="";}else{let stage=document.createElement("div");stage.style.position="absolute";stage.style.inset="0";inner.appendChild(stage);cars[w.id]={i:0,nextAt:0,list:list,stage:stage,nodes:[],activeNode:null};carouselShow(cars[w.id],0);cars[w.id].nextAt=Date.now()+carouselDurationMs(cars[w.id]);}}layerEl.appendChild(el);}return {clocks,cars};}
function tickClocks(nodes){let n=tzNow();for(let el of nodes){let fmt=el.dataset.fmt||"HH:mm:ss";el.textContent=fmtTime(n,fmt);}}
function tickCars(cars){let now=Date.now();for(let id in cars){let st=cars[id];if(!st||!st.list||!st.list.length)continue;tickActiveIframeRefresh(st);if(st.nextAt===0){st.nextAt=now+carouselDurationMs(st);continue;}if(now<st.nextAt)continue;carouselShow(st,(st.i+1)%st.list.length);st.nextAt=now+carouselDurationMs(st);}}
function rotationMs(){let sp=+((DATA.meta&&DATA.meta.globalRotationSpeed)||1)||1;sp=clamp(sp,0.1,100);let base=10000;return clamp(base/sp,1000,3600000);}
function showOverlay(on){elOverlayStage.classList.toggle("on",!!on);}
function renderBase(){if(!basePage)return;let p=deepClone(basePage);stageW=+((p.settings&&p.settings.w)||3840)||3840;stageH=+((p.settings&&p.settings.h)||2160)||2160;applyView();bgSetup(p,baseBgA,baseBgB,baseBgState);bgTick(p,baseBgA,baseBgB,baseBgState);let r=renderStage(elBaseLayer,p);baseClockNodes=r.clocks;baseCars=r.cars;basePage=p;}
function renderOverlay(){if(!overlayPages.length){showOverlay(false);ovPageId="";ovClockNodes=[];ovCars={};return;}showOverlay(true);let p=effectivePage(overlayPages[overlayIdx]);stageW=+((p.settings&&p.settings.w)||3840)||3840;stageH=+((p.settings&&p.settings.h)||2160)||2160;applyView();bgSetup(p,ovBgA,ovBgB,ovBgState);bgTick(p,ovBgA,ovBgB,ovBgState);let r=renderStage(elOvLayer,p);ovClockNodes=r.clocks;ovCars=r.cars;ovPageId=p.id;document.title=String(p.name||"");}
function tick(){if(basePage){bgTick(basePage,baseBgA,baseBgB,baseBgState);tickClocks(baseClockNodes);tickCars(baseCars);}computeOverlayPages();if(!overlayPages.length){showOverlay(false);document.title=String((basePage&&basePage.name)||"");return;}if(ovPageId===""||ovPageId!==overlayPages[overlayIdx].id){nextOverlayAt=Date.now()+rotationMs();renderOverlay();}let p=effectivePage(overlayPages[overlayIdx]);bgTick(p,ovBgA,ovBgB,ovBgState);tickClocks(ovClockNodes);tickCars(ovCars);let now=Date.now();if(nextOverlayAt===0)nextOverlayAt=now+rotationMs();if(now>=nextOverlayAt){overlayIdx=(overlayIdx+1)%overlayPages.length;renderOverlay();nextOverlayAt=now+rotationMs();}}
async function reloadIfChanged(){try{let r=await fetch(location.pathname+"?action=getJson&ts="+Date.now(),{cache:"no-store"});if(!r.ok)return;let j=await r.json();if(JSON.stringify(j)===JSON.stringify(DATA))return;DATA=j;buildBasePage();computeOverlayPages();renderBase();renderOverlay();}catch(e){}}
function autosyncMs(){let s=+((DATA.meta&&DATA.meta.autosync_seconds)||0)||0;if(s>0)return clamp(s*1000,2000,3600000);let h=+((DATA.meta&&DATA.meta.autosync_hours)||6)||6;return clamp(h*3600*1000,30000,24*3600*1000);}
function start(){buildBasePage();renderBase();computeOverlayPages();renderOverlay();setInterval(tick,250);setInterval(reloadIfChanged,autosyncMs());}
start();
</script>
</body>
</html>
