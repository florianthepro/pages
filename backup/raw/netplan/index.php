<?php
declare(strict_types=1);

const APP_TITLE = 'Netzwerk';
const SESSION_LIFETIME = 28800;
const LOGIN_MAX_FAILS = 5;
const LOGIN_LOCK_SECONDS = 60;

const DEVICE_TYPES = [
    'internet'   => 'Internet',
    'firewall'   => 'Firewall',
    'router'     => 'Router',
    'switch'     => 'Switch',
    'ap'         => 'Access Point',
    'server'     => 'Server',
    'hypervisor' => 'Hypervisor',
    'storage'    => 'Storage',
    'client'     => 'Client',
    'other'      => 'Sonstiges',
];

const SERVICES = [
    'HTTPS' => 443, 'HTTP' => 80, 'SSH' => 22, 'RDP' => 3389, 'DNS' => 53,
    'DHCP' => 67, 'SMTP' => 25, 'LDAP' => 389, 'SMB' => 445, 'NFS' => 2049,
    'iSCSI' => 3260, 'SQL' => 1433, 'VPN' => 500, 'NTP' => 123, 'SNMP' => 161,
    'Backup' => 0, 'Sonstiges' => 0,
];

if (PHP_VERSION_ID < 80100) {
    exit('PHP 8.1 oder neuer erforderlich, gefunden: ' . PHP_VERSION);
}

const FILE_GUARD = "<?php exit; ?>\n";

$dataDir     = __DIR__ . '/data';
$netFile     = $dataDir . '/network.php';
$authFile    = $dataDir . '/auth.php';
$throttleFile = $dataDir . '/throttle.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
]);
session_start();

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function js(mixed $v): string
{
    return (string)json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

function cut(string $s, int $len): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $len) : substr($s, 0, $len);
}

function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function ensure_data_dir(string $dir): bool
{
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return false;
    }
    $guard = $dir . '/.htaccess';
    if (!is_file($guard)) {
        @file_put_contents($guard, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    return is_writable($dir);
}

function read_json(string $file): ?array
{
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    if ($raw === false) {
        return null;
    }
    if (str_starts_with($raw, '<?php')) {
        $raw = substr($raw, (int)strpos($raw, "\n") + 1);
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function fail(string $message): never
{
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}

function throttle(string $file, string $client): array
{
    $state = read_json($file) ?? [];
    $now = time();
    $entries = [];
    foreach ($state['clients'] ?? [] as $key => $entry) {
        if (($entry['seen'] ?? 0) + 3600 > $now) {
            $entries[$key] = $entry;
        }
    }
    $mine = $entries[$client] ?? ['fails' => 0, 'until' => 0, 'seen' => $now];
    $recent = array_values(array_filter($state['recent'] ?? [], static fn($t) => $t + 60 > $now));
    $blocked = $mine['until'] > $now || count($recent) >= 20;
    return ['state' => ['clients' => $entries, 'recent' => $recent], 'mine' => $mine, 'blocked' => $blocked];
}

function throttle_fail(string $file, string $client): void
{
    $data = throttle($file, $client);
    $state = $data['state'];
    $mine = $data['mine'];
    $mine['fails']++;
    $mine['seen'] = time();
    if ($mine['fails'] >= LOGIN_MAX_FAILS) {
        $mine['until'] = time() + LOGIN_LOCK_SECONDS * (int)min(10, 2 ** intdiv($mine['fails'], LOGIN_MAX_FAILS) / 2);
    }
    $state['clients'][$client] = $mine;
    $state['recent'][] = time();
    write_json($file, $state);
}

function throttle_clear(string $file, string $client): void
{
    $data = throttle($file, $client);
    $state = $data['state'];
    unset($state['clients'][$client]);
    write_json($file, $state);
}

function write_json(string $file, array $data, bool $backup = false): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, FILE_GUARD . $json, LOCK_EX) === false) {
        return false;
    }
    @chmod($tmp, 0640);
    if ($backup && is_file($file)) {
        @copy($file, $file . '.bak');
    }
    return @rename($tmp, $file);
}

function revision(string $file): string
{
    clearstatcache(true, $file);
    return is_file($file) ? filemtime($file) . '.' . filesize($file) : 'empty';
}

function base32_encode(string $bin): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = '';
    $bits = 0;
    $value = 0;
    for ($i = 0, $n = strlen($bin); $i < $n; $i++) {
        $value = ($value << 8) | ord($bin[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $out .= $alphabet[($value >> $bits) & 31];
        }
    }
    if ($bits > 0) {
        $out .= $alphabet[($value << (5 - $bits)) & 31];
    }
    return $out;
}

function base32_decode(string $text): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $text = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $text) ?? '');
    $out = '';
    $bits = 0;
    $value = 0;
    for ($i = 0, $n = strlen($text); $i < $n; $i++) {
        $pos = strpos($alphabet, $text[$i]);
        if ($pos === false) {
            continue;
        }
        $value = ($value << 5) | $pos;
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $out .= chr(($value >> $bits) & 255);
        }
    }
    return $out;
}

function totp_code(string $secret, int $slice): string
{
    $hash = hash_hmac('sha1', pack('N', 0) . pack('N', $slice), base32_decode($secret), true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $value = ((ord($hash[$offset]) & 0x7F) << 24)
        | (ord($hash[$offset + 1]) << 16)
        | (ord($hash[$offset + 2]) << 8)
        | ord($hash[$offset + 3]);
    return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
}

function totp_match(string $secret, string $code, int $usedSlice = 0): ?int
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return null;
    }
    $slice = (int)floor(time() / 30);
    for ($drift = -1; $drift <= 1; $drift++) {
        $candidate = $slice + $drift;
        if ($candidate > $usedSlice && hash_equals(totp_code($secret, $candidate), $code)) {
            return $candidate;
        }
    }
    return null;
}

