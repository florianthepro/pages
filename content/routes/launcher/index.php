<?php
declare(strict_types=1);

#Kacheln kommen ausschliesslich aus der YAML der Instanz - kein Editor, kein
#gespeicherter Zustand, der die YAML ueberdeckt. Geaendert wird nur die Instanz.
$launcher_theme = (isset($launcher_theme) && in_array($launcher_theme, ['light', 'dark'], true)) ? $launcher_theme : 'auto';
$launcher_title = (isset($launcher_title) && $launcher_title !== '') ? (string)$launcher_title : 'Launcher';

function launcher_pipe_item(string $s): array
{
    $p = array_map('trim', explode('|', $s));
    $item = ['title' => $p[0] ?? '', 'url' => $p[1] ?? '', 'icon' => $p[2] ?? ''];
    if ($item['url'] === '' && preg_match('#^[a-z][a-z0-9+.-]*://#i', $item['title'])) {
        $item['url'] = $item['title'];
        $item['title'] = '';
    }
    return $item;
}

function launcher_inline_item(string $s): array
{
    $item = ['title' => '', 'url' => '', 'icon' => ''];
    if (preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)\s*:\s*(?:"([^"]*)"|\'([^\']*)\'|([^,}]+))/u', $s, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            $k = strtolower($m[1]);
            $v = (isset($m[2]) && $m[2] !== '') ? $m[2] : ((isset($m[3]) && $m[3] !== '') ? $m[3] : trim($m[4] ?? ''));
            if (array_key_exists($k, $item)) $item[$k] = trim($v);
        }
    }
    return $item;
}

#Gruppe = Zeile die auf ":" endet, Kachel = "Name: URL" oder "Name: URL | Icon-URL".
#"- {title:.., url:.., icon:..}" und "- Name | URL | Icon" werden ebenfalls gelesen.
function launcher_parse_yaml(string $text): array
{
    $out = [];
    $group = 'general';
    foreach (preg_split("/\r\n|\n|\r/", $text) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (preg_match('/^([^:#\-][^:]*):\s*$/u', $line, $m)) {
            $group = trim($m[1]) !== '' ? trim($m[1]) : 'general';
            continue;
        }
        $item = null;
        if ($line[0] === '-') {
            $body = trim(substr($line, 1));
            if ($body === '') continue;
            $item = $body[0] === '{' ? launcher_inline_item($body) : launcher_pipe_item($body);
        } elseif (preg_match('/^(.+?):\s*(\S.*)$/u', $line, $m)) {
            $item = launcher_pipe_item(trim($m[1]) . ' | ' . trim($m[2]));
        }
        if (!$item || $item['url'] === '') continue;
        $out[$group][] = [
            'title' => $item['title'] !== '' ? $item['title'] : $item['url'],
            'url'   => $item['url'],
            'icon'  => $item['icon'],
        ];
    }
    return $out;
}

function launcher_site_root(string $url): string
{
    $p = parse_url($url);
    if (!$p || empty($p['host'])) return '';
    return ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
}

function launcher_abs_url(string $base, string $rel): string
{
    if ($rel === '') return '';
    if (preg_match('#^(https?:|data:)#i', $rel)) return $rel;
    $root = launcher_site_root($base);
    if ($root === '') return '';
    if ($rel[0] === '/') return $root . $rel;
    $path = parse_url($base, PHP_URL_PATH);
    $dir = is_string($path) ? preg_replace('#/[^/]*$#', '/', $path) : '/';
    return $root . ($dir !== '' ? $dir : '/') . $rel;
}

