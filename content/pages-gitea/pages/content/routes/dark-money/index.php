<?php
/**
 * Dark-Money — single-file wallet node (hub route build).
 *
 * One PHP file. No admin account. No BTC private keys stored.
 *
 * Hub-integration notes:
 *   - Under the remote loader, __DIR__ is the ephemeral temp cache dir. Every
 *     writable path (SQLite DB, WAL, guard.log, self-healed .htaccess) is taken
 *     from $darkmoney_datadir, an ABSOLUTE path supplied and pre-created by the
 *     instance. Nothing is written into __DIR__.
 *   - The route runs inside the loader closure (via extract($sharedVars)), so
 *     cross-function state is passed through $GLOBALS explicitly rather than via
 *     `global $x` on a top-level script variable.
 *
 * Surface rule:
 *   - The interface shows USD only. Percentages never appear.
 *   - BTC appears only in the info view.
 *
 * Value rule (constancy):
 *   - A least-squares line is fitted over the live BTC/USD samples and evaluated
 *     "now" -> target. The platform rate is a braked anchor that may drift toward
 *     that target by at most SMT_MAX_MOVE_DAY per day.
 */

declare(strict_types=1);

/* ------------------------------------------------------------------ config */

const SMT_BRAND        = 'DARK-MONEY';
const SMT_ACTIVATE_USD = 10.0;   // credited USD required to activate
const SMT_FEE_USD      = 1.0;    // flat fiat fee on incoming value
const SMT_PRICE_TTL    = 180;    // seconds between live price fetches
const SMT_MAX_SAMPLES  = 240;    // price samples kept for the line fit
const SMT_MAX_MOVE_DAY = 0.02;   // brake: max platform-rate drift per day (2%)
const SMT_ACCRUE_CAP   = 2.0;    // brake: max days of drift applied in one step
const SMT_DEMO         = false;  // true -> allow in-UI deposit simulation

const SMT_MAX_FAILS    = 5;      // failed auth attempts before lockout
const SMT_LOCK_SECONDS = 300;    // lockout cooldown window in seconds

/* Writable base comes from the instance (absolute), never from __DIR__. */
$__dmDataDir = (isset($darkmoney_datadir) && is_string($darkmoney_datadir) && $darkmoney_datadir !== '')
    ? $darkmoney_datadir
    : (__DIR__ . '/data');
$DATA = $__dmDataDir;
$DB   = $DATA . '/app.sqlite';
$GLOBALS['DM_DB'] = $DB;

/* Theme: light | dark | auto (default auto = follow OS via prefers-color-scheme). */
$__dmTheme = (isset($darkmoney_theme) && in_array($darkmoney_theme, ['light', 'dark'], true))
    ? $darkmoney_theme
    : 'auto';
$GLOBALS['DM_THEME'] = $__dmTheme;
$GLOBALS['DM_ICON'] = (isset($darkmoney_icon) && is_string($darkmoney_icon)) ? $darkmoney_icon : '';

/* --------------------------------------------------------------- bootstrap */

error_reporting(E_ALL);
ini_set('display_errors', '0');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => $https,
    'samesite' => 'Strict',
]);
session_name('dm_sid');
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action 'self'");
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

/* ------------------------------------------------------- install / selfheal */

/** Contents used to seal the data directory off from the web. */
function htaccess_data(): string {
    return "Require all denied\nDeny from all\n";
}

/**
 * Ensure the data directory's protection files exist. Runs on every request:
 * if the Deny-all .htaccess or the 403 guard index.php is removed it is written
 * back immediately (self-heal) and the event is logged. Everything lives under
 * the instance-supplied data dir; nothing is ever written into __DIR__.
 */
