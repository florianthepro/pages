<?php
/*
 * ============================================================================
 *  NEXUS – Single-File-Edition
 * ----------------------------------------------------------------------------
 *  Eine Datei: Setup, Front Controller und Assets. Beim ersten Aufruf
 *  entstehen data/ (per .htaccess gesperrt), die SQLite-Datenbank und eine
 *  index.php, die auf diese Datei verweist.
 *
 *  Ablauf: setup.php hochladen -> aufrufen -> Konto registrieren -> auf
 *  Freischaltung warten -> über index.php anmelden. Das erste Konto wird
 *  Administrator und ist sofort aktiv.
 *
 *  Datenmodell: Alle Inhalte eines Kontos werden mit einem Schlüssel
 *  verschlüsselt, der ausschließlich über das Passwort erreichbar ist
 *  (Argon2id bzw. PBKDF2 -> XChaCha20-Poly1305 bzw. AES-256-GCM; Austausch
 *  zwischen Konten über X25519-Sealed-Boxes bzw. RSA-OAEP). Zugriff auf
 *  Dateisystem oder Datenbank genügt nicht, um Inhalte zu lesen. Ein
 *  verlorenes Passwort kann nicht zurückgesetzt werden; die Inhalte des
 *  Kontos sind dann nicht wiederherstellbar.
 *
 *  Voraussetzungen: Apache + PHP >= 7.4 mit pdo_sqlite sowie sodium oder
 *  openssl. Optional: imap (externe Postfächer lesen), zlib (Kompression).
 * ============================================================================
 */
declare(strict_types=1);

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $h, string $n): bool { return strncmp($h, $n, strlen($n)) === 0; }
}

session_name('NEXUSSID');

const NX_NAME    = 'Nexus';
const NX_VERSION = '4.0.0';

/** Optionaler STUN-Server für Videoanrufe über NAT-Grenzen hinweg,
 *  z. B. 'stun:stun.example.org:3478'. Leer = nur direkte/lokale Netze. */
const NX_STUN = '';

define('NX_ROOT',     __DIR__);
define('NX_DATA',     NX_ROOT . '/data');
define('NX_SYS',      NX_DATA . '/sys');
define('NX_DB',       NX_SYS  . '/app.sqlite');
define('NX_SESSIONS', NX_SYS  . '/sessions');
define('NX_FILES',    NX_DATA . '/files');
define('NX_CHATBLOB', NX_DATA . '/chat');

define('NX_QUOTA_PENDING', 512 * 1024 * 1024);   // 0,5 GB vor Freischaltung
define('NX_QUOTA_ACTIVE',  1024 * 1024 * 1024);  // 1 GB nach Freischaltung

/* ------------------------------------------------------------------ *
 *  App-Registry (fest, da Single-File)
 * ------------------------------------------------------------------ */
function nx_apps(): array {
    return [
        'home'      => ['name'=>'Startseite',   'desc'=>'Übersicht & Schnellzugriffe',    'icon'=>'grid',     'color'=>'#4d7ea8', 'tile'=>false, 'min'=>'pending'],
        'chat'      => ['name'=>'Chat',         'desc'=>'Nachrichten, Dateien & Anrufe',  'icon'=>'chat',     'color'=>'#4a8ca0', 'tile'=>true,  'min'=>'pending'],
        'mail'      => ['name'=>'Mail',         'desc'=>'Externe IMAP/SMTP-Postfächer',   'icon'=>'mail',     'color'=>'#4d7ea8', 'tile'=>true,  'min'=>'active'],
        'notes'     => ['name'=>'Notizen',      'desc'=>'Gedanken, Listen & Snippets',    'icon'=>'note',     'color'=>'#b3893f', 'tile'=>true,  'min'=>'active'],
        'tasks'     => ['name'=>'Aufgaben',     'desc'=>'To-dos mit Fälligkeit',          'icon'=>'checkbox', 'color'=>'#4a9d6f', 'tile'=>true,  'min'=>'active'],
        'calendar'  => ['name'=>'Kalender',     'desc'=>'Termine & Ereignisse',           'icon'=>'calendar', 'color'=>'#c25a5a', 'tile'=>true,  'min'=>'active'],
        'contacts'  => ['name'=>'Kontakte',     'desc'=>'Adressbuch',                     'icon'=>'user',     'color'=>'#8a7fb0', 'tile'=>true,  'min'=>'active'],
        'files'     => ['name'=>'Dateien',      'desc'=>'Verschlüsselter Speicher',       'icon'=>'folder',   'color'=>'#4a9d6f', 'tile'=>true,  'min'=>'active'],
        'bookmarks' => ['name'=>'Lesezeichen',  'desc'=>'Links & Kacheln',                'icon'=>'link',     'color'=>'#8a7fb0', 'tile'=>true,  'min'=>'active'],
        'admin'     => ['name'=>'Verwaltung',   'desc'=>'Nutzer, Freischaltung & Quota',  'icon'=>'shield',   'color'=>'#7a828e', 'tile'=>true,  'min'=>'admin'],
        'settings'  => ['name'=>'Einstellungen','desc'=>'Profil, Konten & Aussehen',      'icon'=>'cog',      'color'=>'#7a828e', 'tile'=>true,  'min'=>'pending'],
    ];
}

/* ------------------------------------------------------------------ *
 *  Gruppierung der Apps (rein für die Darstellung – Navigation &
 *  Startseite). Ändert keine Zugriffslogik; leere Gruppen werden
 *  ausgeblendet. Schlüssel = Überschrift (leer = ohne Überschrift).
 * ------------------------------------------------------------------ */
function nx_groups(): array {
    return [
        ''              => ['icon'=>'',         'apps'=>['home']],
        'Kommunikation' => ['icon'=>'chat',     'apps'=>['chat', 'mail']],
        'Organisieren'  => ['icon'=>'checkbox', 'apps'=>['notes', 'tasks', 'calendar', 'contacts']],
        'Ablage'        => ['icon'=>'folder',   'apps'=>['files', 'bookmarks']],
        'System'        => ['icon'=>'cog',      'apps'=>['admin', 'settings']],
    ];
}

/* ------------------------------------------------------------------ *
 *  Voraussetzungen & Bootstrap
 * ------------------------------------------------------------------ */
function nx_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}

function nx_requirements(): array {
    $m = [];
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        $m[] = 'PHP &ge; 7.4 erforderlich (aktuell ' . PHP_VERSION . ').';
    }
    if (!extension_loaded('pdo_sqlite')) {
        $m[] = 'PHP-Erweiterung <code>pdo_sqlite</code> ist nicht aktiv.';
    }
    if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt') && !function_exists('openssl_encrypt')) {
        $m[] = 'Weder <code>sodium</code> noch <code>openssl</code> verfügbar – Verschlüsselung nicht möglich.';
    }
    if (!is_writable(NX_ROOT) && !is_dir(NX_DATA)) {
        $m[] = 'Verzeichnis nicht beschreibbar – <code>data/</code> kann nicht angelegt werden.';
    }
    return $m;
}

function nx_setup_page(array $missing): void {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">';
    echo '<title>' . NX_NAME . ' – Setup</title>';
    echo '<style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#0f1115;color:#cfd3d9;'
       . 'display:grid;place-items:center;min-height:100vh;margin:0}.c{max-width:440px;background:#171a1f;'
       . 'border:1px solid #2a2e35;border-radius:6px;padding:28px}h1{font-size:18px;margin:0 0 6px}'
       . 'p{color:#848a94;font-size:14px}li{margin:8px 0}code{font-family:ui-monospace,monospace;color:#c25a5a}</style>';
    echo '<div class="c"><h1>' . NX_NAME . ' – Setup</h1>';
    echo '<p>Bevor es losgeht, fehlen noch Voraussetzungen:</p><ul>';
    foreach ($missing as $m) {
        echo '<li>' . $m . '</li>';
    }
    echo '</ul><p>Nach dem Beheben Seite neu laden.</p></div>';
}

function nx_bootstrap(): void {
    foreach ([NX_DATA, NX_SYS, NX_SESSIONS, NX_FILES, NX_CHATBLOB] as $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0770, true);
        }
    }
    if (!is_dir(NX_DATA)) {
        http_response_code(500);
        exit('Fehler: data/ konnte nicht angelegt werden. Schreibrechte prüfen.');
    }

    // data/ komplett sperren
    $hta = NX_DATA . '/.htaccess';
    if (!is_file($hta)) {
        @file_put_contents($hta, implode("\n", [
            '# Automatisch erstellt – data/ ist komplett gesperrt.',
            '<IfModule mod_authz_core.c>',
            '    Require all denied',
            '</IfModule>',
            '<IfModule !mod_authz_core.c>',
            '    Order allow,deny',
            '    Deny from all',
            '</IfModule>',
            'Options -Indexes',
            '',
        ]));
    }
    $guard = "<?php http_response_code(403); exit('Zugriff verweigert.');";
    foreach ([NX_DATA, NX_SYS] as $d) {
        if (!is_file($d . '/index.php')) {
            @file_put_contents($d . '/index.php', $guard);
        }
    }

    // index.php als Einstieg anlegen (verweist auf diese Datei)
    $self = basename(__FILE__);
    if ($self !== 'index.php' && !is_file(NX_ROOT . '/index.php') && is_writable(NX_ROOT)) {
        @file_put_contents(NX_ROOT . '/index.php', "<?php require __DIR__ . '/" . $self . "';\n");
    }

    if (is_dir(NX_SESSIONS) && is_writable(NX_SESSIONS)) {
        session_save_path(NX_SESSIONS);
    }

    db_migrate();
}

/* ------------------------------------------------------------------ *
 *  Datenbank (SQLite via PDO)
 * ------------------------------------------------------------------ */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . NX_DB);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA busy_timeout = 5000;');
    }
    return $pdo;
}

function db_run(string $sql, array $args = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st;
}

function db_one(string $sql, array $args = []): ?array {
    $row = db_run($sql, $args)->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $args = []): array {
    return db_run($sql, $args)->fetchAll();
}

function db_scalar(string $sql, array $args = []) {
    return db_run($sql, $args)->fetchColumn();
}

function db_lastid(): int {
    return (int) db()->lastInsertId();
}

function db_migrate(): void {
    $tables = [
        // Benutzer inkl. Rolle, Status, Quota und Schlüsselmaterial.
        // kdf_salt/wrapped_mk: Konto-Schlüssel, nur per Passwort entsperrbar.
        // pubkey/wrapped_sk: Schlüsselpaar für Nachrichten zwischen Konten.
        "CREATE TABLE IF NOT EXISTS users (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            username     TEXT UNIQUE NOT NULL,
            email        TEXT NOT NULL,
            pass_hash    TEXT NOT NULL,
            display_name TEXT,
            role         TEXT NOT NULL DEFAULT 'user',      -- user | admin
            status       TEXT NOT NULL DEFAULT 'pending',   -- pending | active | suspended
            quota_bytes  INTEGER NOT NULL DEFAULT 536870912,
            theme        TEXT DEFAULT 'dark',
            accent       TEXT DEFAULT '#4d7ea8',
            kdf          TEXT NOT NULL DEFAULT 'a2',        -- a2 = Argon2id | p2 = PBKDF2
            kdf_salt     TEXT NOT NULL DEFAULT '',
            wrapped_mk   TEXT NOT NULL DEFAULT '',
            pk_alg       TEXT NOT NULL DEFAULT 'x1',        -- x1 = X25519 | r1 = RSA-OAEP
            pubkey       TEXT NOT NULL DEFAULT '',
            wrapped_sk   TEXT NOT NULL DEFAULT '',
            created_at   TEXT DEFAULT (datetime('now')),
            approved_at  TEXT,
            approved_by  INTEGER
        )",
        "CREATE TABLE IF NOT EXISTS tickets (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            code        TEXT UNIQUE NOT NULL,
            user_id     INTEGER NOT NULL,
            status      TEXT NOT NULL DEFAULT 'open',        -- open | approved | rejected
            created_at  TEXT DEFAULT (datetime('now')),
            resolved_at TEXT,
            resolved_by INTEGER
        )",
        // Chat: Sender-Kopie mit Konto-Schlüssel des Senders, Empfänger-Kopie
        // asymmetrisch an den Empfänger versiegelt. blob = Datei-Anhang.
        "CREATE TABLE IF NOT EXISTS chat (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id    INTEGER NOT NULL,
            recipient_id INTEGER NOT NULL,
            ct_sender    TEXT DEFAULT '',
            ct_recip     TEXT DEFAULT '',
            blob         TEXT DEFAULT '',
            bytes        INTEGER NOT NULL DEFAULT 0,
            seen         INTEGER NOT NULL DEFAULT 0,
            ticket_code  TEXT DEFAULT '',
            del_sender   INTEGER NOT NULL DEFAULT 0,
            del_recip    INTEGER NOT NULL DEFAULT 0,
            created_at   TEXT DEFAULT (datetime('now'))
        )",
        // Kurzlebige WebRTC-Signalisierung (Anrufaufbau); Medien laufen P2P.
        "CREATE TABLE IF NOT EXISTS rtc (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            from_id    INTEGER NOT NULL,
            to_id      INTEGER NOT NULL,
            payload    TEXT NOT NULL,
            created_at INTEGER NOT NULL
        )",
        // Virtuelles Dateisystem: Namen und Inhalte verschlüsselt, auf der
        // Platte liegen nur numerische Blob-IDs.
        "CREATE TABLE IF NOT EXISTS vfs (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL,
            parent_id  INTEGER NOT NULL DEFAULT 0,
            is_dir     INTEGER NOT NULL DEFAULT 0,
            enc_name   TEXT NOT NULL,
            size       INTEGER NOT NULL DEFAULT 0,
            blob       TEXT DEFAULT '',
            sealed     INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now'))
        )",
        "CREATE TABLE IF NOT EXISTS notes (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL,
            pinned     INTEGER NOT NULL DEFAULT 0,
            enc        TEXT NOT NULL,
            updated_at TEXT DEFAULT (datetime('now'))
        )",
        "CREATE TABLE IF NOT EXISTS tasks (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL,
            done       INTEGER NOT NULL DEFAULT 0,
            enc        TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime('now'))
        )",
        "CREATE TABLE IF NOT EXISTS events (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            day     TEXT NOT NULL,
            enc     TEXT NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS contacts (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            enc     TEXT NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS bookmarks (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id  INTEGER NOT NULL,
            position INTEGER NOT NULL DEFAULT 0,
            enc      TEXT NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS mail_accounts (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            enc     TEXT NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            ip       TEXT NOT NULL,
            username TEXT DEFAULT '',
            ts       INTEGER NOT NULL
        )",
    ];
    foreach ($tables as $sql) {
        db()->exec($sql);
    }
    $indexes = [
        'CREATE INDEX IF NOT EXISTS idx_chat_recip  ON chat(recipient_id, del_recip, seen)',
        'CREATE INDEX IF NOT EXISTS idx_chat_sender ON chat(sender_id, del_sender)',
        'CREATE INDEX IF NOT EXISTS idx_rtc_to      ON rtc(to_id, from_id)',
        'CREATE INDEX IF NOT EXISTS idx_vfs_user    ON vfs(user_id, parent_id)',
        'CREATE INDEX IF NOT EXISTS idx_notes_user  ON notes(user_id)',
        'CREATE INDEX IF NOT EXISTS idx_tasks_user  ON tasks(user_id)',
        'CREATE INDEX IF NOT EXISTS idx_events_user ON events(user_id, day)',
        'CREATE INDEX IF NOT EXISTS idx_login_ip    ON login_attempts(ip, ts)',
    ];
    foreach ($indexes as $sql) {
        db()->exec($sql);
    }
}

/* ================================================================== *
 *  Helfer
 * ================================================================== */
function h(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}

function param(string $key, string $default = ''): string {
    $v = $_GET[$key] ?? $_POST[$key] ?? $default;
    return is_string($v) ? $v : $default;
}

function param_int(string $key, int $default = 0): int {
    $v = $_GET[$key] ?? $_POST[$key] ?? $default;
    return is_numeric($v) ? (int) $v : $default;
}

function flash(?string $msg = null, string $type = 'ok'): ?array {
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function url(string $app, array $q = []): string {
    return '?app=' . urlencode($app) . ($q ? '&' . http_build_query($q) : '');
}

function human_size(int $bytes): string {
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $b = (float) $bytes;
    while ($b >= 1024 && $i < 4) {
        $b /= 1024;
        $i++;
    }
    return round($b, ($b < 10 && $i > 0) ? 1 : 0) . ' ' . $u[$i];
}

function b64e(string $b): string {
    return base64_encode($b);
}

function b64d(?string $s): string {
    if ($s === null || $s === '') {
        return '';
    }
    $d = base64_decode($s, true);
    return $d === false ? '' : $d;
}

/* ================================================================== *
 *  Kryptografie
 * ------------------------------------------------------------------
 *  - Konto-Schlüssel (MK, 32 B Zufall) wird mit einem aus dem Passwort
 *    abgeleiteten Schlüssel (Argon2id, sonst PBKDF2) umschlossen.
 *  - Inhalte: AEAD (XChaCha20-Poly1305, sonst AES-256-GCM) mit MK.
 *  - Zwischen Konten: Sealed Box an den öffentlichen Schlüssel des
 *    Empfängers (X25519, sonst RSA-2048-OAEP hybrid).
 *  - Während einer Sitzung liegt MK nur mit einem Cookie-Schlüssel
 *    verschlüsselt in der Session; Dateizugriff allein genügt nicht.
 * ================================================================== */
function nx_kdf_alg(): string {
    return function_exists('sodium_crypto_pwhash') ? 'a2' : 'p2';
}

function nx_kdf(string $pass, string $salt, string $alg): ?string {
    if ($alg === 'a2') {
        if (!function_exists('sodium_crypto_pwhash')) {
            return null;
        }
        return sodium_crypto_pwhash(32, $pass, $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
    }
    return hash_pbkdf2('sha256', $pass, $salt, 210000, 32, true);
}

/* ---- AEAD (roh, binär; Präfix 2 Zeichen) ---- */
function aead_enc_raw(string $pt, string $key): string {
    if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
        $n = random_bytes(24);
        return 's1' . $n . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($pt, '', $n, $key);
    }
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($pt, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return 'o1' . $iv . $tag . $ct;
}

function aead_dec_raw(?string $blob, ?string $key): ?string {
    if ($blob === null || $key === null || strlen($blob) < 2) {
        return null;
    }
    $p = substr($blob, 0, 2);
    if ($p === 's1' && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
        if (strlen($blob) < 27) {
            return null;
        }
        try {
            $pt = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($blob, 26), '', substr($blob, 2, 24), $key);
        } catch (Throwable $e) {
            return null;
        }
        return $pt === false ? null : $pt;
    }
    if ($p === 'o1' && function_exists('openssl_decrypt')) {
        if (strlen($blob) < 30) {
            return null;
        }
        $pt = openssl_decrypt(substr($blob, 30), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($blob, 2, 12), substr($blob, 14, 16));
        return $pt === false ? null : $pt;
    }
    return null;
}

/* ---- AEAD (Base64-Armor für DB-Spalten) ---- */
function aead_enc(string $pt, string $key): string {
    return b64e(aead_enc_raw($pt, $key));
}

function aead_dec(?string $blob, ?string $key): ?string {
    if ($blob === null || $blob === '') {
        return null;
    }
    return aead_dec_raw(b64d($blob), $key);
}

/* ---- Schlüsselpaare & Sealed Boxes ---- */
function box_keypair(): ?array {
    if (function_exists('sodium_crypto_box_keypair')) {
        $kp = sodium_crypto_box_keypair();
        return ['x1', sodium_crypto_box_publickey($kp), sodium_crypto_box_secretkey($kp)];
    }
    if (function_exists('openssl_pkey_new')) {
        $res = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        if ($res === false) {
            return null;
        }
        $priv = '';
        openssl_pkey_export($res, $priv);
        $det = openssl_pkey_get_details($res);
        if (!$priv || !$det) {
            return null;
        }
        return ['r1', $det['key'], $priv];
    }
    return null;
}

function box_seal_raw(string $pt, string $pub, string $alg): ?string {
    if ($alg === 'x1' && function_exists('sodium_crypto_box_seal')) {
        return 'x1' . sodium_crypto_box_seal($pt, $pub);
    }
    if ($alg === 'r1' && function_exists('openssl_public_encrypt')) {
        $k = random_bytes(32);
        $ek = '';
        if (!openssl_public_encrypt($k, $ek, $pub, OPENSSL_PKCS1_OAEP_PADDING)) {
            return null;
        }
        return 'r1' . pack('n', strlen($ek)) . $ek . aead_enc_raw($pt, $k);
    }
    return null;
}

function box_open_raw(?string $blob, string $pub, ?string $sec): ?string {
    if ($blob === null || $sec === null || strlen($blob) < 2) {
        return null;
    }
    $p = substr($blob, 0, 2);
    if ($p === 'x1' && function_exists('sodium_crypto_box_seal_open')) {
        try {
            $kp = sodium_crypto_box_keypair_from_secretkey_and_publickey($sec, $pub);
            $pt = sodium_crypto_box_seal_open(substr($blob, 2), $kp);
        } catch (Throwable $e) {
            return null;
        }
        return $pt === false ? null : $pt;
    }
    if ($p === 'r1' && function_exists('openssl_private_decrypt')) {
        if (strlen($blob) < 5) {
            return null;
        }
        $len = unpack('n', substr($blob, 2, 2))[1] ?? 0;
        $ek = substr($blob, 4, $len);
        $k = '';
        if (!openssl_private_decrypt($ek, $k, $sec, OPENSSL_PKCS1_OAEP_PADDING)) {
            return null;
        }
        return aead_dec_raw(substr($blob, 4 + $len), $k);
    }
    return null;
}

/* ---- Strukturierte Nutzdaten (JSON, optional komprimiert) ---- */
function pack_payload(array $data): string {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if (function_exists('gzdeflate') && strlen($json) > 200) {
        return 'z' . gzdeflate($json, 6);
    }
    return 'p' . $json;
}

function unpack_payload(?string $pt): array {
    if ($pt === null || $pt === '') {
        return [];
    }
    $json = $pt[0] === 'z'
        ? (function_exists('gzinflate') ? @gzinflate(substr($pt, 1)) : '')
        : substr($pt, 1);
    $a = json_decode((string) $json, true);
    return is_array($a) ? $a : [];
}

function enc_row(array $data, string $key): string {
    return b64e(aead_enc_raw(pack_payload($data), $key));
}

function dec_row(?string $blob, ?string $key): array {
    return unpack_payload(aead_dec_raw(b64d($blob), $key));
}

/* ---- Sitzungsschlüssel ------------------------------------------- *
 *  MK liegt nie im Klartext auf der Platte: In der Session steht nur
 *  enc(MK, Cookie-Schlüssel); der Cookie-Schlüssel nur beim Client.  */
function mk_store(string $mk): void {
    $sk = random_bytes(32);
    setcookie('NXK', b64e($sk), [
        'expires' => 0, 'path' => '/', 'httponly' => true,
        'samesite' => 'Lax', 'secure' => nx_https(),
    ]);
    $_COOKIE['NXK'] = b64e($sk);
    $_SESSION['emk'] = aead_enc($mk, $sk);
}

function mk(): ?string {
    static $done = false, $mk = null;
    if ($done) {
        return $mk;
    }
    $done = true;
    $sk = b64d($_COOKIE['NXK'] ?? '');
    if (strlen($sk) !== 32 || empty($_SESSION['emk'])) {
        return null;
    }
    return $mk = aead_dec($_SESSION['emk'], $sk);
}

function user_pub(array $u): string {
    return b64d($u['pubkey']);
}

function user_sec(array $u): ?string {
    static $cached = false, $sec = null;
    if ($cached) {
        return $sec;
    }
    $cached = true;
    return $sec = aead_dec($u['wrapped_sk'], mk());
}

/* ================================================================== *
 *  Sicherheits-Header & CSRF
 * ================================================================== */
function sec_headers(): void {
    if (headers_sent()) {
        return;
    }
    header("Content-Security-Policy: "
        . "default-src 'self'; img-src 'self' data:; media-src 'self' blob:; "
        . "style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; "
        . "connect-src 'self'; frame-src 'self'; object-src 'none'; "
        . "base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), interest-cohort=()');
    header_remove('X-Powered-By');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function csrf_check_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Nur POST.');
    }
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Sitzung abgelaufen oder ungültiges Token. Bitte Seite neu laden.');
    }
}

