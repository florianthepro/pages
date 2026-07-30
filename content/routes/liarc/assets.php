<?php
declare(strict_types=1);
if (!defined('LIARC_LIB')) {
    require function_exists('app_get_local_script')
        ? app_get_local_script(($liarc_repo ?? 'https://raw.githubusercontent.com/florianthepro/pages/main/content').'/routes/liarc/lib.php', isset($_GET['_refresh']) && $_GET['_refresh'] === '1', 300)
        : __DIR__.'/lib.php';
}
liarc_boot(get_defined_vars());

// liefert CSS/JS/Manifest/Icons same-origin aus (CSP bleibt 'self')
$f = (string)($_GET['f'] ?? '');
$map = [
    'css' => ['routes/liarc/app.css', 'text/css'],
    'js' => ['routes/liarc/app.js', 'application/javascript'],
    'manifest' => ['routes/liarc/manifest.webmanifest', 'application/manifest+json'],
];

if (isset($map[$f])) {
    [$rel, $mime] = $map[$f];
} elseif (preg_match('/^icon-([a-z]+)$/', $f, $m)) {
    $rel = 'media/liarc/'.$m[1].'.svg';
    $mime = 'image/svg+xml';
} else {
    http_response_code(404);
    exit;
}

$path = liarc_repo_file($rel);
if ($path === null || !is_readable($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: '.$mime.'; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');
readfile($path);
exit;