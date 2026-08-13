<?php
declare(strict_types=1);

const APP_TITLE = 'Netzwerk';
const SESSION_LIFETIME = 28800;
const LOGIN_MAX_FAILS = 5;
const LOGIN_LOCK_SECONDS = 60;

const DEVICE_TYPES = [
    'internet'   => ['label' => 'Internet / Provider',    'group' => 'Extern',            'rank' => 0, 'desc' => 'Anschluss nach draußen',       'ip' => false, 'role' => 'Anschluss',        'hint' => 'Glasfaser 1000/200'],
    'firewall'   => ['label' => 'Firewall',               'group' => 'Netzwerk',          'rank' => 1, 'desc' => 'Trennt Netze, gibt Ports frei','ip' => true,  'role' => 'Modell',           'hint' => 'OPNsense',        'rules' => true],
    'router'     => ['label' => 'Router',                 'group' => 'Netzwerk',          'rank' => 2, 'desc' => 'Verbindet zwei Netze',         'ip' => true,  'role' => 'Modell',           'hint' => 'Fritzbox 7590'],
    'hotspot'    => ['label' => 'Hotspot / LTE-Router',   'group' => 'Netzwerk',          'rank' => 2, 'desc' => 'Zugang über Mobilfunk',        'ip' => true,  'role' => 'Anbieter, Modell', 'hint' => 'Handy-Hotspot'],
    'switch'     => ['label' => 'Switch',                 'group' => 'Netzwerk',          'rank' => 3, 'desc' => 'Verteilt Kabel im Netz',       'ip' => true,  'role' => 'Modell',           'hint' => '48 Port L3'],
    'patch'      => ['label' => 'Patchfeld',              'group' => 'Netzwerk',          'rank' => 3, 'desc' => 'Rangierfeld im Rack',          'ip' => false, 'role' => 'Bezeichnung',      'hint' => 'Patchfeld B, 24 Port'],
    'socket'     => ['label' => 'Netzwerkdose',           'group' => 'Netzwerk',          'rank' => 4, 'desc' => 'Anschluss in der Wand',        'ip' => false, 'role' => 'Bezeichnung',      'hint' => 'Dose 1.03-A'],
    'ap'         => ['label' => 'Access Point',           'group' => 'Netzwerk',          'rank' => 4, 'desc' => 'WLAN aus dem Kabelnetz',       'ip' => true,  'role' => 'SSID, Modell',     'hint' => 'Wi-Fi 6'],
    'hypervisor' => ['label' => 'Hypervisor',             'group' => 'Server & Storage',  'rank' => 4, 'desc' => 'Host für virtuelle Maschinen', 'ip' => true,  'role' => 'Plattform',        'hint' => 'Proxmox, VMware', 'hosts' => true],
    'storage'    => ['label' => 'Storage / NAS',          'group' => 'Server & Storage',  'rank' => 4, 'desc' => 'Ablage und Backup',            'ip' => true,  'role' => 'Modell',           'hint' => 'Synology'],
    'server'     => ['label' => 'Server',                 'group' => 'Server & Storage',  'rank' => 5, 'desc' => 'Eigenes Blech mit Diensten',   'ip' => true,  'role' => 'Aufgabe',          'hint' => 'Fileserver, Gameserver'],
    'vm'         => ['label' => 'Virtuelle Maschine',     'group' => 'Server & Storage',  'rank' => 5, 'desc' => 'Läuft auf einem Host',         'ip' => true,  'role' => 'Aufgabe',          'hint' => 'Domaincontroller', 'virtual' => true],
    'client'     => ['label' => 'PC / Notebook',          'group' => 'Endgeräte',         'rank' => 6, 'desc' => 'Arbeitsplatz',                 'ip' => true,  'role' => 'System',           'hint' => 'Windows 11'],
    'phone'      => ['label' => 'Handy / Tablet',         'group' => 'Endgeräte',         'rank' => 6, 'desc' => 'Mobilgerät',                   'ip' => true,  'role' => 'System',           'hint' => 'iPhone'],
    'printer'    => ['label' => 'Drucker',                'group' => 'Endgeräte',         'rank' => 6, 'desc' => 'Drucker oder Scanner',         'ip' => true,  'role' => 'Modell',           'hint' => 'Kyocera'],
    'camera'     => ['label' => 'Kamera',                 'group' => 'Endgeräte',         'rank' => 6, 'desc' => 'Überwachung',                  'ip' => true,  'role' => 'Modell',           'hint' => 'Axis'],
    'other'      => ['label' => 'Sonstiges',              'group' => 'Endgeräte',         'rank' => 6, 'desc' => 'Alles andere',                 'ip' => true,  'role' => 'Bezeichnung',      'hint' => ''],
    'ups'        => ['label' => 'USV',                    'group' => 'Strom',             'rank' => 7, 'desc' => 'Puffert Stromausfälle',        'ip' => true,  'role' => 'Modell',           'hint' => 'APC 3000 VA'],
    'pdu'        => ['label' => 'Steckdosenleiste / PDU', 'group' => 'Strom',             'rank' => 7, 'desc' => 'Verteilt Strom im Rack',       'ip' => false, 'role' => 'Bezeichnung',      'hint' => '8x C13'],
];

const SERVICES = [
    'HTTPS' => 443, 'HTTP' => 80, 'SSH' => 22, 'RDP' => 3389, 'DNS' => 53,
    'DHCP' => 67, 'SMTP' => 25, 'LDAP' => 389, 'SMB' => 445, 'NFS' => 2049,
    'iSCSI' => 3260, 'SQL' => 1433, 'VPN' => 500, 'NTP' => 123, 'SNMP' => 161,
    'Backup' => 0, 'Sonstiges' => 0,
];

#data=true: die Leitung traegt Nutzdaten, zaehlt also fuer Pfade und VLANs.
const MEDIA = [
    'power'  => ['label' => 'Strom 230 V',           'short' => 'Strom',   'group' => 'Strom',      'data' => false],
    'usv'    => ['label' => 'Strom über USV',        'short' => 'USV',     'group' => 'Strom',      'data' => false],
    'poe'    => ['label' => 'PoE (Strom und Daten)', 'short' => 'PoE',     'group' => 'Strom',      'data' => true],
    'cat'    => ['label' => 'Kupfer (Cat 5e/6/6a)',  'short' => 'Cat',     'group' => 'Datenkabel', 'data' => true],
    'fiber'  => ['label' => 'Glasfaser (LWL)',       'short' => 'LWL',     'group' => 'Datenkabel', 'data' => true],
    'dac'    => ['label' => 'DAC / Twinax',          'short' => 'DAC',     'group' => 'Datenkabel', 'data' => true],
    'serial' => ['label' => 'Seriell (RS-232)',      'short' => 'Seriell', 'group' => 'Datenkabel', 'data' => true],
    'usb'    => ['label' => 'USB',                   'short' => 'USB',     'group' => 'Datenkabel', 'data' => true],
    'wifi'   => ['label' => 'WLAN / Funk',           'short' => 'WLAN',    'group' => 'Ohne Kabel', 'data' => true],
    'wan'    => ['label' => 'WAN / Providerleitung', 'short' => 'WAN',     'group' => 'Ohne Kabel', 'data' => true],
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

#Neben-Dateien behalten die Endung .php, damit der Guard auch dort greift.
function sibling(string $file, string $suffix): string
{
    return preg_replace('/\.php$/', '', $file) . $suffix . '.php';
}

function write_json(string $file, array $data, bool $backup = false): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $tmp = sibling($file, '.tmp');
    if (@file_put_contents($tmp, FILE_GUARD . $json, LOCK_EX) === false) {
        return false;
    }
    @chmod($tmp, 0640);
    if ($backup && is_file($file)) {
        $old = sibling($file, '.bak');
        if (@copy($file, $old)) {
            @chmod($old, 0640);
        }
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
        $type = isset(DEVICE_TYPES[$type]) ? $type : 'other';
        $spec = DEVICE_TYPES[$type];
        $device = [
            'id'    => $id,
            'name'  => cut(trim((string)($raw['name'] ?? '')), 64),
            'ip'    => $spec['ip'] ? cut(trim((string)($raw['ip'] ?? '')), 45) : '',
            'type'  => $type,
            'role'  => cut(trim((string)($raw['role'] ?? '')), 64),
            'notes' => cut(trim((string)($raw['notes'] ?? '')), 1000),
            'site'  => cut(trim((string)($raw['site'] ?? '')), 40),
            'room'  => cut(trim((string)($raw['room'] ?? '')), 40),
            'rack'  => cut(trim((string)($raw['rack'] ?? '')), 40),
            'ru'    => cut(trim((string)($raw['ru'] ?? '')), 12),
        ];
        if ($device['name'] === '' && $device['ip'] === '') {
            continue;
        }
        #Jeder Typ traegt nur die Felder, die zu ihm gehoeren.
        if (!empty($spec['hosts'])) {
            $device['hosts'] = cut(trim((string)($raw['hosts'] ?? '')), 45);
        }
        if (!empty($spec['virtual'])) {
            $device['host'] = cut(trim((string)($raw['host'] ?? '')), 40);
        }
        if (!empty($spec['rules'])) {
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

    foreach ($devices as &$device) {
        if (isset($device['host']) && ($device['host'] === $device['id'] || !isset($known[$device['host']]))) {
            $device['host'] = '';
        }
    }
    unset($device);

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
        $kind = (string)($raw['kind'] ?? '');
        if ($kind === 'cable') {
            $kind = 'cat';                    #Altbestand vor der Medien-Liste
        }
        if ($kind !== 'service' && !isset(MEDIA[$kind])) {
            $kind = 'service';
        }
        $link = ['from' => $from, 'to' => $to, 'kind' => $kind];
        if ($kind === 'service') {
            $service = (string)($raw['service'] ?? '');
            $link['service'] = isset(SERVICES[$service]) ? $service : 'Sonstiges';
            $port = (int)($raw['port'] ?? 0);
            $link['port'] = ($port > 0 && $port <= 65535) ? $port : 0;
            $key = 's|' . $from . '|' . $to . '|' . $link['service'] . '|' . $link['port'];
        } else {
            $link['fromPort'] = cut(trim((string)($raw['fromPort'] ?? '')), 40);
            $link['toPort']   = cut(trim((string)($raw['toPort'] ?? '')), 40);
            $link['via']      = cut(trim((string)($raw['via'] ?? '')), 80);
            $link['vlans']    = MEDIA[$kind]['data'] ? cut(trim((string)($raw['vlans'] ?? '')), 40) : '';
            #Zwei Kabel zwischen denselben Geraeten sind normal - erst die Ports machen sie eindeutig.
            $key = 'p|' . ($from < $to ? $from . $to : $to . $from) . '|' . $kind
                . '|' . $link['fromPort'] . '|' . $link['toPort'];
        }
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $links[] = $link;
    }

    return ['devices' => $devices, 'links' => $links];
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
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= APP_TITLE ?> · Einrichtung</title>
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
button.quiet { background: none; color: var(--label2); height: 22px; padding: 0 7px; }
button.quiet:hover { background: var(--fill); color: var(--label); }
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
.group.rack rect { stroke-dasharray: none; }
.group text { font-size: 11px; font-weight: 600; fill: var(--label2); }

.link { fill: none; stroke-linecap: round; stroke-width: 2.2; }
.link.service { stroke: var(--blue); stroke-width: 1.6; }
.link.blocked { stroke: var(--label3); stroke-width: 1.6; stroke-dasharray: 5 4; }
.link.host { stroke: var(--label3); stroke-width: 1.4; stroke-dasharray: 1 4; }
.link.m-cat { stroke: var(--label2); stroke-width: 2.5; }
.link.m-fiber { stroke: var(--teal); stroke-width: 2.5; }
.link.m-dac { stroke: var(--brown); stroke-width: 2.5; }
.link.m-serial { stroke: var(--indigo); stroke-width: 1.8; stroke-dasharray: 7 3; }
.link.m-usb { stroke: var(--indigo); stroke-width: 1.8; }
.link.m-wifi { stroke: var(--purple); stroke-width: 1.8; stroke-dasharray: 2 4; }
.link.m-wan { stroke: var(--cyan); stroke-width: 2.5; }
.link.m-power { stroke: var(--orange); stroke-width: 1.8; stroke-dasharray: 9 4; }
.link.m-usv { stroke: var(--orange); stroke-width: 2.2; }
.link.m-poe { stroke: var(--green); stroke-width: 2.5; }
.link.strong { stroke-width: 3.4; }
.link.hit { stroke: transparent; stroke-width: 16; cursor: pointer; }
.link-label, .port-label {
    font-weight: 500;
    paint-order: stroke;
    stroke: var(--canvas);
    stroke-width: 3.5px;
    stroke-linejoin: round;
}
.link-label { font-size: 10px; fill: var(--label2); }
.port-label { font-size: 9px; fill: var(--label3); }
.ru { font-size: 9px; font-weight: 600; fill: var(--label3); }
.arrow { fill: var(--blue); }
.dim { opacity: 0.26; }

.t-internet { color: var(--teal); }
.t-firewall { color: var(--red); }
.t-router { color: var(--blue); }
.t-hotspot { color: var(--blue); }
.t-switch { color: var(--cyan); }
.t-patch { color: var(--cyan); }
.t-socket { color: var(--gray); }
.t-ap { color: var(--purple); }
.t-hypervisor { color: var(--orange); }
.t-storage { color: var(--brown); }
.t-server { color: var(--green); }
.t-vm { color: var(--green); }
.t-client { color: var(--indigo); }
.t-phone { color: var(--indigo); }
.t-printer { color: var(--gray); }
.t-camera { color: var(--purple); }
.t-ups { color: var(--orange); }
.t-pdu { color: var(--orange); }
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
.emptyActions { display: flex; gap: 8px; }

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
#demoBar { top: auto; bottom: 12px; color: var(--label2); }
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
.swatch.service { border-top: 1.6px solid var(--blue); }
.swatch.blocked { border-top: 1.6px dashed var(--label3); }
.swatch.host { border-top: 1.4px dotted var(--label3); }
.swatch.m-cat { border-top: 2.5px solid var(--label2); }
.swatch.m-fiber { border-top: 2.5px solid var(--teal); }
.swatch.m-dac { border-top: 2.5px solid var(--brown); }
.swatch.m-serial { border-top: 1.8px dashed var(--indigo); }
.swatch.m-usb { border-top: 1.8px solid var(--indigo); }
.swatch.m-wifi { border-top: 1.8px dashed var(--purple); }
.swatch.m-wan { border-top: 2.5px solid var(--cyan); }
.swatch.m-power { border-top: 1.8px dashed var(--orange); }
.swatch.m-usv { border-top: 2.2px solid var(--orange); }
.swatch.m-poe { border-top: 2.5px solid var(--green); }

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
.sheet.wide { max-width: 620px; }
#jsonText {
    font: 12px/1.5 ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
    max-height: 52vh;
    tab-size: 2;
}
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
.grid.four { grid-template-columns: repeat(4, 1fr); }
.sheet.full { max-width: 1000px; }

.tablewrap { margin-top: 16px; overflow-x: auto; }
table.audit { width: 100%; border-collapse: collapse; }
table.audit th {
    text-align: left;
    font-size: 11px;
    font-weight: 500;
    color: var(--label2);
    padding: 0 6px 6px 0;
    white-space: nowrap;
}
table.audit td { padding: 0 6px 6px 0; vertical-align: top; }
table.audit input, table.audit select { height: 26px; min-width: 96px; }
table.audit td:nth-child(3) input { min-width: 150px; }
table.audit td:last-child { padding-right: 0; width: 1%; }
table.audit input:disabled, table.audit select:disabled { opacity: 0.4; }

button.chip {
    height: 24px;
    padding: 0 10px;
    border-radius: 980px;
    font-size: 12px;
    background: none;
    color: var(--label2);
    border: 1px solid var(--separator);
}
button.chip:hover { background: var(--fill); color: var(--label); }
button.chip.on { background: var(--orange); border-color: transparent; color: #fff; }
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
    .grid, .grid.four { grid-template-columns: 1fr; }
    .legend { display: none; }
}

.picker {
    max-height: 46vh;
    overflow-y: auto;
    margin: 12px -6px 0;
    padding: 0 6px;
}
.pickerGroup {
    font-size: 11px;
    font-weight: 600;
    color: var(--label3);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin: 12px 0 4px 8px;
}
.pickerGroup:first-child { margin-top: 0; }
.pickerItem {
    display: flex;
    align-items: center;
    gap: 11px;
    width: 100%;
    height: 44px;
    padding: 0 10px;
    background: none;
    text-align: left;
}
.pickerItem:hover { background: var(--fill); }
.pickerText { display: flex; flex-direction: column; line-height: 1.3; min-width: 0; }
.pickerLabel { font-size: 13px; font-weight: 500; color: var(--label); }
.pickerDesc { font-size: 11px; font-weight: 400; color: var(--label2); }
.pickerEmpty { padding: 18px 10px; font-size: 12px; color: var(--label2); }
.tico { width: 22px; height: 22px; flex: none; }

.typechip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    height: 30px;
    margin: -8px 0 16px;
    padding: 0 11px;
    border-radius: 980px;
    background: var(--fill);
    font-size: 12px;
    font-weight: 500;
}
.typechip .tico { width: 17px; height: 17px; }
.typechipAction { color: var(--blue); }

