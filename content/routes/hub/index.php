<?php declare(strict_types=1);
# Secure Messenger & Video - als pages-Route nachgebaut.
# Original: https://hub.xo.je/  (E2E-Messenger + WebRTC-Videotelefonie, "Modern UI").
#
# Der Original-Server beantwortete die "?action=..."-Aufrufe (register, login,
# users, contacts, messages, signal-send/-poll) und hielt Sessions per Cookie.
# Ein solcher Mehrbenutzer-/Realtime-/WebRTC-Signaling-Server laesst sich auf
# pages nicht hosten. Das komplette Frontend ist hier originalgetreu nachgebaut
# und die echte Ende-zu-Ende-Krypto (ECDSA P-256 + RSA-4096 + AES-GCM) laeuft
# unveraendert im Browser. Als standalone-Ersatz *nur fuer den Transport* dient
# ein lokales Demo-Relay (localStorage) - siehe Kommentar im JS weiter unten.
# Nichts wird als sicher vorgetaeuscht; geraeteuebergreifender Betrieb braucht
# weiterhin den Server auf xo.je.

# Geteilte Variablen defensiv einlesen (Muster wie content/routes/launcher-v2).
$hub_theme=(isset($hub_theme)&&in_array($hub_theme,['light','dark'],true))?$hub_theme:'auto';
$hub_title=(isset($hub_title)&&is_string($hub_title)&&$hub_title!=='')?$hub_title:'Secure Messenger & Video';
$hub_icon=(isset($hub_icon)&&is_string($hub_icon)&&$hub_icon!=='')?$hub_icon:'';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

# CSRF-Token serverseitig erzeugen (im Original vom xo.je-Server gesetzt).
try{$hub_csrf=bin2hex(random_bytes(24));}catch(\Throwable $e){$hub_csrf=bin2hex(pack('N*',mt_rand(),mt_rand(),mt_rand()));}

# data-theme nur bei fest light/dark setzen, sonst weglassen (auto folgt System).
# Hinweis: Das Tool-Design ist bewusst dunkel; Light-Overrides existieren im
# Original-CSS nicht - das Attribut wird dennoch respektvoll gesetzt.
$dt=$hub_theme==='light'?' data-theme="light"':($hub_theme==='dark'?' data-theme="dark"':'');
?>
<!doctype html>
<html lang="de"<?= $dt ?>>
<head>
    <meta charset="utf-8">
    <title><?= h($hub_title) ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<?php if($hub_icon!==''): ?>    <link rel="icon" href="<?= h($hub_icon) ?>">
    <link rel="apple-touch-icon" href="<?= h($hub_icon) ?>">