function csrf_check_get(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', param('_csrf'))) {
        http_response_code(419);
        exit('Ungültiges Token.');
    }
}

/* ================================================================== *
 *  Authentifizierung & Konten
 * ================================================================== */
function current_user(): ?array {
    static $loaded = false, $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    if (empty($_SESSION['uid'])) {
        return $user = null;
    }
    return $user = db_one('SELECT * FROM users WHERE id=?', [$_SESSION['uid']]);
}

function user_by_id(int $id): ?array {
    return db_one('SELECT * FROM users WHERE id=?', [$id]);
}

function first_admin(): ?array {
    return db_one("SELECT * FROM users WHERE role='admin' ORDER BY id LIMIT 1");
}

function user_count(): int {
    return (int) db_scalar('SELECT COUNT(*) FROM users');
}

function auth_logout(): void {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    setcookie('NXK', '', ['expires' => time() - 3600, 'path' => '/']);
}

function can_access(array $user, string $appId): bool {
    $apps = nx_apps();
    if (!isset($apps[$appId]) || $user['status'] === 'suspended') {
        return false;
    }
    $min = $apps[$appId]['min'];
    if ($min === 'admin') {
        return $user['role'] === 'admin';
    }
    if ($min === 'active') {
        return $user['status'] === 'active' || $user['role'] === 'admin';
    }
    return true;
}

function auth_register(string $user, string $email, string $pass, string $pass2): array {
    $user = trim($user);
    $email = trim($email);
    if (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $user)) {
        return ['err' => 'Benutzername: 3–32 Zeichen (A–Z, 0–9, _.-).'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['err' => 'Bitte eine gültige E-Mail-Adresse angeben.'];
    }
    if (strlen($pass) < 8) {
        return ['err' => 'Passwort muss mindestens 8 Zeichen haben.'];
    }
    if ($pass !== $pass2) {
        return ['err' => 'Passwörter stimmen nicht überein.'];
    }
    if (db_one('SELECT 1 FROM users WHERE username=?', [$user])) {
        return ['err' => 'Benutzername bereits vergeben.'];
    }

    // Schlüsselmaterial
    $alg  = nx_kdf_alg();
    $salt = random_bytes(16);
    $kek  = nx_kdf($pass, $salt, $alg);
    $kp   = box_keypair();
    if ($kek === null || $kp === null) {
        return ['err' => 'Verschlüsselung nicht verfügbar (sodium/openssl prüfen).'];
    }
    $mk = random_bytes(32);
    [$pkAlg, $pub, $sec] = $kp;

    $firstUser = user_count() === 0;
    $role   = $firstUser ? 'admin' : 'user';
    $status = $firstUser ? 'active' : 'pending';
    $quota  = $firstUser ? NX_QUOTA_ACTIVE : NX_QUOTA_PENDING;

    db_run('INSERT INTO users (username,email,pass_hash,display_name,role,status,quota_bytes,
                kdf,kdf_salt,wrapped_mk,pk_alg,pubkey,wrapped_sk,approved_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        $user, $email, password_hash($pass, PASSWORD_DEFAULT), $user, $role, $status, $quota,
        $alg, b64e($salt), aead_enc($mk, $kek), $pkAlg, b64e($pub), aead_enc($sec, $mk),
        $firstUser ? date('Y-m-d H:i:s') : null,
    ]);
    $uid = db_lastid();
    $_SESSION['uid'] = $uid;
    session_regenerate_id(true);
    mk_store($mk);

    if (!$firstUser) {
        $code = ticket_open($uid);
        $admin = first_admin();
        if ($admin) {
            chat_send($uid, $mk, (int) $admin['id'], [
                't'    => 'sys',
                'body' => "Neue Registrierung wartet auf Freischaltung.\n\nBenutzer: $user\nE-Mail: $email\nTicket: $code",
            ], $code, false);
        }
    }
    return ['ok' => true];
}

function auth_login(string $user, string $pass): array {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    db_run('DELETE FROM login_attempts WHERE ts < ?', [time() - 86400]);
    $fails = (int) db_scalar('SELECT COUNT(*) FROM login_attempts WHERE ip=? AND ts>?', [$ip, time() - 900]);
    if ($fails >= 10) {
        return ['err' => 'Zu viele Fehlversuche. Bitte später erneut versuchen.'];
    }

    $row = db_one('SELECT * FROM users WHERE username=?', [trim($user)]);
    if (!$row || !password_verify($pass, $row['pass_hash'])) {
        db_run('INSERT INTO login_attempts (ip,username,ts) VALUES (?,?,?)', [$ip, trim($user), time()]);
        return ['err' => 'Benutzername oder Passwort falsch.'];
    }
    if ($row['status'] === 'suspended') {
        return ['err' => 'Dieses Konto ist gesperrt.'];
    }

    $kek = nx_kdf($pass, b64d($row['kdf_salt']), $row['kdf']);
    $mk  = $kek === null ? null : aead_dec($row['wrapped_mk'], $kek);
    if ($mk === null) {
        return ['err' => 'Kontoschlüssel konnte nicht entsperrt werden.'];
    }

    db_run('DELETE FROM login_attempts WHERE ip=?', [$ip]);
    $_SESSION['uid'] = (int) $row['id'];
    session_regenerate_id(true);
    mk_store($mk);
    return ['ok' => true];
}

/** Passwortwechsel: MK bleibt gleich, wird nur neu umschlossen. */
function auth_rewrap(array $u, string $current, string $new): ?string {
    if (!password_verify($current, $u['pass_hash'])) {
        return 'Aktuelles Passwort falsch.';
    }
    $kek = nx_kdf($current, b64d($u['kdf_salt']), $u['kdf']);
    $mk  = $kek === null ? null : aead_dec($u['wrapped_mk'], $kek);
    if ($mk === null) {
        return 'Kontoschlüssel konnte nicht entsperrt werden.';
    }
    $alg  = nx_kdf_alg();
    $salt = random_bytes(16);
    $nkek = nx_kdf($new, $salt, $alg);
    if ($nkek === null) {
        return 'Verschlüsselung nicht verfügbar.';
    }
    db_run('UPDATE users SET pass_hash=?, kdf=?, kdf_salt=?, wrapped_mk=? WHERE id=?', [
        password_hash($new, PASSWORD_DEFAULT), $alg, b64e($salt), aead_enc($mk, $nkek), $u['id'],
    ]);
    return null;
}

/* ---- Admin-Aktionen ---- */
function admin_approve(int $uid, array $admin): void {
    db_run("UPDATE users SET status='active', quota_bytes=MAX(quota_bytes,?), approved_at=?, approved_by=?
            WHERE id=? AND status!='suspended'",
        [NX_QUOTA_ACTIVE, date('Y-m-d H:i:s'), $admin['id'], $uid]);
    ticket_resolve($uid, 'approved', (int) $admin['id']);
    chat_send((int) $admin['id'], mk(), $uid, [
        't'    => 'sys',
        'body' => 'Dein Konto wurde freigeschaltet. Verfügbarer Speicher: ' . human_size(NX_QUOTA_ACTIVE) . '.',
    ], '', false);
}

function admin_reject(int $uid, array $admin): void {
    ticket_resolve($uid, 'rejected', (int) $admin['id']);
    db_run("UPDATE users SET status='suspended' WHERE id=? AND role!='admin'", [$uid]);
}

function admin_suspend(int $uid): void {
    db_run("UPDATE users SET status='suspended' WHERE id=? AND role!='admin'", [$uid]);
}

function admin_unsuspend(int $uid): void {
    db_run("UPDATE users SET status='active' WHERE id=?", [$uid]);
}

function admin_set_quota(int $uid, int $bytes): void {
    db_run('UPDATE users SET quota_bytes=? WHERE id=?', [max(0, $bytes), $uid]);
}

function admin_promote(int $uid): void {
    db_run("UPDATE users SET role='admin', status='active' WHERE id=?", [$uid]);
}

function admin_demote(int $uid): void {
    if ((int) db_scalar("SELECT COUNT(*) FROM users WHERE role='admin'") > 1) {
        db_run("UPDATE users SET role='user' WHERE id=?", [$uid]);
    }
}

/* ================================================================== *
 *  Quota
 * ================================================================== */
function quota_used(int $uid): int {
    $chat  = (int) db_scalar('SELECT COALESCE(SUM(bytes),0) FROM chat WHERE recipient_id=? AND del_recip=0', [$uid]);
    $files = (int) db_scalar('SELECT COALESCE(SUM(size),0) FROM vfs WHERE user_id=? AND is_dir=0', [$uid]);
    return $chat + $files;
}

function quota_remaining(int $uid): int {
    $u = db_one('SELECT quota_bytes FROM users WHERE id=?', [$uid]);
    return max(0, (int) ($u['quota_bytes'] ?? 0) - quota_used($uid));
}

function quota_can_store(int $uid, int $bytes): bool {
    return $bytes <= quota_remaining($uid);
}

function chat_unread(int $uid): int {
    return (int) db_scalar('SELECT COUNT(*) FROM chat WHERE recipient_id=? AND seen=0 AND del_recip=0', [$uid]);
}

/* ================================================================== *
 *  Freischalt-Tickets
 * ================================================================== */
function ticket_open(int $uid): string {
    $code = 'TCK-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    db_run('INSERT INTO tickets (code,user_id,status) VALUES (?,?,?)', [$code, $uid, 'open']);
    return $code;
}

function ticket_resolve(int $uid, string $status, int $byAdmin): void {
    db_run("UPDATE tickets SET status=?, resolved_at=?, resolved_by=? WHERE user_id=? AND status='open'",
        [$status, date('Y-m-d H:i:s'), $byAdmin, $uid]);
}

function ticket_for_user(int $uid): ?array {
    return db_one('SELECT * FROM tickets WHERE user_id=? ORDER BY id DESC LIMIT 1', [$uid]);
}

function tickets_open_count(): int {
    return (int) db_scalar("SELECT COUNT(*) FROM tickets WHERE status='open'");
}

/* ================================================================== *
 *  Chat-Service
 * ------------------------------------------------------------------
 *  Jede Nachricht wird doppelt abgelegt: für den Sender mit dessen
 *  Konto-Schlüssel, für den Empfänger als Sealed Box an dessen
 *  öffentlichen Schlüssel. Der Server kann ruhende Nachrichten nicht
 *  lesen; der Empfänger entsiegelt sie erst nach dem Login.
 * ================================================================== */

/** Zustellen; liefert die Nachrichten-ID oder einen Fehlertext. */
function chat_send(int $senderId, ?string $senderKey, int $recipId, array $payload,
                   string $ticket = '', bool $enforceQuota = true, string $blobId = '', int $extraBytes = 0) {
    $recip = user_by_id($recipId);
    if (!$recip) {
        return 'Empfänger nicht gefunden.';
    }
    $packed = pack_payload($payload);
    $bytes  = strlen($packed) + $extraBytes;
    if ($enforceQuota && !quota_can_store($recipId, $bytes)) {
        return 'Empfänger-Postfach ist voll (Quota überschritten).';
    }
    $ctRecip = box_seal_raw($packed, user_pub($recip), $recip['pk_alg']);
    if ($ctRecip === null) {
        return 'Verschlüsselung fehlgeschlagen.';
    }
    $ctSender = $senderKey !== null ? aead_enc_raw($packed, $senderKey) : '';
    db_run('INSERT INTO chat (sender_id,recipient_id,ct_sender,ct_recip,blob,bytes,ticket_code)
            VALUES (?,?,?,?,?,?,?)', [
        $senderId, $recipId, $ctSender === '' ? '' : b64e($ctSender), b64e($ctRecip), $blobId, $bytes, $ticket,
    ]);
    return db_lastid();
}

/** Datei senden: Inhalt wird an den Empfänger versiegelt abgelegt. */
function chat_send_file(array $sender, int $recipId, string $name, string $data, string $ticket = '') {
    $recip = user_by_id($recipId);
    if (!$recip) {
        return 'Empfänger nicht gefunden.';
    }
    $size = strlen($data);
    if (!quota_can_store($recipId, $size)) {
        return 'Empfänger-Postfach ist voll (Quota überschritten).';
    }
    $sealed = box_seal_raw($data, user_pub($recip), $recip['pk_alg']);
    if ($sealed === null) {
        return 'Verschlüsselung fehlgeschlagen.';
    }
    $blobId = bin2hex(random_bytes(16));
    if (@file_put_contents(NX_CHATBLOB . '/' . $blobId . '.bin', $sealed) === false) {
        return 'Datei konnte nicht gespeichert werden.';
    }
    $res = chat_send((int) $sender['id'], mk(), $recipId,
        ['t' => 'file', 'name' => $name, 'size' => $size], $ticket, true, $blobId, $size);
    if (!is_int($res)) {
        @unlink(NX_CHATBLOB . '/' . $blobId . '.bin');
    }
    return $res;
}

/** Nutzdaten einer Nachricht aus Sicht von $u entschlüsseln. */
function chat_payload(array $m, array $u): array {
    if ((int) $m['sender_id'] === (int) $u['id']) {
        return unpack_payload(aead_dec_raw(b64d($m['ct_sender']), mk()));
    }
    return unpack_payload(box_open_raw(b64d($m['ct_recip']), user_pub($u), user_sec($u)));
}

/** Erlaubten Chat-Partner bestimmen (Pending nur mit Admin). */
function chat_peer_id(array $u, int $want): int {
    if ($u['status'] === 'pending') {
        $a = first_admin();
        return $a ? (int) $a['id'] : 0;
    }
    if ($want <= 0 || $want === (int) $u['id']) {
        return 0;
    }
    $p = db_one("SELECT id,status FROM users WHERE id=? AND status!='suspended'", [$want]);
    if (!$p) {
        return 0;
    }
    if ($p['status'] === 'pending' && $u['role'] !== 'admin') {
        return 0;
    }
    return (int) $p['id'];
}

function chat_delete(int $id, int $uid): void {
    db_run('UPDATE chat SET del_recip=1 WHERE id=? AND recipient_id=?', [$id, $uid]);
    db_run('UPDATE chat SET del_sender=1 WHERE id=? AND sender_id=?', [$id, $uid]);
    $m = db_one('SELECT blob FROM chat WHERE id=? AND del_recip=1 AND del_sender=1', [$id]);
    if ($m) {
        if ($m['blob'] !== '' && preg_match('/^[0-9a-f]{32}$/', $m['blob'])) {
            @unlink(NX_CHATBLOB . '/' . $m['blob'] . '.bin');
        }
        db_run('DELETE FROM chat WHERE id=?', [$id]);
    }
}

/* ================================================================== *
 *  Datums-Helfer
 * ================================================================== */
function fmt_short(string $sql): string {
    $ts = strtotime($sql . ' UTC') ?: strtotime($sql) ?: time();
    return date('Y-m-d', $ts) === date('Y-m-d') ? date('H:i', $ts) : date('d.m.Y', $ts);
}

function fmt_mail(int $ts): string {
    if (date('Y-m-d', $ts) === date('Y-m-d')) {
        return date('H:i', $ts);
    }
    return (time() - $ts < 6 * 86400) ? date('D, H:i', $ts) : date('d.m.Y', $ts);
}

function linkify(string $escapedText): string {
    return preg_replace('#(https?://[^\s<]+)#',
        '<a href="$1" target="_blank" rel="noopener" style="color:var(--accent)">$1</a>', $escapedText);
}

/* ================================================================== *
 *  Externe Mail: Konten, SMTP, IMAP
 * ------------------------------------------------------------------
 *  Kontodaten (Server, Benutzer, Passwort) liegen als ein mit dem
 *  Konto-Schlüssel verschlüsselter Datensatz in der Datenbank.
 * ================================================================== */
function mail_accounts(array $u): array {
    $out = [];
    foreach (db_all('SELECT * FROM mail_accounts WHERE user_id=? ORDER BY id', [$u['id']]) as $row) {
        $a = dec_row($row['enc'], mk());
        if ($a) {
            $a['id'] = (int) $row['id'];
            $out[] = $a;
        }
    }
    return $out;
}

function mail_account(array $u, int $id): ?array {
    foreach (mail_accounts($u) as $a) {
        if ($a['id'] === $id) {
            return $a;
        }
    }
    return null;
}

function imap_available(): bool {
    return function_exists('imap_open');
}

function imap_mailbox_str(array $acc, string $folder = 'INBOX'): string {
    $enc = $acc['imap_enc'] === 'ssl' ? '/ssl' : ($acc['imap_enc'] === 'tls' ? '/tls' : '/notls');
    $flags = $enc . (empty($acc['validate_cert']) ? '/novalidate-cert' : '/validate-cert');
    return '{' . $acc['imap_host'] . ':' . (int) $acc['imap_port'] . '/imap' . $flags . '}' . $folder;
}

function imap_conn(array $acc, bool $readonly = true) {
    $opts = $readonly ? OP_READONLY : 0;
    return @imap_open(imap_mailbox_str($acc), $acc['username'], (string) $acc['password'], $opts, 1);
}

/** Letzte $count Nachrichten als Overview (nur Kopfzeilen). */
function imap_recent($mbox, int $count = 40): array {
    $total = imap_num_msg($mbox);
    if ($total === 0) {
        return [];
    }
    $from = max(1, $total - $count + 1);
    $ov = imap_fetch_overview($mbox, $from . ':' . $total, 0) ?: [];
    usort($ov, static function ($a, $b) {
        return ($b->uid ?? 0) <=> ($a->uid ?? 0);
    });
    return $ov;
}

function imap_err(): string {
    return imap_last_error() ?: 'unbekannter Fehler';
}

/** Bevorzugten Textteil laden: ['text'=>..,'html'=>..]. */
function imap_body_pref($mbox, int $uid): array {
    $struct = imap_fetchstructure($mbox, $uid, FT_UID);
    $res = ['text' => '', 'html' => ''];
    if (empty($struct->parts)) {
        $data = imap_part_decode(imap_body($mbox, $uid, FT_UID | FT_PEEK), $struct->encoding ?? 0);
        if (($struct->subtype ?? '') === 'HTML') {
            $res['html'] = $data;
        } else {
            $res['text'] = $data;
        }
        return $res;
    }
    imap_walk($mbox, $uid, $struct->parts, '', $res);
    return $res;
}

