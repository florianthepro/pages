# Dark-Money — Vision

> Die Kernidee der Währung. Umgesetzt als **eine einzige `index.php`** (single file).

---

## Die Idee

Dark-Money ist eine Währung, die vollständig auf einer Website als **Wallet** lebt.

Man registriert sich mit seiner **eigenen öffentlichen Wallet-Adresse**. Daraus wird
eine **Konto-ID generiert**. Diese Konto-ID ist zugleich die gespeicherte Adresse:
**Adresse = Konto-ID**. Sobald eine Zahlung von dieser Adresse eingeht, steigt der
Wert der zugehörigen Konto-ID.

---

## Ablauf

1. **Registrierung** mit eigener öffentlicher Wallet-Adresse.
2. Es wird eine **Konto-ID generiert** (= gespeicherte Adresse).
3. Nutzer richtet **Passwort** und **MFA** (TOTP) ein.
4. Zur **Aktivierung** muss zunächst mindestens **10 $** eingezahlt werden
   (effektiv **11 $** wegen der **1 $** Gebühr).
5. Danach ist das Konto aktiv und die Seite ist ein **vollständiges Wallet**.
6. Jede eingehende Zahlung von der Adresse **erhöht den Wert der Konto-ID**.

---

## Oberfläche

- Die Oberfläche zeigt **nur USD**.
- **Prozente tauchen nie auf** (intern an Anteil/BTC gekoppelt, aber nie sichtbar).
- **BTC** erscheint **nur als Info** — wie viel USD der BTC auf der Plattform entspricht.

## Konstanter Wert (Gerade + Tages-Bremse)

Ziel ist **Wertkonstanz**: Wenn BTC oder der Dollar wegbricht, soll der Wert auf der
Plattform **gleich bleiben**.

1. Aus dem **Live-BTC-Kurs** wird per **Ausgleichsgerade** (Least-Squares über die
   Kurs-Samples) eine Gerade abgeleitet und im Jetzt ausgewertet — das ist das *Ziel*.
2. Der tatsächliche Plattform-Kurs ist ein **gebremster Anker**: er darf sich pro Tag
   nur um einen **gedeckelten Maximalbetrag** (Standard **2 %/Tag**) auf dieses Ziel
   zubewegen. Zwischen den Aktualisierungen bewegt er sich gar nicht.

Springt die Gerade also merklich nach oben oder unten (Crash oder Spike), wird die
Bewegung **abgebremst** — pro Tag ist nur die maximale Drift erlaubt. So bleibt der
Wert auf der Plattform effektiv konstant, unabhängig davon, was BTC oder der Dollar
kurzfristig machen.

---

## Technik (single file)

Alles steckt in `index.php`:

- **Ein File**, kein Framework, oldschool DEFCON-Terminal (schwarz, grün).
- **Selbst-Installation** beim ersten Start: legt `data/` (SQLite) und `.htaccess`
  (root + data) an.
- **Selbstheilung live**: bei jedem Request wird die `.htaccess`-Härtung geprüft und
  bei Entfernung sofort wiederhergestellt (mit Logeintrag).
- Gehärtet für Apache: Passwort-Hashing, TOTP-MFA, CSRF, Session-Hardening,
  Security-Header, gesperrtes `data/`-Verzeichnis.
- **Kein Admin-Konto. Keine Private Keys gespeichert.** Bestätigte Gutschriften nur
  über einen server-seitigen Ingest-Endpoint (`?ingest`) mit Key.

Aufruf: `index.php` auf einen PHP-8-Server (SQLite-PDO, ausgehendes HTTPS für den
Live-Kurs) legen und im Browser öffnen. `data/` und `.htaccess` werden zur Laufzeit
erzeugt und sind nicht Teil des Repos.