function qr_matrix(string $text): ?array
{
    $exp = [];
    $log = [];
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x & 0x100) {
            $x ^= 0x11d;
        }
    }
    $mul = static fn(int $a, int $b): int => ($a === 0 || $b === 0) ? 0 : $exp[($log[$a] + $log[$b]) % 255];

    $bytes = array_values(unpack('C*', $text) ?: []);
    $specs = [[21, 26, 7, 1, 0], [25, 44, 10, 1, 18], [29, 70, 15, 1, 22], [33, 100, 20, 1, 26], [37, 134, 26, 1, 30], [41, 172, 36, 2, 34]];
    $spec = null;
    foreach ($specs as [$size, $total, $ec, $blocks, $align]) {
        if (count($bytes) + 2 <= $total - $ec) {
            $spec = ['size' => $size, 'ec' => $ec, 'blocks' => $blocks, 'align' => $align, 'data' => $total - $ec];
            break;
        }
    }
    if ($spec === null) {
        return null;
    }

    $bits = [];
    $push = static function (int $value, int $len) use (&$bits): void {
        for ($i = $len - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    };
    $push(4, 4);
    $push(count($bytes), 8);
    foreach ($bytes as $byte) {
        $push($byte, 8);
    }
    $capacity = $spec['data'] * 8;
    for ($i = 0; $i < 4 && count($bits) < $capacity; $i++) {
        $bits[] = 0;
    }
    while (count($bits) % 8 !== 0) {
        $bits[] = 0;
    }
    $words = [];
    for ($i = 0, $n = count($bits); $i < $n; $i += 8) {
        $byte = 0;
        for ($j = 0; $j < 8; $j++) {
            $byte = ($byte << 1) | $bits[$i + $j];
        }
        $words[] = $byte;
    }
    $pad = [0xEC, 0x11];
    for ($i = 0; count($words) < $spec['data']; $i++) {
        $words[] = $pad[$i % 2];
    }

    $generator = [1];
    $ecPerBlock = intdiv($spec['ec'], $spec['blocks']);
    for ($i = 0; $i < $ecPerBlock; $i++) {
        $next = array_fill(0, count($generator) + 1, 0);
        foreach ($generator as $j => $g) {
            $next[$j] ^= $mul($g, $exp[$i]);
            $next[$j + 1] ^= $g;
        }
        $generator = $next;
    }
    $generator = array_reverse($generator);

    $perBlock = intdiv($spec['data'], $spec['blocks']);
    $dataBlocks = [];
    $ecBlocks = [];
    for ($b = 0; $b < $spec['blocks']; $b++) {
        $chunk = array_slice($words, $b * $perBlock, $perBlock);
        $remainder = array_merge($chunk, array_fill(0, $ecPerBlock, 0));
        for ($i = 0, $len = count($chunk); $i < $len; $i++) {
            $factor = $remainder[$i];
            if ($factor === 0) {
                continue;
            }
            foreach ($generator as $j => $g) {
                $remainder[$i + $j] ^= $mul($g, $factor);
            }
        }
        $dataBlocks[] = $chunk;
        $ecBlocks[] = array_slice($remainder, count($chunk));
    }
    $final = [];
    for ($i = 0; $i < $perBlock; $i++) {
        foreach ($dataBlocks as $block) {
            $final[] = $block[$i];
        }
    }
    for ($i = 0; $i < $ecPerBlock; $i++) {
        foreach ($ecBlocks as $block) {
            $final[] = $block[$i];
        }
    }

    $size = $spec['size'];
    $m = array_fill(0, $size, array_fill(0, $size, false));
    $used = array_fill(0, $size, array_fill(0, $size, false));
    $set = static function (int $r, int $c, bool $v) use (&$m, &$used, $size): void {
        if ($r < 0 || $c < 0 || $r >= $size || $c >= $size) {
            return;
        }
        $m[$r][$c] = $v;
        $used[$r][$c] = true;
    };
    $finder = static function (int $r0, int $c0) use ($set): void {
        for ($i = -1; $i <= 7; $i++) {
            for ($j = -1; $j <= 7; $j++) {
                $on = ($i >= 0 && $i <= 6 && ($j === 0 || $j === 6))
                    || ($j >= 0 && $j <= 6 && ($i === 0 || $i === 6))
                    || ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4);
                $set($r0 + $i, $c0 + $j, $on);
            }
        }
    };
    $finder(0, 0);
    $finder(0, $size - 7);
    $finder($size - 7, 0);
    if ($spec['align'] > 0) {
        $a = $spec['align'];
        for ($i = -2; $i <= 2; $i++) {
            for ($j = -2; $j <= 2; $j++) {
                $set($a + $i, $a + $j, max(abs($i), abs($j)) !== 1);
            }
        }
    }
    for ($i = 8; $i < $size - 8; $i++) {
        if (!$used[6][$i]) {
            $set(6, $i, $i % 2 === 0);
        }
        if (!$used[$i][6]) {
            $set($i, 6, $i % 2 === 0);
        }
    }
    $set($size - 8, 8, true);

    $format = [1, 1, 1, 0, 1, 1, 1, 1, 1, 0, 0, 0, 1, 0, 0];
    for ($i = 0; $i < 6; $i++) {
        $set(8, $i, $format[$i] === 1);
    }
    $set(8, 7, $format[6] === 1);
    $set(8, 8, $format[7] === 1);
    $set(7, 8, $format[8] === 1);
    for ($i = 9; $i < 15; $i++) {
        $set(14 - $i, 8, $format[$i] === 1);
    }
    for ($i = 0; $i < 7; $i++) {
        $set($size - 1 - $i, 8, $format[$i] === 1);
    }
    for ($i = 7; $i < 15; $i++) {
        $set(8, $size - 15 + $i, $format[$i] === 1);
    }

    $bitIndex = 0;
    $totalBits = count($final) * 8;
    $nextBit = static function () use (&$bitIndex, $final, $totalBits): int {
        if ($bitIndex >= $totalBits) {
            return 0;
        }
        $bit = ($final[$bitIndex >> 3] >> (7 - ($bitIndex & 7))) & 1;
        $bitIndex++;
        return $bit;
    };
    $col = $size - 1;
    $up = true;
    while ($col > 0) {
        if ($col === 6) {
            $col--;
        }
        for ($i = 0; $i < $size; $i++) {
            $r = $up ? $size - 1 - $i : $i;
            for ($k = 0; $k < 2; $k++) {
                $c = $col - $k;
                if ($used[$r][$c]) {
                    continue;
                }
                $bit = $nextBit();
                if (($r + $c) % 2 === 0) {
                    $bit ^= 1;
                }
                $m[$r][$c] = $bit === 1;
                $used[$r][$c] = true;
            }
        }
        $up = !$up;
        $col -= 2;
    }
    return $m;
}