function selfheal(string $data): array {
    $healed = [];
    if (!is_dir($data)) {
        @mkdir($data, 0770, true);
    }
    $checks = [
        $data . '/.htaccess' => htaccess_data(),
        $data . '/index.php' => "<?php http_response_code(403); exit;\n",
    ];
    foreach ($checks as $path => $want) {
        $have = is_file($path) ? (string)@file_get_contents($path) : null;
        if ($have !== $want) {
            @file_put_contents($path, $want, LOCK_EX);
            @chmod($path, 0644);
            $healed[] = basename($path);
        }
    }
    if ($healed) {
        @file_put_contents(
            $data . '/guard.log',
            gmdate('c') . ' restored: ' . implode(', ', $healed) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
    return $healed;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . (string)($GLOBALS['DM_DB'] ?? ''), null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
    }
    return $pdo;
}

function install(string $data): void {
    if (!is_dir($data)) {
        @mkdir($data, 0770, true);
    }
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id           INTEGER PRIMARY KEY,
        account_id   TEXT UNIQUE NOT NULL,   -- = stored wallet address
        address      TEXT UNIQUE NOT NULL,
        pass_hash    TEXT NOT NULL,
        totp_secret  TEXT NOT NULL,
        sat_balance  INTEGER NOT NULL DEFAULT 0,
        active       INTEGER NOT NULL DEFAULT 0,
        created_at   INTEGER NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ledger (
        id         INTEGER PRIMARY KEY,
        user_id    INTEGER NOT NULL,
        sat_delta  INTEGER NOT NULL,
        kind       TEXT NOT NULL,
        txid       TEXT,
        created_at INTEGER NOT NULL
    )");
    // SECURITY FIX 1 (ingest idempotency): at most one 'credit' row per txid.
    // Partial unique index so fee rows and null txids are unaffected. Guarded
    // so an existing table with legacy duplicate credit txids cannot fatal the
    // install (the runtime pre-check in credit() still enforces idempotency).
    try {
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_ledger_credit_txid
            ON ledger(txid) WHERE kind='credit' AND txid IS NOT NULL");
    } catch (PDOException $e) {
        // legacy duplicates present -> rely on the credit() pre-check instead
    }
    // SECURITY FIX 2 (auth throttle): failed-attempt counters keyed per
    // account-id and per client IP, with a cooldown lockout window.
    $pdo->exec("CREATE TABLE IF NOT EXISTS auth_throttle (
        k            TEXT PRIMARY KEY,
        fails        INTEGER NOT NULL DEFAULT 0,
        locked_until INTEGER NOT NULL DEFAULT 0,
        updated_at   INTEGER NOT NULL DEFAULT 0
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS price_samples (
        ts    INTEGER PRIMARY KEY,
        price REAL NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS meta (
        k TEXT PRIMARY KEY,
        v TEXT NOT NULL
    )");
    // generate a server-to-server ingest key once
    $st = $pdo->prepare("INSERT OR IGNORE INTO meta(k,v) VALUES('ingest_key',?)");
    $st->execute([bin2hex(random_bytes(24))]);
    $st = $pdo->prepare("INSERT OR IGNORE INTO meta(k,v) VALUES('installed_at',?)");
    $st->execute([(string)time()]);
}

$HEALED = selfheal($DATA);
$GLOBALS['HEALED'] = $HEALED;
install($DATA);

/* ------------------------------------------------------------- small utils */

function meta_get(string $k): ?string {
    $st = db()->prepare("SELECT v FROM meta WHERE k=?");
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string)$v;
}

function meta_set(string $k, string $v): void {
    db()->prepare("INSERT INTO meta(k,v) VALUES(?,?)
                   ON CONFLICT(k) DO UPDATE SET v=excluded.v")
        ->execute([$k, $v]);
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}
function csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function flash(?string $set = null): ?string {
    if ($set !== null) { $_SESSION['flash'] = $set; return null; }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}

function client_ip(): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'local');
}

/* ----------------------------------------------- auth throttle / lockout */

function throttle_state(string $key): array {
    $st = db()->prepare("SELECT fails, locked_until FROM auth_throttle WHERE k=?");
    $st->execute([$key]);
    $row = $st->fetch();
    return $row ?: ['fails' => 0, 'locked_until' => 0];
}

/** Seconds of lockout remaining for $key, or 0 if not currently locked. */
function throttle_locked(string $key): int {
    $s = throttle_state($key);
    $rem = (int)$s['locked_until'] - time();
    return $rem > 0 ? $rem : 0;
}

function throttle_fail(string $key): void {
    $s = throttle_state($key);
    $fails = (int)$s['fails'];
    $lockedUntil = (int)$s['locked_until'];
    // a cooldown that already elapsed opens a fresh attempt window
    if ($lockedUntil > 0 && time() >= $lockedUntil) {
        $fails = 0;
    }
    $fails++;
    $lock = $fails >= SMT_MAX_FAILS ? time() + SMT_LOCK_SECONDS : 0;
    db()->prepare("INSERT INTO auth_throttle(k,fails,locked_until,updated_at) VALUES(?,?,?,?)
                   ON CONFLICT(k) DO UPDATE SET
                     fails=excluded.fails,
                     locked_until=excluded.locked_until,
                     updated_at=excluded.updated_at")
        ->execute([$key, $fails, $lock, time()]);
}

function throttle_clear(string $key): void {
    db()->prepare("DELETE FROM auth_throttle WHERE k=?")->execute([$key]);
}

/* ----------------------------------------------------------------- TOTP/MFA */

function b32_encode(string $bin): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = ''; $bits = 0; $val = 0;
    foreach (str_split($bin) as $c) {
        $val = ($val << 8) | ord($c); $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $out .= $alphabet[($val >> $bits) & 31];
        }
    }
    if ($bits > 0) {
        $out .= $alphabet[($val << (5 - $bits)) & 31];
    }
    return $out;
}
function b32_decode(string $b32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $bits = 0; $val = 0; $out = '';
    foreach (str_split($b32) as $c) {
        $val = ($val << 5) | strpos($alphabet, $c); $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $out .= chr(($val >> $bits) & 0xFF);
        }
    }
    return $out;
}
function totp_now(string $secretB32, int $t = 0, int $step = 30): string {
    $t = $t ?: time();
    $counter = intdiv($t, $step);
    $bin = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $bin, b32_decode($secretB32), true);
    $off  = ord($hash[19]) & 0x0F;
    $code = ((ord($hash[$off]) & 0x7F) << 24)
          | ((ord($hash[$off + 1]) & 0xFF) << 16)
          | ((ord($hash[$off + 2]) & 0xFF) << 8)
          | (ord($hash[$off + 3]) & 0xFF);
    return str_pad((string)($code % 1000000), 6, '0', STR_PAD_LEFT);
}
function totp_verify(string $secretB32, string $code): bool {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    for ($w = -1; $w <= 1; $w++) {          // small clock-skew window
        if (hash_equals(totp_now($secretB32, time() + $w * 30), $code)) {
            return true;
        }
    }
    return false;
}

