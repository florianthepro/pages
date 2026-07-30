<?php
declare(strict_types=1);
// LIARC Kernbibliothek. Wird von allen Routen geladen (via Loader-Cache oder lokal).
if (defined('LIARC_LIB')) return;
define('LIARC_LIB', 1);

const LIARC_LANGS = ['de', 'en', 'th'];
const LIARC_LANG_DEFAULT = 'en';
const LIARC_PBKDF2_ITERS = 310000;
const LIARC_PASSWORD_MIN = 10;
const LIARC_SESSION_LIFETIME = 2592000;
const LIARC_TOKEN_PREFIX = 'liarc_';
const LIARC_LOGIN_MAX_TRIES = 8;
const LIARC_LOGIN_WINDOW = 900;
const LIARC_FIELD_TYPES = ['text', 'number', 'date', 'phone', 'note'];

function liarc_boot(array $vars): void {
    $GLOBALS['LIARC'] = [
        'title' => (string)($vars['liarc_title'] ?? 'LIARC'),
        'repo' => (string)($vars['liarc_repo'] ?? 'https://raw.githubusercontent.com/florianthepro/pages/main/content'),
        'data' => (string)($vars['liarc_datadir'] ?? (dirname($_SERVER['SCRIPT_FILENAME'] ?? __DIR__).'/liarc-data')),
    ];
    if (!function_exists('openssl_encrypt') && !function_exists('sodium_crypto_secretbox')) {
        http_response_code(500);
        exit('LIARC requires the PHP openssl or sodium extension.');
    }
    liarc_store_init();
}

function liarc_cfg(string $k): string { return $GLOBALS['LIARC'][$k]; }
function liarc_refresh(): bool { return isset($_GET['_refresh']) && $_GET['_refresh'] === '1'; }

// Repo-Datei als lokalen Pfad (Loader-Cache; lokale Entwicklung: direkt aus dem Repo)
function liarc_repo_file(string $rel): ?string {
    if (function_exists('app_get_local_script')) {
        return app_get_local_script(liarc_cfg('repo').'/'.$rel, liarc_refresh(), 300);
    }
    $p = dirname(__DIR__, 2).'/'.$rel;
    return is_file($p) ? $p : null;
}

// ---- util ----------------------------------------------------------------

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function liarc_b64e(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
function liarc_b64d(string $s): ?string { $b = base64_decode(strtr($s, '-_', '+/'), true); return $b === false ? null : $b; }
function liarc_id(int $bytes = 9): string { return liarc_b64e(random_bytes($bytes)); }
function liarc_now(): int { return time(); }
function liarc_cut(string $s, int $n): string { return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n); }
function liarc_client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

function liarc_is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function liarc_valid_date(string $date): bool {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

function liarc_years_since(string $date): ?int {
    if (!liarc_valid_date($date)) return null;
    $d = new DateTimeImmutable($date);
    $now = new DateTimeImmutable('today');
    return $d > $now ? null : (int)$d->diff($now)->y;
}

// ---- crypto --------------------------------------------------------------

function liarc_kdf_password(string $password, string $salt, int $iters = LIARC_PBKDF2_ITERS): string {
    return hash_pbkdf2('sha256', $password, $salt, $iters, 32, true);
}

function liarc_kdf_secret(string $secret, string $info): string {
    return hash_hkdf('sha256', $secret, 32, $info);
}

function liarc_encrypt(string $plain, string $key): string {
    if (function_exists('openssl_encrypt')) {
        $nonce = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ct === false) throw new RuntimeException('encryption failed');
        return liarc_b64e("\x01".$nonce.$tag.$ct);
    }
    $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
    return liarc_b64e("\x02".$nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, '', $nonce, $key));
}

function liarc_decrypt(string $blob, string $key): ?string {
    $raw = liarc_b64d($blob);
    if ($raw === null || strlen($raw) < 2) return null;
    if ($raw[0] === "\x01") {
        if (strlen($raw) < 29 || !function_exists('openssl_decrypt')) return null;
        $pt = openssl_decrypt(substr($raw, 29), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 1, 12), substr($raw, 13, 16));
        return $pt === false ? null : $pt;
    }
    if ($raw[0] === "\x02") {
        $n = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($raw) < 1 + $n + 17) return null;
        $pt = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($raw, 1 + $n), '', substr($raw, 1, $n), $key);
        return $pt === false ? null : $pt;
    }
    return null;
}