function qr_svg(string $text, int $scale = 5, int $quiet = 4): string
{
    $m = qr_matrix($text);
    if ($m === null) {
        return '';
    }
    $n = count($m);
    $side = ($n + $quiet * 2) * $scale;
    $rects = '';
    for ($r = 0; $r < $n; $r++) {
        $c = 0;
        while ($c < $n) {
            if (!$m[$r][$c]) {
                $c++;
                continue;
            }
            $start = $c;
            while ($c < $n && $m[$r][$c]) {
                $c++;
            }
            $rects .= '<rect x="' . (($start + $quiet) * $scale) . '" y="' . (($r + $quiet) * $scale)
                . '" width="' . (($c - $start) * $scale) . '" height="' . $scale . '"/>';
        }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $side . '" height="' . $side . '" viewBox="0 0 ' . $side . ' ' . $side . '" shape-rendering="crispEdges">'
        . '<rect width="' . $side . '" height="' . $side . '" fill="#fff"/><g fill="#000">' . $rects . '</g></svg>';
}

function clean_network(array $input): array
{
    $devices = [];
    $known = [];
    foreach ($input['devices'] ?? [] as $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $id = trim((string)($raw['id'] ?? ''));
        if ($id === '' || strlen($id) > 40 || isset($known[$id])) {
            continue;
        }
        $type = (string)($raw['type'] ?? 'other');
        $device = [
            'id'    => $id,
            'name'  => cut(trim((string)($raw['name'] ?? '')), 64),
            'ip'    => cut(trim((string)($raw['ip'] ?? '')), 45),
            'type'  => isset(DEVICE_TYPES[$type]) ? $type : 'other',
            'role'  => cut(trim((string)($raw['role'] ?? '')), 64),
            'notes' => cut(trim((string)($raw['notes'] ?? '')), 1000),
            'hosts' => cut(trim((string)($raw['hosts'] ?? '')), 45),
        ];
        if ($device['name'] === '' && $device['ip'] === '') {
            continue;
        }
        if ($device['type'] === 'firewall') {
            $device['policy'] = ($raw['policy'] ?? '') === 'strict' ? 'strict' : 'open';
            $ports = [];
            foreach ((array)($raw['allow'] ?? []) as $port) {
                $port = (int)$port;
                if ($port > 0 && $port <= 65535 && !in_array($port, $ports, true)) {
                    $ports[] = $port;
                }
            }
            sort($ports);
            $device['allow'] = $ports;
        }
        $known[$id] = true;
        $devices[] = $device;
    }

    $links = [];
    $seen = [];
    foreach ($input['links'] ?? [] as $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $from = trim((string)($raw['from'] ?? ''));
        $to = trim((string)($raw['to'] ?? ''));
        if (!isset($known[$from], $known[$to]) || $from === $to) {
            continue;
        }
        $kind = ($raw['kind'] ?? '') === 'cable' ? 'cable' : 'service';
        $link = ['from' => $from, 'to' => $to, 'kind' => $kind];
        if ($kind === 'cable') {
            $link['vlans'] = cut(trim((string)($raw['vlans'] ?? '')), 40);
            $key = 'c|' . ($from < $to ? $from . $to : $to . $from);
        } else {
            $service = (string)($raw['service'] ?? '');
            $link['service'] = isset(SERVICES[$service]) ? $service : 'Sonstiges';
            $port = (int)($raw['port'] ?? 0);
            $link['port'] = ($port > 0 && $port <= 65535) ? $port : 0;
            $key = 's|' . $from . '|' . $to . '|' . $link['service'] . '|' . $link['port'];
        }
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $links[] = $link;
    }

    return ['devices' => $devices, 'links' => $links];
}

function page_head(string $title): void
{
    ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<title><?= h($title) ?></title>
<style>
:root {
    color-scheme: light dark;
    --blue: #007aff;
    --red: #ff3b30;
    --green: #34c759;
    --orange: #ff9500;
    --purple: #af52de;
    --teal: #30b0c7;
    --cyan: #32ade6;
    --indigo: #5856d6;
    --brown: #a2845e;
    --gray: #8e8e93;
    --bg: #f5f5f7;
    --canvas: #f0f0f3;
    --dots: rgba(60, 60, 67, 0.14);
    --card: #ffffff;
    --bar: rgba(250, 250, 252, 0.72);
    --label: #1d1d1f;
    --label2: rgba(60, 60, 67, 0.6);
    --label3: rgba(60, 60, 67, 0.32);
    --separator: rgba(60, 60, 67, 0.16);
    --fill: rgba(120, 120, 128, 0.12);
    --fill2: rgba(120, 120, 128, 0.2);
    --group: rgba(255, 255, 255, 0.55);
    --shadow: 0 12px 32px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.06);
}

@media (prefers-color-scheme: dark) {
    :root {
        --blue: #0a84ff;
        --red: #ff453a;
        --green: #30d158;
        --orange: #ff9f0a;
        --purple: #bf5af2;
        --teal: #40c8e0;
        --cyan: #64d2ff;
        --indigo: #5e5ce6;
        --brown: #ac8e68;
        --gray: #98989d;
        --bg: #1c1c1e;
        --canvas: #161618;
        --dots: rgba(235, 235, 245, 0.1);
        --card: #2c2c2e;
        --bar: rgba(38, 38, 40, 0.72);
        --label: #f5f5f7;
        --label2: rgba(235, 235, 245, 0.6);
        --label3: rgba(235, 235, 245, 0.3);
        --separator: rgba(84, 84, 88, 0.6);
        --fill: rgba(120, 120, 128, 0.24);
        --fill2: rgba(120, 120, 128, 0.36);
        --group: rgba(255, 255, 255, 0.04);
        --shadow: 0 12px 32px rgba(0, 0, 0, 0.5), 0 1px 2px rgba(0, 0, 0, 0.4);
    }
}

* { box-sizing: border-box; }

body {
    margin: 0;
    font: 13px/1.45 -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, sans-serif;
    color: var(--label);
    background: var(--bg);
    -webkit-font-smoothing: antialiased;
    accent-color: var(--blue);
}

h1 { font-size: 22px; font-weight: 600; letter-spacing: -0.02em; margin: 0 0 6px; }
h2 { font-size: 15px; font-weight: 600; margin: 0 0 16px; }
p { margin: 0 0 18px; color: var(--label2); }

button {
    font: inherit;
    font-weight: 500;
    height: 28px;
    padding: 0 12px;
    border: none;
    border-radius: 7px;
    background: var(--fill);
    color: var(--label);
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s ease, transform 0.1s ease;
}
button:hover { background: var(--fill2); }
button:active { transform: scale(0.97); }
button.primary { background: var(--blue); color: #fff; }
button.primary:hover { background: var(--blue); filter: brightness(1.08); }
button.plain { background: none; color: var(--label2); padding: 0 8px; }
button.plain:hover { background: var(--fill); color: var(--label); }
button.danger { color: var(--red); }
button.danger:hover { background: rgba(255, 59, 48, 0.14); }
button:disabled { opacity: 0.4; cursor: default; }
button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {
    outline: 4px solid color-mix(in srgb, var(--blue) 35%, transparent);
    outline-offset: -1px;
}

input, select, textarea {
    font: inherit;
    width: 100%;
    height: 28px;
    padding: 0 9px;
    border: 1px solid var(--separator);
    border-radius: 7px;
    background: var(--card);
    color: var(--label);
}
textarea { height: auto; padding: 6px 9px; resize: vertical; }
input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 3.5px color-mix(in srgb, var(--blue) 25%, transparent);
}
select { appearance: none; padding-right: 26px; background-image: linear-gradient(45deg, transparent 50%, var(--label2) 50%), linear-gradient(135deg, var(--label2) 50%, transparent 50%); background-position: calc(100% - 14px) 12px, calc(100% - 9px) 12px; background-size: 5px 5px, 5px 5px; background-repeat: no-repeat; }
label { display: block; font-size: 11px; font-weight: 500; color: var(--label2); }
label input, label select, label textarea { margin-top: 5px; }

.gate {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 24px;
}
.gate-card {
    width: 100%;
    max-width: 320px;
    padding: 32px 28px;
    text-align: center;
    background: var(--card);
    border-radius: 18px;
    box-shadow: var(--shadow);
}
.gate-card input[type=text] {
    height: 44px;
    text-align: center;
    letter-spacing: 8px;
    font-size: 19px;
    font-variant-numeric: tabular-nums;
    margin-bottom: 14px;
    background: var(--fill);
    border-color: transparent;
}
.gate-card button {
    width: 100%;
    height: 40px;
    border-radius: 980px;
    font-size: 14px;
}
.qr { margin: 6px 0 14px; }
.qr svg {
    width: 190px;
    height: 190px;
    border-radius: 12px;
    background: #fff;
    padding: 6px;
}
.secret {
    display: block;
    font: 500 12px/1.6 ui-monospace, "SF Mono", Menlo, monospace;
    letter-spacing: 1.5px;
    color: var(--label2);
    margin-bottom: 20px;
    word-break: break-all;
}
.alert {
    background: color-mix(in srgb, var(--red) 12%, transparent);
    color: var(--red);
    border-radius: 8px;
    padding: 7px 10px;
    margin-bottom: 12px;
    font-size: 12px;
}

.bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 14px;
    background: var(--bar);
    backdrop-filter: saturate(180%) blur(20px);
    -webkit-backdrop-filter: saturate(180%) blur(20px);
    border-bottom: 1px solid var(--separator);
    position: relative;
    z-index: 5;
}
.brand { font-weight: 600; letter-spacing: -0.01em; margin-right: 4px; }
.bar #search {
    width: 200px;
    height: 26px;
    background: var(--fill);
    border-color: transparent;
    border-radius: 7px;
}
.spacer { flex: 1; }
.stat { color: var(--label2); font-size: 12px; white-space: nowrap; font-variant-numeric: tabular-nums; }
.inline { display: inline-flex; }

.segmented {
    display: inline-flex;
    padding: 2px;
    gap: 2px;
    background: var(--fill);
    border-radius: 8px;
}
.segmented button {
    height: 22px;
    padding: 0 10px;
    font-size: 12px;
    font-weight: 500;
    border-radius: 6px;
    background: none;
    color: var(--label2);
}
.segmented button:hover { background: none; color: var(--label); }
.segmented button.on {
    background: var(--card);
    color: var(--label);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
}

#stage {
    position: relative;
    height: calc(100vh - 47px);
    overflow: hidden;
    background: var(--canvas);
    background-image: radial-gradient(var(--dots) 1px, transparent 1px);
    background-size: 20px 20px;
}
#map { width: 100%; height: 100%; display: block; cursor: grab; }
#map.grabbing { cursor: grabbing; }

.node { cursor: pointer; }
.node rect.body {
    fill: var(--card);
    stroke: var(--separator);
    filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.06));
}
.node.selected rect.body { stroke: var(--blue); stroke-width: 2; }
.node text.name { font-size: 12px; font-weight: 600; fill: var(--label); letter-spacing: -0.01em; }
.node text.sub { font-size: 10.5px; fill: var(--label2); }
.group rect { fill: var(--group); stroke: var(--separator); stroke-dasharray: 5 5; }
.group text { font-size: 11px; font-weight: 600; fill: var(--label2); }

.link { fill: none; stroke-linecap: round; }
.link.cable { stroke: var(--label2); stroke-width: 2.5; }
.link.service { stroke: var(--blue); stroke-width: 1.6; }
.link.blocked { stroke: var(--label3); stroke-width: 1.6; stroke-dasharray: 5 4; }
.link.host { stroke: var(--label3); stroke-width: 1.4; stroke-dasharray: 1 4; }
.link.strong { stroke-width: 3.2; }
.link.hit { stroke: transparent; stroke-width: 16; cursor: pointer; }
.link-label { font-size: 10px; font-weight: 500; fill: var(--label2); }
.arrow { fill: var(--blue); }
.dim { opacity: 0.26; }

.t-internet { color: var(--teal); }
.t-firewall { color: var(--red); }
.t-router { color: var(--blue); }
.t-switch { color: var(--cyan); }
.t-ap { color: var(--purple); }
.t-server { color: var(--green); }
.t-hypervisor { color: var(--orange); }
.t-storage { color: var(--brown); }
.t-client { color: var(--indigo); }
.t-other { color: var(--gray); }

.empty {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: var(--label2);
}
.empty p { margin: 0; font-size: 15px; }