function launcher_initials(string $text): string
{
    $parts = preg_split('/[\s._\-\/]+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) $out .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
    return $out !== '' ? $out : '?';
}

function launcher_color(string $seed): string
{
    $h = 0;
    for ($i = 0, $n = strlen($seed); $i < $n; $i++) $h = (($h << 5) - $h + ord($seed[$i])) & 0x7FFFFFFF;
    return 'hsl(' . ($h % 360) . ' 52% 46%)';
}

#Letzter Rueckfall: Initialen als SVG, damit auch ohne erreichbares Icon eine Kachel steht.
function launcher_initials_icon(string $title, string $url): string
{
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128">'
        . '<rect width="128" height="128" rx="26" fill="' . launcher_color(launcher_site_root($url) ?: $url) . '"/>'
        . '<text x="64" y="66" fill="#ffffff" font-family="system-ui,-apple-system,Segoe UI,Roboto,Arial"'
        . ' font-size="52" font-weight="600" text-anchor="middle" dominant-baseline="central">'
        . htmlspecialchars(launcher_initials($title), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</text></svg>';
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}

#Das Icon holt der Browser selbst von der Zielseite. Damit bekommen auch interne
#Adressen ihr Icon, die der Server nicht erreicht - der Browser steht im selben Netz.
function launcher_icon_candidates(array $item): array
{
    $out = [];
    $own = launcher_abs_url($item['url'], $item['icon']);
    if ($own !== '') $out[] = $own;
    $root = launcher_site_root($item['url']);
    if ($root !== '') $out[] = $root . '/favicon.ico';
    $out[] = launcher_initials_icon($item['title'], $item['url']);
    return $out;
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$groups = isset($launcher_yaml) ? launcher_parse_yaml((string)$launcher_yaml) : [];
if (!$groups && isset($launcher_links) && is_array($launcher_links)) {
    foreach ($launcher_links as $l) {
        if (!is_array($l)) continue;
        $u = trim((string)($l['url'] ?? ''));
        if ($u === '') continue;
        $g = trim((string)($l['group'] ?? 'general')) ?: 'general';
        $groups[$g][] = [
            'title' => trim((string)($l['title'] ?? $u)) ?: $u,
            'url'   => $u,
            'icon'  => trim((string)($l['icon'] ?? '')),
        ];
    }
}

$htmlClass = $launcher_theme === 'dark' ? ' class="theme-dark"' : ($launcher_theme === 'light' ? ' class="theme-light"' : '');
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
    --bg:#f5f7fa;
    --panel:#ffffff;
    --text:#131922;
    --muted:#5d6875;
    --line:rgba(19,25,34,.12);
    --shadow:0 6px 22px rgba(16,24,40,.09);
    --shadow-hover:0 16px 38px rgba(16,24,40,.16);
    --accent:#0ea5a4;
    --tile:104px;
    --radius:22px;
  }
  @media (prefers-color-scheme: dark){
    :root:not(.theme-light){
      --bg:#0b1220;
      --panel:#111a2b;
      --text:#e6eef8;
      --muted:#9fb0c8;
      --line:rgba(230,238,248,.12);
      --shadow:0 10px 30px rgba(2,6,23,.55);
      --shadow-hover:0 20px 46px rgba(2,6,23,.7);
    }
  }
  :root.theme-dark{
    --bg:#0b1220;
    --panel:#111a2b;
    --text:#e6eef8;
    --muted:#9fb0c8;
    --line:rgba(230,238,248,.12);
    --shadow:0 10px 30px rgba(2,6,23,.55);
    --shadow-hover:0 20px 46px rgba(2,6,23,.7);
  }
  *{box-sizing:border-box}
  html,body{margin:0;min-height:100%}
  body{
    background:var(--bg);color:var(--text);
    font:15px/1.5 Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
    -webkit-font-smoothing:antialiased;
  }
  .wrap{max-width:1080px;margin:0 auto;padding:clamp(20px,5vw,56px) clamp(16px,4vw,32px) 64px}
  .top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:clamp(20px,4vw,36px)}
  h1{margin:0;font-size:clamp(20px,3vw,26px);font-weight:650;letter-spacing:-.01em}
  .theme-btn{
    background:var(--panel);color:var(--text);border:1px solid var(--line);border-radius:10px;
    padding:8px 12px;font:inherit;font-size:14px;cursor:pointer;box-shadow:var(--shadow);
  }
  .theme-btn:hover{border-color:var(--accent)}
  .group{margin-bottom:clamp(24px,4vw,40px)}
  .group h2{
    margin:0 0 14px;padding-bottom:8px;border-bottom:1px solid var(--line);
    font-size:12px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:var(--muted);
  }
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(112px,1fr));gap:clamp(14px,2.4vw,22px)}
  .tile{
    display:flex;flex-direction:column;align-items:center;gap:9px;
    text-decoration:none;color:inherit;padding:6px 2px;border-radius:16px;outline:none;
  }
  .tile:focus-visible{outline:2px solid var(--accent);outline-offset:3px}
  .icon{
    width:var(--tile);height:var(--tile);border-radius:var(--radius);
    background:var(--panel);border:1px solid var(--line);box-shadow:var(--shadow);
    display:flex;align-items:center;justify-content:center;overflow:hidden;
    transition:transform .16s ease,box-shadow .16s ease;
  }
  .tile:hover .icon,.tile:focus-visible .icon{transform:translateY(-4px);box-shadow:var(--shadow-hover)}
  .icon img{width:58%;height:58%;object-fit:contain;display:block}
  .icon img.fill{width:100%;height:100%;object-fit:cover;border-radius:var(--radius)}
  .label{
    font-size:13px;color:var(--muted);text-align:center;max-width:100%;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  }
  .empty{color:var(--muted);padding:40px 0;text-align:center}
  .empty code{font-size:13px}
  @media (max-width:480px){
    :root{--tile:84px;--radius:18px}
    .grid{grid-template-columns:repeat(auto-fill,minmax(92px,1fr))}
  }