function imap_walk($mbox, int $uid, array $parts, string $prefix, array &$res): void {
    foreach ($parts as $i => $part) {
        $section = $prefix === '' ? (string) ($i + 1) : $prefix . '.' . ($i + 1);
        $isText  = ($part->type ?? 0) === 0;
        $type    = strtoupper($part->subtype ?? '');
        $isAttach = false;
        foreach (($part->dparameters ?? []) as $p) {
            if (strtoupper($p->attribute) === 'FILENAME') {
                $isAttach = true;
            }
        }
        // text/plain reicht -> HTML gar nicht erst nachladen
        if ($isText && !$isAttach && !($type === 'HTML' && $res['text'] !== '')) {
            $data = imap_part_decode(imap_fetchbody($mbox, $uid, $section, FT_UID | FT_PEEK), $part->encoding ?? 0);
            $cs = '';
            foreach (($part->parameters ?? []) as $p) {
                if (strtoupper($p->attribute) === 'CHARSET') {
                    $cs = $p->value;
                }
            }
            if ($cs && strtoupper($cs) !== 'UTF-8' && function_exists('mb_convert_encoding')) {
                $data = @mb_convert_encoding($data, 'UTF-8', $cs) ?: $data;
            }
            if ($type === 'HTML') {
                $res['html'] .= $data;
            } else {
                $res['text'] .= $data;
            }
        }
        if (!empty($part->parts)) {
            imap_walk($mbox, $uid, $part->parts, $section, $res);
        }
    }
}

function imap_part_decode(string $data, int $enc): string {
    switch ($enc) {
        case 3: return base64_decode($data);
        case 4: return quoted_printable_decode($data);
        default: return $data;
    }
}

function imap_hdr(string $s): string {
    if (!function_exists('imap_mime_header_decode')) {
        return $s;
    }
    $out = '';
    foreach (imap_mime_header_decode($s) as $part) {
        $cs = strtoupper($part->charset);
        $txt = $part->text;
        if ($cs !== 'DEFAULT' && $cs !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $txt = @mb_convert_encoding($txt, 'UTF-8', $cs) ?: $txt;
        }
        $out .= $txt;
    }
    return $out;
}

function mail_sanitize(string $html): string {
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    $html = preg_replace('#\son\w+\s*=\s*"[^"]*"#i', '', $html);
    $html = preg_replace("#\son\w+\s*=\s*'[^']*'#i", '', $html);
    return preg_replace('#javascript:#i', '', $html);
}

/** Minimaler SMTP-Client (SSL oder STARTTLS). true bei Erfolg, sonst Fehlertext. */
function smtp_send(array $acc, string $to, string $subject, string $body) {
    $host = $acc['smtp_host'];
    $port = (int) $acc['smtp_port'];
    $enc  = $acc['smtp_enc'];
    $pass = (string) $acc['password'];
    $verify = !empty($acc['validate_cert']);

    $transport = $enc === 'ssl' ? 'ssl://' : 'tcp://';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify]]);
    $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return "Verbindung fehlgeschlagen ($errstr)";
    }

    $read = static function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = static function (string $c) use ($fp, $read): string {
        fwrite($fp, $c . "\r\n");
        return $read();
    };
    $ehloName = $_SERVER['SERVER_NAME'] ?? 'localhost';

    $read();
    $cmd('EHLO ' . $ehloName);
    if ($enc === 'tls') {
        $cmd('STARTTLS');
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
            return 'STARTTLS fehlgeschlagen';
        }
        $cmd('EHLO ' . $ehloName);
    }
    $r = $cmd('AUTH LOGIN');
    if (strpos($r, '334') !== 0) {
        return 'AUTH nicht unterstützt: ' . trim($r);
    }
    $cmd(base64_encode($acc['username']));
    $r = $cmd(base64_encode($pass));
    if (strpos($r, '235') !== 0) {
        return 'Anmeldung abgelehnt: ' . trim($r);
    }

    $from = $acc['email'];
    $r = $cmd('MAIL FROM:<' . $from . '>');
    if ($r === '' || $r[0] !== '2') {
        return 'MAIL FROM abgelehnt: ' . trim($r);
    }
    $r = $cmd('RCPT TO:<' . $to . '>');
    if ($r === '' || $r[0] !== '2') {
        return 'Empfänger abgelehnt: ' . trim($r);
    }
    $cmd('DATA');

    $headers = 'From: ' . $from . "\r\n"
        . 'To: ' . $to . "\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
        . 'Date: ' . date('r') . "\r\n"
        . 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: base64' . "\r\n";
    $data = $headers . "\r\n" . chunk_split(base64_encode($body));
    $data = preg_replace('/^\./m', '..', $data);
    fwrite($fp, $data . "\r\n.\r\n");
    $r = $read();
    $cmd('QUIT');
    fclose($fp);
    return ($r !== '' && $r[0] === '2') ? true : 'Server-Antwort: ' . trim($r);
}

/* ================================================================== *
 *  Virtuelles Dateisystem (verschlüsselte Namen & Inhalte)
 * ================================================================== */
function vfs_dir(array $u, int $id): ?array {
    if ($id === 0) {
        return ['id' => 0, 'parent_id' => 0, 'is_dir' => 1];
    }
    return db_one('SELECT * FROM vfs WHERE id=? AND user_id=? AND is_dir=1', [$id, $u['id']]);
}

function vfs_node(array $u, int $id): ?array {
    return db_one('SELECT * FROM vfs WHERE id=? AND user_id=?', [$id, $u['id']]);
}

function vfs_name(array $row, array $u): string {
    if (!empty($row['sealed'])) {
        $n = box_open_raw(b64d($row['enc_name']), user_pub($u), user_sec($u));
    } else {
        $n = aead_dec($row['enc_name'], mk());
    }
    return $n === null || $n === '' ? '(unlesbar)' : $n;
}

function vfs_content(array $row, array $u): ?string {
    if ($row['blob'] === '' || !preg_match('/^[0-9a-f]{32}$/', $row['blob'])) {
        return null;
    }
    $raw = @file_get_contents(NX_FILES . '/' . $u['id'] . '/' . $row['blob'] . '.bin');
    if ($raw === false) {
        return null;
    }
    if (!empty($row['sealed'])) {
        return box_open_raw($raw, user_pub($u), user_sec($u));
    }
    return aead_dec_raw($raw, mk());
}

function vfs_store(array $u, int $parent, string $name, string $data): ?string {
    if (!quota_can_store((int) $u['id'], strlen($data))) {
        return 'Speicher voll (Quota überschritten).';
    }
    $dir = NX_FILES . '/' . $u['id'];
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $blobId = bin2hex(random_bytes(16));
    if (@file_put_contents($dir . '/' . $blobId . '.bin', aead_enc_raw($data, (string) mk())) === false) {
        return 'Datei konnte nicht gespeichert werden.';
    }
    db_run('INSERT INTO vfs (user_id,parent_id,is_dir,enc_name,size,blob) VALUES (?,?,0,?,?,?)',
        [$u['id'], $parent, aead_enc($name, (string) mk()), strlen($data), $blobId]);
    return null;
}

function vfs_delete_rec(array $u, int $id): void {
    $node = vfs_node($u, $id);
    if (!$node) {
        return;
    }
    if ((int) $node['is_dir'] === 1) {
        foreach (db_all('SELECT id FROM vfs WHERE user_id=? AND parent_id=?', [$u['id'], $id]) as $c) {
            vfs_delete_rec($u, (int) $c['id']);
        }
    } elseif ($node['blob'] !== '' && preg_match('/^[0-9a-f]{32}$/', $node['blob'])) {
        @unlink(NX_FILES . '/' . $u['id'] . '/' . $node['blob'] . '.bin');
    }
    db_run('DELETE FROM vfs WHERE id=? AND user_id=?', [$id, $u['id']]);
}

/* ================================================================== *
 *  Icons (inline SVG)
 * ================================================================== */
function icon(string $name, int $size = 20): string {
    $p = [
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'note'     => '<path d="M4 4h16v12l-4 4H4z"/><path d="M16 20v-4h4"/><path d="M8 9h8M8 13h5"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/>',
        'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'folder'   => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'link'     => '<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
        'cog'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'trash'    => '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/>',
        'pin'      => '<path d="M12 17v5M9 3h6l-1 6 3 3H7l3-3z"/>',
        'chevL'    => '<path d="M15 18l-6-6 6-6"/>',
        'chevR'    => '<path d="M9 18l6-6-6-6"/>',
        'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'send'     => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>',
        'inbox'    => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5 5h14l3 7v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z"/>',
        'upload'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/>',
        'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/>',
        'moon'     => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'users'    => '<circle cx="9" cy="8" r="3.2"/><path d="M3 21a6 6 0 0 1 12 0"/><path d="M16 5.2a3.2 3.2 0 0 1 0 6M18 21a6 6 0 0 0-4-5.7"/>',
        'back'     => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check'    => '<path d="M20 6L9 17l-5-5"/>',
        'checkbox' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 12l3 3 5-6"/>',
        'square'   => '<rect x="3" y="3" width="18" height="18" rx="3"/>',
        'shield'   => '<path d="M12 3l8 3v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/>',
        'ban'      => '<circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/>',
        'ticket'   => '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4z"/><path d="M13 6v12"/>',
        'db'       => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'chat'     => '<path d="M4 5h16v11h-9l-5 4v-4H4z"/><path d="M8 9h8M8 12h5"/>',
        'video'    => '<rect x="2" y="6" width="13" height="12" rx="2"/><path d="M15 10l7-4v12l-7-4"/>',
        'share'    => '<circle cx="6" cy="12" r="2.6"/><circle cx="18" cy="5.5" r="2.6"/><circle cx="18" cy="18.5" r="2.6"/><path d="M8.4 10.8l7.2-4M8.4 13.2l7.2 4"/>',
        'key'      => '<circle cx="8" cy="14" r="4.5"/><path d="M11.5 10.5L20 2M16 6l3 3"/>',
    ];
    $body = $p[$name] ?? '<circle cx="12" cy="12" r="9"/>';
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        . $body . '</svg>';
}

function logo_glyph(string $color): string {
    return '<path d="M12 12L12 4.6M12 12L5.4 17.6M12 12L18.6 17.6" stroke="' . $color . '" stroke-width="1.7" stroke-linecap="round"/>'
        . '<circle cx="12" cy="4.6" r="1.9" fill="' . $color . '"/>'
        . '<circle cx="5.4" cy="17.6" r="1.9" fill="' . $color . '"/>'
        . '<circle cx="18.6" cy="17.6" r="1.9" fill="' . $color . '"/>'
        . '<circle cx="12" cy="12" r="2.6" fill="' . $color . '"/>';
}

function logo_mark(int $size = 20): string {
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none">'
        . logo_glyph('currentColor') . '</svg>';
}

function logo_favicon(string $accent): string {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'
        . '<rect width="24" height="24" rx="5" fill="' . $accent . '"/>'
        . '<g transform="translate(3.6 3.6) scale(0.7)">' . logo_glyph('#ffffff') . '</g></svg>';
}

/* ================================================================== *
 *  Assets (CSS/JS über ?asset= – cachefähig)
 * ================================================================== */
