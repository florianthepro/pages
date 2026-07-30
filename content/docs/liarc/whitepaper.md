# LIARC – Whitepaper

## Was ist LIARC

LIARC (LIfe ARChive, liarc.org) ist eine persönliche, verschlüsselte Lebensdatenbank
mit Webinterface und API. Ein Nutzer erfasst alle Werte seines Lebens: Profil,
Menschen, Telefonnummern, Herzfrequenz, Gewicht, Größe, Schritte, Schlaf,
Temperatur, Medizinisches, Dokumente, Notizen – alle Bereiche sind von Anfang an
da, niemand muss erst Kategorien anlegen.

Frühere Arbeitsnamen: DSL (dynamic-secure-life), dann LIA (Domain nicht frei).

## Kernideen

- Zwei Datenarten: Messreihen (Zahlen über Zeit, mit Statistik und Diagramm)
  und Einträge (Datensätze mit Feldern und Status aktiv/alt).
- Als alt markierte Einträge (z.B. alte Nummern) erscheinen als Liste, nie in Statistiken.
- Datumsfelder rechnen mit (Geburtsdatum → Alter).
- Alle Nutzdaten verschlüsselt; Schlüssel nur aus Passwort oder Geräteschlüssel ableitbar.
- Keine Interaktion zwischen Nutzern.
- API parallel zum Webinterface, gleiches Datenmodell.
- Handy: Home-Webapp, dauerhaft angemeldet per Geräteschlüssel, Geräte einzeln löschbar.
- Interface: dunkel, einheitlich, icongeführt – so wenig Text, dass man ohne lesen zurechtkommt.

## Anforderungen (Abgrenzung zu typischen DMS)

Paperless (dokumentenzentriert, schwerer Stack), Nextcloud (Dateien, Mehrbenutzer),
Grocy u.ä. (domänenspezifisch) passen nicht. Daraus abgeleitet:

1. Läuft auf jedem Apache+PHP-Hosting, keine Pakete, keine Datenbank.
2. Alle Lebensbereiche vorkonfiguriert, eigene Kategorien optional.
3. Verschlüsselung als Default, keine Konfiguration.
4. Deployment = eine Datei kopieren (Instance-Format des Repos).
5. API von Anfang an.
6. Mehrsprachig (de, en, th), Sprachen als separate Dateien.

## Nicht-Ziele

Kein Teilen, kein Dateiupload, keine Auswertung über Nutzergrenzen.