.field { display: flex; align-items: center; gap: 10px; }
.fieldlabel { font-size: 11px; font-weight: 500; color: var(--label2); }
</style>
</head>
<body class="gate">
<form class="card gate-card" method="post" action="?action=setup">
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
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= APP_TITLE ?> · Anmeldung</title>
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
button.quiet { background: none; color: var(--label2); height: 22px; padding: 0 7px; }
button.quiet:hover { background: var(--fill); color: var(--label); }
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
.group.rack rect { stroke-dasharray: none; }
.group text { font-size: 11px; font-weight: 600; fill: var(--label2); }

.link { fill: none; stroke-linecap: round; stroke-width: 2.2; }
.link.service { stroke: var(--blue); stroke-width: 1.6; }
.link.blocked { stroke: var(--label3); stroke-width: 1.6; stroke-dasharray: 5 4; }
.link.host { stroke: var(--label3); stroke-width: 1.4; stroke-dasharray: 1 4; }
.link.m-cat { stroke: var(--label2); stroke-width: 2.5; }
.link.m-fiber { stroke: var(--teal); stroke-width: 2.5; }
.link.m-dac { stroke: var(--brown); stroke-width: 2.5; }
.link.m-serial { stroke: var(--indigo); stroke-width: 1.8; stroke-dasharray: 7 3; }
.link.m-usb { stroke: var(--indigo); stroke-width: 1.8; }
.link.m-wifi { stroke: var(--purple); stroke-width: 1.8; stroke-dasharray: 2 4; }
.link.m-wan { stroke: var(--cyan); stroke-width: 2.5; }
.link.m-power { stroke: var(--orange); stroke-width: 1.8; stroke-dasharray: 9 4; }
.link.m-usv { stroke: var(--orange); stroke-width: 2.2; }
.link.m-poe { stroke: var(--green); stroke-width: 2.5; }
.link.strong { stroke-width: 3.4; }
.link.hit { stroke: transparent; stroke-width: 16; cursor: pointer; }
.link-label, .port-label {
    font-weight: 500;
    paint-order: stroke;
    stroke: var(--canvas);
    stroke-width: 3.5px;
    stroke-linejoin: round;
}
.link-label { font-size: 10px; fill: var(--label2); }
.port-label { font-size: 9px; fill: var(--label3); }
.ru { font-size: 9px; font-weight: 600; fill: var(--label3); }
.arrow { fill: var(--blue); }
.dim { opacity: 0.26; }

.t-internet { color: var(--teal); }
.t-firewall { color: var(--red); }
.t-router { color: var(--blue); }
.t-hotspot { color: var(--blue); }
.t-switch { color: var(--cyan); }
.t-patch { color: var(--cyan); }
.t-socket { color: var(--gray); }
.t-ap { color: var(--purple); }
.t-hypervisor { color: var(--orange); }
.t-storage { color: var(--brown); }
.t-server { color: var(--green); }
.t-vm { color: var(--green); }
.t-client { color: var(--indigo); }
.t-phone { color: var(--indigo); }
.t-printer { color: var(--gray); }
.t-camera { color: var(--purple); }
.t-ups { color: var(--orange); }
.t-pdu { color: var(--orange); }
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
.emptyActions { display: flex; gap: 8px; }

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
#demoBar { top: auto; bottom: 12px; color: var(--label2); }
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
.swatch.service { border-top: 1.6px solid var(--blue); }
.swatch.blocked { border-top: 1.6px dashed var(--label3); }
.swatch.host { border-top: 1.4px dotted var(--label3); }
.swatch.m-cat { border-top: 2.5px solid var(--label2); }
.swatch.m-fiber { border-top: 2.5px solid var(--teal); }
.swatch.m-dac { border-top: 2.5px solid var(--brown); }
.swatch.m-serial { border-top: 1.8px dashed var(--indigo); }
.swatch.m-usb { border-top: 1.8px solid var(--indigo); }
.swatch.m-wifi { border-top: 1.8px dashed var(--purple); }
.swatch.m-wan { border-top: 2.5px solid var(--cyan); }
.swatch.m-power { border-top: 1.8px dashed var(--orange); }
.swatch.m-usv { border-top: 2.2px solid var(--orange); }
.swatch.m-poe { border-top: 2.5px solid var(--green); }

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
.sheet.wide { max-width: 620px; }
#jsonText {
    font: 12px/1.5 ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
    max-height: 52vh;
    tab-size: 2;
}
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
.grid.four { grid-template-columns: repeat(4, 1fr); }
.sheet.full { max-width: 1000px; }

.tablewrap { margin-top: 16px; overflow-x: auto; }
table.audit { width: 100%; border-collapse: collapse; }
table.audit th {
    text-align: left;
    font-size: 11px;
    font-weight: 500;
    color: var(--label2);
    padding: 0 6px 6px 0;
    white-space: nowrap;
}
table.audit td { padding: 0 6px 6px 0; vertical-align: top; }
table.audit input, table.audit select { height: 26px; min-width: 96px; }
table.audit td:nth-child(3) input { min-width: 150px; }
table.audit td:last-child { padding-right: 0; width: 1%; }
table.audit input:disabled, table.audit select:disabled { opacity: 0.4; }

button.chip {
    height: 24px;
    padding: 0 10px;
    border-radius: 980px;
    font-size: 12px;
    background: none;
    color: var(--label2);
    border: 1px solid var(--separator);
}
button.chip:hover { background: var(--fill); color: var(--label); }
button.chip.on { background: var(--orange); border-color: transparent; color: #fff; }
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
    .grid, .grid.four { grid-template-columns: 1fr; }
    .legend { display: none; }
}

