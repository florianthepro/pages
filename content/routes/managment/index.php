<?php
declare(strict_types=1);
# Geteilte Variablen aus der Instanz defensiv einlesen (Muster wie launcher-v2).
$managment_theme=(isset($managment_theme)&&in_array($managment_theme,['light','dark'],true))?$managment_theme:'auto';
$managment_title=(isset($managment_title)&&$managment_title!=='')?(string)$managment_title:'Baustellenverwaltung – Login';
$managment_icon=(isset($managment_icon)&&is_string($managment_icon)&&$managment_icon!=='')?$managment_icon:'https://raw.githubusercontent.com/florianthepro/pages/main/content/media/managment/index.svg';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

# Kein echtes Backend vorhanden: bei POST wird die Anmeldung NICHT geprueft.
# Wir zeigen nur einen neutralen Hinweis und taeuschen keine Authentifizierung vor.
$managment_posted=(($_SERVER['REQUEST_METHOD']??'')==='POST');

$dt=$managment_theme==='light'?' data-theme="light"':($managment_theme==='dark'?' data-theme="dark"':'');
?>
<!doctype html>
<html lang="de"<?= $dt ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($managment_title) ?></title>
<link rel="icon" href="<?= h($managment_icon) ?>">
<style>
/* Inline uebernommen aus der originalen public/css/style.css (managment.xo.je). */
body {
    margin: 0;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background-color: #f5f5f5;
}

.login-page,
.main-page {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
}

.login-container {
    background: #ffffff;
    padding: 2rem 2.5rem;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    width: 100%;
    max-width: 520px;
}

.login-container h1 {
    margin-top: 0;
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
    text-align: center;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.25rem;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    border: 1px solid #ccc;
}

button {
    width: 100%;
    padding: 0.6rem 0.75rem;
    border-radius: 4px;
    border: none;
    background-color: #0078d4;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}

button:hover {
    background-color: #0063b1;
}

.alert {
    padding: 0.75rem 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.alert-error {
    background-color: #fde7e9;
    color: #a80000;
}

nav ul {
    list-style: none;
    padding-left: 0;
    margin: 0 0 0.5rem 0;
}

nav li {
    display: inline-block;
    margin-right: 0.75rem;
}

nav a {
    color: #0078d4;
    text-decoration: none;
    font-size: 0.9rem;
}

nav a:hover {
    text-decoration: underline;
}
</style>
</head>
<body class="login-page">
<div class="login-container">
    <h1><?= h($managment_title) ?></h1>
<?php if($managment_posted): ?>
    <!-- Kein Backend in dieser Nachbildung: es findet keine Anmeldepruefung statt. -->
    <div class="alert alert-error">
        Eine echte Anmeldung ist in dieser Nachbildung nicht moeglich. Die Pruefung von
        E-Mail und Passwort erfordert das Server-Backend der Baustellenverwaltung, das hier
        nicht verfuegbar ist.
    </div>
<?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label for="email">E-Mail</label>
            <input type="email" name="email" id="email" required autocomplete="username">
        </div>
        <div class="form-group">
            <label for="password">Passwort</label>
            <input type="password" name="password" id="password" required autocomplete="current-password">
        </div>
        <button type="submit">Anmelden</button>
    </form>
</div>
</body>
</html>
