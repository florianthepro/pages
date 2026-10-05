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
const NX_VERSION = '8.8.0';

/** Videoanrufe laufen vollständig über diesen Webserver (Chunk-Relay,
 *  keine externen STUN/TURN-Dienste, keine Zusatzmodule nötig). */
const NX_CALL_TTL = 120; // Sekunden, danach werden Anruf-Daten aufgeräumt

/* ------------------------------------------------------------------ *
 *  Loader-Integration: beschreibbare Ablage kommt AUSSCHLIESSLICH aus
 *  der Instanz ($nexus_datadir, absolut + gesperrt). Unter dem Remote-
 *  Loader ist __DIR__ das ephemere Cache-Verzeichnis; nichts Schreibbares
 *  darf dort landen. Alle NX_*-Pfade werden daher an $nexus_datadir
 *  verankert. Fallback (__DIR__) nur fuer den Direktbetrieb ohne Loader.
 * ------------------------------------------------------------------ */
$__nxData = (isset($nexus_datadir) && is_string($nexus_datadir) && $nexus_datadir !== '')
    ? rtrim($nexus_datadir, "/\\")
    : __DIR__ . '/data';
define('NX_ROOT',     $__nxData);
define('NX_DATA',     $__nxData);
define('NX_SYS',      NX_DATA . '/sys');
define('NX_DB',       NX_SYS  . '/app.sqlite');
define('NX_SESSIONS', NX_SYS  . '/sessions');
define('NX_FILES',    NX_DATA . '/files');
define('NX_CHATBLOB', NX_DATA . '/chat');

/* Instanz-Theme: 'light' (fix hell) | 'dark' (fix dunkel) | 'auto' (folgt OS). */
define('NX_THEME_MODE', (isset($nexus_theme) && in_array($nexus_theme, ['light', 'dark', 'auto'], true)) ? $nexus_theme : 'auto');

define('NX_QUOTA_PENDING', 512 * 1024 * 1024);   // 0,5 GB vor Freischaltung
define('NX_QUOTA_ACTIVE',  1024 * 1024 * 1024);  // 1 GB nach Freischaltung

/* ------------------------------------------------------------------ *
 *  App-Registry (fest, da Single-File)
 * ------------------------------------------------------------------ */
/* ------------------------------------------------------------------ *
 *  Mehrsprachigkeit (Default: Englisch). Verfuegbar: en, de, th.
 * ------------------------------------------------------------------ */
function nx_langs(): array {
    return ['en' => 'English', 'de' => 'Deutsch', 'th' => 'ไทย'];
}

function nx_i18n(): array {
    return [
        'en' => [
            'app_home' => 'Home',
            'app_chat' => 'Chat',
            'app_mail' => 'Mail',
            'app_notes' => 'Notes',
            'app_tasks' => 'Tasks',
            'app_calendar' => 'Calendar',
            'app_contacts' => 'Contacts',
            'app_files' => 'Files',
            'app_docs' => 'Docs',
            'app_passwords' => 'Passwords',
            'app_settings' => 'Settings',
            'app_admin' => 'Administration',
            'nav_add' => 'Add',
            'nav_apps' => 'Apps',
            'auth_login' => 'Sign in',
            'auth_register' => 'Create account',
            'auth_username' => 'Username',
            'auth_password' => 'Password',
            'auth_password2' => 'Confirm password',
            'auth_do_login' => 'Sign in',
            'auth_do_register' => 'Create account',
            'auth_have' => 'Already registered?',
            'auth_no' => 'No account yet?',
            'auth_note' => 'All content is encrypted with your password. It cannot be reset.',
            'auth_first' => 'The first account becomes administrator.',
            'auth_approve' => 'An administrator will activate your account.',
            'auth_language' => 'Language',
            'set_title' => 'Settings',
            'set_account' => 'Account',
            'set_appearance' => 'Appearance',
            'set_connections' => 'Connections',
            'set_system' => 'System',
            'set_admin' => 'Administration',
            'set_logout' => 'Sign out',
            'set_theme' => 'Theme',
            'set_dark' => 'Dark',
            'set_light' => 'Light',
            'set_accent' => 'Accent color',
            'set_language' => 'Language',
            'set_apply' => 'Apply',
            'set_member' => 'Member',
            'set_admin_role' => 'Administrator',
            'set_pending' => 'Waiting for activation',
            'wait_title' => 'Your account is being reviewed',
            'wait_sub' => 'An administrator will activate you – this page updates automatically.',
            'wait_s1' => 'Account created',
            'wait_s1s' => 'Registration complete',
            'wait_s2' => 'Review in progress',
            'wait_s3' => 'Activation',
            'wait_s3s' => 'Full access to all apps',
            'wait_ask' => 'A question for the administrator?',
            'wait_hello' => 'Hello',
            'wait_logout' => 'Sign out',
            'msg_placeholder' => 'Message…',
        ],
        'de' => [
            'app_home' => 'Start',
            'app_chat' => 'Chat',
            'app_mail' => 'Mail',
            'app_notes' => 'Notizen',
            'app_tasks' => 'Aufgaben',
            'app_calendar' => 'Kalender',
            'app_contacts' => 'Kontakte',
            'app_files' => 'Dateien',
            'app_docs' => 'Dokumente',
            'app_passwords' => 'Passwörter',
            'app_settings' => 'Einstellungen',
            'app_admin' => 'Verwaltung',
            'nav_add' => 'Hinzufügen',
            'nav_apps' => 'Apps',
            'auth_login' => 'Anmelden',
            'auth_register' => 'Konto anlegen',
            'auth_username' => 'Benutzername',
            'auth_password' => 'Passwort',
            'auth_password2' => 'Passwort bestätigen',
            'auth_do_login' => 'Anmelden',
            'auth_do_register' => 'Konto anlegen',
            'auth_have' => 'Bereits registriert?',
            'auth_no' => 'Noch kein Konto?',
            'auth_note' => 'Alle Inhalte werden mit dem Passwort verschlüsselt. Ein Zurücksetzen ist nicht möglich.',
            'auth_first' => 'Das erste Konto wird Administrator.',
            'auth_approve' => 'Ein Administrator schaltet dein Konto frei.',
            'auth_language' => 'Sprache',
            'set_title' => 'Einstellungen',
            'set_account' => 'Konto',
            'set_appearance' => 'Aussehen',
            'set_connections' => 'Verbindungen',
            'set_system' => 'System',
            'set_admin' => 'Verwaltung',
            'set_logout' => 'Abmelden',
            'set_theme' => 'Theme',
            'set_dark' => 'Dunkel',
            'set_light' => 'Hell',
            'set_accent' => 'Akzentfarbe',
            'set_language' => 'Sprache',
            'set_apply' => 'Übernehmen',
            'set_member' => 'Mitglied',
            'set_admin_role' => 'Administrator',
            'set_pending' => 'Wartet auf Freischaltung',
            'wait_title' => 'Dein Konto wird geprüft',
            'wait_sub' => 'Ein Administrator schaltet dich frei – diese Seite aktualisiert sich von selbst.',
            'wait_s1' => 'Konto erstellt',
            'wait_s1s' => 'Registrierung abgeschlossen',
            'wait_s2' => 'Prüfung läuft',
            'wait_s3' => 'Freischaltung',
            'wait_s3s' => 'Voller Zugriff auf alle Apps',
            'wait_ask' => 'Frage an den Administrator?',
            'wait_hello' => 'Hallo',
            'wait_logout' => 'Abmelden',
            'msg_placeholder' => 'Nachricht…',
        ],
        'th' => [
            'app_home' => 'หน้าหลัก',
            'app_chat' => 'แชท',
            'app_mail' => 'อีเมล',
            'app_notes' => 'โน้ต',
            'app_tasks' => 'งาน',
            'app_calendar' => 'ปฏิทิน',
            'app_contacts' => 'รายชื่อ',
            'app_files' => 'ไฟล์',
            'app_docs' => 'เอกสาร',
            'app_passwords' => 'รหัสผ่าน',
            'app_settings' => 'ตั้งค่า',
            'app_admin' => 'ผู้ดูแล',
            'nav_add' => 'เพิ่ม',
            'nav_apps' => 'แอป',
            'auth_login' => 'เข้าสู่ระบบ',
            'auth_register' => 'สร้างบัญชี',
            'auth_username' => 'ชื่อผู้ใช้',
            'auth_password' => 'รหัสผ่าน',
            'auth_password2' => 'ยืนยันรหัสผ่าน',
            'auth_do_login' => 'เข้าสู่ระบบ',
            'auth_do_register' => 'สร้างบัญชี',
            'auth_have' => 'มีบัญชีแล้ว?',
            'auth_no' => 'ยังไม่มีบัญชี?',
            'auth_note' => 'เนื้อหาทั้งหมดถูกเข้ารหัสด้วยรหัสผ่านของคุณ และไม่สามารถรีเซ็ตได้',
            'auth_first' => 'บัญชีแรกจะเป็นผู้ดูแลระบบ',
            'auth_approve' => 'ผู้ดูแลระบบจะเปิดใช้งานบัญชีของคุณ',
            'auth_language' => 'ภาษา',
            'set_title' => 'ตั้งค่า',
            'set_account' => 'บัญชี',
            'set_appearance' => 'รูปลักษณ์',
            'set_connections' => 'การเชื่อมต่อ',
            'set_system' => 'ระบบ',
            'set_admin' => 'ผู้ดูแล',
            'set_logout' => 'ออกจากระบบ',
            'set_theme' => 'ธีม',
            'set_dark' => 'มืด',
            'set_light' => 'สว่าง',
            'set_accent' => 'สีเน้น',
            'set_language' => 'ภาษา',
            'set_apply' => 'ใช้',
            'set_member' => 'สมาชิก',
            'set_admin_role' => 'ผู้ดูแลระบบ',
            'set_pending' => 'รอการเปิดใช้งาน',
            'wait_title' => 'กำลังตรวจสอบบัญชีของคุณ',
            'wait_sub' => 'ผู้ดูแลระบบจะเปิดใช้งานให้ – หน้านี้จะอัปเดตเอง',
            'wait_s1' => 'สร้างบัญชีแล้ว',
            'wait_s1s' => 'ลงทะเบียนเสร็จสิ้น',
            'wait_s2' => 'กำลังตรวจสอบ',
            'wait_s3' => 'เปิดใช้งาน',
            'wait_s3s' => 'เข้าถึงทุกแอปได้เต็มที่',
            'wait_ask' => 'มีคำถามถึงผู้ดูแล?',
            'wait_hello' => 'สวัสดี',
            'wait_logout' => 'ออกจากระบบ',
            'msg_placeholder' => 'ข้อความ…',
        ],
    ];
}

/** Aktive Sprache: ?lang= (setzt Session) > Session > Konto > 'en'. */
function nx_lang(): string {
    static $l = null;
    if ($l !== null) { return $l; }
    $avail = array_keys(nx_langs());
    $q = $_GET['lang'] ?? $_POST['lang'] ?? '';
    if (is_string($q) && in_array($q, $avail, true)) {
        if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION['lang'] = $q; }
        return $l = $q;
    }
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['lang']) && in_array($_SESSION['lang'], $avail, true)) {
        return $l = $_SESSION['lang'];
    }
    $u = function_exists('current_user') ? current_user() : null;
    if ($u && !empty($u['lang']) && in_array($u['lang'], $avail, true)) {
        return $l = $u['lang'];
    }
    return $l = 'en';
}

/** Uebersetzung; Fallback: Englisch, dann Schluessel. */
function t(string $key): string {
    static $d = null;
    if ($d === null) { $d = nx_i18n(); }
    $lang = nx_lang();
    return $d[$lang][$key] ?? $d['en'][$key] ?? $key;
}