.panel {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 288px;
    max-height: calc(100% - 24px);
    overflow: auto;
    background: var(--bar);
    backdrop-filter: saturate(180%) blur(24px);
    -webkit-backdrop-filter: saturate(180%) blur(24px);
    border: 1px solid var(--separator);
    border-radius: 14px;
    box-shadow: var(--shadow);
    padding: 14px 16px 16px;
}
.panel h3 { margin: 0; font-size: 15px; font-weight: 600; letter-spacing: -0.01em; }
.panel .sub { color: var(--label2); font-size: 12px; margin-bottom: 14px; }
.panel dl { display: grid; grid-template-columns: auto 1fr; gap: 7px 12px; margin: 0 0 14px; font-size: 12px; }
.panel dt { color: var(--label2); }
.panel dd { margin: 0; text-align: right; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
.panel h4 {
    margin: 14px 0 4px;
    font-size: 11px;
    font-weight: 600;
    color: var(--label2);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.panel .row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 0;
    border-top: 1px solid var(--separator);
    font-size: 12px;
}
.panel .row .grow { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.panel .row .tag { color: var(--label2); font-size: 11px; }
.panel .row button { height: 22px; padding: 0 6px; }
.panel .note {
    background: var(--fill);
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 12px;
    white-space: pre-wrap;
}
.panel .buttons { display: flex; gap: 6px; margin-top: 14px; }
.panel .buttons button { flex: 1; }
.panel .close { position: absolute; top: 12px; right: 12px; height: 22px; padding: 0 7px; }

.hint, .toast {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 8px 7px 14px;
    border-radius: 980px;
    font-size: 12px;
    font-weight: 500;
    box-shadow: var(--shadow);
    backdrop-filter: saturate(180%) blur(20px);
    -webkit-backdrop-filter: saturate(180%) blur(20px);
}
.hint { top: 14px; background: var(--bar); border: 1px solid var(--separator); }
.toast { bottom: 42px; background: rgba(28, 28, 30, 0.85); color: #fff; padding: 8px 16px; }
.toast.error { background: color-mix(in srgb, var(--red) 92%, black); }

.legend {
    position: absolute;
    left: 14px;
    bottom: 12px;
    display: flex;
    gap: 14px;
    font-size: 11px;
    color: var(--label2);
}
.legend span { display: flex; align-items: center; gap: 6px; }
.swatch { width: 16px; display: inline-block; }
.swatch.cable { border-top: 2.5px solid var(--label2); }
.swatch.service { border-top: 1.6px solid var(--blue); }
.swatch.blocked { border-top: 1.6px dashed var(--label3); }
.swatch.hosts { border-top: 1.4px dotted var(--label3); }

.overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.28);
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 20;
}
.sheet {
    width: 100%;
    max-width: 480px;
    padding: 20px;
    background: var(--card);
    border-radius: 16px;
    box-shadow: var(--shadow);
    animation: sheet 0.18s ease-out;
}
.sheet.narrow { max-width: 340px; }
@keyframes sheet {
    from { opacity: 0; transform: scale(0.97) translateY(6px); }
    to { opacity: 1; transform: none; }
}
@media (prefers-reduced-motion: reduce) {
    .sheet { animation: none; }
    button { transition: none; }
}
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.grid .wide { grid-column: 1 / -1; }
.actions { display: flex; gap: 8px; margin-top: 20px; }
.muted { color: var(--label2); font-size: 12px; margin-bottom: 14px; }

.menu {
    position: fixed;
    z-index: 30;
    min-width: 190px;
    padding: 5px;
    background: var(--bar);
    backdrop-filter: saturate(180%) blur(24px);
    -webkit-backdrop-filter: saturate(180%) blur(24px);
    border: 1px solid var(--separator);
    border-radius: 10px;
    box-shadow: var(--shadow);
}
.menu button {
    display: block;
    width: 100%;
    height: 26px;
    text-align: left;
    background: none;
    border-radius: 6px;
    font-weight: 400;
    padding: 0 9px;
}
.menu button:hover { background: var(--blue); color: #fff; }
.menu button.danger:hover { background: var(--red); color: #fff; }
.menu hr { border: none; border-top: 1px solid var(--separator); margin: 5px 8px; }

[hidden] { display: none !important; }

@media (max-width: 720px) {
    .bar { flex-wrap: wrap; }
    .bar #search { width: 100%; order: 5; }
    .panel { left: 12px; right: 12px; width: auto; }
    .grid { grid-template-columns: 1fr; }
    .legend { display: none; }
}
</style>
</head>
<?php
}

$dataReady = ensure_data_dir($dataDir);
$auth = read_json($authFile);
if ($auth === null) {
    fail('Anmeldedaten sind nicht lesbar. Bitte Rechte von data/auth.php pruefen.');
}
$client = (string)($_SERVER['REMOTE_ADDR'] ?? 'lokal');
$hasAuth = isset($auth['secret']) && is_string($auth['secret']) && $auth['secret'] !== '';
$loggedIn = $hasAuth
    && !empty($_SESSION['nw_auth'])
    && ($_SESSION['nw_secret'] ?? '') === $auth['secret']
    && (int)($_SESSION['nw_time'] ?? 0) + SESSION_LIFETIME > time();

if (empty($_SESSION['nw_csrf'])) {
    $_SESSION['nw_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string)$_SESSION['nw_csrf'];
$action = (string)($_GET['action'] ?? '');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$postedCsrf = (string)($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? ''));
$csrfOk = hash_equals($csrf, $postedCsrf);

if ($action === 'save') {
    if (!$loggedIn || !$csrfOk) {
        json_out(['error' => 'Nicht angemeldet.'], 403);
    }
    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload) || !is_array($payload['network'] ?? null)) {
        json_out(['error' => 'Ungültige Daten.'], 400);
    }
    if (read_json($netFile) === null) {
        json_out(['error' => 'Vorhandene Daten sind nicht lesbar. Nicht gespeichert.'], 409);
    }
    if ((string)($payload['rev'] ?? '') !== revision($netFile)) {
        json_out(['error' => 'Datei wurde zwischenzeitlich geändert. Seite neu laden.'], 409);
    }
    if (!write_json($netFile, clean_network($payload['network']), true)) {
        json_out(['error' => 'Schreiben fehlgeschlagen.'], 500);
    }
    json_out(['rev' => revision($netFile)]);
}

if ($action === 'logout' && $isPost && $csrfOk) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . strtok((string)$_SERVER['REQUEST_URI'], '?'));
    exit;
}

$error = '';

if (!$hasAuth) {
    if (!$dataReady) {
        http_response_code(500);
        exit('Datenverzeichnis nicht beschreibbar: ' . h($dataDir));
    }
    if (empty($_SESSION['nw_setup'])) {
        session_regenerate_id(true);
        $_SESSION['nw_setup'] = base32_encode(random_bytes(20));
        $_SESSION['nw_csrf'] = bin2hex(random_bytes(16));
        $csrf = (string)$_SESSION['nw_csrf'];
        $csrfOk = false;
    }
    $secret = (string)$_SESSION['nw_setup'];
    if ($isPost && $action === 'setup') {
        $slice = totp_match($secret, (string)($_POST['code'] ?? ''));
        if (!$csrfOk) {
            $error = 'Sitzung abgelaufen. Seite neu laden.';
        } elseif ($slice === null) {
            $error = 'Code stimmt nicht.';
        } elseif (!write_json($authFile, ['secret' => $secret, 'created' => date('c'), 'used' => $slice])) {
            $error = 'Konnte Schlüssel nicht speichern.';
        } else {
            unset($_SESSION['nw_setup']);
            session_regenerate_id(true);
            $_SESSION['nw_auth'] = true;
            $_SESSION['nw_secret'] = $secret;
            $_SESSION['nw_time'] = time();
            header('Location: ' . strtok((string)$_SERVER['REQUEST_URI'], '?'));
            exit;
        }
    }
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'server');
    $uri = 'otpauth://totp/' . rawurlencode(APP_TITLE . ':' . $host) . '?secret=' . rawurlencode($secret) . '&issuer=' . rawurlencode(APP_TITLE);
    ?>
<?php page_head(APP_TITLE . ' · Einrichtung'); ?>
<body class="gate">
<form class="gate-card" method="post" action="?action=setup">
    <h1>Einrichtung</h1>
    <p>Code scannen, dann den 6-stelligen Code eingeben.</p>
    <div class="qr"><?= qr_svg($uri) ?></div>
    <code class="secret"><?= h(chunk_split($secret, 4, ' ')) ?></code>
    <?php if ($error !== ''): ?><div class="alert"><?= h($error) ?></div><?php endif; ?>
    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" autofocus required>
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <button type="submit" class="primary">Bestätigen</button>
</form>
</body>
</html>
    <?php
    exit;
}

if (!$loggedIn) {
    if ($isPost && $action === 'login') {
        usleep(300000);
        $slice = null;
        if (throttle($throttleFile, $client)['blocked']) {
            $error = 'Zu viele Versuche. Kurz warten.';
        } elseif (!$csrfOk) {
            $error = 'Sitzung abgelaufen. Seite neu laden.';
        } elseif (($slice = totp_match((string)$auth['secret'], (string)($_POST['code'] ?? ''), (int)($auth['used'] ?? 0))) !== null) {
            $auth['used'] = $slice;
            write_json($authFile, $auth);
            throttle_clear($throttleFile, $client);
            session_regenerate_id(true);
            $_SESSION['nw_auth'] = true;
            $_SESSION['nw_secret'] = (string)$auth['secret'];
            $_SESSION['nw_time'] = time();
            header('Location: ' . strtok((string)$_SERVER['REQUEST_URI'], '?'));
            exit;
        } else {
            throttle_fail($throttleFile, $client);
            $error = 'Code stimmt nicht.';
        }
    }
    ?>
<?php page_head(APP_TITLE . ' · Anmeldung'); ?>
<body class="gate">
<form class="gate-card" method="post" action="?action=login">
    <h1><?= APP_TITLE ?></h1>
    <p>Code aus der Authenticator-App.</p>
    <?php if ($error !== ''): ?><div class="alert"><?= h($error) ?></div><?php endif; ?>
    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" autofocus required>
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <button type="submit" class="primary">Anmelden</button>
</form>
</body>
</html>
    <?php
    exit;
}

