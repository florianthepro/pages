# Hinweise für künftige Agents

LIARC folgt dem Instance-Format dieses Repos (Vorbild: csv-reporting):
Instance in `content/instances/liarc.php`, Funktionen in `content/routes/liarc/`,
Icons in `content/media/liarc/`, Doku hier. Nutzerdaten liegen nur lokal auf dem
Zielserver (`liarc-data/`), nie im Repo.

## Harte Regeln

- Nur Apache + PHP-Standardumfang. Keine Pakete, keine DB, keine externen CDNs.
- Route-Dateien laufen über den Repo-Loader (`?_page=`), laden `lib.php` über
  `app_get_local_script` und beginnen alle mit demselben Boot-Block.
- Krypto/Auth nur in lib.php ändern; DEK nie unverschlüsselt persistieren.
- Kein Inline-JS/-CSS (CSP self-only). Assets über assets.php, Daten via
  `<script type="application/json">` oder data-Attribute.
- Web-POSTs mit CSRF (`liarc_csrf_check`), API mit Bearer (`liarc_api_auth`).
- Alle Strings über `t('key')` + alle drei lang-Dateien pflegen. So wenig Text
  wie möglich; Bedienung über Icons (weiße Linien-SVGs, 24x24, stroke 1.8).
- Icons werden als ein Sprite eingebettet: nach Icon-Änderungen
  media/liarc/sprite.svg neu bauen (Symbole `i-<name>`).
- Gruppen/Kategorien sind fest in lib.php (liarc_groups/liarc_categories);
  Nutzer legen keine an. Die Instance-Datei bleibt minimal (nur Variablen).
- Design dunkel und einheitlich; keine Erklärtexte im Interface.


## Nach Änderungen

- `php -l` auf geänderte Dateien.
- Neue Route: yaml in der Instance ergänzen (und Mapping in liarc_pretty_route).
- Neues Icon: SVG in media/liarc/, Name = `ic('name')` = assets f=icon-name.
- Smoke-Test: Registrierung, Eintrag, Chart, API-Token, Geräte-Widerruf.
- Achtung Cache: Loader cached 300s; `?_refresh=1` erzwingt Neuladen.