function liarc_encrypt_json(array $data, string $key): string {
    return liarc_encrypt(json_encode($data, JSON_UNESCAPED_UNICODE), $key);
}

function liarc_decrypt_json(string $blob, string $key): ?array {
    $pt = liarc_decrypt($blob, $key);
    if ($pt === null) return null;
    $d = json_decode($pt, true);
    return is_array($d) ? $d : null;
}

// ---- store ---------------------------------------------------------------

function liarc_store_init(): void {
    $d = liarc_cfg('data');
    foreach ([$d, $d.'/users', $d.'/sessions', $d.'/ratelimit', $d.'/tmp'] as $dir) {
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
    }
    if (!is_file($d.'/.htaccess')) {
        @file_put_contents($d.'/.htaccess',
            "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
}

function liarc_read_json(string $path): ?array {
    if (!is_readable($path)) return null;
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $d = json_decode($raw, true);
    return is_array($d) ? $d : null;
}

function liarc_write_json(string $path, array $data): bool {
    $tmp = liarc_cfg('data').'/tmp/'.basename($path).'.'.liarc_id(6).'.tmp';
    if (file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) return false;
    if (!rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}

function liarc_uid(string $username): string {
    return substr(hash('sha256', 'liarc-user:'.strtolower(trim($username))), 0, 24);
}

function liarc_user_dir(string $uid): string { return liarc_cfg('data').'/users/'.$uid; }
function liarc_user_exists(string $uid): bool { return is_file(liarc_user_dir($uid).'/auth.json'); }

function liarc_user_lock(string $uid): mixed {
    $fh = fopen(liarc_user_dir($uid).'/.lock', 'c');
    if ($fh === false) return null;
    flock($fh, LOCK_EX);
    return $fh;
}

function liarc_user_unlock(mixed $fh): void {
    if (is_resource($fh)) { flock($fh, LOCK_UN); fclose($fh); }
}

function liarc_rate_check(string $action, int $max, int $window): bool {
    $file = liarc_cfg('data').'/ratelimit/'.hash('sha256', $action.':'.liarc_client_ip()).'.json';
    $now = liarc_now();
    $hits = array_values(array_filter(liarc_read_json($file) ?? [], fn($t) => is_int($t) && $t > $now - $window));
    if (count($hits) >= $max) return false;
    $hits[] = $now;
    liarc_write_json($file, $hits);
    return true;
}

// ---- i18n ----------------------------------------------------------------

function liarc_i18n_init(): void {
    $lang = null;
    if (isset($_GET['lang']) && in_array($_GET['lang'], LIARC_LANGS, true)) {
        $lang = $_GET['lang'];
        setcookie('liarc_lang', $lang, [
            'expires' => liarc_now() + 31536000, 'path' => '/',
            'secure' => liarc_is_https(), 'httponly' => false, 'samesite' => 'Lax',
        ]);
    } elseif (isset($_COOKIE['liarc_lang']) && in_array($_COOKIE['liarc_lang'], LIARC_LANGS, true)) {
        $lang = $_COOKIE['liarc_lang'];
    } else {
        $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        foreach (LIARC_LANGS as $l) if (str_contains($accept, $l)) { $lang = $l; break; }
    }
    $lang = $lang ?? LIARC_LANG_DEFAULT;
    $file = liarc_repo_file('routes/liarc/lang-'.$lang.'.php');
    if ($file === null) $file = liarc_repo_file('routes/liarc/lang-'.LIARC_LANG_DEFAULT.'.php');
    $GLOBALS['liarc_lang'] = $lang;
    $GLOBALS['liarc_strings'] = $file !== null ? (require $file) : [];
}

function liarc_lang(): string { return $GLOBALS['liarc_lang'] ?? LIARC_LANG_DEFAULT; }

function t(string $key, array $vars = []): string {
    $s = $GLOBALS['liarc_strings'][$key] ?? $key;
    foreach ($vars as $k => $v) $s = str_replace('{'.$k.'}', (string)$v, $s);
    return $s;
}

function liarc_next_lang(): string {
    $i = array_search(liarc_lang(), LIARC_LANGS, true);
    return LIARC_LANGS[($i === false ? 0 : $i + 1) % count(LIARC_LANGS)];
}

// ---- http / ui -----------------------------------------------------------

function liarc_url(string $page = 'index', array $params = []): string {
    return '?'.http_build_query(['_page' => $page] + $params);
}

function liarc_redirect(string $page = 'index', array $params = []): never {
    header('Location: '.liarc_url($page, $params));
    exit;
}

function liarc_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

function liarc_json(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function liarc_json_error(string $error, int $code = 400): never {
    liarc_json(['ok' => false, 'error' => $error], $code);
}

function liarc_input(): array {
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $d = json_decode(file_get_contents('php://input') ?: '', true);
        return is_array($d) ? $d : [];
    }
    return $_POST;
}

function liarc_set_page(string $p): void { $GLOBALS['liarc_page'] = $p; }

function ic(string $name, string $alt = ''): string {
    return '<img class="ic" src="'.h(liarc_url('assets', ['f' => 'icon-'.$name])).'" alt="'.h($alt).'"'.($alt !== '' ? ' title="'.h($alt).'"' : '').'>';
}

function liarc_head(string $title, bool $bare = false): void {
    liarc_headers();
    $authed = liarc_user() !== null;
    echo '<!doctype html><html lang="'.h(liarc_lang()).'"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">';
    echo '<meta name="color-scheme" content="dark"><meta name="theme-color" content="#0e0e12">';
    echo '<meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';
    echo '<title>'.h($title !== '' ? $title.' – '.liarc_cfg('title') : liarc_cfg('title')).'</title>';
    echo '<link rel="icon" type="image/svg+xml" href="'.h(liarc_url('assets', ['f' => 'icon-liarc'])).'">';
    echo '<link rel="apple-touch-icon" href="'.h(liarc_url('assets', ['f' => 'icon-liarc'])).'">';
    echo '<link rel="manifest" href="'.h(liarc_url('assets', ['f' => 'manifest'])).'">';
    echo '<link rel="stylesheet" href="'.h(liarc_url('assets', ['f' => 'css'])).'">';
    echo '</head><body data-authed="'.($authed ? '1' : '0').'" data-csrf="'.($authed ? h(liarc_csrf_token()) : '').'" data-page="'.h((string)($GLOBALS['liarc_page'] ?? $_GET['_page'] ?? 'index')).'">';
    if (!$bare) {
        echo '<header class="topbar"><a class="brand" href="'.h(liarc_url()).'">'.ic('liarc').'<span>'.h(liarc_cfg('title')).'</span></a><nav class="nav">';
        echo '<a href="'.h(liarc_url('devices')).'">'.ic('devices', t('nav.devices')).'</a>';
        echo '<a href="'.h(liarc_url('settings')).'">'.ic('gear', t('nav.settings')).'</a>';
        echo '<a href="'.h(liarc_url((string)($_GET['_page'] ?? 'index'), ['lang' => liarc_next_lang()])).'" class="lang">'.ic('globe', strtoupper(liarc_next_lang())).'</a>';
        echo '<form method="post" action="'.h(liarc_url('auth', ['v' => 'logout'])).'" class="inline"><input type="hidden" name="csrf" value="'.h(liarc_csrf_token()).'"><button type="submit" class="iconbtn">'.ic('logout', t('nav.logout')).'</button></form>';
        echo '</nav></header>';
    }
    echo '<main class="main'.($bare ? ' centered' : '').'">';
}

function liarc_foot(): void {
    echo '</main><script src="'.h(liarc_url('assets', ['f' => 'js'])).'"></script></body></html>';
    exit;
}

// ---- auth / session ------------------------------------------------------

function liarc_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_save_path(liarc_cfg('data').'/sessions');
    ini_set('session.gc_maxlifetime', (string)LIARC_SESSION_LIFETIME);
    session_set_cookie_params([
        'lifetime' => LIARC_SESSION_LIFETIME, 'path' => '/',
        'secure' => liarc_is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_name('liarc_session');
    session_start();
}

function liarc_session_login(string $uid, string $dek, ?string $deviceId = null): void {
    liarc_session_start();
    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    $_SESSION['dek'] = liarc_b64e($dek);
    $_SESSION['device'] = $deviceId;
    $_SESSION['csrf'] = liarc_id(24);
}

function liarc_session_logout(): void {
    liarc_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => liarc_now() - 3600, 'path' => $p['path'],
            'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'],
        ]);
    }
    session_destroy();
}