<?php endif; ?>
    <style>
        :root {
            --bg: #05060b;
            --bg-soft: #0d1017;
            --bg-elevated: #11141c;
            --fg: #f5f7fb;
            --muted: #99a3c2;
            --accent: #4f8cff;
            --accent-soft: rgba(79,140,255,0.15);
            --danger: #ff4d4f;
            --success: #2ecc71;
            --border-soft: rgba(255,255,255,0.06);
            --radius-lg: 18px;
            --radius-md: 12px;
            --radius-pill: 999px;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: radial-gradient(circle at top left, #151b2c 0, #05060b 55%);
            color: var(--fg);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: stretch;
        }

        .app-root {
            flex: 1;
            display: flex;
            justify-content: center;
        }

        .app-shell {
            flex: 1;
            max-width: 1240px;
            margin: 10px;
            background: rgba(5,6,11,0.92);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 18px 40px rgba(0,0,0,0.55);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .page {
            flex: 1;
            display: none;
            padding: 18px 20px;
        }

        .page-active {
            display: flex;
            flex-direction: column;
        }

        .header {
            margin-bottom: 16px;
        }

        .header h1 {
            margin: 0 0 4px;
            font-size: 24px;
            letter-spacing: 0.02em;
        }

        .subtitle {
            margin: 0;
            font-size: 13px;
            color: var(--muted);
        }

        .card {
            background: var(--bg-elevated);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            margin-bottom: 12px;
            border: 1px solid var(--border-soft);
        }

        .card h2 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .label {
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 4px;
        }

        input, button, textarea, select {
            font-size: 15px;
            border-radius: var(--radius-pill);
            border: 1px solid var(--border-soft);
            padding: 9px 13px;
            background: #05060c;
            color: var(--fg);
        }

        input, textarea, select {
            width: 100%;
        }

        textarea {
            resize: none;
            border-radius: var(--radius-lg);
        }

        input {
            margin-bottom: 8px;
        }

        button {
            cursor: pointer;
        }

        button.btn {
            border-radius: var(--radius-pill);
        }

        .btn.primary {
            background: var(--accent);
            border-color: var(--accent);
        }

        .btn.danger {
            background: var(--danger);
            border-color: var(--danger);
        }

        .btn.success {
            background: var(--success);
            border-color: var(--success);
        }

        .btn.text {
            background: transparent;
            border: none;
            padding: 4px 8px;
            color: var(--accent);
        }

        .btn.text.danger {
            color: var(--danger);
        }

        .small {
            font-size: 12px;
            color: var(--muted);
        }

        .error {
            font-size: 12px;
            color: var(--danger);
            white-space: pre-wrap;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-soft);
        }

        .user-info {
            font-size: 13px;
            color: var(--muted);
        }

        .layout {
            display: flex;
            flex: 1;
            gap: 10px;
            min-height: 0;
        }

        .sidebar {
            width: 290px;
            max-width: 38%;
            border-right: 1px solid var(--border-soft);
            padding-right: 4px;
        }

        .sidebar .card {
            height: 100%;
            display: flex;
            flex-direction: column;
            margin-bottom: 0;
        }

        .contact-list {
            flex: 1;
            margin-top: 8px;
            max-height: none;
            overflow-y: auto;
        }

        .contact-item {
            padding: 8px 10px;
            border-radius: var(--radius-pill);
            margin-bottom: 4px;
            background: rgba(255,255,255,0.02);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background 0.16s ease;
        }

        .contact-item-name {
            font-size: 14px;
        }

        .contact-item:hover {
            background: rgba(255,255,255,0.06);
        }

        .contact-item.selected {
            background: var(--accent-soft);
            border: 1px solid rgba(79,140,255,0.55);
        }

        .avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 20%, #fff 0, #ffd86f 25%, #ff6a88 55%, #6b48ff 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-right: 8px;
        }

        .main-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            gap: 10px;
            position: relative;
        }

        .chat-card {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 400px;
        }

        .chat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .chat-header-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-header-name {
            font-size: 16px;
            font-weight: 500;
        }

        .chat-header-sub {
            font-size: 12px;
            color: var(--muted);
        }

        .chat-actions {
            display: flex;
            gap: 6px;
        }

        .icon-round {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            color: var(--fg);
            transition: background 0.16s ease, transform 0.08s ease;
        }

        .icon-round.primary {
            background: var(--accent);
            border-color: var(--accent);
        }

        .icon-round:hover {
            transform: translateY(-1px);
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg, rgba(255,255,255,0.02), rgba(0,0,0,0.55));
            padding: 10px 10px 6px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .message-row {
            max-width: 78%;
            padding: 7px 11px;
            border-radius: 20px;
            font-size: 14px;
            line-height: 1.4;
        }

        .message-me {
            align-self: flex-end;
            background: rgba(79,140,255,0.35);
        }

        .message-other {
            align-self: flex-start;
            background: rgba(12,16,30,0.9);
        }

        .message-meta {
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 2px;
        }

        .chat-input-row {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            align-items: flex-end;
        }

        #chat-input {
            flex: 1;
            min-height: 40px;
            max-height: 110px;
        }

        .call-overlay {
            position: absolute;
            inset: 0;
            background: rgba(3,5,12,0.98);
            backdrop-filter: blur(10px);
            display: none;
            flex-direction: column;
            padding: 10px;
            z-index: 50;
        }

        .call-overlay-active {
            display: flex;
        }

        .call-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .call-title {
            font-size: 14px;
            color: var(--muted);
        }

        .call-status {
            font-size: 13px;
            color: var(--muted);
        }

        .call-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid var(--border-soft);
            background: rgba(255,255,255,0.04);
            color: var(--fg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .device-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 6px;
        }

        .device-row select {
            flex: 1;
            border-radius: var(--radius-pill);
        }

        .video-wrapper {
            position: relative;
            background: #000;
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 8px;
            flex: 1;
        }

        #remote-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: #000;
        }

        .remote-video-extra {
            position: absolute;
            width: 110px;
            height: 70px;
            bottom: 8px;
            left: 8px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.75);
            object-fit: cover;
            background: #111;
        }

        #local-video {
            position: absolute;
            right: 8px;
            bottom: 8px;
            width: 120px;
            height: 80px;
            border-radius: 12px;
            border: 2px solid rgba(255,255,255,0.9);
            box-shadow: 0 0 4px rgba(0,0,0,0.6);
            object-fit: cover;
            background: #111;
        }

        .call-controls {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 6px;
        }

        .call-btn-big {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #fff;
        }

        .call-btn-red {
            background: var(--danger);
        }

        .call-btn-muted {
            background: rgba(255,255,255,0.08);
        }

        .add-participant-row {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .add-participant-row select {
            flex: 1;
        }

        .incoming {
            margin-top: 6px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .incoming-actions {
            display: flex;
            gap: 8px;
        }

        @media (max-width: 960px) {
            .app-shell {
                margin: 0;
                border-radius: 0;
                box-shadow: none;
            }
            .page {
                padding: 12px 10px;
            }
            .layout {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                max-width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border-soft);
                padding-right: 0;
                margin-bottom: 8px;
            }
            .chat-card {
                min-height: calc(100vh - 220px);
            }
        }

        @media (max-width: 640px) {
            .header h1 {
                font-size: 20px;
            }
            .chat-header-name {
                font-size: 15px;
            }
            .chat-messages {
                padding: 8px 8px 4px;
            }
            .message-row {
                max-width: 88%;
                font-size: 13px;
            }
            .call-btn-big {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
<div class="app-root">
    <div class="app-shell">

        <section id="page-auth" class="page page-active">
            <header class="header">
                <h1>Secure Messenger &amp; Video</h1>
                <p class="subtitle">Modernes E2E&#8209;Messaging &amp; Videotelefonie</p>
            </header>

            <div class="card">
                <h2>Registrieren</h2>
                <label class="label" for="reg-username">Benutzername (a-z, A-Z, 0-9, _,-)</label>
                <input id="reg-username" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="z. B. max-secure">
                <button id="btn-register" class="btn primary">Schl&uuml;ssel erzeugen &amp; registrieren</button>
                <p class="small">
                    Es werden nur &ouml;ffentliche Schl&uuml;ssel (ECDSA + RSA&#8209;4096) an den Server gesendet.<br>
                    Private Schl&uuml;ssel bleiben bei dir in einer Datei.
                </p>
            </div>

            <div class="card">
                <h2>Login</h2>
                <label class="label" for="key-file">Private-Key-Datei (.json)</label>
                <input type="file" id="key-file" accept=".json,.txt">
                <button id="btn-login" class="btn primary">Login</button>
                <p id="auth-error" class="error" hidden></p>
            </div>

            <!-- Deutscher Hinweis: markiert die fehlende Serverseite (Standalone-Betrieb). -->
            <p class="small">
                Standalone-Demo ohne xo.je-Server: Registrierung, Login und Nachrichten
                laufen lokal in diesem Browser (localStorage), die Krypto echt im Browser.
                Zum Ausprobieren in zwei Browser-Tabs je einen Nutzer anlegen &ndash; ein
                ger&auml;te&uuml;bergreifender Betrieb ben&ouml;tigt den echten Server.
            </p>
        </section>

        <section id="page-app" class="page">
            <header class="topbar">
                <div class="user-info">
                    Eingeloggt als <span id="current-username"></span>
                </div>
                <button id="btn-logout" class="btn text danger">Logout</button>
            </header>

            <main class="layout">
                <aside class="sidebar">
                    <div class="card">
                        <h2>Kontakte</h2>
                        <input id="contact-search" placeholder="Benutzer suchen/ hinzuf&uuml;gen&hellip;" autocomplete="off" autocapitalize="none">
                        <div id="contact-list" class="contact-list"></div>
                    </div>
                </aside>

                <section class="main-pane">
                    <div class="card chat-card">
                        <div class="chat-header">
                            <div class="chat-header-info">
                                <button id="btn-toggle-sidebar" class="btn text" title="Kontakte anzeigen/ausblenden">&#9776;</button>
                                <div class="avatar" id="chat-avatar">?</div>
                                <div>
                                    <div class="chat-header-name" id="chat-contact-name">&ndash; kein Kontakt &ndash;</div>
                                    <div class="chat-header-sub" id="chat-contact-sub">W&auml;hle einen Kontakt aus</div>
                                </div>
                            </div>
                            <div class="chat-actions">
                                <button id="btn-chat-audio" class="icon-round" title="Audio&#8209;Anruf" disabled>&#9742;</button>
                                <button id="btn-chat-video" class="icon-round primary" title="Video&#8209;Anruf" disabled>&#128249;</button>
                            </div>
                        </div>
                        <div id="chat-messages" class="chat-messages"></div>
                        <div class="chat-input-row">
                            <textarea id="chat-input" rows="1" placeholder="Nachricht schreiben&hellip;" autocomplete="off" autocapitalize="none" spellcheck="false"></textarea>
                            <button id="btn-send" class="btn primary">Senden</button>
                        </div>
                    </div>

                    <!-- Call-Overlay (sauber, state-basiert) -->
                    <div id="call-overlay" class="call-overlay">
                        <div class="call-top-row">
                            <div>
                                <div class="call-title">Anruf mit <span id="call-title-name">&ndash;</span></div>
                                <div id="call-status" class="call-status">Bitte einen Kontakt ausw&auml;hlen.</div>
                            </div>
                            <button id="btn-call-close" class="call-close" title="Call schlie&szlig;en">&times;</button>
                        </div>

                        <div id="device-row-call" class="device-row" style="display: none;">
                            <select id="video-source"></select>
                            <select id="audio-source"></select>
                            <button id="btn-refresh-devices" class="btn text">Ger&auml;te aktualisieren</button>
                        </div>

                        <div class="video-wrapper">
                            <video id="remote-video" playsinline autoplay></video>
                            <video id="local-video" playsinline autoplay muted></video>
                        </div>

                        <div id="call-controls-row" class="call-controls" style="display: none;">
                            <button id="btn-mic-toggle" class="call-btn-big call-btn-muted" title="Mikro stumm/aktiv" style="display: none;">&#127897;</button>
                            <button id="btn-hangup" class="call-btn-big call-btn-red" title="Auflegen" style="display: none;">&#10005;</button>
                        </div>

                        <div id="add-participant-row" class="add-participant-row" style="display: none;">
                            <select id="add-participant-select"></select>
                            <button id="btn-add-participant" class="btn">Teilnehmer hinzuf&uuml;gen</button>
                        </div>

                        <div id="incoming-call" class="card incoming" hidden>
                            <div id="incoming-text"></div>
                            <div class="incoming-actions">
                                <button id="btn-accept" class="btn success">Annehmen</button>
                                <button id="btn-reject" class="btn danger">Ablehnen</button>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </section>
    </div>
</div>

<script>
window.INITIAL_CSRF = "<?= h($hub_csrf) ?>";
</script>
<script>
/* ======================
   Lokales Demo-Relay (Standalone-Ersatz fuer den xo.je-Server)
   ----------------------
   Der Original-Server beantwortete die "?action=..."-Aufrufe (register, login,
   users, contacts, contacts-add, messages, send-message, signal-send,
   signal-poll) und hielt Sessions per Cookie. pages kann keinen solchen
   Mehrbenutzer-/Realtime-Server hosten. Damit die komplette Oberflaeche
   (Chat + Video) standalone bedienbar bleibt, ersetzt dieses Relay nur den
   *Transport*: es legt oeffentliche Schluessel, Kontakte, verschluesselte
   Nachrichten und Signaling-Blobs in localStorage ab. Die eigentliche
   Ende-zu-Ende-Krypto (ECDSA/RSA/AES) bleibt unveraendert im Browser.
   Zwei Tabs desselben Browsers koennen so echt verschluesselt chatten und
   (auf demselben Geraet) per WebRTC telefonieren. Fuer echten
   geraeteuebergreifenden Betrieb ist der Server auf xo.je noetig - der fehlt
   hier bewusst; nichts wird als sicher vorgetaeuscht.
   ====================== */
const LocalHub = (() => {
    const NS = 'hub_demo_v1';
    function db() {
        try { return JSON.parse(localStorage.getItem(NS) || '{}'); } catch (e) { return {}; }
    }
    function save(d) {
        try { localStorage.setItem(NS, JSON.stringify(d)); } catch (e) {}
    }
    function ensure(d) {
        d.users    = d.users    || {}; // username -> { pubSign, pubEnc }
        d.contacts = d.contacts || {}; // username -> [namen]
        d.mailbox  = d.mailbox  || {}; // username -> [blobZeile]  (kumulativ)
        d.signals  = d.signals  || {}; // username -> [blobZeile]  (beim Abholen geleert)
        return d;
    }
    // Session pro Tab (im Original serverseitiges Cookie).
    function sessUser() { try { return sessionStorage.getItem(NS + '_user') || null; } catch (e) { return null; } }
    function setSess(u) {
        try { u ? sessionStorage.setItem(NS + '_user', u) : sessionStorage.removeItem(NS + '_user'); } catch (e) {}
    }

    function handle(action, payload) {
        const d = ensure(db());
        const p = payload || {};
        switch (action) {
            case 'register': {
                if (!p.username || !p.pubSign || !p.pubEnc) return { ok: false, error: 'ungueltig' };
                d.users[p.username] = { pubSign: p.pubSign, pubEnc: p.pubEnc };
                d.contacts[p.username] = d.contacts[p.username] || [];
                d.mailbox[p.username]  = d.mailbox[p.username]  || [];
                d.signals[p.username]  = d.signals[p.username]  || [];
                save(d);
                return { ok: true };
            }
            case 'login': {
                // Nur lokale Existenzpruefung - keine echte Server-Authentifizierung.
                if (!p.username || !d.users[p.username]) return { ok: false, error: 'Benutzer in diesem Browser nicht registriert' };
                setSess(p.username);
                return { ok: true, user: p.username, csrf: (window.INITIAL_CSRF || 'demo') };
            }
            case 'logout': {
                setSess(null);
                return { ok: true };
            }
            case 'users': {
                const users = Object.keys(d.users).map(u => ({
                    username: u, pubSign: d.users[u].pubSign, pubEnc: d.users[u].pubEnc
                }));
                return { ok: true, users };
            }
            case 'contacts': {
                const me = sessUser();
                return { ok: true, contacts: (me && d.contacts[me]) ? d.contacts[me].slice() : [] };
            }
            case 'contacts-add': {
                const me = sessUser();
                if (!me) return { ok: false, error: 'keine Session' };
                if (!p.contact || !d.users[p.contact]) return { ok: false, error: 'unbekannter Benutzer' };
                d.contacts[me] = d.contacts[me] || [];
                if (!d.contacts[me].includes(p.contact)) d.contacts[me].push(p.contact);
                save(d);
                return { ok: true };
            }
            case 'messages': {
                // Kumulative Liste - Frontend verarbeitet neue Zeilen per Index.
                const me = sessUser();
                return { ok: true, messages: (me && d.mailbox[me]) ? d.mailbox[me].slice() : [] };
            }
            case 'send-message': {
                if (!p.to || !p.blob) return { ok: false, error: 'ungueltig' };
                d.mailbox[p.to] = d.mailbox[p.to] || [];
                d.mailbox[p.to].push(p.blob);
                save(d);
                return { ok: true };
            }
            case 'signal-send': {
                if (!p.to || !p.blob) return { ok: false, error: 'ungueltig' };
                d.signals[p.to] = d.signals[p.to] || [];
                d.signals[p.to].push(p.blob);
                save(d);
                return { ok: true };
            }
            case 'signal-poll': {
                // Signale werden beim Abholen konsumiert (wie beim Original-Relay).
                const me = sessUser();
                if (!me) return { ok: true, signals: [] };
                const out = (d.signals[me] || []).slice();
                d.signals[me] = [];
                save(d);
                return { ok: true, signals: out };
            }
            default:
                return { ok: false, error: 'unbekannte Aktion' };
        }
    }
    return { handle };
})();

/* ======================
   API / Helper
   ====================== */

// Original-Endpunkt (nicht mehr aktiv, dient nur der Dokumentation):
//   const API = window.location.pathname + '?action=';
//   fetch(API + encodeURIComponent(action), ...) gegen den xo.je-Server.
// Hier lokal ueber das Demo-Relay beantwortet - kein Netzwerk-Backend.

let CSRF = window.INITIAL_CSRF || '';

async function apiCall(action, payload = null, method = 'POST') {
    await Promise.resolve(); // async beibehalten, damit der restliche Code unveraendert bleibt
    try {
        return LocalHub.handle(action, payload);
    } catch (e) {
        return { ok: false, raw: String(e) };
    }
}

/* ======================
   DOM references
   ====================== */

const pageAuth   = document.getElementById('page-auth');
const pageApp    = document.getElementById('page-app');

const regInput   = document.getElementById('reg-username');
const btnRegister = document.getElementById('btn-register');

const keyFile    = document.getElementById('key-file');
const btnLogin   = document.getElementById('btn-login');
const authError  = document.getElementById('auth-error');

const currentUsernameLabel = document.getElementById('current-username');
const btnLogout = document.getElementById('btn-logout');

const sidebar        = document.querySelector('.sidebar');
const contactSearch  = document.getElementById('contact-search');
const contactList    = document.getElementById('contact-list');
const btnToggleSidebar = document.getElementById('btn-toggle-sidebar');

const chatAvatar      = document.getElementById('chat-avatar');
const chatContactName = document.getElementById('chat-contact-name');
const chatContactSub  = document.getElementById('chat-contact-sub');
const chatMessages    = document.getElementById('chat-messages');
const chatInput       = document.getElementById('chat-input');
const btnSend         = document.getElementById('btn-send');

const btnChatAudio = document.getElementById('btn-chat-audio');
const btnChatVideo = document.getElementById('btn-chat-video');

const callOverlay   = document.getElementById('call-overlay');
const callTitleName = document.getElementById('call-title-name');
const callStatus    = document.getElementById('call-status');
const btnCallClose  = document.getElementById('btn-call-close');

const remoteVideo   = document.getElementById('remote-video');
const localVideo    = document.getElementById('local-video');

const deviceRowCall   = document.getElementById('device-row-call');
const videoSourceSel  = document.getElementById('video-source');
const audioSourceSel  = document.getElementById('audio-source');
const btnRefreshDevs  = document.getElementById('btn-refresh-devices');

const callControlsRow = document.getElementById('call-controls-row');
const btnMicToggle    = document.getElementById('btn-mic-toggle');
const btnHangup       = document.getElementById('btn-hangup');

const addParticipantRow = document.getElementById('add-participant-row');
const addParticipantSel = document.getElementById('add-participant-select');
const btnAddParticipant = document.getElementById('btn-add-participant');

const incomingPanel   = document.getElementById('incoming-call');
const incomingText    = document.getElementById('incoming-text');
const btnAccept       = document.getElementById('btn-accept');
const btnReject       = document.getElementById('btn-reject');

function showPageAuth() {
    pageAuth.classList.add('page-active');
    pageApp.classList.remove('page-active');
}

function showPageApp() {
    pageAuth.classList.remove('page-active');
    pageApp.classList.add('page-active');
}

/* Sidebar ein-/ausblenden */

let sidebarVisible = true;
function showSidebar(show) {
    sidebar.style.display = show ? 'block' : 'none';
    sidebarVisible = show;
}
btnToggleSidebar.addEventListener('click', () => {
    showSidebar(!sidebarVisible);
});

/* ======================
   Crypto helper (RSA-4096 + ECDSA P-256)
   ====================== */

function arrayBufferToBase64(buf) {
    const bytes = new Uint8Array(buf);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
}

function base64ToArrayBuffer(b64) {
    const bin = atob(b64);
    const len = bin.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        bytes[i] = bin.charCodeAt(i);
    }
    return bytes.buffer;
}

function toPem(label, buf) {
    const base64 = arrayBufferToBase64(buf);
    const lines = base64.match(/.{1,64}/g) || [];
    return `-----BEGIN ${label}-----\n${lines.join('\n')}\n-----END ${label}-----\n`;
}

function pemToBinary(pem) {
    const cleaned = pem.replace(/-----[^-]+-----/g, '').replace(/\s+/g, '');
    return base64ToArrayBuffer(cleaned);
}

let identityFileData = null;
let currentUser = null;

let privSignKey = null; // ECDSA
let privEncKey  = null; // RSA-OAEP

let usersDirectory = [];  // vom Server
let myContacts      = []; // eigene Kontaktliste

async function generateIdentityKeys() {
    const signPair = await crypto.subtle.generateKey(
        { name: 'ECDSA', namedCurve: 'P-256' },
        true,
        ['sign', 'verify']
    );
    const encPair = await crypto.subtle.generateKey(
        {
            name: 'RSA-OAEP',
            modulusLength: 4096,
            publicExponent: new Uint8Array([1, 0, 1]),
            hash: 'SHA-256'
        },
        true,
        ['encrypt', 'decrypt']
    );

    const pubSign = await crypto.subtle.exportKey('spki', signPair.publicKey);
    const privSign = await crypto.subtle.exportKey('pkcs8', signPair.privateKey);
    const pubEnc = await crypto.subtle.exportKey('spki', encPair.publicKey);
    const privEnc = await crypto.subtle.exportKey('pkcs8', encPair.privateKey);

    return {
        pubSignPem: toPem("PUBLIC KEY", pubSign),
        privSignPem: toPem("PRIVATE KEY", privSign),
        pubEncPem: toPem("PUBLIC KEY", pubEnc),
        privEncPem: toPem("PRIVATE KEY", privEnc)
    };
}

function download(filename, text) {
    const blob = new Blob([text], { type: 'application/json' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
}

async function importPrivateKeysFromFileData(data) {
    const privSignBuf = pemToBinary(data.privSign);
    const privEncBuf  = pemToBinary(data.privEnc);

    privSignKey = await crypto.subtle.importKey(
        'pkcs8',
        privSignBuf,
        { name: 'ECDSA', namedCurve: 'P-256' },
        false,
        ['sign']
    );

    privEncKey = await crypto.subtle.importKey(
        'pkcs8',
        privEncBuf,
        { name: 'RSA-OAEP', hash: 'SHA-256' },
        false,
        ['decrypt']
    );
}

async function encryptForUser(username, plainText) {
    const peer = usersDirectory.find(u => u.username === username);
    if (!peer || !peer.pubEnc) throw new Error('Empf&auml;nger-Public-Key fehlt');

    const pubEncBuf = pemToBinary(peer.pubEnc);
    const pubEncKey = await crypto.subtle.importKey(
        'spki',
        pubEncBuf,
        { name: 'RSA-OAEP', hash: 'SHA-256' },
        false,
        ['encrypt']
    );

    const aesKey = await crypto.subtle.generateKey(
        { name: 'AES-GCM', length: 256 },
        true,
        ['encrypt', 'decrypt']
    );
    const iv  = crypto.getRandomValues(new Uint8Array(12));
    const enc = new TextEncoder().encode(plainText);
    const ciphertext = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        aesKey,
        enc
    );
    const rawKey = await crypto.subtle.exportKey('raw', aesKey);
    const encKey = await crypto.subtle.encrypt(
        { name: 'RSA-OAEP' },
        pubEncKey,
        rawKey
    );

    return {
        encKeyB64: arrayBufferToBase64(encKey),
        ivB64: arrayBufferToBase64(iv.buffer),
        cipherB64: arrayBufferToBase64(ciphertext)
    };
}

async function decryptFromBlob(blob) {
    if (!privEncKey) throw new Error('Privater RSA-Schl&uuml;ssel nicht geladen');
    const encKeyBuf = base64ToArrayBuffer(blob.encKey);
    const rawKey = await crypto.subtle.decrypt(
        { name: 'RSA-OAEP' },
        privEncKey,
        encKeyBuf
    );
    const aesKey = await crypto.subtle.importKey(
        'raw',
        rawKey,
        { name: 'AES-GCM' },
        false,
        ['decrypt']
    );
    const iv   = new Uint8Array(base64ToArrayBuffer(blob.iv));
    const ct   = base64ToArrayBuffer(blob.ciphertext);
    const plainBuf = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv },
        aesKey,
        ct
    );
    return new TextDecoder().decode(plainBuf);
}

async function signPayload(text) {
    if (!privSignKey) throw new Error('Privater Signaturschl&uuml;ssel nicht geladen');
    const bytes = new TextEncoder().encode(text);
    const sigBuf = await crypto.subtle.sign(
        { name: 'ECDSA', hash: 'SHA-256' },
        privSignKey,
        bytes
    );
    return arrayBufferToBase64(sigBuf);
}

async function verifySignatureFromUser(username, text, sigB64) {
    const user = usersDirectory.find(u => u.username === username);
    if (!user || !user.pubSign) return false;

    const pubSignBuf = pemToBinary(user.pubSign);
    const pubKey = await crypto.subtle.importKey(
        'spki',
        pubSignBuf,
        { name: 'ECDSA', namedCurve: 'P-256' },
        false,
        ['verify']
    );
    const sig = base64ToArrayBuffer(sigB64);
    const msg = new TextEncoder().encode(text);

    return crypto.subtle.verify(
        { name: 'ECDSA', hash: 'SHA-256' },
        pubKey,
        sig,
        msg
    );
}

/* ======================
   Kontakte & UI
   ====================== */

function initialAvatarLetter(name) {
    if (!name) return '?';
    return name.trim().charAt(0).toUpperCase();
}

let selectedContact = null;

async function loadMyContacts() {
    const res = await apiCall('contacts', null, 'GET');
    if (res.ok && Array.isArray(res.contacts)) {
        myContacts = res.contacts;
    } else {
        myContacts = [];
    }
    renderContactList(contactSearch.value);
    refreshAddParticipantOptions();
}

async function addContact(username) {
    if (!username) return false;
    const res = await apiCall('contacts-add', {
        contact: username,
        csrf: CSRF
    }, 'POST');
    if (!res.ok) {
        alert('Kontakt hinzuf&uuml;gen fehlgeschlagen.');
        console.error(res);
        return false;
    }
    await loadMyContacts();
    return true;
}

function renderContactList(filter = '') {
    const q = filter.trim().toLowerCase();
    contactList.innerHTML = '';

    let names;
    if (myContacts.length) {
        names = myContacts.slice();
    } else {
        names = usersDirectory
            .filter(u => u.username !== currentUser)
            .map(u => u.username);
    }

    names.sort((a, b) => a.localeCompare(b));

    for (const name of names) {
        if (q && !name.toLowerCase().includes(q)) continue;

        const div = document.createElement('div');
        div.className = 'contact-item';
        div.dataset.username = name;

        const left = document.createElement('div');
        left.style.display = 'flex';
        left.style.alignItems = 'center';

        const av = document.createElement('div');
        av.className = 'avatar';
        av.textContent = initialAvatarLetter(name);

        const nameSpan = document.createElement('span');
        nameSpan.className = 'contact-item-name';
        nameSpan.textContent = name;

        left.appendChild(av);
        left.appendChild(nameSpan);

        const right = document.createElement('div');
        right.style.fontSize = '16px';
        right.textContent = '›';

        div.appendChild(left);
        div.appendChild(right);

        if (selectedContact === name) {
            div.classList.add('selected');
        }

        div.addEventListener('click', () => selectContact(name));
        contactList.appendChild(div);
    }

    updateCallUI();
    renderMessagesForSelectedContact();
    refreshAddParticipantOptions();
}

function getSelectedContact() {
    return selectedContact;
}

function selectContact(username) {
    selectedContact = username;
    for (const item of contactList.querySelectorAll('.contact-item')) {
        item.classList.toggle('selected', item.dataset.username === username);
    }
    if (!username) {
        chatContactName.textContent = '– kein Kontakt –';
        chatContactSub.textContent = 'Wähle einen Kontakt aus';
        chatAvatar.textContent = '?';
    } else {
        chatContactName.textContent = username;
        chatContactSub.textContent = 'Verschlüsselter Chat';
        chatAvatar.textContent = initialAvatarLetter(username);
    }
    if (!username) {
        callStatus.textContent = 'Bitte einen Kontakt auswählen.';
    } else if (callState === 'idle') {
        callStatus.textContent = `Bereit für Call mit ${username}`;
    }
    renderMessagesForSelectedContact();
    updateCallUI();
    if (username) {
        showSidebar(false);
    }
}

async function refreshUserDirectory() {
    const res = await apiCall('users', null, 'GET');
    if (!res.ok || !Array.isArray(res.users)) {
        console.error('users failed', res);
        return;
    }
    usersDirectory = res.users;
    renderContactList(contactSearch.value);
    refreshDeviceListsIfEmpty();
}

contactSearch.addEventListener('input', () => {
    renderContactList(contactSearch.value);
});

contactSearch.addEventListener('keydown', async (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        const name = contactSearch.value.trim();
        if (!name) return;

        await refreshUserDirectory();
        const exists = usersDirectory.some(u => u.username === name);
        if (!exists) {
            alert('Benutzer nicht gefunden.');
            return;
        }

        const added = await addContact(name);
        if (added) {
            contactSearch.value = '';
            selectContact(name);
        }
    }
});

/* ======================
   Chat-Funktionalit&auml;t
   ====================== */

let allMessageLines = [];
let chatMessagesStore = []; // {from,to,ts,text,valid}

function renderMessagesForSelectedContact() {
    const contact = selectedContact;
    chatMessages.innerHTML = '';
    if (!contact) return;

    const msgs = chatMessagesStore.filter(m =>
        (m.from === contact && m.to === currentUser) ||
        (m.from === currentUser && m.to === contact)
    ).sort((a, b) => a.ts - b.ts);

    for (const m of msgs) {
        const row = document.createElement('div');
        row.className = 'message-row ' + (m.from === currentUser ? 'message-me' : 'message-other');
        const meta = document.createElement('div');
        meta.className = 'message-meta';
        const dt = new Date(m.ts || Date.now()).toLocaleString();
        meta.textContent = `${m.from} • ${dt}` + (m.valid ? '' : ' (Signatur?)');
        const text = document.createElement('div');
        text.textContent = m.text;
        row.appendChild(meta);
        row.appendChild(text);
        chatMessages.appendChild(row);
    }
    if (chatMessages.lastElementChild) {
        chatMessages.lastElementChild.scrollIntoView({ behavior: 'auto', block: 'end' });
    }
}

async function pollMessages() {
    if (!currentUser) return;
    const res = await apiCall('messages', null, 'GET');
    if (!res.ok || !Array.isArray(res.messages)) {
        return;
    }
    const lines = res.messages;
    if (lines.length === allMessageLines.length) return;

    for (let i = allMessageLines.length; i < lines.length; i++) {
        const line = lines[i];
        let parsed;
        try { parsed = JSON.parse(line); } catch (e) { continue; }
        if (!parsed || !parsed.ciphertext || !parsed.encKey || !parsed.iv || !parsed.from || !parsed.to) continue;

        try {
            const plain = await decryptFromBlob({
                encKey: parsed.encKey,
                iv: parsed.iv,
                ciphertext: parsed.ciphertext
            });
            let payload;
            try { payload = JSON.parse(plain); } catch (e) { continue; }
            if (payload.type === 'chat') {
                const okSig = await verifySignatureFromUser(parsed.from, plain, parsed.signature || '');
                chatMessagesStore.push({
                    from: payload.from,
                    to: payload.to,
                    ts: payload.ts,
                    text: payload.text,
                    valid: okSig
                });
            }
        } catch (e) {
            console.error('decrypt chat failed', e);
        }
    }
    allMessageLines = lines;
    renderMessagesForSelectedContact();
}

let messagePollTimer = null;
function startMessagePolling() {
    if (messagePollTimer) clearInterval(messagePollTimer);
    messagePollTimer = setInterval(() => {
        pollMessages().catch(console.error);
    }, 3000);
}
function stopMessagePolling() {
    if (messagePollTimer) {
        clearInterval(messagePollTimer);
        messagePollTimer = null;
    }
}

async function sendChatMessage() {
    const contact = getSelectedContact();
    if (!contact) {
        alert('Bitte zuerst einen Kontakt ausw&auml;hlen.');
        return;
    }
    if (!currentUser) {
        alert('Nicht eingeloggt.');
        return;
    }
    if (!privSignKey || !privEncKey) {
        alert('Schl&uuml;ssel nicht geladen, bitte neu einloggen.');
        return;
    }
    const text = chatInput.value.trim();
    if (!text) return;

    const ts = Date.now();
    const payload = {
        type: 'chat',
        from: currentUser,
        to: contact,
        ts,
        text
    };
    const plain = JSON.stringify(payload);

    try {
        const encRecipient = await encryptForUser(contact, plain);
        const sig = await signPayload(plain);
        const blobRecipient = {
            from: currentUser,
            to: contact,
            ts,
            encKey: encRecipient.encKeyB64,
            iv: encRecipient.ivB64,
            ciphertext: encRecipient.cipherB64,
            signature: sig
        };

        const encSelf = await encryptForUser(currentUser, plain);
        const blobSelf = {
            from: currentUser,
            to: currentUser,
            ts,
            encKey: encSelf.encKeyB64,
            iv: encSelf.ivB64,
            ciphertext: encSelf.cipherB64,
            signature: sig
        };

        let res = await apiCall('send-message', {
            to: contact,
            blob: JSON.stringify(blobRecipient),
            csrf: CSRF
        }, 'POST');
        if (!res.ok) {
            console.error(res);
            alert('Senden an Empf&auml;nger fehlgeschlagen.');
            return;
        }

        res = await apiCall('send-message', {
            to: currentUser,
            blob: JSON.stringify(blobSelf),
            csrf: CSRF
        }, 'POST');
        if (!res.ok) {
            console.error(res);
            alert('Senden an eigenes Postfach fehlgeschlagen.');
            return;
        }

        chatInput.value = '';
        await pollMessages();
    } catch (e) {
        console.error(e);
        alert('Fehler beim Senden: ' + e.message);
    }
}

btnSend.addEventListener('click', () => {
    sendChatMessage().catch(console.error);
});
chatInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendChatMessage().catch(console.error);
    }
});

