# Launcher-Icons

Icons für die Kacheln des Launchers. Die Zuordnung läuft **über den Namen**:

```
Dateiname (ohne Endung) == Kachel-Titel
```

Beispiel: Die Kachel `GitHub` sucht ihr Icon unter `GitHub.ico` in diesem Ordner.

## Konfiguration (in der Instanz)

In `content/instances/launcher.php`:

- `$launcher_iconbase` – Basis-URL dieses Ordners (mit oder ohne `/` am Ende).
- `$launcher_iconext` – Dateiendung der Icons (Standard: `.ico`, z. B. auch `.png`, `.svg`).

Die Engine baut die Icon-URL so:

```
$launcher_iconbase + encodeURIComponent(Titel) + $launcher_iconext
```

## Fallback-Kette

Fehlt ein Icon in diesem Ordner, greift die Engine automatisch:

1. Icon aus diesem Ordner (`Titel` + Endung)
2. Favicon der Ziel-Seite (Google-Favicon-Dienst)
3. Generierte Initialen-Kachel

## Eigenes Icon pro Kachel überschreiben

Optional lässt sich in `$launcher_links` je Eintrag ein `icon` setzen. Das darf sein:

- ein reiner Dateiname → relativ zu `$launcher_iconbase`
- eine absolute `https://…`- oder `data:`-URL
- ein absoluter Pfad (beginnt mit `/`)

Ist `icon` gesetzt, hat es Vorrang vor der Namens-Zuordnung.