function liarc_user(): ?array {
    liarc_session_start();
    if (empty($_SESSION['uid']) || empty($_SESSION['dek'])) return null;
    $dek = liarc_b64d($_SESSION['dek']);
    if ($dek === null || strlen($dek) !== 32) return null;
    return ['uid' => $_SESSION['uid'], 'dek' => $dek, 'device' => $_SESSION['device'] ?? null];
}

function liarc_require_user(): array {
    $u = liarc_user();
    if ($u === null) liarc_redirect('auth', ['v' => 'login']);
    return $u;
}

function liarc_csrf_token(): string {
    liarc_session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = liarc_id(24);
    return $_SESSION['csrf'];
}

function liarc_csrf_check(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(liarc_csrf_token(), $token)) {
        http_response_code(403);
        exit('invalid csrf token');
    }
}

function liarc_valid_username(string $u): bool {
    return (bool)preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $u);
}

function liarc_register(string $username, string $password): array {
    if (!liarc_valid_username($username)) return ['error' => 'auth.err_username'];
    if (strlen($password) < LIARC_PASSWORD_MIN) return ['error' => 'auth.err_password'];
    $uid = liarc_uid($username);
    if (liarc_user_exists($uid)) return ['error' => 'auth.err_exists'];
    $dir = liarc_user_dir($uid);
    if (!is_dir($dir) && !@mkdir($dir, 0770, true)) return ['error' => 'auth.err_server'];
    $dek = random_bytes(32);
    $salt = random_bytes(16);
    $auth = [
        'username' => $username,
        'pw_hash' => password_hash($password, PASSWORD_DEFAULT),
        'kdf' => ['alg' => 'pbkdf2-sha256', 'salt' => liarc_b64e($salt), 'iters' => LIARC_PBKDF2_ITERS],
        'dek_wrap' => liarc_encrypt($dek, liarc_kdf_password($password, $salt)),
        'created' => liarc_now(),
    ];
    if (!liarc_write_json($dir.'/auth.json', $auth)) return ['error' => 'auth.err_server'];
    liarc_write_json($dir.'/devices.json', []);
    liarc_vault_save($uid, $dek, ['categories' => liarc_default_categories(), 'entries' => []]);
    return ['uid' => $uid, 'dek' => $dek];
}