$stored = read_json($netFile);
if ($stored === null) {
    fail('Netzwerkdatei ist beschaedigt oder nicht lesbar: ' . $netFile . "\nSicherung liegt unter " . $netFile . '.bak');
}
$network = clean_network($stored);
$rev = revision($netFile);
?>
<?php page_head(APP_TITLE); ?>
<body>
<header class="bar">
    <span class="brand"><?= APP_TITLE ?></span>
    <input type="search" id="search" placeholder="Suchen" autocomplete="off">
    <div class="segmented" id="linkFilter">
        <button type="button" data-value="all" class="on">Alle</button>
        <button type="button" data-value="cable">Physisch</button>
        <button type="button" data-value="service">Dienste</button>
    </div>
    <span class="spacer"></span>
    <span class="stat" id="stat"></span>
    <button type="button" id="addDevice">+ Gerät</button>
    <button type="button" id="save" class="primary">Sichern</button>
    <form method="post" action="?action=logout" class="inline">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="plain">Abmelden</button>
    </form>
</header>

<main id="stage">
    <svg id="map"><g id="viewport"></g></svg>
    <div id="empty" class="empty" hidden>
        <p id="emptyText">Noch keine Geräte.</p>
        <button type="button" class="primary" id="addFirst">Gerät anlegen</button>
    </div>
    <aside id="panel" class="panel" hidden></aside>
    <div id="hint" class="hint" hidden><span id="hintText"></span><button type="button" id="hintCancel" class="quiet">Abbrechen</button></div>
    <div id="toast" class="toast" hidden></div>
    <footer class="legend">
        <span><i class="swatch cable"></i>Kabel</span>
        <span><i class="swatch service"></i>Dienst</span>
        <span><i class="swatch blocked"></i>blockiert</span>
        <span><i class="swatch hosts"></i>hostet</span>
    </footer>
</main>

<div id="dialog" class="overlay" hidden>
    <form class="sheet" id="deviceForm">
        <h2 id="dialogTitle">Gerät</h2>
        <div class="grid">
            <label>Name<input type="text" id="fName" maxlength="64" autocomplete="off"></label>
            <label>IP-Adresse<input type="text" id="fIp" maxlength="45" autocomplete="off" placeholder="10.0.1.10"></label>
            <label>Typ<select id="fType"></select></label>
            <label>Rolle<input type="text" id="fRole" maxlength="64" autocomplete="off" placeholder="z. B. Game-Server"></label>
            <label class="wide">Hostet IP-Bereich<input type="text" id="fHosts" maxlength="45" autocomplete="off" placeholder="10.0.2.0/24"></label>
            <label class="wide fw">Regel<select id="fPolicy"><option value="open">Alles erlauben</option><option value="strict">Nur Freigaben erlauben</option></select></label>
            <label class="wide fw">Freigegebene Ports<input type="text" id="fAllow" maxlength="120" autocomplete="off" placeholder="443, 53"></label>
            <label class="wide">Notiz<textarea id="fNotes" maxlength="1000" rows="2"></textarea></label>
        </div>
        <div class="actions">
            <button type="button" class="danger" id="deleteDevice">Löschen</button>
            <span class="spacer"></span>
            <button type="button" id="cancelDevice">Abbrechen</button>
            <button type="submit" class="primary">Sichern</button>
        </div>
    </form>
</div>

<div id="linkDialog" class="overlay" hidden>
    <form class="sheet narrow" id="linkForm">
        <h2>Dienst</h2>
        <p class="muted" id="linkInfo"></p>
        <div class="grid">
            <label>Dienst<select id="lService"></select></label>
            <label>Port<input type="number" id="lPort" min="1" max="65535" placeholder="443"></label>
        </div>
        <div class="actions">
            <span class="spacer"></span>
            <button type="button" id="cancelLink">Abbrechen</button>
            <button type="submit" class="primary">Verbinden</button>
        </div>
    </form>
</div>

<div id="menu" class="menu" hidden></div>