.picker {
    max-height: 46vh;
    overflow-y: auto;
    margin: 12px -6px 0;
    padding: 0 6px;
}
.pickerGroup {
    font-size: 11px;
    font-weight: 600;
    color: var(--label3);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin: 12px 0 4px 8px;
}
.pickerGroup:first-child { margin-top: 0; }
.pickerItem {
    display: flex;
    align-items: center;
    gap: 11px;
    width: 100%;
    height: 44px;
    padding: 0 10px;
    background: none;
    text-align: left;
}
.pickerItem:hover { background: var(--fill); }
.pickerText { display: flex; flex-direction: column; line-height: 1.3; min-width: 0; }
.pickerLabel { font-size: 13px; font-weight: 500; color: var(--label); }
.pickerDesc { font-size: 11px; font-weight: 400; color: var(--label2); }
.pickerEmpty { padding: 18px 10px; font-size: 12px; color: var(--label2); }
.tico { width: 22px; height: 22px; flex: none; }

.typechip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    height: 30px;
    margin: -8px 0 16px;
    padding: 0 11px;
    border-radius: 980px;
    background: var(--fill);
    font-size: 12px;
    font-weight: 500;
}
.typechip .tico { width: 17px; height: 17px; }
.typechipAction { color: var(--blue); }

.field { display: flex; align-items: center; gap: 10px; }
.fieldlabel { font-size: 11px; font-weight: 500; color: var(--label2); }
</style>
</head>
<body class="gate">
<form class="card gate-card" method="post" action="?action=login">
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
    fail('Netzwerkdatei ist beschaedigt oder nicht lesbar: ' . $netFile . "\nSicherung liegt unter " . sibling($netFile, '.bak'));
}
$network = clean_network($stored);
$rev = revision($netFile);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= APP_TITLE ?></title>
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
button.quiet { background: none; color: var(--label2); height: 22px; padding: 0 7px; }
button.quiet:hover { background: var(--fill); color: var(--label); }
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
.group.rack rect { stroke-dasharray: none; }
.group text { font-size: 11px; font-weight: 600; fill: var(--label2); }

.link { fill: none; stroke-linecap: round; stroke-width: 2.2; }
.link.service { stroke: var(--blue); stroke-width: 1.6; }
.link.blocked { stroke: var(--label3); stroke-width: 1.6; stroke-dasharray: 5 4; }
.link.host { stroke: var(--label3); stroke-width: 1.4; stroke-dasharray: 1 4; }
.link.m-cat { stroke: var(--label2); stroke-width: 2.5; }
.link.m-fiber { stroke: var(--teal); stroke-width: 2.5; }
.link.m-dac { stroke: var(--brown); stroke-width: 2.5; }
.link.m-serial { stroke: var(--indigo); stroke-width: 1.8; stroke-dasharray: 7 3; }
.link.m-usb { stroke: var(--indigo); stroke-width: 1.8; }
.link.m-wifi { stroke: var(--purple); stroke-width: 1.8; stroke-dasharray: 2 4; }
.link.m-wan { stroke: var(--cyan); stroke-width: 2.5; }
.link.m-power { stroke: var(--orange); stroke-width: 1.8; stroke-dasharray: 9 4; }
.link.m-usv { stroke: var(--orange); stroke-width: 2.2; }
.link.m-poe { stroke: var(--green); stroke-width: 2.5; }
.link.strong { stroke-width: 3.4; }
.link.hit { stroke: transparent; stroke-width: 16; cursor: pointer; }
.link-label, .port-label {
    font-weight: 500;
    paint-order: stroke;
    stroke: var(--canvas);
    stroke-width: 3.5px;
    stroke-linejoin: round;
}
.link-label { font-size: 10px; fill: var(--label2); }
.port-label { font-size: 9px; fill: var(--label3); }
.ru { font-size: 9px; font-weight: 600; fill: var(--label3); }
.arrow { fill: var(--blue); }
.dim { opacity: 0.26; }

.t-internet { color: var(--teal); }
.t-firewall { color: var(--red); }
.t-router { color: var(--blue); }
.t-hotspot { color: var(--blue); }
.t-switch { color: var(--cyan); }
.t-patch { color: var(--cyan); }
.t-socket { color: var(--gray); }
.t-ap { color: var(--purple); }
.t-hypervisor { color: var(--orange); }
.t-storage { color: var(--brown); }
.t-server { color: var(--green); }
.t-vm { color: var(--green); }
.t-client { color: var(--indigo); }
.t-phone { color: var(--indigo); }
.t-printer { color: var(--gray); }
.t-camera { color: var(--purple); }
.t-ups { color: var(--orange); }
.t-pdu { color: var(--orange); }
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
.emptyActions { display: flex; gap: 8px; }

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
#demoBar { top: auto; bottom: 12px; color: var(--label2); }
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
.swatch.service { border-top: 1.6px solid var(--blue); }
.swatch.blocked { border-top: 1.6px dashed var(--label3); }
.swatch.host { border-top: 1.4px dotted var(--label3); }
.swatch.m-cat { border-top: 2.5px solid var(--label2); }
.swatch.m-fiber { border-top: 2.5px solid var(--teal); }
.swatch.m-dac { border-top: 2.5px solid var(--brown); }
.swatch.m-serial { border-top: 1.8px dashed var(--indigo); }
.swatch.m-usb { border-top: 1.8px solid var(--indigo); }
.swatch.m-wifi { border-top: 1.8px dashed var(--purple); }
.swatch.m-wan { border-top: 2.5px solid var(--cyan); }
.swatch.m-power { border-top: 1.8px dashed var(--orange); }
.swatch.m-usv { border-top: 2.2px solid var(--orange); }
.swatch.m-poe { border-top: 2.5px solid var(--green); }

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
.sheet.wide { max-width: 620px; }
#jsonText {
    font: 12px/1.5 ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
    max-height: 52vh;
    tab-size: 2;
}
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
.grid.four { grid-template-columns: repeat(4, 1fr); }
.sheet.full { max-width: 1000px; }

.tablewrap { margin-top: 16px; overflow-x: auto; }
table.audit { width: 100%; border-collapse: collapse; }
table.audit th {
    text-align: left;
    font-size: 11px;
    font-weight: 500;
    color: var(--label2);
    padding: 0 6px 6px 0;
    white-space: nowrap;
}
table.audit td { padding: 0 6px 6px 0; vertical-align: top; }
table.audit input, table.audit select { height: 26px; min-width: 96px; }
table.audit td:nth-child(3) input { min-width: 150px; }
table.audit td:last-child { padding-right: 0; width: 1%; }
table.audit input:disabled, table.audit select:disabled { opacity: 0.4; }

button.chip {
    height: 24px;
    padding: 0 10px;
    border-radius: 980px;
    font-size: 12px;
    background: none;
    color: var(--label2);
    border: 1px solid var(--separator);
}
button.chip:hover { background: var(--fill); color: var(--label); }
button.chip.on { background: var(--orange); border-color: transparent; color: #fff; }
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
    .grid, .grid.four { grid-template-columns: 1fr; }
    .legend { display: none; }
}

.picker {
    max-height: 46vh;
    overflow-y: auto;
    margin: 12px -6px 0;
    padding: 0 6px;
}
.pickerGroup {
    font-size: 11px;
    font-weight: 600;
    color: var(--label3);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin: 12px 0 4px 8px;
}
.pickerGroup:first-child { margin-top: 0; }
.pickerItem {
    display: flex;
    align-items: center;
    gap: 11px;
    width: 100%;
    height: 44px;
    padding: 0 10px;
    background: none;
    text-align: left;
}
.pickerItem:hover { background: var(--fill); }
.pickerText { display: flex; flex-direction: column; line-height: 1.3; min-width: 0; }
.pickerLabel { font-size: 13px; font-weight: 500; color: var(--label); }
.pickerDesc { font-size: 11px; font-weight: 400; color: var(--label2); }
.pickerEmpty { padding: 18px 10px; font-size: 12px; color: var(--label2); }
.tico { width: 22px; height: 22px; flex: none; }

.typechip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    height: 30px;
    margin: -8px 0 16px;
    padding: 0 11px;
    border-radius: 980px;
    background: var(--fill);
    font-size: 12px;
    font-weight: 500;
}
.typechip .tico { width: 17px; height: 17px; }
.typechipAction { color: var(--blue); }

.field { display: flex; align-items: center; gap: 10px; }
.fieldlabel { font-size: 11px; font-weight: 500; color: var(--label2); }
</style>
</head>
<body>
<header class="bar">
    <span class="brand"><?= APP_TITLE ?></span>
    <input type="search" id="search" placeholder="Suchen" autocomplete="off">
    <div id="viewMode" class="segmented" role="group" aria-label="Ansicht">
        <button type="button" data-value="logisch" class="on">Logisch</button>
        <button type="button" data-value="physisch">Physisch</button>
        <button type="button" data-value="standort">Standort</button>
    </div>
    <button type="button" id="powerToggle" class="chip" hidden>Strom</button>
    <span class="spacer"></span>
    <span class="stat" id="stat"></span>
    <button type="button" id="addDevice">+ Gerät</button>
    <button type="button" id="save" class="primary">Sichern</button>
    <button type="button" id="more" class="plain" title="Weitere Aktionen">•••</button>
</header>
<form id="logoutForm" method="post" action="?action=logout" hidden>
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
</form>

<main id="stage">
    <svg id="map"><g id="viewport"></g></svg>
    <div id="empty" class="empty" hidden>
        <p id="emptyText">Noch keine Geräte.</p>
        <div class="emptyActions">
            <button type="button" class="primary" id="addFirst">Gerät anlegen</button>
            <button type="button" id="showDemo">Beispiel ansehen</button>
        </div>
    </div>
    <aside id="panel" class="panel" hidden></aside>
    <div id="demoBar" class="hint" hidden><span>Beispiel-Netzwerk · nicht speicherbar</span><button type="button" id="demoExit" class="quiet">Verlassen</button></div>
    <div id="hint" class="hint" hidden><span id="hintText"></span><button type="button" id="hintCancel" class="quiet">Abbrechen</button></div>
    <div id="toast" class="toast" hidden></div>
    <footer class="legend" id="legend"></footer>
</main>

<div id="pickerDialog" class="overlay" hidden>
    <div class="sheet" role="dialog" aria-label="Gerät auswählen">
        <h2>Was steht vor dir?</h2>
        <input type="search" id="pickerSearch" placeholder="Suchen, z. B. Switch, Drucker, Dose" autocomplete="off">
        <div id="pickerList" class="picker"></div>
        <div class="actions">
            <span class="spacer"></span>
            <button type="button" id="cancelPicker">Abbrechen</button>
        </div>
    </div>
</div>