/* ======================
   WebRTC / Video-Call (sauberes Overlay)
   ----------------------
   Deutscher Hinweis: WebRTC-Aufbau, Geraeteauswahl und Mediastreams laufen
   echt im Browser. Das Signaling (Offer/Answer/ICE) laeuft im Original ueber
   den xo.je-Server; hier uebernimmt das lokale Demo-Relay (localStorage,
   signal-send/-poll) den Austausch - funktioniert zwischen zwei Tabs
   desselben Geraets, aber nicht geraeteuebergreifend ohne echten Server.
   ====================== */

const iceConfig = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' }
    ]
};

let localStream = null;
let localMicMuted = false;

// Stern-Topologie: Host hat mehrere Verbindungen zu Teilnehmern
const peers = new Map(); // username -> { pc, videoEl }

let signalPollTimer = null;
let pendingOffer = null;

let callState = 'idle'; // 'idle' | 'calling' | 'incoming' | 'in_call'
let callHost   = null;
let isHost     = false;
let currentMediaKind = null; // 'audio' | 'video'

// Ger&auml;teauswahl
let selectedVideoDeviceId = null;
let selectedAudioDeviceId = null;

async function refreshDeviceLists() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
    const devices = await navigator.mediaDevices.enumerateDevices();

    const videos = devices.filter(d => d.kind === 'videoinput');
    const audios = devices.filter(d => d.kind === 'audioinput');

    videoSourceSel.innerHTML = '';
    audioSourceSel.innerHTML = '';

    if (videos.length === 0) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'Keine Kamera';
        videoSourceSel.appendChild(opt);
    } else {
        for (const d of videos) {
            const opt = document.createElement('option');
            opt.value = d.deviceId;
            opt.textContent = d.label || `Kamera ${videoSourceSel.length + 1}`;
            videoSourceSel.appendChild(opt);
        }
    }

    if (audios.length === 0) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'Kein Mikro';
        audioSourceSel.appendChild(opt);
    } else {
        for (const d of audios) {
            const opt = document.createElement('option');
            opt.value = d.deviceId;
            opt.textContent = d.label || `Mikrofon ${audioSourceSel.length + 1}`;
            audioSourceSel.appendChild(opt);
        }
    }

    if (selectedVideoDeviceId) {
        videoSourceSel.value = selectedVideoDeviceId;
    } else if (videos[0]) {
        videoSourceSel.value = videos[0].deviceId;
        selectedVideoDeviceId = videos[0].deviceId;
    }

    if (selectedAudioDeviceId) {
        audioSourceSel.value = selectedAudioDeviceId;
    } else if (audios[0]) {
        audioSourceSel.value = audios[0].deviceId;
        selectedAudioDeviceId = audios[0].deviceId;
    }
}