<script>
const TYPES = <?= js(DEVICE_TYPES) ?>;
const SERVICES = <?= js(SERVICES) ?>;
const CSRF = <?= js($csrf) ?>;
let net = <?= js($network) ?>;
let rev = <?= js($rev) ?>;
</script>
<script>
(() => {
'use strict';

const NODE_W = 180, NODE_H = 48, CELL_X = 200, CELL_Y = 66;
const BOX_PAD = 14, BOX_HEAD = 22, BOX_GAP = 28, ROW_GAP = 38, MARGIN = 28;
const NS = 'http://www.w3.org/2000/svg';

const RANK = {
    internet: 0, firewall: 1, router: 2, switch: 3,
    ap: 4, hypervisor: 4, storage: 4, server: 5, client: 6, other: 6,
};

const ICONS = {
    internet: 'M12 3.5a8.5 8.5 0 110 17 8.5 8.5 0 010-17M3.6 9.5h16.8M3.6 14.5h16.8M12 3.5c-3 3-3 14 0 17M12 3.5c3 3 3 14 0 17',
    firewall: 'M3.5 6.5h17v11h-17zM3.5 12h17M9 6.5v5.5M16 6.5v5.5M6 12v5.5M12.5 12v5.5M19 12v5.5',
    router: 'M3.5 13.5h17v6h-17zM7 16.5h10M12 4v6M9 7l3-3 3 3',
    switch: 'M3.5 8.5h17v7h-17zM7 15.5v3M12 15.5v3M17 15.5v3M7 5.5v3M12 5.5v3M17 5.5v3',
    ap: 'M12 19.5v.01M8.6 15.4a5 5 0 016.8 0M5.4 12.2a9.5 9.5 0 0113.2 0M12 19.5v-3',
    server: 'M4 4.5h16v6H4zM4 13.5h16v6H4zM7.5 7.5v.01M7.5 16.5v.01M11 7.5h6M11 16.5h6',
    hypervisor: 'M4 4.5h6.5v6.5H4zM13.5 4.5H20v6.5h-6.5zM4 13.5h6.5V20H4zM13.5 13.5H20V20h-6.5z',
    storage: 'M4 6.8c0-1.6 3.6-2.8 8-2.8s8 1.2 8 2.8-3.6 2.8-8 2.8-8-1.2-8-2.8zM4 6.8v10.4c0 1.6 3.6 2.8 8 2.8s8-1.2 8-2.8V6.8M20 12c0 1.6-3.6 2.8-8 2.8S4 13.6 4 12',
    client: 'M3.5 4.5h17v11h-17zM9 19.5h6M12 15.5v4',
    other: 'M12 4.5a7.5 7.5 0 110 15 7.5 7.5 0 010-15M12 11.4v.01',
};

const $ = id => document.getElementById(id);
const el = (tag, attrs = {}, text) => {
    const node = document.createElementNS(NS, tag);
    for (const key in attrs) node.setAttribute(key, attrs[key]);
    if (text !== undefined) node.textContent = text;
    return node;
};
const html = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
};

let selected = null;
let connecting = null;
let dirty = false;
let search = '';
let linkFilter = 'all';
let places = {};
let boxes = [];
let editing = null;
let pendingLink = null;
const view = { x: 0, y: 0, k: 1 };

const byId = id => net.devices.find(d => d.id === id);
const clip = (text, max) => text.length > max ? text.slice(0, max - 1) + '…' : text;
const label = d => d.name || d.ip || 'Gerät';
const typeKey = d => TYPES[d.type] ? d.type : 'other';
const typeLabel = d => TYPES[typeKey(d)];
const portOf = link => link.port || SERVICES[link.service] || 0;

function newId() {
    return 'd' + Math.random().toString(16).slice(2, 10);
}

function ipToInt(ip) {
    const parts = String(ip).split('.');
    if (parts.length !== 4) return null;
    let value = 0;
    for (const part of parts) {
        if (!/^\d{1,3}$/.test(part)) return null;
        const n = Number(part);
        if (n > 255) return null;
        value = value * 256 + n;
    }
    return value;
}

function inCidr(ip, cidr) {
    const [base, bitsRaw] = String(cidr).split('/');
    const bits = Number(bitsRaw);
    const a = ipToInt(base), b = ipToInt(ip);
    if (a === null || b === null || !Number.isInteger(bits) || bits < 0 || bits > 32) return false;
    if (bits === 0) return true;
    const mask = (0xffffffff << (32 - bits)) >>> 0;
    return ((a & mask) >>> 0) === ((b & mask) >>> 0);
}

function subnetOf(device) {
    if (ipToInt(device.ip) === null) return 'Ohne IP';
    const p = device.ip.split('.');
    return `${p[0]}.${p[1]}.${p[2]}.0/24`;
}

function matches(device) {
    if (!search) return true;
    const hay = [device.name, device.ip, typeLabel(device), device.role, device.notes, subnetOf(device)]
        .join(' ').toLowerCase();
    return search.split(/\s+/).every(term => hay.includes(term));
}

function hostRelations() {
    const cabled = new Set();
    net.links.filter(l => l.kind === 'cable').forEach(l => { cabled.add(l.from); cabled.add(l.to); });
    const out = [];
    for (const host of net.devices) {
        if (!host.hosts || !host.hosts.includes('/')) continue;
        for (const guest of net.devices) {
            if (guest.id === host.id || cabled.has(guest.id)) continue;
            if (inCidr(guest.ip, host.hosts)) out.push({ host: host.id, guest: guest.id });
        }
    }
    return out;
}

function cableGraph() {
    const graph = {};
    const add = (a, b) => {
        (graph[a] = graph[a] || []).push(b);
        (graph[b] = graph[b] || []).push(a);
    };
    net.links.filter(l => l.kind === 'cable').forEach(l => add(l.from, l.to));
    hostRelations().forEach(r => add(r.host, r.guest));
    return graph;
}

function shortestPath(graph, from, to) {
    if (from === to) return [from];
    const previous = { [from]: null };
    const queue = [from];
    while (queue.length) {
        const current = queue.shift();
        if (current === to) break;
        for (const next of graph[current] || []) {
            if (!(next in previous)) {
                previous[next] = current;
                queue.push(next);
            }
        }
    }
    if (!(to in previous)) return null;
    const path = [];
    for (let at = to; at !== null; at = previous[at]) path.push(at);
    return path.reverse();
}

function blockingFirewall(link, graph) {
    if (link.kind !== 'service') return null;
    const port = portOf(link);
    if (!port) return null;
    const path = shortestPath(graph, link.from, link.to);
    if (!path) return null;
    for (const id of path.slice(1, -1)) {
        const device = byId(id);
        if (device && device.type === 'firewall' && device.policy === 'strict' && !(device.allow || []).includes(port)) {
            return device;
        }
    }
    return null;
}

function computeLayout(width) {
    const rows = new Map();
    for (const device of net.devices.filter(matches)) {
        const rank = RANK[device.type] ?? 6;
        if (!rows.has(rank)) rows.set(rank, new Map());
        const clusters = rows.get(rank);
        const key = subnetOf(device);
        if (!clusters.has(key)) clusters.set(key, []);
        clusters.get(key).push(device);
    }

    places = {};
    boxes = [];
    let y = MARGIN;

    for (const rank of [...rows.keys()].sort((a, b) => a - b)) {
        const clusters = [...rows.get(rank).entries()].sort((a, b) => {
            if (a[0] === 'Ohne IP') return 1;
            if (b[0] === 'Ohne IP') return -1;
            return (ipToInt(a[0].split('/')[0]) || 0) - (ipToInt(b[0].split('/')[0]) || 0);
        });

        const measured = clusters.map(([key, members]) => {
            members.sort((a, b) => label(a).localeCompare(label(b), 'de', { numeric: true }));
            const cols = Math.min(4, members.length);
            const lines = Math.ceil(members.length / cols);
            return {
                key, members, cols,
                w: cols * CELL_X - (CELL_X - NODE_W) + BOX_PAD * 2,
                h: lines * CELL_Y - (CELL_Y - NODE_H) + BOX_PAD * 2 + BOX_HEAD,
            };
        });

        const totalWidth = measured.reduce((sum, c) => sum + c.w, 0) + BOX_GAP * (measured.length - 1);
        let x = Math.max(MARGIN, (width - totalWidth) / 2);
        let rowHeight = 0;

        for (const cluster of measured) {
            boxes.push({ key: cluster.key, x, y, w: cluster.w, h: cluster.h });
            cluster.members.forEach((device, index) => {
                places[device.id] = {
                    x: x + BOX_PAD + (index % cluster.cols) * CELL_X,
                    y: y + BOX_HEAD + BOX_PAD + Math.floor(index / cluster.cols) * CELL_Y,
                };
            });
            x += cluster.w + BOX_GAP;
            rowHeight = Math.max(rowHeight, cluster.h);
        }
        y += rowHeight + ROW_GAP;
    }
}

function center(id) {
    const place = places[id];
    return place ? { x: place.x + NODE_W / 2, y: place.y + NODE_H / 2 } : null;
}

function borderPoint(from, to) {
    const dx = to.x - from.x, dy = to.y - from.y;
    if (dx === 0 && dy === 0) return from;
    const halfW = NODE_W / 2 + 3, halfH = NODE_H / 2 + 3;
    const scale = Math.min(
        dx === 0 ? Infinity : halfW / Math.abs(dx),
        dy === 0 ? Infinity : halfH / Math.abs(dy)
    );
    return { x: from.x + dx * scale, y: from.y + dy * scale };
}

function relatedTo(id) {
    const set = new Set([id]);
    net.links.forEach(l => {
        if (l.from === id) set.add(l.to);
        if (l.to === id) set.add(l.from);
    });
    hostRelations().forEach(r => {
        if (r.host === id) set.add(r.guest);
        if (r.guest === id) set.add(r.host);
    });
    return set;
}

function render() {
    const map = $('map');
    const viewport = $('viewport');
    viewport.textContent = '';
    computeLayout(map.clientWidth || 1200);

    const graph = cableGraph();
    const highlight = selected && selected.type === 'device' ? relatedTo(selected.id) : null;
    const groupLayer = el('g'), linkLayer = el('g'), nodeLayer = el('g');
    viewport.append(groupLayer, linkLayer, nodeLayer);

    for (const box of boxes) {
        const group = el('g', { class: 'group' });
        group.append(
            el('rect', { x: box.x, y: box.y, width: box.w, height: box.h, rx: 10 }),
            el('text', { x: box.x + 12, y: box.y + 17 }, box.key)
        );
        groupLayer.append(group);
    }

    for (const relation of hostRelations()) {
        const a = center(relation.host), b = center(relation.guest);
        if (!a || !b) continue;
        const dim = highlight && !(highlight.has(relation.host) && highlight.has(relation.guest));
        const start = borderPoint(a, b), end = borderPoint(b, a);
        linkLayer.append(el('line', {
            x1: start.x, y1: start.y, x2: end.x, y2: end.y,
            class: 'link host' + (dim ? ' dim' : ''),
        }));
    }

    const drawable = net.links
        .map((link, index) => ({ link, index }))
        .filter(({ link }) => (linkFilter === 'all' || link.kind === linkFilter) && center(link.from) && center(link.to));
    const pairIndex = {};
    for (const { index, link } of drawable) {
        const key = [link.from, link.to].sort().join('|');
        (pairIndex[key] = pairIndex[key] || []).push(index);
    }

    drawable.forEach(({ link, index }) => {
        const a = center(link.from), b = center(link.to);
        const siblings = pairIndex[[link.from, link.to].sort().join('|')];
        const flip = link.from < link.to ? 1 : -1;
        const offset = (siblings.indexOf(index) - (siblings.length - 1) / 2) * 20 * flip;
        const start = borderPoint(a, b), end = borderPoint(b, a);
        const dx = end.x - start.x, dy = end.y - start.y;
        const length = Math.hypot(dx, dy) || 1;
        const cx = (start.x + end.x) / 2 - (dy / length) * offset;
        const cy = (start.y + end.y) / 2 + (dx / length) * offset;
        const d = `M ${start.x} ${start.y} Q ${cx} ${cy} ${end.x} ${end.y}`;

        const blocked = link.kind === 'service' ? blockingFirewall(link, graph) : null;
        const dim = highlight && !(highlight.has(link.from) && highlight.has(link.to));
        const isSelected = selected && selected.type === 'link' && selected.index === index;

        const kind = link.kind === 'cable' ? 'cable' : (blocked ? 'blocked' : 'service');
        const path = el('path', {
            d,
            class: `link ${kind}${dim ? ' dim' : ''}${isSelected ? ' strong' : ''}`,
        });
        if (kind === 'service') path.setAttribute('marker-end', 'url(#arrow)');

        const hit = el('path', { d, class: 'link hit' });
        hit.addEventListener('click', event => { event.stopPropagation(); select({ type: 'link', index }); });
        hit.addEventListener('contextmenu', event => {
            event.preventDefault();
            event.stopPropagation();
            openMenu(event, [
                { text: 'Verbindung löschen', danger: true, run: () => removeLink(index) },
            ]);
        });
        linkLayer.append(path, hit);

        const text = !highlight || dim ? '' : (link.kind === 'cable' ? (link.vlans ? 'VLAN ' + link.vlans : '') : link.service);
        if (text) {
            const t = 0.32, u = 1 - t;
            linkLayer.append(el('text', {
                x: u * u * start.x + 2 * u * t * cx + t * t * end.x - (dy / length) * 8,
                y: u * u * start.y + 2 * u * t * cy + t * t * end.y + (dx / length) * 8,
                'text-anchor': 'middle',
                class: 'link-label',
            }, text));
        }
    });

    for (const device of net.devices) {
        const place = places[device.id];
        if (!place) continue;
        const dim = highlight && !highlight.has(device.id);
        const isSelected = selected && selected.type === 'device' && selected.id === device.id;
        const node = el('g', {
            class: 'node' + (isSelected ? ' selected' : '') + (dim ? ' dim' : ''),
            transform: `translate(${place.x},${place.y})`,
        });
        node.append(el('rect', { class: 'body', x: 0, y: 0, width: NODE_W, height: NODE_H, rx: 8 }));
        node.append(el('path', {
            d: ICONS[typeKey(device)],
            class: 't-' + typeKey(device),
            transform: 'translate(11,12) scale(0.95)',
            fill: 'none',
            stroke: 'currentColor',
            'stroke-width': 1.6,
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round',
        }));
        const detail = [device.ip, device.role].filter(Boolean).join(' · ') || typeLabel(device);
        node.append(el('text', { class: 'name', x: 44, y: 21 }, clip(label(device), 20)));
        node.append(el('text', { class: 'sub', x: 44, y: 35 }, clip(detail, 26)));
        node.append(el('title', {}, [label(device), device.ip, device.role, typeLabel(device)].filter(Boolean).join(' · ')));
        node.addEventListener('click', event => {
            event.stopPropagation();
            if (connecting) finishConnect(device.id); else select({ type: 'device', id: device.id });
        });
        node.addEventListener('contextmenu', event => {
            event.preventDefault();
            event.stopPropagation();
            openMenu(event, [
                { text: 'Bearbeiten', run: () => openDevice(device.id) },
                { text: 'Kabel ziehen', run: () => startConnect(device.id, 'cable') },
                { text: 'Dienst verbinden', run: () => startConnect(device.id, 'service') },
                { separator: true },
                { text: 'Gerät löschen', danger: true, run: () => removeDevice(device.id) },
            ]);
        });
        nodeLayer.append(node);
    }

    applyTransform();
    const nothingShown = !Object.keys(places).length;
    $('empty').hidden = !nothingShown;
    $('emptyText').textContent = net.devices.length ? 'Keine Treffer.' : 'Noch keine Geräte.';
    $('addFirst').hidden = net.devices.length > 0;
    $('stat').textContent = `${net.devices.length} Geräte · ${net.links.length} Verbindungen`;
}

function applyTransform() {
    $('viewport').setAttribute('transform', `translate(${view.x},${view.y}) scale(${view.k})`);
}

function fitView() {
    const map = $('map');
    if (!boxes.length) {
        view.x = view.y = 0;
        view.k = 1;
        return applyTransform();
    }
    const minX = Math.min(...boxes.map(b => b.x));
    const minY = Math.min(...boxes.map(b => b.y));
    const maxX = Math.max(...boxes.map(b => b.x + b.w));
    const maxY = Math.max(...boxes.map(b => b.y + b.h));
    const pad = 32;
    const scale = Math.min(1, (map.clientWidth - pad * 2) / (maxX - minX), (map.clientHeight - pad * 2) / (maxY - minY));
    view.k = Math.max(0.3, scale);
    view.x = (map.clientWidth - (maxX - minX) * view.k) / 2 - minX * view.k;
    view.y = (map.clientHeight - (maxY - minY) * view.k) / 2 - minY * view.k;
    applyTransform();
}

function select(target) {
    selected = target;
    render();
    renderPanel();
}

function clearSelection() {
    if (!selected) return;
    selected = null;
    render();
    renderPanel();
}

function renderPanel() {
    const panel = $('panel');
    panel.textContent = '';
    if (!selected) {
        panel.hidden = true;
        return;
    }
    panel.hidden = false;
    const close = html('button', 'quiet close', '×');
    close.addEventListener('click', clearSelection);
    panel.append(close);

    if (selected.type === 'link') {
        const link = net.links[selected.index];
        if (!link) return clearSelection();
        const from = byId(link.from), to = byId(link.to);
        panel.append(html('h3', null, link.kind === 'cable' ? 'Kabel' : link.service));
        panel.append(html('div', 'sub', `${label(from)} → ${label(to)}`));
        const list = html('dl');
        const add = (key, value) => { list.append(html('dt', null, key), html('dd', null, value)); };
        if (link.kind === 'cable') {
            if (link.vlans) add('VLANs', link.vlans);
        } else {
            add('Port', String(portOf(link) || '–'));
            const blocked = blockingFirewall(link, cableGraph());
            add('Status', blocked ? 'blockiert durch ' + label(blocked) : 'erlaubt');
        }
        panel.append(list);
        const remove = html('button', 'danger', 'Verbindung löschen');
        remove.addEventListener('click', () => removeLink(selected.index));
        const buttons = html('div', 'buttons');
        buttons.append(remove);
        panel.append(buttons);
        return;
    }

    const device = byId(selected.id);
    if (!device) return clearSelection();
    panel.append(html('h3', null, label(device)));
    panel.append(html('div', 'sub', [device.role, typeLabel(device)].filter(Boolean).join(' · ')));

    const list = html('dl');
    const add = (key, value) => { list.append(html('dt', null, key), html('dd', null, value)); };
    if (device.ip) add('IP', device.ip);
    add('Netz', subnetOf(device));
    if (device.hosts) add('Hostet', device.hosts);
    if (device.type === 'firewall') {
        add('Regel', device.policy === 'strict' ? 'Nur Freigaben' : 'Alles erlaubt');
        if (device.policy === 'strict') add('Freigaben', (device.allow || []).join(', ') || 'keine');
    }
    const host = hostRelations().find(r => r.guest === device.id);
    if (host) add('Läuft auf', label(byId(host.host)));
    panel.append(list);

    if (device.notes) {
        panel.append(html('h4', null, 'Notiz'));
        panel.append(html('div', 'note', device.notes));
    }

    const connections = net.links
        .map((link, index) => ({ link, index }))
        .filter(({ link }) => link.from === device.id || link.to === device.id);
    if (connections.length) {
        const graph = cableGraph();
        panel.append(html('h4', null, 'Verbindungen'));
        for (const { link, index } of connections) {
            const other = byId(link.from === device.id ? link.to : link.from);
            const blocked = blockingFirewall(link, graph);
            const row = html('div', 'row');
            const text = html('span', 'grow', (link.kind === 'cable' ? 'Kabel' : link.service) + ' · ' + label(other));
            text.style.cursor = 'pointer';
            text.addEventListener('click', () => select({ type: 'link', index }));
            const remove = html('button', 'quiet', '×');
            remove.addEventListener('click', () => removeLink(index));
            row.append(text, html('span', 'tag', blocked ? 'blockiert' : (link.from === device.id ? '→' : '←')), remove);
            panel.append(row);
        }
    }

    const buttons = html('div', 'buttons');
    const edit = html('button', null, 'Bearbeiten');
    edit.addEventListener('click', () => openDevice(device.id));
    const cable = html('button', null, 'Kabel');
    cable.addEventListener('click', () => startConnect(device.id, 'cable'));
    const service = html('button', null, 'Dienst');
    service.addEventListener('click', () => startConnect(device.id, 'service'));
    buttons.append(edit, cable, service);
    panel.append(buttons);
}

function setDirty(value) {
    dirty = value;
    $('save').textContent = value ? 'Speichern *' : 'Speichern';
}

function changed() {
    setDirty(true);
    render();
    renderPanel();
}

function openDevice(id) {
    const device = id ? byId(id) : null;
    editing = device ? device.id : null;
    $('dialogTitle').textContent = device ? 'Gerät bearbeiten' : 'Neues Gerät';
    $('fName').value = device ? device.name : '';
    $('fIp').value = device ? device.ip : '';
    $('fType').value = device ? device.type : 'client';
    $('fRole').value = device ? device.role : '';
    $('fHosts').value = device ? device.hosts || '' : '';
    $('fNotes').value = device ? device.notes : '';
    $('fPolicy').value = device && device.policy === 'strict' ? 'strict' : 'open';
    $('fAllow').value = device && device.allow ? device.allow.join(', ') : '';
    $('deleteDevice').hidden = !device;
    updateFirewallFields();
    $('dialog').hidden = false;
    $('fName').focus();
}

function updateFirewallFields() {
    const show = $('fType').value === 'firewall';
    document.querySelectorAll('.fw').forEach(node => { node.hidden = !show; });
}

function submitDevice(event) {
    event.preventDefault();
    const name = $('fName').value.trim();
    const ip = $('fIp').value.trim();
    if (!name && !ip) {
        $('fName').focus();
        return;
    }
    const device = editing ? byId(editing) : { id: newId() };
    device.name = name;
    device.ip = ip;
    device.type = $('fType').value;
    device.role = $('fRole').value.trim();
    device.hosts = $('fHosts').value.trim();
    device.notes = $('fNotes').value.trim();
    if (device.type === 'firewall') {
        device.policy = $('fPolicy').value;
        device.allow = $('fAllow').value.split(/[,;\s]+/)
            .map(Number)
            .filter(port => Number.isInteger(port) && port > 0 && port <= 65535);
    } else {
        delete device.policy;
        delete device.allow;
    }
    if (!editing) {
        net.devices.push(device);
        selected = { type: 'device', id: device.id };
    }
    $('dialog').hidden = true;
    editing = null;
    changed();
}

function removeDevice(id) {
    const device = byId(id);
    if (!device || !confirm(`„${label(device)}" löschen?`)) return;
    net.devices = net.devices.filter(d => d.id !== id);
    net.links = net.links.filter(l => l.from !== id && l.to !== id);
    if (!selected || selected.type === 'link' || selected.id === id) selected = null;
    $('dialog').hidden = true;
    cancelConnect();
    changed();
}

function removeLink(index) {
    net.links.splice(index, 1);
    if (selected && selected.type === 'link') selected = null;
    changed();
}

function startConnect(id, kind) {
    connecting = { from: id, kind };
    $('hintText').textContent = kind === 'cable' ? 'Kabelziel anklicken' : 'Dienstziel anklicken';
    $('hint').hidden = false;
    $('panel').hidden = true;
}

function cancelConnect() {
    connecting = null;
    $('hint').hidden = true;
    renderPanel();
}

function finishConnect(toId) {
    if (!connecting || connecting.from === toId) return;
    const { from, kind } = connecting;
    cancelConnect();
    if (kind === 'cable') {
        const exists = net.links.some(l => l.kind === 'cable'
            && ((l.from === from && l.to === toId) || (l.from === toId && l.to === from)));
        if (!exists) net.links.push({ from, to: toId, kind: 'cable', vlans: '' });
        selected = { type: 'device', id: from };
        changed();
        return;
    }
    pendingLink = { from, to: toId };
    $('linkInfo').textContent = `${label(byId(from))} → ${label(byId(toId))}`;
    $('lService').value = 'HTTPS';
    $('lPort').value = SERVICES.HTTPS || '';
    $('linkDialog').hidden = false;
    $('lService').focus();
}

function submitLink(event) {
    event.preventDefault();
    if (!pendingLink) return;
    const service = $('lService').value;
    const port = Number($('lPort').value) || 0;
    net.links.push({ ...pendingLink, kind: 'service', service, port });
    selected = { type: 'device', id: pendingLink.from };
    pendingLink = null;
    $('linkDialog').hidden = true;
    changed();
}

function openMenu(event, items) {
    const menu = $('menu');
    menu.textContent = '';
    for (const item of items) {
        if (item.separator) {
            menu.append(document.createElement('hr'));
            continue;
        }
        const button = html('button', item.danger ? 'danger' : null, item.text);
        button.addEventListener('click', () => { closeMenu(); item.run(); });
        menu.append(button);
    }
    menu.hidden = false;
    const x = Math.min(event.clientX, window.innerWidth - menu.offsetWidth - 8);
    const y = Math.min(event.clientY, window.innerHeight - menu.offsetHeight - 8);
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
}

function closeMenu() {
    $('menu').hidden = true;
}

let toastTimer = 0;
function toast(message, isError) {
    const node = $('toast');
    node.textContent = message;
    node.classList.toggle('error', !!isError);
    node.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { node.hidden = true; }, 2600);
}