<div id="dialog" class="overlay" hidden>
    <form class="sheet" id="deviceForm">
        <h2 id="dialogTitle">Gerät</h2>
        <button type="button" id="fTypeChip" class="typechip"></button>
        <div class="grid">
            <label id="wrapName">Name<input type="text" id="fName" maxlength="64" autocomplete="off"></label>
            <label id="wrapIp">IP-Adresse<input type="text" id="fIp" maxlength="45" autocomplete="off" placeholder="10.0.1.10"></label>
            <label class="wide" id="wrapRole"><span id="fRoleLabel">Rolle</span><input type="text" id="fRole" maxlength="64" autocomplete="off"></label>
            <label class="wide" id="wrapHosts">Hostet IP-Bereich<input type="text" id="fHosts" maxlength="45" autocomplete="off" placeholder="10.0.2.0/24"></label>
            <label class="wide" id="wrapHost">Läuft auf<select id="fHost"></select></label>
            <label class="wide" id="wrapPolicy">Regel<select id="fPolicy"><option value="open">Alles erlauben</option><option value="strict">Nur Freigaben erlauben</option></select></label>
            <label class="wide" id="wrapAllow">Freigegebene Ports<input type="text" id="fAllow" maxlength="120" autocomplete="off" placeholder="443, 53"></label>
            <div class="wide field" id="wrapPlaceMode">
                <span class="fieldlabel">Ort</span>
                <div id="placeMode" class="segmented" role="group" aria-label="Ort">
                    <button type="button" data-value="none" class="on">Ohne</button>
                    <button type="button" data-value="room">Raum</button>
                    <button type="button" data-value="rack">Rack</button>
                </div>
            </div>
            <label id="wrapSite">Gebäude<input type="text" id="fSite" maxlength="40" autocomplete="off" placeholder="Haus A"></label>
            <label id="wrapRoom">Raum<input type="text" id="fRoom" maxlength="40" autocomplete="off" placeholder="Technik EG"></label>
            <label id="wrapRack">Rack<input type="text" id="fRack" maxlength="40" autocomplete="off" placeholder="Rack 1"></label>
            <label id="wrapRu">Höheneinheit<input type="text" id="fRu" maxlength="12" autocomplete="off" placeholder="12"></label>
            <label class="wide">Notiz<textarea id="fNotes" maxlength="1000" rows="2"></textarea></label>
        </div>
        <div class="actions">
            <button type="button" class="danger" id="deleteDevice">Löschen</button>
            <span class="spacer"></span>
            <button type="button" id="cancelDevice">Abbrechen</button>
            <button type="submit" class="primary">Übernehmen</button>
        </div>
    </form>
</div>

<div id="linkDialog" class="overlay" hidden>
    <form class="sheet" id="linkForm">
        <h2 id="linkTitle">Verbindung</h2>
        <p class="muted" id="linkInfo"></p>
        <div class="grid">
            <label class="wide">Art<select id="lKind"></select></label>
            <label id="wrapPort">Port<input type="number" id="lPort" min="1" max="65535" placeholder="443"></label>
            <label id="wrapFromPort"><span id="lFromPortLabel">Port A</span><input type="text" id="lFromPort" maxlength="40" autocomplete="off" placeholder="Gi1/0/1"></label>
            <label id="wrapToPort"><span id="lToPortLabel">Port B</span><input type="text" id="lToPort" maxlength="40" autocomplete="off" placeholder="eth0"></label>
            <label class="wide" id="wrapVia">Weg<input type="text" id="lVia" maxlength="80" autocomplete="off" placeholder="Patchfeld B / 12 → Dose 1.03-A"></label>
            <label class="wide" id="wrapVlans">VLANs<input type="text" id="lVlans" maxlength="120" autocomplete="off" placeholder="10, 20, 30"></label>
        </div>
        <div class="actions">
            <button type="button" class="danger" id="deleteLink" hidden>Löschen</button>
            <span class="spacer"></span>
            <button type="button" id="cancelLink">Abbrechen</button>
            <button type="submit" class="primary" id="linkSubmit">Verbinden</button>
        </div>
    </form>
</div>

<div id="auditDialog" class="overlay" hidden>
    <form class="sheet full" id="auditForm">
        <h2 id="auditTitle">Aufnahme</h2>
        <p class="muted">Nur eintragen, was am Gerät steht. Unbekannte Ziele werden angelegt.</p>
        <div class="grid four" id="auditPlace">
            <div class="wide field">
                <span class="fieldlabel">Ort</span>
                <div id="auditPlaceMode" class="segmented" role="group" aria-label="Ort">
                    <button type="button" data-value="none" class="on">Ohne</button>
                    <button type="button" data-value="room">Raum</button>
                    <button type="button" data-value="rack">Rack</button>
                </div>
            </div>
            <label id="wrapASite">Gebäude<input type="text" id="aSite" maxlength="40" autocomplete="off" placeholder="Haus A"></label>
            <label id="wrapARoom">Raum<input type="text" id="aRoom" maxlength="40" autocomplete="off" placeholder="Technik EG"></label>
            <label id="wrapARack">Rack<input type="text" id="aRack" maxlength="40" autocomplete="off" placeholder="Rack 1"></label>
            <label id="wrapARu">Höheneinheit<input type="text" id="aRu" maxlength="12" autocomplete="off" placeholder="12"></label>
        </div>
        <div class="tablewrap">
            <table class="audit">
                <thead><tr>
                    <th>Port hier</th><th>Art</th><th>Ziel</th><th>Port dort</th><th>Weg</th><th>VLANs</th><th></th>
                </tr></thead>
                <tbody id="auditRows"></tbody>
            </table>
        </div>
        <datalist id="deviceNames"></datalist>
        <div class="actions">
            <span class="spacer"></span>
            <button type="button" id="cancelAudit">Abbrechen</button>
            <button type="submit" class="primary">Übernehmen</button>
        </div>
    </form>
</div>

<div id="jsonDialog" class="overlay" hidden>
    <form class="sheet wide" id="jsonForm">
        <h2>JSON</h2>
        <p class="muted">Vollständige Daten inklusive Notizen. Änderungen werden geprüft.</p>
        <textarea id="jsonText" spellcheck="false" rows="18"></textarea>
        <div class="actions">
            <button type="button" id="jsonCopy">Kopieren</button>
            <span class="spacer"></span>
            <button type="button" id="cancelJson">Abbrechen</button>
            <button type="submit" class="primary">Übernehmen</button>
        </div>
    </form>
</div>

<div id="menu" class="menu" hidden></div>