function liarc_login(string $username, string $password): array {
    if (!liarc_rate_check('login', LIARC_LOGIN_MAX_TRIES, LIARC_LOGIN_WINDOW)) return ['error' => 'auth.err_rate'];
    $uid = liarc_uid($username);
    $auth = liarc_read_json(liarc_user_dir($uid).'/auth.json');
    if ($auth === null || !password_verify($password, $auth['pw_hash'] ?? '')) return ['error' => 'auth.err_login'];
    $salt = liarc_b64d($auth['kdf']['salt'] ?? '');
    if ($salt === null) return ['error' => 'auth.err_server'];
    $dek = liarc_decrypt($auth['dek_wrap'] ?? '', liarc_kdf_password($password, $salt, (int)($auth['kdf']['iters'] ?? LIARC_PBKDF2_ITERS)));
    if ($dek === null || strlen($dek) !== 32) return ['error' => 'auth.err_server'];
    return ['uid' => $uid, 'dek' => $dek, 'username' => $auth['username']];
}

function liarc_change_password(string $uid, string $dek, string $old, string $new): ?string {
    if (strlen($new) < LIARC_PASSWORD_MIN) return 'auth.err_password';
    $lock = liarc_user_lock($uid);
    $file = liarc_user_dir($uid).'/auth.json';
    $auth = liarc_read_json($file);
    if ($auth === null || !password_verify($old, $auth['pw_hash'] ?? '')) {
        liarc_user_unlock($lock);
        return 'auth.err_login';
    }
    $salt = random_bytes(16);
    $auth['pw_hash'] = password_hash($new, PASSWORD_DEFAULT);
    $auth['kdf'] = ['alg' => 'pbkdf2-sha256', 'salt' => liarc_b64e($salt), 'iters' => LIARC_PBKDF2_ITERS];
    $auth['dek_wrap'] = liarc_encrypt($dek, liarc_kdf_password($new, $salt));
    liarc_write_json($file, $auth);
    liarc_user_unlock($lock);
    return null;
}

