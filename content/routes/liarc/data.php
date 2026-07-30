<?php
declare(strict_types=1);
if (!defined('LIARC_LIB')) {
    require function_exists('app_get_local_script')
        ? app_get_local_script(($liarc_repo ?? 'https://raw.githubusercontent.com/florianthepro/pages/main/content').'/routes/liarc/lib.php', isset($_GET['_refresh']) && $_GET['_refresh'] === '1', 86400)
        : __DIR__.'/lib.php';
}
liarc_boot(get_defined_vars());
liarc_i18n_init();

// Schreib-Endpunkte des Webinterfaces (nur POST, mit CSRF)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') liarc_redirect();
$user = liarc_require_user();
liarc_csrf_check();

$do = (string)($_GET['do'] ?? '');
$catKey = (string)($_GET['cat'] ?? '');
$entryId = (string)($_GET['id'] ?? '');

if ($do === 'provision') {
    $name = liarc_cut(trim((string)($_POST['name'] ?? 'Web')), 80);
    $dev = liarc_device_create($user['uid'], $user['dek'], $name !== '' ? $name : 'Web', 'web');
    liarc_session_start();
    $_SESSION['device'] = $dev['id'];
    liarc_json(['ok' => true, 'token' => $dev['token'], 'username' => liarc_username($user['uid'])]);
}

$cat = liarc_category($catKey);
if ($cat === null) liarc_redirect();

$lock = liarc_user_lock($user['uid']);
$vault = liarc_vault_load($user['uid'], $user['dek']);
if ($vault === null) { liarc_user_unlock($lock); liarc_redirect(); }

switch ($do) {
    case 'entry_add':
        $res = liarc_entry_build($cat, $_POST);
        if (isset($res['error'])) {
            liarc_user_unlock($lock);
            liarc_redirect('index', ['cat' => $catKey, 'error' => $res['error']]);
        }
        $vault['entries'][$catKey][] = $res['entry'];
        liarc_vault_save($user['uid'], $user['dek'], $vault);
        break;

    case 'entry_status':
        if (isset($vault['entries'][$catKey])) {
            foreach ($vault['entries'][$catKey] as &$e) {
                if ($e['id'] === $entryId) {
                    $e['status'] = ($e['status'] ?? 'active') === 'active' ? 'old' : 'active';
                    $e['updated'] = liarc_now();
                    unset($e['me']);
                    break;
                }
            }
            unset($e);
            liarc_vault_save($user['uid'], $user['dek'], $vault);
        }
        break;

    case 'entry_me':
        if (liarc_entry_set_me($vault, $catKey, $entryId)) {
            liarc_vault_save($user['uid'], $user['dek'], $vault);
        }
        break;

    case 'entry_del':
        $vault['entries'][$catKey] = array_values(array_filter(
            $vault['entries'][$catKey] ?? [], fn($e) => $e['id'] !== $entryId
        ));
        liarc_vault_save($user['uid'], $user['dek'], $vault);
        break;
}

liarc_user_unlock($lock);
liarc_redirect('index', ['cat' => $catKey]);