function nx_apps(): array {
    return [
        'home'      => ['name'=>t('app_home'),        'desc'=>'Übersicht & Schnellzugriffe',    'icon'=>'grid',     'color'=>'#4d7ea8', 'tile'=>false, 'min'=>'pending'],
        'chat'      => ['name'=>t('app_chat'),         'desc'=>'Nachrichten, Dateien & Anrufe',  'icon'=>'chat',     'color'=>'#4a8ca0', 'tile'=>true,  'min'=>'pending'],
        'mail'      => ['name'=>t('app_mail'),         'desc'=>'Externe IMAP/SMTP-Postfächer',   'icon'=>'mail',     'color'=>'#4d7ea8', 'tile'=>true,  'min'=>'active'],
        'notes'     => ['name'=>t('app_notes'),      'desc'=>'Gedanken, Listen & Snippets',    'icon'=>'note',     'color'=>'#b3893f', 'tile'=>true,  'min'=>'active'],
        'tasks'     => ['name'=>t('app_tasks'),     'desc'=>'To-dos mit Fälligkeit',          'icon'=>'checkbox', 'color'=>'#4a9d6f', 'tile'=>true,  'min'=>'active'],
        'calendar'  => ['name'=>t('app_calendar'),     'desc'=>'Termine & Ereignisse',           'icon'=>'calendar', 'color'=>'#c25a5a', 'tile'=>true,  'min'=>'active'],
        'contacts'  => ['name'=>t('app_contacts'),     'desc'=>'Adressbuch',                     'icon'=>'user',     'color'=>'#8a7fb0', 'tile'=>true,  'min'=>'active'],
        'files'     => ['name'=>t('app_files'),      'desc'=>'Verschlüsselter Speicher',       'icon'=>'folder',   'color'=>'#4a9d6f', 'tile'=>true,  'min'=>'active'],
        'docs'      => ['name'=>t('app_docs'),       'desc'=>'Dokumente mit Formatierung',     'icon'=>'note',     'color'=>'#4d7ea8', 'tile'=>true,  'min'=>'active'],
        'passwords' => ['name'=>t('app_passwords'),   'desc'=>'Verschlüsselter Login-Speicher',  'icon'=>'key',      'color'=>'#b3893f', 'tile'=>true,  'min'=>'active'],
        'web'       => ['name'=>'Web-App',      'desc'=>'Eingebettete App',               'icon'=>'grid',     'color'=>'#4a8ca0', 'tile'=>false, 'min'=>'active'],
        // App-Store: Apps hinzufuegen/entfernen
        'store'     => ['name'=>'App-Store',    'desc'=>'Apps hinzufügen',                'icon'=>'download', 'color'=>'#4d7ea8', 'tile'=>false, 'min'=>'active'],
        // Lesezeichen leben auf der Startseite; Eintrag bleibt für
        // Handler-Dispatch und Zugriffsprüfung erhalten.
        'bookmarks' => ['name'=>'Lesezeichen',  'desc'=>'Links & Kacheln',                'icon'=>'link',     'color'=>'#8a7fb0', 'tile'=>false, 'min'=>'active'],
        // Verwaltung ist ein Abschnitt der Profilseite; Eintrag bleibt fuer
        // Handler-Dispatch und Zugriffspruefung erhalten.
        'admin'     => ['name'=>t('app_admin'),   'desc'=>'Nutzer, Freischaltung & Quota',  'icon'=>'shield',   'color'=>'#7a828e', 'tile'=>false,  'min'=>'admin'],
        'settings'  => ['name'=>t('app_settings'),       'desc'=>'Meine Karte, Konten & Aussehen', 'icon'=>'user',     'color'=>'#7a828e', 'tile'=>false, 'min'=>'pending'],
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

    // Installer-Verhalten unter dem Remote-Loader DEAKTIVIERT.
    // Der Loader besitzt und liefert index.php; __DIR__ ist hier das
    // ephemere Cache-Verzeichnis. Es wird sich daher NICHT selbst als
    // index.php in die Web-Wurzel kopieren, keine Setup-Quelle vermerken
    // und keine Ursprungsdatei loeschen. Die App laeuft als reine Route.

    // Optionale PHP-Erweiterungen genau EINMAL zu laden versuchen
    // (falls dl() erlaubt ist); danach laeuft die App auch ohne sie.
    // PHP kann keine System-Pakete installieren - fehlt eine Erweiterung
    // dauerhaft, wird die Funktion einfach ausgelassen.
    $extMark = NX_SYS . '/ext_tried';
    if (!is_file($extMark)) {
        @file_put_contents($extMark, date('c'));
        if (function_exists('dl') && !in_array('dl', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            $pfx = (stripos(PHP_OS, 'WIN') === 0) ? 'php_' : '';
            $sfx = (stripos(PHP_OS, 'WIN') === 0) ? '.dll' : '.so';
            foreach (['imap', 'zlib'] as $ext) {
                if (!extension_loaded($ext)) {
                    @dl($pfx . $ext . $sfx);
                }
            }
        }
    }

    // App-Icon-Schreibvorgang in die Web-Wurzel DEAKTIVIERT (Loader-Betrieb).
    // Das Icon wird ausschliesslich ueber ?asset=icon ausgeliefert
    // (nx_icon_url() faellt automatisch darauf zurueck).

    if (is_dir(NX_SESSIONS) && is_writable(NX_SESSIONS)) {
        session_save_path(NX_SESSIONS);
    }

    db_migrate();

    // Selbst-Weiterleitung auf eine kopierte index.php entfaellt im
    // Loader-Betrieb (siehe oben: kein Self-Install).
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
        "CREATE TABLE IF NOT EXISTS app_installs (
            user_id INTEGER NOT NULL,
            app_id  TEXT NOT NULL,
            UNIQUE(user_id, app_id)
        )",
        "CREATE TABLE IF NOT EXISTS docs (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL,
            enc        TEXT NOT NULL,
            updated_at TEXT DEFAULT (datetime('now'))
        )",
        "CREATE TABLE IF NOT EXISTS passwords (
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
        // Externe Sync-Konten (CalDAV/CardDAV/iCal-URL). enc enthaelt
        // Typ, URL, Zugangsdaten und Richtung (push|pull|both).
        "CREATE TABLE IF NOT EXISTS sync_accounts (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id   INTEGER NOT NULL,
            enc       TEXT NOT NULL,
            last_sync TEXT DEFAULT ''
        )",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            ip       TEXT NOT NULL,
            username TEXT DEFAULT '',
            ts       INTEGER NOT NULL
        )",
        // Verknuepfte Anbieterkonten (Google/Microsoft/Apple ...). Schaltet die
        // zugehoerigen Anbieter-Apps im Katalog frei. enc = Label/E-Mail/Notiz.
        "CREATE TABLE IF NOT EXISTS linked_accounts (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL,
            provider   TEXT NOT NULL,
            enc        TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime('now')),
            UNIQUE(user_id, provider)
        )",
        // Freundschaftsanfragen zwischen Nexus-Konten (per Benutzername).
        "CREATE TABLE IF NOT EXISTS friend_requests (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            from_id    INTEGER NOT NULL,
            to_id      INTEGER NOT NULL,
            status     TEXT NOT NULL DEFAULT 'pending',
            created_at TEXT DEFAULT (datetime('now')),
            UNIQUE(from_id, to_id)
        )",
        // Bestaetigte Freundschaften (je Richtung eine Zeile).
        "CREATE TABLE IF NOT EXISTS friends (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL,
            friend_id  INTEGER NOT NULL,
            created_at TEXT DEFAULT (datetime('now')),
            UNIQUE(user_id, friend_id)
        )",
        // Geteilte Felder: Eigentuemer -> Betrachter, pro Feld eine Zeile.
        // val_enc ist mit dem OEFFENTLICHEN Schluessel des Betrachters
        // versiegelt (box_seal). 'einmal geteilt bleibt': Zeilen werden nur
        // angelegt/aktualisiert, das Loeschen entzieht nur kuenftige Updates.
        "CREATE TABLE IF NOT EXISTS shares (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            owner_id   INTEGER NOT NULL,
            viewer_id  INTEGER NOT NULL,
            field      TEXT NOT NULL,
            val_enc    TEXT NOT NULL DEFAULT '',
            sig        TEXT NOT NULL DEFAULT '',
            version    INTEGER NOT NULL DEFAULT 1,
            active     INTEGER NOT NULL DEFAULT 1,
            updated_at TEXT DEFAULT (datetime('now')),
            UNIQUE(owner_id, viewer_id, field)
        )",
        // Betrachter-Zustand je geteiltem Feld: gesehene Version (fuer das
        // Ausrufezeichen) und ob der Betrachter das Feld manuell ueberschrieb.
        "CREATE TABLE IF NOT EXISTS share_state (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            viewer_id    INTEGER NOT NULL,
            owner_id     INTEGER NOT NULL,
            field        TEXT NOT NULL,
            seen_version INTEGER NOT NULL DEFAULT 0,
            manual       INTEGER NOT NULL DEFAULT 0,
            UNIQUE(viewer_id, owner_id, field)
        )",
        // Verlauf fuer 'alles': Kontakte, Freigaben, Konten, Freundschaften.
        // old_enc/new_enc sind mit dem eigenen Master-Key verschluesselt.
        "CREATE TABLE IF NOT EXISTS history (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL,
            scope      TEXT NOT NULL,
            ref        TEXT NOT NULL DEFAULT '',
            field      TEXT NOT NULL DEFAULT '',
            old_enc    TEXT NOT NULL DEFAULT '',
            new_enc    TEXT NOT NULL DEFAULT '',
            actor_id   INTEGER NOT NULL DEFAULT 0,
            note       TEXT NOT NULL DEFAULT '',
            created_at TEXT DEFAULT (datetime('now'))
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
        'CREATE INDEX IF NOT EXISTS idx_friends_u   ON friends(user_id)',
        'CREATE INDEX IF NOT EXISTS idx_freq_to     ON friend_requests(to_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_freq_from   ON friend_requests(from_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_shares_v    ON shares(viewer_id)',
        'CREATE INDEX IF NOT EXISTS idx_shares_o    ON shares(owner_id, viewer_id)',
        'CREATE INDEX IF NOT EXISTS idx_hist_u      ON history(user_id, id)',
    ];
    foreach ($indexes as $sql) {
        db()->exec($sql);
    }
    // Sprach-Spalte nachruesten (bestehende Konten -> Englisch)
    $cols = array_column(db_all('PRAGMA table_info(users)'), 'name');
    if (!in_array('lang', $cols, true)) {
        db()->exec("ALTER TABLE users ADD COLUMN lang TEXT DEFAULT 'en'");
    }
    // Kontakt <-> Nexus-Konto verknuepfen (fuer freigegebene Freund-Kontakte)
    $ccols = array_column(db_all('PRAGMA table_info(contacts)'), 'name');
    if (!in_array('nx_uid', $ccols, true)) {
        db()->exec('ALTER TABLE contacts ADD COLUMN nx_uid INTEGER NOT NULL DEFAULT 0');
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
        . "connect-src 'self'; frame-src 'self'" . (($GLOBALS['nx_frame_src'] ?? '') !== '' ? ' ' . $GLOBALS['nx_frame_src'] : '') . "; object-src 'none'; "
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

/* ------------------------------------------------------------------ *
 *  App-Store: Katalog nativ einbettbarer Web-Apps + Installationsstatus
 * ------------------------------------------------------------------ */
/** Ueber den Store installierbare Nexus-Apps (Standard fuer neue Konten). */
function nx_store_builtins(): array {
    return ['chat', 'mail', 'notes', 'tasks', 'calendar', 'contacts', 'files', 'docs', 'passwords'];
}

/** Katalog bekannter Web-Apps. embed=true laeuft in Nexus (iframe),
 *  embed=false oeffnet die Seite (viele grosse Seiten verbieten Framing). */
function nx_webapps(): array {
    return [
        'youtube'        => ['name'=>'YouTube','cat'=>'Video','embed'=>true,'frame'=>'https://www.youtube-nocookie.com','ratio'=>'video','rx'=>'(?:v=|youtu\\.be/|embed/|shorts/)([A-Za-z0-9_-]{11})','tpl'=>'https://www.youtube-nocookie.com/embed/%s?rel=0','hint'=>'YouTube-Link oder Video-ID'],
        'vimeo'          => ['name'=>'Vimeo','cat'=>'Video','embed'=>true,'frame'=>'https://player.vimeo.com','ratio'=>'video','rx'=>'vimeo\\.com/(?:video/)?(\\d{6,})','tpl'=>'https://player.vimeo.com/video/%s','hint'=>'Vimeo-Link oder ID'],
        'dailymotion'    => ['name'=>'Dailymotion','cat'=>'Video','embed'=>true,'frame'=>'https://geo.dailymotion.com','ratio'=>'video','rx'=>'dailymotion\\.com/(?:video/|embed/video/)?([A-Za-z0-9]+)','tpl'=>'https://geo.dailymotion.com/player.html?video=%s','hint'=>'Dailymotion-Link'],
        'streamable'     => ['name'=>'Streamable','cat'=>'Video','embed'=>true,'frame'=>'https://streamable.com','ratio'=>'video','rx'=>'streamable\\.com/(?:e/)?([A-Za-z0-9]+)','tpl'=>'https://streamable.com/e/%s','hint'=>'Streamable-Link'],
        'loom'           => ['name'=>'Loom','cat'=>'Video','embed'=>true,'frame'=>'https://www.loom.com','ratio'=>'video','rx'=>'loom\\.com/(?:share/|embed/)?([A-Za-z0-9]+)','tpl'=>'https://www.loom.com/embed/%s','hint'=>'Loom-Link'],
        'ted'            => ['name'=>'TED','cat'=>'Video','embed'=>true,'frame'=>'https://embed.ted.com','ratio'=>'video','rx'=>'ted\\.com/talks/([A-Za-z0-9_]+)','tpl'=>'https://embed.ted.com/talks/%s','hint'=>'TED-Talk-Link'],
        'tiktok'         => ['name'=>'TikTok','cat'=>'Video','embed'=>true,'frame'=>'https://www.tiktok.com','ratio'=>'video','rx'=>'tiktok\\.com/@[^/]+/video/(\\d+)','tpl'=>'https://www.tiktok.com/embed/v2/%s','hint'=>'TikTok-Video-Link'],
        'twitch'         => ['name'=>'Twitch','cat'=>'Video','embed'=>true,'frame'=>'https://player.twitch.tv','ratio'=>'video','special'=>'twitch','hint'=>'Twitch-Kanalname'],
        'facebookvid'    => ['name'=>'Facebook Video','cat'=>'Video','embed'=>true,'frame'=>'https://www.facebook.com','ratio'=>'video','rx'=>'(https?://[^\\s]*facebook\\.com/[^\\s]+/videos/\\d+)','tpl'=>'https://www.facebook.com/plugins/video.php?href=%s','hint'=>'Facebook-Video-Link'],
        'coub'           => ['name'=>'Coub','cat'=>'Video','embed'=>true,'frame'=>'https://coub.com','ratio'=>'video','rx'=>'coub\\.com/view/([A-Za-z0-9]+)','tpl'=>'https://coub.com/embed/%s','hint'=>'Coub-Link'],
        'spotify'        => ['name'=>'Spotify','cat'=>'Musik','embed'=>true,'frame'=>'https://open.spotify.com','ratio'=>'audio','special'=>'spotify','hint'=>'Spotify-Link (Song/Album/Playlist)'],
        'soundcloud'     => ['name'=>'SoundCloud','cat'=>'Musik','embed'=>true,'frame'=>'https://w.soundcloud.com','ratio'=>'audio','special'=>'soundcloud','hint'=>'SoundCloud-Track-Link'],
        'mixcloud'       => ['name'=>'Mixcloud','cat'=>'Musik','embed'=>true,'frame'=>'https://player-widget.mixcloud.com','ratio'=>'audio','special'=>'mixcloud','hint'=>'Mixcloud-Show-Link'],
        'deezer'         => ['name'=>'Deezer','cat'=>'Musik','embed'=>true,'frame'=>'https://widget.deezer.com','ratio'=>'audio','special'=>'deezer','hint'=>'Deezer-Link (Track/Album/Playlist)'],
        'applemusic'     => ['name'=>'Apple Music','cat'=>'Musik','embed'=>true,'frame'=>'https://embed.music.apple.com','ratio'=>'audio','special'=>'applemusic','hint'=>'Apple-Music-Link'],
        'bandcamp'       => ['name'=>'Bandcamp','cat'=>'Musik','embed'=>true,'frame'=>'https://bandcamp.com','ratio'=>'audio','rx'=>'(https?://[^\\s]*bandcamp\\.com/(?:album|track)/[^\\s]+)','tpl'=>'https://bandcamp.com/EmbeddedPlayer/v=2/?url=%s','hint'=>'Bandcamp-Link'],
        'radio'          => ['name'=>'Radio Garden','cat'=>'Musik','embed'=>true,'frame'=>'https://radio.garden','ratio'=>'full','static'=>'https://radio.garden/visit/'],
        'gmaps'          => ['name'=>'Google Maps','vendor'=>'google','cat'=>'Karten','embed'=>true,'frame'=>'https://www.google.com','ratio'=>'full','special'=>'gmaps','hint'=>'Ort oder Adresse'],
        'osm'            => ['name'=>'OpenStreetMap','cat'=>'Karten','embed'=>true,'frame'=>'https://www.openstreetmap.org','ratio'=>'full','static'=>'https://www.openstreetmap.org/export/embed.html?layer=mapnik'],
        'windy'          => ['name'=>'Windy Wetter','cat'=>'Karten','embed'=>true,'frame'=>'https://embed.windy.com','ratio'=>'full','static'=>'https://embed.windy.com/embed2.html?type=map&metricWind=km%2Fh&metricTemp=%C2%B0C'],
        'codepen'        => ['name'=>'CodePen','cat'=>'Entwickeln','embed'=>true,'frame'=>'https://codepen.io','ratio'=>'full','rx'=>'codepen\\.io/([^/\\s]+)/(?:pen|embed)/([A-Za-z0-9]+)','tpl'=>'https://codepen.io/%s/embed/%s?default-tab=result','hint'=>'CodePen-Link'],
        'codesandbox'    => ['name'=>'CodeSandbox','cat'=>'Entwickeln','embed'=>true,'frame'=>'https://codesandbox.io','ratio'=>'full','rx'=>'codesandbox\\.io/(?:s|embed|p/sandbox)/([A-Za-z0-9_-]+)','tpl'=>'https://codesandbox.io/embed/%s','hint'=>'CodeSandbox-Link'],
        'stackblitz'     => ['name'=>'StackBlitz','cat'=>'Entwickeln','embed'=>true,'frame'=>'https://stackblitz.com','ratio'=>'full','rx'=>'stackblitz\\.com/edit/([A-Za-z0-9-]+)','tpl'=>'https://stackblitz.com/edit/%s?embed=1','hint'=>'StackBlitz-Link'],
        'replit'         => ['name'=>'Replit','cat'=>'Entwickeln','embed'=>true,'frame'=>'https://replit.com','ratio'=>'full','rx'=>'replit\\.com/(@[^\\s?]+)','tpl'=>'https://replit.com/%s?embed=true','hint'=>'Replit-Link'],
        'jsfiddle'       => ['name'=>'JSFiddle','cat'=>'Entwickeln','embed'=>true,'frame'=>'https://jsfiddle.net','ratio'=>'full','rx'=>'jsfiddle\\.net/([A-Za-z0-9_/]+?)/?$','tpl'=>'https://jsfiddle.net/%s/embedded/','hint'=>'JSFiddle-Link'],
        'shadertoy'      => ['name'=>'Shadertoy','cat'=>'Entwickeln','embed'=>true,'frame'=>'https://www.shadertoy.com','ratio'=>'full','rx'=>'shadertoy\\.com/view/([A-Za-z0-9]+)','tpl'=>'https://www.shadertoy.com/embed/%s','hint'=>'Shadertoy-Link'],
        'diagrams'       => ['name'=>'diagrams.net','cat'=>'Entwickeln','embed'=>true,'frame'=>'https://app.diagrams.net','ratio'=>'full','static'=>'https://app.diagrams.net/?embed=1&spin=1'],
        'gdocs'          => ['name'=>'Google Docs','vendor'=>'google','cat'=>'Dokumente','embed'=>true,'frame'=>'https://docs.google.com','ratio'=>'full','special'=>'gdoc','hint'=>'Google-Docs/Sheets/Slides-Link'],
        'gcal'           => ['name'=>'Google Kalender','vendor'=>'google','cat'=>'Dokumente','embed'=>true,'frame'=>'https://calendar.google.com','ratio'=>'full','special'=>'gcal','hint'=>'Google-Kalender src oder Einbett-Link'],
        'gforms'         => ['name'=>'Google Forms','vendor'=>'google','cat'=>'Dokumente','embed'=>true,'frame'=>'https://docs.google.com','ratio'=>'full','rx'=>'(https?://docs\\.google\\.com/forms/[^\\s]+)','tpl'=>'%s','hint'=>'Google-Forms-Link'],
        'gdrive'         => ['name'=>'Google Drive','vendor'=>'google','cat'=>'Dokumente','embed'=>true,'frame'=>'https://drive.google.com','ratio'=>'full','rx'=>'drive\\.google\\.com/file/d/([A-Za-z0-9_-]+)','tpl'=>'https://drive.google.com/file/d/%s/preview','hint'=>'Google-Drive-Datei-Link'],
        'msoffice'       => ['name'=>'Office Online','vendor'=>'microsoft','cat'=>'Dokumente','embed'=>true,'frame'=>'https://view.officeapps.live.com','ratio'=>'full','rx'=>'(https?://[^\\s]+\\.(?:docx|xlsx|pptx))','tpl'=>'https://view.officeapps.live.com/op/embed.aspx?src=%s','hint'=>'Öffentlicher Office-Datei-Link (docx/xlsx/pptx)'],
        'notion'         => ['name'=>'Notion','cat'=>'Dokumente','embed'=>true,'frame'=>'https://www.notion.so','ratio'=>'full','rx'=>'(https?://[a-z0-9-]+\\.notion\\.site/[^\\s]+)','tpl'=>'%s','hint'=>'Notion-Seiten-Link (notion.site)'],
        'canva'          => ['name'=>'Canva','cat'=>'Dokumente','embed'=>true,'frame'=>'https://www.canva.com','ratio'=>'full','rx'=>'canva\\.com/design/([A-Za-z0-9_-]+)','tpl'=>'https://www.canva.com/design/%s/view?embed','hint'=>'Canva-Design-Link'],
        'figma'          => ['name'=>'Figma','cat'=>'Dokumente','embed'=>true,'frame'=>'https://www.figma.com','ratio'=>'full','special'=>'figma','hint'=>'Figma-Datei-Link'],
        'photopea'       => ['name'=>'Photopea','cat'=>'Werkzeuge','embed'=>true,'frame'=>'https://www.photopea.com','ratio'=>'full','static'=>'https://www.photopea.com'],
        'excalidraw'     => ['name'=>'Excalidraw','cat'=>'Werkzeuge','embed'=>true,'frame'=>'https://excalidraw.com','ratio'=>'full','static'=>'https://excalidraw.com'],
        'tldraw'         => ['name'=>'tldraw','cat'=>'Werkzeuge','embed'=>true,'frame'=>'https://www.tldraw.com','ratio'=>'full','static'=>'https://www.tldraw.com'],
        'desmos'         => ['name'=>'Desmos','cat'=>'Werkzeuge','embed'=>true,'frame'=>'https://www.desmos.com','ratio'=>'full','static'=>'https://www.desmos.com/calculator'],
        'geogebra'       => ['name'=>'GeoGebra','cat'=>'Werkzeuge','embed'=>true,'frame'=>'https://www.geogebra.org','ratio'=>'full','static'=>'https://www.geogebra.org/calculator'],
        'weather'        => ['name'=>'Wetter','cat'=>'Werkzeuge','embed'=>true,'frame'=>'https://wttr.in','ratio'=>'full','static'=>'https://wttr.in/?format=v2&m'],
        'scratch'        => ['name'=>'Scratch','cat'=>'Werkzeuge','embed'=>true,'frame'=>'https://scratch.mit.edu','ratio'=>'video','rx'=>'scratch\\.mit\\.edu/projects/(\\d+)','tpl'=>'https://scratch.mit.edu/projects/%s/embed','hint'=>'Scratch-Projekt-Link'],
        'sketchfab'      => ['name'=>'Sketchfab','cat'=>'Werkzeuge','embed'=>true,'frame'=>'https://sketchfab.com','ratio'=>'full','rx'=>'sketchfab\\.com/(?:3d-models/[^\\s]*-|models/)([A-Za-z0-9]+)','tpl'=>'https://sketchfab.com/models/%s/embed','hint'=>'Sketchfab-Modell-Link'],
        'wikipedia'      => ['name'=>'Wikipedia','cat'=>'Wissen','embed'=>true,'frame'=>'https://de.m.wikipedia.org','ratio'=>'full','static'=>'https://de.m.wikipedia.org/wiki/Wikipedia:Hauptseite'],
        'wiktionary'     => ['name'=>'Wiktionary','cat'=>'Wissen','embed'=>true,'frame'=>'https://de.m.wiktionary.org','ratio'=>'full','static'=>'https://de.m.wiktionary.org'],
        'wikivoyage'     => ['name'=>'Wikivoyage','cat'=>'Wissen','embed'=>true,'frame'=>'https://de.m.wikivoyage.org','ratio'=>'full','static'=>'https://de.m.wikivoyage.org'],
        'archive'        => ['name'=>'Archive.org','cat'=>'Wissen','embed'=>true,'frame'=>'https://archive.org','ratio'=>'full','rx'=>'archive\\.org/(?:details|embed)/([^/?\\s]+)','tpl'=>'https://archive.org/embed/%s','hint'=>'Archive.org-Link'],
        'giphy'          => ['name'=>'Giphy','cat'=>'Bilder','embed'=>true,'frame'=>'https://giphy.com','ratio'=>'full','rx'=>'giphy\\.com/(?:gifs|embed|clips)/(?:.*-)?([A-Za-z0-9]+)','tpl'=>'https://giphy.com/embed/%s','hint'=>'Giphy-Link'],
        'imgur'          => ['name'=>'Imgur','cat'=>'Bilder','embed'=>true,'frame'=>'https://imgur.com','ratio'=>'full','rx'=>'imgur\\.com/(?:a/|gallery/)?([A-Za-z0-9]+)','tpl'=>'https://imgur.com/a/%s/embed','hint'=>'Imgur-Link'],
        'flickr'         => ['name'=>'Flickr','cat'=>'Bilder','embed'=>true,'frame'=>'https://www.flickr.com','ratio'=>'full','rx'=>'(https?://[^\\s]*flickr\\.com/photos/[^\\s]+)','tpl'=>'%s','hint'=>'Flickr-Foto-Link'],
        'redditpost'     => ['name'=>'Reddit-Post','cat'=>'Soziales','embed'=>true,'frame'=>'https://www.redditmedia.com','ratio'=>'full','rx'=>'reddit\\.com(/r/[^\\s]+/comments/[^\\s]+)','tpl'=>'https://www.redditmedia.com%s?embed=true','hint'=>'Reddit-Post-Link'],
        'mastodon'       => ['name'=>'Mastodon','cat'=>'Soziales','embed'=>true,'frame'=>'https://mastodon.social','ratio'=>'full','rx'=>'(https?://[a-z0-9.-]+/@[^\\s/]+/\\d+)','tpl'=>'%s/embed','hint'=>'Mastodon-Post-Link'],
    ];
}

/** Farbe je App aus dem Namen ableiten (stabile, bunte Kacheln). */
function nx_wa_color(string $name): string {
    $h = crc32($name) % 360;
    return 'hsl(' . $h . ' 55% 45%)';
}

/* ================================================================== *
 *  Anbieter, verknuepfte Konten, Freunde & Freigaben, Verlauf
 * ================================================================== */

/** Bekannte Anbieter fuer Konten-Verknuepfung und Anbieter-Ordner. */
function nx_providers(): array {
    return [
        'google'    => ['name' => 'Google',    'color' => '#4285f4', 'icon' => 'grid'],
        'microsoft' => ['name' => 'Microsoft', 'color' => '#0a7bd4', 'icon' => 'grid'],
        'apple'     => ['name' => 'Apple',     'color' => '#8a8f98', 'icon' => 'grid'],
        'meta'      => ['name' => 'Meta',      'color' => '#0866ff', 'icon' => 'grid'],
    ];
}

/** Verknuepfte Konten des Nutzers: provider => ['id','label','created_at']. */
function nx_linked(array $u): array {
    $out = [];
    foreach (db_all('SELECT * FROM linked_accounts WHERE user_id=? ORDER BY provider', [$u['id']]) as $r) {
        $d = dec_row($r['enc'], mk()) ?: [];
        $out[$r['provider']] = [
            'id'         => (int) $r['id'],
            'label'      => (string) ($d['label'] ?? ''),
            'created_at' => (string) $r['created_at'],
        ];
    }
    return $out;
}

/** Felder, die ein Kontakt/Konto teilen kann (Basis + je verknuepftem Konto). */
function nx_share_fields(array $u): array {
    $f = [
        'name'     => 'Name',
        'email'    => 'E-Mail',
        'phone'    => 'Telefon',
        'birthday' => 'Geburtstag',
        'address'  => 'Adresse',
        'note'     => 'Notiz',
    ];
    $prov = nx_providers();
    foreach (nx_linked($u) as $p => $meta) {
        $label = $prov[$p]['name'] ?? ucfirst($p);
        $f['acct:' . $p] = $label . '-Konto';
    }
    return $f;
}

/** Eigene teilbare Werte (aus 'Meine Karte' + Konto-Labels). */
function nx_self_values(array $u): array {
    // 'Meine Karte' ist die eigene contacts-Zeile mit me=1 (nx_uid=0).
    $card = [];
    foreach (db_all('SELECT enc FROM contacts WHERE user_id=? AND nx_uid=0', [$u['id']]) as $row) {
        $c = dec_row($row['enc'], mk());
        if ($c && (int) ($c['me'] ?? 0) === 1) { $card = $c; break; }
    }
    $vals = [
        'name'     => (string) ($card['name'] ?? ($u['display_name'] ?: $u['username'])),
        'email'    => (string) ($card['email'] ?? ($u['email'] ?? '')),
        'phone'    => (string) ($card['phone'] ?? ''),
        'birthday' => (string) ($card['birthday'] ?? ''),
        'address'  => (string) ($card['address'] ?? ''),
        'note'     => (string) ($card['note'] ?? ''),
    ];
    foreach (nx_linked($u) as $p => $meta) {
        $vals['acct:' . $p] = $meta['label'] !== '' ? $meta['label'] : 'verknüpft';
    }
    return $vals;
}

/** Fingerabdruck eines Wertes (nur der Eigentuemer mit mk() kann ihn bilden). */
function nx_share_sig(string $field, string $value): string {
    return substr(hash_hmac('sha256', $field . '|' . $value, (string) mk()), 0, 24);
}

function nx_is_friend(int $uid, int $fid): bool {
    return (bool) db_one('SELECT 1 FROM friends WHERE user_id=? AND friend_id=?', [$uid, $fid]);
}

/** Freunde als Nutzerzeilen. */
function nx_friends(array $u): array {
    $rows = db_all('SELECT friend_id FROM friends WHERE user_id=? ORDER BY id', [$u['id']]);
    $out  = [];
    foreach ($rows as $r) {
        $f = user_by_id((int) $r['friend_id']);
        if ($f) { $out[] = $f; }
    }
    return $out;
}

/**
 * Ein Feld an einen Betrachter freigeben oder aktualisieren.
 * Versiegelt den Wert mit dem oeffentlichen Schluessel des Betrachters.
 * Erhoeht die Version nur bei tatsaechlicher Wertaenderung ('!').
 */
function nx_share_set(array $owner, int $viewerId, string $field, string $value, bool $active = true): void {
    $viewer = user_by_id($viewerId);
    if (!$viewer || empty($viewer['pubkey'])) { return; }
    $sig = nx_share_sig($field, $value);
    $cur = db_one('SELECT * FROM shares WHERE owner_id=? AND viewer_id=? AND field=?',
        [$owner['id'], $viewerId, $field]);
    if ($cur && $cur['sig'] === $sig && (int) $cur['active'] === ($active ? 1 : 0)) {
        return; // nichts geaendert
    }
    $sealed = box_seal_raw($value, user_pub($viewer), $viewer['pk_alg']);
    if ($sealed === null) { return; }
    $blob = b64e($sealed);
    if ($cur) {
        $bump = ($cur['sig'] !== $sig) ? 1 : 0;
        db_run('UPDATE shares SET val_enc=?, sig=?, version=version+?, active=?, updated_at=' . "datetime('now')" . ' WHERE id=?',
            [$blob, $sig, $bump, $active ? 1 : 0, $cur['id']]);
    } else {
        db_run('INSERT INTO shares (owner_id,viewer_id,field,val_enc,sig,version,active) VALUES (?,?,?,?,?,1,?)',
            [$owner['id'], $viewerId, $field, $blob, $sig, $active ? 1 : 0]);
    }
    nx_history_add((int) $owner['id'], 'share', 'u' . $viewerId, $field, null, $value,
        (int) $owner['id'], $cur ? 'aktualisiert' : 'freigegeben');
}

/** Aktive Freigaben des Eigentuemers an einen Betrachter neu berechnen. */
function nx_share_resync(array $owner, int $viewerId): void {
    $vals = nx_self_values($owner);
    foreach (db_all('SELECT field FROM shares WHERE owner_id=? AND viewer_id=? AND active=1',
        [$owner['id'], $viewerId]) as $r) {
        $f = $r['field'];
        if (array_key_exists($f, $vals)) {
            nx_share_set($owner, $viewerId, $f, (string) $vals[$f], true);
        }
    }
}

/** Nach Aenderung der eigenen Daten alle Freunde aktualisieren. */
function nx_share_broadcast(array $owner): void {
    foreach (db_all('SELECT DISTINCT viewer_id FROM shares WHERE owner_id=? AND active=1',
        [$owner['id']]) as $r) {
        nx_share_resync($owner, (int) $r['viewer_id']);
    }
}

/**
 * Alle an mich (Betrachter) freigegebenen Felder eines Eigentuemers oeffnen.
 * Rueckgabe: field => ['value','version','active','seen','manual','changed'].
 */
function nx_shared_from(array $viewer, int $ownerId): array {
    $out = [];
    $state = [];
    foreach (db_all('SELECT field,seen_version,manual FROM share_state WHERE viewer_id=? AND owner_id=?',
        [$viewer['id'], $ownerId]) as $s) {
        $state[$s['field']] = ['seen' => (int) $s['seen_version'], 'manual' => (int) $s['manual']];
    }
    foreach (db_all('SELECT * FROM shares WHERE owner_id=? AND viewer_id=? ORDER BY field',
        [$ownerId, $viewer['id']]) as $r) {
        $pt = box_open_raw(b64d($r['val_enc']), user_pub($viewer), user_sec($viewer));
        if ($pt === null) { continue; }
        $st = $state[$r['field']] ?? ['seen' => 0, 'manual' => 0];
        $out[$r['field']] = [
            'value'   => $pt,
            'version' => (int) $r['version'],
            'active'  => (int) $r['active'],
            'seen'    => $st['seen'],
            'manual'  => $st['manual'],
            'changed' => (int) $r['version'] > $st['seen'],
        ];
    }
    return $out;
}

/** Anzahl offener Freigabe-Aenderungen (fuer '!' an einem Freund-Kontakt). */
function nx_share_pending(array $viewer, int $ownerId): int {
    $n = 0;
    foreach (nx_shared_from($viewer, $ownerId) as $f) {
        if ($f['changed']) { $n++; }
    }
    return $n;
}

/** Verlaufseintrag schreiben (old/new mit eigenem Master-Key verschluesselt). */
function nx_history_add(int $uid, string $scope, string $ref, string $field, $old, $new, int $actor = 0, string $note = ''): void {
    $mk = mk();
    if ($mk === null) { return; }
    $encOld = ($old === null || $old === '') ? '' : enc_row(['v' => (string) $old], $mk);
    $encNew = ($new === null || $new === '') ? '' : enc_row(['v' => (string) $new], $mk);
    db_run('INSERT INTO history (user_id,scope,ref,field,old_enc,new_enc,actor_id,note) VALUES (?,?,?,?,?,?,?,?)',
        [$uid, $scope, $ref, $field, $encOld, $encNew, $actor, $note]);
}

/** Verlauf lesen und entschluesseln. */
function nx_history(array $u, string $scope = '', string $ref = '', int $limit = 60): array {
    $sql = 'SELECT * FROM history WHERE user_id=?';
    $args = [$u['id']];
    if ($scope !== '') { $sql .= ' AND scope=?'; $args[] = $scope; }
    if ($ref !== '')   { $sql .= ' AND ref=?';   $args[] = $ref; }
    $sql .= ' ORDER BY id DESC LIMIT ' . (int) $limit;
    $out = [];
    foreach (db_all($sql, $args) as $r) {
        $old = $r['old_enc'] !== '' ? (dec_row($r['old_enc'], mk())['v'] ?? '') : '';
        $new = $r['new_enc'] !== '' ? (dec_row($r['new_enc'], mk())['v'] ?? '') : '';
        $out[] = [
            'scope' => $r['scope'], 'ref' => $r['ref'], 'field' => $r['field'],
            'old' => $old, 'new' => $new, 'actor_id' => (int) $r['actor_id'],
            'note' => $r['note'], 'created_at' => $r['created_at'],
        ];
    }
    return $out;
}



/** Baut die iframe-Quelle einer eingebetteten App aus optionaler
 *  Nutzereingabe $v. Leerer Rueckgabewert => Eingabe noch noetig. */
function nx_embed_src(array $w, string $v): string {
    $v = trim($v);
    if (isset($w['static'])) {
        return $w['static'];
    }
    if (isset($w['special'])) {
        return nx_embed_special($w['special'], $v);
    }
    if (isset($w['rx']) && $v !== '') {
        if (preg_match('#' . $w['rx'] . '#i', $v, $m)) {
            $args = array_slice($m, 1);
            // Frei-URL-Vorlagen (%s == ganze URL) roh, sonst je Segment kodiert
            if ($w['tpl'] !== '%s' && strpos($w['tpl'], '://%s') === false) {
                $args = array_map('rawurlencode', $args);
            }
            return vsprintf($w['tpl'], $args);
        }
        return '';
    }
    return '';
}

function nx_embed_special(string $key, string $v): string {
    if ($key === 'spotify') {
        return preg_match('#open\.spotify\.com/(?:intl-[a-z]+/)?(track|album|playlist|episode|show|artist)/([A-Za-z0-9]+)#', $v, $m)
            ? 'https://open.spotify.com/embed/' . $m[1] . '/' . $m[2] : '';
    }
    if ($key === 'soundcloud') {
        return preg_match('#^https?://(?:www\.|m\.)?soundcloud\.com/\S+#i', $v, $m)
            ? 'https://w.soundcloud.com/player/?url=' . rawurlencode($m[0]) . '&color=%23ff5500' : '';
    }
    if ($key === 'mixcloud') {
        return preg_match('#mixcloud\.com/(\S+)#i', $v, $m)
            ? 'https://player-widget.mixcloud.com/widget/iframe/?feed=' . rawurlencode('/' . rtrim($m[1], '/') . '/') : '';
    }
    if ($key === 'deezer') {
        return preg_match('#deezer\.com/(?:[a-z]{2}/)?(track|album|playlist)/(\d+)#i', $v, $m)
            ? 'https://widget.deezer.com/widget/dark/' . $m[1] . '/' . $m[2] : '';
    }
    if ($key === 'applemusic') {
        return preg_match('#music\.apple\.com/(\S+)#i', $v, $m)
            ? 'https://embed.music.apple.com/' . $m[1] : '';
    }
    if ($key === 'twitch') {
        $ch = preg_replace('/[^A-Za-z0-9_]/', '', preg_replace('#^.*twitch\.tv/#', '', $v));
        if ($ch === '') { return ''; }
        $host = explode(':', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'))[0];
        $host = preg_replace('/[^A-Za-z0-9.\-]/', '', $host) ?: 'localhost';
        return 'https://player.twitch.tv/?channel=' . $ch . '&parent=' . $host;
    }
    if ($key === 'gmaps') {
        return 'https://www.google.com/maps?q=' . rawurlencode($v !== '' ? $v : 'Berlin') . '&output=embed';
    }
    if ($key === 'gdoc') {
        if (preg_match('#(https?://docs\.google\.com/(?:document|spreadsheets|presentation)/d/[A-Za-z0-9_-]+)#', $v, $m)) {
            return $m[1] . '/preview';
        }
        return '';
    }
    if ($key === 'gcal') {
        if (preg_match('#src=([^&\s]+)#', $v, $m)) {
            return 'https://calendar.google.com/calendar/embed?src=' . rawurlencode(urldecode($m[1]));
        }
        return preg_match('#([^@\s]+@[^&\s]+)#', $v, $m2) ? 'https://calendar.google.com/calendar/embed?src=' . rawurlencode($m2[1]) : '';
    }
    if ($key === 'figma') {
        return preg_match('#(https?://(?:www\.)?figma\.com/(?:file|design|proto)/\S+)#', $v, $m)
            ? 'https://www.figma.com/embed?embed_host=nexus&url=' . rawurlencode($m[1]) : '';
    }
    return '';
}

function nx_installed(array $u): array {
    $rows = db_all('SELECT app_id FROM app_installs WHERE user_id=?', [$u['id']]);
    if (!$rows) {
        return nx_store_builtins(); // Standard fuer neue/alte Konten
    }
    return array_map(static function ($r) { return $r['app_id']; }, $rows);
}

/** Vor der ersten Aenderung Standardauswahl materialisieren. */
function nx_installed_materialize(array $u): void {
    if (!db_one('SELECT 1 FROM app_installs WHERE user_id=?', [$u['id']])) {
        foreach (nx_store_builtins() as $id) {
            db_run('INSERT OR IGNORE INTO app_installs (user_id, app_id) VALUES (?,?)', [$u['id'], $id]);
        }
    }
}

function can_access(array $user, string $appId): bool {
    $apps = nx_apps();
    if (!isset($apps[$appId]) || $user['status'] === 'suspended') {
        return false;
    }
    if ($appId === 'mail' && !nx_cap('mail')) {
        return false; // kein Weg das Postfach zu lesen -> App ausblenden (unable.txt)
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
    // E-Mail ist optional; wenn angegeben, muss sie gültig sein.
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['err' => 'Die angegebene E-Mail-Adresse ist ungültig.'];
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
            $info = "Neue Registrierung wartet auf Freischaltung.\n\nBenutzer: $user"
                . ($email !== '' ? "\nE-Mail: $email" : '') . "\nTicket: $code";
            chat_send($uid, $mk, (int) $admin['id'], ['t' => 'sys', 'body' => $info], $code, false);
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

/* ---- Videoanruf-Relay: Chunk-Ablage (nur PHP + Dateisystem) ---- */
function call_dir(): string {
    $d = NX_SYS . '/rtc';
    if (!is_dir($d)) {
        @mkdir($d, 0770, true);
    }
    return $d;
}

/** Veraltete Chunks entfernen (Anruf beendet/abgebrochen). */
function call_gc(string $dir): void {
    foreach (glob($dir . '/c*.bin') ?: [] as $f) {
        if (@filemtime($f) < time() - NX_CALL_TTL) {
            @unlink($f);
        }
    }
}

/** Alle Chunks beider Richtungen eines Gesprächs löschen. */
function call_cleanup(int $a, int $b): void {
    $dir = call_dir();
    foreach (glob($dir . '/c' . $a . '_' . $b . '_*.bin') ?: [] as $f) {
        @unlink($f);
    }
    foreach (glob($dir . '/c' . $b . '_' . $a . '_*.bin') ?: [] as $f) {
        @unlink($f);
    }
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

/** Feature konnte nicht bereitgestellt werden -> einmal in unable.txt
 *  auf dem Webserver protokollieren. */
function nx_unable(string $feature, string $reason): void {
    $f = NX_DATA . '/unable.txt';
    $line = $feature;
    $existing = @file_get_contents($f);
    if ($existing !== false && preg_match('/^' . preg_quote($feature, '/') . '\t/m', $existing)) {
        return; // bereits vermerkt
    }
    @file_put_contents($f, $feature . "\t" . str_replace(["\n", "\r", "\t"], ' ', $reason) . "\t" . gmdate('c') . "\n", FILE_APPEND);
}

/** Fähigkeit vorhanden? Versucht die Erweiterung, sonst einen anderen
 *  Weg; klappt gar nichts -> false (+ unable.txt). Ergebnis gecacht. */
function nx_cap(string $feature): bool {
    static $c = [];
    if (isset($c[$feature])) { return $c[$feature]; }
    $ok = false;
    if ($feature === 'mail') {
        // 1. imap-Erweiterung  2. anderer Weg: Socket-IMAP
        $ok = function_exists('imap_open') || function_exists('stream_socket_client');
        if (!$ok) {
            nx_unable('mail', 'imap-Erweiterung fehlt und keine Socket-Verbindungen moeglich (stream_socket_client/allow_url_fopen deaktiviert).');
        }
    } else {
        $ok = true;
    }
    return $c[$feature] = $ok;
}

/* ---- Socket-IMAP (anderer Weg, falls imap-Erweiterung fehlt) ---- */
function nx_imap_conn(array $acc) {
    $enc = $acc['imap_enc'] ?? 'ssl';
    $target = ($enc === 'notls' ? 'tcp://' : 'ssl://') . $acc['imap_host'] . ':' . (int) $acc['imap_port'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => !empty($acc['validate_cert']), 'verify_peer_name' => !empty($acc['validate_cert'])]]);
    $fp = @stream_socket_client($target, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { return null; }
    stream_set_timeout($fp, 12);
    $greet = fgets($fp);
    if ($greet === false || strpos($greet, '* OK') !== 0) { fclose($fp); return null; }
    return $fp;
}
function nx_imap_cmd($fp, string $tag, string $cmd): array {
    fwrite($fp, "$tag $cmd\r\n");
    $lines = [];
    while (($l = fgets($fp)) !== false) {
        $lines[] = $l;
        if (strpos($l, "$tag ") === 0) { break; }
        if (preg_match('/\{(\d+)\}\r\n$/', $l, $m)) {
            $need = (int) $m[1]; $buf = '';
            while (strlen($buf) < $need && !feof($fp)) { $r = fread($fp, $need - strlen($buf)); if ($r === '' || $r === false) break; $buf .= $r; }
            $lines[] = $buf;
        }
    }
    $last = end($lines);
    return [is_string($last) && strpos($last, "$tag OK") === 0, $lines];
}
function nx_imap_login($fp, string $user, string $pass): bool {
    [$ok] = nx_imap_cmd($fp, 'a1', 'LOGIN "' . addcslashes($user, '"\\') . '" "' . addcslashes($pass, '"\\') . '"');
    return $ok;
}
function nx_imap_list(array $acc, int $count = 40): array {
    $fp = nx_imap_conn($acc);
    if (!$fp) { return ['err' => 'Verbindung fehlgeschlagen']; }
    if (!nx_imap_login($fp, $acc['username'], $acc['password'])) { fclose($fp); return ['err' => 'Anmeldung abgelehnt']; }
    [$ok, $lines] = nx_imap_cmd($fp, 'a2', 'SELECT INBOX');
    $total = 0;
    foreach ($lines as $l) { if (preg_match('/\* (\d+) EXISTS/', $l, $m)) { $total = (int) $m[1]; } }
    if (!$ok) { fclose($fp); return ['err' => 'Postfach nicht waehlbar']; }
    if ($total === 0) { nx_imap_cmd($fp, 'a9', 'LOGOUT'); fclose($fp); return ['msgs' => [], 'total' => 0]; }
    $from = max(1, $total - $count + 1);
    [, $l2] = nx_imap_cmd($fp, 'a3', "FETCH $from:$total (UID FLAGS BODY.PEEK[HEADER.FIELDS (FROM SUBJECT DATE)])");
    $flat = [];
    foreach ($l2 as $chunk) { foreach (preg_split('/\r\n|\n/', $chunk) as $sub) { $flat[] = $sub; } }
    $msgs = []; $cur = null;
    foreach ($flat as $line) {
        if (preg_match('/^\* (\d+) FETCH .*UID (\d+)/', $line, $m)) {
            if ($cur) { $msgs[] = $cur; }
            $cur = ['uid' => (int) $m[2], 'from' => '', 'subject' => '(kein Betreff)', 'date' => '', 'seen' => strpos($line, '\\Seen') !== false];
        } elseif ($cur !== null) {
            if (stripos($line, 'From:') === 0) { $cur['from'] = nx_mime_hdr(trim(substr($line, 5))); }
            elseif (stripos($line, 'Subject:') === 0) { $cur['subject'] = nx_mime_hdr(trim(substr($line, 8))); }
            elseif (stripos($line, 'Date:') === 0) { $cur['date'] = trim(substr($line, 5)); }
        }
    }
    if ($cur) { $msgs[] = $cur; }
    nx_imap_cmd($fp, 'a9', 'LOGOUT'); fclose($fp);
    usort($msgs, static fn($a, $b) => $b['uid'] <=> $a['uid']);
    return ['msgs' => $msgs, 'total' => $total];
}
function nx_imap_raw(array $acc, int $uid): ?string {
    $fp = nx_imap_conn($acc);
    if (!$fp || !nx_imap_login($fp, $acc['username'], $acc['password'])) { if ($fp) { fclose($fp); } return null; }
    nx_imap_cmd($fp, 'a2', 'SELECT INBOX');
    [$ok, $lines] = nx_imap_cmd($fp, 'a3', "UID FETCH $uid (BODY.PEEK[])");
    nx_imap_cmd($fp, 'a9', 'LOGOUT'); fclose($fp);
    if (!$ok) { return null; }
    $raw = '';
    foreach ($lines as $l) { if (strlen($l) > strlen($raw) && strpos($l, '* ') !== 0 && !preg_match('/^a\d /', $l)) { $raw = $l; } }
    return $raw !== '' ? $raw : null;
}
/** MIME-Header dekodieren (=?utf-8?..?=) – best effort. */
function nx_mime_hdr(string $s): string {
    if (function_exists('iconv_mime_decode')) {
        $d = @iconv_mime_decode($s, 0, 'UTF-8');
        if ($d !== false) { return $d; }
    }
    if (function_exists('mb_decode_mimeheader')) { return mb_decode_mimeheader($s); }
    return $s;
}
/** Aus einer Rohnachricht den Klartext (text/plain) extrahieren. */
function nx_mail_text(string $raw): string {
    $parts = preg_split("/\r?\n\r?\n/", $raw, 2);
    $head = $parts[0] ?? '';
    $body = $parts[1] ?? '';
    if (preg_match('/boundary="?([^"\r\n;]+)"?/i', $head, $bm)) {
        $segs = explode('--' . $bm[1], $raw);
        foreach ($segs as $seg) {
            if (stripos($seg, 'text/plain') !== false) {
                $sp = preg_split("/\r?\n\r?\n/", $seg, 2);
                $body = $sp[1] ?? $body;
                $head = $sp[0] ?? $head;
                break;
            }
        }
    }
    if (preg_match('/Content-Transfer-Encoding:\s*base64/i', $head)) { $body = (string) base64_decode(preg_replace('/\s+/', '', $body)); }
    elseif (preg_match('/Content-Transfer-Encoding:\s*quoted-printable/i', $head)) { $body = quoted_printable_decode($body); }
    return trim($body);
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

/** Grobe Dateiart aus der Endung. */
function nx_file_kind(string $name): string {
    $e = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($e, ['mp3','m4a','aac','ogg','oga','wav','flac','opus'], true)) return 'audio';
    if (in_array($e, ['mp4','webm','mov','m4v','ogv'], true)) return 'video';
    if (in_array($e, ['png','jpg','jpeg','gif','webp','bmp','svg','avif'], true)) return 'image';
    if (in_array($e, ['txt','md','markdown','log','csv','json','xml','ini','conf','yml','yaml','html','css','js','php','py','sh'], true)) return 'text';
    if ($e === 'pdf') return 'pdf';
    return 'other';
}

/** Content-Type fuer die Inline-Auslieferung. */
function nx_mime(string $name): string {
    $e = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $m = ['mp3'=>'audio/mpeg','m4a'=>'audio/mp4','aac'=>'audio/aac','ogg'=>'audio/ogg','oga'=>'audio/ogg','opus'=>'audio/ogg','wav'=>'audio/wav','flac'=>'audio/flac',
          'mp4'=>'video/mp4','webm'=>'video/webm','mov'=>'video/quicktime','m4v'=>'video/mp4','ogv'=>'video/ogg',
          'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','bmp'=>'image/bmp','svg'=>'image/svg+xml','avif'=>'image/avif',
          'pdf'=>'application/pdf','txt'=>'text/plain','md'=>'text/plain','csv'=>'text/plain','json'=>'text/plain'];
    return $m[$e] ?? 'application/octet-stream';
}

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
const NX_ICON_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAgAAAAIACAIAAAB7GkOtAAAJj0lEQVR42u3Zi7GjQAwAQfJ0WM6NdHAK/kAZ7fRWJ/CMpKmr2x7PHYCgzU8AIAAACAAAAgCAAAAgAAAIAAACAIAAACAAAAgAAAIAgAAAIAAACAAAAgCAAAAgAAAIAAACAIAAACAAAAgAAAIAIAAACAAAAgCAAAAgAAAIAAACAIAAACAAAAgAAAIAgAAAIAAACAAAAgCAAAAgAAAIAAACAIAAACAAAAgAAAIAIAB+BQABAEAAABAAAAQAAAEAQAAAEAAABAAAAQBAAAAQAAAEAAABAEAAABAAAAQAAAEAQAAAEAAABAAAAQBAAAAQAAABAEAAABAAAAQAAAEAQAAAEAAABAAAAYAbOd57figEACoXXw8QAHD6ZQABAHdfCRAAcPplAAEAp18GEADIX38NQACge/01AAGA6OmXAQQA6tdfAxAA6F5/DUAAoHv9NQABgO711wAEALrXXwMQAOhefw1AAEAAQAAgdv01AAGA7vXXAAQABAAEAGLXXwMQAOhefw1AAEAAQAAgdv01AAEAAQABAAEAAYDC9dcABAAEAAQABAAEAArXXwMQABAAEAAQABAAEAAQAARAAEAAcP01AAQAARAAEAAEQABAABAAAQABQAAEAAQAARAAEAAEQABAABAAAQABQAAEAAQAARAABAAEQAAQANAA1x8BAAEQAAQABEAAEAAQAAFAAEAABAABAA1w/REAEAABQABAAAQAAYByA3xWBAAEAAQAMg3wQREAEAAQAMg0wKdEAEAAQAAg0wAfEQGAYgN8PgQABAAEADIN8OEQACg2wCdDAKDYAB8LAQABAAGATAN8JgQAig3wgRAAyGXAR0EAoNgAnwMBgGIDfAgEAHIZ8OMjAFBsgJ8dAYBcBvzUCAAUG+BHRgBAAEAAQABAAEAAQABAAEAAQABAAEAAQABAAEAAQABAAEAAQABAAEAAQABAAEAAQABAAEAAQABAABAAAQABQAAEAAQAARAAEAAEQABAABAAAUAAQAAEAAEAARAABAAEQAAQABAAAUAAQABAAEAAQABAAEAAQABAAEAAQABAAEAAQABAAEAAQABAAEAAQABAAEAAQABAAEAAQABAAEAAQAAQAAEAAUAABAAEAAEQABAABEAAQAAQAAFAAPwKCIAAIAAgAAKAAIAACAACAAIgAAgACIDfGQEAAQABAAEAAYBF774SIADg9MsAAgDt0y8DCACkT78MIACQPv0ygABA+vTLAAIA6dMvAwgA1K+/BiAA0L3+GoAAQPT0ywACAPXrrwEIAHSvvwYgANC9/hqAAED3+msAAgDd668BCAB0r78GIADQvf4agADg+h8aAAKA668BIAAIgACAAOD6awAIAK6/BoAA4PprAAgAAiAAIAC4/hoAAoAACAAIAK6/BoAAIAACAAKA668BCAAIgAAgAOD6awACAAIgAAgAuP4agACAAAgAAgCuvwYgACAAAoAAgAAIAAIAAiAACACuv6cBCAAC4AkAAoAAeAKAAOD6exqAACAAngAgAAiAJwAIAAIgACAACIAAgAAgAAIAAoAACAAIAK6/BoAAIAACAAKAAAgACAACIAAgAAiAACAAIAACgACAAAgAAgACIAAIAAiAACAAIAACgACAAAgAAgAa4PojACAAAoAAIACeACAACIAnAAgAAuAJAAKAAHgCgAAgAJ4AIAAIgCcACABOvycDCACuvwaAAOD0ywAIAE6/DIAA4PprAAgATr8MgADg9MsACABOvwyAAOD6awACAE6/DCAA4PTLAAKA0+/JAAKA6+9pAAKA0+/JAAKA0+/JAAKA6+9pAAKA0+/JAALAaYfY6ffGZUB4BICr7q/T790qA/4hIgD84fi6/t4ys+Q4CIC7f+HmuHHeiFlyLgTA6T9zbdw1b9wsOR0C4PT/ujZumTd6lpwRAXD9v1wbJ8xbYJAcEwFw+j1PihAA19/zNAABcP09TwMQANff8zQAAXD9/WejP1MDEADXf9wy+0s9DRAA17+7w/5YTwMEwPVPr64/1tMAAXD9u0vrj/U0QABc/+6u+ns9DRAAAUivqL/XEwABcP3Ty+nv9TRAAFz/9Fr6Yz0NEAABSG+jP9YTAAFw/dN76I/1NEAALGF6/fyxngAIgN2ze7u/0RwiABavvnL+QNOIAFg5y/bBFzSTZlIAsGk2zWSaTAHAmlkw82k+BcCCecbAlJpSAbBXlgrjalwFwEZZJ0ysiRUA62SRMLfmVgAskhXC9JpeAbBClgczbIYFwPLYHIyxMRYAm2NnMMyGWQAsjJ3BPJtnAbAwFgbzbJ4FwMJYGMyzeRYAC2NhMM/mWQAsjIXBPJtnAbAwFgbzbJ4FwM5YGAyzYRYAO2NnMMy+sgDYGTuDYUYA7IydwTAjAHbGwmCeEQALY2HMs3kWAPI74/saZsMsANgZDLNhFgAyO+PjmmfzLABEd8aXNcyGWQCwMxhmwywAZHbGZzXP5lkAiO6Mb2qYDbMAUNwZH9Q8m2cBILozvqZhNswCQHFnfErMswAQ3RnfEcMsABTXxhfEMAuAX6G4Mz4f5hkBiO6Mb4dhRgCKa+OrYZgRgOLa+F4YZgSguDM+FuYZASjujM+EeUYAijvjA2GeEYDizvg0mGcEoLg2vgiGGQEoro1vgWFGAHJr4xNgnhGA4tr45THMCEBuc/zaGGYEILc5fmEMMwLQ2hw/KeYZAWhtjh8Q84wAhJbHD4V5RgAAEAAABAAAAQBAAAAQAAAEAEAA/AoAAgCAAAAgAAAIAAACAIAAACAAAAgAAAIAgAAAIAAACAAAAgCAAAAgAAAIAAACAIAAACAAAAgAAAIAgAAAIAAAAgCAAAAgAAAIAAACAIAAACAAAAgAAAIAgAAAIAAACAAAAgCAAAAgAAAIAAACAIAAACAAAAgAAAIAgAAAIAAAAuBXABAAAAQAAAEAQAAAEAAABAAAAQBAAAAQAAAEAAABAEAAABAAAAQAAAEAQAAAEAAABAAAAQBAAAAQAAAEAAABABAAAAQAAAEAQAAAEAAABAAAAQBAAAAQAAAEAAABAEAAABAAAAQAAAEAQAAAEAAABAAAAQBAAAAQAAAEAAABABAAAAQAAAEAQAAAEAAABAAAAQBAAAAQAAAEAAABAEAAABAAAAQAAAEAQAAAEAAABAAAAQBAAAAQAAAEAAABAEAAAAQAAAEAQAAAEAAABAAAAQBAAAAQAAAEAAABAEAAABAAAAQAAAEAQAAAEAAABAAAAQBAAAAQAAAEAAABAEAAANJebYHqf53t6BgAAAAASUVORK5CYII=';

function nx_icon_url(): string {
    return is_file(NX_ROOT . '/apple-touch-icon.png') ? 'apple-touch-icon.png' : '?asset=icon';
}

/** Vollbild-Hinweis fuer Handy-Browser: Nexus wird dort nicht benutzt,
 *  sondern als Web-App installiert. JS blendet das Gate nur ein, wenn
 *  mobil UND nicht im Standalone-Modus. */
function install_gate(): string {
    $out  = '<div id="instGate" hidden><div class="ig-box">';
    $out .= '<img class="ig-icon" src="' . nx_icon_url() . '" alt="" width="84" height="84">';
    $out .= '<h2>' . NX_NAME . ' als App nutzen</h2>';
    $out .= '<p>Auf dem Handy läuft ' . NX_NAME . ' als installierte App vom Startbildschirm – nicht im Browser.</p>';
    $out .= '<div id="igIos" hidden><p class="ig-step"><b>1.</b> Unten <b>Teilen</b> antippen</p>'
          . '<p class="ig-step"><b>2.</b> <b>„Zum Home-Bildschirm"</b> wählen</p>'
          . '<p class="ig-step"><b>3.</b> Vom Startbildschirm öffnen</p></div>';
    $out .= '<div id="igAndroid" hidden><button class="btn" onclick="pwaInstall()" style="width:100%;justify-content:center">'
          . 'Jetzt installieren</button>'
          . '<p class="ig-step" style="margin-top:10px">Oder: Browser-Menü <b>⋮</b> → <b>„App installieren"</b></p></div>';
    $out .= '</div></div>';
    return $out;
}

function serve_asset(string $which): void {
    if ($which === 'icon') {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=604800');
        echo base64_decode(NX_ICON_PNG);
        exit;
    }
    if ($which === 'manifest') {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo json_encode([
            'name' => NX_NAME, 'short_name' => NX_NAME,
            'start_url' => './', 'scope' => './', 'display' => 'standalone',
            'background_color' => '#0c0e12', 'theme_color' => '#0c0e12',
            'icons' => [['src' => nx_icon_url(), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($which === 'sw') {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        // Minimaler Service Worker: macht die Seite installierbar,
        // laesst alle Anfragen normal ans Netz durch.
        echo "self.addEventListener('install',e=>self.skipWaiting());self.addEventListener('fetch',()=>{});";
        exit;
    }
    header('Content-Type: ' . ($which === 'js' ? 'application/javascript' : 'text/css') . '; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    echo $which === 'js' ? asset_js() : asset_css();
    exit;
}

function asset_css(): string {
    // Instanz-Theme steuert die Grundstimmung. Nexus ist dunkel-zuerst:
    // :root traegt die dunkle Palette, [data-theme="light"] die helle.
    //   'dark'  -> nur dunkle Basis (color-scheme:dark)
    //   'light' -> <html data-theme="light"> erzwingt hell (color-scheme:light)
    //   'auto'  -> zusaetzlicher @media(prefers-color-scheme:light)-Block
    //              kippt bei hellem System auf hell, sofern der Nutzer nicht
    //              explizit dunkel gewaehlt hat (color-scheme:light dark).
    $mode = defined('NX_THEME_MODE') ? NX_THEME_MODE : 'auto';
    $colorScheme = $mode === 'light' ? 'light' : ($mode === 'dark' ? 'dark' : 'light dark');
    $lightVars = <<<'NXLIGHT'
  --bg:#f4f6f9; --bg2:#eef1f5; --panel:#ffffff; --panel2:#f5f7fa;
  --line:#e4e8ee; --txt:#1a1f27; --muted:#5f6774; --muted2:#98a1ad;
  --ok:#2f9d68; --warn:#a87d2c; --err:#cf5b5b;
  --shadow:0 6px 24px -10px rgba(30,40,60,.2);
  --shadow-sm:0 1px 2px rgba(30,40,60,.06);
NXLIGHT;
    $themeHead = ":root{color-scheme:{$colorScheme}}\n";
    if ($mode === 'auto') {
        $themeHead .= "@media (prefers-color-scheme: light){:root:not([data-theme=\"dark\"]){\n{$lightVars}\n}}\n";
    }
    return $themeHead . <<<'NXCSS'
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
  --main-y:clamp(15px,2vw,24px);
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
a:focus-visible,button:focus-visible,.iconbtn:focus-visible,.side-user:focus-visible,.nav a:focus-visible{outline:2px solid var(--accent);outline-offset:2px;border-radius:11px}
code,.mono{font-family:var(--mono);font-size:12.5px}
.ic{display:block;flex:none}
button,input,select,textarea{font:inherit;color:inherit}

/* Shell: eine Fläche, keine Seitenleiste – die Startseite ist der
   Launcher, jede App traegt ihren eigenen Zurueck-Knopf. */
.shell{min-height:100vh;min-height:100dvh;display:flex;justify-content:center}
.avatar{width:34px;height:34px;border-radius:10px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-weight:650;font-size:14px;flex:none}
.rbadge{position:absolute;top:-4px;right:-4px;background:var(--accent);color:#fff;font-size:9.5px;font-weight:700;min-width:16px;height:16px;border-radius:8px;display:grid;place-items:center;padding:0 4px;font-family:var(--mono);box-shadow:0 0 0 2px var(--bg)}
.rbadge.warn{background:var(--warn)}

/* App-Store: klarer Hinzufuegen-/Zustand */
.store-intro{display:flex;gap:12px;align-items:flex-start;background:var(--panel);border-radius:12px;padding:13px 15px;box-shadow:var(--shadow-sm);max-width:560px;margin-bottom:14px}
.store-intro .ic{color:var(--accent);flex:none;margin-top:1px}
.store-intro strong{font-size:14px}
.store-intro p{color:var(--muted);font-size:12.5px;margin:2px 0 0}
.store-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:8px 12px;border-radius:10px;border:0;cursor:pointer;font-weight:600;font-size:13px;transition:filter .12s}
.store-btn.is-add{background:var(--accent);color:#fff}
.store-btn.is-add:hover{filter:brightness(1.08)}
.store-btn.is-on{background:color-mix(in srgb,var(--ok) 16%,transparent);color:var(--ok)}
.store-btn.is-on:hover{background:color-mix(in srgb,var(--err) 16%,transparent);color:var(--err)}
.ccard.installed{border:1px solid color-mix(in srgb,var(--ok) 35%,var(--line))}
.on-badge{flex:none;width:20px;height:20px;border-radius:50%;background:var(--ok);color:#fff;display:grid;place-items:center;align-self:center}

/* App-Store-Leiste (Suche + Filter) */
.store-bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;max-width:560px;margin-bottom:12px}
.store-chips{display:flex;gap:7px;overflow-x:auto;padding-bottom:4px;margin-bottom:14px;-webkit-overflow-scrolling:touch}
.chip-btn{flex:none;padding:7px 14px;border-radius:99px;border:0;background:var(--panel);color:var(--muted);box-shadow:var(--shadow-sm);cursor:pointer;font-size:13px;font-weight:600;white-space:nowrap}
.chip-btn.on{background:var(--accent);color:#fff}
.store-bar .input{flex:1;min-width:160px}
/* App-Store: Anbieter/Themen-Ordner */
.folder-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;margin-bottom:12px}
.folder-tile{display:flex;align-items:center;gap:11px;background:var(--panel);border-radius:14px;padding:13px 14px;box-shadow:var(--shadow-sm);text-decoration:none;color:var(--text);position:relative;transition:transform .1s}
.folder-tile:active{transform:scale(.98)}
.folder-tile .favatar{flex:none;width:40px;height:40px;border-radius:11px;display:grid;place-items:center}
.folder-tile .fname{flex:1;min-width:0;display:flex;flex-direction:column}
.folder-tile .fname strong{font-size:14px;display:flex;align-items:center;gap:4px}
.folder-tile .fname small{color:var(--muted);font-size:11.5px;margin-top:1px}
.folder-tile .fcount{flex:none;font-size:12px;font-weight:700;color:var(--muted);background:var(--bg);border-radius:99px;min-width:22px;height:22px;display:grid;place-items:center;padding:0 6px}
.folder-tile.locked{opacity:.85}
.folder-tile.locked .favatar{filter:grayscale(.4)}
.empty.locked h3{color:var(--text)}
/* Freund-Detail: geteilte Felder */
.bang{display:inline-grid;place-items:center;width:16px;height:16px;border-radius:99px;background:var(--warn);color:#000;font-size:11px;font-weight:800;line-height:1}
.sh-link{font-weight:500;font-size:12px;color:var(--accent);text-decoration:none;margin-left:8px}
.frow{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--line);flex-wrap:wrap}
.frow:last-child{border-bottom:0}
.frow.changed{background:color-mix(in srgb,var(--warn) 8%,transparent);border-radius:8px;padding:10px;margin:2px 0}
.frow .fmeta{flex:1;min-width:150px;display:flex;flex-direction:column}
.frow .fmeta small{color:var(--muted);font-size:11.5px}
.frow .fmeta strong{font-size:14px;word-break:break-word}
.frow .fack{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);flex-wrap:wrap}
.frow .fack form{display:inline-flex;gap:6px;align-items:center}
.shrow{display:flex;align-items:center;gap:11px;padding:9px 0;border-bottom:1px solid var(--line);cursor:pointer}
.shrow:last-of-type{border-bottom:0}
.shrow input{width:auto;flex:none}
.shrow .shlbl{display:flex;flex-direction:column;min-width:0}
.shrow .shlbl small{color:var(--muted);font-size:11.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.people-search{display:flex;gap:8px}
.people-search .input{flex:1}
.lrow{display:flex;align-items:center;gap:11px;padding:9px 0;border-bottom:1px solid var(--line)}
.lrow:last-of-type{border-bottom:0}
.lrow .mlabel{flex:1;min-width:0;display:flex;flex-direction:column}
.lrow .mlabel small{color:var(--muted);font-size:11.5px}
.ccard.friend .avatar{background:color-mix(in srgb,#8a7fb0 16%,transparent);color:#8a7fb0}
.store-chk{display:flex;align-items:center;gap:7px;font-size:13px;color:var(--muted);white-space:nowrap}
.store-chk input{width:auto}
.lapp-add .lico{background:var(--panel);color:var(--accent);border:1px dashed var(--line);box-shadow:none}

/* App-Store & eingebettete Web-Apps */
.store-act{display:flex}
.store-act form{width:100%}
.store-act .btn{width:100%;justify-content:center}
.viewer-media{text-align:center;padding:10px}
.viewer-media .vtitle{font-weight:600;font-size:15px}
.embed-audio{border-radius:12px;overflow:hidden}
.embed-audio iframe{width:100%;height:152px;border:0;display:block}
.embed-16x9{position:relative;width:100%;aspect-ratio:16/9;border-radius:12px;overflow:hidden;background:#000}
.embed-16x9 iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.embed-full{border-radius:12px;overflow:hidden;background:var(--panel);box-shadow:var(--shadow-sm)}
.embed-full iframe{width:100%;height:calc(100dvh - 150px);min-height:420px;border:0;display:block}

/* Dokumente-App (nativer Editor) */
.doc-wrap{max-width:820px}
.doc-title{font-size:20px;font-weight:650;border:0;background:transparent;padding:6px 2px;margin-bottom:6px}
.doc-title:focus{box-shadow:none;background:transparent}
.doc-toolbar{display:flex;flex-wrap:wrap;gap:3px;align-items:center;background:var(--panel);border-radius:10px;padding:6px;box-shadow:var(--shadow-sm);position:sticky;top:6px;z-index:5;margin-bottom:10px}
.dbtn{min-width:32px;height:32px;padding:0 8px;border:0;background:transparent;color:var(--txt);border-radius:8px;cursor:pointer;font-size:14px}
.dbtn:hover{background:var(--panel2)}
.dsep{width:1px;height:20px;background:var(--line);margin:0 4px}
.doc-editor{background:var(--panel);border-radius:12px;box-shadow:var(--shadow-sm);padding:20px;min-height:60vh;outline:none;line-height:1.6;font-size:15px}
.doc-editor:focus{box-shadow:0 0 0 2px var(--accent-soft),var(--shadow-sm)}
.doc-editor h1{font-size:26px;font-weight:700;margin:12px 0 6px}
.doc-editor h2{font-size:20px;font-weight:650;margin:10px 0 5px}
.doc-editor p{margin:0 0 8px}
.doc-editor ul,.doc-editor ol{margin:0 0 8px 22px}
.doc-editor a{color:var(--accent);text-decoration:underline}
.doc-editor:empty::before{content:"Text eingeben…";color:var(--muted2)}
.doc-card .doc-prev{color:var(--muted);font-size:12.5px;margin-top:8px;line-height:1.45;max-height:54px;overflow:hidden}

/* Passwort-Manager */
.pw-field{position:relative;display:flex}
.pw-field .input{padding-right:40px}
.pw-eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);background:transparent;border:0;color:var(--muted);cursor:pointer;padding:6px;border-radius:8px}
.pw-eye:hover{background:var(--panel2);color:var(--txt)}
.pw-dot{font-family:var(--mono);letter-spacing:1px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis}
.pw-mini{background:var(--panel2);border:0;color:var(--accent);cursor:pointer;font-size:11px;font-weight:600;padding:3px 8px;border-radius:7px}
.pw-mini:hover{filter:brightness(1.08)}

/* Startseite: App-Launcher */
.lgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,86px),1fr));gap:6px 4px}
.lapp{display:flex;flex-direction:column;align-items:center;gap:7px;padding:11px 4px;border-radius:14px;transition:background .12s}
.lapp:hover{background:var(--panel)}
.lapp .lico{position:relative;width:54px;height:54px;border-radius:15px;display:grid;place-items:center;color:#fff;background:var(--tc,var(--accent));box-shadow:var(--shadow-sm)}
.lapp small{font-size:11px;font-weight:550;color:var(--txt);max-width:84px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.lapp .lico{font-weight:700;font-size:20px}
.lapp-add .lico{background:var(--panel2);color:var(--muted);box-shadow:none;border:1px dashed var(--line)}
.homeava{border:0;background:transparent;box-shadow:none;width:auto;height:auto}

/* Installations-Gate: Handy-Browser zeigt nur die Anleitung */
#instGate{position:fixed;inset:0;z-index:100;background:var(--bg);display:grid;place-items:center;padding:24px;padding-bottom:max(24px,var(--safe-b))}
#instGate[hidden]{display:none}
.ig-box{width:100%;max-width:340px;text-align:center}
.ig-icon{border-radius:22%;box-shadow:var(--shadow);margin-bottom:16px}
.ig-box h2{font-size:19px;font-weight:650;margin-bottom:6px}
.ig-box>p{color:var(--muted);font-size:13.5px;margin-bottom:16px}
.ig-step{background:var(--panel);border-radius:11px;padding:10px 13px;margin-bottom:7px;font-size:13.5px;text-align:left;box-shadow:var(--shadow-sm)}

/* Ausklappbare erweiterte Einstellungen (Mail-Setup) */
.adv{margin-top:6px;border-radius:11px;background:var(--panel2);overflow:hidden}
.adv>summary{list-style:none;cursor:pointer;padding:11px 13px;font-size:13.5px;font-weight:600;display:flex;align-items:center;gap:8px}
.adv>summary::-webkit-details-marker{display:none}
.adv>summary::before{content:"";width:8px;height:8px;border-right:2px solid var(--muted);border-bottom:2px solid var(--muted);transform:rotate(-45deg);transition:transform .15s;margin-left:2px}
.adv[open]>summary::before{transform:rotate(45deg)}
.adv-in{padding:4px 13px 13px}

/* Profil: native Menüliste mit Unterseiten */
.mlist{display:flex;flex-direction:column;background:var(--panel);border-radius:var(--radius);box-shadow:var(--shadow-sm);overflow:hidden;max-width:480px;margin-bottom:12px}
.settings-w{max-width:480px}
.lang-row{width:100%;background:transparent;border:0;text-align:left;cursor:pointer;font:inherit}
.lang-row.on{color:var(--accent)}
.lang-row.on .chev{color:var(--accent)}
.mrow{padding:12px 14px}
.mrow{display:flex;align-items:center;gap:12px;padding:13px 14px;border-bottom:1px solid var(--line);transition:background .12s}
.mrow:last-child{border-bottom:0}
a.mrow:hover{background:var(--panel2)}
.mrow .mic{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;flex:none;color:var(--mc,var(--accent));background:color-mix(in srgb,var(--mc,var(--accent)) 14%,transparent)}
.mrow .mlabel{flex:1;min-width:0;font-weight:500}
.mrow .mval{color:var(--muted2);font-size:12.5px;display:flex;align-items:center;gap:6px}
.mrow .chev{color:var(--muted2);flex:none;display:grid;place-items:center}
.mrow.danger{color:var(--err)}
.mrow.danger .mic{color:var(--err);background:color-mix(in srgb,var(--err) 12%,transparent)}
.mrow.static{cursor:default}
.quota-bar.mq{margin-top:5px;max-width:170px}
.btn.ghost.danger-txt{color:var(--err)}
.phead{display:flex;align-items:center;gap:14px}
.phead .avatar.big{width:52px;height:52px;font-size:20px;border-radius:14px}
.phead .pwho{flex:1;min-width:0;line-height:1.3}
.phead .pwho strong{display:block;font-size:16px;font-weight:650}
.phead .pwho small{color:var(--muted)}

/* Main */
.main{padding:var(--main-y) var(--main-x) calc(46px + var(--safe-b));padding-right:max(var(--main-x),var(--safe-r));max-width:1240px;width:100%;min-width:0}
.topbar{display:flex;align-items:center;gap:clamp(10px,2vw,14px);margin-bottom:clamp(14px,2vw,20px)}
.topbar h1{font-size:clamp(18px,16px + .8vw,21px);font-weight:650;letter-spacing:-.3px}
.topbar .sub{color:var(--muted);font-size:13px;margin-top:3px}
.spacer{flex:1}
.iconbtn{width:38px;height:38px;border-radius:11px;border:1px solid transparent;background:var(--panel);display:grid;place-items:center;cursor:pointer;color:var(--txt);transition:.12s;box-shadow:var(--shadow-sm)}
.iconbtn:hover{background:var(--panel2)}

/* Buttons / Forms */
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 15px;border-radius:11px;background:var(--accent);color:#fff;border:1px solid transparent;cursor:pointer;font-weight:600;font-size:13.5px;transition:filter .12s,transform .06s;box-shadow:var(--shadow-sm)}
.btn:hover{filter:brightness(1.08)}
.btn:active{transform:translateY(1px)}
.btn.ghost{background:var(--panel2);color:var(--txt);border-color:transparent;box-shadow:none}
.btn.ghost:hover{filter:brightness(1.06)}
.btn.danger{background:var(--err);border-color:transparent}
.btn.ok{background:var(--ok);border-color:transparent}
.btn.sm{padding:6px 11px;font-size:12.5px}
.field{margin-bottom:13px}
.field label{display:block;font-size:12.5px;color:var(--muted);margin-bottom:6px;font-weight:500}
/* Felder im iOS-Stil: gefüllte Fläche statt Rahmen, Fokus = Akzentring.
   Alle Steuerelemente (Text, Datum, Zeit, Auswahl) haben dieselbe Höhe
   und Optik – in jeder App gleich. */
.input,textarea,select{width:100%;min-width:0;max-width:100%;padding:10px 13px;border-radius:11px;border:1px solid transparent;background:var(--bg2);color:var(--txt);transition:border-color .12s,box-shadow .12s,background .12s;outline:none;font-size:13.5px;min-height:42px}
/* Datum/Zeit: natives Widget vereinheitlichen; leere Felder kollabieren
   auf iOS sonst, Werte sitzen zentriert statt links */
input[type=date],input[type=time]{-webkit-appearance:none;appearance:none;display:block;line-height:normal}
input[type=date]::-webkit-date-and-time-value,input[type=time]::-webkit-date-and-time-value{text-align:left;margin:0}
input[type=date]::-webkit-calendar-picker-indicator{opacity:.55}
/* Auswahlfelder: eigener, ueberall gleicher Pfeil statt Plattform-Optik */
select{-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23848a94' stroke-width='2.4' stroke-linecap='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 13px center;padding-right:36px}
.input:focus,textarea:focus,select:focus{border-color:var(--accent);background:var(--panel);box-shadow:0 0 0 3px var(--accent-soft)}
textarea{resize:vertical;min-height:100px;font-family:inherit}
.row{display:flex;gap:12px;flex-wrap:wrap}
.row>*{flex:1;min-width:0}

/* Cards */
.panel{background:var(--panel);border:1px solid transparent;border-radius:var(--radius);padding:16px;box-shadow:var(--shadow-sm)}
.grid{display:grid;gap:var(--gap)}
.section-h{display:flex;align-items:center;gap:9px;margin:18px 0 10px;font-size:11.5px;font-weight:600;color:var(--muted2);text-transform:uppercase;letter-spacing:.7px}
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
.note{break-inside:avoid;margin-bottom:12px;border-radius:var(--radius);padding:15px;border:1px solid transparent;box-shadow:var(--shadow-sm);background:var(--panel);border-left:3px solid var(--nc,var(--accent));position:relative}
.note h4{margin-bottom:7px;font-size:14px;font-weight:600}
.note .body{color:var(--muted);white-space:pre-wrap;font-size:13px;max-height:320px;overflow:auto}
.note .meta{margin-top:11px;display:flex;align-items:center;gap:7px;font-size:11.5px;color:var(--muted2);font-family:var(--mono)}
.note .acts{margin-left:auto;display:flex;gap:2px}
.note .acts a{padding:4px;border-radius:var(--radius-sm);color:var(--muted)}
.note .acts a:hover{background:var(--panel2);color:var(--txt)}

/* Calendar */
.cal-head{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.cal-head h2{font-size:15px;font-weight:600;min-width:180px;text-align:center}
/* Kalender im iOS-Stil: keine Zellrahmen, ruhige Fläche, Akzent-Kreis
   für heute – wirkt wie eine durchgehende Ansicht statt eines Gitters */
.cal{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;background:var(--panel);border-radius:var(--radius);padding:10px;box-shadow:var(--shadow-sm);max-width:840px}
.cal-head{max-width:840px}
.cal .dow{text-align:center;color:var(--muted2);font-size:11px;font-weight:600;padding:4px 0 8px;text-transform:uppercase;letter-spacing:.5px}
.cal .cell{background:transparent;border:0;border-radius:var(--radius-sm);aspect-ratio:1/1;min-height:0;padding:5px;display:flex;flex-direction:column;gap:2px;transition:background .12s;cursor:pointer;overflow:hidden}
.cal .cell:hover{background:var(--panel2)}
.cal .cell.out{opacity:.3}
.cal .cell .num{font-size:12px;font-weight:600;color:var(--muted);font-family:var(--mono);width:22px;height:22px;display:grid;place-items:center;border-radius:99px}
@media(min-width:560px){.cal .cell{aspect-ratio:auto;min-height:74px;max-height:104px}}
.cal .cell.today .num{background:var(--accent);color:#fff}
.ev{font-size:11px;padding:2px 5px;border-radius:3px;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;background:var(--ec,var(--accent));border-left:2px solid rgba(0,0,0,.25)}
.ev small{opacity:.85;font-weight:600;margin-right:3px;font-family:var(--mono)}

/* Tasks */
.tasklist{display:flex;flex-direction:column;border-radius:var(--radius);overflow:hidden;background:var(--panel);box-shadow:var(--shadow-sm)}
.task{display:flex;align-items:center;gap:11px;padding:9px 13px;border-bottom:1px solid var(--line)}
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

/* Startseite: Lesezeichen-Kacheln, Heute-Zeilen, App-Chips */
.bmgrid{grid-template-columns:repeat(auto-fill,minmax(min(100%,190px),1fr))}
.bmtile{position:relative;display:flex;align-items:center;background:var(--panel);border:1px solid transparent;border-radius:12px;box-shadow:var(--shadow-sm);transition:transform .12s,box-shadow .12s}
.bmtile:hover{box-shadow:var(--shadow);transform:translateY(-1px)}
.bmtile .bmgo{display:flex;align-items:center;gap:10px;padding:11px 12px;flex:1;min-width:0}
.bmtile .tico{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;flex:none;color:var(--tc,var(--accent));background:color-mix(in srgb,var(--tc,var(--accent)) 14%,transparent)}
.bmtile .bmtxt{min-width:0;line-height:1.3}
.bmtile .bmtxt strong{display:block;font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bmtile .bmtxt small{display:block;color:var(--muted2);font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bmtile .bmdel{position:absolute;top:6px;right:8px;color:var(--muted2);font-size:15px;line-height:1;padding:3px;border-radius:7px;opacity:0;transition:.12s}
.bmtile:hover .bmdel{opacity:1}
.bmtile .bmdel:hover{background:var(--err);color:#fff}
.bmtile.add{cursor:pointer;border:1px dashed var(--line);box-shadow:none;color:var(--muted);padding:11px 12px;gap:10px}
.bmtile.add:hover{border-color:var(--accent);color:var(--txt);transform:none}
.today{padding:6px 10px}
.tline{display:flex;align-items:center;gap:12px;padding:9px 6px;border-radius:9px}
.tline:hover{background:var(--panel2)}
.tline .dot{width:8px;height:8px;border-radius:50%;flex:none}
.tline strong{min-width:44px;font-size:12.5px;color:var(--muted)}
.tline .ttl{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.appgrid{grid-template-columns:repeat(auto-fill,minmax(min(100%,150px),1fr));gap:9px}
.app-chip{display:flex;align-items:center;gap:10px;background:var(--panel);border-radius:11px;padding:9px 12px;font-size:13px;font-weight:600;box-shadow:var(--shadow-sm);transition:transform .12s,box-shadow .12s}
.app-chip:hover{box-shadow:var(--shadow);transform:translateY(-1px)}
.app-chip .tico{width:28px;height:28px;border-radius:8px;display:grid;place-items:center;flex:none;color:var(--tc,var(--accent));background:color-mix(in srgb,var(--tc,var(--accent)) 14%,transparent)}

/* Verwaltung: Kennzahlen-Kacheln */
.statgrid{grid-template-columns:repeat(auto-fill,minmax(min(100%,150px),1fr));gap:10px;margin-bottom:6px}
.stat{display:flex;align-items:center;gap:11px;background:var(--panel);border-radius:12px;padding:12px 13px;box-shadow:var(--shadow-sm)}
.stat .tico{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;flex:none;color:var(--tc,var(--accent));background:color-mix(in srgb,var(--tc,var(--accent)) 14%,transparent)}
.stat strong{display:block;font-size:16px;font-weight:700;line-height:1.2}
.stat small{color:var(--muted);font-size:11px}

/* Warteraum (Konto noch nicht freigeschaltet) */
.wr-wrap{min-height:100vh;min-height:100dvh;display:grid;place-items:start center;padding:clamp(16px,4vw,32px);padding-bottom:max(24px,var(--safe-b))}
.wr-col{width:100%;max-width:480px;display:flex;flex-direction:column;gap:14px}
.wr-head{display:flex;align-items:center;gap:12px}
.wr-head .logo{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;color:#fff;background:var(--accent);box-shadow:var(--shadow-sm);flex:none}
.wr-head>div{flex:1;min-width:0;line-height:1.3}
.wr-head strong{display:block;font-size:15px;font-weight:650}
.wr-head small{color:var(--muted)}
.wr-status h2{font-size:17px;font-weight:650;margin-bottom:3px}
.wr-sub{color:var(--muted);font-size:13px;margin-bottom:14px}
.steps{display:flex;flex-direction:column;gap:0}
.step{display:flex;gap:12px;padding:8px 0;position:relative}
.step+.step::before{content:"";position:absolute;left:11px;top:-9px;width:2px;height:16px;background:var(--line)}
.step .sdot{width:24px;height:24px;border-radius:50%;flex:none;display:grid;place-items:center;background:var(--panel2);color:var(--muted2);border:2px solid var(--line)}
.step.done .sdot{background:var(--ok);border-color:var(--ok);color:#fff}
.step.active .sdot{border-color:var(--accent)}
.step.active .sdot::after{content:"";width:8px;height:8px;border-radius:50%;background:var(--accent);animation:wr-pulse 1.6s ease infinite}
@keyframes wr-pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
.step strong{display:block;font-size:13.5px;font-weight:600;line-height:1.3}
.step small{color:var(--muted);font-size:12px}
.step:not(.done):not(.active) strong{color:var(--muted2)}
.wr-chat{display:flex;flex-direction:column;padding:0;overflow:hidden}
.wr-chat-h{display:flex;align-items:center;gap:8px;padding:12px 14px;border-bottom:1px solid var(--line);font-size:13.5px}
.wr-chat .chat-msgs{max-height:240px;min-height:110px}
.wr-empty{color:var(--muted2);font-size:12.5px;text-align:center;padding:16px 8px}

/* Kontakte */
.cgrid{display:grid;gap:var(--gap);grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr))}
.ccard{background:var(--panel);border:1px solid transparent;border-radius:var(--radius);padding:15px;box-shadow:var(--shadow-sm);display:flex;flex-direction:column;gap:10px}
.ccard.me{border-color:color-mix(in srgb,var(--accent) 40%,var(--line));background:color-mix(in srgb,var(--accent) 4%,var(--panel))}
.ccard.invite{cursor:pointer;border-style:dashed;border-color:var(--line);box-shadow:none;color:var(--muted)}
.ccard.invite:hover{border-color:var(--accent);color:var(--txt)}
.ccard.member{cursor:pointer;transition:transform .14s,box-shadow .14s,border-color .14s}
.ccard.member:hover{border-color:color-mix(in srgb,var(--accent) 45%,var(--line));box-shadow:var(--shadow);transform:translateY(-2px)}
.ccard .chead{display:flex;align-items:center;gap:11px}
.ccard .cname{flex:1;min-width:0}
.ccard .cname strong{display:block;font-size:14.5px;font-weight:650;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ccard .cname small{color:var(--muted);font-size:11.5px}
.ccard .cacts{display:flex;gap:2px;color:var(--muted)}
.ccard .cacts a{padding:5px;border-radius:8px;color:var(--muted)}
.ccard .cacts a:hover{background:var(--panel2);color:var(--txt)}
.ccard .cacts a.del:hover{background:var(--err);color:#fff}
.ccard .cbody{display:flex;flex-direction:column;gap:6px}
.ccard .cbody:empty{display:none}
.cline{display:flex;align-items:center;gap:9px;font-size:13px;color:var(--muted);min-width:0}
.cline .ic{color:var(--muted2)}
.cline span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cline.note span{white-space:normal}
a.cline:hover{color:var(--accent)}
a.cline:hover .ic{color:var(--accent)}

/* Chat */
.chat-grid{display:grid;grid-template-columns:230px 1fr;gap:14px;align-items:start}
.peer-col{display:flex;flex-direction:column;gap:8px;min-width:0}
.peer-search .input{width:100%}
.peer-list{display:flex;flex-direction:column;border-radius:var(--radius);overflow:hidden;background:var(--panel);box-shadow:var(--shadow-sm)}
.peer{display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid var(--line);color:var(--muted)}
.peer:last-child{border-bottom:0}
.peer:hover{background:var(--panel2);color:var(--txt)}
.peer.active{background:var(--accent-soft);color:var(--accent);font-weight:600}
.peer .nav-badge{margin-left:auto}
.peer .pname{display:flex;flex-direction:column;min-width:0;line-height:1.25}
.peer .pname small{color:var(--muted2);font-size:10.5px}
.peer .pname span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chat-thread{display:flex;flex-direction:column;background:var(--panel);border:1px solid transparent;box-shadow:var(--shadow-sm);border-radius:var(--radius);height:calc(100dvh - 190px);min-height:min(380px,60dvh)}
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
.cb-toggle.off{opacity:.45;text-decoration:line-through}

/* Mail */
.mail-tabs{display:flex;gap:2px;margin-bottom:14px;flex-wrap:wrap}
.mail-tabs a{padding:6px 12px;border-radius:var(--radius-sm);color:var(--muted);font-size:13px;font-weight:500;display:flex;align-items:center;gap:7px}
.mail-tabs a.active{background:var(--accent-soft);color:var(--accent);font-weight:600}
.mail-list{display:flex;flex-direction:column;border-radius:var(--radius);overflow:hidden;background:var(--panel);box-shadow:var(--shadow-sm)}
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
.file-card{background:var(--panel);border:1px solid transparent;box-shadow:var(--shadow-sm);border-radius:var(--radius);padding:14px;text-align:center;position:relative;transition:border-color .1s}
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
.dropzone{border:1px dashed var(--line);border-radius:var(--radius);padding:13px;text-align:center;color:var(--muted);margin-bottom:16px;transition:.1s;cursor:pointer}
.dropzone.drag{border-color:var(--accent);background:color-mix(in srgb,var(--accent) 6%,transparent)}

/* Tabelle */
.table{width:100%;border-collapse:collapse;border-radius:var(--radius);overflow:hidden;background:var(--panel);box-shadow:var(--shadow-sm)}
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
.auth-card{width:100%;max-width:372px;background:var(--panel);border:1px solid transparent;border-radius:18px;padding:32px;box-shadow:var(--shadow)}
.auth-card .logo{width:50px;height:50px;border-radius:14px;margin:0 auto 18px;display:grid;place-items:center;color:#fff;background:var(--accent);box-shadow:var(--shadow-sm)}
.auth-card h1{text-align:center;font-size:19px;font-weight:650;margin-bottom:4px;letter-spacing:-.2px}
.auth-card .tag{text-align:center;color:var(--muted);margin-bottom:22px;font-size:13px}
.auth-switch{text-align:center;margin-top:16px;color:var(--muted);font-size:13px}
.lang-pick{display:flex;gap:6px;justify-content:center;margin-bottom:14px}
.lang-pick a{font-size:12px;padding:4px 10px;border-radius:99px;color:var(--muted);background:var(--panel2)}
.lang-pick a.on{background:var(--accent);color:#fff}
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
.modal{position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);display:none;place-items:center;z-index:50;padding:clamp(12px,4vw,20px);padding-bottom:max(clamp(12px,4vw,20px),var(--safe-b))}
.modal.open{display:grid}
.modal .box{background:var(--panel);border:0;border-radius:18px;padding:clamp(18px,3vw,24px);width:100%;max-width:500px;box-shadow:var(--shadow);max-height:min(90vh,90dvh);overflow:auto;overscroll-behavior:contain;position:relative;animation:nx-pop .18s ease}
@keyframes nx-pop{from{transform:scale(.97);opacity:.5}to{transform:none;opacity:1}}
/* Auf dem Handy als Bottom-Sheet: gleitet von unten herein, volle
   Breite, oben abgerundet – fühlt sich nach einer nativen App an. */
@media(max-width:640px){
  .modal{place-items:end stretch;padding:0}
  .modal .box{max-width:none;border-radius:20px 20px 0 0;padding-bottom:max(26px,var(--safe-b));max-height:92dvh;animation:nx-sheet .22s ease}
  @keyframes nx-sheet{from{transform:translateY(48px);opacity:.6}to{transform:none;opacity:1}}
}
.modal h3{margin-bottom:18px;font-size:16px;font-weight:650}
.modal-x{position:absolute;top:16px;right:18px;cursor:pointer;color:var(--muted);font-size:20px;line-height:1;width:26px;height:26px;border-radius:8px;display:grid;place-items:center;transition:background .12s}
.modal-x:hover{background:var(--panel2);color:var(--txt)}

/* Responsive – der Grossteil skaliert bereits stufenlos (clamp/fluide
   Raster); die Breakpoints regeln nur noch Umbrüche, die sich nicht
   fluide lösen lassen (Off-Canvas-Menü, Ein-Spalten-Layouts). */
@media(max-width:880px){
  .mail-item .from{width:clamp(84px,26vw,150px)}
  .chat-grid{grid-template-columns:1fr}
  .peer-col{width:100%}
  .peer-list{flex-direction:row;overflow-x:auto;overscroll-behavior-x:contain}
  .peer{border-bottom:0;border-right:1px solid var(--line);white-space:nowrap}
  .chat-thread{height:calc(100dvh - 230px)}
}
/* Kleine Handys (iPhone SE & Co.): Eingabefelder >= 16px verhindern das
   automatische Einzoomen von iOS Safari beim Fokussieren; Formularreihen
   stapeln, damit nichts zusammengequetscht wird. Die Kacheln bleiben
   dank fluidem Raster kompakt (i. d. R. 2-spaltig) statt einspaltig. */
@media(max-width:640px){
  .input,textarea,select{font-size:16px;min-height:44px}
  /* Zweierreihen (Von/Bis, Geburtstag/Adresse ...) bleiben nebeneinander –
     nur volle Reihen mit 3+ Feldern stapeln, damit nichts quetscht */
  .row:has(> :nth-child(3)){flex-direction:column;gap:0}
  .row:has(> :nth-child(3))>*{width:100%;flex:none}
  .row{gap:10px}
  .topbar{flex-wrap:wrap;gap:8px 12px}
  .cal-head h2{min-width:0}
}
@media(max-width:430px){
  .cal .cell{min-height:56px;padding:4px}
  .cal .cell .num{font-size:11px}
  .ev{font-size:10px;padding:1px 4px}
  .mail-item{padding:9px 11px}
  .bubble{max-width:88%}
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

// Modals
function openModal(id){document.getElementById(id)?.classList.add('open');}
function closeModal(id){if(id){document.getElementById(id)?.classList.remove('open');}else{document.querySelectorAll('.modal.open').forEach(m=>m.classList.remove('open'));}}
document.addEventListener('click',e=>{if(e.target.classList&&e.target.classList.contains('modal'))e.target.classList.remove('open');});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});

// Notizen

// Aufgaben

// Kalender

// Kontakte

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
function shareModal(id,name){const f=document.getElementById('shareForm');if(!f)return;f.elements['id'].value=id;document.getElementById('shareName').textContent=name;openModal('shareModal');}

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
  if(document.hidden&&!pc)return;
  fetch('?app=chat&action=poll&peer='+nxPeer+'&after='+nxLast)
    .then(r=>r.json())
    .then(d=>{
      (d.msgs||[]).forEach(m=>{if(m.id<=nxLast)return;nxLast=m.id;chatAppend(m);});
      // Signale strikt nacheinander verarbeiten: ein Offer braucht Zeit
      // (Rückfrage + Kamera), erst danach dürfen ICE-Kandidaten laufen.
      (d.sig||[]).forEach(s=>{sigChain=sigChain.then(()=>onSig(s)).catch(()=>{});});
    })
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

// Videoanruf über Server-Relay: der Sender zeichnet mit MediaRecorder
// kurze Chunks auf und lädt sie zum Webserver hoch; die Gegenseite holt
// sie der Reihe nach ab und spielt sie per MediaSource ab. Läuft damit
// vollständig über PHP/Apache – keine STUN/TURN-Dienste, keine Module.
let callOn=false,localStream=null,nxRing=false,sigChain=Promise.resolve(),nxFast=null;
let rec=null,myMime='',sendSeq=0,recvSeq=0,ms=null,sb=null,sbQueue=[],pullTimer=null;
function pickMime(){
  if(!window.MediaRecorder)return '';
  const list=['video/webm;codecs=vp8,opus','video/webm','video/mp4;codecs=avc1.42E01E,mp4a.40.2','video/mp4'];
  for(const m of list){if(MediaRecorder.isTypeSupported(m))return m;}
  return '';
}
function sendSig(o){
  fetch('?app=chat&action=sig',{method:'POST',keepalive:true,headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'_csrf='+encodeURIComponent(NXCSRF)+'&peer='+nxPeer+'&payload='+encodeURIComponent(JSON.stringify(o))}).catch(()=>{});
}
function callFast(on){
  if(on&&!nxFast)nxFast=setInterval(chatPoll,1000);
  if(!on&&nxFast){clearInterval(nxFast);nxFast=null;}
}
async function callMedia(){
  localStream=await navigator.mediaDevices.getUserMedia({
    video:{width:{ideal:480},height:{ideal:360},frameRate:{ideal:15}},audio:true});
  const v=document.getElementById('vLocal');if(v)v.srcObject=localStream;
}
function startSending(){
  sendSeq=0;
  rec=new MediaRecorder(localStream,{mimeType:myMime,videoBitsPerSecond:350000,audioBitsPerSecond:32000});
  rec.ondataavailable=e=>{
    if(!e.data||!e.data.size||!callOn)return;
    sendSeq++;
    fetch('?app=chat&action=vchunk&peer='+nxPeer+'&seq='+sendSeq+'&_csrf='+encodeURIComponent(NXCSRF),
      {method:'POST',body:e.data}).catch(()=>{});
  };
  rec.start(600);
}
function startReceiving(mime){
  recvSeq=0;sbQueue=[];
  const v=document.getElementById('vRemote');
  if(!mime||!('MediaSource' in window)||!MediaSource.isTypeSupported(mime)){
    alert('Dieser Browser kann den Videostream der Gegenseite nicht abspielen.');return;
  }
  ms=new MediaSource();v.src=URL.createObjectURL(ms);
  ms.addEventListener('sourceopen',()=>{
    try{sb=ms.addSourceBuffer(mime);sb.mode='sequence';sb.addEventListener('updateend',pump);}catch(e){}
  },{once:true});
  pullTimer=setInterval(pullChunks,700);
}
function pump(){
  if(sb&&!sb.updating&&sbQueue.length){try{sb.appendBuffer(sbQueue.shift());}catch(e){}}
  const v=document.getElementById('vRemote');
  try{
    if(v&&v.buffered.length){
      const end=v.buffered.end(v.buffered.length-1);
      if(end-v.currentTime>2.5)v.currentTime=end-0.6;  // nah an "live" bleiben
      if(v.paused)v.play().catch(()=>{});
    }
  }catch(e){}
}
function pullChunks(){
  if(!callOn)return;
  fetch('?app=chat&action=vpull&peer='+nxPeer+'&after='+recvSeq)
    .then(r=>r.json())
    .then(d=>{
      (d.chunks||[]).forEach(c=>{
        recvSeq=c.seq;
        const b=Uint8Array.from(atob(c.d),ch=>ch.charCodeAt(0));
        sbQueue.push(b.buffer);
      });
      pump();
    }).catch(()=>{});
}
async function startCall(){
  if(callOn||nxPeer<=0)return;
  myMime=pickMime();
  if(!myMime){alert('Dieser Browser unterstützt keine Videoaufnahme.');return;}
  try{
    document.getElementById('callbox').classList.add('open');
    await callMedia();callOn=true;
    sendSig({type:'call',mime:myMime});callFast(true);
  }catch(e){alert('Kamera/Mikrofon nicht verfügbar: '+e.message);endCall(false);}
}
async function onSig(s){
  if(!s||!s.type)return;
  try{
    if(s.type==='call'){
      if(callOn||nxRing)return;nxRing=true;
      const who=(typeof NXPEERUSER!=='undefined'&&NXPEERUSER)?' von @'+NXPEERUSER:'';
      const ok=confirm('Eingehender Videoanruf'+who+' – annehmen?');nxRing=false;
      if(!ok){sendSig({type:'bye'});return;}
      myMime=pickMime();
      if(!myMime){sendSig({type:'bye'});alert('Dieser Browser unterstützt keine Videoaufnahme.');return;}
      document.getElementById('callbox').classList.add('open');
      await callMedia();callOn=true;
      sendSig({type:'go',mime:myMime});
      startSending();startReceiving(s.mime||'');callFast(true);
    }else if(s.type==='go'&&callOn&&!rec){
      startSending();startReceiving(s.mime||'');
    }else if(s.type==='bye'){
      endCall(false);
    }
  }catch(e){}
}
function toggleTrack(kind,btn){
  if(!localStream)return;
  localStream.getTracks().forEach(t=>{if(t.kind===kind)t.enabled=!t.enabled;});
  const on=localStream.getTracks().some(t=>t.kind===kind&&t.enabled);
  if(btn)btn.classList.toggle('off',!on);
}
function endCall(notify){
  if(notify===undefined)notify=true;
  if(notify&&nxPeer>0&&callOn)sendSig({type:'bye'});
  callOn=false;
  if(rec){try{rec.stop();}catch(e){} rec=null;}
  if(pullTimer){clearInterval(pullTimer);pullTimer=null;}
  if(localStream){localStream.getTracks().forEach(t=>t.stop());localStream=null;}
  if(ms){try{if(ms.readyState==='open')ms.endOfStream();}catch(e){} ms=null;sb=null;}
  sbQueue=[];callFast(false);
  const v=document.getElementById('vRemote');if(v){v.removeAttribute('src');try{v.load();}catch(e){}}
  document.getElementById('callbox')?.classList.remove('open');
}
window.addEventListener('beforeunload',()=>{if(callOn)endCall();});

/* ------------------------------------------------------------------ *
 *  Leichter Hintergrund-Ping: haelt Chat-/Ticket-Badges und den
 *  Seitentitel aktuell, ohne die Seite neu zu laden.
 * ------------------------------------------------------------------ */
(function(){
  if(!document.querySelector('.main'))return;
  function setBadge(el,n,cls){
    if(!el)return;
    let b=el.querySelector('.rbadge');
    if(n>0){if(!b){b=document.createElement('span');b.className='rbadge'+(cls?' '+cls:'');el.appendChild(b);}b.textContent=n>99?'99+':n;}
    else if(b){b.remove();}
  }
  function ping(){
    if(document.hidden)return;
    fetch('?action=ping').then(r=>r.json()).then(d=>{
      const chatIc=document.querySelector('#appChat .lico');
      setBadge(chatIc,d.chat||0,'');
      document.title=document.title.replace(/^\(\d+\+?\) /,'');
      if(d.chat>0)document.title='('+(d.chat>99?'99+':d.chat)+') '+document.title;
    }).catch(()=>{});
  }
  setInterval(ping,25000);
})();

/* Installations-Gate: auf dem Handy laeuft Nexus nur als installierte
   Web-App. Im mobilen Browser wird ausschliesslich die Anleitung
   gezeigt; Android bekommt zusaetzlich den echten Install-Prompt. */
let nxPwa=null;
window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();nxPwa=e;});
function pwaInstall(){if(nxPwa){nxPwa.prompt();nxPwa=null;}}
(function(){
  const g=document.getElementById('instGate');if(!g)return;
  const ua=navigator.userAgent;
  const ios=/iphone|ipad|ipod/i.test(ua)||(/macintosh/i.test(ua)&&navigator.maxTouchPoints>1);
  const android=/android/i.test(ua);
  const standalone=window.matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
  if((ios||android)&&!standalone){
    g.hidden=false;
    const p=document.getElementById(ios?'igIos':'igAndroid');
    if(p)p.hidden=false;
  }
})();
function storeSearchAll(){var s=document.getElementById('storeSearch');var q=(s&&s.value||'').toLowerCase().trim();
 var folders=document.getElementById('storeFolders');var all=document.getElementById('storeAll');var note=document.getElementById('storeNote');
 if(!all)return;
 if(q){var n=0;all.querySelectorAll('.wacard').forEach(function(c){var ok=c.dataset.name.indexOf(q)>=0;c.style.display=ok?'':'none';if(ok)n++;});
  if(folders)folders.style.display='none';all.style.display='';if(note)note.style.display='none';}
 else{if(folders)folders.style.display='';all.style.display='none';if(note)note.style.display='';}}
function nxLinkOpen(prov,name){var f=document.getElementById('nxLinkForm');if(!f)return;document.getElementById('nxLinkProv').value=prov;
 var l=document.getElementById('nxLinkLbl');if(l)l.textContent=name+'-Konto';f.style.display='';var i=document.getElementById('nxLinkInput');if(i)i.focus();}
function peerFilter(q){q=(q||'').toLowerCase().trim();var n=0,list=document.getElementById('peerList');if(!list)return;list.querySelectorAll('.peer[data-name]').forEach(function(a){var ok=!q||a.dataset.name.indexOf(q)>=0;a.style.display=ok?'':'none';if(ok)n++;});var e=document.getElementById('peerEmpty');if(e)e.style.display=n?'none':'';}
function pwToggle(btn,id){var i=document.getElementById(id);if(!i)return;i.type=(i.type==='password')?'text':'password';}
function pwReveal(id){var e=document.getElementById(id);if(!e)return;if(e.textContent==='••••••••'){e.textContent=e.dataset.v||'';}else{e.textContent='••••••••';}}
function pwCopy(id){var e=document.getElementById(id);if(!e)return;var v=e.dataset.v||'';if(navigator.clipboard){navigator.clipboard.writeText(v).then(()=>{var o=e.parentNode.querySelector('.pw-mini');});}else{var t=document.createElement('textarea');t.value=v;document.body.appendChild(t);t.select();try{document.execCommand('copy');}catch(e){}t.remove();}var b=event&&event.target;if(b){var x=b.textContent;b.textContent='kopiert';setTimeout(()=>{b.textContent=x;},1200);}}
function docCmd(c){document.execCommand(c,false,null);document.getElementById('docEditor').focus();}
function docBlock(t){document.execCommand('formatBlock',false,t);document.getElementById('docEditor').focus();}
function docLink(){var u=prompt('Link-URL (https://…)');if(u){document.execCommand('createLink',false,u);}document.getElementById('docEditor').focus();}
function docSave(){var e=document.getElementById('docEditor');document.getElementById('docBody').value=e?e.innerHTML:'';document.getElementById('docForm').submit();}
function docInit(){var f=document.getElementById('docForm');if(f)f.addEventListener('submit',function(){var e=document.getElementById('docEditor');document.getElementById('docBody').value=e?e.innerHTML:'';});}
function nxSyncType(v){var d=document.getElementById('syncDir');var h=document.getElementById('syncHint');
if(d)d.style.display=(v==='ical')?'none':'';
if(h)h.style.display=(v==='ical')?'':'none';}
if('serviceWorker' in navigator){navigator.serviceWorker.register('?asset=sw').catch(()=>{});}
NXJS;
}

/* ================================================================== *
 *  Layout
 * ================================================================== */
/** Liefert das data-theme-Attribut fuer <html> gemaess Instanz-Theme.
 *  Feste Modi (light/dark) erzwingen ihren Wert; im auto-Modus gewinnt die
 *  explizite Nutzerwahl, sonst bleibt das Attribut leer (das System/der
 *  @media-Block entscheidet). */
function nx_theme_attr(string $userTheme = ''): string {
    $m = defined('NX_THEME_MODE') ? NX_THEME_MODE : 'auto';
    if ($m === 'light' || $m === 'dark') {
        return ' data-theme="' . $m . '"';
    }
    return $userTheme !== '' ? ' data-theme="' . h($userTheme) . '"' : '';
}

function layout_head(array $user, string $activeApp): void {
    sec_headers();
    $theme  = $user['theme'] ?: 'dark';
    $accent = $user['accent'] ?: '#4d7ea8';
    $apps   = nx_apps();
    $meta   = $apps[$activeApp] ?? $apps['home'];

    echo '<!doctype html><html lang="de"' . nx_theme_attr($user['theme'] ?? '') . '><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">';
    echo '<title>' . h($meta['name']) . ' · ' . NX_NAME . '</title>';
    echo '<link rel="stylesheet" href="?asset=css&v=' . NX_VERSION . '">';
    echo '<style>:root{--accent:' . h($accent) . '}</style>';
    echo '<link rel="icon" href="data:image/svg+xml,' . rawurlencode(logo_favicon($accent)) . '">';
    echo '<link rel="manifest" href="?asset=manifest"><link rel="apple-touch-icon" href="' . nx_icon_url() . '">';
    echo '<meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes">';
    echo '<meta name="theme-color" content="#0c0e12">';
    echo '</head><body>';
    echo install_gate();
    echo '<div class="shell"><main class="main">';
}

function layout_topbar(string $title, string $sub = '', string $actions = '', string $back = ''): void {
    $user = current_user();
    // App-Gefuehl: jede Ansicht ausser der Startseite hat einen
    // Zurueck-Knopf (Unterseiten setzen ihr Ziel selbst).
    if ($back === '' && ($GLOBALS['nxApp'] ?? 'home') !== 'home') {
        $back = url('home');
    }
    echo '<div class="topbar">';
    if ($back !== '') {
        echo '<a class="iconbtn" href="' . $back . '" title="Zurück">' . icon('back') . '</a>';
    }
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
    echo '<!doctype html><html lang="' . h(nx_lang()) . '"' . nx_theme_attr() . '><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">';
    echo '<title>' . h($mode === 'register' ? t('auth_register') : t('auth_login')) . ' · ' . NX_NAME . '</title>';
    echo '<link rel="stylesheet" href="?asset=css&v=' . NX_VERSION . '">';
    echo '<link rel="manifest" href="?asset=manifest"><link rel="apple-touch-icon" href="' . nx_icon_url() . '">';
    echo '<meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes">';
    echo '<script>const t=localStorage.getItem("nx_theme");if(t)document.documentElement.setAttribute("data-theme",t);</script>';
    echo '</head><body><div class="auth-wrap"><div class="auth-card">';
    echo '<div class="logo">' . logo_mark(26) . '</div>';

    // Sprachwahl (vor der Kontoerstellung aenderbar)
    $cur = nx_lang();
    echo '<div class="lang-pick">';
    foreach (nx_langs() as $code => $name) {
        echo '<a class="' . ($code === $cur ? 'on' : '') . '" href="?view=' . ($mode === 'register' ? 'register' : 'login') . '&lang=' . $code . '">' . h($name) . '</a>';
    }
    echo '</div>';

    if ($mode === 'register') {
        echo '<h1>' . h(t('auth_register')) . '</h1>';
        echo '<p class="tag">' . h($firstUser ? t('auth_first') : t('auth_approve')) . '</p>';
    } else {
        echo '<h1>' . h(t('auth_login')) . '</h1><p class="tag">&nbsp;</p>';
    }
    if ($err) {
        echo '<div class="alert err">' . h($err) . '</div>';
    }

    echo '<form method="post" action="?action=' . ($mode === 'register' ? 'register' : 'login') . '">';
    echo csrf_field();
    echo '<input type="hidden" name="lang" value="' . h($cur) . '">';
    echo '<div class="field"><label>' . h(t('auth_username')) . '</label><input class="input" name="username" autofocus autocomplete="username" required value="' . h(param('username')) . '"></div>';
    echo '<div class="field"><label>' . h(t('auth_password')) . '</label><input class="input" type="password" name="password" autocomplete="' . ($mode === 'register' ? 'new-password' : 'current-password') . '" required></div>';
    if ($mode === 'register') {
        echo '<div class="field"><label>' . h(t('auth_password2')) . '</label><input class="input" type="password" name="password2" autocomplete="new-password" required></div>';
        echo '<p class="note-line">' . h(t('auth_note')) . '</p>';
    }
    echo '<button class="btn" style="width:100%;justify-content:center" type="submit">' . h($mode === 'register' ? t('auth_do_register') : t('auth_do_login')) . '</button>';
    echo '</form>';

    echo '<div class="auth-switch">' . ($mode === 'register'
        ? h(t('auth_have')) . ' <a href="?view=login">' . h(t('auth_login')) . '</a>'
        : h(t('auth_no')) . ' <a href="?view=register">' . h(t('auth_register')) . '</a>') . '</div>';
    echo '</div></div><script src="?asset=js&v=' . NX_VERSION . '"></script></body></html>';
}

/* Warteraum: eigene Oberflaeche fuer noch nicht freigeschaltete Konten.
   Zeigt Status, Fortschritt und Ticket; Kontakt zum Administrator ist
   eingebettet (nutzt die vorhandenen Chat-Endpunkte). Prueft im
   Hintergrund den Status und laedt bei Freischaltung automatisch. */
function render_waiting(array $u): void {
    sec_headers();
    $t = ticket_for_user((int) $u['id']);
    $admin = first_admin();
    $adminId = $admin ? (int) $admin['id'] : 0;

    $last = 0;
    $msgs = '';
    if ($adminId > 0) {
        $rows = db_all('SELECT * FROM chat WHERE ((sender_id=? AND recipient_id=? AND del_sender=0)
                        OR (sender_id=? AND recipient_id=? AND del_recip=0)) ORDER BY id DESC LIMIT 30',
            [$u['id'], $adminId, $adminId, $u['id']]);
        foreach (array_reverse($rows) as $m) {
            $v = chat_msg_view($m, $u);
            $last = max($last, $v['id']);
            $msgs .= chat_bubble($v);
        }
        db_run('UPDATE chat SET seen=1 WHERE recipient_id=? AND sender_id=? AND seen=0', [$u['id'], $adminId]);
    }

    echo '<!doctype html><html lang="' . h(nx_lang()) . '"' . nx_theme_attr($u['theme'] ?? '') . '><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">';
    echo '<title>Freischaltung · ' . NX_NAME . '</title>';
    echo '<link rel="stylesheet" href="?asset=css&v=' . NX_VERSION . '">';
    echo '<link rel="manifest" href="?asset=manifest"><link rel="apple-touch-icon" href="' . nx_icon_url() . '">';
    echo '</head><body><div class="wr-wrap"><div class="wr-col">';

    echo '<div class="wr-head"><div class="logo">' . logo_mark(24) . '</div>';
    echo '<div><strong>' . h(t('wait_hello')) . ', ' . h($u['display_name'] ?: $u['username']) . '</strong>';
    echo '<small>@' . h($u['username']) . '</small></div>';
    echo '<a class="btn ghost sm" href="?action=logout">' . icon('logout', 15) . ' ' . h(t('wait_logout')) . '</a></div>';

    echo '<div class="panel wr-status">';
    echo '<h2>' . h(t('wait_title')) . '</h2>';
    echo '<p class="wr-sub">' . h(t('wait_sub')) . '</p>';
    echo '<div class="steps">';
    echo '<div class="step done"><span class="sdot">' . icon('check', 12) . '</span><div><strong>' . h(t('wait_s1')) . '</strong><small>' . h(t('wait_s1s')) . '</small></div></div>';
    echo '<div class="step active"><span class="sdot"></span><div><strong>' . h(t('wait_s2')) . '</strong><small>Ticket <span class="mono">' . h($t['code'] ?? '—') . '</span></small></div></div>';
    echo '<div class="step"><span class="sdot"></span><div><strong>' . h(t('wait_s3')) . '</strong><small>' . h(t('wait_s3s')) . '</small></div></div>';
    echo '</div></div>';

    if ($adminId > 0) {
        echo '<div class="panel wr-chat">';
        echo '<div class="wr-chat-h">' . icon('chat', 16) . ' <strong>' . h(t('wait_ask')) . '</strong></div>';
        echo '<div class="chat-msgs" id="chatMsgs">' . ($msgs !== '' ? $msgs : '<p class="wr-empty">Noch keine Nachrichten – schreib bei Fragen einfach hier.</p>') . '</div>';
        echo '<div class="chat-input"><textarea id="chatText" placeholder="Nachricht…" rows="1"></textarea>';
        echo '<button class="btn" onclick="chatSend()" title="Senden">' . icon('send', 18) . '</button></div>';
        echo '</div>';
    }

    echo '</div></div>';
    echo '<script src="?asset=js&v=' . NX_VERSION . '"></script>';
    echo '<script>const NXCSRF=' . json_encode(csrf_token()) . ';';
    if ($adminId > 0) {
        echo 'document.addEventListener("DOMContentLoaded",function(){chatInit(' . $adminId . ',' . $last . ');});';
    }
    // Statusprüfung: bei Freischaltung automatisch in die App wechseln
    echo 'setInterval(function(){if(document.hidden)return;fetch("?action=ping").then(r=>r.json())'
        . '.then(d=>{if(d.status==="active")location.href="?app=home";}).catch(()=>{});},12000);</script>';
    echo '</body></html>';
}

function view_locked(): void {
    sec_headers();
    echo '<!doctype html><html lang="de"' . nx_theme_attr() . '><head><meta charset="utf-8">';
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
    if (param('view') === 'bm') {
        redirect(url('store', ['view' => 'bm']));
    }
    $ava = '<a class="iconbtn homeava" href="' . url('settings') . '" title="Profil">'
        . '<span class="avatar" style="width:30px;height:30px;font-size:13px;border-radius:9px">'
        . h(strtoupper(substr($u['display_name'] ?: $u['username'], 0, 1))) . '</span></a>';
    layout_topbar(NX_NAME, strftime_de(), $ava);

    if ($u['status'] === 'pending') {
        $t = ticket_for_user((int) $u['id']);
        echo '<div class="banner">' . icon('clock', 20) . '<div>';
        echo '<b>Konto wartet auf Freischaltung</b>';
        echo '<p>Ein Administrator prüft deine Registrierung. Bis dahin steht dir der '
            . '<a href="' . url('chat') . '" style="color:var(--accent)">Chat mit dem Administrator</a> zur Verfügung. '
            . 'Ticket: <span class="mono">' . h($t['code'] ?? '—') . '</span></p>';
        echo '</div></div>';
    }

    /* Apps als Launcher-Raster (Icon + Name), Badges live per Ping.
       Es erscheinen nur ueber den App-Store installierte Apps. */
    $inst = nx_installed($u);
    echo '<div class="section-h">' . icon('grid') . ' Apps</div><div class="lgrid">';
    foreach (nx_apps() as $id => $a) {
        if (empty($a['tile']) || !can_access($u, $id)) {
            continue;
        }
        // Store-verwaltete Apps nur zeigen, wenn installiert (admin immer)
        if (in_array($id, nx_store_builtins(), true) && !in_array($id, $inst, true)) {
            continue;
        }
        $badge = '';
        if ($id === 'chat') {
            $unseen = chat_unread((int) $u['id']);
            if ($unseen > 0) {
                $badge = '<span class="rbadge">' . ($unseen > 99 ? '99+' : $unseen) . '</span>';
            }
        }
        if ($id === 'admin') {
            $openT = tickets_open_count();
            if ($openT > 0) {
                $badge = '<span class="rbadge warn">' . $openT . '</span>';
            }
        }
        echo '<a class="lapp"' . ($id === 'chat' ? ' id="appChat"' : '') . ' href="' . url($id) . '">';
        echo '<span class="lico" style="--tc:' . h($a['color']) . '">' . icon($a['icon'], 22) . $badge . '</span>';
        echo '<small>' . h($a['name']) . '</small></a>';
    }
    // Installierte Web-Apps (nativ eingebettet)
    foreach (nx_webapps() as $wid => $w) {
        if (!in_array('web:' . $wid, $inst, true)) {
            continue;
        }
        echo '<a class="lapp" href="' . url('web', ['id' => $wid]) . '">';
        echo '<span class="lico" style="--tc:' . nx_wa_color($w['name']) . '">' . h(strtoupper(mb_substr($w['name'], 0, 1))) . '</span>';
        echo '<small>' . h($w['name']) . '</small></a>';
    }
    // Lesezeichen (unter Erweitert im Store verwaltet) als Kacheln
    if (can_access($u, 'bookmarks')) {
        foreach (db_all('SELECT * FROM bookmarks WHERE user_id=? ORDER BY position,id', [$u['id']]) as $row) {
            $b = dec_row($row['enc'], mk());
            if (!$b) { continue; }
            $host = preg_replace('#^https?://(www\.)?#', '', (string) ($b['url'] ?? ''));
            echo '<a class="lapp" href="' . h($b['url'] ?? '#') . '" target="_blank" rel="noopener" title="' . h($host) . '">';
            echo '<span class="lico" style="--tc:' . h($b['color'] ?? '#4d7ea8') . '">' . h(strtoupper(mb_substr((string) ($b['title'] ?? '?'), 0, 1))) . '</span>';
            echo '<small>' . h($b['title'] ?? '') . '</small></a>';
        }
    }
    // Einziges Plus: App-Store (Apps & Lesezeichen hinzufuegen)
    echo '<a class="lapp lapp-add" href="' . url('store') . '" title="Apps hinzufügen"><span class="lico">' . icon('plus', 24) . '</span><small>Hinzufügen</small></a>';
    echo '</div>';

    /* 2) Heute: nur zeigen, wenn wirklich etwas ansteht. */
    if (can_access($u, 'calendar')) {
        $evs = [];
        foreach (db_all('SELECT * FROM events WHERE user_id=? AND day=?', [$u['id'], date('Y-m-d')]) as $row) {
            $e = dec_row($row['enc'], mk());
            if ($e) {
                $evs[] = $e;
            }
        }
        usort($evs, static function ($a, $b) {
            return strcmp((string) ($a['time'] ?? ''), (string) ($b['time'] ?? ''));
        });
        if ($evs) {
            echo '<div class="section-h">' . icon('calendar') . ' Heute</div><div class="panel today">';
            foreach ($evs as $e) {
                echo '<a class="tline" href="' . url('calendar') . '">';
                echo '<span class="dot" style="background:' . h($e['color'] ?? '#c25a5a') . '"></span>';
                echo '<strong class="mono">' . h(($e['time'] ?? '') !== '' ? substr((string) $e['time'], 0, 5) : '·') . '</strong>';
                echo '<span class="ttl">' . h($e['title'] ?? '') . '</span></a>';
            }
            echo '</div>';
        }
    }

    /* 3) Apps: flaches, kompaktes Chip-Raster – ein Tipp, drin. */
}

/** Kurzes deutsches Datum für die Startseite, ohne Locale-Abhängigkeit. */
function strftime_de(): string {
    $wd = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
    return $wd[(int) date('w')] . ', ' . date('d.m.Y');
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
            $sig = json_decode($pl, true);
            if (is_array($sig) && ($sig['type'] ?? '') === 'bye') {
                call_cleanup($me, $peer);
            }
        }
        echo '{"ok":true}';
        exit;
    }

    // Videoanruf-Relay: Medien-Chunks laufen über den Server (kein
    // STUN/TURN, keine Zusatzmodule). Sender lädt nummerierte Chunks
    // hoch, Empfänger holt sie der Reihe nach ab; abgeholte und
    // veraltete Chunks werden gelöscht.
    if ($action === 'vchunk') {
        csrf_check_get();
        header('Content-Type: application/json');
        $peer = chat_peer_id($u, param_int('peer'));
        $seq  = param_int('seq');
        $data = (string) file_get_contents('php://input');
        if ($peer > 0 && $seq > 0 && $data !== '' && strlen($data) <= 1024 * 1024) {
            $dir = call_dir();
            @file_put_contents($dir . '/c' . $me . '_' . $peer . '_' . $seq . '.bin', $data);
            call_gc($dir);
        }
        echo '{"ok":true}';
        exit;
    }

    if ($action === 'vpull') {
        header('Content-Type: application/json');
        $peer  = chat_peer_id($u, param_int('peer'));
        $after = param_int('after');
        $out = [];
        if ($peer > 0) {
            $dir = call_dir();
            for ($seq = $after + 1, $n = 0; $n < 20; $seq++, $n++) {
                $f = $dir . '/c' . $peer . '_' . $me . '_' . $seq . '.bin';
                if (!is_file($f)) {
                    break;
                }
                $d = (string) file_get_contents($f);
                @unlink($f);
                $out[] = ['seq' => $seq, 'd' => base64_encode($d)];
            }
        }
        echo json_encode(['chunks' => $out]);
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
    } else {
        // Chat ist getrennt vom Ticket-/Freischaltungssystem: hier nur
        // aktive Mitglieder. Ticket-Konversationen laufen im Warteraum
        // (Nutzer) bzw. in der Verwaltung (Admin).
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

    echo '<div class="peer-col">';
    if ($u['status'] !== 'pending') {
        echo '<div class="peer-search"><input class="input" id="peerSearch" placeholder="Nutzer suchen (@name)…" oninput="peerFilter(this.value)"></div>';
    }
    echo '<div class="peer-list" id="peerList">';
    if (!$peers) {
        echo '<div class="peer" style="color:var(--muted2)">Noch keine Mitglieder</div>';
    }
    foreach ($peers as $p) {
        $pid = (int) $p['id'];
        $cls = 'peer' . ($pid === $peer ? ' active' : '');
        $badge = isset($unreadBy[$pid]) ? '<span class="nav-badge">' . $unreadBy[$pid] . '</span>' : '';
        $nameData = mb_strtolower(($p['display_name'] ?: '') . ' @' . $p['username']);
        echo '<a class="' . $cls . '" data-name="' . h($nameData) . '" href="' . url('chat', ['peer' => $pid]) . '">';
        echo '<div class="avatar" style="width:26px;height:26px;font-size:12px">' . h(strtoupper(substr($p['display_name'] ?: $p['username'], 0, 1))) . '</div>';
        echo '<span class="pname"><span>' . h($p['display_name'] ?: $p['username']) . '</span><small>@' . h($p['username']) . '</small></span>' . $badge . '</a>';
    }
    echo '<div class="peer" id="peerEmpty" style="display:none;color:var(--muted2)">Kein Treffer</div>';
    echo '</div></div>';

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
        echo '<small style="color:var(--muted)">@' . h($pu['username']) . '</small>';
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
        echo '<div class="cbar">';
        echo '<button class="btn ghost sm cb-toggle" title="Mikrofon an/aus" onclick="toggleTrack(\'audio\',this)">Mikro</button>';
        echo '<button class="btn ghost sm cb-toggle" title="Kamera an/aus" onclick="toggleTrack(\'video\',this)">Kamera</button>';
        echo '<button class="btn danger sm" onclick="endCall()">Auflegen</button></div></div>';

        echo '<script>const NXCSRF=' . json_encode(csrf_token()) . ',NXPEERUSER=' . json_encode($pu['username']) . ';'
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
        $acc = nx_mail_build();
        $advReq = trim(param('imap_host')) !== '';
        if (!filter_var($acc['email'], FILTER_VALIDATE_EMAIL) || $acc['password'] === '') {
            layout_head($u, 'mail');
            mail_setup_form($acc, $advReq, 'Bitte E-Mail-Adresse und Passwort angeben.');
            layout_foot();
            return;
        }
        if ($acc['imap_host'] === '' || $acc['smtp_host'] === '') {
            // Domain unbekannt -> Erweitert ausklappen (Fall a)
            layout_head($u, 'mail');
            mail_setup_form($acc, true, 'Für diese Adresse sind keine Server bekannt – bitte unten IMAP/SMTP eintragen.');
            layout_foot();
            return;
        }
        // Verbindung pruefen (Fall b: bei Fehler Erweitert ausklappen)
        if (imap_available()) {
            $test = @imap_open(imap_mailbox_str($acc), $acc['username'], $acc['password'], 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
            if (!$test) {
                $msg = imap_last_error() ?: 'unbekannter Fehler';
                imap_errors();
                layout_head($u, 'mail');
                mail_setup_form($acc, true, 'Verbindung fehlgeschlagen (' . h($msg) . '). Bitte Servereinstellungen prüfen.');
                layout_foot();
                return;
            }
            imap_close($test);
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

/** Postfach-Ansicht ohne imap-Erweiterung (Socket-IMAP). Faellt bei
 *  Fehlern sauber zurueck und vermerkt sie in unable.txt. */
function render_mail_socket(array $u, array $acc): void {
    $uid = param_int('uid');
    if ($uid > 0) {
        $raw = nx_imap_raw($acc, $uid);
        if ($raw === null) {
            nx_unable('mail', 'Socket-IMAP-Abruf fehlgeschlagen fuer ' . $acc['imap_host']);
            echo '<div class="alert err">Nachricht konnte nicht geladen werden.</div>';
            return;
        }
        $text = nx_mail_text($raw);
        echo '<a class="btn ghost sm" href="' . url('mail', ['acc' => $acc['id']]) . '" style="margin-bottom:12px">' . icon('back') . ' Zurück</a>';
        echo '<div class="panel"><div class="mail-body">' . linkify(h($text !== '' ? $text : '(kein Textinhalt)')) . '</div></div>';
        return;
    }
    $r = nx_imap_list($acc, 40);
    if (isset($r['err'])) {
        nx_unable('mail', 'Socket-IMAP ' . $r['err'] . ' fuer ' . $acc['imap_host']);
        echo '<div class="alert err">Postfach nicht erreichbar: ' . h($r['err']) . '.</div>';
        return;
    }
    echo '<p class="note-line" style="margin-bottom:10px">Basis-Ansicht ohne <code>imap</code>-Erweiterung.</p>';
    if (!$r['msgs']) {
        echo '<div class="empty">' . icon('mail', 40) . '<h3>Postfach leer</h3></div>';
        return;
    }
    echo '<div class="mail-list">';
    foreach ($r['msgs'] as $m) {
        echo '<a class="mail-item' . ($m['seen'] ? '' : ' unseen') . '" href="' . url('mail', ['acc' => $acc['id'], 'uid' => $m['uid']]) . '">';
        echo '<span class="from">' . h($m['from'] !== '' ? $m['from'] : '—') . '</span>';
        echo '<span class="subj">' . h($m['subject']) . '</span>';
        echo '<span class="date">' . h($m['date'] !== '' ? date('d.m.', strtotime($m['date']) ?: time()) : '') . '</span></a>';
    }
    echo '</div>';
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
        // Anderer Weg: Postfach ueber Socket-IMAP lesen (ohne Erweiterung)
        render_mail_socket($u, $acc);
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

/** Bekannte Anbieter: E-Mail-Domain -> IMAP/SMTP. Fuer alles andere
 *  wird imap./smtp.<domain> geraten. */
function nx_mail_preset(string $email): ?array {
    $at = strrchr($email, '@');
    if ($at === false) {
        return null;
    }
    $d = strtolower(substr($at, 1));
    $map = [
        'gmail.com'    => ['g', 'imap.gmail.com', 'smtp.gmail.com', 465, 'ssl'],
        'googlemail.com'=> ['g', 'imap.gmail.com', 'smtp.gmail.com', 465, 'ssl'],
        'outlook.com'  => ['o', 'outlook.office365.com', 'smtp.office365.com', 587, 'tls'],
        'hotmail.com'  => ['o', 'outlook.office365.com', 'smtp.office365.com', 587, 'tls'],
        'live.com'     => ['o', 'outlook.office365.com', 'smtp.office365.com', 587, 'tls'],
        'live.de'      => ['o', 'outlook.office365.com', 'smtp.office365.com', 587, 'tls'],
        'hotmail.de'   => ['o', 'outlook.office365.com', 'smtp.office365.com', 587, 'tls'],
        'gmx.de'       => ['', 'imap.gmx.net', 'mail.gmx.net', 587, 'tls'],
        'gmx.net'      => ['', 'imap.gmx.net', 'mail.gmx.net', 587, 'tls'],
        'web.de'       => ['', 'imap.web.de', 'smtp.web.de', 587, 'tls'],
        'yahoo.com'    => ['', 'imap.mail.yahoo.com', 'smtp.mail.yahoo.com', 465, 'ssl'],
        'yahoo.de'     => ['', 'imap.mail.yahoo.com', 'smtp.mail.yahoo.com', 465, 'ssl'],
        'icloud.com'   => ['a', 'imap.mail.me.com', 'smtp.mail.me.com', 587, 'tls'],
        'me.com'       => ['a', 'imap.mail.me.com', 'smtp.mail.me.com', 587, 'tls'],
        't-online.de'  => ['', 'secureimap.t-online.de', 'securesmtp.t-online.de', 465, 'ssl'],
        'zoho.com'     => ['', 'imap.zoho.com', 'smtp.zoho.com', 465, 'ssl'],
        'yandex.com'   => ['', 'imap.yandex.com', 'smtp.yandex.com', 465, 'ssl'],
        'fastmail.com' => ['', 'imap.fastmail.com', 'smtp.fastmail.com', 465, 'ssl'],
    ];
    if (isset($map[$d])) {
        [$note, $ih, $sh, $sp, $se] = $map[$d];
        return ['imap_host' => $ih, 'imap_port' => 993, 'imap_enc' => 'ssl',
                'smtp_host' => $sh, 'smtp_port' => $sp, 'smtp_enc' => $se,
                'known' => true, 'apppw' => in_array($note, ['g', 'o', 'a'], true)];
    }
    return ['imap_host' => 'imap.' . $d, 'imap_port' => 993, 'imap_enc' => 'ssl',
            'smtp_host' => 'smtp.' . $d, 'smtp_port' => 465, 'smtp_enc' => 'ssl',
            'known' => false, 'apppw' => false];
}

/** Baut einen Konto-Datensatz aus E-Mail/Passwort (+ optionalen
 *  Erweitert-Feldern). Erweitert schlaegt Auto-Erkennung. */
function nx_mail_build(): array {
    $email = trim(param('email'));
    $pre   = nx_mail_preset($email) ?? [];
    $adv   = trim(param('imap_host')) !== '';
    $ih = $adv ? trim(param('imap_host')) : ($pre['imap_host'] ?? '');
    $sh = $adv ? trim(param('smtp_host')) : ($pre['smtp_host'] ?? '');
    return [
        'label'         => trim(param('label')) !== '' ? trim(param('label')) : $email,
        'email'         => $email,
        'imap_host'     => $ih,
        'imap_port'     => $adv ? (param_int('imap_port', 993) ?: 993) : ($pre['imap_port'] ?? 993),
        'imap_enc'      => $adv && in_array(param('imap_enc'), ['ssl','tls','notls'], true) ? param('imap_enc') : ($pre['imap_enc'] ?? 'ssl'),
        'smtp_host'     => $sh,
        'smtp_port'     => $adv ? (param_int('smtp_port', 465) ?: 465) : ($pre['smtp_port'] ?? 465),
        'smtp_enc'      => $adv ? (param('smtp_enc') === 'tls' ? 'tls' : 'ssl') : ($pre['smtp_enc'] ?? 'ssl'),
        'username'      => trim(param('username')) !== '' ? trim(param('username')) : $email,
        'password'      => param('password'),
        'validate_cert' => param('validate_cert') ? 1 : 0,
    ];
}

function mail_setup_form(array $pre = [], bool $adv = false, string $err = ''): void {
    layout_topbar('Mail-Konto verbinden', 'E-Mail-Adresse & Passwort genügen');
    echo '<div class="panel" style="max-width:560px;margin:0 auto">';
    if ($err !== '') {
        echo '<div class="alert err">' . h($err) . '</div>';
    }
    echo '<form method="post" action="?app=mail&action=acc_save">' . csrf_field();
    echo '<div class="field"><label>E-Mail-Adresse</label><input class="input" type="email" name="email" required value="' . h($pre['email'] ?? '') . '" autocomplete="username"></div>';
    echo '<div class="field"><label>Passwort</label><input class="input" type="password" name="password" required autocomplete="current-password"></div>';
    if (!empty($pre['apppw'])) {
        echo '<p class="note-line">Für dieses Postfach ist ein <b>App-Passwort</b> nötig (normales Konto-Passwort wird vom Anbieter abgelehnt).</p>';
    }
    echo '<div class="field"><label>Bezeichnung (optional)</label><input class="input" name="label" value="' . h($pre['label'] ?? '') . '" placeholder="z. B. Privat"></div>';
    echo '<details class="adv"' . ($adv ? ' open' : '') . '><summary>Erweiterte Servereinstellungen</summary><div class="adv-in">';
    echo '<div class="row"><div class="field" style="flex:2"><label>IMAP-Server</label><input class="input" name="imap_host" placeholder="imap.example.com" value="' . h($pre['imap_host'] ?? '') . '"></div>';
    echo '<div class="field"><label>Port</label><input class="input" name="imap_port" value="' . h((string) ($pre['imap_port'] ?? 993)) . '"></div>';
    echo '<div class="field"><label>Verschl.</label><select name="imap_enc">';
    foreach (['ssl'=>'SSL','tls'=>'TLS','notls'=>'Keine'] as $k=>$v) { echo '<option value="'.$k.'"'.((($pre['imap_enc']??'ssl')===$k)?' selected':'').'>'.$v.'</option>'; }
    echo '</select></div></div>';
    echo '<div class="row"><div class="field" style="flex:2"><label>SMTP-Server</label><input class="input" name="smtp_host" placeholder="smtp.example.com" value="' . h($pre['smtp_host'] ?? '') . '"></div>';
    echo '<div class="field"><label>Port</label><input class="input" name="smtp_port" value="' . h((string) ($pre['smtp_port'] ?? 465)) . '"></div>';
    echo '<div class="field"><label>Verschl.</label><select name="smtp_enc">';
    foreach (['ssl'=>'SSL','tls'=>'STARTTLS'] as $k=>$v) { echo '<option value="'.$k.'"'.((($pre['smtp_enc']??'ssl')===$k)?' selected':'').'>'.$v.'</option>'; }
    echo '</select></div></div>';
    echo '<div class="field"><label>Benutzername (falls abweichend)</label><input class="input" name="username" value="' . h($pre['username'] ?? '') . '" placeholder="meist die E-Mail-Adresse"></div>';
    echo '<label style="display:flex;gap:8px;align-items:center;font-size:13px;color:var(--muted)"><input type="checkbox" name="validate_cert" value="1"' . (!empty($pre['validate_cert']) ? ' checked' : '') . ' style="width:auto"> TLS-Zertifikat streng prüfen</label>';
    echo '</div></details>';
    echo '<button class="btn" type="submit" style="width:100%;justify-content:center;margin-top:14px">Verbinden</button>';
    echo '<p class="note-line" style="margin-top:12px">Nachrichten werden <b>nicht gespeichert</b> – nur die Übersicht und die geöffnete Mail werden bei Bedarf direkt geladen.</p>';
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
    if (param('view') === 'edit') {
        $nid = param_int('id');
        $n = null;
        if ($nid > 0) {
            $row = db_one('SELECT * FROM notes WHERE id=? AND user_id=?', [$nid, $u['id']]);
            $n = $row ? dec_row($row['enc'], mk()) : null;
            if (!$n) {
                redirect(url('notes'));
            }
        }
        layout_topbar($nid > 0 ? 'Notiz bearbeiten' : 'Neue Notiz', '', '', url('notes'));
        echo '<div class="panel" style="max-width:560px">';
        echo '<form method="post" action="?app=notes&action=save">' . csrf_field();
        echo '<input type="hidden" name="id" value="' . ($nid > 0 ? $nid : '') . '">';
        echo '<div class="field"><label>Titel</label><input class="input" name="title" value="' . h($n['title'] ?? '') . '"></div>';
        echo '<div class="field"><label>Inhalt</label><textarea name="body" required' . ($nid > 0 ? '' : ' autofocus') . '>' . h($n['body'] ?? '') . '</textarea></div>';
        echo '<div class="field"><label>Farbe</label>' . color_swatch('color', (string) ($n['color'] ?? '#b3893f')) . '</div>';
        echo '<button class="btn" style="width:100%;justify-content:center">Speichern</button></form>';
        if ($nid > 0) {
            echo '<a class="btn ghost danger-txt" style="width:100%;justify-content:center;margin-top:9px" href="?app=notes&action=del&id=' . $nid . '&_csrf=' . csrf_token() . '" onclick="return confirm(\'Notiz löschen?\')">' . icon('trash', 15) . ' Löschen</a>';
        }
        echo '</div>';
        return;
    }
    layout_topbar('Notizen', count($rows) . ' gespeichert',
        '<a class="btn" href="?app=notes&view=edit">' . icon('plus') . ' Neue Notiz</a>');

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
                echo '<a href="#" onclick="document.getElementById(\'noteShareForm\').elements[\'id\'].value=' . $row['id'] . ';openModal(\'noteShareModal\');return false" title="Teilen">' . icon('share', 15) . '</a>';
            }
            echo '<a href="?app=notes&view=edit&id=' . $row['id'] . '" title="Bearbeiten">' . icon('edit', 15) . '</a>';
            echo '<a href="?app=notes&action=del&id=' . $row['id'] . '&_csrf=' . csrf_token() . '" onclick="return confirm(\'Notiz löschen?\')" title="Löschen">' . icon('trash', 15) . '</a>';
            echo '</div></div></div>';
        }
        echo '</div>';
    } else {
        echo '<div class="empty">' . icon('note', 40) . '<h3>Noch keine Notizen</h3></div>';
    }



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

    if (param('view') === 'new') {
        layout_topbar('Neue Aufgabe', '', '', url('tasks'));
        echo '<div class="panel" style="max-width:560px">';
        echo '<form method="post" action="?app=tasks&action=add">' . csrf_field();
        echo '<div class="field"><label>Aufgabe</label><input class="input" name="title" required autofocus></div>';
        echo '<div class="row"><div class="field"><label>Fällig</label><input class="input" type="date" name="due"></div>';
        echo '<div class="field"><label>Priorität</label><select name="priority"><option value="1">Normal</option><option value="2">Hoch</option><option value="0">Niedrig</option></select></div></div>';
        echo '<button class="btn" style="width:100%;justify-content:center">Hinzufügen</button>';
        echo '</form></div>';
        return;
    }
    layout_topbar('Aufgaben', count($open) . ' offen',
        '<a class="btn" href="?app=tasks&view=new">' . icon('plus') . ' Aufgabe</a>');

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

    if (param('view') === 'edit') {
        $eid = param_int('id');
        $ev  = null;
        $evDay = param('day');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $evDay)) {
            $evDay = date('Y-m-d');
        }
        if ($eid > 0) {
            $row = db_one('SELECT * FROM events WHERE id=? AND user_id=?', [$eid, $u['id']]);
            $ev  = $row ? dec_row($row['enc'], mk()) : null;
            if (!$ev) {
                redirect(url('calendar'));
            }
            $evDay = (string) $row['day'];
        }
        layout_topbar($eid > 0 ? 'Termin bearbeiten' : 'Neuer Termin', '', '', url('calendar', ['m' => substr($evDay, 0, 7)]));
        echo '<div class="panel" style="max-width:560px">';
        echo '<form method="post" action="?app=calendar&action=save">' . csrf_field();
        echo '<input type="hidden" name="id" value="' . ($eid > 0 ? $eid : '') . '">';
        echo '<div class="field"><label>Titel</label><input class="input" name="title" required value="' . h($ev['title'] ?? '') . '"' . ($eid > 0 ? '' : ' autofocus') . '></div>';
        echo '<div class="field"><label>Datum</label><input class="input" type="date" name="day" required value="' . h($evDay) . '"></div>';
        echo '<div class="row"><div class="field"><label>Von</label><input class="input" type="time" name="time" value="' . h($ev['time'] ?? '') . '"></div>';
        echo '<div class="field"><label>Bis</label><input class="input" type="time" name="end_time" value="' . h($ev['end'] ?? '') . '"></div></div>';
        echo '<div class="field"><label>Beschreibung</label><textarea name="description" style="min-height:56px">' . h($ev['desc'] ?? '') . '</textarea></div>';
        echo '<div class="field"><label>Farbe</label>' . color_swatch('color', (string) ($ev['color'] ?? '#c25a5a')) . '</div>';
        echo '<button class="btn" style="width:100%;justify-content:center">Speichern</button></form>';
        if ($eid > 0) {
            echo '<form method="post" action="?app=calendar&action=del" onsubmit="return confirm(\'Termin löschen?\')" style="margin-top:9px">' . csrf_field();
            echo '<input type="hidden" name="id" value="' . $eid . '">';
            echo '<button class="btn ghost danger-txt" style="width:100%;justify-content:center">' . icon('trash', 15) . ' Löschen</button></form>';
        }
        echo '</div>';
        return;
    }

    layout_topbar('Kalender', 'Termine & Ereignisse',
        '<a class="btn" href="?app=calendar&view=edit&day=' . date('Y-m-d') . '">' . icon('plus') . ' Termin</a>');

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
        echo '<div class="cell' . ($day === $today ? ' today' : '') . '" onclick="location.href=\'?app=calendar&view=edit&day=' . $day . '\'">';
        echo '<span class="num">' . $d . '</span>';
        foreach (($byDay[$day] ?? []) as $e) {
            echo '<a class="ev" style="--ec:' . h($e['color'] ?? '#c25a5a') . '" title="' . h($e['title'] ?? '') . '"'
                . ' href="?app=calendar&view=edit&id=' . $e['id'] . '" onclick="event.stopPropagation()">';
            if (($e['time'] ?? '') !== '') {
                echo '<small>' . h(substr($e['time'], 0, 5)) . '</small>';
            }
            echo h($e['title'] ?? '') . '</a>';
        }
        echo '</div>';
    }
    $filled = $startDow + $daysIn;
    for ($i = 0; $i < (7 - $filled % 7) % 7; $i++) {
        echo '<div class="cell out"><span class="num">' . ($i + 1) . '</span></div>';
    }
    echo '</div>';


}

/* ================================================================== *
 *  APP: KONTAKTE
 * ================================================================== */
/** Betrachter-Zustand fuer ein geteiltes Feld setzen. */
function nx_state_set(int $viewerId, int $ownerId, string $field, int $seen, int $manual): void {
    db_run('INSERT INTO share_state (viewer_id,owner_id,field,seen_version,manual) VALUES (?,?,?,?,?)
            ON CONFLICT(viewer_id,owner_id,field) DO UPDATE SET seen_version=excluded.seen_version, manual=excluded.manual',
        [$viewerId, $ownerId, $field, $seen, $manual]);
}

function handle_contacts(array $u, string $action): void {
    /* ---- Freundschaftsanfrage per Nexus-Benutzername senden ---- */
    if ($action === 'freq_send') {
        csrf_check_post();
        $name = trim(param('username'));
        $target = db_one("SELECT * FROM users WHERE username=? AND status!='suspended'", [$name]);
        if (!$target || (int) $target['id'] === (int) $u['id']) {
            flash('Kein Nutzer mit diesem Benutzernamen.', 'err');
            redirect(url('contacts', ['view' => 'people']));
        }
        $tid = (int) $target['id'];
        if (nx_is_friend((int) $u['id'], $tid)) {
            flash('Ihr seid bereits verbunden.', 'err');
            redirect(url('contacts', ['view' => 'people']));
        }
        // Gegenanfrage vorhanden? -> direkt annehmen
        $rev = db_one("SELECT * FROM friend_requests WHERE from_id=? AND to_id=? AND status='pending'", [$tid, $u['id']]);
        if ($rev) {
            nx_friend_accept($u, (int) $rev['id']);
            redirect(url('contacts', ['view' => 'people']));
        }
        db_run("INSERT OR IGNORE INTO friend_requests (from_id,to_id,status) VALUES (?,?,'pending')", [$u['id'], $tid]);
        db_run("UPDATE friend_requests SET status='pending' WHERE from_id=? AND to_id=?", [$u['id'], $tid]);
        nx_history_add((int) $u['id'], 'friend', 'u' . $tid, 'request', null, $target['username'], (int) $u['id'], 'Anfrage gesendet');
        flash('Freundschaftsanfrage an @' . h($target['username']) . ' gesendet.');
        redirect(url('contacts', ['view' => 'people']));
    }
    if ($action === 'freq_accept') {
        csrf_check_post();
        nx_friend_accept($u, param_int('id'));
        redirect(url('contacts', ['view' => 'people']));
    }
    if ($action === 'freq_decline') {
        csrf_check_post();
        $req = db_one('SELECT * FROM friend_requests WHERE id=? AND to_id=?', [param_int('id'), $u['id']]);
        if ($req) {
            db_run("UPDATE friend_requests SET status='declined' WHERE id=?", [$req['id']]);
            nx_history_add((int) $u['id'], 'friend', 'u' . $req['from_id'], 'request', null, null, (int) $u['id'], 'Anfrage abgelehnt');
        }
        flash('Anfrage abgelehnt.');
        redirect(url('contacts', ['view' => 'people']));
    }
    if ($action === 'unfriend') {
        csrf_check_post();
        $fid = param_int('uid');
        db_run('DELETE FROM friends WHERE user_id=? AND friend_id=?', [$u['id'], $fid]);
        db_run('DELETE FROM friends WHERE user_id=? AND friend_id=?', [$fid, $u['id']]);
        // eigene Freigaben an diese Person einfrieren (einmal geteiltes bleibt beim Empfaenger)
        db_run('UPDATE shares SET active=0 WHERE owner_id=? AND viewer_id=?', [$u['id'], $fid]);
        nx_history_add((int) $u['id'], 'friend', 'u' . $fid, 'friend', 'verbunden', null, (int) $u['id'], 'Freundschaft beendet');
        flash('Freundschaft beendet.');
        redirect(url('contacts', ['view' => 'people']));
    }
    /* ---- Welche eigenen Felder teile ich mit diesem Freund? ---- */
    if ($action === 'share_save') {
        csrf_check_post();
        $fid = param_int('uid');
        if (!nx_is_friend((int) $u['id'], $fid)) { redirect(url('contacts')); }
        $vals = nx_self_values($u);
        $picked = array_keys(array_filter($_POST['share'] ?? [], static fn($v) => $v === '1' || $v === 'on'));
        foreach (nx_share_fields($u) as $f => $lbl) {
            $want = in_array($f, $picked, true);
            $cur  = db_one('SELECT active FROM shares WHERE owner_id=? AND viewer_id=? AND field=?', [$u['id'], $fid, $f]);
            if ($want) {
                nx_share_set($u, $fid, $f, (string) ($vals[$f] ?? ''), true);
            } elseif ($cur && (int) $cur['active'] === 1) {
                // deaktivieren: einmal Geteiltes bleibt beim Empfaenger, nur keine Updates mehr
                db_run('UPDATE shares SET active=0 WHERE owner_id=? AND viewer_id=? AND field=?', [$u['id'], $fid, $f]);
                nx_history_add((int) $u['id'], 'share', 'u' . $fid, $f, null, null, (int) $u['id'], 'Freigabe gestoppt');
            }
        }
        flash('Freigabe aktualisiert.');
        redirect(url('contacts', ['view' => 'friend', 'uid' => $fid]));
    }
    /* ---- Betrachter loest einen Vorschlag auf (uebernehmen / behalten) ---- */
    if ($action === 'share_ack') {
        csrf_check_post();
        $oid = param_int('uid');
        $field = preg_replace('/[^a-z:]/', '', param('field'));
        $mode = param('mode');
        $shared = nx_shared_from($u, $oid);
        if (isset($shared[$field])) {
            $s = $shared[$field];
            if ($mode === 'adopt') {
                nx_apply_shared_to_contact($u, $oid, $field, $s['value']);
                nx_state_set((int) $u['id'], $oid, $field, $s['version'], 0);
                nx_history_add((int) $u['id'], 'share', 'u' . $oid, $field, null, $s['value'], $oid, 'Vorschlag übernommen');
            } else { // keep
                nx_state_set((int) $u['id'], $oid, $field, $s['version'], 1);
                nx_history_add((int) $u['id'], 'share', 'u' . $oid, $field, null, null, (int) $u['id'], 'eigenen Wert behalten');
            }
        }
        redirect(url('contacts', ['view' => 'friend', 'uid' => $oid]));
    }
    if ($action === 'save') {
        csrf_check_post();
        $id = param_int('id');
        $name = trim(param('name'));
        if ($name === '') {
            redirect(url('contacts'));
        }
        $birthday = trim(param('birthday'));
        if ($birthday !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
            $birthday = '';
        }
        $old = null;
        $nxUid = 0;
        if ($id) {
            $row = db_one('SELECT * FROM contacts WHERE id=? AND user_id=?', [$id, $u['id']]);
            if ($row) { $old = dec_row($row['enc'], mk()); $nxUid = (int) $row['nx_uid']; }
        }
        $new = [
            'name'     => $name,
            'email'    => trim(param('email')),
            'phone'    => trim(param('phone')),
            'birthday' => $birthday,
            'address'  => trim(param('address')),
            'note'     => trim(param('note')),
            'me'       => param('me') === '1' ? 1 : 0,
        ];
        $enc = enc_row($new, (string) mk());
        if ($id) {
            db_run('UPDATE contacts SET enc=? WHERE id=? AND user_id=?', [$enc, $id, $u['id']]);
        } else {
            db_run('INSERT INTO contacts (user_id,enc) VALUES (?,?)', [$u['id'], $enc]);
        }
        // Verlauf je geaendertem Feld
        foreach (['name', 'email', 'phone', 'birthday', 'address', 'note'] as $f) {
            $ov = (string) ($old[$f] ?? '');
            $nv = (string) ($new[$f] ?? '');
            if ($ov !== $nv) {
                nx_history_add((int) $u['id'], 'contact', ($id ? (string) $id : 'new'), $f, $ov, $nv, (int) $u['id'],
                    (int) ($new['me'] ?? 0) === 1 ? 'eigene Karte' : 'Kontakt bearbeitet');
            }
        }
        // Eigene Karte geaendert -> Freunde bekommen Aktualisierung (mit '!')
        if ((int) ($new['me'] ?? 0) === 1) {
            nx_share_broadcast($u);
        }
        // Verknuepfter Freund-Kontakt: manuelle Ueberschreibung markieren
        if ($nxUid > 0) {
            $shared = nx_shared_from($u, $nxUid);
            foreach (['name', 'email', 'phone', 'birthday', 'address', 'note'] as $f) {
                if (!isset($shared[$f])) { continue; }
                $manual = ((string) ($new[$f] ?? '') !== (string) $shared[$f]['value']) ? 1 : 0;
                nx_state_set((int) $u['id'], $nxUid, $f, $shared[$f]['version'], $manual);
            }
        }
        flash('Kontakt gespeichert.');
        redirect(param('back') === 'profile' ? url('settings') : url('contacts'));
    }
    if ($action === 'del') {
        csrf_check_get();
        db_run('DELETE FROM contacts WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
        flash('Kontakt gelöscht.');
        redirect(url('contacts'));
    }
}

/** Anfrage annehmen: beide werden Freunde, verknuepfte Kontakte anlegen. */
function nx_friend_accept(array $u, int $reqId): void {
    $req = db_one("SELECT * FROM friend_requests WHERE id=? AND to_id=? AND status='pending'", [$reqId, $u['id']]);
    if (!$req) { flash('Anfrage nicht gefunden.', 'err'); return; }
    $from = user_by_id((int) $req['from_id']);
    if (!$from) { return; }
    db_run("UPDATE friend_requests SET status='accepted' WHERE id=?", [$reqId]);
    db_run('INSERT OR IGNORE INTO friends (user_id,friend_id) VALUES (?,?)', [$u['id'], $from['id']]);
    db_run('INSERT OR IGNORE INTO friends (user_id,friend_id) VALUES (?,?)', [$from['id'], $u['id']]);
    nx_link_contact($u, $from);                          // mein Kontakt fuer den Absender
    $fromRow = user_by_id((int) $from['id']);
    if ($fromRow) { nx_link_contact($fromRow, $u); }     // dessen Kontakt fuer mich
    nx_history_add((int) $u['id'], 'friend', 'u' . $from['id'], 'friend', null, $from['username'], (int) $from['id'], 'Freundschaft bestätigt');
    nx_history_add((int) $from['id'], 'friend', 'u' . $u['id'], 'friend', null, $u['username'], (int) $u['id'], 'Freundschaft bestätigt');
    flash('Ihr seid jetzt verbunden – lege in @' . h($from['username']) . ' fest, was du teilst.');
}

/** Legt (falls noetig) einen mit einem Nexus-Konto verknuepften Kontakt an. */
function nx_link_contact(array $owner, array $friend): void {
    $exists = db_one('SELECT id FROM contacts WHERE user_id=? AND nx_uid=?', [$owner['id'], $friend['id']]);
    if ($exists) { return; }
    $card = enc_row([
        'name'  => $friend['display_name'] ?: $friend['username'],
        'email' => '', 'phone' => '', 'birthday' => '', 'address' => '',
        'note'  => '', 'me' => 0,
    ], (string) mk());
    db_run('INSERT INTO contacts (user_id,enc,nx_uid) VALUES (?,?,?)', [$owner['id'], $card, $friend['id']]);
}

/** Schreibt einen freigegebenen Wert in den verknuepften Kontakt. */
function nx_apply_shared_to_contact(array $viewer, int $ownerId, string $field, string $value): void {
    if (strpos($field, 'acct:') === 0) { return; } // Konto-Felder sind kein Kontaktfeld
    $row = db_one('SELECT * FROM contacts WHERE user_id=? AND nx_uid=?', [$viewer['id'], $ownerId]);
    if (!$row) { return; }
    $c = dec_row($row['enc'], mk()) ?: [];
    $c[$field] = $value;
    db_run('UPDATE contacts SET enc=? WHERE id=?', [enc_row($c, (string) mk()), $row['id']]);
}

function contact_edit_page(array $u, string $back): void {
    $id = param_int('id');
    $c = null;
    if ($id > 0) {
        $row = db_one('SELECT * FROM contacts WHERE id=? AND user_id=?', [$id, $u['id']]);
        if ($row) {
            $c = dec_row($row['enc'], mk());
        }
        if (!$c) {
            redirect(url('contacts'));
        }
    }
    $isMe = $c !== null ? (int) ($c['me'] ?? 0) === 1 : param('me') === '1';
    $title = $isMe ? 'Meine Karte' : ($id > 0 ? 'Kontakt bearbeiten' : 'Neuer Kontakt');
    layout_topbar($title, '', '', $back);

    echo '<div class="panel" style="max-width:560px">';
    echo '<form method="post" action="?app=contacts&action=save">' . csrf_field();
    echo '<input type="hidden" name="id" value="' . ($id > 0 ? $id : '') . '">';
    echo '<input type="hidden" name="me" value="' . ($isMe ? '1' : '') . '">';
    echo '<input type="hidden" name="back" value="' . (param('back') === 'profile' ? 'profile' : '') . '">';
    $preName  = $c['name'] ?? ($isMe ? ($u['display_name'] ?: $u['username']) : '');
    $preMail  = $c['email'] ?? ($isMe ? $u['email'] : '');
    echo '<div class="field"><label>Name</label><input class="input" name="name" required value="' . h((string) $preName) . '"></div>';
    echo '<div class="row"><div class="field"><label>E-Mail</label><input class="input" type="email" name="email" value="' . h((string) $preMail) . '"></div>';
    echo '<div class="field"><label>Telefon</label><input class="input" type="tel" name="phone" value="' . h($c['phone'] ?? '') . '"></div></div>';
    echo '<div class="row"><div class="field"><label>Geburtstag</label><input class="input" type="date" name="birthday" value="' . h($c['birthday'] ?? '') . '"></div>';
    echo '<div class="field"><label>Adresse</label><input class="input" name="address" value="' . h($c['address'] ?? '') . '"></div></div>';
    echo '<div class="field"><label>Notiz</label><textarea name="note" style="min-height:56px">' . h($c['note'] ?? '') . '</textarea></div>';
    echo '<button class="btn" style="width:100%;justify-content:center">Speichern</button></form>';
    if ($id > 0) {
        echo '<a class="btn ghost danger-txt" style="width:100%;justify-content:center;margin-top:9px" '
            . 'href="?app=contacts&action=del&id=' . $id . '&_csrf=' . csrf_token() . '" '
            . 'onclick="return confirm(\'' . ($isMe ? 'Meine Karte' : 'Kontakt') . ' löschen?\')">' . icon('trash', 15) . ' Löschen</a>';
    }
    echo '</div>';
}

function render_contacts(array $u): void {
    $view = param('view');
    if ($view === 'edit') { contact_edit_page($u, url('contacts')); return; }
    if ($view === 'people')  { render_people($u); return; }
    if ($view === 'friend')  { render_friend($u); return; }
    if ($view === 'history') { render_history($u); return; }

    $rows = [];
    foreach (db_all('SELECT * FROM contacts WHERE user_id=?', [$u['id']]) as $row) {
        $c = dec_row($row['enc'], mk());
        if ($c) {
            $c['id'] = (int) $row['id'];
            $c['nx_uid'] = (int) $row['nx_uid'];
            $rows[] = $c;
        }
    }
    usort($rows, static function ($a, $b) {
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    $mine = null;
    $friends = [];
    $others = [];
    foreach ($rows as $c) {
        if ($mine === null && (int) ($c['me'] ?? 0) === 1) {
            $mine = $c;
        } elseif (($c['nx_uid'] ?? 0) > 0) {
            $friends[] = $c;
        } else {
            $others[] = $c;
        }
    }

    $reqIn = (int) db_scalar("SELECT COUNT(*) FROM friend_requests WHERE to_id=? AND status='pending'", [$u['id']]);
    $peopleBtn = '<a class="btn ghost" href="?app=contacts&view=people">' . icon('users', 15) . ' Personen'
        . ($reqIn > 0 ? ' <span class="nav-badge warn">' . $reqIn . '</span>' : '') . '</a>';
    layout_topbar('Kontakte', count($others) + count($friends) . ' Einträge',
        $peopleBtn . ' <a class="btn" href="?app=contacts&view=edit">' . icon('plus') . ' Kontakt</a>');

    $card = static function (array $c, bool $isMe): string {
        $edit = '?app=contacts&view=edit&id=' . $c['id'];
        $out  = '<div class="ccard' . ($isMe ? ' me' : '') . '">';
        $out .= '<a class="chead" href="' . $edit . '">';
        $out .= '<span class="avatar">' . h(strtoupper(mb_substr((string) ($c['name'] ?? '?'), 0, 1))) . '</span>';
        $out .= '<span class="cname"><strong>' . h($c['name'] ?? '') . '</strong>';
        $out .= '<small>' . ($isMe ? 'Meine Karte · Antippen zum Bearbeiten' : 'Antippen zum Bearbeiten') . '</small></span>';
        $out .= '<span class="cacts">' . icon('edit', 15) . '</span></a>';
        $out .= '<div class="cbody">';
        if (($c['email'] ?? '') !== '') {
            $out .= '<a class="cline" href="mailto:' . h($c['email']) . '">' . icon('mail', 15) . '<span>' . h($c['email']) . '</span></a>';
        }
        if (($c['phone'] ?? '') !== '') {
            $tel = preg_replace('/[^0-9+]/', '', (string) $c['phone']);
            $out .= '<a class="cline" href="tel:' . h($tel) . '">' . icon('chat', 15) . '<span class="mono">' . h($c['phone']) . '</span></a>';
        }
        if (($c['birthday'] ?? '') !== '') {
            $ts = strtotime((string) $c['birthday']);
            $out .= '<span class="cline">' . icon('calendar', 15) . '<span>' . ($ts ? date('d.m.Y', $ts) : h($c['birthday'])) . '</span></span>';
        }
        if (($c['address'] ?? '') !== '') {
            $out .= '<span class="cline">' . icon('pin', 15) . '<span>' . h($c['address']) . '</span></span>';
        }
        if (($c['note'] ?? '') !== '') {
            $out .= '<span class="cline note">' . icon('note', 15) . '<span>' . h($c['note']) . '</span></span>';
        }
        return $out . '</div></div>';
    };

    echo '<div class="section-h">' . icon('user') . ' Meine Karte</div>';
    if ($mine !== null) {
        echo '<div class="cgrid">' . $card($mine, true) . '</div>';
    } else {
        echo '<a class="ccard invite" href="?app=contacts&view=edit&me=1">'
            . '<span class="chead"><span class="avatar">' . icon('plus', 16) . '</span>'
            . '<span class="cname"><strong>Meine Karte anlegen</strong><small>Eigene E-Mail, Telefon &amp; Co. hinterlegen</small></span></span></a>';
    }

    if ($friends) {
        echo '<div class="section-h">' . icon('users') . ' Freunde <a class="sh-link" href="?app=contacts&view=people">verwalten</a></div><div class="cgrid">';
        foreach ($friends as $c) {
            $uid = (int) $c['nx_uid'];
            $pend = nx_share_pending($u, $uid);
            echo '<a class="ccard friend" href="?app=contacts&view=friend&uid=' . $uid . '">';
            echo '<span class="chead"><span class="avatar">' . h(strtoupper(mb_substr((string) ($c['name'] ?? '?'), 0, 1))) . '</span>';
            echo '<span class="cname"><strong>' . h($c['name'] ?? '') . ($pend > 0 ? ' <span class="bang" title="' . $pend . ' Aktualisierung(en)">!</span>' : '') . '</strong>';
            echo '<small>' . ($pend > 0 ? $pend . ' neue Aktualisierung(en)' : 'Geteilte Infos ansehen') . '</small></span>';
            echo '<span class="cacts">' . icon('chevR', 15) . '</span></span></a>';
        }
        echo '</div>';
    }

    if ($others) {
        echo '<div class="section-h">' . icon('user') . ' Kontakte</div><div class="cgrid">';
        foreach ($others as $c) {
            echo $card($c, false);
        }
        echo '</div>';
    } elseif (!$friends) {
        echo '<div class="empty">' . icon('user', 40) . '<h3>Keine Kontakte</h3><p>Lege mit „+ Kontakt" einen Eintrag an – oder finde unter <a href="?app=contacts&view=people">Personen</a> Freunde per Benutzername.</p></div>';
    }

    echo '<div class="mlist settings-w" style="margin-top:16px"><a class="mrow" href="?app=contacts&view=history">'
        . '<span class="mic" style="--mc:#8a7fb0">' . icon('note', 16) . '</span>'
        . '<span class="mlabel"><strong>Verlauf</strong><small>Änderungen an Kontakten, Freigaben &amp; Konten</small></span>'
        . '<span class="chev">' . icon('chevR', 15) . '</span></a></div>';
}

/* ---- Personen: Freunde per Nexus-Benutzername finden & verwalten ---- */
function render_people(array $u): void {
    layout_topbar('Personen', 'Freunde per Benutzername', '', url('contacts'));
    echo '<div class="panel" style="max-width:560px">';
    echo '<form method="post" action="?app=contacts&action=freq_send" class="people-search">' . csrf_field();
    echo '<input class="input" name="username" placeholder="Nexus-Benutzername …" autocomplete="off" required>';
    echo '<button class="btn">' . icon('plus', 15) . ' Anfrage</button></form>';
    echo '<p class="note-line" style="margin:8px 0 0">Du brauchst nur den Benutzernamen. Nach dem Bestätigen entscheidet jede Seite selbst, welche Infos sie teilt.</p></div>';

    // Eingehende Anfragen
    $in = db_all("SELECT * FROM friend_requests WHERE to_id=? AND status='pending' ORDER BY id DESC", [$u['id']]);
    if ($in) {
        echo '<div class="section-h">' . icon('users') . ' Anfragen an dich</div><div class="mlist" style="max-width:560px">';
        foreach ($in as $r) {
            $from = user_by_id((int) $r['from_id']);
            if (!$from) { continue; }
            echo '<div class="mrow static"><span class="mic" style="--mc:#4a9d6f">' . icon('user', 16) . '</span>';
            echo '<span class="mlabel"><strong>' . h($from['display_name'] ?: $from['username']) . '</strong><small>@' . h($from['username']) . '</small></span>';
            echo '<span style="display:flex;gap:6px">';
            echo '<form method="post" action="?app=contacts&action=freq_accept" style="margin:0">' . csrf_field() . '<input type="hidden" name="id" value="' . $r['id'] . '"><button class="btn sm">Annehmen</button></form>';
            echo '<form method="post" action="?app=contacts&action=freq_decline" style="margin:0">' . csrf_field() . '<input type="hidden" name="id" value="' . $r['id'] . '"><button class="btn ghost sm">Ablehnen</button></form>';
            echo '</span></div>';
        }
        echo '</div>';
    }

    // Ausgehende Anfragen
    $out = db_all("SELECT * FROM friend_requests WHERE from_id=? AND status='pending' ORDER BY id DESC", [$u['id']]);
    if ($out) {
        echo '<div class="section-h">' . icon('send') . ' Gesendet</div><div class="mlist" style="max-width:560px">';
        foreach ($out as $r) {
            $to = user_by_id((int) $r['to_id']);
            if (!$to) { continue; }
            echo '<div class="mrow static"><span class="mic" style="--mc:#b3893f">' . icon('user', 16) . '</span>';
            echo '<span class="mlabel"><strong>@' . h($to['username']) . '</strong><small>Wartet auf Bestätigung</small></span></div>';
        }
        echo '</div>';
    }

    // Freunde
    $fr = nx_friends($u);
    echo '<div class="section-h">' . icon('users') . ' Freunde</div>';
    if ($fr) {
        echo '<div class="mlist" style="max-width:560px">';
        foreach ($fr as $f) {
            $pend = nx_share_pending($u, (int) $f['id']);
            echo '<a class="mrow" href="?app=contacts&view=friend&uid=' . (int) $f['id'] . '"><span class="mic" style="--mc:#8a7fb0">' . icon('user', 16) . '</span>';
            echo '<span class="mlabel"><strong>' . h($f['display_name'] ?: $f['username']) . ($pend > 0 ? ' <span class="bang">!</span>' : '') . '</strong><small>@' . h($f['username']) . '</small></span>';
            echo '<span class="chev">' . icon('chevR', 15) . '</span></a>';
        }
        echo '</div>';
    } else {
        echo '<p style="color:var(--muted);max-width:560px">Noch keine Freunde – sende oben eine Anfrage.</p>';
    }
}

/* ---- Freund-Detail: geteilte Infos, eigene Freigabe, Verlauf ---- */
function render_friend(array $u, int $uid = 0): void {
    $uid = $uid ?: param_int('uid');
    if (!nx_is_friend((int) $u['id'], $uid)) { redirect(url('contacts', ['view' => 'people'])); }
    $f = user_by_id($uid);
    if (!$f) { redirect(url('contacts', ['view' => 'people'])); }
    $name = $f['display_name'] ?: $f['username'];
    $crow = db_one('SELECT id FROM contacts WHERE user_id=? AND nx_uid=?', [$u['id'], $uid]);
    $chat = '<a class="btn ghost sm" href="' . url('chat', ['peer' => $uid]) . '">' . icon('send', 15) . ' Nachricht</a>';
    layout_topbar($name, '@' . $f['username'], $chat, url('contacts', ['view' => 'people']));

    $fields = nx_share_fields($u);
    $labels = ['name' => 'Name', 'email' => 'E-Mail', 'phone' => 'Telefon', 'birthday' => 'Geburtstag', 'address' => 'Adresse', 'note' => 'Notiz'];

    // Was @user mit MIR teilt
    $shared = nx_shared_from($u, $uid);
    echo '<div class="section-h">' . icon('users') . ' Von ' . h($name) . ' geteilt</div>';
    if ($shared) {
        echo '<div class="panel" style="max-width:560px">';
        foreach ($shared as $field => $s) {
            $lbl = $labels[$field] ?? (nx_providers()[substr($field, 5)]['name'] ?? $field) . '-Konto';
            $val = $s['value'];
            if ($field === 'birthday' && $val !== '') { $ts = strtotime($val); if ($ts) { $val = date('d.m.Y', $ts); } }
            echo '<div class="frow' . ($s['changed'] ? ' changed' : '') . '">';
            echo '<div class="fmeta"><small>' . h($lbl) . ($s['manual'] ? ' · <em>eigener Wert gesetzt</em>' : '') . ($s['active'] ? '' : ' · eingefroren') . '</small>';
            echo '<strong>' . ($val === '' ? '—' : h($val)) . '</strong></div>';
            if ($s['changed']) {
                echo '<div class="fack"><span class="bang">!</span> Aktualisiert';
                echo '<form method="post" action="?app=contacts&action=share_ack" style="margin:0">' . csrf_field()
                    . '<input type="hidden" name="uid" value="' . $uid . '"><input type="hidden" name="field" value="' . h($field) . '">'
                    . '<button class="btn sm" name="mode" value="adopt">Übernehmen</button> '
                    . '<button class="btn ghost sm" name="mode" value="keep">Behalten</button></form>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p style="color:var(--muted);max-width:560px">' . h($name) . ' teilt noch keine Infos mit dir.</p>';
    }

    // Was ICH mit @user teile
    $myShares = [];
    foreach (db_all('SELECT field,active FROM shares WHERE owner_id=? AND viewer_id=?', [$u['id'], $uid]) as $r) {
        $myShares[$r['field']] = (int) $r['active'];
    }
    echo '<div class="section-h">' . icon('send') . ' Was du teilst</div>';
    echo '<form method="post" action="?app=contacts&action=share_save" class="panel" style="max-width:560px">' . csrf_field();
    echo '<input type="hidden" name="uid" value="' . $uid . '">';
    echo '<p class="note-line" style="margin:0 0 10px">Einmal geteiltes bleibt beim Empfänger – Abwählen stoppt nur künftige Aktualisierungen.</p>';
    $vals = nx_self_values($u);
    foreach ($fields as $fk => $flbl) {
        $on = isset($myShares[$fk]) && $myShares[$fk] === 1;
        $frozen = isset($myShares[$fk]) && $myShares[$fk] === 0;
        $preview = (string) ($vals[$fk] ?? '');
        if ($fk === 'birthday' && $preview !== '') { $ts = strtotime($preview); if ($ts) { $preview = date('d.m.Y', $ts); } }
        echo '<label class="shrow"><input type="checkbox" name="share[' . h($fk) . ']" value="1"' . ($on ? ' checked' : '') . '>';
        echo '<span class="shlbl"><strong>' . h($flbl) . '</strong><small>' . ($preview === '' ? '—' : h(mb_strimwidth($preview, 0, 40, '…'))) . ($frozen ? ' · eingefroren' : '') . '</small></span></label>';
    }
    echo '<button class="btn" style="width:100%;justify-content:center;margin-top:10px">Freigabe speichern</button></form>';

    // Verwaltung + Verlauf
    echo '<div class="mlist settings-w">';
    if ($crow) {
        echo '<a class="mrow" href="?app=contacts&view=edit&id=' . (int) $crow['id'] . '"><span class="mic" style="--mc:#4a8ca0">' . icon('edit', 16) . '</span><span class="mlabel"><strong>Kontaktkarte bearbeiten</strong><small>Eigene Werte manuell setzen</small></span><span class="chev">' . icon('chevR', 15) . '</span></a>';
    }
    echo '<a class="mrow" href="?app=contacts&view=history&ref=u' . $uid . '"><span class="mic" style="--mc:#8a7fb0">' . icon('note', 16) . '</span><span class="mlabel"><strong>Verlauf</strong><small>Alle Änderungen mit ' . h($name) . '</small></span><span class="chev">' . icon('chevR', 15) . '</span></a>';
    echo '</div>';
    echo '<form method="post" action="?app=contacts&action=unfriend" style="max-width:560px;margin-top:10px" onsubmit="return confirm(\'Freundschaft mit ' . h($name) . ' beenden?\')">' . csrf_field()
        . '<input type="hidden" name="uid" value="' . $uid . '"><button class="btn ghost danger-txt" style="width:100%;justify-content:center">Freundschaft beenden</button></form>';
}

/* ---- Verlauf (global oder gefiltert) ---- */
function render_history(array $u): void {
    $ref = preg_replace('/[^a-z0-9]/', '', param('ref'));
    $back = $ref !== '' ? url('contacts', ['view' => 'friend', 'uid' => (int) ltrim($ref, 'u')]) : url('contacts');
    layout_topbar('Verlauf', $ref !== '' ? 'Gefiltert' : 'Alle Änderungen', '', $back);
    $rows = nx_history($u, '', $ref, 120);
    if (!$rows) {
        echo '<div class="empty">' . icon('note', 40) . '<h3>Noch kein Verlauf</h3><p>Änderungen an Kontakten, Freigaben und Konten erscheinen hier.</p></div>';
        return;
    }
    $scopeLbl = ['contact' => 'Kontakt', 'share' => 'Freigabe', 'account' => 'Konto', 'friend' => 'Freundschaft'];
    $fieldLbl = ['name' => 'Name', 'email' => 'E-Mail', 'phone' => 'Telefon', 'birthday' => 'Geburtstag', 'address' => 'Adresse', 'note' => 'Notiz', 'link' => 'Verknüpfung', 'request' => 'Anfrage', 'friend' => 'Freundschaft'];
    echo '<div class="mlist" style="max-width:640px">';
    foreach ($rows as $r) {
        $sc = $scopeLbl[$r['scope']] ?? $r['scope'];
        $fl = $fieldLbl[$r['field']] ?? ($r['field'] !== '' ? $r['field'] : '');
        $chg = '';
        if ($r['old'] !== '' || $r['new'] !== '') {
            $chg = h(mb_strimwidth($r['old'], 0, 24, '…')) . ' → <strong>' . h(mb_strimwidth($r['new'], 0, 24, '…')) . '</strong>';
        }
        $when = h(date('d.m.Y H:i', strtotime((string) $r['created_at'] . ' UTC') ?: time()));
        echo '<div class="mrow static"><span class="mic" style="--mc:#7a828e">' . icon('note', 15) . '</span>';
        echo '<span class="mlabel"><strong>' . h($sc) . ($fl !== '' ? ' · ' . h($fl) : '') . '</strong>';
        echo '<small>' . ($r['note'] !== '' ? h($r['note']) . ' · ' : '') . $when . ($chg !== '' ? '<br>' . $chg : '') . '</small></span></div>';
    }
    echo '</div>';
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

    if ($action === 'raw') {
        // Inline-Auslieferung (Bild/Audio/Video/Text) fuer die Vorschau
        $node = vfs_node($u, param_int('id'));
        if ($node && (int) $node['is_dir'] === 0) {
            $data = vfs_content($node, $u);
            if ($data !== null) {
                header('Content-Type: ' . nx_mime(vfs_name($node, $u)));
                header('Content-Disposition: inline');
                header('Content-Length: ' . strlen($data));
                header('X-Content-Type-Options: nosniff');
                // Neutralisiert aktive Inhalte (z. B. Script in SVG/HTML),
                // falls die Datei direkt aufgerufen wird.
                header("Content-Security-Policy: default-src 'none'; sandbox; style-src 'unsafe-inline'; img-src 'self' data:; media-src 'self'");
                header('X-Frame-Options: DENY');
                echo $data;
                exit;
            }
        }
        http_response_code(404);
        exit('Nicht gefunden.');
    }

    if ($action === 'mktext') {
        csrf_check_post();
        $dir = vfs_dir($u, $dirId);
        $name = trim(param('name'));
        if ($name === '') { $name = 'Notiz.txt'; }
        if (!preg_match('/\.[A-Za-z0-9]{1,6}$/', $name)) { $name .= '.txt'; }
        if ($dir) {
            $err = vfs_store($u, $dirId, $name, (string) param('body'));
            flash($err ?? 'Textdatei gespeichert.', $err ? 'err' : 'ok');
        }
        redirect(url('files', ['d' => $dirId]));
    }

    if ($action === 'savetext') {
        csrf_check_post();
        $node = vfs_node($u, param_int('id'));
        if ($node && (int) $node['is_dir'] === 0 && empty($node['sealed'])
            && preg_match('/^[0-9a-f]{32}$/', (string) $node['blob'])) {
            $body = (string) param('body');
            $old = (int) $node['size'];
            if (quota_can_store((int) $u['id'], max(0, strlen($body) - $old))) {
                $path = NX_FILES . '/' . $u['id'] . '/' . $node['blob'] . '.bin';
                if (@file_put_contents($path, aead_enc_raw($body, (string) mk())) !== false) {
                    db_run('UPDATE vfs SET size=? WHERE id=? AND user_id=?', [strlen($body), $node['id'], $u['id']]);
                    flash('Gespeichert.');
                }
            } else {
                flash('Speicher voll.', 'err');
            }
        }
        redirect(url('files', ['open' => param_int('id')]));
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

    // Neue Textdatei anlegen
    if (param('new') === 'text') {
        layout_topbar('Neue Textdatei', '', '', url('files', ['d' => $dirId]));
        echo '<div class="panel" style="max-width:760px"><form method="post" action="?app=files&action=mktext">' . csrf_field();
        echo '<input type="hidden" name="d" value="' . $dirId . '">';
        echo '<div class="field"><label>Dateiname</label><input class="input" name="name" value="Notiz.txt" required></div>';
        echo '<div class="field"><label>Inhalt</label><textarea name="body" style="min-height:320px;font-family:var(--mono)" autofocus></textarea></div>';
        echo '<button class="btn" style="width:100%;justify-content:center">Speichern</button></form></div>';
        return;
    }

    // Datei-Vorschau/Player/Editor
    if ($oid = param_int('open')) {
        $node = vfs_node($u, $oid);
        if (!$node || (int) $node['is_dir'] === 1) {
            redirect(url('files', ['d' => $dirId]));
        }
        $name = vfs_name($node, $u);
        $kind = nx_file_kind($name);
        $raw  = '?app=files&action=raw&id=' . $oid;
        $dl   = '<a class="btn ghost sm" href="?app=files&action=dl&id=' . $oid . '">' . icon('download', 15) . ' Herunterladen</a>';
        layout_topbar($name, human_size((int) $node['size']), $dl, url('files', ['d' => (int) $node['parent_id']]));
        echo '<div class="panel" style="max-width:900px">';
        if ($kind === 'audio') {
            echo '<div class="viewer-media"><span class="tico" style="width:56px;height:56px;--tc:var(--accent);margin:0 auto 14px">' . icon('note', 26) . '</span>';
            echo '<div class="vtitle">' . h($name) . '</div>';
            echo '<audio controls autoplay style="width:100%;margin-top:12px" src="' . $raw . '"></audio></div>';
        } elseif ($kind === 'video') {
            echo '<div class="embed-16x9"><video controls src="' . $raw . '" style="position:absolute;inset:0;width:100%;height:100%"></video></div>';
        } elseif ($kind === 'image') {
            echo '<img src="' . $raw . '" alt="' . h($name) . '" style="max-width:100%;border-radius:10px;display:block;margin:0 auto">';
        } elseif ($kind === 'text' && empty($node['sealed'])) {
            $content = (string) vfs_content($node, $u);
            echo '<form method="post" action="?app=files&action=savetext">' . csrf_field();
            echo '<input type="hidden" name="id" value="' . $oid . '">';
            echo '<textarea name="body" style="min-height:420px;font-family:var(--mono);width:100%">' . h($content) . '</textarea>';
            echo '<button class="btn" style="margin-top:10px">Speichern</button></form>';
        } elseif ($kind === 'pdf') {
            echo '<p style="color:var(--muted)">PDF – ' . $dl . '</p>';
        } else {
            echo '<div class="empty">' . icon('file', 40) . '<h3>Keine Vorschau</h3><p>' . $dl . '</p></div>';
        }
        echo '</div>';
        return;
    }

    layout_topbar('Dateien', 'Frei: ' . human_size(quota_remaining((int) $u['id'])),
        '<form method="post" action="?app=files&action=mkdir" style="display:flex;gap:8px">' . csrf_field()
        . '<input type="hidden" name="d" value="' . $dirId . '">'
        . '<input class="input" name="name" placeholder="Neuer Ordner" style="width:150px;padding:7px 11px">'
        . '<button class="btn ghost sm">' . icon('plus') . '</button></form>'
        . '<a class="btn ghost sm" href="?app=files&new=text&d=' . $dirId . '" title="Neue Textdatei">' . icon('note', 15) . '</a>');

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
        $k = nx_file_kind($f['_name']);
        $fico = ['audio'=>'note','video'=>'video','image'=>'file','text'=>'note','pdf'=>'file'][$k] ?? 'file';
        echo '<a href="?app=files&open=' . $f['id'] . '">';
        echo '<div class="fi">' . icon($fico, 30) . '</div><div class="fn">' . h($f['_name']) . '</div><div class="fs">' . human_size((int) $f['size']) . '</div></a>';
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
        redirect(url('store', ['view' => 'bm']));
    }
    if ($action === 'del') {
        csrf_check_get();
        db_run('DELETE FROM bookmarks WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
        flash('Lesezeichen gelöscht.');
        redirect(url('store', ['view' => 'bm']));
    }
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
        redirect(url('settings', ['view' => 'admin']));
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
    redirect(url('settings', ['view' => 'admin']));
}

/** Admin-GUI als Abschnitt (ohne eigene Topbar) – lebt in den
 *  Einstellungen. Alle aktuellen Admin-Funktionen und Platz fuer
 *  kuenftige (weitere admin_sections-Bloecke). */
function admin_gui(array $u): void {
    $st = db_one("SELECT COUNT(*) t, SUM(status='active') a, SUM(status='pending') p, SUM(status='suspended') x FROM users") ?: [];
    $storage = (int) db_scalar('SELECT COALESCE(SUM(size),0) FROM vfs')
             + (int) db_scalar('SELECT COALESCE(SUM(bytes),0) FROM chat');
    $open = tickets_open_count();

    echo '<div class="grid statgrid">';
    foreach ([
        ['users',  '#4d7ea8', (string) (int) ($st['t'] ?? 0), 'Nutzer gesamt'],
        ['check',  '#4a9d6f', (string) (int) ($st['a'] ?? 0), 'Aktiv'],
        ['ticket', '#b3893f', (string) (int) ($st['p'] ?? 0), 'Wartend'],
        ['ban',    '#c25a5a', (string) (int) ($st['x'] ?? 0), 'Gesperrt'],
        ['inbox',  '#8a7fb0', (string) $open, 'Offene Tickets'],
        ['db',     '#4a8ca0', human_size($storage), 'Speicher belegt'],
    ] as [$ic, $col, $val, $lbl]) {
        echo '<div class="stat"><span class="tico" style="--tc:' . $col . '">' . icon($ic, 17) . '</span>';
        echo '<div><strong>' . h($val) . '</strong><small>' . h($lbl) . '</small></div></div>';
    }
    echo '</div>';

    admin_sections($u);
}

function admin_sections(array $u): void {
    $pending = db_all("SELECT * FROM users WHERE status='pending' ORDER BY id");
    if ($pending) {
        echo '<div class="section-h">' . icon('ticket') . ' Freischaltungen (' . count($pending) . ')</div>';
        echo '<div class="wrap-scroll"><table class="table"><thead><tr><th>Benutzer</th><th>E-Mail</th><th>Ticket</th><th>Registriert</th><th></th></tr></thead><tbody>';
        foreach ($pending as $p) {
            $t = db_one('SELECT code FROM tickets WHERE user_id=? ORDER BY id DESC LIMIT 1', [$p['id']]);
            echo '<tr><td><strong>' . h($p['username']) . '</strong></td><td>' . ($p['email'] !== '' ? h($p['email']) : '<span style="color:var(--muted2)">—</span>') . '</td>';
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
        echo '<td><strong>' . h($r['username']) . '</strong>' . ($self ? ' <span class="chip">du</span>' : '') . ($r['email'] !== '' ? '<br><small style="color:var(--muted)">' . h($r['email']) . '</small>' : '') . '</td>';
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
 *  Externer Sync (Kalender/Kontakte) – CalDAV/CardDAV & iCal-URL.
 *  Reines PHP (curl/streams), keine Zusatzmodule. Richtung push|pull|
 *  both pro Konto. Google/iCloud/Nextcloud-Kalender per geheimer
 *  iCal-URL (Pull ohne OAuth); CalDAV/CardDAV mit App-Passwort.
 * ================================================================== */
function nx_sync_accounts(array $u): array {
    $out = [];
    foreach (db_all('SELECT * FROM sync_accounts WHERE user_id=? ORDER BY id', [$u['id']]) as $r) {
        $c = dec_row($r['enc'], mk());
        if ($c) {
            $c['id'] = (int) $r['id'];
            $c['last_sync'] = $r['last_sync'];
            $out[] = $c;
        }
    }
    return $out;
}

function nx_sync_account(array $u, int $id): ?array {
    foreach (nx_sync_accounts($u) as $a) {
        if ($a['id'] === $id) {
            return $a;
        }
    }
    return null;
}

/** Einfacher HTTP-Client (curl bevorzugt, sonst Streams). */
function nx_http(string $method, string $url, array $o = []): array {
    $user = $o['user'] ?? '';
    $pass = $o['pass'] ?? '';
    $body = $o['body'] ?? '';
    $hdr  = $o['headers'] ?? [];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => $hdr,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => NX_NAME . '/' . NX_VERSION,
        ]);
        if ($user !== '') {
            curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
        }
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ['code' => $code, 'body' => (string) $resp, 'err' => $err];
    }
    $opts = ['http' => ['method' => $method, 'timeout' => 25, 'ignore_errors' => true,
        'header' => implode("\r\n", $hdr)]];
    if ($user !== '') {
        $opts['http']['header'] .= "\r\nAuthorization: Basic " . base64_encode($user . ':' . $pass);
    }
    if ($body !== '') {
        $opts['http']['content'] = $body;
    }
    $resp = @file_get_contents($url, false, stream_context_create($opts));
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $mm)) {
        $code = (int) $mm[1];
    }
    return ['code' => $code, 'body' => (string) $resp, 'err' => $resp === false ? 'Verbindung fehlgeschlagen' : ''];
}

/* ---- iCalendar ---- */
function nx_ical_unfold(string $t): string {
    return preg_replace("/\r\n[ \t]/", '', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $t)));
}

function nx_ical_unesc(string $v): string {
    return str_replace(['\\n', '\\N', '\\,', '\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $v);
}

function nx_ical_esc(string $v): string {
    return str_replace(['\\', "\n", ',', ';'], ['\\\\', '\\n', '\\,', '\;'], $v);
}

/** iCal-Text -> Liste von Terminen [uid,title,day,time,end,desc]. */
function nx_ical_parse(string $text): array {
    $text = nx_ical_unfold($text);
    $events = [];
    $cur = null;
    foreach (explode("\r\n", $text) as $line) {
        if ($line === 'BEGIN:VEVENT') {
            $cur = [];
            continue;
        }
        if ($line === 'END:VEVENT') {
            if ($cur !== null && isset($cur['day'])) {
                $events[] = $cur;
            }
            $cur = null;
            continue;
        }
        if ($cur === null || strpos($line, ':') === false) {
            continue;
        }
        [$key, $val] = explode(':', $line, 2);
        $name = strtoupper(explode(';', $key)[0]);
        if ($name === 'UID') {
            $cur['uid'] = $val;
        } elseif ($name === 'SUMMARY') {
            $cur['title'] = nx_ical_unesc($val);
        } elseif ($name === 'DESCRIPTION') {
            $cur['desc'] = nx_ical_unesc($val);
        } elseif ($name === 'DTSTART') {
            [$d, $t] = nx_ical_dt($val);
            $cur['day'] = $d;
            $cur['time'] = $t;
        } elseif ($name === 'DTEND') {
            [, $t] = nx_ical_dt($val);
            $cur['end'] = $t;
        }
    }
    return $events;
}

/** DTSTART-Wert -> [Y-m-d, H:i]. Unterstuetzt Datum und Datum-Zeit. */
function nx_ical_dt(string $v): array {
    $v = trim($v);
    if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})/', $v, $m)) {
        $ts = strtotime("$m[1]-$m[2]-$m[3] $m[4]:$m[5]" . (substr($v, -1) === 'Z' ? ' UTC' : ''));
        return [date('Y-m-d', $ts), date('H:i', $ts)];
    }
    if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $v, $m)) {
        return ["$m[1]-$m[2]-$m[3]", ''];
    }
    return [date('Y-m-d'), ''];
}

/** Termin -> VEVENT-Block. */
function nx_ical_build_event(array $e, string $uid): string {
    $day = str_replace('-', '', (string) ($e['day'] ?? date('Y-m-d')));
    $t = ($e['time'] ?? '') !== '' ? 'T' . str_replace(':', '', $e['time']) . '00' : '';
    $dt = $day . $t;
    $out  = "BEGIN:VEVENT\r\nUID:" . $uid . "\r\n";
    $out .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
    $out .= ($t !== '' ? "DTSTART:" : "DTSTART;VALUE=DATE:") . $dt . "\r\n";
    if (($e['end'] ?? '') !== '' && $t !== '') {
        $out .= "DTEND:" . $day . 'T' . str_replace(':', '', $e['end']) . "00\r\n";
    }
    $out .= "SUMMARY:" . nx_ical_esc((string) ($e['title'] ?? '')) . "\r\n";
    if (($e['desc'] ?? '') !== '') {
        $out .= "DESCRIPTION:" . nx_ical_esc((string) $e['desc']) . "\r\n";
    }
    return $out . "END:VEVENT\r\n";
}

function nx_ical_wrap(string $inner): string {
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//" . NX_NAME . "//DE\r\n" . $inner . "END:VCALENDAR\r\n";
}

/* ---- vCard (Kontakte) ---- */
function nx_vcard_parse(string $text): array {
    $text = nx_ical_unfold($text);
    $cards = [];
    $cur = null;
    foreach (explode("\r\n", $text) as $line) {
        if (strtoupper($line) === 'BEGIN:VCARD') {
            $cur = [];
            continue;
        }
        if (strtoupper($line) === 'END:VCARD') {
            if ($cur !== null && ($cur['name'] ?? '') !== '') {
                $cards[] = $cur;
            }
            $cur = null;
            continue;
        }
        if ($cur === null || strpos($line, ':') === false) {
            continue;
        }
        [$key, $val] = explode(':', $line, 2);
        $name = strtoupper(explode(';', $key)[0]);
        if ($name === 'UID') {
            $cur['uid'] = $val;
        } elseif ($name === 'FN') {
            $cur['name'] = nx_ical_unesc($val);
        } elseif ($name === 'EMAIL' && !isset($cur['email'])) {
            $cur['email'] = trim($val);
        } elseif ($name === 'TEL' && !isset($cur['phone'])) {
            $cur['phone'] = trim($val);
        } elseif ($name === 'BDAY') {
            [$d] = nx_ical_dt(str_replace('-', '', $val));
            $cur['birthday'] = $d;
        } elseif ($name === 'ADR' && !isset($cur['address'])) {
            $cur['address'] = trim(str_replace(';', ' ', nx_ical_unesc($val)));
        } elseif ($name === 'NOTE') {
            $cur['note'] = nx_ical_unesc($val);
        }
    }
    return $cards;
}

function nx_vcard_build(array $c, string $uid): string {
    $out  = "BEGIN:VCARD\r\nVERSION:3.0\r\nUID:" . $uid . "\r\n";
    $out .= "FN:" . nx_ical_esc((string) ($c['name'] ?? '')) . "\r\n";
    if (($c['email'] ?? '') !== '') {
        $out .= "EMAIL:" . $c['email'] . "\r\n";
    }
    if (($c['phone'] ?? '') !== '') {
        $out .= "TEL:" . $c['phone'] . "\r\n";
    }
    if (($c['birthday'] ?? '') !== '') {
        $out .= "BDAY:" . str_replace('-', '', (string) $c['birthday']) . "\r\n";
    }
    if (($c['address'] ?? '') !== '') {
        $out .= "ADR:;;" . nx_ical_esc((string) $c['address']) . ";;;;\r\n";
    }
    if (($c['note'] ?? '') !== '') {
        $out .= "NOTE:" . nx_ical_esc((string) $c['note']) . "\r\n";
    }
    return $out . "END:VCARD\r\n";
}

/** CalDAV/CardDAV: Ressourcen-URLs einer Collection ermitteln. */
function nx_dav_list(array $acc): array {
    $xml = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:getetag/></d:prop></d:propfind>';
    $r = nx_http('PROPFIND', $acc['url'], ['user' => $acc['username'], 'pass' => $acc['password'],
        'body' => $xml, 'headers' => ['Depth: 1', 'Content-Type: application/xml']]);
    if ($r['code'] < 200 || $r['code'] >= 300) {
        return [];
    }
    preg_match_all('#<[^>]*href[^>]*>([^<]+\.(?:ics|vcf))</#i', $r['body'], $m);
    $base = preg_replace('#^(https?://[^/]+).*#', '$1', $acc['url']);
    $out = [];
    foreach ($m[1] as $href) {
        $out[] = str_starts_with($href, 'http') ? $href : $base . $href;
    }
    return $out;
}

/** Einen Sync-Lauf ausfuehren. Gibt eine Statusmeldung zurueck. */
function nx_sync_run(array $u, array $acc): string {
    $dir  = in_array($acc['direction'] ?? 'pull', ['push', 'pull', 'both'], true) ? $acc['direction'] : 'pull';
    $type = $acc['type'] ?? 'ical';
    $mk   = (string) mk();
    $pulled = $pushed = 0;

    if ($type === 'ical' || $type === 'caldav') {
        // ---- PULL Kalender ----
        if ($dir === 'pull' || $dir === 'both') {
            $texts = [];
            if ($type === 'ical') {
                $r = nx_http('GET', $acc['url'], ['user' => $acc['username'] ?? '', 'pass' => $acc['password'] ?? '']);
                if ($r['code'] >= 200 && $r['code'] < 300) {
                    $texts[] = $r['body'];
                } elseif ($r['code'] === 0) {
                    return 'Fehler: ' . ($r['err'] ?: 'nicht erreichbar');
                }
            } else {
                foreach (nx_dav_list($acc) as $href) {
                    $g = nx_http('GET', $href, ['user' => $acc['username'], 'pass' => $acc['password']]);
                    if ($g['code'] >= 200 && $g['code'] < 300) {
                        $texts[] = $g['body'];
                    }
                }
            }
            // gezielt: nur zuvor von DIESEM Konto importierte Termine entfernen
            foreach (db_all('SELECT id, enc FROM events WHERE user_id=?', [$u['id']]) as $row) {
                $d = dec_row($row['enc'], $mk);
                if (($d['sync'] ?? 0) === $acc['id']) {
                    db_run('DELETE FROM events WHERE id=?', [$row['id']]);
                }
            }
            foreach ($texts as $tx) {
                foreach (nx_ical_parse($tx) as $ev) {
                    $ev['sync'] = $acc['id'];
                    $ev['color'] = $ev['color'] ?? '#4a8ca0';
                    db_run('INSERT INTO events (user_id,day,enc) VALUES (?,?,?)',
                        [$u['id'], $ev['day'], enc_row($ev, $mk)]);
                    $pulled++;
                }
            }
        }
        // ---- PUSH Kalender (nur CalDAV) ----
        if (($dir === 'push' || $dir === 'both') && $type === 'caldav') {
            foreach (db_all('SELECT id, day, enc FROM events WHERE user_id=?', [$u['id']]) as $row) {
                $e = dec_row($row['enc'], $mk);
                if (!$e || ($e['sync'] ?? 0) === $acc['id']) {
                    continue; // eigene Importe nicht zurueckschieben
                }
                $uid = ($e['uid'] ?? '') !== '' ? $e['uid'] : ('nx-' . $u['id'] . '-' . $row['id'] . '@' . NX_NAME);
                $put = nx_http('PUT', rtrim($acc['url'], '/') . '/' . rawurlencode($uid) . '.ics',
                    ['user' => $acc['username'], 'pass' => $acc['password'],
                     'body' => nx_ical_wrap(nx_ical_build_event($e, $uid)),
                     'headers' => ['Content-Type: text/calendar; charset=utf-8']]);
                if ($put['code'] >= 200 && $put['code'] < 300) {
                    $pushed++;
                }
            }
        }
        db_run('UPDATE sync_accounts SET last_sync=? WHERE id=? AND user_id=?',
            [date('Y-m-d H:i'), $acc['id'], $u['id']]);
        return "Kalender synchronisiert: $pulled empfangen, $pushed gesendet.";
    }

    if ($type === 'carddav') {
        if ($dir === 'pull' || $dir === 'both') {
            foreach (db_all('SELECT id, enc FROM contacts WHERE user_id=?', [$u['id']]) as $row) {
                $d = dec_row($row['enc'], $mk);
                if (($d['sync'] ?? 0) === $acc['id']) {
                    db_run('DELETE FROM contacts WHERE id=?', [$row['id']]);
                }
            }
            foreach (nx_dav_list($acc) as $href) {
                $g = nx_http('GET', $href, ['user' => $acc['username'], 'pass' => $acc['password']]);
                if ($g['code'] >= 200 && $g['code'] < 300) {
                    foreach (nx_vcard_parse($g['body']) as $card) {
                        $card['sync'] = $acc['id'];
                        db_run('INSERT INTO contacts (user_id,enc) VALUES (?,?)', [$u['id'], enc_row($card, $mk)]);
                        $pulled++;
                    }
                }
            }
        }
        if ($dir === 'push' || $dir === 'both') {
            foreach (db_all('SELECT id, enc FROM contacts WHERE user_id=?', [$u['id']]) as $row) {
                $c = dec_row($row['enc'], $mk);
                if (!$c || ($c['sync'] ?? 0) === $acc['id']) {
                    continue;
                }
                $uid = 'nx-c-' . $u['id'] . '-' . $row['id'];
                $put = nx_http('PUT', rtrim($acc['url'], '/') . '/' . $uid . '.vcf',
                    ['user' => $acc['username'], 'pass' => $acc['password'],
                     'body' => nx_vcard_build($c, $uid),
                     'headers' => ['Content-Type: text/vcard; charset=utf-8']]);
                if ($put['code'] >= 200 && $put['code'] < 300) {
                    $pushed++;
                }
            }
        }
        db_run('UPDATE sync_accounts SET last_sync=? WHERE id=? AND user_id=?',
            [date('Y-m-d H:i'), $acc['id'], $u['id']]);
        return "Kontakte synchronisiert: $pulled empfangen, $pushed gesendet.";
    }
    return 'Unbekannter Sync-Typ.';
}


/* ================================================================== *
 *  APP: PASSWORT-MANAGER (verschluesselter Login-Speicher)
 *  Eintraege sind mit dem Konto-Schluessel verschluesselt und nur hier
 *  sichtbar. Passwoerter werden nur auf Anforderung eingeblendet.
 * ================================================================== */
function handle_passwords(array $u, string $action): void {
    if ($action === 'save') {
        csrf_check_post();
        $id = param_int('id');
        $title = trim(param('title'));
        if ($title === '') {
            redirect(url('passwords'));
        }
        $enc = enc_row([
            'title'    => $title,
            'url'      => trim(param('url')),
            'username' => trim(param('username')),
            'password' => param('password'),
            'note'     => trim(param('note')),
        ], (string) mk());
        if ($id) {
            db_run('UPDATE passwords SET enc=? WHERE id=? AND user_id=?', [$enc, $id, $u['id']]);
        } else {
            db_run('INSERT INTO passwords (user_id,enc) VALUES (?,?)', [$u['id'], $enc]);
        }
        flash('Zugang gespeichert.');
        redirect(url('passwords'));
    }
    if ($action === 'del') {
        csrf_check_get();
        db_run('DELETE FROM passwords WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
        flash('Zugang gelöscht.');
        redirect(url('passwords'));
    }
}

function render_passwords(array $u): void {
    if (param('view') === 'edit') {
        $id = param_int('id');
        $p = null;
        if ($id > 0) {
            $row = db_one('SELECT * FROM passwords WHERE id=? AND user_id=?', [$id, $u['id']]);
            $p = $row ? dec_row($row['enc'], mk()) : null;
            if (!$p) {
                redirect(url('passwords'));
            }
        }
        layout_topbar($id > 0 ? 'Zugang bearbeiten' : 'Neuer Zugang', '', '', url('passwords'));
        echo '<div class="panel" style="max-width:560px">';
        echo '<form method="post" action="?app=passwords&action=save">' . csrf_field();
        echo '<input type="hidden" name="id" value="' . ($id > 0 ? $id : '') . '">';
        echo '<div class="field"><label>Bezeichnung</label><input class="input" name="title" required value="' . h($p['title'] ?? '') . '"' . ($id > 0 ? '' : ' autofocus') . '></div>';
        echo '<div class="field"><label>Website (optional)</label><input class="input" type="url" name="url" placeholder="https://…" value="' . h($p['url'] ?? '') . '"></div>';
        echo '<div class="field"><label>Benutzername / E-Mail</label><input class="input" name="username" autocomplete="off" value="' . h($p['username'] ?? '') . '"></div>';
        echo '<div class="field"><label>Passwort</label><div class="pw-field"><input class="input" type="password" name="password" id="pwInput" autocomplete="new-password" value="' . h($p['password'] ?? '') . '">';
        echo '<button type="button" class="pw-eye" onclick="pwToggle(this,\'pwInput\')" title="Anzeigen">' . icon('user', 15) . '</button></div></div>';
        echo '<div class="field"><label>Notiz</label><textarea name="note" style="min-height:56px">' . h($p['note'] ?? '') . '</textarea></div>';
        echo '<button class="btn" style="width:100%;justify-content:center">Speichern</button></form>';
        if ($id > 0) {
            echo '<a class="btn ghost danger-txt" style="width:100%;justify-content:center;margin-top:9px" href="?app=passwords&action=del&id=' . $id . '&_csrf=' . csrf_token() . '" onclick="return confirm(\'Zugang löschen?\')">' . icon('trash', 15) . ' Löschen</a>';
        }
        echo '</div>';
        return;
    }

    $rows = [];
    foreach (db_all('SELECT * FROM passwords WHERE user_id=? ORDER BY id DESC', [$u['id']]) as $row) {
        $p = dec_row($row['enc'], mk());
        if ($p) {
            $p['id'] = (int) $row['id'];
            $rows[] = $p;
        }
    }
    usort($rows, static function ($a, $b) {
        return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
    });
    layout_topbar('Passwörter', count($rows) . ' Zugänge · nur hier sichtbar',
        '<a class="btn" href="?app=passwords&view=edit">' . icon('plus') . ' Zugang</a>');

    if (!$rows) {
        echo '<div class="empty">' . icon('key', 40) . '<h3>Keine Zugänge</h3><p>Logins werden verschlüsselt gespeichert und sind nur in dieser App sichtbar.</p></div>';
        return;
    }
    echo '<div class="cgrid">';
    foreach ($rows as $p) {
        $host = preg_replace('#^https?://(www\\.)?#', '', (string) ($p['url'] ?? ''));
        echo '<div class="ccard">';
        echo '<a class="chead" href="?app=passwords&view=edit&id=' . $p['id'] . '">';
        echo '<span class="avatar">' . h(strtoupper(mb_substr((string) ($p['title'] ?? '?'), 0, 1))) . '</span>';
        echo '<span class="cname"><strong>' . h($p['title'] ?? '') . '</strong><small>' . h($p['username'] ?? '') . '</small></span>';
        echo '<span class="cacts">' . icon('edit', 15) . '</span></a>';
        echo '<div class="cbody">';
        if ($host !== '') {
            echo '<a class="cline" href="' . h($p['url']) . '" target="_blank" rel="noopener">' . icon('link', 15) . '<span>' . h($host) . '</span></a>';
        }
        $pwid = 'pw' . $p['id'];
        echo '<div class="cline"><span class="ic">' . icon('key', 15) . '</span>'
            . '<span class="pw-dot" id="' . $pwid . '" data-v="' . h($p['password'] ?? '') . '">••••••••</span>'
            . '<button type="button" class="pw-mini" onclick="pwReveal(\'' . $pwid . '\')">zeigen</button>'
            . '<button type="button" class="pw-mini" onclick="pwCopy(\'' . $pwid . '\')">kopieren</button></div>';
        echo '</div></div>';
    }
    echo '</div>';
}

/* ================================================================== *
 *  APP: DOKUMENTE (nativer Rich-Text-Editor, eigenes Design)
 *  Inhalte werden mit dem Konto-Schluessel verschluesselt gespeichert.
 * ================================================================== */
/** Erlaubt nur sichere Formatierungs-Tags/Attribute (verhindert XSS). */
function nx_html_clean(string $html): string {
    $html = trim($html);
    if ($html === '') { return ''; }
    $allowTags = ['p','br','div','span','b','strong','i','em','u','s','h1','h2','h3','ul','ol','li','a','blockquote','pre','code','hr'];
    if (class_exists('DOMDocument')) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<meta charset="utf-8"><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $bodies = $dom->getElementsByTagName('body');
        if (!$bodies->length) { return ''; }
        $root = $bodies->item(0)->getElementsByTagName('div')->item(0); // unser Wrapper
        if ($root === null) { return ''; }
        $xp = new DOMXPath($dom);
        foreach (iterator_to_array($xp->query('.//*', $root)) as $el) {
            $tag = strtolower($el->nodeName);
            if (!in_array($tag, $allowTags, true)) {
                while ($el->firstChild) { $el->parentNode->insertBefore($el->firstChild, $el); }
                if ($el->parentNode) { $el->parentNode->removeChild($el); }
                continue;
            }
            foreach (iterator_to_array($el->attributes ?? []) as $attr) {
                $keep = ($tag === 'a' && strtolower($attr->nodeName) === 'href'
                    && preg_match('#^(https?:|mailto:)#i', trim((string) $attr->nodeValue)));
                if (!$keep) { $el->removeAttribute($attr->nodeName); }
            }
            if ($tag === 'a') { $el->setAttribute('target', '_blank'); $el->setAttribute('rel', 'noopener'); }
        }
        $out = '';
        foreach ($root->childNodes as $ch) { $out .= $dom->saveHTML($ch); }
        return $out;
    }
    // Fallback ohne DOMDocument: gefaehrliche Teile grob entfernen
    $html = preg_replace('#<(script|style|iframe|object|embed|link|meta)\b[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|style|iframe|object|embed|link|meta)\b[^>]*/?>#i', '', $html);
    $html = preg_replace('#\son\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    $html = preg_replace('#\s(?:href|src)\s*=\s*(?:"\s*javascript:[^"]*"|\'\s*javascript:[^\']*\')#i', '', $html);
    return $html;
}

function handle_docs(array $u, string $action): void {
    if ($action === 'save') {
        csrf_check_post();
        $id = param_int('id');
        $title = trim(param('title'));
        if ($title === '') { $title = 'Unbenannt'; }
        $body = nx_html_clean((string) ($_POST['body'] ?? ''));
        $enc = enc_row(['title' => $title, 'html' => $body], (string) mk());
        if ($id) {
            db_run("UPDATE docs SET enc=?, updated_at=datetime('now') WHERE id=? AND user_id=?", [$enc, $id, $u['id']]);
        } else {
            db_run('INSERT INTO docs (user_id,enc) VALUES (?,?)', [$u['id'], $enc]);
            $id = db_lastid();
        }
        flash('Dokument gespeichert.');
        redirect(url('docs', ['id' => $id]));
    }
    if ($action === 'del') {
        csrf_check_get();
        db_run('DELETE FROM docs WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
        flash('Dokument gelöscht.');
        redirect(url('docs'));
    }
}

function render_docs(array $u): void {
    $id = param_int('id');
    if ($id > 0 || param('new') === '1') {
        $doc = ['title' => '', 'html' => ''];
        if ($id > 0) {
            $row = db_one('SELECT * FROM docs WHERE id=? AND user_id=?', [$id, $u['id']]);
            if (!$row) { redirect(url('docs')); }
            $doc = dec_row($row['enc'], mk()) ?: $doc;
        }
        $actions = '<button class="btn" onclick="docSave()">' . icon('check', 15) . ' Speichern</button>';
        if ($id > 0) {
            $actions = '<a class="btn ghost sm" href="?app=docs&action=del&id=' . $id . '&_csrf=' . csrf_token() . '" onclick="return confirm(\'Dokument löschen?\')">' . icon('trash', 15) . '</a> ' . $actions;
        }
        layout_topbar('Dokument', '', $actions, url('docs'));
        echo '<form method="post" action="?app=docs&action=save" id="docForm" class="doc-wrap">' . csrf_field();
        echo '<input type="hidden" name="id" value="' . ($id > 0 ? $id : '') . '"><input type="hidden" name="body" id="docBody">';
        echo '<input class="input doc-title" name="title" placeholder="Titel" value="' . h($doc['title'] ?? '') . '" required>';
        echo '<div class="doc-toolbar">';
        $tb = [
            ['bold', 'B', 'Fett', 'font-weight:700'], ['italic', 'I', 'Kursiv', 'font-style:italic'],
            ['underline', 'U', 'Unterstrichen', 'text-decoration:underline'],
        ];
        foreach ($tb as [$cmd, $lbl, $ti, $st]) {
            echo '<button type="button" class="dbtn" title="' . $ti . '" style="' . $st . '" onmousedown="event.preventDefault()" onclick="docCmd(\'' . $cmd . '\')">' . $lbl . '</button>';
        }
        echo '<span class="dsep"></span>';
        echo '<button type="button" class="dbtn" title="Überschrift 1" onmousedown="event.preventDefault()" onclick="docBlock(\'H1\')">H1</button>';
        echo '<button type="button" class="dbtn" title="Überschrift 2" onmousedown="event.preventDefault()" onclick="docBlock(\'H2\')">H2</button>';
        echo '<button type="button" class="dbtn" title="Normal" onmousedown="event.preventDefault()" onclick="docBlock(\'P\')">¶</button>';
        echo '<span class="dsep"></span>';
        echo '<button type="button" class="dbtn" title="Aufzählung" onmousedown="event.preventDefault()" onclick="docCmd(\'insertUnorderedList\')">•</button>';
        echo '<button type="button" class="dbtn" title="Nummeriert" onmousedown="event.preventDefault()" onclick="docCmd(\'insertOrderedList\')">1.</button>';
        echo '<button type="button" class="dbtn" title="Link" onmousedown="event.preventDefault()" onclick="docLink()">🔗</button>';
        echo '<button type="button" class="dbtn" title="Formatierung entfernen" onmousedown="event.preventDefault()" onclick="docCmd(\'removeFormat\')">✕</button>';
        echo '</div>';
        echo '<div class="doc-editor" id="docEditor" contenteditable="true">' . ($doc['html'] ?? '') . '</div>';
        echo '</form>';
        echo '<script>document.addEventListener("DOMContentLoaded",docInit);</script>';
        return;
    }

    $rows = db_all('SELECT * FROM docs WHERE user_id=? ORDER BY updated_at DESC, id DESC', [$u['id']]);
    layout_topbar(t('app_docs'), count($rows) . ' Dokumente', '<a class="btn" href="?app=docs&new=1">' . icon('plus') . ' Neu</a>');
    if (!$rows) {
        echo '<div class="empty">' . icon('note', 40) . '<h3>Noch keine Dokumente</h3><p>Lege mit „+ Neu" dein erstes Dokument an – mit Formatierung, komplett in Nexus.</p></div>';
        return;
    }
    echo '<div class="cgrid">';
    foreach ($rows as $row) {
        $d = dec_row($row['enc'], mk());
        if (!$d) { continue; }
        $preview = trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags((string) ($d['html'] ?? ''))), 0, 120));
        echo '<a class="ccard doc-card" href="?app=docs&id=' . $row['id'] . '">';
        echo '<div class="chead"><span class="avatar" style="background:color-mix(in srgb,#4d7ea8 16%,transparent);color:#4d7ea8">' . icon('note', 17) . '</span>';
        echo '<span class="cname"><strong>' . h($d['title'] ?? 'Unbenannt') . '</strong><small>' . h(date('d.m.Y', strtotime((string) $row['updated_at'] . ' UTC') ?: time())) . '</small></span></div>';
        if ($preview !== '') { echo '<div class="doc-prev">' . h($preview) . '</div>'; }
        echo '</a>';
    }
    echo '</div>';
}

/* ================================================================== *
 *  APP: APP-STORE  (Apps hinzufuegen/entfernen; nur fertige, nativ
 *  integrierbare Apps: die gebauten Nexus-Apps + einbettbare Web-Apps)
 * ================================================================== */
function handle_store(array $u, string $action): void {
    csrf_check_post();
    $id = trim(param('id'));
    if ($action === 'install' && $id !== '') {
        nx_installed_materialize($u);
        // Nur bekannte IDs zulassen
        $ok = in_array($id, nx_store_builtins(), true)
            || (str_starts_with($id, 'web:') && isset(nx_webapps()[substr($id, 4)]));
        if ($ok) {
            db_run('INSERT OR IGNORE INTO app_installs (user_id, app_id) VALUES (?,?)', [$u['id'], $id]);
            flash('App hinzugefügt.');
        }
    }
    if ($action === 'remove' && $id !== '') {
        nx_installed_materialize($u);
        db_run('DELETE FROM app_installs WHERE user_id=? AND app_id=?', [$u['id'], $id]);
        flash('App entfernt.');
    }
    redirect(url('store'));
}

function render_store(array $u): void {
    // Erweitert: Lesezeichen hinzufuegen
    if (param('view') === 'bm') {
        layout_topbar('Lesezeichen', 'Erweitert', '', url('store'));
        echo '<div class="panel" style="max-width:560px"><form method="post" action="?app=bookmarks&action=save" style="margin:0">' . csrf_field();
        echo '<div class="field"><label>Titel</label><input class="input" name="title" required autofocus></div>';
        echo '<div class="field"><label>URL</label><input class="input" type="text" name="url" placeholder="https://…" required></div>';
        echo '<div class="field"><label>Farbe</label>' . color_swatch('color', '#4d7ea8') . '</div>';
        echo '<button class="btn" style="width:100%;justify-content:center">Hinzufügen</button></form>';
        $bms = db_all('SELECT * FROM bookmarks WHERE user_id=? ORDER BY position,id', [$u['id']]);
        if ($bms) {
            echo '<div class="section-h" style="margin-top:18px">' . icon('link') . ' Vorhanden</div>';
            foreach ($bms as $row) {
                $b = dec_row($row['enc'], mk());
                if (!$b) { continue; }
                echo '<div class="mrow static"><span class="mic" style="--mc:' . h($b['color'] ?? '#4d7ea8') . '">' . icon('link', 15) . '</span>';
                echo '<span class="mlabel"><strong>' . h($b['title'] ?? '') . '</strong><small style="color:var(--muted);font-weight:400">' . h(preg_replace('#^https?://(www\.)?#', '', (string) ($b['url'] ?? ''))) . '</small></span>';
                echo '<a class="mic" style="--mc:var(--err);width:30px;height:30px" href="?app=bookmarks&action=del&id=' . $row['id'] . '&_csrf=' . csrf_token() . '" onclick="return confirm(\'Löschen?\')">' . icon('trash', 15) . '</a></div>';
            }
        }
        echo '</div>';
        return;
    }

    $inst   = nx_installed($u);
    $apps   = nx_apps();
    $linked = nx_linked($u);
    $prov   = nx_providers();

    // Web-Apps nach Anbieter (vendor) bzw. Thema (cat) gruppieren
    $vendorApps = [];
    $topicApps  = [];
    foreach (nx_webapps() as $wid => $w) {
        if (isset($w['vendor'])) { $vendorApps[$w['vendor']][$wid] = $w; }
        else                     { $topicApps[$w['cat']][$wid] = $w; }
    }

    $btn = static function (string $id, bool $on): string {
        if ($on) {
            return '<form method="post" action="?app=store&action=remove" style="margin:0">' . csrf_field()
                . '<input type="hidden" name="id" value="' . h($id) . '">'
                . '<button class="store-btn is-on" title="Von der Startseite entfernen">' . icon('check', 15) . ' Hinzugefügt</button></form>';
        }
        return '<form method="post" action="?app=store&action=install" style="margin:0">' . csrf_field()
            . '<input type="hidden" name="id" value="' . h($id) . '">'
            . '<button class="store-btn is-add">' . icon('plus', 15) . ' Hinzufügen</button></form>';
    };
    $card = static function (string $id, string $name, string $desc, string $color, string $ico, bool $on) use ($btn): string {
        $out  = '<div class="ccard wacard' . ($on ? ' installed' : '') . '" data-name="' . h(mb_strtolower($name)) . '"><div class="chead">';
        $out .= '<span class="avatar" style="background:color-mix(in srgb,' . $color . ' 16%,transparent);color:' . $color . '">' . $ico . '</span>';
        $out .= '<span class="cname"><strong>' . h($name) . '</strong><small>' . h($desc) . '</small></span>';
        if ($on) { $out .= '<span class="on-badge" title="Auf deiner Startseite">' . icon('check', 13) . '</span>'; }
        $out .= '</div>';
        $out .= '<div class="cbody"><div class="store-act">' . $btn($id, $on) . '</div></div></div>';
        return $out;
    };

    /* ---------- Ordneransicht: eine Gruppe (Standard/Anbieter/Thema) ---------- */
    if (param('view') === 'folder') {
        $f = preg_replace('/[^A-Za-zÀ-ÿ]/u', '', param('f'));
        $isProv = isset($prov[$f]);
        $title  = $isProv ? $prov[$f]['name'] : ($f === 'default' ? 'Standard' : $f);
        layout_topbar($title, 'App-Store', '', url('store'));

        if ($isProv && !isset($linked[$f])) {
            echo '<div class="empty locked">' . icon('key', 40) . '<h3>' . h($prov[$f]['name']) . '-Konto verknüpfen</h3>';
            echo '<p>Die ' . h($prov[$f]['name']) . '-Apps erscheinen, sobald du dein Konto verknüpfst. Deine Freunde sehen nur, was du bewusst teilst.</p>';
            echo '<a class="btn" href="' . url('settings', ['view' => 'account']) . '">' . icon('plus', 15) . ' Konto verknüpfen</a></div>';
            return;
        }

        echo '<div class="cgrid">';
        if ($f === 'default') {
            foreach (nx_store_builtins() as $id) {
                if (!can_access($u, $id)) { continue; }
                $a = $apps[$id];
                echo $card($id, $a['name'], $a['desc'], $a['color'], icon($a['icon'], 17), in_array($id, $inst, true));
            }
        } elseif ($isProv) {
            foreach ($vendorApps[$f] ?? [] as $wid => $w) {
                echo $card('web:' . $wid, $w['name'], 'Läuft in Nexus', nx_wa_color($w['name']), h(strtoupper(mb_substr($w['name'], 0, 1))), in_array('web:' . $wid, $inst, true));
            }
        } else {
            foreach ($topicApps[$f] ?? [] as $wid => $w) {
                echo $card('web:' . $wid, $w['name'], 'Läuft in Nexus', nx_wa_color($w['name']), h(strtoupper(mb_substr($w['name'], 0, 1))), in_array('web:' . $wid, $inst, true));
            }
        }
        echo '</div>';
        return;
    }

    /* ---------- Landing: Ordner-Kacheln + globale Suche ---------- */
    layout_topbar('App-Store', 'Apps hinzufügen', '', url('home'));
    echo '<div class="store-bar"><input class="input" id="storeSearch" placeholder="Alle Apps durchsuchen…" oninput="storeSearchAll()"></div>';

    // Ordner-Kacheln
    $folder = static function (string $f, string $label, string $sub, string $color, string $ico, bool $locked, int $count): string {
        $out = '<a class="folder-tile' . ($locked ? ' locked' : '') . '" href="?app=store&view=folder&f=' . rawurlencode($f) . '">';
        $out .= '<span class="favatar" style="background:color-mix(in srgb,' . $color . ' 18%,transparent);color:' . $color . '">' . $ico . '</span>';
        $out .= '<span class="fname"><strong>' . h($label) . ($locked ? ' ' . icon('key', 12) : '') . '</strong><small>' . h($sub) . '</small></span>';
        $out .= '<span class="fcount">' . $count . '</span></a>';
        return $out;
    };
    echo '<div class="folder-grid" id="storeFolders">';
    // Standard
    $stdCount = 0;
    foreach (nx_store_builtins() as $id) { if (can_access($u, $id)) { $stdCount++; } }
    echo $folder('default', 'Standard', 'Native Nexus-Apps', '#4d7ea8', icon('grid', 18), false, $stdCount);
    // Anbieter-Ordner (gesperrt bis verknüpft)
    foreach ($prov as $pid => $p) {
        $n = count($vendorApps[$pid] ?? []);
        if ($n === 0) { continue; }
        $locked = !isset($linked[$pid]);
        echo $folder($pid, $p['name'], $locked ? 'Konto verknüpfen' : 'Verknüpft', $p['color'], icon($p['icon'], 18), $locked, $n);
    }
    // Themen-Ordner
    ksort($topicApps);
    foreach ($topicApps as $cat => $list) {
        echo $folder($cat, $cat, count($list) . ' Apps', nx_wa_color($cat), h(strtoupper(mb_substr($cat, 0, 1))), false, count($list));
    }
    // Erweitert (Lesezeichen)
    echo '<a class="folder-tile" href="?app=store&view=bm"><span class="favatar" style="background:color-mix(in srgb,#8a7fb0 18%,transparent);color:#8a7fb0">' . icon('link', 18) . '</span><span class="fname"><strong>Erweitert</strong><small>Lesezeichen</small></span><span class="fcount">+</span></a>';
    echo '</div>';

    // Verstecktes Suchergebnis-Grid (nur sichtbar bei aktiver Suche)
    echo '<div class="cgrid" id="storeAll" style="display:none">';
    foreach (nx_store_builtins() as $id) {
        if (!can_access($u, $id)) { continue; }
        $a = $apps[$id];
        echo $card($id, $a['name'], $a['desc'], $a['color'], icon($a['icon'], 17), in_array($id, $inst, true));
    }
    foreach (nx_webapps() as $wid => $w) {
        if (isset($w['vendor']) && !isset($linked[$w['vendor']])) { continue; } // gesperrte Anbieter-Apps nicht zeigen
        echo $card('web:' . $wid, $w['name'], 'Läuft in Nexus', nx_wa_color($w['name']), h(strtoupper(mb_substr($w['name'], 0, 1))), in_array('web:' . $wid, $inst, true));
    }
    echo '</div>';
    echo '<p class="note-line" id="storeNote" style="max-width:560px;margin-top:12px">Öffne einen Ordner und tippe bei einer App auf <b>+ Hinzufügen</b>. Anbieter-Ordner (Google &amp; Co.) schalten sich frei, sobald du das Konto unter <a href="' . url('settings', ['view' => 'account']) . '">Konto</a> verknüpfst.</p>';
}

/* Web-App-Viewer: bettet eine installierte Web-App nativ per iframe ein.
   Die CSP wird gezielt nur um deren Host erweitert. */
function render_web(array $u): void {
    $wid = preg_replace('/[^a-z0-9_]/', '', strtolower(param('id')));
    $cat = nx_webapps();
    if (!isset($cat[$wid]) || !in_array('web:' . $wid, nx_installed($u), true)) {
        redirect(url('store'));
    }
    $w = $cat[$wid];
    $GLOBALS['nx_frame_src'] = $w['frame'];
    layout_topbar($w['name'], '', '<a class="btn ghost sm" href="' . url('store') . '">' . icon('download', 15) . ' Store</a>', url('home'));

    $needsInput = !isset($w['static']); // statische Apps ohne Eingabe
    $v = param('v');
    $src = nx_embed_src($w, $v);
    $ratio = $w['ratio'] ?? 'full';
    $cls = $ratio === 'audio' ? 'embed-audio' : ($ratio === 'video' ? 'embed-16x9' : 'embed-full');

    if ($needsInput) {
        echo '<div class="panel" style="max-width:960px">';
        echo '<form method="get" style="display:flex;gap:8px;margin-bottom:12px">';
        echo '<input type="hidden" name="app" value="web"><input type="hidden" name="id" value="' . h($wid) . '">';
        echo '<input class="input" name="v" placeholder="' . h($w['hint'] ?? 'Link einfügen') . '" value="' . h($v) . '" autofocus>';
        echo '<button class="btn">Öffnen</button></form>';
        if ($src !== '') {
            echo '<div class="' . $cls . '"><iframe src="' . h($src)
                . '" allow="autoplay;encrypted-media;fullscreen;picture-in-picture;clipboard-write;geolocation" allowfullscreen loading="lazy"></iframe></div>';
        } else {
            echo '<div class="empty">' . icon('link', 40) . '<h3>' . h($w['name']) . '</h3><p>' . h($w['hint'] ?? 'Link einfügen und öffnen.') . '</p></div>';
        }
        echo '</div>';
        return;
    }
    echo '<div class="' . $cls . '"><iframe src="' . h($src) . '" allow="fullscreen;geolocation" allowfullscreen referrerpolicy="no-referrer" loading="lazy"></iframe></div>';
}


/* ================================================================== *
 *  APP: EINSTELLUNGEN
 * ================================================================== */
function handle_settings(array $u, string $action): void {
    if ($action === 'sync_run') {
        csrf_check_get();
        $acc = nx_sync_account($u, param_int('id'));
        if ($acc) {
            $msg = nx_sync_run($u, $acc);
            flash($msg, str_starts_with($msg, 'Fehler') ? 'err' : 'ok');
        }
        redirect(url('settings', ['view' => 'sync']));
    }
    if ($action === 'sync_del') {
        csrf_check_get();
        db_run('DELETE FROM sync_accounts WHERE id=? AND user_id=?', [param_int('id'), $u['id']]);
        // Importierte Einträge dieses Kontos bereinigen
        $sid = param_int('id');
        $mkk = (string) mk();
        foreach (['events', 'contacts'] as $tbl) {
            foreach (db_all("SELECT id, enc FROM $tbl WHERE user_id=?", [$u['id']]) as $rw) {
                $d = dec_row($rw['enc'], $mkk);
                if (($d['sync'] ?? 0) === $sid) {
                    db_run("DELETE FROM $tbl WHERE id=?", [$rw['id']]);
                }
            }
        }
        flash('Sync-Konto entfernt.');
        redirect(url('settings', ['view' => 'sync']));
    }
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
        case 'language':
            $lang = in_array(param('lang'), array_keys(nx_langs()), true) ? param('lang') : ($u['lang'] ?? 'en');
            db_run('UPDATE users SET lang=? WHERE id=?', [$lang, $u['id']]);
            $_SESSION['lang'] = $lang;
            redirect(url('settings', ['view' => 'language']));
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
        case 'link_add':
            $prov = preg_replace('/[^a-z]/', '', strtolower(param('provider')));
            if (!isset(nx_providers()[$prov])) {
                flash('Unbekannter Anbieter.', 'err');
                redirect(url('settings', ['view' => 'account']));
            }
            $label = trim(param('label'));
            db_run('INSERT OR REPLACE INTO linked_accounts (id,user_id,provider,enc,created_at) VALUES (
                        (SELECT id FROM linked_accounts WHERE user_id=? AND provider=?), ?, ?, ?, ' . "datetime('now')" . ')',
                [$u['id'], $prov, $u['id'], $prov, enc_row(['label' => $label], (string) mk())]);
            nx_history_add((int) $u['id'], 'account', $prov, 'link', null, $label !== '' ? $label : 'verknüpft', (int) $u['id'], 'verknüpft');
            flash(nx_providers()[$prov]['name'] . '-Konto verknüpft – Apps freigeschaltet.');
            redirect(url('settings', ['view' => 'account']));
        case 'link_del':
            $prov = preg_replace('/[^a-z]/', '', strtolower(param('provider')));
            db_run('DELETE FROM linked_accounts WHERE user_id=? AND provider=?', [$u['id'], $prov]);
            nx_history_add((int) $u['id'], 'account', $prov, 'link', 'verknüpft', null, (int) $u['id'], 'getrennt');
            flash('Konto getrennt.');
            redirect(url('settings', ['view' => 'account']));
        case 'sync_add':
            $type = in_array(param('type'), ['ical', 'caldav', 'carddav'], true) ? param('type') : 'ical';
            $dir  = in_array(param('direction'), ['push', 'pull', 'both'], true) ? param('direction') : 'pull';
            $urlv = trim(param('url'));
            if (!preg_match('#^https?://#i', $urlv)) {
                flash('Bitte eine gültige URL (https://…) angeben.', 'err');
                redirect(url('settings', ['view' => 'sync']));
            }
            if ($type === 'ical') {
                $dir = 'pull'; // iCal-URL ist nur lesbar
            }
            db_run('INSERT INTO sync_accounts (user_id,enc) VALUES (?,?)', [$u['id'], enc_row([
                'label'     => trim(param('label')) !== '' ? trim(param('label')) : $urlv,
                'type'      => $type,
                'url'       => $urlv,
                'username'  => trim(param('username')),
                'password'  => param('password'),
                'direction' => $dir,
            ], (string) mk())]);
            flash('Sync-Konto hinzugefügt.');
            redirect(url('settings', ['view' => 'sync']));
    }
    redirect(url('settings'));
}

function render_settings(array $u): void {
    $view = param('view');
    $back = url('settings');

    /* --- KONTO: Meine Karte + Anmeldedaten + Passwort auf einer Seite --- */
    if ($view === 'account') {
        layout_topbar('Konto', '', '', $back);
        // Meine Karte (eigener Kontakt)
        $mine = null;
        foreach (db_all('SELECT * FROM contacts WHERE user_id=?', [$u['id']]) as $row) {
            $c = dec_row($row['enc'], mk());
            if ($c && (int) ($c['me'] ?? 0) === 1) { $c['id'] = (int) $row['id']; $mine = $c; break; }
        }
        echo '<div class="section-h">' . icon('user') . ' Meine Karte</div><div class="panel" style="max-width:480px">';
        echo '<form method="post" action="?app=contacts&action=save">' . csrf_field();
        echo '<input type="hidden" name="id" value="' . ($mine['id'] ?? '') . '"><input type="hidden" name="me" value="1"><input type="hidden" name="back" value="profile">';
        echo '<div class="field"><label>Name</label><input class="input" name="name" required value="' . h($mine['name'] ?? ($u['display_name'] ?: $u['username'])) . '"></div>';
        echo '<div class="row"><div class="field"><label>E-Mail</label><input class="input" type="email" name="email" value="' . h($mine['email'] ?? $u['email']) . '"></div>';
        echo '<div class="field"><label>Telefon</label><input class="input" type="tel" name="phone" value="' . h($mine['phone'] ?? '') . '"></div></div>';
        echo '<div class="row"><div class="field"><label>Geburtstag</label><input class="input" type="date" name="birthday" value="' . h($mine['birthday'] ?? '') . '"></div>';
        echo '<div class="field"><label>Adresse</label><input class="input" name="address" value="' . h($mine['address'] ?? '') . '"></div></div>';
        echo '<button class="btn">' . ($mine ? 'Karte aktualisieren' : 'Karte anlegen') . '</button></form></div>';
        // Verknuepfte Konten (schalten Anbieter-Apps frei)
        $linked = nx_linked($u);
        echo '<div class="section-h">' . icon('grid') . ' Verknüpfte Konten</div><div class="panel" style="max-width:480px">';
        echo '<p class="note-line" style="margin:0 0 12px">Verknüpfe ein Anbieterkonto, um dessen Apps im Katalog freizuschalten. Freunde sehen nur, was du bewusst teilst.</p>';
        foreach (nx_providers() as $pid => $p) {
            $on = isset($linked[$pid]);
            echo '<div class="lrow"><span class="mic" style="--mc:' . h($p['color']) . '">' . icon($p['icon'], 16) . '</span>';
            echo '<span class="mlabel"><strong>' . h($p['name']) . '</strong><small>' . ($on ? ('Verknüpft' . ($linked[$pid]['label'] !== '' ? ' · ' . h($linked[$pid]['label']) : '')) : 'Nicht verknüpft') . '</small></span>';
            if ($on) {
                echo '<form method="post" action="?app=settings&action=link_del" style="margin:0" onsubmit="return confirm(\'' . h($p['name']) . '-Konto trennen?\')">' . csrf_field()
                    . '<input type="hidden" name="provider" value="' . $pid . '"><button class="btn ghost sm danger-txt">' . icon('trash', 14) . '</button></form>';
            } else {
                echo '<button class="btn ghost sm" type="button" onclick="nxLinkOpen(\'' . $pid . '\',\'' . h($p['name']) . '\')">' . icon('plus', 14) . ' Verknüpfen</button>';
            }
            echo '</div>';
        }
        echo '<form method="post" action="?app=settings&action=link_add" id="nxLinkForm" style="display:none;margin-top:12px;border-top:1px solid var(--line);padding-top:12px">' . csrf_field();
        echo '<input type="hidden" name="provider" id="nxLinkProv">';
        echo '<div class="field"><label id="nxLinkLbl">Konto</label><input class="input" name="label" id="nxLinkInput" placeholder="E-Mail / Kontoname (optional)"></div>';
        echo '<button class="btn" style="width:100%;justify-content:center">Verknüpfen</button></form></div>';
        // Anmeldedaten
        echo '<div class="section-h">' . icon('cog') . ' Anmeldedaten</div><div class="panel" style="max-width:480px">';
        echo '<form method="post" action="?app=settings&action=profile">' . csrf_field();
        echo '<div class="field"><label>Anzeigename</label><input class="input" name="display_name" value="' . h($u['display_name']) . '"></div>';
        echo '<div class="field"><label>E-Mail (optional)</label><input class="input" type="email" name="email" value="' . h($u['email']) . '"></div>';
        echo '<div class="field"><label>Benutzername</label><input class="input" value="' . h($u['username']) . '" disabled></div>';
        echo '<button class="btn">Speichern</button></form></div>';
        // Passwort
        echo '<div class="section-h">' . icon('key') . ' Passwort</div><div class="panel" style="max-width:480px">';
        echo '<form method="post" action="?app=settings&action=password">' . csrf_field();
        echo '<div class="field"><label>Aktuelles Passwort</label><input class="input" type="password" name="current" required></div>';
        echo '<div class="row"><div class="field"><label>Neues Passwort</label><input class="input" type="password" name="new" required></div>';
        echo '<div class="field"><label>Wiederholen</label><input class="input" type="password" name="new2" required></div></div>';
        echo '<p class="note-line">Ein verlorenes Passwort kann nicht zurückgesetzt werden.</p>';
        echo '<button class="btn">Passwort ändern</button></form></div>';
        return;
    }

    /* --- AUSSEHEN --- */
    if ($view === 'appearance') {
        layout_topbar(t('set_appearance'), '', '', $back);
        echo '<div class="panel" style="max-width:480px">';
        echo '<form method="post" action="?app=settings&action=appearance">' . csrf_field();
        echo '<div class="field"><label>' . h(t('set_theme')) . '</label><select name="theme">';
        foreach (['dark' => t('set_dark'), 'light' => t('set_light')] as $k => $v) {
            echo '<option value="' . $k . '" ' . ($u['theme'] === $k ? 'selected' : '') . '>' . $v . '</option>';
        }
        echo '</select></div>';
        echo '<div class="field"><label>' . h(t('set_accent')) . '</label>' . color_swatch('accent', (string) $u['accent']) . '</div>';
        echo '<button class="btn" style="width:100%;justify-content:center">' . h(t('set_apply')) . '</button></form></div>';
        return;
    }

    /* --- SPRACHE (eigener Einstellungspunkt) --- */
    if ($view === 'language') {
        layout_topbar(t('set_language'), '', '', $back);
        echo '<div class="mlist settings-w">';
        foreach (nx_langs() as $code => $lname) {
            $on = nx_lang() === $code;
            echo '<form method="post" action="?app=settings&action=language" style="margin:0">' . csrf_field();
            echo '<input type="hidden" name="lang" value="' . $code . '">';
            echo '<button class="mrow lang-row' . ($on ? ' on' : '') . '" type="submit">';
            echo '<span class="mic" style="--mc:#4a8ca0">' . icon('grid', 16) . '</span>';
            echo '<span class="mlabel"><strong>' . h($lname) . '</strong></span>';
            echo '<span class="chev">' . ($on ? icon('check', 16) : '') . '</span></button></form>';
        }
        echo '</div>';
        return;
    }

    /* --- VERBINDUNGEN: Mail-Konten + Kalender/Kontakte-Sync --- */
    if ($view === 'connections') {
        layout_topbar('Verbindungen', 'Mail, Kalender & Kontakte', '', $back);
        // Mail-Konten
        echo '<div class="section-h">' . icon('mail') . ' Mail-Konten</div><div class="panel" style="max-width:480px">';
        $accts = mail_accounts($u);
        if ($accts) {
            foreach ($accts as $a) {
                echo '<div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--line)">';
                echo '<div class="tico" style="width:34px;height:34px">' . icon('mail') . '</div>';
                echo '<div style="flex:1;min-width:0"><strong>' . h($a['label']) . '</strong><br><small style="color:var(--muted)">' . h($a['email']) . '</small></div>';
                echo '<form method="post" action="?app=settings&action=acc_del" onsubmit="return confirm(\'Konto entfernen?\')">' . csrf_field()
                    . '<input type="hidden" name="id" value="' . $a['id'] . '"><button class="btn ghost sm danger-txt">' . icon('trash', 14) . '</button></form>';
                echo '</div>';
            }
        } else {
            echo '<p style="color:var(--muted);margin-bottom:12px">Kein externes Mail-Konto verbunden.</p>';
        }
        echo '<a class="btn ghost" href="' . url('mail', ['new' => 1]) . '" style="margin-top:12px">' . icon('plus') . ' Mail-Konto hinzufügen</a></div>';

        // Sync (CalDAV/CardDAV/iCal)
        echo '<div class="section-h">' . icon('calendar') . ' Kalender &amp; Kontakte synchronisieren</div>';
        $accs = nx_sync_accounts($u);
        if ($accs) {
            echo '<div class="mlist" style="max-width:480px">';
            foreach ($accs as $a) {
                $dirLbl = ['push' => 'Nur senden', 'pull' => 'Nur empfangen', 'both' => 'Beidseitig'][$a['direction']] ?? $a['direction'];
                $tLbl = ['ical' => 'iCal-URL', 'caldav' => 'CalDAV', 'carddav' => 'CardDAV'][$a['type']] ?? $a['type'];
                echo '<div class="mrow static"><span class="mic" style="--mc:#4a9d6f">' . icon($a['type'] === 'carddav' ? 'user' : 'calendar', 16) . '</span>';
                echo '<span class="mlabel"><strong>' . h($a['label']) . '</strong><small style="color:var(--muted);font-weight:400">' . h($tLbl) . ' · ' . h($dirLbl)
                    . ($a['last_sync'] !== '' ? ' · zuletzt ' . h($a['last_sync']) : '') . '</small></span>';
                echo '<span style="display:flex;gap:2px">';
                echo '<a class="mic" style="--mc:var(--accent);width:30px;height:30px" title="Jetzt synchronisieren" href="?app=settings&action=sync_run&id=' . $a['id'] . '&_csrf=' . csrf_token() . '">' . icon('download', 15) . '</a>';
                echo '<a class="mic" style="--mc:var(--err);width:30px;height:30px" title="Entfernen" href="?app=settings&action=sync_del&id=' . $a['id'] . '&_csrf=' . csrf_token() . '" onclick="return confirm(\'Sync-Konto entfernen?\')">' . icon('trash', 15) . '</a>';
                echo '</span></div>';
            }
            echo '</div>';
        }
        echo '<div class="panel" style="max-width:480px">';
        echo '<form method="post" action="?app=settings&action=sync_add">' . csrf_field();
        echo '<div class="field"><label>Was?</label><select name="type" onchange="nxSyncType(this.value)">'
            . '<option value="ical">Kalender abonnieren (iCal-URL, nur empfangen)</option>'
            . '<option value="caldav">Kalender (CalDAV, senden/empfangen)</option>'
            . '<option value="carddav">Kontakte (CardDAV, senden/empfangen)</option></select></div>';
        echo '<div class="field"><label>Bezeichnung (optional)</label><input class="input" name="label" placeholder="z. B. Google-Kalender"></div>';
        echo '<div class="field"><label>URL</label><input class="input" type="url" name="url" placeholder="https://…" required>'
            . '<p class="note-line" id="syncHint" style="margin:6px 0 0">Bei Google: Kalender-Einstellungen → „Geheime Adresse im iCal-Format".</p></div>';
        echo '<div class="row"><div class="field"><label>Benutzername</label><input class="input" name="username" placeholder="bei CalDAV/CardDAV"></div>';
        echo '<div class="field"><label>Passwort / App-Passwort</label><input class="input" type="password" name="password"></div></div>';
        echo '<div class="field" id="syncDir"><label>Richtung</label><select name="direction">'
            . '<option value="pull">Nur empfangen (Pull)</option><option value="both">Beidseitig (Both)</option><option value="push">Nur senden (Push)</option></select></div>';
        echo '<button class="btn" style="width:100%;justify-content:center">Sync hinzufügen</button></form></div>';
        return;
    }

    /* --- SPEICHER & SYSTEM --- */
    if ($view === 'system') {
        if ($u['role'] !== 'admin') { redirect(url('settings')); }
        layout_topbar('System', '', '', $back);
        $used = quota_used((int) $u['id']);
        $max  = (int) $u['quota_bytes'];
        $pct  = $max > 0 ? min(100, (int) round($used / $max * 100)) : 0;
        $barc = $pct >= 90 ? 'var(--err)' : ($pct >= 70 ? 'var(--warn)' : 'var(--accent)');
        echo '<div class="section-h">' . icon('db') . ' Speicher</div><div class="panel" style="max-width:480px">';
        echo '<div class="quota-top"><span>Belegt</span><span class="mono">' . human_size($used) . ' / ' . human_size($max) . '</span></div>';
        echo '<div class="quota-bar"><i style="width:' . $pct . '%;background:' . $barc . '"></i></div></div>';
        echo '<div class="section-h">' . icon('cog') . ' System</div><div class="panel" style="max-width:480px">';
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px">';
        $crypto = function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt') ? 'sodium' : 'openssl';
        foreach ([
            'Version' => NX_NAME . ' ' . NX_VERSION, 'PHP' => PHP_VERSION, 'Krypto' => $crypto,
            'IMAP' => imap_available() ? 'aktiv' : 'nicht verfügbar', 'Datenbank' => 'SQLite',
            'Kompression' => function_exists('gzdeflate') ? 'aktiv' : 'inaktiv',
        ] as $k => $v) {
            echo '<div><span style="color:var(--muted)">' . h($k) . '</span><br>' . h((string) $v) . '</div>';
        }
        echo '</div></div>';
        return;
    }

    /* --- VERWALTUNG (nur Admin) --- */
    if ($view === 'admin') {
        if ($u['role'] !== 'admin') { redirect(url('settings')); }
        layout_topbar('Verwaltung', 'Nutzer, Freischaltung & Quota', '', $back);
        admin_gui($u);
        return;
    }

    /* --- HAUPTSEITE: Kopf + kurze Gruppen (1-2 Woerter) --- */
    layout_topbar(t('set_title'), '');
    $roleLbl = $u['role'] === 'admin' ? 'Administrator'
        : ($u['status'] === 'pending' ? 'Wartet auf Freischaltung' : 'Mitglied');
    echo '<div class="panel phead settings-w">';
    echo '<span class="avatar big">' . h(strtoupper(substr($u['display_name'] ?: $u['username'], 0, 1))) . '</span>';
    echo '<div class="pwho"><strong>' . h($u['display_name'] ?: $u['username']) . '</strong>';
    echo '<small>@' . h($u['username']) . ' · ' . h($roleLbl) . '</small></div></div>';

    $row = static function (string $href, string $ic, string $color, string $label, string $val = ''): string {
        $out = '<a class="mrow" href="' . $href . '"><span class="mic" style="--mc:' . $color . '">' . icon($ic, 16) . '</span>';
        $out .= '<span class="mlabel"><strong>' . $label . '</strong></span>';
        if ($val !== '') { $out .= '<span class="mval">' . $val . '</span>'; }
        return $out . '<span class="chev">' . icon('chevR', 15) . '</span></a>';
    };

    echo '<div class="mlist settings-w">';
    echo $row('?app=settings&view=account', 'user', '#4d7ea8', t('set_account'));
    echo $row('?app=settings&view=appearance', 'sun', '#b3893f', t('set_appearance'), $u['theme'] === 'dark' ? t('set_dark') : t('set_light'));
    echo $row('?app=settings&view=language', 'grid', '#4a8ca0', t('set_language'), nx_langs()[nx_lang()] ?? 'English');
    echo $row('?app=settings&view=connections', 'mail', '#4a8ca0', t('set_connections'), (string) (count(mail_accounts($u)) + count(nx_sync_accounts($u))));
    echo '</div>';

    // Speicher/System und Verwaltung: nur Administratoren
    if ($u['role'] === 'admin') {
        $open = tickets_open_count();
        echo '<div class="mlist settings-w">';
        echo $row('?app=settings&view=admin', 'shield', '#c25a5a', t('set_admin'),
            $open > 0 ? '<span class="nav-badge warn">' . $open . '</span>' : '');
        echo $row('?app=settings&view=system', 'db', '#4a9d6f', t('set_system'));
        echo '</div>';
    }

    echo '<div class="mlist settings-w">';
    echo '<a class="mrow danger" href="?action=logout"><span class="mic">' . icon('logout', 16) . '</span><span class="mlabel"><strong>' . h(t('set_logout')) . '</strong></span></a>';
    echo '</div>';
}

/* ================================================================== *
 *  FRONT CONTROLLER / ROUTER
 * ================================================================== */

// Assets zuerst (ohne Session/DB)
if (isset($_GET['asset'])) {
    $w = (string) $_GET['asset'];
    serve_asset(in_array($w, ['js', 'icon', 'manifest', 'sw'], true) ? $w : 'css');
}

if ($nxMissing = nx_requirements()) {
    nx_setup_page($nxMissing);
    exit;
}

nx_bootstrap();

// Lange Sitzung: Webapp bleibt angemeldet (Cookie + serverseitige
// Session ~30 Tage, gleitend erneuert). Der Master-Key liegt nur in der
// serverseitigen Session (nie im Cookie) - Dateizugriff allein genuegt
// weiterhin nicht zum Entschluesseln.
const NX_SESSION_TTL = 2592000; // 30 Tage
@ini_set('session.gc_maxlifetime', (string) NX_SESSION_TTL);
@ini_set('session.cookie_lifetime', (string) NX_SESSION_TTL);
session_set_cookie_params([
    'lifetime' => NX_SESSION_TTL,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => nx_https(),
]);
session_start();
// Gleitende Verlaengerung: bei jeder Nutzung Ablauf zuruecksetzen
if (!empty($_SESSION['uid'])) {
    if (($_SESSION['seen'] ?? 0) < time() - 3600) {
        $_SESSION['seen'] = time();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + NX_SESSION_TTL,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => nx_https(),
        ]);
    }
}

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
        if (empty($r['err'])) { db_run('UPDATE users SET lang=? WHERE username=?', [nx_lang(), trim(param('username'))]); }
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

// Leichter Zaehler-Ping: Chat-/Ticket-Badges ohne Seiten-Refresh
if ($nxAction === 'ping') {
    header('Content-Type: application/json');
    echo json_encode([
        'chat'    => chat_unread((int) $nxUser['id']),
        'tickets' => $nxUser['role'] === 'admin' ? tickets_open_count() : 0,
        'status'  => $nxUser['status'],
    ]);
    exit;
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
        case 'passwords': handle_passwords($nxUser, $nxAction); break;
        case 'docs':      handle_docs($nxUser, $nxAction);      break;
        case 'store':     handle_store($nxUser, $nxAction);     break;
        case 'admin':     handle_admin($nxUser, $nxAction);     break;
        case 'settings':  handle_settings($nxUser, $nxAction);  break;
    }
}

// Seite rendern
// Lesezeichen -> Startseite; Verwaltung -> Einstellungen (kein eigener App).
if ($nxApp === 'bookmarks') {
    redirect(url('home'));
}
if ($nxApp === 'admin') {
    redirect(url('settings', ['view' => 'admin']));
}

// Noch nicht freigeschaltet: eigener Warteraum statt der App-Oberflaeche
// (Profil/Einstellungen bleiben erreichbar, alle Aktionen liefen bereits).
if ($nxUser['status'] === 'pending' && $nxApp !== 'settings') {
    render_waiting($nxUser);
    exit;
}
if ($nxApp === 'web') {
    $wid = preg_replace('/[^a-z0-9_]/', '', strtolower(param('id')));
    $cat = nx_webapps();
    if (isset($cat[$wid])) { $GLOBALS['nx_frame_src'] = $cat[$wid]['frame']; }
}
layout_head($nxUser, $nxApp);
switch ($nxApp) {
    case 'chat':      render_chat($nxUser);      break;
    case 'mail':      render_mail($nxUser);      break;
    case 'notes':     render_notes($nxUser);     break;
    case 'tasks':     render_tasks($nxUser);     break;
    case 'calendar':  render_calendar($nxUser);  break;
    case 'contacts':  render_contacts($nxUser);  break;
    case 'files':     render_files($nxUser);     break;
    case 'passwords': render_passwords($nxUser);  break;
    case 'docs':      render_docs($nxUser);       break;
    case 'web':       render_web($nxUser);        break;
    case 'store':     render_store($nxUser);      break;
    case 'settings':  render_settings($nxUser);  break;
    default:          render_home($nxUser);      break;
}
layout_foot();