/* --------------------------------------------------- live price -> a line */

/** Fetch the raw live BTC/USD spot price (best-effort, cached in samples). */
function fetch_live_btc_usd(): ?float {
    $ctx = stream_context_create(['http' => ['timeout' => 4, 'header' => "User-Agent: dark-money/1\r\n"]]);
    $tries = [
        ['https://api.coinbase.com/v2/prices/BTC-USD/spot', ['data', 'amount']],
        ['https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd', ['bitcoin', 'usd']],
    ];
    foreach ($tries as [$url, $path]) {
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) continue;
        $j = json_decode($raw, true);
        if (!is_array($j)) continue;
        $cur = $j;
        foreach ($path as $k) { $cur = $cur[$k] ?? null; }
        if (is_numeric($cur)) return (float)$cur;
    }
    return null;
}

/**
 * Least-squares straight line fitted over the live samples, evaluated "now".
 * This is the *unbraked* target the platform rate is allowed to drift toward.
 */
function line_target(): float {
    $rows = db()->query("SELECT ts,price FROM price_samples ORDER BY ts ASC")
                ->fetchAll();
    $n = count($rows);
    if ($n === 0) return 0.0;
    if ($n === 1) return (float)$rows[0]['price'];

    $t0 = (int)$rows[0]['ts'];
    $sx = $sy = $sxx = $sxy = 0.0;
    foreach ($rows as $r) {
        $x = ((int)$r['ts'] - $t0) / 3600.0;   // hours since first sample
        $y = (float)$r['price'];
        $sx += $x; $sy += $y; $sxx += $x * $x; $sxy += $x * $y;
    }
    $den = $n * $sxx - $sx * $sx;
    if (abs($den) < 1e-9) return $sy / $n;      // degenerate -> mean
    $m = ($n * $sxy - $sx * $sy) / $den;         // slope (USD per hour)
    $b = ($sy - $m * $sx) / $n;                  // intercept
    $nowX = (time() - $t0) / 3600.0;
    $val = $m * $nowX + $b;
    return $val > 0 ? $val : $sy / $n;
}

/**
 * Refresh at most once per TTL: pull a live sample, then advance the braked
 * anchor rate toward the fitted line by AT MOST SMT_MAX_MOVE_DAY per day.
 */