async function save() {
    const button = $('save');
    button.disabled = true;
    const sent = JSON.stringify(net);
    try {
        const response = await fetch('?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
            body: JSON.stringify({ rev, network: JSON.parse(sent) }),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            toast(data.error || 'Speichern fehlgeschlagen', true);
            return;
        }
        rev = data.rev;
        setDirty(JSON.stringify(net) !== sent);
        toast('Gespeichert');
    } catch (error) {
        toast('Keine Verbindung zum Server', true);
    } finally {
        button.disabled = false;
    }
}

function setupTypes() {
    const select = $('fType');
    for (const key in TYPES) select.append(new Option(TYPES[key], key));
    const services = $('lService');
    for (const key in SERVICES) services.append(new Option(key, key));
}

function setupPanZoom() {
    const map = $('map');
    let dragging = false, moved = false, lastX = 0, lastY = 0;

    map.addEventListener('mousedown', event => {
        if (event.button !== 0) return;
        dragging = true;
        moved = false;
        lastX = event.clientX;
        lastY = event.clientY;
        map.classList.add('grabbing');
    });
    window.addEventListener('mousemove', event => {
        if (!dragging) return;
        const dx = event.clientX - lastX, dy = event.clientY - lastY;
        if (Math.abs(dx) + Math.abs(dy) > 2) moved = true;
        lastX = event.clientX;
        lastY = event.clientY;
        view.x += dx;
        view.y += dy;
        applyTransform();
    });
    window.addEventListener('mouseup', () => {
        dragging = false;
        map.classList.remove('grabbing');
    });
    map.addEventListener('click', () => {
        if (moved) { moved = false; return; }
        clearSelection();
    });
    map.addEventListener('wheel', event => {
        event.preventDefault();
        const rect = map.getBoundingClientRect();
        const px = event.clientX - rect.left, py = event.clientY - rect.top;
        const factor = event.deltaY > 0 ? 0.9 : 1.1;
        const next = Math.min(2.5, Math.max(0.3, view.k * factor));
        const ratio = next / view.k;
        view.x = px - (px - view.x) * ratio;
        view.y = py - (py - view.y) * ratio;
        view.k = next;
        applyTransform();
    }, { passive: false });
    map.addEventListener('contextmenu', event => {
        event.preventDefault();
        openMenu(event, [
            { text: 'Gerät anlegen', run: () => openDevice(null) },
            { text: 'Ansicht einpassen', run: fitView },
        ]);
    });
}