function refreshDeviceListsIfEmpty() {
    if (!videoSourceSel.options.length || !audioSourceSel.options.length) {
        refreshDeviceLists().catch(console.error);
    }
}

videoSourceSel.addEventListener('change', () => {
    selectedVideoDeviceId = videoSourceSel.value || null;
});
audioSourceSel.addEventListener('change', () => {
    selectedAudioDeviceId = audioSourceSel.value || null;
});
btnRefreshDevs.addEventListener('click', () => {
    refreshDeviceLists().catch(console.error);
});

async function openLocalMedia(kind) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('getUserMedia wird vom Browser nicht unterst&uuml;tzt');
    }

    if (localStream) {
        localStream.getTracks().forEach(t => t.stop());
        localStream = null;
    }

    const audioConstraint = selectedAudioDeviceId
        ? { deviceId: { exact: selectedAudioDeviceId } }
        : true;

    const videoConstraint = kind === 'video'
        ? (selectedVideoDeviceId ? { deviceId: { exact: selectedVideoDeviceId } } : true)
        : false;

    const constraints = {
        audio: audioConstraint,
        video: videoConstraint
    };

    localStream = await navigator.mediaDevices.getUserMedia(constraints);
    localVideo.srcObject = localStream;
    localMicMuted = false;
    updateMicButton();
}