function refresh_samples(): void {
    $pdo = db();
    $last = (int)($pdo->query("SELECT MAX(ts) FROM price_samples")->fetchColumn() ?: 0);
    if (time() - $last < SMT_PRICE_TTL) return;

    $p = fetch_live_btc_usd();
    if ($p !== null && $p > 0) {
        $pdo->prepare("INSERT OR REPLACE INTO price_samples(ts,price) VALUES(?,?)")
            ->execute([time(), $p]);
        $pdo->exec("DELETE FROM price_samples WHERE ts NOT IN
            (SELECT ts FROM price_samples ORDER BY ts DESC LIMIT " . SMT_MAX_SAMPLES . ")");
    }

    $target = line_target();
    if ($target <= 0) return;

    $anchor = meta_get('rate_value');
    if ($anchor === null) {                       // first ever anchor
        meta_set('rate_value', (string)$target);
        meta_set('rate_ts', (string)time());
        return;
    }
    $anchor   = (float)$anchor;
    $anchorTs = (int)(meta_get('rate_ts') ?: time());
    $days     = min(SMT_ACCRUE_CAP, max(0.0, (time() - $anchorTs) / 86400.0));
    $allowed  = $anchor * SMT_MAX_MOVE_DAY * $days;      // capped daily drift
    $new      = max($anchor - $allowed, min($target, $anchor + $allowed));
    meta_set('rate_value', (string)$new);
    meta_set('rate_ts', (string)time());
}

/**
 * Platform BTC/USD rate: the braked anchor. Between refreshes it does not
 * move at all, and across days it can drift by at most SMT_MAX_MOVE_DAY.
 */
function platform_rate(): float {
    refresh_samples();
    $r = meta_get('rate_value');
    if ($r !== null && (float)$r > 0) return (float)$r;
    return line_target();                          // fallback before first anchor
}

/* --------------------------------------------------------- money helpers */

function sat_to_usd(int $sat, ?float $rate = null): float {
    $rate = $rate ?? platform_rate();
    return ($sat / 100_000_000) * $rate;
}
function usd_to_sat(float $usd, ?float $rate = null): int {
    $rate = $rate ?? platform_rate();
    if ($rate <= 0) return 0;
    return (int)round(($usd / $rate) * 100_000_000);
}
function usd(float $v): string {
    return '$' . number_format($v, 2);
}

/* ------------------------------------------------------------- data access */

function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $st = db()->prepare("SELECT * FROM users WHERE id=?");
    $st->execute([(int)$_SESSION['uid']]);
    $u = $st->fetch();
    return $u ?: null;
}

/**
 * Credit confirmed value from the user's address; applies the flat fee.
 *
 * SECURITY FIX 1 (idempotency): a non-empty txid is credited at most once.
 * A prior 'credit' ledger row with the same txid short-circuits to a no-op,
 * and the partial unique index catches any concurrent double-submit as well.
 * Returns true when a credit was applied, false on a duplicate/no-op.
 */
function credit(int $userId, int $sat, string $txid = ''): bool {
    $pdo = db();
    if ($txid !== '') {
        $seen = $pdo->prepare("SELECT 1 FROM ledger WHERE kind='credit' AND txid=?");
        $seen->execute([$txid]);
        if ($seen->fetchColumn()) {
            return false;                         // already credited -> no-op
        }
    }
    $feeSat = usd_to_sat(SMT_FEE_USD);
    $net    = max(0, $sat - $feeSat);
    try {
        $pdo->beginTransaction();
        // insert the credit row first so the unique index rejects a racing dup
        $pdo->prepare("INSERT INTO ledger(user_id,sat_delta,kind,txid,created_at) VALUES(?,?,'credit',?,?)")
            ->execute([$userId, $net, $txid !== '' ? $txid : null, time()]);
        $pdo->prepare("UPDATE users SET sat_balance = sat_balance + ? WHERE id=?")
            ->execute([$net, $userId]);
        if ($feeSat > 0) {
            $pdo->prepare("INSERT INTO ledger(user_id,sat_delta,kind,txid,created_at) VALUES(?,?,'fee',?,?)")
                ->execute([$userId, -$feeSat, $txid !== '' ? $txid : null, time()]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;                             // duplicate txid -> no-op
    }
    // activation gate: >= SMT_ACTIVATE_USD credited value
    $st = $pdo->prepare("SELECT sat_balance FROM users WHERE id=?");
    $st->execute([$userId]);
    $bal = (int)$st->fetchColumn();
    if (sat_to_usd($bal) >= SMT_ACTIVATE_USD) {
        $pdo->prepare("UPDATE users SET active=1 WHERE id=?")->execute([$userId]);
    }
    return true;
}

/* ------------------------------------------------- server-to-server ingest */
/* Confirmed on-chain credits from an external watcher. Never trusts the UI.  */

if (isset($_GET['ingest'])) {
    header('Content-Type: application/json');
    if (($_POST['key'] ?? '') === '' || !hash_equals((string)meta_get('ingest_key'), (string)($_POST['key'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'bad key']); exit;
    }
    $acct = trim((string)($_POST['account_id'] ?? ''));
    $sat  = (int)($_POST['credit_sat'] ?? 0);
    $txid = trim((string)($_POST['txid'] ?? ''));
    if ($acct === '' || $sat <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'bad params']); exit;
    }
    $st = db()->prepare("SELECT id FROM users WHERE account_id=? OR address=?");
    $st->execute([$acct, $acct]);
    $uid = $st->fetchColumn();
    if ($uid === false) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'unknown account']); exit;
    }
    // Idempotent: a replayed txid is a no-op but still reports ok.
    $applied = credit((int)$uid, $sat, $txid);
    echo json_encode(['ok' => true, 'applied' => $applied]); exit;
}

