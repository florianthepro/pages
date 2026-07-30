# Architektur

## Integration (Instance-Format des Repos)

```
content/instances/liarc.php     eine Datei, wird als index.php auf den Server kopiert
content/routes/liarc/           Funktionen (via Loader von GitHub raw geladen, tmp-Cache)
  lib.php                       Kern: crypto, store, auth, devices, vault, i18n, ui
  index.php                     Hauptseite (Kategorieleiste + Inhalt)
  auth.php                      login/register/logout/install (?v=)
  data.php                      Schreib-Endpunkte Web (POST, ?do=)
  devices.php  settings.php     Geräte, Einstellungen
  api.php                       JSON-API
  assets.php                    liefert css/js/manifest/icons same-origin aus
  app.css  app.js  manifest.webmanifest
  lang-de.php  lang-en.php  lang-th.php
content/media/liarc/*.svg       Icons (weiße Linien, 24x24)
content/docs/liarc/             diese Dateien
```

Die Instance definiert Config (`$liarc_datadir`, `$liarc_repo`, Branch) und die
YAML-Routenliste; der Repo-Loader (`content/loader/loader.php`) lädt und cached
die Route-Dateien. Route-Dateien laden `lib.php` über denselben Cache.

Nutzerdaten liegen NUR lokal auf dem Server unter `$liarc_datadir`
(Default: neben index.php, `liarc-data/`). Das Verzeichnis wird beim ersten
Start angelegt und automatisch per .htaccess gesperrt.

## Routing

`?_page=<route>` (Loader-Standard). Optional hübsche URLs (/login, /api/...):
die Instance mappt Pfade auf `_page`, wenn eine .htaccess alles auf index.php leitet:

```
Options -Indexes
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

## Datenmodell

Pro Nutzer ein Vault (eine verschlüsselte JSON-Datei):

- categories[]: id, key (Default-Bereich, i18n) oder name, icon, kind (series|records), unit, fields[]
- entries{catId: []}: series {id, value, at, note} / records {id, fields{}, status active|old}

12 Default-Bereiche werden bei Registrierung angelegt (profile, people, phones,
heart, weight, height, steps, sleep, temp, medical, documents, notes).
Default-Bereiche sind nicht löschbar; eigene Kategorien schon.
Feldtypen: text, number, date, phone, note. Datum zeigt berechnete Jahre.

## Verschlüsselung

- Pro Nutzer ein 32-Byte-DEK, verschlüsselt den Vault (AES-256-GCM, Fallback sodium).
- DEK nie im Klartext gespeichert, nur gewrappt:
  Passwort → PBKDF2-SHA256 (310k) in auth.json; pro Gerät → HKDF(Secret) in devices.json.
- Passwort zusätzlich als password_hash (bcrypt) zum Verifizieren.
- Passwortverlust = Datenverlust (bewusst).

## Geräte / Sessions

- Nach Web-Login legt app.js einen Geräteschlüssel an (`liarc_<id>_<secret>`,
  localStorage). Wiederanmeldung ohne Passwort über POST api p=auth/device.
- Serverseitig nur SHA-256-Hash des Secrets + DEK-Wrap; Löschen = Gerät sofort tot.
- PHP-Sessions unter data/sessions, Cookie HttpOnly, SameSite=Lax, Secure bei HTTPS, 30 Tage.
- Logout widerruft den Geräteschlüssel des aktuellen Geräts.

## Sicherheit (Defaults)

CSP self-only (kein Inline-JS/CSS, Assets über assets.php), CSRF für alle
Web-POSTs, Bearer-Token für API, Rate-Limits pro IP, atomare Writes (tmp+rename)
mit .bak und Lock pro Nutzer, Nutzerverzeichnisse als Hash des Namens.