function resetCallState() {
    for (const [user, entry] of peers.entries()) {
        try { entry.pc.onicecandidate = null; } catch (e) {}
        try { entry.pc.ontrack = null; } catch (e) {}
        try { entry.pc.onconnectionstatechange = null; } catch (e) {}
        try { entry.pc.close(); } catch (e) {}
        if (entry.videoEl && entry.videoEl !== remoteVideo) {
            try { entry.videoEl.remove(); } catch (e) {}
        } else if (entry.videoEl === remoteVideo) {
            remoteVideo.srcObject = null;
        }
    }
    peers.clear();

    if (localStream) {
        localStream.getTracks().forEach(t => t.stop());
    }
    localStream = null;
    localVideo.srcObject = null;

    pendingOffer = null;
    callHost = null;
    isHost = false;
    currentMediaKind = null;
    localMicMuted = false;

    remoteVideo.srcObject = null;
}

function updateCallStatusText() {
    if (callState === 'idle') {
        if (!selectedContact) {
            callStatus.textContent = 'Bitte einen Kontakt auswählen.';
        } else {
            callStatus.textContent = `Bereit für Call mit ${selectedContact}`;
        }
        return;
    }
    if (callState === 'calling') {
        const names = Array.from(peers.keys());
        callStatus.textContent = `Rufe an: ${names.join(', ')}`;
        return;
    }
    if (callState === 'incoming') {
        callStatus.textContent = 'Eingehender Anruf…';
        return;
    }
    if (callState === 'in_call') {
        const names = Array.from(peers.keys());
        if (isHost) {
            callStatus.textContent = `Im Gespräch mit: ${names.join(', ')}`;
        } else {
            callStatus.textContent = `Im Gespräch mit Host: ${callHost || (names[0] || 'Unbekannt')}`;
        }
    }
}