function liarc_username(string $uid): string {
    return liarc_read_json(liarc_user_dir($uid).'/auth.json')['username'] ?? '';
}

// ---- devices / tokens ----------------------------------------------------

function liarc_devices_load(string $uid): array {
    return liarc_read_json(liarc_user_dir($uid).'/devices.json') ?? [];
}

function liarc_devices_save(string $uid, array $devices): void {
    liarc_write_json(liarc_user_dir($uid).'/devices.json', $devices);
}

function liarc_device_create(string $uid, string $dek, string $name, string $type = 'web'): array {
    $id = liarc_id(8);
    $secret = random_bytes(32);
    $lock = liarc_user_lock($uid);
    $devices = liarc_devices_load($uid);
    $devices[$id] = [
        'secret_hash' => hash('sha256', $secret),
        'dek_wrap' => liarc_encrypt($dek, liarc_kdf_secret($secret, 'liarc-device-wrap:'.$id)),
        'name' => liarc_cut($name, 80),
        'type' => $type === 'api' ? 'api' : 'web',
        'created' => liarc_now(),
        'last_seen' => liarc_now(),
    ];
    liarc_devices_save($uid, $devices);
    liarc_user_unlock($lock);
    return ['id' => $id, 'token' => LIARC_TOKEN_PREFIX.$id.'_'.liarc_b64e($secret)];
}

function liarc_token_auth(string $username, string $token): ?array {
    if (!str_starts_with($token, LIARC_TOKEN_PREFIX)) return null;
    $parts = explode('_', substr($token, strlen(LIARC_TOKEN_PREFIX)), 2);
    if (count($parts) !== 2) return null;
    [$id, $secretB64] = $parts;
    $secret = liarc_b64d($secretB64);
    if ($secret === null || strlen($secret) !== 32) return null;
    $uid = liarc_uid($username);
    $dev = liarc_devices_load($uid)[$id] ?? null;
    if ($dev === null || !hash_equals($dev['secret_hash'], hash('sha256', $secret))) return null;
    $dek = liarc_decrypt($dev['dek_wrap'], liarc_kdf_secret($secret, 'liarc-device-wrap:'.$id));
    if ($dek === null || strlen($dek) !== 32) return null;
    $lock = liarc_user_lock($uid);
    $devices = liarc_devices_load($uid);
    if (isset($devices[$id])) {
        $devices[$id]['last_seen'] = liarc_now();
        liarc_devices_save($uid, $devices);
    }
    liarc_user_unlock($lock);
    return ['uid' => $uid, 'dek' => $dek, 'device' => $id];
}

function liarc_device_revoke(string $uid, string $id): bool {
    $lock = liarc_user_lock($uid);
    $devices = liarc_devices_load($uid);
    if (!isset($devices[$id])) { liarc_user_unlock($lock); return false; }
    unset($devices[$id]);
    liarc_devices_save($uid, $devices);
    liarc_user_unlock($lock);
    return true;
}