/* ------------------------------------------------------------------ router */

$action = (string)($_GET['a'] ?? 'home');
$method = $_SERVER['REQUEST_METHOD'];
$err    = null;

/* -- register -- */
if ($action === 'register' && $method === 'POST') {
    if (!csrf_ok()) { $err = 'Session expired.'; }
    else {
        $addr = trim((string)($_POST['address'] ?? ''));
        $pw   = (string)($_POST['password'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9:_-]{6,120}$/', $addr)) {
            $err = 'Enter a valid public wallet address.';
        } elseif (strlen($pw) < 8) {
            $err = 'Password must be at least 8 characters.';
        } else {
            $exists = db()->prepare("SELECT 1 FROM users WHERE address=?");
            $exists->execute([$addr]);
            if ($exists->fetchColumn()) {
                $err = 'This address is already registered.';
            } else {
                // account id is derived from and stored as the address
                $account = 'DM-' . strtoupper(substr(hash('sha256', $addr . microtime()), 0, 10));
                $secret  = b32_encode(random_bytes(20));
                db()->prepare("INSERT INTO users(account_id,address,pass_hash,totp_secret,created_at)
                               VALUES(?,?,?,?,?)")
                    ->execute([$account, $addr, password_hash($pw, PASSWORD_DEFAULT), $secret, time()]);
                $_SESSION['setup_uid'] = (int)db()->lastInsertId();
                redirect('?a=mfa');
            }
        }
    }
}

/* -- confirm MFA setup -- */
if ($action === 'mfa' && $method === 'POST') {
    $uid = (int)($_SESSION['setup_uid'] ?? 0);
    $st = db()->prepare("SELECT * FROM users WHERE id=?");
    $st->execute([$uid]);
    $u = $st->fetch();
    if (!csrf_ok()) { $err = 'Session expired.'; }
    elseif (!$u) { redirect('?a=register'); }
    else {
        // SECURITY FIX 2: throttle MFA confirmation per client IP.
        $mkey = 'mfa:ip:' . client_ip();
        $wait = throttle_locked($mkey);
        if ($wait > 0) {
            $err = 'Too many attempts. Try again in ' . $wait . ' s.';
        } elseif (!totp_verify($u['totp_secret'], (string)($_POST['code'] ?? ''))) {
            throttle_fail($mkey);
            $err = 'Wrong MFA code, try again.';
        } else {
            throttle_clear($mkey);
            unset($_SESSION['setup_uid']);
            session_regenerate_id(true);
            $_SESSION['uid'] = $uid;
            flash('Account secured. Deposit to activate.');
            redirect('?a=home');
        }
    }
}

/* -- login (password + TOTP) -- */
if ($action === 'login' && $method === 'POST') {
    if (!csrf_ok()) { $err = 'Session expired.'; }
    else {
        $acct = trim((string)($_POST['account_id'] ?? ''));
        $pw   = (string)($_POST['password'] ?? '');
        $code = (string)($_POST['code'] ?? '');
        // SECURITY FIX 2: throttle login per client IP and per account id.
        $ipKey   = 'login:ip:' . client_ip();
        $acctKey = $acct !== '' ? 'login:acct:' . strtolower($acct) : '';
        $wait = max(
            throttle_locked($ipKey),
            $acctKey !== '' ? throttle_locked($acctKey) : 0
        );
        if ($wait > 0) {
            $err = 'Too many attempts. Try again in ' . $wait . ' s.';
        } else {
            $st = db()->prepare("SELECT * FROM users WHERE account_id=? OR address=?");
            $st->execute([$acct, $acct]);
            $u = $st->fetch();
            if ($u && password_verify($pw, $u['pass_hash']) && totp_verify($u['totp_secret'], $code)) {
                throttle_clear($ipKey);
                if ($acctKey !== '') { throttle_clear($acctKey); }
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$u['id'];
                redirect('?a=home');
            } else {
                throttle_fail($ipKey);
                if ($acctKey !== '') { throttle_fail($acctKey); }
                $err = 'Invalid account id, password or MFA code.';
            }
        }
    }
}

/* -- demo-only deposit simulation (server keeps the ingest key) -- */
if ($action === 'simulate' && $method === 'POST' && SMT_DEMO) {
    $u = current_user();
    if ($u && csrf_ok()) {
        credit((int)$u['id'], usd_to_sat(SMT_ACTIVATE_USD + SMT_FEE_USD), 'demo-' . bin2hex(random_bytes(6)));
        flash('Simulated confirmed deposit.');
    }
    redirect('?a=home');
}

/* -- logout -- */
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    redirect('?a=home');
}