function init() {
    const defs = el('defs');
    const marker = el('marker', {
        id: 'arrow', markerWidth: 7, markerHeight: 7, refX: 6, refY: 3,
        orient: 'auto-start-reverse', markerUnits: 'userSpaceOnUse',
    });
    marker.append(el('path', { d: 'M0 0 L6 3 L0 6 z', class: 'arrow' }));
    defs.append(marker);
    $('map').prepend(defs);

    setupTypes();
    setupPanZoom();

    $('addDevice').addEventListener('click', () => openDevice(null));
    $('addFirst').addEventListener('click', () => openDevice(null));
    $('save').addEventListener('click', save);
    $('deviceForm').addEventListener('submit', submitDevice);
    $('cancelDevice').addEventListener('click', () => { $('dialog').hidden = true; editing = null; });
    $('deleteDevice').addEventListener('click', () => editing && removeDevice(editing));
    $('fType').addEventListener('change', updateFirewallFields);
    $('linkForm').addEventListener('submit', submitLink);
    $('cancelLink').addEventListener('click', () => { $('linkDialog').hidden = true; pendingLink = null; });
    $('lService').addEventListener('change', () => { $('lPort').value = SERVICES[$('lService').value] || ''; });
    $('hintCancel').addEventListener('click', cancelConnect);
    $('search').addEventListener('input', event => {
        search = event.target.value.trim().toLowerCase();
        selected = null;
        render();
        renderPanel();
    });
    $('linkFilter').addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        linkFilter = button.dataset.value;
        for (const item of $('linkFilter').children) item.classList.toggle('on', item === button);
        render();
    });

    document.addEventListener('click', closeMenu);
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        closeMenu();
        cancelConnect();
        $('dialog').hidden = true;
        $('linkDialog').hidden = true;
        clearSelection();
    });
    window.addEventListener('resize', render);
    window.addEventListener('beforeunload', event => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    render();
    fitView();
}

init();
})();
</script>
</body>
</html>