function liarc_devices_list(string $uid, ?string $currentId): array {
    $out = [];
    foreach (liarc_devices_load($uid) as $id => $d) {
        $out[] = ['id' => $id, 'name' => $d['name'], 'type' => $d['type'],
            'created' => $d['created'], 'last_seen' => $d['last_seen'], 'current' => $id === $currentId];
    }
    usort($out, fn($a, $b) => $b['last_seen'] <=> $a['last_seen']);
    return $out;
}

function liarc_api_auth(): ?array {
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $user = $_SERVER['HTTP_X_LIARC_USER'] ?? '';
    if ($user !== '' && preg_match('/^Bearer\s+(\S+)$/i', $hdr, $m)) return liarc_token_auth($user, $m[1]);
    return null;
}

// ---- vault ---------------------------------------------------------------

function liarc_vault_file(string $uid): string { return liarc_user_dir($uid).'/vault.enc'; }

function liarc_vault_load(string $uid, string $dek): ?array {
    $blob = @file_get_contents(liarc_vault_file($uid));
    return $blob === false ? null : liarc_decrypt_json(trim($blob), $dek);
}

function liarc_vault_save(string $uid, string $dek, array $vault): bool {
    $file = liarc_vault_file($uid);
    if (is_file($file)) @copy($file, $file.'.bak');
    $tmp = liarc_cfg('data').'/tmp/vault.'.liarc_id(6).'.tmp';
    if (file_put_contents($tmp, liarc_encrypt_json($vault, $dek), LOCK_EX) === false) return false;
    if (!rename($tmp, $file)) { @unlink($tmp); return false; }
    return true;
}

// Alle Lebensbereiche sind von Anfang an da; Namen/Feld-Labels via lkey uebersetzt
function liarc_default_categories(): array {
    $now = liarc_now();
    $cat = fn(string $key, string $icon, string $kind, string $unit, array $fields) => [
        'id' => liarc_id(), 'key' => $key, 'name' => '', 'icon' => $icon,
        'kind' => $kind, 'unit' => $unit, 'fields' => $fields, 'created' => $now,
    ];
    $f = fn(string $key, string $type) => ['key' => $key, 'label' => '', 'lkey' => 'f.'.$key, 'type' => $type];
    return [
        $cat('profile', 'user', 'records', '', [$f('name', 'text'), $f('birthdate', 'date'), $f('blood', 'text'), $f('note', 'note')]),
        $cat('people', 'users', 'records', '', [$f('name', 'text'), $f('relation', 'text'), $f('birthdate', 'date'), $f('number', 'phone'), $f('note', 'note')]),
        $cat('phones', 'phone', 'records', '', [$f('label', 'text'), $f('number', 'phone')]),
        $cat('heart', 'heart', 'series', 'bpm', []),
        $cat('weight', 'scale', 'series', 'kg', []),
        $cat('height', 'ruler', 'series', 'cm', []),
        $cat('steps', 'steps', 'series', '', []),
        $cat('sleep', 'moon', 'series', 'h', []),
        $cat('temp', 'thermo', 'series', '°C', []),
        $cat('medical', 'pill', 'records', '', [$f('label', 'text'), $f('date', 'date'), $f('note', 'note')]),
        $cat('documents', 'card', 'records', '', [$f('label', 'text'), $f('number', 'text'), $f('date', 'date'), $f('note', 'note')]),
        $cat('notes', 'note', 'records', '', [$f('title', 'text'), $f('note', 'note')]),
    ];
}

function liarc_cat_name(array $cat): string {
    if (($cat['name'] ?? '') !== '') return $cat['name'];
    return isset($cat['key']) ? t('cat.'.$cat['key']) : t('cat.custom');
}

function liarc_field_label(array $field): string {
    if (($field['label'] ?? '') !== '') return $field['label'];
    return isset($field['lkey']) ? t($field['lkey']) : $field['key'];
}

function liarc_category_get(array $vault, string $catId): ?array {
    foreach ($vault['categories'] as $c) if ($c['id'] === $catId) return $c;
    return null;
}