function serve_asset(string $which): void {
    header('Content-Type: ' . ($which === 'js' ? 'application/javascript' : 'text/css') . '; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    echo $which === 'js' ? asset_js() : asset_css();
    exit;
}

function asset_css(): string {
    return <<<'NXCSS'
/* Nexus – finale GUI. Ruhiges, weiches, vollständig dynamisches
   Interface: fluide Abstände/Typo per clamp(), dvh gegen iOS-
   Adressleisten-Sprünge, Safe-Area-Insets, kompakte Rasterung auf
   kleinen Handys (iPhone SE) bis großzügig auf dem PC. Off-Canvas-Menü
   verschwindet vollständig hinter dem Rand. Rein visuell, keine
   Logikänderung. */
:root{
  --accent:#5b93d6;
  --accent-soft:color-mix(in srgb,var(--accent) 15%,transparent);
  --bg:#0c0e12; --bg2:#111318; --panel:#161920; --panel2:#1d212a;
  --line:#262b34; --txt:#e6e9ee; --muted:#949aa6; --muted2:#626a77;
  --ok:#57b98a; --warn:#cda255; --err:#d97575;
  --radius:14px; --radius-sm:10px;
  --shadow:0 6px 24px -8px rgba(0,0,0,.5);
  --shadow-sm:0 1px 2px rgba(0,0,0,.3);
  /* Dynamische Maße: passen sich stufenlos an die Fensterbreite an */
  --sidebar:clamp(212px,20vw,258px);
  --gap:clamp(10px,1.4vw,14px);
  --main-x:clamp(14px,4vw,34px);
  --main-y:clamp(16px,2.4vw,26px);
  --safe-l:env(safe-area-inset-left,0px);
  --safe-r:env(safe-area-inset-right,0px);
  --safe-b:env(safe-area-inset-bottom,0px);
  --mono:ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace;
  --ui:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
}
[data-theme="light"]{
  --bg:#f4f6f9; --bg2:#eef1f5; --panel:#ffffff; --panel2:#f5f7fa;
  --line:#e4e8ee; --txt:#1a1f27; --muted:#5f6774; --muted2:#98a1ad;
  --ok:#2f9d68; --warn:#a87d2c; --err:#cf5b5b;
  --shadow:0 6px 24px -10px rgba(30,40,60,.2);
  --shadow-sm:0 1px 2px rgba(30,40,60,.06);
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
/* verhindert automatisches Vergrößern des Textes im iOS-Querformat */
html{-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{font-family:var(--ui);font-size:14px;background:var(--bg);color:var(--txt);line-height:1.5;-webkit-font-smoothing:antialiased;overflow-x:hidden}
img,video,iframe,svg{max-width:100%}
a{color:inherit;text-decoration:none}
/* dezente, einheitliche Scrollbalken auf allen Geräten */
*{scrollbar-width:thin;scrollbar-color:var(--line) transparent}
::-webkit-scrollbar{width:11px;height:11px}
::-webkit-scrollbar-thumb{background:var(--line);border-radius:99px;border:3px solid transparent;background-clip:padding-box}
::-webkit-scrollbar-thumb:hover{background:var(--muted2);border:3px solid transparent;background-clip:padding-box}
/* klare Tastatur-Fokusringe, ohne Maus-Klicks zu stören */
:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
a:focus-visible,button:focus-visible,.tile:focus-visible,.iconbtn:focus-visible,.side-user:focus-visible,.nav a:focus-visible{outline:2px solid var(--accent);outline-offset:2px;border-radius:11px}
code,.mono{font-family:var(--mono);font-size:12.5px}
.ic{display:block;flex:none}
button,input,select,textarea{font:inherit;color:inherit}

/* Shell */
.shell{display:grid;grid-template-columns:var(--sidebar) 1fr;min-height:100vh;min-height:100dvh}
.sidebar{background:var(--panel);border-right:1px solid var(--line);padding:18px 14px;padding-left:max(14px,var(--safe-l));display:flex;flex-direction:column;gap:2px;position:sticky;top:0;height:100vh;height:100dvh;overflow-y:auto;overscroll-behavior:contain}
.brand{display:flex;align-items:center;gap:11px;padding:4px 8px 16px;margin-bottom:8px;font-weight:650;font-size:16px;letter-spacing:.2px}
.brand .logo{width:34px;height:34px;border-radius:11px;display:grid;place-items:center;color:#fff;background:var(--accent);box-shadow:var(--shadow-sm)}
.nav{display:flex;flex-direction:column;gap:3px;margin-top:2px}
.nav a{display:flex;align-items:center;gap:12px;padding:9px 12px;border-radius:11px;color:var(--muted);font-weight:500;font-size:13.5px;transition:background .12s,color .12s}
.nav a:hover{background:var(--panel2);color:var(--txt)}
.nav a.active{background:var(--accent-soft);color:var(--accent);font-weight:600}
.nav a.active .ic{color:var(--accent)}
.nav-group{font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:.7px;color:var(--muted2);padding:15px 12px 5px;margin-top:2px}
.nav-group:first-child{margin-top:0}
.nav .dot{width:6px;height:6px;border-radius:50%;margin-left:auto;opacity:.7}
.nav-badge{margin-left:auto;background:var(--accent);color:#fff;font-size:10.5px;font-weight:600;min-width:17px;height:17px;border-radius:9px;display:grid;place-items:center;padding:0 5px;font-family:var(--mono)}
.nav-badge.warn{background:var(--warn)}
.nav-badge+.dot{display:none}
.quota{margin-top:14px;padding:12px;border:1px solid var(--line);border-radius:12px;background:var(--panel2)}
.quota-top{display:flex;justify-content:space-between;font-size:11px;color:var(--muted);margin-bottom:7px}
.quota-bar{height:6px;border-radius:99px;background:var(--bg2);overflow:hidden}
.quota-bar i{display:block;height:100%;border-radius:99px;transition:width .3s}
.side-foot{margin-top:auto;display:flex;flex-direction:column;gap:2px;padding-top:10px}
.side-user{display:flex;align-items:center;gap:11px;padding:9px 10px;border-radius:11px;transition:background .12s}
.side-user:hover{background:var(--panel2)}
.avatar{width:34px;height:34px;border-radius:11px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-weight:650;font-size:14px}
.side-user small{color:var(--muted);display:block;font-size:11.5px}

/* Main */
.main{padding:var(--main-y) var(--main-x) calc(60px + var(--safe-b));padding-right:max(var(--main-x),var(--safe-r));max-width:1240px;width:100%;min-width:0}
.topbar{display:flex;align-items:center;gap:clamp(10px,2vw,14px);margin-bottom:clamp(18px,2.6vw,24px)}
.topbar h1{font-size:clamp(18px,16px + .8vw,21px);font-weight:650;letter-spacing:-.3px}
.topbar .sub{color:var(--muted);font-size:13px;margin-top:3px}
.spacer{flex:1}
.iconbtn{width:38px;height:38px;border-radius:11px;border:1px solid var(--line);background:var(--panel);display:grid;place-items:center;cursor:pointer;color:var(--txt);transition:.12s}
.iconbtn:hover{background:var(--panel2);border-color:var(--muted2)}

/* Buttons / Forms */
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 15px;border-radius:11px;background:var(--accent);color:#fff;border:1px solid transparent;cursor:pointer;font-weight:600;font-size:13.5px;transition:filter .12s,transform .06s;box-shadow:var(--shadow-sm)}
.btn:hover{filter:brightness(1.08)}
.btn:active{transform:translateY(1px)}
.btn.ghost{background:var(--panel);color:var(--txt);border-color:var(--line);box-shadow:none}
.btn.ghost:hover{background:var(--panel2);border-color:var(--muted2)}
.btn.danger{background:var(--err);border-color:transparent}
.btn.ok{background:var(--ok);border-color:transparent}
.btn.sm{padding:6px 11px;font-size:12.5px}
.field{margin-bottom:14px}
.field label{display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;font-weight:500}
.input,textarea,select{width:100%;padding:10px 13px;border-radius:11px;border:1px solid var(--line);background:var(--bg2);color:var(--txt);transition:border-color .12s,box-shadow .12s;outline:none;font-size:13.5px}
.input:focus,textarea:focus,select:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
textarea{resize:vertical;min-height:100px;font-family:inherit}
.row{display:flex;gap:12px;flex-wrap:wrap}
.row>*{flex:1;min-width:0}

/* Cards */
.panel{background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow-sm)}
.grid{display:grid;gap:var(--gap)}
/* dynamische Kacheln: auf iPhone SE kompakt 2-spaltig, auf großen
   Displays bis 210px breit – stufenlos über die Fensterbreite */
.tiles{grid-template-columns:repeat(auto-fill,minmax(min(100%,clamp(148px,44vw,210px)),1fr))}
.tile{position:relative;background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);padding:18px;cursor:pointer;transition:transform .14s,box-shadow .14s,border-color .14s;display:flex;flex-direction:column;gap:13px;min-height:120px;box-shadow:var(--shadow-sm)}
.tile:hover{border-color:color-mix(in srgb,var(--tc,var(--accent)) 45%,var(--line));box-shadow:var(--shadow);transform:translateY(-2px)}
.tile .tico{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;color:var(--tc,var(--accent));background:color-mix(in srgb,var(--tc,var(--accent)) 14%,transparent)}
.tile h3{font-size:15px;font-weight:650}
.tile p{color:var(--muted);font-size:12.5px;margin-top:-6px}
.section-h{display:flex;align-items:center;gap:9px;margin:28px 0 14px;font-size:12px;font-weight:600;color:var(--muted2);text-transform:uppercase;letter-spacing:.7px}
.section-h .ic{width:14px;height:14px}
.section-h::after{content:"";flex:1;height:1px;background:var(--line)}

/* Banner */
.banner{display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border:1px solid var(--line);border-left:3px solid var(--warn);border-radius:var(--radius);background:var(--panel);margin-bottom:18px}
.banner.ok{border-left-color:var(--ok)}
.banner .ic{color:var(--warn);margin-top:1px}
.banner b{font-weight:600}
.banner p{color:var(--muted);font-size:13px;margin-top:2px}

/* Notes */
.masonry{columns:270px;column-gap:12px}
.note{break-inside:avoid;margin-bottom:12px;border-radius:var(--radius);padding:15px;border:1px solid var(--line);background:var(--panel);border-left:3px solid var(--nc,var(--accent));position:relative}
.note h4{margin-bottom:7px;font-size:14px;font-weight:600}
.note .body{color:var(--muted);white-space:pre-wrap;font-size:13px;max-height:320px;overflow:auto}
.note .meta{margin-top:11px;display:flex;align-items:center;gap:7px;font-size:11.5px;color:var(--muted2);font-family:var(--mono)}
.note .acts{margin-left:auto;display:flex;gap:2px}
.note .acts a{padding:4px;border-radius:var(--radius-sm);color:var(--muted)}
.note .acts a:hover{background:var(--panel2);color:var(--txt)}

/* Calendar */
.cal-head{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.cal-head h2{font-size:15px;font-weight:600;min-width:180px;text-align:center}
.cal{display:grid;grid-template-columns:repeat(7,1fr);gap:4px}
.cal .dow{text-align:center;color:var(--muted2);font-size:11px;font-weight:600;padding:4px 0;text-transform:uppercase;letter-spacing:.5px}
.cal .cell{background:var(--panel);border:1px solid var(--line);border-radius:var(--radius-sm);min-height:96px;padding:6px;display:flex;flex-direction:column;gap:3px;transition:border-color .1s;cursor:pointer}
.cal .cell:hover{border-color:var(--muted2)}
.cal .cell.out{opacity:.35}
.cal .cell.today{border-color:var(--accent);background:var(--accent-soft)}
.cal .cell .num{font-size:12px;font-weight:600;color:var(--muted);font-family:var(--mono)}
.cal .cell.today .num{color:var(--accent)}
.ev{font-size:11px;padding:2px 5px;border-radius:3px;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;background:var(--ec,var(--accent));border-left:2px solid rgba(0,0,0,.25)}
.ev small{opacity:.85;font-weight:600;margin-right:3px;font-family:var(--mono)}

/* Tasks */
.tasklist{display:flex;flex-direction:column;border:1px solid var(--line);border-radius:var(--radius);overflow:hidden}
.task{display:flex;align-items:center;gap:12px;padding:11px 14px;border-bottom:1px solid var(--line)}
.task:last-child{border-bottom:0}
.task .tcheck{color:var(--muted2);cursor:pointer;flex:none}
.task.done .ttitle{text-decoration:line-through;color:var(--muted2)}
.task.done .tcheck{color:var(--ok)}
.task .ttitle{flex:1}
.task .tdue{font-size:11.5px;color:var(--muted2);font-family:var(--mono)}
.task .tdue.over{color:var(--err)}
.task .prio{width:6px;height:6px;border-radius:50%;flex:none}
.task .tdel{color:var(--muted);padding:3px;border-radius:var(--radius-sm)}
.task .tdel:hover{background:var(--err);color:#fff}

/* Chat */
.chat-grid{display:grid;grid-template-columns:230px 1fr;gap:14px;align-items:start}
.peer-list{display:flex;flex-direction:column;border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;background:var(--panel)}
.peer{display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid var(--line);color:var(--muted)}
.peer:last-child{border-bottom:0}
.peer:hover{background:var(--panel2);color:var(--txt)}
.peer.active{background:var(--accent-soft);color:var(--accent);font-weight:600}
.peer .nav-badge{margin-left:auto}
.chat-thread{display:flex;flex-direction:column;background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);height:calc(100dvh - 190px);min-height:min(380px,60dvh)}
.chat-head{display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--line)}
.chat-msgs{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:8px}
.bubble{max-width:72%;padding:9px 13px;border-radius:16px;border-bottom-left-radius:5px;font-size:13.5px;white-space:pre-wrap;word-break:break-word;border:1px solid var(--line);background:var(--panel2);align-self:flex-start}
.bubble.me{align-self:flex-end;border-radius:16px;border-bottom-right-radius:5px;background:var(--accent);color:#fff;border-color:transparent}
.bubble.me a,.bubble.me .bfile a{color:#fff;text-decoration:underline}
.bubble.me .bmeta{color:rgba(255,255,255,.75)}
.bubble.sys{align-self:center;background:transparent;color:var(--muted);font-size:12.5px;border-style:dashed;max-width:88%}
.bubble .bmeta{display:block;font-size:10.5px;color:var(--muted2);margin-top:4px;font-family:var(--mono)}
.bubble .bfile{display:flex;align-items:center;gap:8px}
.bubble .bfile a{color:var(--accent);font-weight:500}
.chat-input{display:flex;gap:8px;padding:10px;border-top:1px solid var(--line);align-items:flex-end}
.chat-input textarea{min-height:38px;max-height:140px;flex:1}
.tag-tic{font-family:var(--mono);font-size:10.5px;color:var(--warn);border:1px solid var(--line);border-radius:3px;padding:1px 5px}

/* Video-Anruf */
.callbox{position:fixed;right:max(18px,var(--safe-r));bottom:max(18px,var(--safe-b));z-index:60;background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:10px;display:none;flex-direction:column;gap:8px;width:min(340px,calc(100vw - 28px))}
.callbox.open{display:flex}
.callbox video{width:100%;border-radius:var(--radius-sm);background:#000;aspect-ratio:4/3;object-fit:cover}
.callbox .vlocal{position:absolute;right:16px;top:16px;width:90px;border:1px solid var(--line)}
.callbox .cbar{display:flex;gap:8px;justify-content:center}

/* Mail */
.mail-tabs{display:flex;gap:2px;margin-bottom:14px;flex-wrap:wrap}
.mail-tabs a{padding:6px 12px;border-radius:var(--radius-sm);color:var(--muted);font-size:13px;font-weight:500;display:flex;align-items:center;gap:7px}
.mail-tabs a.active{background:var(--accent-soft);color:var(--accent);font-weight:600}
.mail-list{display:flex;flex-direction:column;border:1px solid var(--line);border-radius:var(--radius);overflow:hidden}
.mail-item{display:flex;gap:12px;padding:10px 14px;border-bottom:1px solid var(--line);cursor:pointer;transition:background .1s;align-items:center}
.mail-item:last-child{border-bottom:0}
.mail-item:hover{background:var(--panel2)}
.mail-item.unseen{font-weight:600}
.mail-item .from{width:190px;flex:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px}
.mail-item .subj{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted);font-size:13px}
.mail-item.unseen .subj{color:var(--txt)}
.mail-item .date{color:var(--muted2);font-size:11.5px;flex:none;font-family:var(--mono)}
.mail-body{white-space:pre-wrap;line-height:1.65;padding:12px 2px;font-size:13.5px}
.mail-body iframe{width:100%;border:1px solid var(--line);background:#fff;border-radius:var(--radius-sm)}

/* Files */
.file-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,clamp(104px,30vw,150px)),1fr));gap:var(--gap)}
.file-card{background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);padding:14px;text-align:center;position:relative;transition:border-color .1s}
.file-card:hover{border-color:var(--muted2)}
.file-card .fi{color:var(--accent);display:grid;place-items:center;margin-bottom:7px}
.file-card .fn{font-size:12.5px;word-break:break-word}
.file-card .fs{font-size:11px;color:var(--muted2);margin-top:3px;font-family:var(--mono)}
.file-card .facts{position:absolute;top:5px;right:5px;display:flex;gap:2px;opacity:0;transition:.1s}
.file-card:hover .facts{opacity:1}
.file-card .facts a{padding:3px;border-radius:var(--radius-sm);color:var(--muted)}
.file-card .facts a:hover{background:var(--panel2);color:var(--txt)}
.file-card .facts a.del:hover{background:var(--err);color:#fff}
.crumb{display:flex;gap:5px;align-items:center;color:var(--muted);margin-bottom:14px;font-size:13px;font-family:var(--mono);flex-wrap:wrap}
.dropzone{border:1px dashed var(--line);border-radius:var(--radius);padding:20px;text-align:center;color:var(--muted);margin-bottom:16px;transition:.1s;cursor:pointer}
.dropzone.drag{border-color:var(--accent);background:color-mix(in srgb,var(--accent) 6%,transparent)}

/* Tabelle */
.table{width:100%;border-collapse:collapse;border:1px solid var(--line);border-radius:var(--radius);overflow:hidden}
.table th,.table td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--line);font-size:13px}
.table th{color:var(--muted2);font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.4px;background:var(--panel2)}
.table tr:last-child td{border-bottom:0}
.table .actions{display:flex;gap:5px;flex-wrap:wrap;justify-content:flex-end}
.wrap-scroll{overflow-x:auto}
.badge{display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;border:1px solid}
.badge.pending{color:var(--warn);border-color:color-mix(in srgb,var(--warn) 45%,transparent);background:color-mix(in srgb,var(--warn) 10%,transparent)}
.badge.active{color:var(--ok);border-color:color-mix(in srgb,var(--ok) 45%,transparent);background:color-mix(in srgb,var(--ok) 10%,transparent)}
.badge.suspended{color:var(--err);border-color:color-mix(in srgb,var(--err) 45%,transparent);background:color-mix(in srgb,var(--err) 10%,transparent)}
.badge.admin{color:var(--accent);border-color:color-mix(in srgb,var(--accent) 45%,transparent);background:color-mix(in srgb,var(--accent) 10%,transparent)}

/* Auth */
.auth-wrap{min-height:100vh;min-height:100dvh;display:grid;place-items:center;padding:clamp(16px,5vw,24px)}
.auth-card{width:100%;max-width:372px;background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:32px;box-shadow:var(--shadow)}
.auth-card .logo{width:50px;height:50px;border-radius:14px;margin:0 auto 18px;display:grid;place-items:center;color:#fff;background:var(--accent);box-shadow:var(--shadow-sm)}
.auth-card h1{text-align:center;font-size:19px;font-weight:650;margin-bottom:4px;letter-spacing:-.2px}
.auth-card .tag{text-align:center;color:var(--muted);margin-bottom:22px;font-size:13px}
.auth-switch{text-align:center;margin-top:16px;color:var(--muted);font-size:13px}
.auth-switch a{color:var(--accent);font-weight:500}
.note-line{font-size:12px;color:var(--muted2);margin:-6px 0 13px}

/* Alerts / Chips / Empty */
.alert{padding:10px 13px;border-radius:var(--radius-sm);margin-bottom:14px;font-size:13px;border:1px solid;border-left-width:3px}
.alert.err{background:color-mix(in srgb,var(--err) 10%,transparent);border-color:color-mix(in srgb,var(--err) 45%,transparent);color:var(--err)}
.alert.ok{background:color-mix(in srgb,var(--ok) 10%,transparent);border-color:color-mix(in srgb,var(--ok) 45%,transparent);color:var(--ok)}
.chip{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:var(--radius-sm);background:var(--panel2);border:1px solid var(--line);font-size:11px;color:var(--muted);font-family:var(--mono);text-transform:uppercase;letter-spacing:.3px}
.empty{text-align:center;padding:50px 20px;color:var(--muted)}
.empty .ic{margin:0 auto 12px;color:var(--muted2);width:40px;height:40px}
.empty h3{font-size:14px;font-weight:600;margin-bottom:4px}
.swatch{display:flex;gap:7px;flex-wrap:wrap}
.swatch label{width:26px;height:26px;border-radius:var(--radius-sm);cursor:pointer;position:relative;border:1px solid var(--line)}
.swatch input{position:absolute;opacity:0}
.swatch input:checked+span{outline:2px solid var(--txt);outline-offset:1px}
.swatch span{position:absolute;inset:0;border-radius:3px}

/* Modal */
.modal{position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(2px);display:none;place-items:center;z-index:50;padding:clamp(12px,4vw,20px);padding-bottom:max(clamp(12px,4vw,20px),var(--safe-b))}
.modal.open{display:grid}
.modal .box{background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:clamp(18px,3vw,24px);width:100%;max-width:500px;box-shadow:var(--shadow);max-height:min(90vh,90dvh);overflow:auto;overscroll-behavior:contain;position:relative}
.modal h3{margin-bottom:18px;font-size:16px;font-weight:650}
.modal-x{position:absolute;top:16px;right:18px;cursor:pointer;color:var(--muted);font-size:20px;line-height:1;width:26px;height:26px;border-radius:8px;display:grid;place-items:center;transition:background .12s}
.modal-x:hover{background:var(--panel2);color:var(--txt)}

/* Responsive – der Grossteil skaliert bereits stufenlos (clamp/fluide
   Raster); die Breakpoints regeln nur noch Umbrüche, die sich nicht
   fluide lösen lassen (Off-Canvas-Menü, Ein-Spalten-Layouts). */
.menu-toggle{display:none}
@media(max-width:880px){
  .shell{grid-template-columns:1fr}
  /* translateX(-100%) blendet die Leiste immer um ihre volle Breite aus –
     sie verschwindet restlos hinter dem Rand, egal wie breit sie ist. */
  .sidebar{position:fixed;top:0;left:0;z-index:40;width:min(86vw,300px);transform:translateX(-100%);transition:transform .24s ease;box-shadow:var(--shadow);will-change:transform}
  .sidebar.open{transform:translateX(0)}
  .menu-toggle{display:grid}
  .mail-item .from{width:clamp(84px,26vw,150px)}
  .chat-grid{grid-template-columns:1fr}
  .peer-list{flex-direction:row;overflow-x:auto;overscroll-behavior-x:contain}
  .peer{border-bottom:0;border-right:1px solid var(--line);white-space:nowrap}
  .chat-thread{height:calc(100dvh - 230px)}
  .backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:39;display:none}
  .backdrop.open{display:block}
}
/* Kleine Handys (iPhone SE & Co.): Eingabefelder >= 16px verhindern das
   automatische Einzoomen von iOS Safari beim Fokussieren; Formularreihen
   stapeln, damit nichts zusammengequetscht wird. Die Kacheln bleiben
   dank fluidem Raster kompakt (i. d. R. 2-spaltig) statt einspaltig. */
@media(max-width:640px){
  .input,textarea,select{font-size:16px}
  .row{flex-direction:column;gap:0}
  .row>*{width:100%;flex:none}
  .topbar{flex-wrap:wrap;gap:8px 12px}
  .cal-head h2{min-width:0}
}
@media(max-width:430px){
  .cal .cell{min-height:56px;padding:4px}
  .cal .cell .num{font-size:11px}
  .ev{font-size:10px;padding:1px 4px}
  .mail-item{padding:9px 11px}
  .bubble{max-width:88%}
  .tile{min-height:auto}
}
NXCSS;
}

function asset_js(): string {
    return <<<'NXJS'
// Theme
function applyTheme(t){document.documentElement.setAttribute('data-theme',t);localStorage.setItem('nx_theme',t);}
(function(){const t=localStorage.getItem('nx_theme');if(t)applyTheme(t);})();
function toggleTheme(){const c=document.documentElement.getAttribute('data-theme')||'dark';const n=c==='dark'?'light':'dark';applyTheme(n);fetch('?action=save_theme&theme='+n);}

// Sidebar (mobil)
function toggleMenu(){document.querySelector('.sidebar')?.classList.toggle('open');document.querySelector('.backdrop')?.classList.toggle('open');}

// Modals
function openModal(id){document.getElementById(id)?.classList.add('open');}
function closeModal(id){if(id){document.getElementById(id)?.classList.remove('open');}else{document.querySelectorAll('.modal.open').forEach(m=>m.classList.remove('open'));}}
document.addEventListener('click',e=>{if(e.target.classList&&e.target.classList.contains('modal'))e.target.classList.remove('open');});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});

// Notizen
function newNote(){const f=document.getElementById('noteForm');if(!f)return;f.reset();f.id.value='';document.getElementById('noteModalTitle').textContent='Neue Notiz';openModal('noteModal');}
function editNote(el){const d=el.dataset,f=document.getElementById('noteForm');openModal('noteModal');f.id.value=d.id;f.title.value=d.title;f.body.value=d.body;f.querySelector('[name=color][value="'+d.color+'"]')?.click();document.getElementById('noteModalTitle').textContent='Notiz bearbeiten';}

// Kalender
function openDay(day){const f=document.getElementById('evForm');if(!f)return;f.reset();f.id.value='';f.day.value=day;document.getElementById('evModalTitle').textContent='Termin am '+day;openModal('evModal');}
function editEvent(el){event.stopPropagation();const d=el.dataset,f=document.getElementById('evForm');f.id.value=d.id;f.title.value=d.title;f.day.value=d.day;f.time.value=d.time;f.end_time.value=d.end||'';f.description.value=d.desc;f.querySelector('[name=color][value="'+d.color+'"]')?.click();document.getElementById('evModalTitle').textContent='Termin bearbeiten';openModal('evModal');}

// Kontakte
function newContact(){const f=document.getElementById('contactForm');if(!f)return;f.reset();f.id.value='';document.getElementById('contactModalTitle').textContent='Neuer Kontakt';openModal('contactModal');}
function editContact(el){const d=el.dataset,f=document.getElementById('contactForm');f.id.value=d.id;f.name.value=d.name;f.email.value=d.email;f.phone.value=d.phone;f.note.value=d.note;document.getElementById('contactModalTitle').textContent='Kontakt bearbeiten';openModal('contactModal');}

// Verwaltung: Quota
function quotaModal(id,name,gb){const f=document.getElementById('quotaForm');if(!f)return;f.uid.value=id;f.gb.value=gb;document.getElementById('quotaUser').textContent=name;openModal('quotaModal');}

// Dateien: Drag & Drop + Teilen
function initDrop(){const dz=document.getElementById('dropzone');if(!dz)return;const inp=document.getElementById('fileInput');
  ['dragenter','dragover'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();dz.classList.add('drag');}));
  ['dragleave','drop'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();dz.classList.remove('drag');}));
  dz.addEventListener('drop',e=>{inp.files=e.dataTransfer.files;document.getElementById('uploadForm').submit();});
  dz.addEventListener('click',()=>inp.click());
  inp.addEventListener('change',()=>document.getElementById('uploadForm').submit());}
document.addEventListener('DOMContentLoaded',initDrop);
function shareModal(id,name){const f=document.getElementById('shareForm');if(!f)return;f.id.value=id;document.getElementById('shareName').textContent=name;openModal('shareModal');}

/* ------------------------------------------------------------------ *
 *  Chat: Polling, Senden, Videoanruf (WebRTC, Medien laufen P2P)
 * ------------------------------------------------------------------ */
let nxPeer=0,nxLast=0,nxTimer=null;
function chatInit(peer,last){
  nxPeer=peer;nxLast=last;
  const box=document.getElementById('chatMsgs');if(box)box.scrollTop=box.scrollHeight;
  const ta=document.getElementById('chatText');
  if(ta)ta.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();chatSend();}});
  if(nxPeer>0)nxTimer=setInterval(chatPoll,3000);
}
function chatPoll(){
  if(document.hidden)return;
  fetch('?app=chat&action=poll&peer='+nxPeer+'&after='+nxLast)
    .then(r=>r.json())
    .then(d=>{ (d.msgs||[]).forEach(m=>{if(m.id>nxLast)nxLast=m.id;chatAppend(m);}); (d.sig||[]).forEach(onSig); })
    .catch(()=>{});
}
function chatAppend(m){
  const box=document.getElementById('chatMsgs');if(!box)return;
  const b=document.createElement('div');
  b.className='bubble'+(m.sys?' sys':(m.me?' me':''));
  if(m.t==='file'){
    const w=document.createElement('span');w.className='bfile';
    const a=document.createElement('a');
    a.textContent=m.name||'Datei';
    if(!m.me)a.href='?app=chat&action=dl&id='+m.id; else a.removeAttribute('href');
    w.appendChild(a);
    const s=document.createElement('span');s.textContent=' ('+(m.hsize||'')+')';w.appendChild(s);
    b.appendChild(w);
  }else{
    b.textContent=m.body||'';
  }
  const meta=document.createElement('span');meta.className='bmeta';meta.textContent=m.ts||'';b.appendChild(meta);
  box.appendChild(b);box.scrollTop=box.scrollHeight;
}
function chatSend(){
  const ta=document.getElementById('chatText');
  const body=(ta.value||'').trim();
  if(body===''||nxPeer<=0)return;
  ta.value='';
  fetch('?app=chat&action=send',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'_csrf='+encodeURIComponent(NXCSRF)+'&peer='+nxPeer+'&body='+encodeURIComponent(body)})
    .then(r=>r.json())
    .then(d=>{if(d.ok){if(d.id>nxLast)nxLast=d.id;chatAppend({id:d.id,me:true,t:'text',body:body,ts:d.ts});}else if(d.err){alert(d.err);}})
    .catch(()=>{});
}

