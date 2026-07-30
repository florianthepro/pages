# Architektur

## Integration (Instance-Format des Repos)

```
content/instances/liarc.php     eine Datei, wird als index.php auf den Server kopiert
                                enthaelt nur die anpassbaren Variablen (Titel, Branch, Datenpfad)
content/routes/liarc/           Funktionen (via Loader von GitHub raw geladen, tmp-Cache)
  lib.php                       Kern: crypto, store, auth, devices, vault, i18n, ui
                                + liarc_groups()/liarc_categories(): hier Gruppen/Kategorien/Felder aendern
  index.php                     Hauptseite (Gruppen-Tabs, Kategorie-Chips, Inhalt)
                                + Dispatcher fuer huebsche URLs (/login, /api/...)
  auth.php                      login/register/logout/install (?v=)
  data.php                      Schreib-Endpunkte Web (POST, ?do=)
  devices.php  settings.php     Geraete, Einstellungen
  api.php                       JSON-API
  assets.php                    css/js/manifest/icons same-origin, ETag + 7 Tage Cache
  app.css  app.js  manifest.webmanifest
  lang-de.php  lang-en.php  lang-th.php
content/media/liarc/*.svg       Icons (weisse Linien, 24x24) + sprite.svg (alle in einer Datei)
content/docs/liarc/             diese Dateien
```

## .htaccess (automatisch)

Beim ersten Aufruf legt die App selbst an:
- Webroot-.htaccess: alles Nichtexistente auf index.php (huebsche URLs),
  Datenverzeichnis gesperrt, nosniff-Header.
- liarc-data/.htaccess: Require all denied.

Nutzerdaten liegen NUR lokal unter `$liarc_datadir` (Default `liarc-data/` neben index.php).

## Performance

- Icons als ein Sprite (media/liarc/sprite.svg), inline im HTML: keine Einzelrequests.
- lib/lang/sprite/assets werden 1 Tag lokal gecached (Loader-Cache), `?_refresh=1` erzwingt neu.
- assets.php sendet ETag + Cache-Control 7 Tage.
- Nach dem Aendern von Einzel-Icons sprite.svg neu bauen (Symbole `i-<name>`).

## Ansicht / URLs

- Auto-Erkennung PC/Handy per CSS-Breakpoint: ab 920px Seitenleiste
  (Gruppen + Kategorien, Footer-Icons), darunter Topbar + Tabs/Chips.
- Sichtbare URL bleibt sauber: app.js ersetzt die Adresse nach dem Laden
  durch den Pfad (/, /devices, /login, ...); die aktuelle Kategorie steht im
  Cookie `liarc_view`, "/" oeffnet also immer die letzte Ansicht.
- Direktpfade funktionieren trotzdem: /health (Gruppe), /heart (Kategorie),
  /login usw. werden von liarc_pretty_route() aufgeloest.

## Gruppen & Kategorien (fest im Code)

Nutzer legen keine Kategorien an. Definition in lib.php:

- Gruppen: contacts (Kontakte), health (Gesundheit), security (Sicherheit), misc (Mehr)
- Kategorien: contacts (mit "Ich"-Markierung wie iOS, genau ein Eintrag), phones,
  heart, weight, height, steps, sleep, temp, medical,
  passwords (Dienst/Benutzer/Passwort/MFA), certs (Lizenzen/Zertifikate),
  serials (Geraete-Seriennummern), documents, notes
- kind: series (Zahlen ueber Zeit, Statistik + Diagramm) | records (Felder, Status aktiv/alt)
- Feldtypen: text, number, date (zeigt berechnete Jahre), phone, note,
  secret (maskiert, per Tipp aufdecken)

Vault pro Nutzer (eine verschluesselte JSON-Datei): `{entries: {catKey: [...]}}`.

## Verschluesselung

- Pro Nutzer ein 32-Byte-DEK, verschluesselt den Vault (AES-256-GCM, Fallback sodium).
- DEK nie im Klartext gespeichert, nur gewrappt:
  Passwort → PBKDF2-SHA256 (310k) in auth.json; pro Geraet → HKDF(Secret) in devices.json.
- Passwort zusaetzlich als password_hash (bcrypt) zum Verifizieren.
- Passwortverlust = Datenverlust (bewusst).

## Geraete / Sessions

- Nach Web-Login legt app.js einen Geraeteschluessel an (`liarc_<id>_<secret>`,
  localStorage). Wiederanmeldung ohne Passwort ueber POST /api/auth/device.
- Serverseitig nur SHA-256-Hash des Secrets + DEK-Wrap; Loeschen = Geraet sofort tot.
- PHP-Sessions unter data/sessions, Cookie HttpOnly, SameSite=Lax, Secure bei HTTPS, 30 Tage.
- Logout widerruft den Geraeteschluessel des aktuellen Geraets.

## Sicherheit (Defaults)

CSP self-only (kein Inline-JS/CSS), CSRF fuer alle Web-POSTs, Bearer-Token fuer API,
Rate-Limits pro IP, atomare Writes (tmp+rename) mit .bak und Lock pro Nutzer,
Nutzerverzeichnisse als Hash des Namens.