/* --------------------------------------------------------------- rendering */

function layout_top(string $title): void {
    $healed = $GLOBALS['HEALED'] ?? [];
    $theme  = $GLOBALS['DM_THEME'] ?? 'auto';
    $attr   = ($theme === 'light' || $theme === 'dark') ? ' data-theme="' . $theme . '"' : '';
    echo '<!doctype html><html lang="en"' . $attr . '><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h(SMT_BRAND) . ' | ' . h($title) . '</title>';
    $__dmIcon = $GLOBALS['DM_ICON'] ?? '';
    if ($__dmIcon !== '') echo '<link rel="icon" type="image/svg+xml" href="' . h($__dmIcon) . '">';
    echo '<style>' . css() . '</style></head><body>';
    echo '<div class="wrap"><header><span class="logo">[ ' . h(SMT_BRAND) . ' ]</span>';
    echo '<span class="tag">SECURE VALUE NODE</span></header>';
    if ($healed) {
        echo '<div class="alert">GUARD: protection file was missing and has been restored.</div>';
    }
    if ($m = flash()) {
        echo '<div class="ok">' . h($m) . '</div>';
    }
}
function layout_bottom(): void {
    echo '<footer>no admin &middot; no private keys stored &middot; USD interface</footer>';
    echo '</div></body></html>';
}

/**
 * Token layer: light palette is the :root default; the dark ("DEFCON terminal")
 * palette applies for the fixed dark theme (:root[data-theme="dark"]) and, in
 * auto mode only (no data-theme attribute), when the OS prefers dark. Primary
 * surfaces are remapped onto the tokens so both themes read coherently.
 */
function css(): string {
    return '
    :root{
      --bg:#f3f6f3; --fg:#123320; --muted:#3c7a52; --border:#bcd6c4;
      --panel:#ffffff; --accent:#0a7d38; --btn-bg:#e6f1ea; --btn-hover:#d6e8dc;
      --err:#c0392b; --warn:#b8791a;
    }
    :root[data-theme="dark"]{
      --bg:#000000; --fg:#33ff66; --muted:#2aa34a; --border:#1c6b33;
      --panel:#020a04; --accent:#33ff66; --btn-bg:#0a1f10; --btn-hover:#112a17;
      --err:#ff6b6b; --warn:#ffb454;
    }
    @media (prefers-color-scheme: dark){
      :root:not([data-theme]){
        --bg:#000000; --fg:#33ff66; --muted:#2aa34a; --border:#1c6b33;
        --panel:#020a04; --accent:#33ff66; --btn-bg:#0a1f10; --btn-hover:#112a17;
        --err:#ff6b6b; --warn:#ffb454;
      }
    }
    *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--fg);
      font:14px/1.5 "DejaVu Sans Mono",Menlo,Consolas,monospace}
    .wrap{max-width:640px;margin:0 auto;padding:24px 16px}
    header{display:flex;justify-content:space-between;align-items:baseline;
      border:1px solid var(--border);padding:10px 14px;margin-bottom:18px}
    .logo{font-weight:bold;letter-spacing:2px} .tag{color:var(--muted);font-size:11px}
    h1{font-size:16px;letter-spacing:1px;border-bottom:1px solid var(--border);padding-bottom:6px}
    .panel{border:1px solid var(--border);padding:16px;margin:14px 0;background:var(--panel)}
    .big{font-size:34px;font-weight:bold;margin:6px 0} .muted{color:var(--muted);font-size:12px}
    label{display:block;margin:12px 0 4px;color:var(--muted);font-size:12px}
    input{width:100%;background:var(--bg);border:1px solid var(--border);color:var(--fg);
      padding:9px;font:inherit}
    input:focus{outline:none;border-color:var(--accent)}
    button,.btn{display:inline-block;margin-top:14px;background:var(--btn-bg);border:1px solid var(--accent);
      color:var(--accent);padding:9px 16px;font:inherit;cursor:pointer;text-decoration:none}
    button:hover,.btn:hover{background:var(--btn-hover)}
    nav{margin:16px 0;display:flex;gap:14px;flex-wrap:wrap}
    nav a{color:var(--accent);text-decoration:none;border-bottom:1px dashed var(--border)}
    .err{border:1px solid var(--err);color:var(--err);padding:8px 12px;margin:12px 0}
    .ok{border:1px solid var(--accent);color:var(--fg);padding:8px 12px;margin:12px 0}
    .alert{border:1px solid var(--warn);color:var(--warn);padding:8px 12px;margin:12px 0}
    code{color:var(--accent);word-break:break-all} footer{margin-top:24px;color:var(--muted);font-size:11px}
    table{width:100%;border-collapse:collapse;margin-top:10px} td{padding:4px 0;border-bottom:1px solid var(--border);font-size:12px}
    ';
}