function updateCallUI() {
    const target = getSelectedContact();

    const showOverlay = callState === 'calling' || callState === 'in_call' || callState === 'incoming';
    callOverlay.classList.toggle('call-overlay-active', showOverlay);

    const canStartCall = !!target && callState === 'idle';
    btnChatAudio.disabled = !canStartCall;
    btnChatVideo.disabled = !canStartCall;

    deviceRowCall.style.display      = 'none';
    callControlsRow.style.display    = 'none';
    addParticipantRow.style.display  = 'none';
    incomingPanel.hidden             = true;
    btnMicToggle.style.display       = 'none';
    btnHangup.style.display          = 'none';

    if (callState === 'incoming') {
        incomingPanel.hidden = false;
    } else if (callState === 'calling') {
        callControlsRow.style.display = 'flex';
        btnHangup.style.display       = 'flex';
    } else if (callState === 'in_call') {
        deviceRowCall.style.display   = 'flex';
        callControlsRow.style.display = 'flex';
        btnMicToggle.style.display    = 'flex';
        btnHangup.style.display       = 'flex';
        if (isHost) {
            addParticipantRow.style.display = 'flex';
        }
    }

    updateCallStatusText();
}

function createPeerConnectionFor(remoteUser) {
    const pc = new RTCPeerConnection(iceConfig);

    let videoEl;
    if (!peers.size) {
        videoEl = remoteVideo;
    } else {
        videoEl = document.createElement('video');
        videoEl.autoplay = true;
        videoEl.playsInline = true;
        videoEl.className = 'remote-video-extra';
        remoteVideo.parentNode.appendChild(videoEl);
    }

    peers.set(remoteUser, { pc, videoEl });

    pc.onicecandidate = evt => {
        if (evt.candidate) {
            sendSignal(remoteUser, 'ice', { candidate: evt.candidate }).catch(console.error);
        }
    };

    pc.ontrack = evt => {
        if (evt.streams && evt.streams[0]) {
            videoEl.srcObject = evt.streams[0];
        }
    };

    pc.onconnectionstatechange = () => {
        const state = pc.connectionState;
        if (state === 'connected') {
            updateCallStatusText();
        } else if (state === 'failed' || state === 'closed') {
            peers.delete(remoteUser);
            if (videoEl && videoEl !== remoteVideo) {
                try { videoEl.remove(); } catch (e) {}
            } else if (videoEl === remoteVideo) {
                remoteVideo.srcObject = null;
            }
            if (!peers.size) {
                resetCallState();
                callState = 'idle';
            }
            updateCallUI();
        }
    };

    return pc;
}