// WebRTC-Videoanruf: Signalisierung über den Server, Medien direkt P2P.
let pc=null,localStream=null,nxRing=false;
function rtcCfg(){return (typeof NXSTUN!=='undefined'&&NXSTUN)?{iceServers:[{urls:NXSTUN}]}:{iceServers:[]};}
function sendSig(o){
  fetch('?app=chat&action=sig',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'_csrf='+encodeURIComponent(NXCSRF)+'&peer='+nxPeer+'&payload='+encodeURIComponent(JSON.stringify(o))});
}
function newPC(){
  const p=new RTCPeerConnection(rtcCfg());
  p.onicecandidate=e=>{if(e.candidate)sendSig({type:'ice',c:e.candidate});};
  p.ontrack=e=>{const v=document.getElementById('vRemote');if(v)v.srcObject=e.streams[0];};
  return p;
}
async function callMedia(){
  localStream=await navigator.mediaDevices.getUserMedia({video:true,audio:true});
  const v=document.getElementById('vLocal');if(v)v.srcObject=localStream;
  return localStream;
}
async function startCall(){
  if(pc||nxPeer<=0)return;
  try{
    document.getElementById('callbox').classList.add('open');
    await callMedia();pc=newPC();
    localStream.getTracks().forEach(t=>pc.addTrack(t,localStream));
    const o=await pc.createOffer();await pc.setLocalDescription(o);
    sendSig({type:'offer',sdp:pc.localDescription});
  }catch(e){alert('Kamera/Mikrofon nicht verfügbar: '+e.message);endCall(false);}
}
async function onSig(s){
  if(!s||!s.type)return;
  try{
    if(s.type==='offer'){
      if(!pc){
        if(nxRing)return;nxRing=true;
        const ok=confirm('Eingehender Videoanruf – annehmen?');nxRing=false;
        if(!ok){sendSig({type:'bye'});return;}
        document.getElementById('callbox').classList.add('open');
        await callMedia();pc=newPC();
        localStream.getTracks().forEach(t=>pc.addTrack(t,localStream));
      }
      await pc.setRemoteDescription(s.sdp);
      const a=await pc.createAnswer();await pc.setLocalDescription(a);
      sendSig({type:'answer',sdp:pc.localDescription});
    }else if(s.type==='answer'&&pc){
      await pc.setRemoteDescription(s.sdp);
    }else if(s.type==='ice'&&pc){
      try{await pc.addIceCandidate(s.c);}catch(e){}
    }else if(s.type==='bye'){
      endCall(false);
    }
  }catch(e){}
}
function endCall(notify){
  if(notify===undefined)notify=true;
  if(notify&&nxPeer>0)sendSig({type:'bye'});
  if(pc){pc.close();pc=null;}
  if(localStream){localStream.getTracks().forEach(t=>t.stop());localStream=null;}
  document.getElementById('callbox')?.classList.remove('open');
}
NXJS;
}

/* ================================================================== *
 *  Layout
 * ================================================================== */
function layout_head(array $user, string $activeApp): void {
    sec_headers();
    $theme  = $user['theme'] ?: 'dark';
    $accent = $user['accent'] ?: '#4d7ea8';
    $apps   = nx_apps();
    $meta   = $apps[$activeApp] ?? $apps['home'];

    echo '<!doctype html><html lang="de" data-theme="' . h($theme) . '"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">';
    echo '<title>' . h($meta['name']) . ' · ' . NX_NAME . '</title>';
    echo '<link rel="stylesheet" href="?asset=css&v=' . NX_VERSION . '">';
    echo '<style>:root{--accent:' . h($accent) . '}</style>';
    echo '<link rel="icon" href="data:image/svg+xml,' . rawurlencode(logo_favicon($accent)) . '">';
    echo '</head><body>';
    echo '<div class="backdrop" onclick="toggleMenu()"></div><div class="shell">';

    echo '<aside class="sidebar">';
    echo '<div class="brand"><div class="logo">' . logo_mark(20) . '</div>' . NX_NAME . '</div>';
    echo '<nav class="nav">';
    foreach (nx_groups() as $label => $grp) {
        // Nur zugängliche Apps dieser Gruppe (Zugriffslogik unverändert)
        $items = [];
        foreach ($grp['apps'] as $id) {
            if (isset($apps[$id]) && can_access($user, $id)) {
                $items[] = $id;
            }
        }
        if (!$items) {
            continue; // leere Gruppe komplett ausblenden
        }
        if ($label !== '') {
            echo '<div class="nav-group">' . h($label) . '</div>';
        }
        foreach ($items as $id) {
            $a      = $apps[$id];
            $active = $id === $activeApp ? ' active' : '';
            $badge  = '';
            if ($id === 'chat') {
                $unseen = chat_unread((int) $user['id']);
                if ($unseen > 0) {
                    $badge = '<span class="nav-badge">' . $unseen . '</span>';
                }
            }
            if ($id === 'admin') {
                $open = tickets_open_count();
                if ($open > 0) {
                    $badge = '<span class="nav-badge warn">' . $open . '</span>';
                }
            }
            echo '<a class="' . $active . '" href="' . url($id) . '">' . icon($a['icon'])
                . '<span>' . h($a['name']) . '</span>' . $badge
                . '<span class="dot" style="background:' . h($a['color']) . '"></span></a>';
        }
    }
    echo '</nav>';

    $used = quota_used((int) $user['id']);
    $max  = (int) $user['quota_bytes'];
    $pct  = $max > 0 ? min(100, (int) round($used / $max * 100)) : 0;
    $barc = $pct >= 90 ? 'var(--err)' : ($pct >= 70 ? 'var(--warn)' : 'var(--accent)');
    echo '<div class="quota">';
    echo '<div class="quota-top"><span>Speicher</span><span class="mono">' . human_size($used) . ' / ' . human_size($max) . '</span></div>';
    echo '<div class="quota-bar"><i style="width:' . $pct . '%;background:' . $barc . '"></i></div>';
    echo '</div>';

    echo '<div class="side-foot">';
    echo '<a class="side-user" href="' . url('settings') . '">';
    echo '<div class="avatar">' . h(strtoupper(substr($user['display_name'] ?: $user['username'], 0, 1))) . '</div>';
    $roleLbl = $user['role'] === 'admin' ? 'Administrator'
        : ($user['status'] === 'pending' ? 'Wartet auf Freischaltung' : 'Benutzer');
    echo '<div><strong>' . h($user['display_name'] ?: $user['username']) . '</strong><small>' . h($roleLbl) . '</small></div></a>';
    echo '<a class="side-user" style="color:var(--muted)" href="?action=logout">' . icon('logout', 20) . '<span>Abmelden</span></a>';
    echo '</div></aside><main class="main">';
}

function layout_topbar(string $title, string $sub = '', string $actions = ''): void {
    $user = current_user();
    echo '<div class="topbar">';
    echo '<button class="iconbtn menu-toggle" onclick="toggleMenu()">' . icon('grid') . '</button>';
    echo '<div><h1>' . h($title) . '</h1>';
    if ($sub !== '') {
        echo '<div class="sub">' . h($sub) . '</div>';
    }
    echo '</div><div class="spacer"></div>' . $actions;
    $isDark = ($user['theme'] ?? 'dark') === 'dark';
    echo '<button class="iconbtn" title="Theme wechseln" onclick="toggleTheme()">' . icon($isDark ? 'sun' : 'moon') . '</button>';
    echo '</div>';
    if ($f = flash()) {
        echo '<div class="alert ' . h($f['type']) . '">' . h($f['msg']) . '</div>';
    }
}

function layout_foot(): void {
    echo '</main></div><script src="?asset=js&v=' . NX_VERSION . '"></script></body></html>';
}

function color_swatch(string $field, string $current, array $colors = []): string {
    if (!$colors) {
        $colors = ['#4d7ea8', '#4a9d6f', '#b3893f', '#c25a5a', '#8a7fb0', '#4a8ca0', '#7a828e', '#546072'];
    }
    $out = '<div class="swatch">';
    foreach ($colors as $c) {
        $chk = strcasecmp($c, $current) === 0 ? 'checked' : '';
        $out .= '<label><input type="radio" name="' . $field . '" value="' . $c . '" ' . $chk . '><span style="background:' . $c . '"></span></label>';
    }
    return $out . '</div>';
}

/* ================================================================== *
 *  Auth-Ansichten
 * ================================================================== */
function view_auth(string $mode, ?string $err = null): void {
    sec_headers();
    $firstUser = user_count() === 0;
    echo '<!doctype html><html lang="de" data-theme="dark"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">';
    echo '<title>' . ($mode === 'register' ? 'Registrieren' : 'Anmelden') . ' · ' . NX_NAME . '</title>';
    echo '<link rel="stylesheet" href="?asset=css&v=' . NX_VERSION . '">';
    echo '<script>const t=localStorage.getItem("nx_theme");if(t)document.documentElement.setAttribute("data-theme",t);</script>';
    echo '</head><body><div class="auth-wrap"><div class="auth-card">';
    echo '<div class="logo">' . logo_mark(26) . '</div>';

    if ($mode === 'register') {
        echo '<h1>Konto anlegen</h1>';
        echo '<p class="tag">' . ($firstUser
            ? 'Das erste Konto wird Administrator.'
            : 'Ein Administrator schaltet dein Konto frei.') . '</p>';
    } else {
        echo '<h1>Anmelden</h1><p class="tag">&nbsp;</p>';
    }
    if ($err) {
        echo '<div class="alert err">' . h($err) . '</div>';
    }

    echo '<form method="post" action="?action=' . ($mode === 'register' ? 'register' : 'login') . '">';
    echo csrf_field();
    echo '<div class="field"><label>Benutzername</label><input class="input" name="username" autofocus autocomplete="username" required value="' . h(param('username')) . '"></div>';
    if ($mode === 'register') {
        echo '<div class="field"><label>E-Mail</label><input class="input" type="email" name="email" autocomplete="email" required value="' . h(param('email')) . '"></div>';
    }
    echo '<div class="field"><label>Passwort</label><input class="input" type="password" name="password" autocomplete="' . ($mode === 'register' ? 'new-password' : 'current-password') . '" required></div>';
    if ($mode === 'register') {
        echo '<div class="field"><label>Passwort bestätigen</label><input class="input" type="password" name="password2" autocomplete="new-password" required></div>';
        echo '<p class="note-line">Alle Inhalte werden mit dem Passwort verschlüsselt. Ein Zurücksetzen ist nicht möglich.</p>';
    }
    echo '<button class="btn" style="width:100%;justify-content:center" type="submit">' . ($mode === 'register' ? 'Konto anlegen' : 'Anmelden') . '</button>';
    echo '</form>';

    echo '<div class="auth-switch">' . ($mode === 'register'
        ? 'Bereits registriert? <a href="?view=login">Anmelden</a>'
        : 'Noch kein Konto? <a href="?view=register">Registrieren</a>') . '</div>';
    echo '</div></div><script src="?asset=js&v=' . NX_VERSION . '"></script></body></html>';
}

function view_locked(): void {
    sec_headers();
    echo '<!doctype html><html lang="de" data-theme="dark"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">';
    echo '<title>Gesperrt · ' . NX_NAME . '</title><link rel="stylesheet" href="?asset=css&v=' . NX_VERSION . '">';
    echo '</head><body><div class="auth-wrap"><div class="auth-card" style="text-align:center">';
    echo '<div class="logo" style="color:var(--err)">' . icon('ban', 26) . '</div>';
    echo '<h1>Konto gesperrt</h1>';
    echo '<p class="tag">Dieses Konto wurde durch einen Administrator gesperrt.</p>';
    echo '<a class="btn ghost" style="width:100%;justify-content:center" href="?action=logout">Abmelden</a>';
    echo '</div></div></body></html>';
}

/* ================================================================== *
 *  APP: STARTSEITE
 * ================================================================== */
function render_home(array $u): void {
    layout_topbar('Übersicht', 'Angemeldet als ' . ($u['display_name'] ?: $u['username']) . ' · ' . date('d.m.Y H:i'));

    if ($u['status'] === 'pending') {
        $t = ticket_for_user((int) $u['id']);
        echo '<div class="banner">' . icon('clock', 20) . '<div>';
        echo '<b>Konto wartet auf Freischaltung</b>';
        echo '<p>Ein Administrator prüft deine Registrierung. Bis dahin steht dir der '
            . '<a href="' . url('chat') . '" style="color:var(--accent)">Chat mit dem Administrator</a> zur Verfügung. '
            . 'Ticket: <span class="mono">' . h($t['code'] ?? '—') . '</span></p>';
        echo '</div></div>';
    }

    if (can_access($u, 'bookmarks')) {
        $bm = db_all('SELECT * FROM bookmarks WHERE user_id=? ORDER BY position,id', [$u['id']]);
        $tiles = '';
        foreach ($bm as $row) {
            $b = dec_row($row['enc'], mk());
            if (!$b) {
                continue;
            }
            $tiles .= '<a class="tile" style="--tc:' . h($b['color'] ?? '#4d7ea8') . ';min-height:auto" href="' . h($b['url'] ?? '#') . '" target="_blank" rel="noopener">';
            $tiles .= '<div class="tico" style="width:32px;height:32px">' . icon('link', 16) . '</div>';
            $tiles .= '<div><h3 style="font-size:14px">' . h($b['title'] ?? '') . '</h3><p style="font-size:11.5px;word-break:break-all;margin:0">'
                . h(preg_replace('#^https?://#', '', (string) ($b['url'] ?? ''))) . '</p></div></a>';
        }
        if ($tiles !== '') {
            echo '<div class="section-h">' . icon('link') . ' Schnellzugriff</div><div class="grid tiles">' . $tiles . '</div>';
        }
    }

    $apps = nx_apps();
    foreach (nx_groups() as $label => $grp) {
        if ($label === '') {
            continue; // Startseite selbst ist keine Kachel
        }
        // Kacheln dieser Gruppe (Zugriffslogik unverändert)
        $tiles = '';
        foreach ($grp['apps'] as $id) {
            $a = $apps[$id] ?? null;
            if (!$a || empty($a['tile']) || !can_access($u, $id)) {
                continue;
            }
            $tiles .= '<a class="tile" style="--tc:' . h($a['color']) . '" href="' . url($id) . '">';
            $tiles .= '<div class="tico">' . icon($a['icon'], 22) . '</div>';
            $tiles .= '<div><h3>' . h($a['name']) . '</h3><p>' . h($a['desc']) . '</p></div></a>';
        }
        if ($tiles === '') {
            continue; // leere Gruppe ausblenden
        }
        $gicon = $grp['icon'] !== '' ? icon($grp['icon']) : icon('grid');
        echo '<div class="section-h">' . $gicon . ' ' . h($label) . '</div>';
        echo '<div class="grid tiles">' . $tiles . '</div>';
    }

    if (can_access($u, 'calendar')) {
        $rows = db_all('SELECT * FROM events WHERE user_id=? AND day=?', [$u['id'], date('Y-m-d')]);
        $evs = [];
        foreach ($rows as $row) {
            $e = dec_row($row['enc'], mk());
            if ($e) {
                $e['id'] = $row['id'];
                $evs[] = $e;
            }
        }
        usort($evs, static function ($a, $b) {
            return strcmp((string) ($a['time'] ?? ''), (string) ($b['time'] ?? ''));
        });
        echo '<div class="section-h">' . icon('calendar') . ' Heute</div><div class="panel">';
        if ($evs) {
            foreach ($evs as $e) {
                echo '<div style="display:flex;align-items:center;gap:14px;padding:10px 0;border-bottom:1px solid var(--line)">';
                echo '<span style="width:9px;height:9px;border-radius:50%;background:' . h($e['color'] ?? '#c25a5a') . ';flex:none"></span>';
                echo '<strong class="mono" style="min-width:56px">' . h(($e['time'] ?? '') !== '' ? $e['time'] : '—') . '</strong>';
                echo '<div style="flex:1">' . h($e['title'] ?? '');
                if (!empty($e['desc'])) {
                    echo '<br><small style="color:var(--muted)">' . h($e['desc']) . '</small>';
                }
                echo '</div></div>';
            }
        } else {
            echo '<div class="empty" style="padding:26px">' . icon('clock', 34) . '<p>Keine Termine für heute.</p></div>';
        }
        echo '</div>';
    }
}

/* ================================================================== *
 *  APP: CHAT (Text, Dateien, Videoanruf)
 * ================================================================== */
function chat_msg_view(array $m, array $u): array {
    $pl = chat_payload($m, $u);
    $me = (int) $m['sender_id'] === (int) $u['id'];
    $t  = $pl['t'] ?? 'text';
    return [
        'id'    => (int) $m['id'],
        'me'    => $me,
        't'     => $t === 'file' ? 'file' : 'text',
        'sys'   => $t === 'sys',
        'body'  => $t === 'file' ? '' : (string) ($pl['body'] ?? ($pl ? '' : '(nicht entschlüsselbar)')),
        'name'  => (string) ($pl['name'] ?? ''),
        'hsize' => isset($pl['size']) ? human_size((int) $pl['size']) : '',
        'ts'    => fmt_short((string) $m['created_at']),
        'tic'   => (string) $m['ticket_code'],
    ];
}

function chat_bubble(array $v): string {
    $cls = 'bubble' . ($v['sys'] ? ' sys' : ($v['me'] ? ' me' : ''));
    if ($v['t'] === 'file') {
        $lbl = h($v['name'] !== '' ? $v['name'] : 'Datei') . ' <span style="color:var(--muted2)">(' . h($v['hsize']) . ')</span>';
        if ($v['me']) {
            $inner = '<span class="bfile">' . icon('file', 15) . ' <span>' . $lbl . '</span></span>';
        } else {
            $inner = '<span class="bfile">' . icon('file', 15)
                . ' <a href="?app=chat&action=dl&id=' . $v['id'] . '">' . $lbl . '</a>'
                . ' <a title="In Dateien sichern" href="?app=chat&action=keep&id=' . $v['id'] . '&_csrf=' . csrf_token() . '">' . icon('download', 14) . '</a></span>';
        }
    } else {
        $inner = linkify(h($v['body']));
        if ($v['tic'] !== '') {
            $inner = '<span class="tag-tic">' . h($v['tic']) . '</span> ' . $inner;
        }
    }
    return '<div class="' . $cls . '">' . $inner . '<span class="bmeta">' . h($v['ts']) . '</span></div>';
}

function chat_dl_name(string $n): string {
    $n = preg_replace('/[\r\n"\\\\\/]+/', '_', $n);
    return $n === '' ? 'datei' : $n;
}

function handle_chat(array $u, string $action): void {
    $me = (int) $u['id'];

    if ($action === 'send') {
        csrf_check_post();
        header('Content-Type: application/json');
        $peer = chat_peer_id($u, param_int('peer'));
        $body = trim(param('body'));
        if ($peer === 0 || $body === '' || strlen($body) > 100000) {
            echo '{"ok":false}';
            exit;
        }
        $pending = $u['status'] === 'pending';
        $ticket  = '';
        if ($pending) {
            $t = ticket_for_user($me);
            $ticket = $t['code'] ?? '';
        }
        $res = chat_send($me, mk(), $peer, ['t' => 'text', 'body' => $body], $ticket, !$pending);
        if (is_int($res)) {
            echo json_encode(['ok' => true, 'id' => $res, 'ts' => date('H:i')]);
        } else {
            echo json_encode(['ok' => false, 'err' => $res]);
        }
        exit;
    }

    if ($action === 'poll') {
        header('Content-Type: application/json');
        $peer  = chat_peer_id($u, param_int('peer'));
        $after = param_int('after');
        $msgs  = [];
        $sigs  = [];
        if ($peer > 0) {
            $rows = db_all('SELECT * FROM chat WHERE id>? AND ((sender_id=? AND recipient_id=? AND del_sender=0)
                            OR (sender_id=? AND recipient_id=? AND del_recip=0)) ORDER BY id LIMIT 50',
                [$after, $me, $peer, $peer, $me]);
            foreach ($rows as $m) {
                $msgs[] = chat_msg_view($m, $u);
            }
            db_run('UPDATE chat SET seen=1 WHERE recipient_id=? AND sender_id=? AND seen=0', [$me, $peer]);
            foreach (db_all('SELECT * FROM rtc WHERE to_id=? AND from_id=? ORDER BY id', [$me, $peer]) as $s) {
                $p = json_decode($s['payload'], true);
                if (is_array($p)) {
                    $sigs[] = $p;
                }
            }
            db_run('DELETE FROM rtc WHERE to_id=? AND from_id=?', [$me, $peer]);
            db_run('DELETE FROM rtc WHERE created_at < ?', [time() - 180]);
        }
        echo json_encode(['msgs' => $msgs, 'sig' => $sigs]);
        exit;
    }

    if ($action === 'sig') {
        csrf_check_post();
        header('Content-Type: application/json');
        $peer = chat_peer_id($u, param_int('peer'));
        $pl = param('payload');
        if ($peer > 0 && $pl !== '' && strlen($pl) < 200000 && json_decode($pl) !== null) {
            db_run('INSERT INTO rtc (from_id,to_id,payload,created_at) VALUES (?,?,?,?)', [$me, $peer, $pl, time()]);
        }
        echo '{"ok":true}';
        exit;
    }

    if ($action === 'file') {
        csrf_check_post();
        $peer = chat_peer_id($u, param_int('peer'));
        if ($peer > 0 && !empty($_FILES['file']) && is_uploaded_file((string) $_FILES['file']['tmp_name'])) {
            $name = basename((string) $_FILES['file']['name']);
            $data = (string) file_get_contents($_FILES['file']['tmp_name']);
            $ticket = '';
            if ($u['status'] === 'pending') {
                $t = ticket_for_user($me);
                $ticket = $t['code'] ?? '';
            }
            $res = chat_send_file($u, $peer, $name, $data, $ticket);
            flash(is_int($res) ? 'Datei gesendet.' : (string) $res, is_int($res) ? 'ok' : 'err');
        }
        redirect(url('chat', ['peer' => $peer]));
    }

    if ($action === 'dl') {
        $m = db_one('SELECT * FROM chat WHERE id=? AND recipient_id=? AND del_recip=0', [param_int('id'), $me]);
        if ($m && $m['blob'] !== '' && preg_match('/^[0-9a-f]{32}$/', $m['blob'])) {
            $pl  = chat_payload($m, $u);
            $raw = @file_get_contents(NX_CHATBLOB . '/' . $m['blob'] . '.bin');
            $data = $raw === false ? null : box_open_raw($raw, user_pub($u), user_sec($u));
            if ($data !== null) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . chat_dl_name((string) ($pl['name'] ?? '')) . '"');
                header('Content-Length: ' . strlen($data));
                echo $data;
                exit;
            }
        }
        http_response_code(404);
        exit('Nicht gefunden.');
    }

    if ($action === 'keep') {
        csrf_check_get();
        $m = db_one('SELECT * FROM chat WHERE id=? AND recipient_id=? AND del_recip=0', [param_int('id'), $me]);
        $peer = $m ? (int) $m['sender_id'] : 0;
        if ($m && $m['blob'] !== '' && preg_match('/^[0-9a-f]{32}$/', $m['blob'])) {
            $pl  = chat_payload($m, $u);
            $raw = @file_get_contents(NX_CHATBLOB . '/' . $m['blob'] . '.bin');
            $data = $raw === false ? null : box_open_raw($raw, user_pub($u), user_sec($u));
            if ($data !== null) {
                $err = vfs_store($u, 0, (string) ($pl['name'] ?? 'datei'), $data);
                flash($err === null ? 'In Dateien gespeichert.' : $err, $err === null ? 'ok' : 'err');
            }
        }
        redirect(url('chat', ['peer' => $peer]));
    }

    if ($action === 'del') {
        csrf_check_get();
        chat_delete(param_int('id'), $me);
        redirect(url('chat', ['peer' => param_int('peer')]));
    }
}