/* --------------------------------------------------------------- pages */

$u = current_user();

if ($action === 'mfa') {
    $uid = (int)($_SESSION['setup_uid'] ?? 0);
    $st = db()->prepare("SELECT * FROM users WHERE id=?");
    $st->execute([$uid]);
    $su = $st->fetch();
    if (!$su) { redirect('?a=register'); }
    $otpauth = 'otpauth://totp/' . rawurlencode(SMT_BRAND . ':' . $su['account_id'])
             . '?secret=' . $su['totp_secret'] . '&issuer=' . rawurlencode(SMT_BRAND);
    layout_top('Set up MFA');
    echo '<h1>SET PASSWORD MFA</h1>';
    if ($err) echo '<div class="err">' . h($err) . '</div>';
    echo '<div class="panel"><div class="muted">YOUR ACCOUNT ID</div>';
    echo '<div class="big">' . h($su['account_id']) . '</div>';
    echo '<p class="muted">Add this secret to your authenticator app, then confirm the 6-digit code.</p>';
    echo '<p>Secret: <code>' . h($su['totp_secret']) . '</code></p>';
    echo '<p class="muted">otpauth: <code>' . h($otpauth) . '</code></p></div>';
    echo '<form method="post" action="?a=mfa" class="panel">';
    echo '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
    echo '<label>6-DIGIT CODE</label><input name="code" inputmode="numeric" autocomplete="one-time-code" autofocus>';
    echo '<button>CONFIRM &amp; ACTIVATE ACCOUNT</button></form>';
    layout_bottom();
    exit;
}

if ($action === 'register') {
    layout_top('Register');
    echo '<h1>REGISTER WITH YOUR WALLET</h1>';
    if ($err) echo '<div class="err">' . h($err) . '</div>';
    echo '<div class="panel"><p class="muted">Register with your own public wallet address. '
       . 'An account id is generated for you; it is the stored address. '
       . 'Payments from your address raise the value on your account id.</p></div>';
    echo '<form method="post" action="?a=register" class="panel">';
    echo '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
    echo '<label>PUBLIC WALLET ADDRESS</label><input name="address" autofocus placeholder="bc1... / 0x...">';
    echo '<label>PASSWORD (min 8)</label><input type="password" name="password">';
    echo '<button>CREATE ACCOUNT</button></form>';
    echo '<nav><a href="?a=login">have an account? log in</a></nav>';
    layout_bottom();
    exit;
}

if ($action === 'login') {
    layout_top('Login');
    echo '<h1>LOG IN</h1>';
    if ($err) echo '<div class="err">' . h($err) . '</div>';
    echo '<form method="post" action="?a=login" class="panel">';
    echo '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
    echo '<label>ACCOUNT ID OR ADDRESS</label><input name="account_id" autofocus>';
    echo '<label>PASSWORD</label><input type="password" name="password">';
    echo '<label>MFA CODE</label><input name="code" inputmode="numeric" autocomplete="one-time-code">';
    echo '<button>LOG IN</button></form>';
    echo '<nav><a href="?a=register">register with a wallet</a></nav>';
    layout_bottom();
    exit;
}