async function startOutgoingCall(kind) {
    const target = getSelectedContact();
    if (!target) {
        alert('Bitte zuerst einen Kontakt ausw&auml;hlen.');
        return;
    }
    if (!currentUser) {
        alert('Nicht eingeloggt.');
        return;
    }

    resetCallState();
    isHost = true;
    callHost = currentUser;
    callState = 'calling';
    currentMediaKind = kind;
    callTitleName.textContent = target;
    updateCallUI();

    try {
        await openLocalMedia(kind);

        const pc = createPeerConnectionFor(target);
        localStream.getTracks().forEach(track => pc.addTrack(track, localStream));

        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);

        await sendSignal(target, 'offer', {
            media: kind,
            sdp: offer,
            host: currentUser
        });

    } catch (e) {
        console.error(e);
        alert('Fehler beim Starten des Calls: ' + e.message);
        resetCallState();
        callState = 'idle';
        updateCallUI();
    }
}

async function addParticipantToCurrentCall() {
    if (!isHost || callState !== 'in_call') return;
    const newUser = addParticipantSel.value;
    if (!newUser) {
        alert('Bitte einen Teilnehmer ausw&auml;hlen.');
        return;
    }
    if (peers.has(newUser)) {
        alert('Benutzer ist bereits im Call.');
        return;
    }

    try {
        if (!localStream) {
            await openLocalMedia(currentMediaKind || 'video');
        }
        const pc = createPeerConnectionFor(newUser);
        localStream.getTracks().forEach(track => pc.addTrack(track, localStream));

        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);

        await sendSignal(newUser, 'offer', {
            media: currentMediaKind || 'video',
            sdp: offer,
            host: currentUser,
            multi: true
        });

        updateCallStatusText();
        updateCallUI();
    } catch (e) {
        console.error(e);
        alert('Fehler beim Hinzuf&uuml;gen: ' + e.message);
    }
}

async function handleIncomingOffer(from, data) {
    if (callState !== 'idle' && !peers.has(from)) {
        await sendSignal(from, 'bye', { reason: 'busy' }).catch(console.error);
        return;
    }

    pendingOffer = { from, data };
    callState = 'incoming';
    callHost = data.host || from;
    isHost = (callHost === currentUser);
    currentMediaKind = data.media;
    callTitleName.textContent = callHost === currentUser ? from : callHost;
    incomingText.textContent = `Eingehender ${data.media === 'audio' ? 'Audio' : 'Video'}‑Call von ${from}`;
    updateCallUI();
}

async function acceptIncomingOffer() {
    if (!pendingOffer) return;
    const { from, data } = pendingOffer;
    pendingOffer = null;

    resetCallState();
    callState = 'in_call';
    callHost = data.host || from;
    isHost = (callHost === currentUser);
    currentMediaKind = data.media;
    callTitleName.textContent = callHost === currentUser ? from : callHost;
    updateCallUI();

    try {
        await openLocalMedia(currentMediaKind);

        const pc = createPeerConnectionFor(from);
        localStream.getTracks().forEach(track => pc.addTrack(track, localStream));

        await pc.setRemoteDescription(new RTCSessionDescription(data.sdp));
        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);

        await sendSignal(from, 'answer', {
            media: currentMediaKind,
            sdp: answer,
            host: callHost
        });

        updateCallStatusText();
        updateCallUI();
    } catch (e) {
        console.error(e);
        alert('Fehler beim Annehmen des Calls: ' + e.message);
        resetCallState();
        callState = 'idle';
        updateCallUI();
    }
}

function rejectIncomingOffer() {
    if (!pendingOffer) return;
    const { from } = pendingOffer;
    pendingOffer = null;
    sendSignal(from, 'bye', { reason: 'rejected' }).catch(console.error);
    callState = 'idle';
    callStatus.textContent = 'Call abgelehnt';
    updateCallUI();
}

async function handleIncomingAnswer(from, data) {
    const entry = peers.get(from);
    if (!entry) return;
    try {
        await entry.pc.setRemoteDescription(new RTCSessionDescription(data.sdp));
        callState = 'in_call';
        currentMediaKind = data.media;
        updateCallStatusText();
        updateCallUI();
    } catch (e) {
        console.error(e);
    }
}

async function handleIncomingIce(from, data) {
    const entry = peers.get(from);
    if (!entry) return;
    if (!data.candidate) return;
    try {
        await entry.pc.addIceCandidate(new RTCIceCandidate(data.candidate));
    } catch (e) {
        console.error('ICE error', e);
    }
}

function handleIncomingBye(from, data) {
    const entry = peers.get(from);
    if (!entry) return;
    peers.delete(from);
    if (entry.videoEl && entry.videoEl !== remoteVideo) {
        try { entry.videoEl.remove(); } catch (e) {}
    } else if (entry.videoEl === remoteVideo) {
        remoteVideo.srcObject = null;
    }
    if (!peers.size) {
        resetCallState();
        callState = 'idle';
    }
    updateCallUI();
}

async function hangup() {
    for (const [u] of peers) {
        await sendSignal(u, 'bye', { reason: 'hangup' }).catch(console.error);
    }
    resetCallState();
    callState = 'idle';
    callStatus.textContent = 'Call beendet';
    updateCallUI();
}

async function sendSignal(to, kind, data) {
    if (!currentUser) throw new Error('Nicht eingeloggt');
    const payload = {
        type: 'signal',
        signalKind: kind,
        from: currentUser,
        to,
        data
    };
    const plain = JSON.stringify(payload);
    const enc = await encryptForUser(to, plain);
    const sig = await signPayload(plain);
    const blob = {
        from: currentUser,
        to,
        encKey: enc.encKeyB64,
        iv: enc.ivB64,
        ciphertext: enc.cipherB64,
        signature: sig
    };
    const res = await apiCall('signal-send', {
        to,
        blob: JSON.stringify(blob),
        csrf: CSRF
    }, 'POST');
    if (!res.ok) {
        console.error('signal-send failed', res);
        throw new Error('signal-send failed');
    }
}