function render_chat(array $u): void {
    $me = (int) $u['id'];
    $peer = chat_peer_id($u, param_int('peer'));

    if ($u['status'] === 'pending') {
        $peers = [];
        $a = first_admin();
        if ($a) {
            $peers[] = $a;
        }
    } elseif ($u['role'] === 'admin') {
        $peers = db_all("SELECT * FROM users WHERE id!=? AND status!='suspended' ORDER BY (status='pending') DESC, username", [$me]);
    } else {
        $peers = db_all("SELECT * FROM users WHERE id!=? AND status='active' ORDER BY username", [$me]);
    }
    if ($peer === 0 && $peers) {
        $peer = (int) $peers[0]['id'];
    }
    $unreadBy = [];
    foreach (db_all('SELECT sender_id s, COUNT(*) c FROM chat WHERE recipient_id=? AND seen=0 AND del_recip=0 GROUP BY sender_id', [$me]) as $r) {
        $unreadBy[(int) $r['s']] = (int) $r['c'];
    }

    $actions = $peer > 0 ? '<button class="btn ghost" onclick="startCall()">' . icon('video') . ' Videoanruf</button>' : '';
    layout_topbar('Chat', '', $actions);

    if ($u['status'] === 'pending') {
        $t = ticket_for_user($me);
        echo '<div class="banner">' . icon('ticket', 18) . '<div><b>Freischaltung ausstehend</b>'
            . '<p>Bis zur Freischaltung kannst du nur den Administrator kontaktieren. '
            . 'Ticket: <span class="mono">' . h($t['code'] ?? '—') . '</span></p></div></div>';
    }

    echo '<div class="chat-grid">';

    echo '<div class="peer-list">';
    if (!$peers) {
        echo '<div class="peer" style="color:var(--muted2)">Keine Kontakte</div>';
    }
    foreach ($peers as $p) {
        $pid = (int) $p['id'];
        $cls = 'peer' . ($pid === $peer ? ' active' : '');
        $badge = isset($unreadBy[$pid]) ? '<span class="nav-badge">' . $unreadBy[$pid] . '</span>' : '';
        $sub = $p['status'] === 'pending' ? ' <span class="tag-tic">wartet</span>' : '';
        echo '<a class="' . $cls . '" href="' . url('chat', ['peer' => $pid]) . '">';
        echo '<div class="avatar" style="width:26px;height:26px;font-size:12px">' . h(strtoupper(substr($p['display_name'] ?: $p['username'], 0, 1))) . '</div>';
        echo '<span>' . h($p['display_name'] ?: $p['username']) . '</span>' . $sub . $badge . '</a>';
    }
    echo '</div>';

    if ($peer > 0) {
        $pu = user_by_id($peer);
        $rows = db_all('SELECT * FROM chat WHERE ((sender_id=? AND recipient_id=? AND del_sender=0)
                        OR (sender_id=? AND recipient_id=? AND del_recip=0)) ORDER BY id DESC LIMIT 100',
            [$me, $peer, $peer, $me]);
        $rows = array_reverse($rows);
        $last = 0;

        echo '<div class="chat-thread">';
        echo '<div class="chat-head"><div class="avatar" style="width:26px;height:26px;font-size:12px">'
            . h(strtoupper(substr($pu['display_name'] ?: $pu['username'], 0, 1))) . '</div>';
        echo '<strong>' . h($pu['display_name'] ?: $pu['username']) . '</strong>';
        if ($pu['status'] === 'pending') {
            $t = ticket_for_user($peer);
            echo '<span class="tag-tic">' . h($t['code'] ?? 'wartet') . '</span>';
        }
        echo '</div>';
        echo '<div class="chat-msgs" id="chatMsgs">';
        foreach ($rows as $m) {
            $v = chat_msg_view($m, $u);
            $last = max($last, $v['id']);
            echo chat_bubble($v);
        }
        if (!$rows) {
            echo '<div class="empty" style="padding:30px">' . icon('chat', 34) . '<p>Noch keine Nachrichten.</p></div>';
        }
        echo '</div>';

        echo '<div class="chat-input">';
        echo '<form method="post" action="?app=chat&action=file" enctype="multipart/form-data" style="display:flex">' . csrf_field();
        echo '<input type="hidden" name="peer" value="' . $peer . '">';
        echo '<label class="iconbtn" title="Datei senden" style="cursor:pointer">' . icon('upload', 18)
            . '<input type="file" name="file" hidden onchange="this.form.submit()"></label></form>';
        echo '<textarea id="chatText" placeholder="Nachricht…" rows="1"></textarea>';
        echo '<button class="btn" onclick="chatSend()" title="Senden">' . icon('send', 18) . '</button>';
        echo '</div></div>';

        db_run('UPDATE chat SET seen=1 WHERE recipient_id=? AND sender_id=? AND seen=0', [$me, $peer]);

        echo '<div class="callbox" id="callbox" style="position:fixed">';
        echo '<div style="position:relative"><video id="vRemote" autoplay playsinline></video>';
        echo '<video id="vLocal" class="vlocal" autoplay playsinline muted style="position:absolute"></video></div>';
        echo '<div class="cbar"><button class="btn danger sm" onclick="endCall()">Auflegen</button></div></div>';

        echo '<script>const NXCSRF=' . json_encode(csrf_token()) . ',NXSTUN=' . json_encode(NX_STUN) . ';'
            . 'document.addEventListener("DOMContentLoaded",function(){chatInit(' . $peer . ',' . $last . ');});</script>';
    } else {
        echo '<div class="empty">' . icon('chat', 40) . '<h3>Kein Chat ausgewählt</h3></div>';
    }
    echo '</div>';
}

/* ================================================================== *
 *  APP: MAIL (externe IMAP/SMTP-Konten)
 * ================================================================== */
function handle_mail(array $u, string $action): void {
    if ($action === 'acc_save') {
        csrf_check_post();
        $acc = [
            'label'         => trim(param('label')),
            'email'         => trim(param('email')),
            'imap_host'     => trim(param('imap_host')),
            'imap_port'     => param_int('imap_port', 993) ?: 993,
            'imap_enc'      => in_array(param('imap_enc'), ['ssl', 'tls', 'notls'], true) ? param('imap_enc') : 'ssl',
            'smtp_host'     => trim(param('smtp_host')),
            'smtp_port'     => param_int('smtp_port', 465) ?: 465,
            'smtp_enc'      => param('smtp_enc') === 'tls' ? 'tls' : 'ssl',
            'username'      => trim(param('username')),
            'password'      => param('password'),
            'validate_cert' => param('validate_cert') ? 1 : 0,
        ];
        if ($acc['label'] === '' || $acc['email'] === '' || $acc['imap_host'] === '' || $acc['smtp_host'] === '') {
            flash('Bitte alle Felder ausfüllen.', 'err');
            redirect(url('mail', ['new' => 1]));
        }
        db_run('INSERT INTO mail_accounts (user_id,enc) VALUES (?,?)', [$u['id'], enc_row($acc, (string) mk())]);
        flash('Mail-Konto verbunden.');
        redirect(url('mail', ['acc' => db_lastid()]));
    }
    if ($action === 'send') {
        csrf_check_post();
        $acc = mail_account($u, param_int('acc'));
        if ($acc) {
            $res = smtp_send($acc, trim(param('to')), param('subject'), param('body'));
            flash($res === true ? 'Nachricht gesendet.' : 'Fehler: ' . $res, $res === true ? 'ok' : 'err');
            redirect(url('mail', ['acc' => $acc['id']]));
        }
        redirect(url('mail'));
    }
}

function mail_tabs(array $accts, int $activeId): string {
    $t = '<div class="mail-tabs">';
    foreach ($accts as $a) {
        $cls = $a['id'] === $activeId ? 'active' : '';
        $t .= '<a class="' . $cls . '" href="' . url('mail', ['acc' => $a['id']]) . '">' . icon('mail', 16) . ' ' . h($a['label']) . '</a>';
    }
    $t .= '<a href="' . url('mail', ['new' => 1]) . '">' . icon('plus', 16) . ' Konto</a>';
    return $t . '</div>';
}

function render_mail(array $u): void {
    $accts = mail_accounts($u);
    if (param('new') === '1' || !$accts) {
        mail_setup_form();
        return;
    }
    $accId = param_int('acc') ?: $accts[0]['id'];
    $acc = mail_account($u, $accId) ?: $accts[0];

    if (param('compose')) {
        layout_topbar('Neue Nachricht', 'von ' . $acc['email'],
            '<a class="btn ghost" href="' . url('mail', ['acc' => $acc['id']]) . '">' . icon('back') . ' Abbrechen</a>');
        echo '<div class="panel" style="max-width:680px"><form method="post" action="?app=mail&action=send">' . csrf_field();
        echo '<input type="hidden" name="acc" value="' . $acc['id'] . '">';
        echo '<div class="field"><label>An</label><input class="input" type="email" name="to" value="' . h(param('to')) . '" required></div>';
        echo '<div class="field"><label>Betreff</label><input class="input" name="subject"></div>';
        echo '<div class="field"><label>Nachricht</label><textarea name="body" style="min-height:240px" required></textarea></div>';
        echo '<button class="btn" type="submit">' . icon('send') . ' Senden</button>';
        echo '</form></div>';
        return;
    }

    $uid = param_int('uid');
    layout_topbar($acc['label'], $acc['email'],
        '<a class="btn" href="' . url('mail', ['acc' => $acc['id'], 'compose' => 1]) . '">' . icon('send') . ' Schreiben</a>');
    echo mail_tabs($accts, (int) $acc['id']);

    if (!imap_available()) {
        echo '<div class="alert err">Die PHP-Erweiterung <b>imap</b> ist nicht aktiv. Postfach-Lesen benötigt <code>extension=imap</code>. Senden per SMTP funktioniert dennoch.</div>';
        return;
    }

    if ($uid) {
        $mbox = imap_conn($acc, false);
        if (!$mbox) {
            echo '<div class="alert err">Verbindung fehlgeschlagen: ' . h(imap_err()) . '</div>';
            return;
        }
        $head = imap_headerinfo($mbox, imap_msgno($mbox, $uid));
        $from = imap_hdr($head->fromaddress ?? '');
        $body = imap_body_pref($mbox, $uid);
        echo '<div class="panel">';
        echo '<div style="display:flex;align-items:center;gap:12px;padding-bottom:12px;margin-bottom:12px;border-bottom:1px solid var(--line)">';
        echo '<div class="avatar" style="width:38px;height:38px">' . h(strtoupper(substr($from, 0, 1))) . '</div>';
        echo '<div style="flex:1"><strong>' . h($from) . '</strong><br><small class="mono" style="color:var(--muted)">'
            . h(isset($head->udate) ? date('d.m.Y H:i', $head->udate) : '') . '</small></div>';
        echo '<a class="btn ghost sm" href="' . url('mail', ['acc' => $acc['id']]) . '">' . icon('back') . '</a></div>';
        if ($body['text'] !== '') {
            echo '<div class="mail-body">' . linkify(h($body['text'])) . '</div>';
        } elseif ($body['html'] !== '') {
            // Isoliert + eigene CSP: keine Skripte, keine Remote-Requests
            $csp = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'; font-src data:">';
            $safe = $csp . '<base target="_blank">' . mail_sanitize($body['html']);
            echo '<iframe sandbox="" style="min-height:480px" srcdoc="' . h($safe) . '"></iframe>';
        } else {
            echo '<div class="empty">Kein darstellbarer Inhalt.</div>';
        }
        echo '</div>';
        @imap_setflag_full($mbox, (string) $uid, "\\Seen", ST_UID);
        imap_close($mbox);
        return;
    }

    $mbox = imap_conn($acc);
    if (!$mbox) {
        echo '<div class="alert err">Verbindung fehlgeschlagen: ' . h(imap_err()) . '</div>';
        return;
    }
    $ov = imap_recent($mbox, 40);
    if (!$ov) {
        echo '<div class="empty">' . icon('inbox', 40) . '<h3>Posteingang leer</h3></div>';
        imap_close($mbox);
        return;
    }
    echo '<div class="mail-list">';
    foreach ($ov as $o) {
        $seen = !empty($o->seen);
        echo '<a class="mail-item ' . ($seen ? '' : 'unseen') . '" href="' . url('mail', ['acc' => $acc['id'], 'uid' => $o->uid]) . '">';
        echo '<span style="width:9px;height:9px;border-radius:50%;flex:none;background:' . ($seen ? 'transparent' : 'var(--accent)') . '"></span>';
        echo '<span class="from">' . h(imap_hdr($o->from ?? '')) . '</span>';
        echo '<span class="subj">' . h(imap_hdr($o->subject ?? '(kein Betreff)')) . '</span>';
        echo '<span class="date">' . h(isset($o->udate) ? fmt_mail($o->udate) : '') . '</span></a>';
    }
    echo '</div>';
    imap_close($mbox);
}

function mail_setup_form(): void {
    layout_topbar('Mail-Konto verbinden', 'IMAP/SMTP');
    echo '<div class="panel" style="max-width:620px;margin:0 auto">';
    echo '<form method="post" action="?app=mail&action=acc_save">' . csrf_field();
    echo '<div class="field"><label>Bezeichnung</label><input class="input" name="label" required></div>';
    echo '<div class="field"><label>E-Mail-Adresse</label><input class="input" type="email" name="email" required></div>';
    echo '<div class="row"><div class="field" style="flex:2"><label>IMAP-Server</label><input class="input" name="imap_host" placeholder="imap.example.com" required></div>';
    echo '<div class="field"><label>Port</label><input class="input" name="imap_port" value="993"></div>';
    echo '<div class="field"><label>Verschl.</label><select name="imap_enc"><option value="ssl">SSL</option><option value="tls">TLS</option><option value="notls">Keine</option></select></div></div>';
    echo '<div class="row"><div class="field" style="flex:2"><label>SMTP-Server</label><input class="input" name="smtp_host" placeholder="smtp.example.com" required></div>';
    echo '<div class="field"><label>Port</label><input class="input" name="smtp_port" value="465"></div>';
    echo '<div class="field"><label>Verschl.</label><select name="smtp_enc"><option value="ssl">SSL</option><option value="tls">STARTTLS</option></select></div></div>';
    echo '<div class="field"><label>Benutzername</label><input class="input" name="username" required></div>';
    echo '<div class="field"><label>Passwort</label><input class="input" type="password" name="password" required></div>';
    echo '<label style="display:flex;gap:8px;align-items:center;font-size:13px;color:var(--muted);margin-bottom:14px"><input type="checkbox" name="validate_cert" value="1" style="width:auto"> TLS-Zertifikat streng prüfen</label>';
    echo '<button class="btn" type="submit" style="width:100%;justify-content:center">Konto speichern</button>';
    echo '</form></div>';
}

/* ================================================================== *
 *  APP: NOTIZEN
 * ================================================================== */