if ($action === 'info') {
    // The only place BTC is shown: total platform BTC and its USD equivalent.
    $rate     = platform_rate();
    $totalSat = (int)db()->query("SELECT COALESCE(SUM(sat_balance),0) FROM users")->fetchColumn();
    $totalBtc = $totalSat / 100_000_000;
    layout_top('BTC info');
    echo '<h1>BTC INFO</h1>';
    echo '<div class="panel"><div class="muted">PLATFORM BTC (total)</div>';
    echo '<div class="big">' . rtrim(rtrim(number_format($totalBtc, 8), '0'), '.') . ' BTC</div>';
    echo '<div class="muted">equals</div><div class="big">' . usd(sat_to_usd($totalSat, $rate)) . '</div></div>';
    echo '<div class="panel"><div class="muted">PLATFORM RATE (braked)</div>';
    echo '<div class="big">' . usd($rate) . ' <span class="muted">/ BTC</span></div>';
    echo '<p class="muted">A straight line is fitted over the live BTC/USD feed; the platform '
       . 'rate drifts toward it by at most ' . rtrim(rtrim(number_format(SMT_MAX_MOVE_DAY * 100, 2), '0'), '.')
       . '% per day. Even if BTC or the dollar breaks away, the on-platform value stays constant.</p></div>';
    echo '<nav><a href="?a=home">&larr; back</a></nav>';
    layout_bottom();
    exit;
}

/* -- home / dashboard -- */
layout_top('Home');

if (!$u) {
    echo '<h1>ONE WALLET. USD ON THE SURFACE.</h1>';
    echo '<div class="panel"><p>Register with your own public wallet address, get an account id, '
       . 'set a password and MFA, then deposit to activate. Your balance is always shown in USD.</p></div>';
    echo '<nav><a class="btn" href="?a=register">REGISTER</a> <a class="btn" href="?a=login">LOG IN</a></nav>';
    layout_bottom();
    exit;
}

$rate    = platform_rate();
$balUsd  = sat_to_usd((int)$u['sat_balance'], $rate);
$active  = (int)$u['active'] === 1;
$needUsd = max(0.0, SMT_ACTIVATE_USD - $balUsd);

echo '<h1>WALLET</h1>';
echo '<nav><a href="?a=info">btc info</a><a href="?a=logout">log out</a></nav>';

echo '<div class="panel"><div class="muted">BALANCE</div>';
echo '<div class="big">' . usd($balUsd) . '</div>';
echo '<div class="muted">ACCOUNT ID &middot; ' . h($u['account_id']) . '</div></div>';

if (!$active) {
    echo '<div class="panel"><div class="muted">STATUS</div>';
    echo '<div class="big">NOT ACTIVE</div>';
    echo '<p>Activate by depositing at least ' . usd(SMT_ACTIVATE_USD)
       . ' &mdash; effectively ' . usd(SMT_ACTIVATE_USD + SMT_FEE_USD)
       . ' (' . usd(SMT_FEE_USD) . ' fee). Still needed: <b>' . usd($needUsd) . '</b>.</p>';
    echo '<p class="muted">Send from your registered wallet address to this account id:</p>';
    echo '<p><code>' . h($u['address']) . '</code></p>';
    if (SMT_DEMO) {
        echo '<form method="post" action="?a=simulate">';
        echo '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
        echo '<button>SIMULATE CONFIRMED DEPOSIT (demo)</button></form>';
    }
    echo '</div>';
} else {
    echo '<div class="panel"><div class="muted">STATUS</div><div class="big">ACTIVE</div>'
       . '<p class="muted">Incoming payments from your address raise this balance.</p></div>';
}

// recent activity (USD only)
$st = db()->prepare("SELECT sat_delta,kind,created_at FROM ledger WHERE user_id=? ORDER BY id DESC LIMIT 8");
$st->execute([(int)$u['id']]);
$rows = $st->fetchAll();
if ($rows) {
    echo '<div class="panel"><div class="muted">RECENT</div><table>';
    foreach ($rows as $r) {
        $amt = sat_to_usd((int)$r['sat_delta'], $rate);
        $sign = $amt >= 0 ? '+' : '-';
        echo '<tr><td>' . h(strtoupper($r['kind'])) . '</td><td style="text-align:right">'
           . $sign . usd(abs($amt)) . '</td></tr>';
    }
    echo '</table></div>';
}

layout_bottom();