</style>
</head>
<body>
<div class="wrap">
  <header class="top">
    <h1><?= h($launcher_title) ?></h1>
    <button id="themeBtn" class="theme-btn" type="button" aria-label="Hell/Dunkel umschalten">Theme</button>
  </header>

<?php if (!$groups): ?>
  <p class="empty">Keine Eintr&auml;ge. Kacheln in <code>$launcher_yaml</code> der Instanz eintragen.</p>
<?php else: ?>
<?php foreach ($groups as $name => $items): ?>
  <section class="group">
    <h2><?= h($name) ?></h2>
    <div class="grid">
<?php foreach ($items as $item):
        $cands = launcher_icon_candidates($item);
        $first = array_shift($cands);
?>
      <a class="tile" href="<?= h($item['url']) ?>" target="_blank" rel="noopener noreferrer">
        <span class="icon">
          <img src="<?= h($first) ?>" alt="" loading="lazy" decoding="async"
               data-fallback="<?= h(json_encode(array_values($cands), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>" />
        </span>
        <span class="label"><?= h($item['title']) ?></span>
      </a>
<?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
<?php endif; ?>
</div>
<script>
(function(){
  "use strict";

  // Icon-Kette: laedt ein Kandidat nicht, kommt der naechste dran.
  document.querySelectorAll('img[data-fallback]').forEach(function(img){
    var list;
    try { list = JSON.parse(img.getAttribute('data-fallback')) || []; } catch(e){ list = []; }
    var i = 0;
    img.addEventListener('error', function(){
      if (i < list.length) { img.src = list[i++]; }
      else { img.onerror = null; }
    });
    img.addEventListener('load', function(){
      // Initialen-SVG fuellt die Kachel ganz aus, echte Favicons bleiben mittig.
      if (img.src.indexOf('data:image/svg+xml') === 0) img.classList.add('fill');
      else img.classList.remove('fill');
    });
  });

  var KEY = 'launcher_theme';
  var root = document.documentElement;
  var btn = document.getElementById('themeBtn');

  function prefersDark(){
    try { return window.matchMedia('(prefers-color-scheme: dark)').matches; } catch(e){ return false; }
  }
  function isDark(){
    if (root.classList.contains('theme-dark')) return true;
    if (root.classList.contains('theme-light')) return false;
    return prefersDark();
  }
  function apply(mode){
    root.classList.remove('theme-light','theme-dark');
    if (mode === 'light' || mode === 'dark') root.classList.add('theme-' + mode);
    btn.textContent = isDark() ? 'Hell' : 'Dunkel';
  }

  // Vorgabe der Instanz steht schon im <html>; eine eigene Wahl des Nutzers geht vor.
  var instance = root.classList.contains('theme-dark') ? 'dark'
               : root.classList.contains('theme-light') ? 'light' : '';
  var saved = null;
  try { saved = localStorage.getItem(KEY); } catch(e){}
  apply(saved === 'light' || saved === 'dark' ? saved : instance);

  btn.addEventListener('click', function(){
    var next = isDark() ? 'light' : 'dark';
    apply(next);
    try { localStorage.setItem(KEY, next); } catch(e){}
  });
})();
</script>
</body>
</html>
