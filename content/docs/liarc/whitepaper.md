# LIARC – Whitepaper

## Was ist LIARC

LIARC (LIfe ARChive, liarc.org) ist eine persönliche, verschlüsselte Lebensdatenbank
mit Webinterface und API. Ein Nutzer erfasst alle Werte seines Lebens in festen
Gruppen: Kontakte (ein Kontakt ist als „Ich" markierbar, wie iOS), Gesundheit
(Herz, Gewicht, Größe, Schritte, Schlaf, Temperatur, Medizin), Sicherheit
(Passwörter, MFA, Lizenzen/Zertifikate, Geräte-Seriennummern) und Mehr
(Dokumente, Notizen). Alles ist von Anfang an da – Nutzer legen keine
Kategorien an; angepasst wird zentral im Code (lib.php).

Frühere Arbeitsnamen: DSL (dynamic-secure-life), dann LIA (Domain nicht frei).

## Kernideen

- Zwei Datenarten: Messreihen (Zahlen über Zeit, mit Statistik und Diagramm)
  und Einträge (Datensätze mit Feldern und Status aktiv/alt).
- Nichts wird gelöscht: Bearbeiten erzeugt eine neue Version, die alte wandert
  ins Archiv (Status alt, nie in Statistiken). Einzige echte Löschung: der Account.
- Getrennte Oberflächen: Handy = App-Aufbau (Startliste, Unteransichten,
  Tab-Leiste), PC = Seitenleiste – nie beides zugleich.
- Datumsfelder rechnen mit (Geburtsdatum → Alter).
- Alle Nutzdaten verschlüsselt; Schlüssel nur aus Passwort oder Geräteschlüssel ableitbar.
- Keine Interaktion zwischen Nutzern.
- API parallel zum Webinterface, gleiches Datenmodell.
- Handy: Home-Webapp, dauerhaft angemeldet per Geräteschlüssel, Geräte einzeln löschbar.
- Interface: dunkel, einheitlich, icongeführt – so wenig Text, dass man ohne lesen zurechtkommt.

## Anforderungen (Abgrenzung zu typischen DMS)

Paperless (dokumentenzentriert, schwerer Stack), Nextcloud (Dateien, Mehrbenutzer),
Grocy u.ä. (domänenspezifisch) passen nicht. Daraus abgeleitet:

1. Läuft auf jedem Apache-Hosting mit PHP 8.1+, keine Pakete, keine Datenbank
   (die Instance prüft die PHP-Version und meldet zu alte klar statt 500).
2. Alle Lebensbereiche fest vorkonfiguriert, Anpassung nur zentral im Code.
3. Verschlüsselung als Default, keine Konfiguration.
4. Deployment = eine Datei kopieren (Instance-Format des Repos); .htaccess
   fuer Routing und Datenschutz legt die App selbst an.
5. API von Anfang an.
6. Mehrsprachig (de, en, th), Sprachen als separate Dateien.

## Nicht-Ziele

Kein Teilen, kein Dateiupload, keine Auswertung über Nutzergrenzen.