async function pollSignals() {
    if (!currentUser) return;
    const res = await apiCall('signal-poll', null, 'GET');
    if (!res.ok || !Array.isArray(res.signals)) return;

    for (const line of res.signals) {
        let parsed;
        try { parsed = JSON.parse(line); } catch (e) { continue; }
        if (!parsed || !parsed.ciphertext || !parsed.encKey || !parsed.iv || !parsed.from) continue;

        const from = parsed.from;
        try {
            const plain = await decryptFromBlob({
                encKey: parsed.encKey,
                iv: parsed.iv,
                ciphertext: parsed.ciphertext
            });
            const valid = await verifySignatureFromUser(from, plain, parsed.signature || '');
            if (!valid) {
                console.warn('Signal-Signatur ungültig von', from);
                continue;
            }
            const payload = JSON.parse(plain);
            if (payload.type !== 'signal') continue;
            const kind = payload.signalKind;
            const data = payload.data;

            if (kind === 'offer') {
                await handleIncomingOffer(from, data);
            } else if (kind === 'answer') {
                await handleIncomingAnswer(from, data);
            } else if (kind === 'ice') {
                await handleIncomingIce(from, data);
            } else if (kind === 'bye') {
                handleIncomingBye(from, data);
            }
        } catch (e) {
            console.error('signal decrypt/handle error', e);
        }
    }
}

function startSignalPolling() {
    if (signalPollTimer) clearInterval(signalPollTimer);
    signalPollTimer = setInterval(() => {
        pollSignals().catch(console.error);
    }, 2000);
}

function stopSignalPolling() {
    if (signalPollTimer) {
        clearInterval(signalPollTimer);
        signalPollTimer = null;
    }
}

/* Teilnehmer-Hinzuf&uuml;gen-Auswahl */

function refreshAddParticipantOptions() {
    addParticipantSel.innerHTML = '';
    if (!usersDirectory.length) return;

    const existing = new Set(peers.keys());
    existing.add(currentUser);

    const candidates = usersDirectory.filter(u => !existing.has(u.username));

    const optEmpty = document.createElement('option');
    optEmpty.value = '';
    optEmpty.textContent = 'Teilnehmer auswählen…';
    addParticipantSel.appendChild(optEmpty);

    for (const u of candidates) {
        const opt = document.createElement('option');
        opt.value = u.username;
        opt.textContent = u.username;
        addParticipantSel.appendChild(opt);
    }
}

/* Mic Toggle */

function updateMicButton() {
    btnMicToggle.textContent = localMicMuted ? '🔇' : '🎙';
}

btnMicToggle.addEventListener('click', () => {
    if (!localStream) return;
    localMicMuted = !localMicMuted;
    localStream.getAudioTracks().forEach(t => t.enabled = !localMicMuted);
    updateMicButton();
});

/* Buttons f&uuml;r Call */

btnChatVideo.addEventListener('click', () => {
    if (callState !== 'idle') return;
    currentMediaKind = 'video';
    startOutgoingCall('video').catch(console.error);
});
btnChatAudio.addEventListener('click', () => {
    if (callState !== 'idle') return;
    currentMediaKind = 'audio';
    startOutgoingCall('audio').catch(console.error);
});

btnHangup.addEventListener('click', () => {
    hangup().catch(console.error);
});

btnAccept.addEventListener('click', () => {
    acceptIncomingOffer().catch(console.error);
});
btnReject.addEventListener('click', () => {
    rejectIncomingOffer();
});

btnAddParticipant.addEventListener('click', () => {
    addParticipantToCurrentCall().catch(console.error);
});

btnCallClose.addEventListener('click', () => {
    hangup().catch(console.error);
});

/* ======================
   Auth / Registrierung
   ====================== */

btnRegister.addEventListener('click', async () => {
    const username = regInput.value.trim();
    if (!/^[a-zA-Z0-9_-]{3,64}$/.test(username)) {
        alert('Ung&uuml;ltiger Benutzername');
        return;
    }
    try {
        const keys = await generateIdentityKeys();
        const payload = {
            username,
            pubSign: keys.pubSignPem,
            pubEnc: keys.pubEncPem,
            csrf: CSRF
        };
        const res = await apiCall('register', payload, 'POST');
        if (!res.ok) {
            alert('Registrierung fehlgeschlagen: ' + (res.error || res.raw || 'Unbekannter Fehler'));
            return;
        }
        const exportObj = {
            username,
            pubSign: keys.pubSignPem,
            pubEnc: keys.pubEncPem,
            privSign: keys.privSignPem,
            privEnc: keys.privEncPem,
            created: Date.now()
        };
        download(`${username}-secure-keys.json`, JSON.stringify(exportObj, null, 2));
        alert('Registrierung erfolgreich. Private Schl&uuml;ssel wurden heruntergeladen.');
    } catch (e) {
        console.error(e);
        alert('Fehler bei der Registrierung: ' + e.message);
    }
});

keyFile.addEventListener('change', async () => {
    authError.hidden = true;
    authError.textContent = '';
    if (!keyFile.files || keyFile.files.length === 0) {
        identityFileData = null;
        return;
    }
    const file = keyFile.files[0];
    const text = await file.text();
    try {
        const obj = JSON.parse(text);
        if (!obj.username || !obj.privSign || !obj.privEnc || !obj.pubSign || !obj.pubEnc) {
            throw new Error('Datei unvollst&auml;ndig');
        }
        identityFileData = obj;
        alert('Schl&uuml;sseldatei geladen f&uuml;r Benutzer: ' + obj.username);
    } catch (e) {
        identityFileData = null;
        alert('Ung&uuml;ltige Schl&uuml;ssel-Datei: ' + e.message);
    }
});

btnLogin.addEventListener('click', async () => {
    authError.hidden = true;
    authError.textContent = '';
    if (!identityFileData) {
        alert('Bitte zuerst eine Schl&uuml;ssel-Datei ausw&auml;hlen.');
        return;
    }
    try {
        // Demo-Relay: sorgt dafuer, dass der oeffentliche Schluessel des Nutzers
        // in diesem Browser bekannt ist (im Original haelt der Server das Verzeichnis).
        await apiCall('register', {
            username: identityFileData.username,
            pubSign: identityFileData.pubSign,
            pubEnc: identityFileData.pubEnc,
            csrf: CSRF
        }, 'POST');

        const payload = {
            username: identityFileData.username,
            pubSign: identityFileData.pubSign,
            csrf: CSRF
        };
        const res = await apiCall('login', payload, 'POST');
        if (!res.ok) {
            authError.hidden = false;
            authError.textContent = JSON.stringify(res, null, 2);
            alert('Login fehlgeschlagen (Details im Fehlerfeld).');
            return;
        }
        currentUser = res.user || identityFileData.username;
        currentUsernameLabel.textContent = currentUser;
        if (res.csrf) {
            CSRF = res.csrf;
        }
        await importPrivateKeysFromFileData(identityFileData);
        await refreshUserDirectory();
        await loadMyContacts();

        showPageApp();
        showSidebar(true);
        resetCallState();
        callState = 'idle';
        updateCallUI();
        allMessageLines = [];
        chatMessagesStore = [];
        startMessagePolling();
        startSignalPolling();
        refreshDeviceLists().catch(console.error);
    } catch (e) {
        authError.hidden = false;
        authError.textContent = e.message;
        alert('Fehler beim Login: ' + e.message);
    }
});

btnLogout.addEventListener('click', async () => {
    stopMessagePolling();
    stopSignalPolling();
    resetCallState();
    callState = 'idle';
    try {
        await apiCall('logout', { csrf: CSRF }, 'POST');
    } catch (e) {}
    currentUser = null;
    privSignKey = null;
    privEncKey  = null;
    usersDirectory = [];
    myContacts = [];
    identityFileData = null;
    selectedContact = null;
    contactList.innerHTML = '';
    chatMessagesStore = [];
    allMessageLines = [];
    chatMessages.innerHTML = '';
    chatContactName.textContent = '– kein Kontakt –';
    chatContactSub.textContent  = 'Wähle einen Kontakt aus';
    chatAvatar.textContent = '?';
    showPageAuth();
});

/* Initial */
showPageAuth();
resetCallState();
callState = 'idle';
updateCallUI();
</script>

</body>
</html>