function handle_notes(array $u, string $action): void {
    if ($action === 'save') {
        csrf_check_post();
        $id    = param_int('id');
        $title = trim(param('title'));
        $body  = trim(param('body'));
        $color = param('color', '#b3893f');
        if ($body === '' && $title === '') {
            redirect(url('notes'));
        }
        $enc = enc_row(['title' => $title, 'body' => $body, 'color' => $color], (string) mk());
        if ($id) {
            db_run("UPDATE notes SET enc=?, updated_at=datetime('now') WHERE id=? AND user_id=?", [$enc, $id, $u['id']]);
        } else {
            db_run('INSERT INTO notes (user_id,enc) VALUES (?,?)', [$u['id'], $enc]);
        }
        flash('Notiz gespeichert.');
        redirect(url('notes'));
    }
    if ($action === 'del') {
        csrf_check_get();
        db_run('DELETE FROM notes WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
        flash('Notiz gelöscht.');
        redirect(url('notes'));
    }
    if ($action === 'pin') {
        csrf_check_get();
        db_run('UPDATE notes SET pinned=1-pinned WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
        redirect(url('notes'));
    }
    if ($action === 'share') {
        csrf_check_post();
        $row = db_one('SELECT * FROM notes WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
        $to = chat_peer_id($u, param_int('to'));
        if ($row && $to > 0) {
            $n = dec_row($row['enc'], mk());
            $text = trim(($n['title'] ?? '') !== '' ? "Notiz: {$n['title']}\n\n" . ($n['body'] ?? '') : (string) ($n['body'] ?? ''));
            $res = chat_send((int) $u['id'], mk(), $to, ['t' => 'text', 'body' => $text]);
            flash(is_int($res) ? 'Notiz im Chat geteilt.' : (string) $res, is_int($res) ? 'ok' : 'err');
        }
        redirect(url('notes'));
    }
}

function share_targets(array $u): array {
    return db_all("SELECT id,username,display_name FROM users WHERE id!=? AND status='active' ORDER BY username", [$u['id']]);
}

function render_notes(array $u): void {
    $rows = db_all('SELECT * FROM notes WHERE user_id=? ORDER BY pinned DESC, updated_at DESC', [$u['id']]);
    layout_topbar('Notizen', count($rows) . ' gespeichert',
        '<button class="btn" onclick="newNote()">' . icon('plus') . ' Neue Notiz</button>');

    $targets = share_targets($u);
    if ($rows) {
        echo '<div class="masonry">';
        foreach ($rows as $row) {
            $n = dec_row($row['enc'], mk());
            $color = $n['color'] ?? '#b3893f';
            echo '<div class="note" style="--nc:' . h($color) . '">';
            if ($row['pinned']) {
                echo '<div style="position:absolute;top:13px;right:13px;color:' . h($color) . '">' . icon('pin', 15) . '</div>';
            }
            if (($n['title'] ?? '') !== '') {
                echo '<h4>' . h($n['title']) . '</h4>';
            }
            echo '<div class="body">' . nl2br(h($n['body'] ?? '')) . '</div>';
            echo '<div class="meta">' . icon('clock', 13) . ' ' . h(date('d.m.Y H:i', strtotime((string) $row['updated_at'] . ' UTC') ?: time()));
            echo '<div class="acts">';
            echo '<a href="?app=notes&action=pin&id=' . $row['id'] . '&_csrf=' . csrf_token() . '" title="Anheften">' . icon('pin', 15) . '</a>';
            if ($targets) {
                echo '<a href="#" onclick="document.getElementById(\'noteShareForm\').id.value=' . $row['id'] . ';openModal(\'noteShareModal\');return false" title="Teilen">' . icon('share', 15) . '</a>';
            }
            echo '<a href="#" onclick="editNote(this);return false" data-id="' . $row['id'] . '" data-title="' . h($n['title'] ?? '') . '" data-body="' . h($n['body'] ?? '') . '" data-color="' . h($color) . '" title="Bearbeiten">' . icon('edit', 15) . '</a>';
            echo '<a href="?app=notes&action=del&id=' . $row['id'] . '&_csrf=' . csrf_token() . '" onclick="return confirm(\'Notiz löschen?\')" title="Löschen">' . icon('trash', 15) . '</a>';
            echo '</div></div></div>';
        }
        echo '</div>';
    } else {
        echo '<div class="empty">' . icon('note', 40) . '<h3>Noch keine Notizen</h3></div>';
    }

    echo '<div class="modal" id="noteModal"><div class="box">';
    echo '<span class="modal-x" onclick="closeModal(\'noteModal\')">&times;</span><h3 id="noteModalTitle">Neue Notiz</h3>';
    echo '<form method="post" action="?app=notes&action=save" id="noteForm">' . csrf_field();
    echo '<input type="hidden" name="id" value="">';
    echo '<div class="field"><label>Titel</label><input class="input" name="title"></div>';
    echo '<div class="field"><label>Inhalt</label><textarea name="body" required></textarea></div>';
    echo '<div class="field"><label>Farbe</label>' . color_swatch('color', '#b3893f') . '</div>';
    echo '<button class="btn" type="submit" style="width:100%;justify-content:center">Speichern</button>';
    echo '</form></div></div>';

    if ($targets) {
        echo '<div class="modal" id="noteShareModal"><div class="box">';
        echo '<span class="modal-x" onclick="closeModal(\'noteShareModal\')">&times;</span><h3>Notiz teilen</h3>';
        echo '<form method="post" action="?app=notes&action=share" id="noteShareForm">' . csrf_field();
        echo '<input type="hidden" name="id" value="">';
        echo '<div class="field"><label>An</label><select name="to">';
        foreach ($targets as $t) {
            echo '<option value="' . $t['id'] . '">' . h($t['display_name'] ?: $t['username']) . '</option>';
        }
        echo '</select></div>';
        echo '<button class="btn" type="submit" style="width:100%;justify-content:center">' . icon('share', 15) . ' Teilen</button>';
        echo '</form></div></div>';
    }
}

/* ================================================================== *
 *  APP: AUFGABEN
 * ================================================================== */
function handle_tasks(array $u, string $action): void {
    if ($action === 'add') {
        csrf_check_post();
        $title = trim(param('title'));
        if ($title !== '') {
            $enc = enc_row([
                'title'    => $title,
                'due'      => preg_match('/^\d{4}-\d{2}-\d{2}$/', param('due')) ? param('due') : '',
                'priority' => max(0, min(2, param_int('priority', 1))),
            ], (string) mk());
            db_run('INSERT INTO tasks (user_id,enc) VALUES (?,?)', [$u['id'], $enc]);
        }
        redirect(url('tasks'));
    }
    if ($action === 'toggle') {
        csrf_check_get();
        db_run('UPDATE tasks SET done=1-done WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
        redirect(url('tasks'));
    }
    if ($action === 'del') {
        csrf_check_get();
        db_run('DELETE FROM tasks WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
        redirect(url('tasks'));
    }
}

function render_tasks(array $u): void {
    $open = $done = [];
    foreach (db_all('SELECT * FROM tasks WHERE user_id=? ORDER BY id DESC', [$u['id']]) as $row) {
        $d = dec_row($row['enc'], mk());
        $d['id'] = (int) $row['id'];
        if ((int) $row['done'] === 1) {
            if (count($done) < 30) {
                $done[] = $d;
            }
        } else {
            $open[] = $d;
        }
    }
    usort($open, static function ($a, $b) {
        $p = (int) ($b['priority'] ?? 1) <=> (int) ($a['priority'] ?? 1);
        if ($p !== 0) {
            return $p;
        }
        $da = (string) ($a['due'] ?? '');
        $db2 = (string) ($b['due'] ?? '');
        if (($da === '') !== ($db2 === '')) {
            return $da === '' ? 1 : -1;
        }
        return strcmp($da, $db2) ?: ($a['id'] <=> $b['id']);
    });

    layout_topbar('Aufgaben', count($open) . ' offen');

    echo '<div class="panel" style="margin-bottom:16px"><form method="post" action="?app=tasks&action=add" class="row" style="align-items:flex-end">' . csrf_field();
    echo '<div class="field" style="flex:3;margin:0"><label>Neue Aufgabe</label><input class="input" name="title" required></div>';
    echo '<div class="field" style="flex:1;margin:0"><label>Fällig</label><input class="input" type="date" name="due"></div>';
    echo '<div class="field" style="flex:1;margin:0"><label>Priorität</label><select name="priority"><option value="1">Normal</option><option value="2">Hoch</option><option value="0">Niedrig</option></select></div>';
    echo '<button class="btn" style="flex:0 0 auto">' . icon('plus') . '</button>';
    echo '</form></div>';

    $prioColor = ['#7a828e', '#4d7ea8', '#c25a5a'];
    $today = date('Y-m-d');
    if ($open) {
        echo '<div class="tasklist">';
        foreach ($open as $t) {
            $due = (string) ($t['due'] ?? '');
            echo '<div class="task">';
            echo '<span class="prio" style="background:' . $prioColor[(int) ($t['priority'] ?? 1)] . '"></span>';
            echo '<a class="tcheck" href="?app=tasks&action=toggle&id=' . $t['id'] . '&_csrf=' . csrf_token() . '">' . icon('square', 18) . '</a>';
            echo '<span class="ttitle">' . h($t['title'] ?? '') . '</span>';
            if ($due !== '') {
                echo '<span class="tdue' . ($due < $today ? ' over' : '') . '">' . h(date('d.m.', strtotime($due))) . '</span>';
            }
            echo '<a class="tdel" href="?app=tasks&action=del&id=' . $t['id'] . '&_csrf=' . csrf_token() . '" onclick="return confirm(\'Aufgabe löschen?\')">' . icon('trash', 15) . '</a>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="empty">' . icon('check', 40) . '<h3>Keine offenen Aufgaben</h3></div>';
    }

    if ($done) {
        echo '<div class="section-h">' . icon('check') . ' Erledigt</div><div class="tasklist">';
        foreach ($done as $t) {
            echo '<div class="task done">';
            echo '<span class="prio" style="background:transparent"></span>';
            echo '<a class="tcheck" href="?app=tasks&action=toggle&id=' . $t['id'] . '&_csrf=' . csrf_token() . '">' . icon('checkbox', 18) . '</a>';
            echo '<span class="ttitle">' . h($t['title'] ?? '') . '</span>';
            echo '<a class="tdel" href="?app=tasks&action=del&id=' . $t['id'] . '&_csrf=' . csrf_token() . '">' . icon('trash', 15) . '</a>';
            echo '</div>';
        }
        echo '</div>';
    }
}

/* ================================================================== *
 *  APP: KALENDER
 * ================================================================== */
function handle_calendar(array $u, string $action): void {
    if ($action === 'save') {
        csrf_check_post();
        $id    = param_int('id');
        $title = trim(param('title'));
        $day   = param('day');
        if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            redirect(url('calendar'));
        }
        $enc = enc_row([
            'title' => $title,
            'time'  => param('time'),
            'end'   => param('end_time'),
            'desc'  => trim(param('description')),
            'color' => param('color', '#c25a5a'),
        ], (string) mk());
        if ($id) {
            db_run('UPDATE events SET day=?, enc=? WHERE id=? AND user_id=?', [$day, $enc, $id, $u['id']]);
        } else {
            db_run('INSERT INTO events (user_id,day,enc) VALUES (?,?,?)', [$u['id'], $day, $enc]);
        }
        flash('Termin gespeichert.');
        redirect(url('calendar', ['m' => substr($day, 0, 7)]));
    }
    if ($action === 'del') {
        csrf_check_post();
        db_run('DELETE FROM events WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
        flash('Termin gelöscht.');
        redirect(url('calendar'));
    }
}

function render_calendar(array $u): void {
    $ym = param('m', date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
        $ym = date('Y-m');
    }
    [$Y, $M] = array_map('intval', explode('-', $ym));
    $first = new DateTime(sprintf('%04d-%02d-01', $Y, $M));
    $daysIn = (int) $first->format('t');
    $startDow = ((int) $first->format('N')) - 1;
    $prev = (clone $first)->modify('-1 month')->format('Y-m');
    $next = (clone $first)->modify('+1 month')->format('Y-m');

    $byDay = [];
    foreach (db_all('SELECT * FROM events WHERE user_id=? AND day LIKE ?', [$u['id'], sprintf('%04d-%02d-%%', $Y, $M)]) as $row) {
        $e = dec_row($row['enc'], mk());
        if ($e) {
            $e['id'] = (int) $row['id'];
            $e['day'] = $row['day'];
            $byDay[$row['day']][] = $e;
        }
    }
    foreach ($byDay as &$list) {
        usort($list, static function ($a, $b) {
            return strcmp((string) ($a['time'] ?? ''), (string) ($b['time'] ?? ''));
        });
    }
    unset($list);

    $months = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    layout_topbar('Kalender', 'Termine & Ereignisse',
        '<button class="btn" onclick="openDay(\'' . date('Y-m-d') . '\')">' . icon('plus') . ' Termin</button>');

    echo '<div class="cal-head">';
    echo '<a class="iconbtn" href="' . url('calendar', ['m' => $prev]) . '">' . icon('chevL') . '</a>';
    echo '<h2>' . $months[$M] . ' ' . $Y . '</h2>';
    echo '<a class="iconbtn" href="' . url('calendar', ['m' => $next]) . '">' . icon('chevR') . '</a>';
    echo '<a class="btn ghost sm" href="' . url('calendar') . '">Heute</a></div>';

    echo '<div class="cal">';
    foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $d) {
        echo '<div class="dow">' . $d . '</div>';
    }
    $prevDays = (int) (clone $first)->modify('-1 month')->format('t');
    for ($i = $startDow; $i > 0; $i--) {
        echo '<div class="cell out"><span class="num">' . ($prevDays - $i + 1) . '</span></div>';
    }
    $today = date('Y-m-d');
    for ($d = 1; $d <= $daysIn; $d++) {
        $day = sprintf('%04d-%02d-%02d', $Y, $M, $d);
        echo '<div class="cell' . ($day === $today ? ' today' : '') . '" onclick="openDay(\'' . $day . '\')">';
        echo '<span class="num">' . $d . '</span>';
        foreach (($byDay[$day] ?? []) as $e) {
            echo '<div class="ev" style="--ec:' . h($e['color'] ?? '#c25a5a') . '" title="' . h($e['title'] ?? '') . '" onclick="editEvent(this)"'
                . ' data-id="' . $e['id'] . '" data-title="' . h($e['title'] ?? '') . '" data-day="' . h($e['day']) . '"'
                . ' data-time="' . h($e['time'] ?? '') . '" data-end="' . h($e['end'] ?? '') . '" data-desc="' . h($e['desc'] ?? '') . '" data-color="' . h($e['color'] ?? '#c25a5a') . '">';
            if (($e['time'] ?? '') !== '') {
                echo '<small>' . h(substr($e['time'], 0, 5)) . '</small>';
            }
            echo h($e['title'] ?? '') . '</div>';
        }
        echo '</div>';
    }
    $filled = $startDow + $daysIn;
    for ($i = 0; $i < (7 - $filled % 7) % 7; $i++) {
        echo '<div class="cell out"><span class="num">' . ($i + 1) . '</span></div>';
    }
    echo '</div>';

    echo '<div class="modal" id="evModal"><div class="box">';
    echo '<span class="modal-x" onclick="closeModal(\'evModal\')">&times;</span><h3 id="evModalTitle">Termin</h3>';
    echo '<form method="post" action="?app=calendar&action=save" id="evForm">' . csrf_field();
    echo '<input type="hidden" name="id" value="">';
    echo '<div class="field"><label>Titel</label><input class="input" name="title" required></div>';
    echo '<div class="field"><label>Datum</label><input class="input" type="date" name="day" required></div>';
    echo '<div class="row"><div class="field"><label>Von</label><input class="input" type="time" name="time"></div>';
    echo '<div class="field"><label>Bis</label><input class="input" type="time" name="end_time"></div></div>';
    echo '<div class="field"><label>Beschreibung</label><textarea name="description" style="min-height:70px"></textarea></div>';
    echo '<div class="field"><label>Farbe</label>' . color_swatch('color', '#c25a5a') . '</div>';
    echo '<div class="row"><button class="btn" type="submit" style="justify-content:center">Speichern</button>';
    echo '<button class="btn danger" type="submit" formaction="?app=calendar&action=del" style="flex:0 0 auto" onclick="return this.form.id.value?confirm(\'Termin löschen?\'):false">' . icon('trash') . '</button></div>';
    echo '</form></div></div>';
}

/* ================================================================== *
 *  APP: KONTAKTE
 * ================================================================== */
function handle_contacts(array $u, string $action): void {
    if ($action === 'save') {
        csrf_check_post();
        $id = param_int('id');
        $name = trim(param('name'));
        if ($name === '') {
            redirect(url('contacts'));
        }
        $enc = enc_row([
            'name'  => $name,
            'email' => trim(param('email')),
            'phone' => trim(param('phone')),
            'note'  => trim(param('note')),
        ], (string) mk());
        if ($id) {
            db_run('UPDATE contacts SET enc=? WHERE id=? AND user_id=?', [$enc, $id, $u['id']]);
        } else {
            db_run('INSERT INTO contacts (user_id,enc) VALUES (?,?)', [$u['id'], $enc]);
        }
        flash('Kontakt gespeichert.');
        redirect(url('contacts'));
    }
    if ($action === 'del') {
        csrf_check_get();
        db_run('DELETE FROM contacts WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
        flash('Kontakt gelöscht.');
        redirect(url('contacts'));
    }
}

function render_contacts(array $u): void {
    $rows = [];
    foreach (db_all('SELECT * FROM contacts WHERE user_id=?', [$u['id']]) as $row) {
        $c = dec_row($row['enc'], mk());
        if ($c) {
            $c['id'] = (int) $row['id'];
            $rows[] = $c;
        }
    }
    usort($rows, static function ($a, $b) {
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    layout_topbar('Kontakte', count($rows) . ' Einträge',
        '<button class="btn" onclick="newContact()">' . icon('plus') . ' Kontakt</button>');

    if ($rows) {
        echo '<div class="wrap-scroll"><table class="table"><thead><tr><th>Name</th><th>E-Mail</th><th>Telefon</th><th>Notiz</th><th></th></tr></thead><tbody>';
        foreach ($rows as $c) {
            echo '<tr><td><strong>' . h($c['name'] ?? '') . '</strong></td>';
            echo '<td>' . (($c['email'] ?? '') !== '' ? h($c['email']) : '<span style="color:var(--muted2)">—</span>') . '</td>';
            echo '<td class="mono">' . (($c['phone'] ?? '') !== '' ? h($c['phone']) : '—') . '</td>';
            echo '<td style="color:var(--muted)">' . h($c['note'] ?? '') . '</td>';
            echo '<td><div class="actions">';
            echo '<a class="btn ghost sm" href="#" onclick="editContact(this);return false" data-id="' . $c['id'] . '" data-name="' . h($c['name'] ?? '') . '" data-email="' . h($c['email'] ?? '') . '" data-phone="' . h($c['phone'] ?? '') . '" data-note="' . h($c['note'] ?? '') . '">' . icon('edit', 14) . '</a>';
            echo '<a class="btn ghost sm" href="?app=contacts&action=del&id=' . $c['id'] . '&_csrf=' . csrf_token() . '" onclick="return confirm(\'Kontakt löschen?\')">' . icon('trash', 14) . '</a>';
            echo '</div></td></tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div class="empty">' . icon('user', 40) . '<h3>Keine Kontakte</h3></div>';
    }

    echo '<div class="modal" id="contactModal"><div class="box">';
    echo '<span class="modal-x" onclick="closeModal(\'contactModal\')">&times;</span><h3 id="contactModalTitle">Neuer Kontakt</h3>';
    echo '<form method="post" action="?app=contacts&action=save" id="contactForm">' . csrf_field();
    echo '<input type="hidden" name="id" value="">';
    echo '<div class="field"><label>Name</label><input class="input" name="name" required></div>';
    echo '<div class="field"><label>E-Mail</label><input class="input" type="email" name="email"></div>';
    echo '<div class="field"><label>Telefon</label><input class="input" name="phone"></div>';
    echo '<div class="field"><label>Notiz</label><textarea name="note" style="min-height:70px"></textarea></div>';
    echo '<button class="btn" type="submit" style="width:100%;justify-content:center">Speichern</button>';
    echo '</form></div></div>';
}

/* ================================================================== *
 *  APP: DATEIEN (verschlüsseltes virtuelles Dateisystem)
 * ================================================================== */
function handle_files(array $u, string $action): void {
    $dirId = param_int('d');

    if ($action === 'upload') {
        csrf_check_post();
        $dir = vfs_dir($u, $dirId);
        if ($dir && !empty($_FILES['files'])) {
            $n = 0;
            $err = null;
            foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
                if (!is_uploaded_file($tmp)) {
                    continue;
                }
                $name = basename((string) $_FILES['files']['name'][$i]);
                if ($name === '') {
                    $name = 'datei_' . $i;
                }
                $data = (string) file_get_contents($tmp);
                $err = vfs_store($u, $dirId, $name, $data);
                if ($err !== null) {
                    break;
                }
                $n++;
            }
            flash($err !== null ? $err : $n . ' Datei(en) hochgeladen.', $err !== null ? 'err' : 'ok');
        }
        redirect(url('files', ['d' => $dirId]));
    }

    if ($action === 'mkdir') {
        csrf_check_post();
        $name = trim(param('name'));
        $dir = vfs_dir($u, $dirId);
        if ($name !== '' && $dir) {
            db_run('INSERT INTO vfs (user_id,parent_id,is_dir,enc_name) VALUES (?,?,1,?)',
                [$u['id'], $dirId, aead_enc($name, (string) mk())]);
            flash('Ordner erstellt.');
        }
        redirect(url('files', ['d' => $dirId]));
    }

    if ($action === 'rm') {
        csrf_check_get();
        $node = vfs_node($u, param_int('id'));
        $parent = $node ? (int) $node['parent_id'] : 0;
        if ($node) {
            vfs_delete_rec($u, (int) $node['id']);
            flash('Gelöscht.');
        }
        redirect(url('files', ['d' => $parent]));
    }

    if ($action === 'dl') {
        $node = vfs_node($u, param_int('id'));
        if ($node && (int) $node['is_dir'] === 0) {
            $data = vfs_content($node, $u);
            if ($data !== null) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . chat_dl_name(vfs_name($node, $u)) . '"');
                header('Content-Length: ' . strlen($data));
                echo $data;
                exit;
            }
        }
        http_response_code(404);
        exit('Nicht gefunden.');
    }

    if ($action === 'share') {
        csrf_check_post();
        $node = vfs_node($u, param_int('id'));
        $to = chat_peer_id($u, param_int('to'));
        if ($node && (int) $node['is_dir'] === 0 && $to > 0) {
            $data = vfs_content($node, $u);
            if ($data !== null) {
                $res = chat_send_file($u, $to, vfs_name($node, $u), $data);
                flash(is_int($res) ? 'Datei im Chat geteilt.' : (string) $res, is_int($res) ? 'ok' : 'err');
            } else {
                flash('Datei nicht lesbar.', 'err');
            }
        }
        redirect(url('files', ['d' => $node ? (int) $node['parent_id'] : 0]));
    }
}

function render_files(array $u): void {
    $dirId = param_int('d');
    if (!vfs_dir($u, $dirId)) {
        $dirId = 0;
    }

    layout_topbar('Dateien', 'Frei: ' . human_size(quota_remaining((int) $u['id'])),
        '<form method="post" action="?app=files&action=mkdir" style="display:flex;gap:8px">' . csrf_field()
        . '<input type="hidden" name="d" value="' . $dirId . '">'
        . '<input class="input" name="name" placeholder="Neuer Ordner" style="width:150px;padding:7px 11px">'
        . '<button class="btn ghost sm">' . icon('plus') . '</button></form>');

    // Breadcrumb
    $crumbs = [];
    $cur = $dirId;
    $guard = 0;
    while ($cur > 0 && $guard++ < 30) {
        $n = vfs_node($u, $cur);
        if (!$n) {
            break;
        }
        $crumbs[] = ['id' => $cur, 'name' => vfs_name($n, $u)];
        $cur = (int) $n['parent_id'];
    }
    $crumbs = array_reverse($crumbs);
    echo '<div class="crumb"><a href="' . url('files') . '">' . icon('folder', 15) . ' home</a>';
    foreach ($crumbs as $c) {
        echo ' / <a href="' . url('files', ['d' => $c['id']]) . '">' . h($c['name']) . '</a>';
    }
    echo '</div>';

    echo '<form method="post" action="?app=files&action=upload" enctype="multipart/form-data" id="uploadForm">' . csrf_field();
    echo '<input type="hidden" name="d" value="' . $dirId . '">';
    echo '<input type="file" name="files[]" id="fileInput" multiple hidden>';
    echo '<div class="dropzone" id="dropzone">' . icon('upload', 28) . '<div style="margin-top:6px">Dateien hierher ziehen oder klicken</div></div></form>';

    $dirs = $files = [];
    foreach (db_all('SELECT * FROM vfs WHERE user_id=? AND parent_id=?', [$u['id'], $dirId]) as $row) {
        $row['_name'] = vfs_name($row, $u);
        if ((int) $row['is_dir'] === 1) {
            $dirs[] = $row;
        } else {
            $files[] = $row;
        }
    }
    $byName = static function ($a, $b) {
        return strcasecmp($a['_name'], $b['_name']);
    };
    usort($dirs, $byName);
    usort($files, $byName);

    if (!$dirs && !$files) {
        echo '<div class="empty">' . icon('folder', 40) . '<h3>Leerer Ordner</h3></div>';
        return;
    }

    $targets = share_targets($u);
    echo '<div class="file-grid">';
    foreach ($dirs as $d2) {
        echo '<div class="file-card">';
        echo '<div class="facts"><a class="del" href="?app=files&action=rm&id=' . $d2['id'] . '&_csrf=' . csrf_token() . '" onclick="return confirm(\'Ordner inkl. Inhalt löschen?\')">' . icon('trash', 14) . '</a></div>';
        echo '<a href="' . url('files', ['d' => $d2['id']]) . '">';
        echo '<div class="fi">' . icon('folder', 30) . '</div><div class="fn">' . h($d2['_name']) . '</div><div class="fs">Ordner</div></a></div>';
    }
    foreach ($files as $f) {
        echo '<div class="file-card">';
        echo '<div class="facts">';
        if ($targets) {
            echo '<a href="#" title="Teilen" onclick="shareModal(' . $f['id'] . ',' . h(json_encode($f['_name'])) . ');return false">' . icon('share', 14) . '</a>';
        }
        echo '<a class="del" href="?app=files&action=rm&id=' . $f['id'] . '&_csrf=' . csrf_token() . '" onclick="return confirm(\'Datei löschen?\')">' . icon('trash', 14) . '</a></div>';
        echo '<a href="?app=files&action=dl&id=' . $f['id'] . '">';
        echo '<div class="fi">' . icon('file', 30) . '</div><div class="fn">' . h($f['_name']) . '</div><div class="fs">' . human_size((int) $f['size']) . '</div></a>';
        if (!empty($f['sealed'])) {
            echo '<div style="margin-top:5px"><span class="chip">geteilt</span></div>';
        }
        echo '</div>';
    }
    echo '</div>';

    if ($targets) {
        echo '<div class="modal" id="shareModal"><div class="box">';
        echo '<span class="modal-x" onclick="closeModal(\'shareModal\')">&times;</span><h3>Teilen: <span id="shareName" class="mono"></span></h3>';
        echo '<form method="post" action="?app=files&action=share" id="shareForm">' . csrf_field();
        echo '<input type="hidden" name="id" value="">';
        echo '<div class="field"><label>An</label><select name="to">';
        foreach ($targets as $t) {
            echo '<option value="' . $t['id'] . '">' . h($t['display_name'] ?: $t['username']) . '</option>';
        }
        echo '</select></div>';
        echo '<button class="btn" type="submit" style="width:100%;justify-content:center">' . icon('share', 15) . ' Teilen</button>';
        echo '</form></div></div>';
    }
}

/* ================================================================== *
 *  APP: LESEZEICHEN
 * ================================================================== */
function handle_bookmarks(array $u, string $action): void {
    if ($action === 'save') {
        csrf_check_post();
        $title = trim(param('title'));
        $urlv  = trim(param('url'));
        if (!preg_match('#^https?://#i', $urlv)) {
            $urlv = 'https://' . $urlv;
        }
        if ($title !== '' && filter_var($urlv, FILTER_VALIDATE_URL)) {
            db_run('INSERT INTO bookmarks (user_id,enc) VALUES (?,?)',
                [$u['id'], enc_row(['title' => $title, 'url' => $urlv, 'color' => param('color', '#4d7ea8')], (string) mk())]);
            flash('Lesezeichen hinzugefügt.');
        }
        redirect(url('bookmarks'));
    }
    if ($action === 'del') {
        csrf_check_get();
        db_run('DELETE FROM bookmarks WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
        flash('Lesezeichen gelöscht.');
        redirect(url('bookmarks'));
    }
}

function render_bookmarks(array $u): void {
    $rows = db_all('SELECT * FROM bookmarks WHERE user_id=? ORDER BY position,id', [$u['id']]);
    layout_topbar('Lesezeichen', count($rows) . ' Links · erscheinen auf der Startseite',
        '<button class="btn" onclick="openModal(\'bmModal\')">' . icon('plus') . ' Link hinzufügen</button>');

    if ($rows) {
        echo '<div class="grid tiles">';
        foreach ($rows as $row) {
            $b = dec_row($row['enc'], mk());
            if (!$b) {
                continue;
            }
            echo '<div class="tile" style="--tc:' . h($b['color'] ?? '#4d7ea8') . ';min-height:auto">';
            echo '<a href="' . h($b['url'] ?? '#') . '" target="_blank" rel="noopener" style="display:flex;gap:11px;align-items:center">';
            echo '<div class="tico" style="width:32px;height:32px">' . icon('link', 16) . '</div>';
            echo '<div style="min-width:0"><h3 style="font-size:14px">' . h($b['title'] ?? '') . '</h3>';
            echo '<p style="font-size:11.5px;word-break:break-all;margin:0">' . h(preg_replace('#^https?://#', '', (string) ($b['url'] ?? ''))) . '</p></div></a>';
            echo '<div style="display:flex;gap:6px;margin-top:auto"><a class="btn ghost sm" href="?app=bookmarks&action=del&id=' . $row['id'] . '&_csrf=' . csrf_token() . '" onclick="return confirm(\'Löschen?\')">' . icon('trash', 14) . '</a></div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="empty">' . icon('link', 40) . '<h3>Keine Lesezeichen</h3></div>';
    }

    echo '<div class="modal" id="bmModal"><div class="box">';
    echo '<span class="modal-x" onclick="closeModal(\'bmModal\')">&times;</span><h3>Lesezeichen hinzufügen</h3>';
    echo '<form method="post" action="?app=bookmarks&action=save">' . csrf_field();
    echo '<div class="field"><label>Titel</label><input class="input" name="title" required></div>';
    echo '<div class="field"><label>URL</label><input class="input" type="text" name="url" placeholder="https://…" required></div>';
    echo '<div class="field"><label>Farbe</label>' . color_swatch('color', '#4d7ea8') . '</div>';
    echo '<button class="btn" type="submit" style="width:100%;justify-content:center">Hinzufügen</button>';
    echo '</form></div></div>';
}

/* ================================================================== *
 *  APP: VERWALTUNG
 * ================================================================== */
function admin_btn(string $action, int $uid, string $inner, string $confirm = ''): string {
    $onsubmit = $confirm !== '' ? ' onsubmit="return confirm(\'' . h($confirm) . '\')"' : '';
    return '<form method="post" action="?app=admin&action=' . $action . '" style="display:inline"' . $onsubmit . '>'
        . csrf_field() . '<input type="hidden" name="uid" value="' . $uid . '">'
        . '<button type="submit" style="background:none;border:0;padding:0;cursor:pointer">' . $inner . '</button></form>';
}

function admin_status_label(string $s): string {
    return ['pending' => 'Wartet', 'active' => 'Aktiv', 'suspended' => 'Gesperrt'][$s] ?? $s;
}

function handle_admin(array $u, string $action): void {
    csrf_check_post();
    $uid = param_int('uid');
    if ($uid === (int) $u['id'] && in_array($action, ['suspend', 'demote'], true)) {
        flash('Diese Aktion auf das eigene Konto ist nicht möglich.', 'err');
        redirect(url('admin'));
    }
    switch ($action) {
        case 'approve':   admin_approve($uid, $u); flash('Konto freigeschaltet.'); break;
        case 'reject':    admin_reject($uid, $u); flash('Registrierung abgelehnt.'); break;
        case 'suspend':   admin_suspend($uid); flash('Konto gesperrt.'); break;
        case 'unsuspend': admin_unsuspend($uid); flash('Sperre aufgehoben.'); break;
        case 'promote':   admin_promote($uid); flash('Nutzer ist jetzt Administrator.'); break;
        case 'demote':    admin_demote($uid); flash('Administratorrechte entzogen.'); break;
        case 'quota':
            $gb = (float) str_replace(',', '.', param('gb', '1'));
            admin_set_quota($uid, (int) round($gb * 1024 * 1024 * 1024));
            flash('Quota angepasst.');
            break;
    }
    redirect(url('admin'));
}

function render_admin(array $u): void {
    $pending = db_all("SELECT * FROM users WHERE status='pending' ORDER BY id");
    layout_topbar('Verwaltung', 'Nutzer, Freischaltung & Quota');

    if ($pending) {
        echo '<div class="section-h">' . icon('ticket') . ' Offene Freischaltungen (' . count($pending) . ')</div>';
        echo '<div class="wrap-scroll"><table class="table"><thead><tr><th>Benutzer</th><th>E-Mail</th><th>Ticket</th><th>Registriert</th><th></th></tr></thead><tbody>';
        foreach ($pending as $p) {
            $t = db_one('SELECT code FROM tickets WHERE user_id=? ORDER BY id DESC LIMIT 1', [$p['id']]);
            echo '<tr><td><strong>' . h($p['username']) . '</strong></td><td>' . h($p['email']) . '</td>';
            echo '<td class="mono">' . h($t['code'] ?? '—') . '</td>';
            echo '<td class="mono">' . h(date('d.m.Y', strtotime((string) $p['created_at']))) . '</td>';
            echo '<td><div class="actions">';
            echo '<a class="btn ghost sm" href="' . url('chat', ['peer' => $p['id']]) . '">' . icon('chat', 14) . '</a>';
            echo admin_btn('approve', (int) $p['id'], '<span class="btn ok sm">' . icon('check', 14) . ' Freischalten</span>');
            echo admin_btn('reject', (int) $p['id'], '<span class="btn ghost sm">Ablehnen</span>', 'Registrierung ablehnen und Konto sperren?');
            echo '</div></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    $users = db_all('SELECT * FROM users ORDER BY role DESC, id');
    echo '<div class="section-h">' . icon('users') . ' Alle Nutzer (' . count($users) . ')</div>';
    echo '<div class="wrap-scroll"><table class="table"><thead><tr><th>Benutzer</th><th>Rolle</th><th>Status</th><th>Speicher</th><th>Aktionen</th></tr></thead><tbody>';
    foreach ($users as $r) {
        $used = quota_used((int) $r['id']);
        $max = (int) $r['quota_bytes'];
        $pct = $max > 0 ? min(100, (int) round($used / $max * 100)) : 0;
        $self = (int) $r['id'] === (int) $u['id'];
        echo '<tr>';
        echo '<td><strong>' . h($r['username']) . '</strong>' . ($self ? ' <span class="chip">du</span>' : '') . '<br><small style="color:var(--muted)">' . h($r['email']) . '</small></td>';
        echo '<td><span class="badge ' . ($r['role'] === 'admin' ? 'admin' : '') . '">' . ($r['role'] === 'admin' ? 'Admin' : 'Nutzer') . '</span></td>';
        echo '<td><span class="badge ' . h($r['status']) . '">' . admin_status_label($r['status']) . '</span></td>';
        echo '<td><div class="mono" style="font-size:11.5px;margin-bottom:3px">' . human_size($used) . ' / ' . human_size($max) . '</div>'
            . '<div class="quota-bar" style="width:120px"><i style="width:' . $pct . '%;background:' . ($pct >= 90 ? 'var(--err)' : 'var(--accent)') . '"></i></div></td>';
        echo '<td><div class="actions">';
        if ($r['status'] === 'pending') {
            echo admin_btn('approve', (int) $r['id'], '<span class="btn ok sm">Freischalten</span>');
        }
        if ($r['status'] === 'suspended') {
            echo admin_btn('unsuspend', (int) $r['id'], '<span class="btn ghost sm">Entsperren</span>');
        } elseif (!$self && $r['role'] !== 'admin') {
            echo admin_btn('suspend', (int) $r['id'], '<span class="btn ghost sm">' . icon('ban', 14) . '</span>', 'Konto sperren?');
        }
        echo '<button class="btn ghost sm" onclick="quotaModal(' . $r['id'] . ',' . h(json_encode($r['username'])) . ',' . round($max / 1073741824, 2) . ')">' . icon('db', 14) . '</button>';
        if ($r['role'] === 'admin') {
            if (!$self) {
                echo admin_btn('demote', (int) $r['id'], '<span class="btn ghost sm">Admin entziehen</span>', 'Administratorrechte entziehen?');
            }
        } else {
            echo admin_btn('promote', (int) $r['id'], '<span class="btn ghost sm">' . icon('shield', 14) . '</span>', 'Zum Administrator machen?');
        }
        echo '</div></td></tr>';
    }
    echo '</tbody></table></div>';

    echo '<div class="modal" id="quotaModal"><div class="box">';
    echo '<span class="modal-x" onclick="closeModal(\'quotaModal\')">&times;</span><h3>Quota für <span id="quotaUser" class="mono"></span></h3>';
    echo '<form method="post" action="?app=admin&action=quota" id="quotaForm">' . csrf_field();
    echo '<input type="hidden" name="uid" value="">';
    echo '<div class="field"><label>Speicher in GB</label><input class="input" name="gb" type="number" step="0.5" min="0" required></div>';
    echo '<button class="btn" style="width:100%;justify-content:center">Speichern</button>';
    echo '</form></div></div>';
}

/* ================================================================== *
 *  APP: EINSTELLUNGEN
 * ================================================================== */
function handle_settings(array $u, string $action): void {
    csrf_check_post();
    switch ($action) {
        case 'profile':
            db_run('UPDATE users SET display_name=?, email=? WHERE id=?',
                [trim(param('display_name')) ?: $u['username'], trim(param('email')), $u['id']]);
            flash('Profil aktualisiert.');
            break;
        case 'appearance':
            $theme = param('theme') === 'light' ? 'light' : 'dark';
            $accent = preg_match('/^#[0-9a-f]{6}$/i', param('accent')) ? param('accent') : '#4d7ea8';
            db_run('UPDATE users SET theme=?, accent=? WHERE id=?', [$theme, $accent, $u['id']]);
            flash('Aussehen übernommen.');
            break;
        case 'password':
            if (strlen(param('new')) < 8 || param('new') !== param('new2')) {
                flash('Neues Passwort ungültig (min. 8 Zeichen) oder stimmt nicht überein.', 'err');
                break;
            }
            $err = auth_rewrap($u, param('current'), param('new'));
            flash($err === null ? 'Passwort geändert.' : $err, $err === null ? 'ok' : 'err');
            break;
        case 'acc_del':
            db_run('DELETE FROM mail_accounts WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
            flash('Mail-Konto entfernt.');
            break;
    }
    redirect(url('settings'));
}

function render_settings(array $u): void {
    layout_topbar('Einstellungen', 'Profil, Konten & Aussehen');

    echo '<div class="section-h">' . icon('user') . ' Profil</div><div class="panel" style="max-width:620px">';
    echo '<form method="post" action="?app=settings&action=profile">' . csrf_field();
    echo '<div class="field"><label>Anzeigename</label><input class="input" name="display_name" value="' . h($u['display_name']) . '"></div>';
    echo '<div class="field"><label>E-Mail</label><input class="input" type="email" name="email" value="' . h($u['email']) . '"></div>';
    echo '<div class="field"><label>Benutzername</label><input class="input" value="' . h($u['username']) . '" disabled></div>';
    echo '<button class="btn">Speichern</button></form></div>';

    echo '<div class="section-h">' . icon('sun') . ' Aussehen</div><div class="panel" style="max-width:620px">';
    echo '<form method="post" action="?app=settings&action=appearance">' . csrf_field();
    echo '<div class="field"><label>Theme</label><select name="theme">';
    foreach (['dark' => 'Dunkel', 'light' => 'Hell'] as $k => $v) {
        echo '<option value="' . $k . '" ' . ($u['theme'] === $k ? 'selected' : '') . '>' . $v . '</option>';
    }
    echo '</select></div>';
    echo '<div class="field"><label>Akzentfarbe</label>' . color_swatch('accent', (string) $u['accent']) . '</div>';
    echo '<button class="btn">Übernehmen</button></form></div>';

    echo '<div class="section-h">' . icon('key') . ' Passwort ändern</div><div class="panel" style="max-width:620px">';
    echo '<form method="post" action="?app=settings&action=password">' . csrf_field();
    echo '<div class="field"><label>Aktuelles Passwort</label><input class="input" type="password" name="current" required></div>';
    echo '<div class="row"><div class="field"><label>Neues Passwort</label><input class="input" type="password" name="new" required></div>';
    echo '<div class="field"><label>Wiederholen</label><input class="input" type="password" name="new2" required></div></div>';
    echo '<p class="note-line">Ein verlorenes Passwort kann nicht zurückgesetzt werden; die Inhalte des Kontos wären dann nicht wiederherstellbar.</p>';
    echo '<button class="btn">Passwort ändern</button></form></div>';

    $accts = mail_accounts($u);
    echo '<div class="section-h">' . icon('mail') . ' Externe Mail-Konten</div><div class="panel" style="max-width:620px">';
    if ($accts) {
        foreach ($accts as $a) {
            echo '<div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--line)">';
            echo '<div class="tico" style="width:34px;height:34px">' . icon('mail') . '</div>';
            echo '<div style="flex:1"><strong>' . h($a['label']) . '</strong><br><small style="color:var(--muted)">' . h($a['email']) . ' · ' . h($a['imap_host']) . '</small></div>';
            echo '<form method="post" action="?app=settings&action=acc_del" onsubmit="return confirm(\'Konto entfernen?\')">' . csrf_field()
                . '<input type="hidden" name="id" value="' . $a['id'] . '"><button class="btn ghost sm danger">' . icon('trash', 14) . '</button></form>';
            echo '</div>';
        }
    } else {
        echo '<p style="color:var(--muted);margin-bottom:12px">Kein externes Mail-Konto verbunden.</p>';
    }
    echo '<a class="btn ghost" href="' . url('mail', ['new' => 1]) . '" style="margin-top:12px">' . icon('plus') . ' Konto hinzufügen</a></div>';

    echo '<div class="section-h">' . icon('db') . ' System</div><div class="panel" style="max-width:620px">';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px">';
    $crypto = function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt') ? 'sodium' : 'openssl';
    foreach ([
        'Version' => NX_NAME . ' ' . NX_VERSION,
        'PHP' => PHP_VERSION,
        'Krypto' => $crypto,
        'IMAP-Erweiterung' => imap_available() ? 'aktiv' : 'nicht verfügbar',
        'Datenbank' => 'SQLite',
        'Kompression' => function_exists('gzdeflate') ? 'aktiv' : 'inaktiv',
    ] as $k => $v) {
        echo '<div><span style="color:var(--muted)">' . h($k) . '</span><br>' . h((string) $v) . '</div>';
    }
    echo '</div></div>';
}

/* ================================================================== *
 *  FRONT CONTROLLER / ROUTER
 * ================================================================== */

// Assets zuerst (ohne Session/DB)
if (isset($_GET['asset'])) {
    serve_asset($_GET['asset'] === 'js' ? 'js' : 'css');
}

if ($nxMissing = nx_requirements()) {
    nx_setup_page($nxMissing);
    exit;
}

nx_bootstrap();

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => nx_https()]);
session_start();

$nxAction = param('action');

// Auth-freie Aktionen
if ($nxAction === 'logout') {
    auth_logout();
    redirect('?view=login');
}
if ($nxAction === 'save_theme') {
    if ($nxU = current_user()) {
        $t = param('theme') === 'light' ? 'light' : 'dark';
        db_run('UPDATE users SET theme=? WHERE id=?', [$t, $nxU['id']]);
    }
    header('Content-Type: application/json');
    echo '{"ok":true}';
    exit;
}

$nxUser = current_user();

// Nicht eingeloggt -> Login/Registrierung
if (!$nxUser) {
    if ($nxAction === 'register') {
        csrf_check_post();
        $r = auth_register(param('username'), param('email'), param('password'), param('password2'));
        if (!empty($r['ok'])) {
            redirect(url('home'));
        }
        view_auth('register', $r['err']);
        exit;
    }
    if ($nxAction === 'login') {
        csrf_check_post();
        $r = auth_login(param('username'), param('password'));
        if (!empty($r['ok'])) {
            redirect(url('home'));
        }
        view_auth('login', $r['err']);
        exit;
    }
    $nxMode = (param('view') === 'register' || user_count() === 0) ? 'register' : 'login';
    view_auth($nxMode);
    exit;
}

// Gesperrt -> alles blockiert außer Abmelden
if ($nxUser['status'] === 'suspended') {
    view_locked();
    exit;
}

// Ohne entsperrten Konto-Schlüssel ist nichts lesbar -> neu anmelden
if (mk() === null) {
    auth_logout();
    redirect('?view=login');
}

$nxApp = param('app', 'home');
if (!array_key_exists($nxApp, nx_apps())) {
    $nxApp = 'home';
}

if (!can_access($nxUser, $nxApp)) {
    flash('Diese Funktion ist erst nach der Freischaltung verfügbar.', 'err');
    redirect(url('home'));
}

// Aktionen (führen i. d. R. zu Redirect oder JSON-Ausgabe)
if ($nxAction !== '') {
    switch ($nxApp) {
        case 'chat':      handle_chat($nxUser, $nxAction);      break;
        case 'mail':      handle_mail($nxUser, $nxAction);      break;
        case 'notes':     handle_notes($nxUser, $nxAction);     break;
        case 'tasks':     handle_tasks($nxUser, $nxAction);     break;
        case 'calendar':  handle_calendar($nxUser, $nxAction);  break;
        case 'contacts':  handle_contacts($nxUser, $nxAction);  break;
        case 'files':     handle_files($nxUser, $nxAction);     break;
        case 'bookmarks': handle_bookmarks($nxUser, $nxAction); break;
        case 'admin':     handle_admin($nxUser, $nxAction);     break;
        case 'settings':  handle_settings($nxUser, $nxAction);  break;
    }
}

// Seite rendern
layout_head($nxUser, $nxApp);
switch ($nxApp) {
    case 'chat':      render_chat($nxUser);      break;
    case 'mail':      render_mail($nxUser);      break;
    case 'notes':     render_notes($nxUser);     break;
    case 'tasks':     render_tasks($nxUser);     break;
    case 'calendar':  render_calendar($nxUser);  break;
    case 'contacts':  render_contacts($nxUser);  break;
    case 'files':     render_files($nxUser);     break;
    case 'bookmarks': render_bookmarks($nxUser); break;
    case 'admin':     render_admin($nxUser);     break;
    case 'settings':  render_settings($nxUser);  break;
    default:          render_home($nxUser);      break;
}
layout_foot();