function liarc_category_normalize(array $in): array {
    $name = trim((string)($in['name'] ?? ''));
    $kind = (string)($in['kind'] ?? 'records');
    if ($name === '' || strlen($name) > 120) return ['error' => 'err.name'];
    if (!in_array($kind, ['series', 'records'], true)) return ['error' => 'err.kind'];
    $fields = [];
    $seen = [];
    if ($kind === 'records') {
        foreach ((array)($in['fields'] ?? []) as $fd) {
            $label = trim((string)($fd['label'] ?? ''));
            $type = (string)($fd['type'] ?? 'text');
            if ($label === '') continue;
            if (!in_array($type, LIARC_FIELD_TYPES, true)) $type = 'text';
            $key = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label)), '_');
            if ($key === '') $key = 'f'.count($fields);
            while (isset($seen[$key])) $key .= '_';
            $seen[$key] = true;
            $fields[] = ['key' => $key, 'label' => liarc_cut($label, 40), 'type' => $type];
        }
        if (count($fields) === 0) return ['error' => 'err.fields'];
    }
    return ['category' => [
        'id' => liarc_id(), 'key' => null, 'name' => liarc_cut($name, 60),
        'icon' => 'folder', 'kind' => $kind,
        'unit' => liarc_cut(trim((string)($in['unit'] ?? '')), 12),
        'fields' => $fields, 'created' => liarc_now(),
    ]];
}

function liarc_entry_build(array $cat, array $in): array {
    $now = liarc_now();
    if ($cat['kind'] === 'series') {
        $value = $in['value'] ?? null;
        if (!is_numeric($value)) return ['error' => 'err.value'];
        $at = $now;
        if (!empty($in['at'])) {
            $ts = strtotime((string)$in['at']);
            if ($ts === false) return ['error' => 'err.date'];
            $at = $ts;
        }
        return ['entry' => ['id' => liarc_id(), 'value' => (float)$value, 'at' => $at,
            'note' => liarc_cut(trim((string)($in['note'] ?? '')), 200), 'created' => $now, 'updated' => $now]];
    }
    $fields = [];
    foreach ($cat['fields'] as $fd) {
        $v = trim((string)($in['field_'.$fd['key']] ?? ($in['fields'][$fd['key']] ?? '')));
        if ($v !== '') {
            if ($fd['type'] === 'number' && !is_numeric($v)) return ['error' => 'err.value'];
            if ($fd['type'] === 'date' && !liarc_valid_date($v)) return ['error' => 'err.date'];
        }
        $fields[$fd['key']] = liarc_cut($v, $fd['type'] === 'note' ? 2000 : 200);
    }
    if (implode('', $fields) === '') return ['error' => 'err.empty'];
    return ['entry' => ['id' => liarc_id(), 'fields' => $fields, 'status' => 'active',
        'created' => $now, 'updated' => $now]];
}

function liarc_category_stats(array $cat, array $entries): array {
    if ($cat['kind'] === 'series') {
        $values = array_map(fn($e) => (float)$e['value'], $entries);
        if (count($values) === 0) return ['count' => 0, 'min' => null, 'max' => null, 'avg' => null, 'latest' => null];
        $sorted = $entries;
        usort($sorted, fn($a, $b) => $a['at'] <=> $b['at']);
        $last = end($sorted);
        return ['count' => count($values), 'min' => min($values), 'max' => max($values),
            'avg' => round(array_sum($values) / count($values), 2),
            'latest' => ['value' => $last['value'], 'at' => $last['at']]];
    }
    $active = count(array_filter($entries, fn($e) => ($e['status'] ?? 'active') === 'active'));
    return ['count' => count($entries), 'active' => $active, 'old' => count($entries) - $active];
}

function liarc_series_points(array $entries): array {
    $points = array_map(fn($e) => ['at' => $e['at'], 'value' => (float)$e['value']], $entries);
    usort($points, fn($a, $b) => $a['at'] <=> $b['at']);
    return $points;
}

function liarc_field_display(array $field, string $value): string {
    if ($value === '') return '';
    if ($field['type'] === 'date') {
        $years = liarc_years_since($value);
        if ($years !== null) return $value.' · '.$years;
    }
    return $value;
}