<script>
const TYPES = <?= js(DEVICE_TYPES) ?>;
const SERVICES = <?= js(SERVICES) ?>;
const MEDIA = <?= js(MEDIA) ?>;
const CSRF = <?= js($csrf) ?>;
let net = <?= js($network) ?>;
let rev = <?= js($rev) ?>;
</script>
<script>
(() => {
'use strict';

const NODE_W = 180, NODE_H = 48, CELL_X = 200, CELL_Y = 66;
const BOX_PAD = 14, RACK_PAD = 46, BOX_HEAD = 22, BOX_GAP = 28, ROW_GAP = 38, MARGIN = 28;
const NS = 'http://www.w3.org/2000/svg';
const SPREAD = 26, SPREAD_TOTAL = 130;

const ICONS = {
    internet: 'M12 3.5a8.5 8.5 0 110 17 8.5 8.5 0 010-17M3.6 9.5h16.8M3.6 14.5h16.8M12 3.5c-3 3-3 14 0 17M12 3.5c3 3 3 14 0 17',
    firewall: 'M3.5 6.5h17v11h-17zM3.5 12h17M9 6.5v5.5M16 6.5v5.5M6 12v5.5M12.5 12v5.5M19 12v5.5',
    router: 'M3.5 13.5h17v6h-17zM7 16.5h10M12 4v6M9 7l3-3 3 3',
    hotspot: 'M8.5 10.5h7v9h-7zM11 17.5h2M12 3.5v3M8.6 5.4l1.7 1.7M15.4 5.4l-1.7 1.7',
    switch: 'M3.5 8.5h17v7h-17zM7 15.5v3M12 15.5v3M17 15.5v3M7 5.5v3M12 5.5v3M17 5.5v3',
    patch: 'M3 8h18v8H3zM6.5 11v2M10 11v2M13.5 11v2M17 11v2',
    socket: 'M4.5 4.5h15v15h-15zM10 9.5v5M14 9.5v5',
    ap: 'M12 19.5v.01M8.6 15.4a5 5 0 016.8 0M5.4 12.2a9.5 9.5 0 0113.2 0M12 19.5v-3',
    hypervisor: 'M4 4.5h6.5v6.5H4zM13.5 4.5H20v6.5h-6.5zM4 13.5h6.5V20H4zM13.5 13.5H20V20h-6.5z',
    storage: 'M4 6.8c0-1.6 3.6-2.8 8-2.8s8 1.2 8 2.8-3.6 2.8-8 2.8-8-1.2-8-2.8zM4 6.8v10.4c0 1.6 3.6 2.8 8 2.8s8-1.2 8-2.8V6.8M20 12c0 1.6-3.6 2.8-8 2.8S4 13.6 4 12',
    server: 'M4 4.5h16v6H4zM4 13.5h16v6H4zM7.5 7.5v.01M7.5 16.5v.01M11 7.5h6M11 16.5h6',
    vm: 'M3.5 4.5h17v11h-17zM8 19.5h8M12 15.5v4M7.5 8h5v4h-5z',
    client: 'M3.5 4.5h17v11h-17zM9 19.5h6M12 15.5v4',
    phone: 'M8 3.5h8v17H8zM11 18h2',
    printer: 'M7 4.5h10v4H7zM4.5 8.5h15v7h-15zM7 15.5h10v4H7zM16.5 11.5v.01',
    camera: 'M3.5 8h11v8h-11zM14.5 10.5l5-2.5v8l-5-2.5zM7 19.5v.01',
    ups: 'M4.5 6.5h15v11h-15zM7.5 12h4M9.5 10v4M15 12h2.5',
    pdu: 'M3 9.5h18v5H3zM6.5 12v.01M10 12v.01M13.5 12v.01M17 12v.01',
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
let viewMode = 'logisch';
let showPower = false;
let places = {};
let boxes = [];
let editing = null;
let linkEdit = null;
let auditing = null;
let picking = null;
let draftType = 'other';
let demo = null;
const view = { x: 0, y: 0, k: 1 };

const byId = id => net.devices.find(d => d.id === id);
const clip = (text, max) => text.length > max ? text.slice(0, max - 1) + '…' : text;
const label = d => d.name || d.ip || 'Gerät';
const typeKey = d => TYPES[d.type] ? d.type : 'other';
const spec = d => TYPES[typeKey(d)];
const typeLabel = d => spec(d).label;

const isService = link => link.kind === 'service';
const medium = link => MEDIA[link.kind] || null;
const carriesData = link => isService(link) ? false : !!(medium(link) && medium(link).data);
const portOf = link => link.port || SERVICES[link.service] || 0;
const linkName = link => isService(link) ? (link.service || 'Dienst') : (medium(link) ? medium(link).label : link.kind);
const linkShort = link => isService(link) ? (link.service || 'Dienst') : (medium(link) ? medium(link).short : link.kind);

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

// Virtuelle Geraete stehen nirgends - sie erben Gebaeude und Raum vom Host.
function locationOf(device, depth = 0) {
    const own = [device.site, device.room, device.rack].map(s => (s || '').trim());
    if (own.some(Boolean) || !spec(device).virtual || depth > 3) return own;
    const host = device.host && byId(device.host);
    if (!host || host.id === device.id) return own;
    return locationOf(host, depth + 1).slice(0, 2).concat('');
}

function placeOf(device) {
    return locationOf(device).filter(Boolean);
}

function placeMode(device) {
    if ((device.rack || '').trim()) return 'rack';
    if ((device.site || '').trim() || (device.room || '').trim()) return 'room';
    return 'none';
}

function ruOf(device) {
    const found = String(device.ru || '').match(/\d+/);
    return found ? Number(found[0]) : -1;
}

function matches(device) {
    if (!search) return true;
    const hay = [device.name, device.ip, typeLabel(device), device.role, device.notes,
        subnetOf(device), device.site, device.room, device.rack, device.ru]
        .join(' ').toLowerCase();
    return search.split(/\s+/).every(term => hay.includes(term));
}

function hostRelations() {
    const out = [];
    const named = new Set();
    for (const guest of net.devices) {
        if (!guest.host || guest.host === guest.id || !byId(guest.host)) continue;
        out.push({ host: guest.host, guest: guest.id });
        named.add(guest.id);
    }
    const wired = new Set();
    net.links.filter(carriesData).forEach(l => { wired.add(l.from); wired.add(l.to); });
    for (const host of net.devices) {
        if (!host.hosts || !host.hosts.includes('/')) continue;
        for (const guest of net.devices) {
            if (guest.id === host.id || wired.has(guest.id) || named.has(guest.id)) continue;
            if (inCidr(guest.ip, host.hosts)) out.push({ host: host.id, guest: guest.id });
        }
    }
    return out;
}

function dataGraph() {
    const graph = {};
    const add = (a, b) => {
        (graph[a] = graph[a] || []).push(b);
        (graph[b] = graph[b] || []).push(a);
    };
    net.links.filter(carriesData).forEach(l => add(l.from, l.to));
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
    if (!isService(link)) return null;
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

function visibleLink(link) {
    if (viewMode === 'logisch') return isService(link);
    if (isService(link)) return false;
    return showPower || carriesData(link);
}

function layoutSpec(device) {
    if (viewMode === 'standort') {
        const parts = placeOf(device);
        return {
            row: parts[0] || '￿',
            group: parts.length ? parts.join(' › ') : 'Ohne Standort',
            rack: !!locationOf(device)[2],
        };
    }
    return {
        row: String(spec(device).rank).padStart(2, '0'),
        group: subnetOf(device),
        rack: false,
    };
}

function groupOrder(key) {
    if (viewMode === 'standort') return key === 'Ohne Standort' ? '￿' : key;
    if (key === 'Ohne IP') return '￿';
    return String(ipToInt(key.split('/')[0]) || 0).padStart(12, '0');
}

function computeLayout(width) {
    const rows = new Map();
    for (const device of net.devices.filter(matches)) {
        const spec = layoutSpec(device);
        if (!rows.has(spec.row)) rows.set(spec.row, new Map());
        const clusters = rows.get(spec.row);
        if (!clusters.has(spec.group)) clusters.set(spec.group, { rack: spec.rack, members: [] });
        clusters.get(spec.group).members.push(device);
    }

    places = {};
    boxes = [];
    let y = MARGIN;

    for (const row of [...rows.keys()].sort()) {
        const clusters = [...rows.get(row).entries()]
            .sort((a, b) => groupOrder(a[0]).localeCompare(groupOrder(b[0])));

        const measured = clusters.map(([key, cluster]) => {
            const { members, rack } = cluster;
            if (rack) {
                members.sort((a, b) => ruOf(b) - ruOf(a) || label(a).localeCompare(label(b), 'de', { numeric: true }));
            } else {
                members.sort((a, b) => label(a).localeCompare(label(b), 'de', { numeric: true }));
            }
            const cols = rack ? 1 : Math.min(4, members.length);
            const lines = Math.ceil(members.length / cols);
            const pad = rack ? RACK_PAD : BOX_PAD;
            return {
                key, members, cols, rack,
                w: cols * CELL_X - (CELL_X - NODE_W) + pad + BOX_PAD,
                h: lines * CELL_Y - (CELL_Y - NODE_H) + BOX_PAD * 2 + BOX_HEAD,
            };
        });

        const totalWidth = measured.reduce((sum, c) => sum + c.w, 0) + BOX_GAP * (measured.length - 1);
        let x = Math.max(MARGIN, (width - totalWidth) / 2);
        let rowHeight = 0;

        for (const cluster of measured) {
            boxes.push({
                key: cluster.key, x, y, w: cluster.w, h: cluster.h,
                rack: cluster.rack,
                framed: cluster.members.length > 1 || cluster.rack,
            });
            const pad = cluster.rack ? RACK_PAD : BOX_PAD;
            cluster.members.forEach((device, index) => {
                places[device.id] = {
                    x: x + pad + (index % cluster.cols) * CELL_X,
                    y: y + BOX_HEAD + BOX_PAD + Math.floor(index / cluster.cols) * CELL_Y,
                    rack: cluster.rack,
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
    if (dx === 0 && dy === 0) return { x: from.x, y: from.y };
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
        if (!visibleLink(l)) return;
        if (l.from === id) set.add(l.to);
        if (l.to === id) set.add(l.from);
    });
    hostRelations().forEach(r => {
        if (r.host === id) set.add(r.guest);
        if (r.guest === id) set.add(r.host);
    });
    return set;
}

function bundles(drawable) {
    const pairs = new Map();
    for (const item of drawable) {
        const key = [item.link.from, item.link.to].sort().join('|');
        if (!pairs.has(key)) pairs.set(key, []);
        pairs.get(key).push(item);
    }
    for (const group of pairs.values()) {
        group.sort((a, b) => sortKey(a.link).localeCompare(sortKey(b.link)));
    }
    return pairs;
}

function sortKey(link) {
    const rank = isService(link) ? '2' : (carriesData(link) ? '1' : '0');
    return rank + '|' + linkName(link) + '|' + (link.fromPort || '') + '|' + (link.toPort || '');
}

function geometry(key, group, item, index) {
    const [idA, idB] = key.split('|');
    const A = center(idA), B = center(idB);
    const vx = B.x - A.x, vy = B.y - A.y;
    const len = Math.hypot(vx, vy) || 1;
    const nx = -vy / len, ny = vx / len;

    const count = group.length;
    // Kurze Strecken (Rack-Nachbarn) vertragen keinen breiten Faecher.
    const spread = count > 1
        ? Math.min(SPREAD, SPREAD_TOTAL / (count - 1), Math.max(11, len / 4))
        : 0;
    const offset = (index - (count - 1) / 2) * spread;

    const p = center(item.link.from), q = center(item.link.to);
    // Enden voll mitversetzen: echte Parallelfuehrung statt sich schneidender Boegen.
    const start = borderPoint(p, q);
    const end = borderPoint(q, p);
    start.x += nx * offset; start.y += ny * offset;
    end.x += nx * offset; end.y += ny * offset;

    // Nur lange Strecken bekommen einen leichten Bogen.
    const bow = offset * Math.min(0.35, len / 500);
    const cx = (start.x + end.x) / 2 + nx * bow;
    const cy = (start.y + end.y) / 2 + ny * bow;
    const peak = { x: (start.x + 2 * cx + end.x) / 4, y: (start.y + 2 * cy + end.y) / 4 };
    const away = offset < 0 ? -1 : 1;
    return {
        d: `M ${start.x} ${start.y} Q ${cx} ${cy} ${end.x} ${end.y}`,
        start, end,
        labelAt: { x: peak.x + nx * 10 * away, y: peak.y + ny * 10 * away },
    };
}

function render() {
    const map = $('map');
    const viewport = $('viewport');
    viewport.textContent = '';
    computeLayout(map.clientWidth || 1200);

    const graph = dataGraph();
    const highlight = selected && selected.type === 'device' ? relatedTo(selected.id) : null;
    const groupLayer = el('g'), linkLayer = el('g'), nodeLayer = el('g');
    viewport.append(groupLayer, linkLayer, nodeLayer);

    for (const box of boxes) {
        if (!box.framed) continue;
        const group = el('g', { class: 'group' + (box.rack ? ' rack' : '') });
        group.append(
            el('rect', { x: box.x, y: box.y, width: box.w, height: box.h, rx: 10 }),
            el('text', { x: box.x + 12, y: box.y + 17 }, box.key)
        );
        groupLayer.append(group);
    }

    if (viewMode === 'logisch') {
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
    }

    const drawable = net.links
        .map((link, index) => ({ link, index }))
        .filter(({ link }) => visibleLink(link) && center(link.from) && center(link.to));

    for (const [key, group] of bundles(drawable)) {
        group.forEach((item, position) => {
            const { link, index } = item;
            const geo = geometry(key, group, item, position);
            const blocked = blockingFirewall(link, graph);
            const dim = highlight && !(highlight.has(link.from) && highlight.has(link.to));
            const isSelected = selected && selected.type === 'link' && selected.index === index;
            const kind = isService(link) ? (blocked ? 'blocked' : 'service') : 'm-' + link.kind;

            const path = el('path', {
                d: geo.d,
                class: `link ${kind}${dim ? ' dim' : ''}${isSelected ? ' strong' : ''}`,
            });
            if (isService(link)) path.setAttribute('marker-end', 'url(#arrow)');

            const hit = el('path', { d: geo.d, class: 'link hit' });
            hit.addEventListener('click', event => { event.stopPropagation(); select({ type: 'link', index }); });
            hit.addEventListener('dblclick', event => { event.stopPropagation(); openLink(index); });
            hit.addEventListener('contextmenu', event => {
                event.preventDefault();
                event.stopPropagation();
                openMenu(event, [
                    { text: 'Bearbeiten', run: () => openLink(index) },
                    { separator: true },
                    { text: 'Verbindung löschen', danger: true, run: () => removeLink(index) },
                ]);
            });
            linkLayer.append(path, hit);

            if (isSelected || (highlight && !dim)) {
                const text = [linkShort(link), link.vlans ? 'VLAN ' + link.vlans : '']
                    .filter(Boolean).join(' · ');
                linkLayer.append(el('text', {
                    x: geo.labelAt.x, y: geo.labelAt.y, 'text-anchor': 'middle', class: 'link-label',
                }, text));
            }
            if (isSelected && !isService(link)) {
                if (link.fromPort) linkLayer.append(portLabel(geo.start, geo.end, link.fromPort));
                if (link.toPort) linkLayer.append(portLabel(geo.end, geo.start, link.toPort));
            }
        });
    }

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
        node.append(el('title', {}, [label(device), device.ip, device.role, typeLabel(device), placeOf(device).join(' › ')]
            .filter(Boolean).join(' · ')));
        node.addEventListener('click', event => {
            event.stopPropagation();
            if (connecting) finishConnect(device.id); else select({ type: 'device', id: device.id });
        });
        node.addEventListener('dblclick', event => { event.stopPropagation(); openAudit(device.id); });
        node.addEventListener('contextmenu', event => {
            event.preventDefault();
            event.stopPropagation();
            openMenu(event, [
                { text: 'Aufnahme', run: () => openAudit(device.id) },
                { text: 'Bearbeiten', run: () => openDevice(device.id) },
                { text: 'Verbindung ziehen', run: () => startConnect(device.id) },
                { separator: true },
                { text: 'Gerät löschen', danger: true, run: () => removeDevice(device.id) },
            ]);
        });
        nodeLayer.append(node);

        if (place.rack && device.ru) {
            nodeLayer.append(el('text', {
                x: place.x - 10, y: place.y + 30, 'text-anchor': 'end', class: 'ru',
            }, 'HE ' + device.ru));
        }
    }

    applyTransform();
    renderLegend(drawable);
    const nothingShown = !Object.keys(places).length;
    $('empty').hidden = !nothingShown;
    $('emptyText').textContent = net.devices.length ? 'Keine Treffer.' : 'Noch keine Geräte.';
    $('addFirst').hidden = net.devices.length > 0;
    $('showDemo').hidden = net.devices.length > 0 || !!demo;
    $('powerToggle').hidden = viewMode === 'logisch';
    $('powerToggle').classList.toggle('on', showPower);
    $('stat').textContent = `${net.devices.length} Geräte · ${net.links.length} Verbindungen`;
}

function portLabel(at, towards, text) {
    const dx = towards.x - at.x, dy = towards.y - at.y;
    const len = Math.hypot(dx, dy) || 1;
    return el('text', {
        x: at.x + (dx / len) * 26,
        y: at.y + (dy / len) * 26,
        'text-anchor': 'middle',
        class: 'port-label',
    }, clip(text, 14));
}

function renderLegend(drawable) {
    const legend = $('legend');
    legend.textContent = '';
    const seen = new Map();
    for (const { link } of drawable) {
        if (isService(link)) seen.set('service', 'Dienst');
        else if (!seen.has('m-' + link.kind)) seen.set('m-' + link.kind, linkShort(link));
    }
    if (viewMode === 'logisch') {
        seen.set('blocked', 'blockiert');
        if (hostRelations().length) seen.set('host', 'hostet');
    }
    for (const [kind, text] of seen) {
        const item = html('span');
        item.append(html('i', 'swatch ' + kind), document.createTextNode(text));
        legend.append(item);
    }
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
        panel.append(html('h3', null, linkName(link)));
        panel.append(html('div', 'sub', `${label(from)} → ${label(to)}`));
        const list = html('dl');
        const add = (key, value) => { list.append(html('dt', null, key), html('dd', null, value)); };
        if (isService(link)) {
            add('Port', String(portOf(link) || '–'));
            const blocked = blockingFirewall(link, dataGraph());
            add('Status', blocked ? 'blockiert durch ' + label(blocked) : 'erlaubt');
        } else {
            if (link.fromPort) add('Port ' + label(from), link.fromPort);
            if (link.toPort) add('Port ' + label(to), link.toPort);
            if (link.via) add('Weg', link.via);
            if (link.vlans) add('VLANs', link.vlans);
        }
        panel.append(list);
        const index = selected.index;
        const edit = html('button', null, 'Bearbeiten');
        edit.addEventListener('click', () => openLink(index));
        const remove = html('button', 'danger', 'Löschen');
        remove.addEventListener('click', () => removeLink(index));
        const buttons = html('div', 'buttons');
        buttons.append(edit, remove);
        panel.append(buttons);
        return;
    }

    const device = byId(selected.id);
    if (!device) return clearSelection();
    panel.append(html('h3', null, label(device)));
    panel.append(html('div', 'sub', [device.role, typeLabel(device)].filter(Boolean).join(' · ')));

    const list = html('dl');
    const add = (key, value) => { list.append(html('dt', null, key), html('dd', null, value)); };
    if (device.ip) {
        add('IP', device.ip);
        add('Netz', subnetOf(device));
    }
    const where = placeOf(device);
    if (where.length) add('Standort', where.join(' › ') + (device.ru ? ' · HE ' + device.ru : ''));
    if (device.hosts) add('Hostet', device.hosts);
    if (spec(device).rules) {
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
        .filter(({ link }) => link.from === device.id || link.to === device.id)
        .sort((a, b) => sortKey(a.link).localeCompare(sortKey(b.link)));
    const physical = connections.filter(c => !isService(c.link));
    const logical = connections.filter(c => isService(c.link));
    const graph = dataGraph();

    const section = (title, items) => {
        if (!items.length) return;
        panel.append(html('h4', null, title));
        for (const { link, index } of items) {
            const outgoing = link.from === device.id;
            const other = byId(outgoing ? link.to : link.from);
            const own = outgoing ? link.fromPort : link.toPort;
            const far = outgoing ? link.toPort : link.fromPort;
            const blocked = blockingFirewall(link, graph);
            const row = html('div', 'row');
            const text = html('span', 'grow',
                linkShort(link) + ' · ' + label(other) + (far ? ' (' + far + ')' : ''));
            text.style.cursor = 'pointer';
            text.addEventListener('click', () => select({ type: 'link', index }));
            const remove = html('button', 'quiet', '×');
            remove.addEventListener('click', () => removeLink(index));
            const tag = blocked ? 'blockiert' : (own || (outgoing ? '→' : '←'));
            row.append(text, html('span', 'tag', tag), remove);
            panel.append(row);
        }
    };
    section('Physisch', physical);
    section('Dienste', logical);

    const buttons = html('div', 'buttons');
    const audit = html('button', 'primary', 'Aufnahme');
    audit.addEventListener('click', () => openAudit(device.id));
    const edit = html('button', null, 'Bearbeiten');
    edit.addEventListener('click', () => openDevice(device.id));
    const connect = html('button', null, 'Verbinden');
    connect.addEventListener('click', () => startConnect(device.id));
    buttons.append(audit, edit, connect);
    panel.append(buttons);
}

function setDirty(value) {
    dirty = value && !demo;
    $('save').textContent = dirty ? 'Sichern •' : 'Sichern';
    $('save').disabled = !!demo;
}

function changed() {
    setDirty(true);
    render();
    renderPanel();
}

function typeIcon(key) {
    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('class', 'tico t-' + key);
    svg.append(el('path', {
        d: ICONS[key] || ICONS.other,
        fill: 'none', stroke: 'currentColor', 'stroke-width': 1.6,
        'stroke-linecap': 'round', 'stroke-linejoin': 'round',
    }));
    return svg;
}

function renderPicker() {
    const list = $('pickerList');
    const term = $('pickerSearch').value.trim().toLowerCase();
    const words = term ? term.split(/\s+/) : [];
    list.textContent = '';

    const hits = [];
    for (const key in TYPES) {
        const type = TYPES[key];
        const hay = (key + ' ' + type.label + ' ' + type.desc + ' ' + type.group).toLowerCase();
        if (words.length && !words.every(word => hay.includes(word))) continue;
        const name = type.label.toLowerCase();
        // Treffer im Namen schlagen Treffer in der Beschreibung.
        const score = !term ? 0 : name.startsWith(term) ? 0 : name.includes(term) ? 1 : 2;
        hits.push({ key, type, score });
    }
    if (!hits.length) {
        list.append(html('div', 'pickerEmpty', 'Nichts gefunden — „Sonstiges" passt immer.'));
        return;
    }
    if (term) hits.sort((a, b) => a.score - b.score);

    let group = '';
    for (const { key, type } of hits) {
        if (!term && type.group !== group) {
            group = type.group;
            list.append(html('div', 'pickerGroup', group));
        }
        const item = html('button', 'pickerItem');
        item.type = 'button';
        item.dataset.key = key;
        const text = html('span', 'pickerText');
        text.append(html('span', 'pickerLabel', type.label), html('span', 'pickerDesc', type.desc));
        item.append(typeIcon(key), text);
        item.addEventListener('click', () => pickType(key));
        list.append(item);
    }
}

function openPicker(changeType) {
    picking = changeType ? 'change' : 'new';
    $('pickerSearch').value = '';
    renderPicker();
    $('pickerDialog').hidden = false;
    $('pickerSearch').focus();
}

function pickType(key) {
    $('pickerDialog').hidden = true;
    if (picking === 'change' && editing) {
        const device = byId(editing);
        if (device) device.type = key;
        picking = null;
        openDevice(editing);
        return;
    }
    picking = null;
    openDevice(null, key);
}

function openDevice(id, type) {
    const device = id ? byId(id) : null;
    if (!device && !type) return openPicker(false);
    editing = device ? device.id : null;
    draftType = device ? typeKey(device) : type;
    const info = TYPES[draftType] || TYPES.other;

    $('dialogTitle').textContent = device ? 'Gerät bearbeiten' : 'Neues Gerät';
    const chip = $('fTypeChip');
    chip.textContent = '';
    chip.append(typeIcon(draftType), html('span', null, info.label), html('span', 'typechipAction', 'ändern'));

    $('fName').value = device ? device.name : '';
    $('fIp').value = device ? device.ip || '' : '';
    $('fRole').value = device ? device.role : '';
    $('fRoleLabel').textContent = info.role;
    $('fRole').placeholder = info.hint || '';
    $('fHosts').value = device && device.hosts ? device.hosts : '';
    $('fNotes').value = device ? device.notes : '';
    $('fSite').value = device ? device.site || '' : '';
    $('fRoom').value = device ? device.room || '' : '';
    $('fRack').value = device ? device.rack || '' : '';
    $('fRu').value = device ? device.ru || '' : '';
    $('fPolicy').value = device && device.policy === 'strict' ? 'strict' : 'open';
    $('fAllow').value = device && device.allow ? device.allow.join(', ') : '';

    const hosts = $('fHost');
    hosts.textContent = '';
    hosts.append(new Option('— nicht gesetzt —', ''));
    for (const other of net.devices) {
        if (!device || other.id !== device.id) hosts.append(new Option(label(other), other.id));
    }
    hosts.value = device && device.host ? device.host : '';

    setPlaceMode('placeMode', device ? placeMode(device) : 'none');
    applyTypeMask();
    $('deleteDevice').hidden = !device;
    $('dialog').hidden = false;
    $('fName').focus();
}

function setPlaceMode(groupId, mode) {
    for (const button of $(groupId).children) {
        button.classList.toggle('on', button.dataset.value === mode);
    }
}

function currentPlaceMode(groupId) {
    const active = [...$(groupId).children].find(b => b.classList.contains('on'));
    return active ? active.dataset.value : 'none';
}

function applyTypeMask() {
    const info = TYPES[draftType] || TYPES.other;
    $('wrapIp').hidden = !info.ip;
    $('wrapName').classList.toggle('wide', !info.ip);
    $('wrapHosts').hidden = !info.hosts;
    $('wrapHost').hidden = !info.virtual;
    $('wrapPolicy').hidden = !info.rules;
    $('wrapAllow').hidden = !info.rules;
    const place = !info.virtual;
    const mode = currentPlaceMode('placeMode');
    $('wrapPlaceMode').hidden = !place;
    $('wrapSite').hidden = !place || mode === 'none';
    $('wrapRoom').hidden = !place || mode === 'none';
    $('wrapRack').hidden = !place || mode !== 'rack';
    $('wrapRu').hidden = !place || mode !== 'rack';
}

function submitDevice(event) {
    event.preventDefault();
    const info = TYPES[draftType] || TYPES.other;
    const name = $('fName').value.trim();
    const ip = info.ip ? $('fIp').value.trim() : '';
    if (!name && !ip) {
        $('fName').focus();
        return;
    }
    const device = editing ? byId(editing) : { id: newId() };
    for (const key of ['ip', 'hosts', 'host', 'policy', 'allow', 'site', 'room', 'rack', 'ru']) {
        delete device[key];
    }
    device.name = name;
    device.type = draftType;
    device.role = $('fRole').value.trim();
    device.notes = $('fNotes').value.trim();
    if (info.ip) device.ip = ip;
    if (info.hosts) device.hosts = $('fHosts').value.trim();
    if (info.virtual) device.host = $('fHost').value;
    if (info.rules) {
        device.policy = $('fPolicy').value;
        device.allow = $('fAllow').value.split(/[,;\s]+/)
            .map(Number)
            .filter(port => Number.isInteger(port) && port > 0 && port <= 65535);
    }
    if (!info.virtual) {
        const mode = currentPlaceMode('placeMode');
        if (mode !== 'none') {
            device.site = $('fSite').value.trim();
            device.room = $('fRoom').value.trim();
        }
        if (mode === 'rack') {
            device.rack = $('fRack').value.trim();
            device.ru = $('fRu').value.trim();
        }
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

function startConnect(id) {
    connecting = { from: id };
    $('hintText').textContent = 'Ziel anklicken';
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
    const { from } = connecting;
    cancelConnect();
    const draft = viewMode === 'logisch'
        ? { from, to: toId, kind: 'service', service: 'HTTPS', port: SERVICES.HTTPS || 0 }
        : { from, to: toId, kind: 'cat', fromPort: '', toPort: '', via: '', vlans: '' };
    openLink(null, draft);
}

function kindValue(link) {
    return isService(link) ? 'svc:' + (link.service || 'Sonstiges') : link.kind;
}

function openLink(index, draft) {
    const link = index === null ? draft : net.links[index];
    if (!link) return;
    linkEdit = { index, link: { ...link } };
    $('linkTitle').textContent = index === null ? 'Neue Verbindung' : 'Verbindung';
    $('linkInfo').textContent = `${label(byId(link.from))} → ${label(byId(link.to))}`;
    $('lKind').value = kindValue(link);
    $('lPort').value = link.port || '';
    $('lVlans').value = link.vlans || '';
    $('lFromPort').value = link.fromPort || '';
    $('lToPort').value = link.toPort || '';
    $('lVia').value = link.via || '';
    $('lFromPortLabel').textContent = 'Port ' + clip(label(byId(link.from)), 18);
    $('lToPortLabel').textContent = 'Port ' + clip(label(byId(link.to)), 18);
    updateLinkFields();
    $('deleteLink').hidden = index === null;
    $('linkSubmit').textContent = index === null ? 'Verbinden' : 'Übernehmen';
    $('linkDialog').hidden = false;
    $('lKind').focus();
}

function updateLinkFields() {
    const value = $('lKind').value;
    const service = value.startsWith('svc:');
    const data = service ? false : !!(MEDIA[value] && MEDIA[value].data);
    $('wrapPort').hidden = !service;
    $('wrapVlans').hidden = !data;
    $('wrapFromPort').hidden = service;
    $('wrapToPort').hidden = service;
    $('wrapVia').hidden = service;
    if (service) $('lPort').value = SERVICES[value.slice(4)] || '';
}

function submitLink(event) {
    event.preventDefault();
    if (!linkEdit) return;
    const { index } = linkEdit;
    const value = $('lKind').value;
    const link = { from: linkEdit.link.from, to: linkEdit.link.to };
    if (value.startsWith('svc:')) {
        link.kind = 'service';
        link.service = value.slice(4);
        link.port = Number($('lPort').value) || SERVICES[link.service] || 0;
    } else {
        link.kind = value;
        link.fromPort = $('lFromPort').value.trim();
        link.toPort = $('lToPort').value.trim();
        link.via = $('lVia').value.trim();
        link.vlans = MEDIA[value] && MEDIA[value].data ? cleanVlans($('lVlans').value) : '';
    }
    if (index === null) net.links.push(link);
    else net.links[index] = link;
    selected = { type: 'link', index: index === null ? net.links.length - 1 : index };
    if (!isService(link) && viewMode === 'logisch') viewMode = 'physisch';
    if (isService(link) && viewMode !== 'logisch') viewMode = 'logisch';
    syncViewButtons();
    closeLink();
    changed();
}

function cleanVlans(text) {
    return text.split(/[,;\s]+/)
        .map(Number)
        .filter(id => Number.isInteger(id) && id > 0 && id < 4095)
        .join(', ');
}

function closeLink() {
    linkEdit = null;
    $('linkDialog').hidden = true;
}

function kindSelect(value) {
    const select = html('select');
    const groups = {};
    for (const key in MEDIA) {
        const group = MEDIA[key].group;
        if (!groups[group]) {
            groups[group] = document.createElement('optgroup');
            groups[group].label = group;
            select.append(groups[group]);
        }
        groups[group].append(new Option(MEDIA[key].label, key));
    }
    const services = document.createElement('optgroup');
    services.label = 'Dienst';
    for (const key in SERVICES) services.append(new Option(key, 'svc:' + key));
    select.append(services);
    select.value = value;
    return select;
}

function auditRow(deviceId, entry) {
    const link = entry ? entry.link : null;
    const outgoing = !link || link.from === deviceId;
    const row = html('tr');
    row.dataset.index = entry ? String(entry.index) : '';
    row.dataset.outgoing = outgoing ? '1' : '';

    const cell = (child) => {
        const td = html('td');
        td.append(child);
        row.append(td);
        return child;
    };
    const input = (value, placeholder, cls) => {
        const node = html('input');
        node.type = 'text';
        node.value = value || '';
        node.placeholder = placeholder || '';
        if (cls) node.className = cls;
        return node;
    };

    const ownValue = !link ? '' : (isService(link)
        ? String(portOf(link) || '')
        : (outgoing ? link.fromPort : link.toPort));
    const own = cell(input(ownValue, 'Gi1/0/1', 'own'));
    const kind = cell(kindSelect(link ? kindValue(link) : 'cat'));
    kind.className = 'kind';
    const target = cell(input(link ? label(byId(outgoing ? link.to : link.from)) : '', 'Gerät oder Dose', 'target'));
    target.setAttribute('list', 'deviceNames');
    const far = cell(input(link ? (outgoing ? link.toPort : link.fromPort) : '', 'Port', 'far'));
    const via = cell(input(link ? link.via : '', 'Patchfeld, Trasse', 'via'));
    const vlans = cell(input(link ? link.vlans : '', '10, 20', 'vlans'));

    const remove = html('button', 'quiet', '×');
    remove.type = 'button';
    remove.addEventListener('click', () => {
        row.remove();
        ensureBlankRow(deviceId);
    });
    cell(remove);

    const adapt = () => {
        const service = kind.value.startsWith('svc:');
        far.disabled = service;
        via.disabled = service;
        vlans.disabled = service || !(MEDIA[kind.value] && MEDIA[kind.value].data);
        own.placeholder = service ? 'Port 443' : 'Gi1/0/1';
    };
    kind.addEventListener('change', adapt);
    adapt();

    const touch = () => ensureBlankRow(deviceId);
    row.addEventListener('input', touch);
    return row;
}

function rowIsEmpty(row) {
    return !row.querySelector('.target').value.trim() && !row.querySelector('.own').value.trim();
}

function ensureBlankRow(deviceId) {
    const body = $('auditRows');
    const rows = [...body.children];
    if (!rows.length || !rowIsEmpty(rows[rows.length - 1])) {
        body.append(auditRow(deviceId, null));
    }
}

function openAudit(deviceId) {
    const device = byId(deviceId);
    if (!device) return;
    auditing = deviceId;
    $('auditTitle').textContent = 'Aufnahme · ' + label(device);
    $('aSite').value = device.site || '';
    $('aRoom').value = device.room || '';
    $('aRack').value = device.rack || '';
    $('aRu').value = device.ru || '';
    setPlaceMode('auditPlaceMode', placeMode(device));
    applyAuditPlace();

    const names = $('deviceNames');
    names.textContent = '';
    for (const other of net.devices) {
        if (other.id !== deviceId) names.append(new Option(label(other)));
    }

    const body = $('auditRows');
    body.textContent = '';
    net.links
        .map((link, index) => ({ link, index }))
        .filter(({ link }) => link.from === deviceId || link.to === deviceId)
        .sort((a, b) => sortKey(a.link).localeCompare(sortKey(b.link)))
        .forEach(entry => body.append(auditRow(deviceId, entry)));
    ensureBlankRow(deviceId);

    $('auditDialog').hidden = false;
    $('aSite').focus();
}

function applyAuditPlace() {
    const device = auditing && byId(auditing);
    const place = device && !spec(device).virtual;
    const mode = currentPlaceMode('auditPlaceMode');
    $('auditPlace').hidden = !place;
    $('wrapASite').hidden = !place || mode === 'none';
    $('wrapARoom').hidden = !place || mode === 'none';
    $('wrapARack').hidden = !place || mode !== 'rack';
    $('wrapARu').hidden = !place || mode !== 'rack';
}

function findOrCreate(name) {
    const wanted = name.trim().toLowerCase();
    const existing = net.devices.find(d => label(d).toLowerCase() === wanted);
    if (existing) return existing.id;
    const device = { id: newId(), name: name.trim(), ip: '', type: 'other', role: '', notes: '' };
    if (currentPlaceMode('auditPlaceMode') !== 'none') {
        device.site = $('aSite').value.trim();
        device.room = $('aRoom').value.trim();
    }
    net.devices.push(device);
    return device.id;
}

function submitAudit(event) {
    event.preventDefault();
    if (!auditing) return;
    const device = byId(auditing);
    if (!device) return;
    const mode = spec(device).virtual ? 'none' : currentPlaceMode('auditPlaceMode');
    delete device.site; delete device.room; delete device.rack; delete device.ru;
    if (mode !== 'none') {
        device.site = $('aSite').value.trim();
        device.room = $('aRoom').value.trim();
    }
    if (mode === 'rack') {
        device.rack = $('aRack').value.trim();
        device.ru = $('aRu').value.trim();
    }

    const kept = new Set();
    let created = 0;
    for (const row of $('auditRows').children) {
        const target = row.querySelector('.target').value.trim();
        if (!target) continue;
        const before = net.devices.length;
        const otherId = findOrCreate(target);
        if (net.devices.length > before) created++;
        if (otherId === auditing) continue;

        const own = row.querySelector('.own').value.trim();
        const far = row.querySelector('.far').value.trim();
        const value = row.querySelector('.kind').value;
        const outgoing = row.dataset.outgoing === '1';
        let link;
        if (value.startsWith('svc:')) {
            const service = value.slice(4);
            link = {
                from: auditing, to: otherId, kind: 'service', service,
                port: Number(own) || SERVICES[service] || 0,
            };
        } else {
            link = {
                kind: value,
                via: row.querySelector('.via').value.trim(),
                vlans: MEDIA[value] && MEDIA[value].data ? cleanVlans(row.querySelector('.vlans').value) : '',
            };
            Object.assign(link, outgoing
                ? { from: auditing, to: otherId, fromPort: own, toPort: far }
                : { from: otherId, to: auditing, fromPort: far, toPort: own });
        }
        const index = row.dataset.index === '' ? -1 : Number(row.dataset.index);
        if (index >= 0 && net.links[index]) {
            net.links[index] = link;
            kept.add(index);
        } else {
            net.links.push(link);
            kept.add(net.links.length - 1);
        }
    }

    // Zeilen, die der Nutzer geleert oder entfernt hat, verschwinden mit.
    net.links = net.links.filter((link, index) =>
        (link.from !== auditing && link.to !== auditing) || kept.has(index));

    auditing = null;
    $('auditDialog').hidden = true;
    selected = { type: 'device', id: device.id };
    if (created) toast(created === 1 ? 'Ein Gerät neu angelegt' : created + ' Geräte neu angelegt');
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
    if (demo) {
        toast('Im Beispiel-Netzwerk wird nicht gespeichert', true);
        return;
    }
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

const DEMO = {
    devices: [
        { id: 'd1', name: 'Provider', type: 'internet', role: 'Glasfaser 1000/200', notes: '', site: 'Haus A', room: 'Technik EG' },
        { id: 'd2', name: 'Perimeter-Firewall', ip: '192.168.0.1', type: 'firewall', role: 'OPNsense', notes: 'Nur freigegebene Ports nach innen.', policy: 'strict', allow: [443, 80, 53, 3389], site: 'Haus A', room: 'Technik EG', rack: 'Rack 1', ru: '42' },
        { id: 'd3', name: 'Core-Switch', ip: '192.168.0.2', type: 'switch', role: '48 Port L3', notes: '', site: 'Haus A', room: 'Technik EG', rack: 'Rack 1', ru: '40' },
        { id: 'd15', name: 'Patchfeld A', type: 'patch', role: '24 Port Cat 6a', notes: '', site: 'Haus A', room: 'Technik EG', rack: 'Rack 1', ru: '38' },
        { id: 'd4', name: 'Access-Switch Büro', ip: '192.168.0.3', type: 'switch', role: '24 Port PoE', notes: '', site: 'Haus B', room: '1.OG Flur', rack: 'Wandschrank', ru: '6' },
        { id: 'd5', name: 'WLAN Büro', ip: '192.168.20.5', type: 'ap', role: 'Wi-Fi 6', notes: '', site: 'Haus B', room: '1.03' },
        { id: 'd16', name: 'Dose 1.03-A', type: 'socket', role: 'Doppeldose', notes: '', site: 'Haus B', room: '1.03' },
        { id: 'd6', name: 'ESXi-01', ip: '192.168.10.10', type: 'hypervisor', role: 'VMware', hosts: '192.168.10.0/24', notes: '', site: 'Haus A', room: 'Technik EG', rack: 'Rack 1', ru: '20' },
        { id: 'd7', name: 'Domaincontroller', ip: '192.168.10.11', type: 'vm', role: 'Active Directory', host: 'd6', notes: '' },
        { id: 'd8', name: 'Fileserver', ip: '192.168.10.12', type: 'vm', role: 'SMB', host: 'd6', notes: '' },
        { id: 'd9', name: 'Gameserver', ip: '192.168.10.13', type: 'vm', role: 'Minecraft 25565', host: 'd6', notes: 'Port 25565 ist in der Firewall nicht freigegeben.' },
        { id: 'd10', name: 'Backup-NAS', ip: '192.168.10.20', type: 'storage', role: 'Synology', notes: '', site: 'Haus A', room: 'Technik EG', rack: 'Rack 1', ru: '12' },
        { id: 'd11', name: 'Arbeitsplatz 12', ip: '192.168.20.112', type: 'client', role: 'Windows 11', notes: '', site: 'Haus B', room: '1.03' },
        { id: 'd12', name: 'Notebook Vertrieb', ip: '192.168.20.140', type: 'client', role: 'macOS', notes: '', site: 'Haus B', room: '1.03' },
        { id: 'd17', name: 'Handy Aussendienst', ip: '192.168.20.150', type: 'phone', role: 'iPhone', notes: '', site: 'Haus B', room: '1.03' },
        { id: 'd13', name: 'Drucker Flur', ip: '192.168.20.60', type: 'printer', role: 'Kyocera', notes: '', site: 'Haus B', room: '1.OG Flur' },
        { id: 'd14', name: 'USV Rack 1', ip: '192.168.0.9', type: 'ups', role: 'APC 3000 VA', notes: '', site: 'Haus A', room: 'Technik EG', rack: 'Rack 1', ru: '2' },
    ],
    links: [
        { from: 'd1', to: 'd2', kind: 'wan', fromPort: 'ONT', toPort: 'WAN', via: 'HÜP Keller', vlans: '' },
        { from: 'd2', to: 'd3', kind: 'cat', fromPort: 'LAN1', toPort: 'Gi1/0/1', via: 'Rack 1 intern', vlans: '10, 20' },
        { from: 'd2', to: 'd3', kind: 'cat', fromPort: 'LAN2', toPort: 'Gi1/0/2', via: 'Rack 1 intern', vlans: '10, 20' },
        { from: 'd3', to: 'd6', kind: 'fiber', fromPort: 'Te1/0/49', toPort: 'vmnic0', via: 'Rack 1 intern', vlans: '10' },
        { from: 'd3', to: 'd6', kind: 'fiber', fromPort: 'Te1/0/50', toPort: 'vmnic1', via: 'Rack 1 intern', vlans: '10' },
        { from: 'd3', to: 'd15', kind: 'cat', fromPort: 'Gi1/0/13', toPort: 'Port 13', via: '', vlans: '20' },
        { from: 'd15', to: 'd4', kind: 'fiber', fromPort: 'Port 24', toPort: 'SFP1', via: 'Trasse Hof → Haus B', vlans: '20' },
        { from: 'd3', to: 'd10', kind: 'cat', fromPort: 'Gi1/0/24', toPort: 'LAN1', via: 'Rack 1 intern', vlans: '10' },
        { from: 'd4', to: 'd5', kind: 'poe', fromPort: 'Gi0/12', toPort: 'eth0', via: 'Patchfeld B / 12', vlans: '20' },
        { from: 'd4', to: 'd16', kind: 'cat', fromPort: 'Gi0/3', toPort: 'Buchse A', via: 'Patchfeld B / 3', vlans: '20' },
        { from: 'd16', to: 'd11', kind: 'cat', fromPort: 'Buchse A', toPort: 'onboard', via: '', vlans: '20' },
        { from: 'd4', to: 'd13', kind: 'cat', fromPort: 'Gi0/8', toPort: 'LAN', via: 'Patchfeld B / 8 → Dose Flur', vlans: '20' },
        { from: 'd5', to: 'd12', kind: 'wifi', fromPort: 'SSID Buero', toPort: 'WLAN', via: '', vlans: '20' },
        { from: 'd5', to: 'd17', kind: 'wifi', fromPort: 'SSID Buero', toPort: 'WLAN', via: '', vlans: '20' },
        { from: 'd14', to: 'd3', kind: 'usv', fromPort: 'Out 1', toPort: 'PSU1', via: '', vlans: '' },
        { from: 'd14', to: 'd6', kind: 'usv', fromPort: 'Out 2', toPort: 'PSU1', via: '', vlans: '' },
        { from: 'd14', to: 'd10', kind: 'power', fromPort: 'Out 3', toPort: 'PSU', via: '', vlans: '' },
        { from: 'd11', to: 'd7', kind: 'service', service: 'LDAP', port: 389 },
        { from: 'd11', to: 'd8', kind: 'service', service: 'SMB', port: 445 },
        { from: 'd11', to: 'd8', kind: 'service', service: 'HTTPS', port: 443 },
        { from: 'd12', to: 'd7', kind: 'service', service: 'RDP', port: 3389 },
        { from: 'd8', to: 'd10', kind: 'service', service: 'Backup', port: 0 },
        { from: 'd1', to: 'd9', kind: 'service', service: 'Sonstiges', port: 25565 },
        { from: 'd1', to: 'd8', kind: 'service', service: 'HTTPS', port: 443 },
    ],
};

function loadDemo() {
    if (dirty && !confirm('Ungespeicherte Änderungen verwerfen?')) return;
    demo = { net, rev };
    net = JSON.parse(JSON.stringify(DEMO));
    selected = null;
    document.body.classList.add('demo');
    $('demoBar').hidden = false;
    setDirty(false);
    render();
    renderPanel();
    fitView();
}

function exitDemo() {
    if (!demo) return;
    net = demo.net;
    rev = demo.rev;
    demo = null;
    selected = null;
    document.body.classList.remove('demo');
    $('demoBar').hidden = true;
    setDirty(false);
    render();
    renderPanel();
    fitView();
}

function openJson() {
    $('jsonText').value = JSON.stringify(net, null, 2);
    $('jsonDialog').hidden = false;
    $('jsonText').focus();
    $('jsonText').setSelectionRange(0, 0);
    $('jsonText').scrollTop = 0;
}

function submitJson(event) {
    event.preventDefault();
    let parsed;
    try {
        parsed = JSON.parse($('jsonText').value);
    } catch (error) {
        toast('Kein gültiges JSON', true);
        return;
    }
    if (!parsed || !Array.isArray(parsed.devices) || !Array.isArray(parsed.links)) {
        toast('Erwartet werden „devices" und „links"', true);
        return;
    }
    const ids = new Set(parsed.devices.map(d => d && d.id));
    if (parsed.links.some(l => !l || !ids.has(l.from) || !ids.has(l.to))) {
        toast('Eine Verbindung zeigt auf ein unbekanntes Gerät', true);
        return;
    }
    net = parsed;
    selected = null;
    $('jsonDialog').hidden = true;
    changed();
    fitView();
}

function setupSelects() {
    const kinds = $('lKind');
    const groups = {};
    for (const key in MEDIA) {
        const group = MEDIA[key].group;
        if (!groups[group]) {
            groups[group] = document.createElement('optgroup');
            groups[group].label = group;
            kinds.append(groups[group]);
        }
        groups[group].append(new Option(MEDIA[key].label, key));
    }
    const services = document.createElement('optgroup');
    services.label = 'Dienst (logisch)';
    for (const key in SERVICES) services.append(new Option(key, 'svc:' + key));
    kinds.append(services);
}

function syncViewButtons() {
    for (const button of $('viewMode').children) {
        button.classList.toggle('on', button.dataset.value === viewMode);
    }
}

function setViewMode(mode) {
    viewMode = mode;
    selected = null;
    syncViewButtons();
    render();
    renderPanel();
    fitView();
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
            { text: 'Gerät anlegen', run: () => openPicker(false) },
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

    setupSelects();
    setupPanZoom();

    $('addDevice').addEventListener('click', () => openPicker(false));
    $('addFirst').addEventListener('click', () => openPicker(false));
    $('fTypeChip').addEventListener('click', () => {
        $('dialog').hidden = true;
        openPicker(!!editing);
    });
    $('pickerSearch').addEventListener('input', renderPicker);
    $('pickerSearch').addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        const first = $('pickerList').querySelector('.pickerItem');
        if (first) pickType(first.dataset.key);
    });
    $('cancelPicker').addEventListener('click', () => {
        $('pickerDialog').hidden = true;
        if (picking === 'change' && editing) openDevice(editing);
        picking = null;
    });
    $('placeMode').addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        setPlaceMode('placeMode', button.dataset.value);
        applyTypeMask();
    });
    $('auditPlaceMode').addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        setPlaceMode('auditPlaceMode', button.dataset.value);
        applyAuditPlace();
    });
    $('showDemo').addEventListener('click', loadDemo);
    $('demoExit').addEventListener('click', exitDemo);
    $('save').addEventListener('click', save);
    $('deviceForm').addEventListener('submit', submitDevice);
    $('cancelDevice').addEventListener('click', () => { $('dialog').hidden = true; editing = null; });
    $('deleteDevice').addEventListener('click', () => editing && removeDevice(editing));
    $('linkForm').addEventListener('submit', submitLink);
    $('cancelLink').addEventListener('click', closeLink);
    $('lKind').addEventListener('change', updateLinkFields);
    $('deleteLink').addEventListener('click', () => {
        if (linkEdit && linkEdit.index !== null) {
            const index = linkEdit.index;
            closeLink();
            removeLink(index);
        }
    });
    $('auditForm').addEventListener('submit', submitAudit);
    $('cancelAudit').addEventListener('click', () => { $('auditDialog').hidden = true; auditing = null; });
    $('jsonForm').addEventListener('submit', submitJson);
    $('cancelJson').addEventListener('click', () => { $('jsonDialog').hidden = true; });
    $('jsonCopy').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText($('jsonText').value);
            toast('In die Zwischenablage kopiert');
        } catch (error) {
            $('jsonText').select();
            toast('Bitte manuell kopieren', true);
        }
    });
    $('more').addEventListener('click', event => {
        event.stopPropagation();
        openMenu(event, [
            { text: 'JSON bearbeiten', run: openJson },
            { text: demo ? 'Beispiel verlassen' : 'Beispiel ansehen', run: demo ? exitDemo : loadDemo },
            { text: 'Ansicht einpassen', run: fitView },
            { separator: true },
            { text: 'Abmelden', danger: true, run: () => $('logoutForm').submit() },
        ]);
    });
    $('hintCancel').addEventListener('click', cancelConnect);
    $('search').addEventListener('input', event => {
        search = event.target.value.trim().toLowerCase();
        selected = null;
        render();
        renderPanel();
    });
    $('viewMode').addEventListener('click', event => {
        const button = event.target.closest('button');
        if (button) setViewMode(button.dataset.value);
    });
    $('powerToggle').addEventListener('click', () => {
        showPower = !showPower;
        render();
    });

    document.addEventListener('click', closeMenu);
    document.addEventListener('keydown', event => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            save();
            return;
        }
        if (event.key !== 'Escape') return;
        closeMenu();
        cancelConnect();
        $('dialog').hidden = true;
        $('pickerDialog').hidden = true;
        $('jsonDialog').hidden = true;
        $('auditDialog').hidden = true;
        picking = null;
        auditing = null;
        closeLink();
        clearSelection();
    });
    window.addEventListener('resize', render);
    window.addEventListener('beforeunload', event => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    syncViewButtons();
    render();
    fitView();
}

init();
})();
</script>
</body>
</html>
