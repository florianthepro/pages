<?php declare(strict_types=1);

# Geteilte Instanz-Variablen defensiv einlesen (Muster wie launcher-v2).
$loockup_theme=(isset($loockup_theme)&&in_array($loockup_theme,['light','dark'],true))?$loockup_theme:'auto';
$loockup_title=(isset($loockup_title)&&$loockup_title!=='')?(string)$loockup_title:'Loockup DNS';
$loockup_icon=(isset($loockup_icon)&&is_string($loockup_icon)&&$loockup_icon!=='')?$loockup_icon:'https://raw.githubusercontent.com/florianthepro/pages/main/content/media/loockup/index.svg';

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

# ---------------------------------------------------------------------------
# Serverseite (das Original loest die Hosts serverseitig auf und zeigt je Host
# IP, Abfragezeit und HTTP-Status). Hier originalgetreu in PHP nachgebaut.
# Feste Hostliste wie im Original.
# ---------------------------------------------------------------------------
$loockup_hosts=['api.github.com','github.com','raw.githubusercontent.com'];

# Kurzer, fehlertoleranter HTTP-HEAD-Check. Liefert Statuscode oder null.
function loockup_http_status(string $host): ?int{
	$url='https://'.$host.'/';
	if(function_exists('curl_init')){
		$ch=curl_init($url);
		curl_setopt_array($ch,[
			CURLOPT_NOBODY=>true,
			CURLOPT_FOLLOWLOCATION=>true,
			CURLOPT_CONNECTTIMEOUT=>4,
			CURLOPT_TIMEOUT=>6,
			CURLOPT_SSL_VERIFYPEER=>false,
			CURLOPT_SSL_VERIFYHOST=>0,
			CURLOPT_USERAGENT=>'loockup-dns/1.0',
		]);
		curl_exec($ch);
		$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
		curl_close($ch);
		return $code>0?$code:null;
	}
	# Fallback ohne cURL: get_headers mit kurzem Timeout.
	$ctx=stream_context_create([
		'http'=>['method'=>'HEAD','timeout'=>6,'ignore_errors'=>true],
		'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false],
	]);
	$prev=error_reporting(0);
	$headers=@get_headers($url,false,$ctx);
	error_reporting($prev);
	if(is_array($headers)&&isset($headers[0])&&preg_match('#\s(\d{3})\s#',(string)$headers[0],$m)){
		return (int)$m[1];
	}
	return null;
}

$loockup_rows=[];
foreach($loockup_hosts as $host){
	$ip=@gethostbyname($host);       # A-Record; bei Fehler kommt der Host-String zurueck.
	$resolved=($ip!==''&&$ip!==$host);
	$status=loockup_http_status($host);
	$loockup_rows[]=[
		'host'=>$host,
		'id'=>md5($host),            # gleiche ID-Bildung wie im Original (md5 des Hostnamens)
		'ip'=>$resolved?$ip:null,
		'status'=>$status,
		'time'=>date('c'),           # ISO-8601 mit Offset, z.B. 2026-08-29T08:44:31-04:00
	];
}

# Footer-Hinweis / Debug-Ausgabe per ?debug=1.
$loockup_debug=(isset($_GET['debug'])&&$_GET['debug']==='1');

# data-theme nur bei fixem Theme setzen, bei 'auto' weglassen (folgt System).
$dt=$loockup_theme==='light'?' data-theme="light"':($loockup_theme==='dark'?' data-theme="dark"':'');
?>
<!DOCTYPE html>
<html lang="de"<?= $dt ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($loockup_title) ?></title>
<link rel="icon" href="<?= h($loockup_icon) ?>">
<link rel="apple-touch-icon" href="<?= h($loockup_icon) ?>">
<style>
  :root{
    --bg:#fff;--fg:#111;--sub:#666;--row-border:#eee;
    --ip-bg:#f7f7f7;--btn-bg:#f3f4f6;--btn-border:#e6e9ee;--err:#b91c1c;
  }
  @media(prefers-color-scheme:dark){
    :root:not([data-theme="light"]){
      --bg:#111;--fg:#eee;--sub:#9aa0a6;--row-border:#2a2a2a;
      --ip-bg:#1b1b1b;--btn-bg:#1f2937;--btn-border:#374151;--err:#f87171;
    }
  }
  :root[data-theme="dark"]{
    --bg:#111;--fg:#eee;--sub:#9aa0a6;--row-border:#2a2a2a;
    --ip-bg:#1b1b1b;--btn-bg:#1f2937;--btn-border:#374151;--err:#f87171;
  }
  html,body{margin:0;padding:18px;font-family:Inter,system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--fg)}
  .wrap{max-width:900px;margin:0 auto}
  h1{margin:0 0 6px 0;font-size:20px}
  p.sub{margin:0 0 14px 0;color:var(--sub)}
  .row{border:1px solid var(--row-border);padding:12px;border-radius:8px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center}
  .left{display:flex;flex-direction:column}
  .host{font-weight:600}
  .meta{color:var(--sub);font-size:13px;margin-top:6px}
  .ip{font-family:monospace;background:var(--ip-bg);padding:6px 8px;border-radius:6px;display:inline-block}
  .err{color:var(--err)}
  .btn{background:var(--btn-bg);border:1px solid var(--btn-border);padding:6px 8px;border-radius:6px;cursor:pointer;color:inherit}
  .small{font-size:13px;color:var(--sub)}
  pre.debug{background:#111;color:#fff;padding:8px;border-radius:6px;overflow:auto;max-height:260px}
  footer{margin-top:14px;color:var(--sub);font-size:13px}
  @media(max-width:640px){.row{flex-direction:column;align-items:flex-start;gap:8px}}
</style>
</head>
<body>
<div class="wrap">
<h1>DNS Loockup</h1>
<?php foreach($loockup_rows as $r): ?>
<div class="row">
<div class="left">
<div class="host"><?= h($r['host']) ?></div>
<div class="meta">Abfrage: <?= h($r['time']) ?> &mdash; HTTP: <?= $r['status']!==null?h((string)$r['status']):'&mdash;' ?></div>
</div>
<div style="text-align:right">
<?php if($r['ip']!==null): ?>
<div class="ip" id="ip-<?= h($r['id']) ?>"><?= h($r['ip']) ?></div>
<div style="margin-top:8px"><button class="btn" onclick="copyIp('<?= h($r['id']) ?>')">Kopieren</button></div>
<?php else: ?>
<div class="ip err">nicht aufl&ouml;sbar</div>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php if($loockup_debug): ?>
<pre class="debug"><?= h(print_r($loockup_rows,true)) ?></pre>
<?php endif; ?>
<footer>Debug: <code>?debug=1</code></footer>
</div>
<script>
function copyIp(id){
  const el = document.getElementById('ip-' + id);
  if(!el) return;
  const txt = el.textContent.trim();
  if(!txt) return;
  navigator.clipboard.writeText(txt).then(()=>{const old=el.textContent; el.textContent='kopiert'; setTimeout(()=>el.textContent=old,900)}).catch(()=>{});
}
</script>
</body>
</html>
