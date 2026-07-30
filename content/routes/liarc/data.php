<?php
declare(strict_types=1);
if (!defined('LIARC_LIB')) {
    require function_exists('app_get_local_script')
        ? app_get_local_script(($liarc_repo ?? 'https://raw.githubusercontent.com/florianthepro/pages/main/content').'/routes/liarc/lib.php', isset($_GET['_refresh']) && $_GET['_refresh'] === '1', 300)
        : __DIR__.'/lib.php';
}
liarc_boot(get_defined_vars());
liarc_i18n_init();

// Schreib-Endpunkte des Webinterfaces (nur POST, mit CSRF)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') liarc_redirect();
$user = liarc_require_user();
liarc_csrf_check();

$do = (string)($_GET['do'] ?? '');
$catId = (string)($_GET['cat'] ?? '');
$entryId = (string)($_GET['id'] ?? '');

if ($do === 'provision') {
    $name = liarc_cut(trim((string)($_POST['name'] ?? 'Web')), 80);
    $dev = liarc_device_create($user['uid'], $user['dek'], $name !== '' ? $name : 'Web', 'web');
    liarc_session_start();
    $_SESSION['device'] = $dev['id'];
    liarc_json(['ok' => true, 'token' => $dev['token'], 'username' => liarc_username($user['uid'])]);
}

$lock = liarc_user_lock($user['uid']);
$vault = liarc_vault_load($user['uid'], $user['dek']);
if ($vault === null) { liarc_user_unlock($lock); liarc_redirect(); }

switch ($do) {
    case 'entry_add':
        $cat = liarc_category_get($vault, $catId);
        if ($cat === null) break;
        $res = liarc_entry_build($cat, $_POST);
        if (isset($res['error'])) {
            liarc_user_unlock($lock);
            liarc_redirect('index', ['cat' => $catId, 'error' => $res['error']]);
        }
        $vault['entries'][$catId][] = $res['entry'];
        liarc_vault_save($user['uid'], $user['dek'], $vault);
        break;

    case 'entry_status':
        foreach ($vault['entries'][$catId] ?? [] as &$e) {
            if ($e['id'] === $entryId) {
                $e['status'] = ($e['status'] ?? 'active') === 'active' ? 'old' : 'active';
                $e['updated'] = liarc_now();
                break;
            }
        }
        unset($e);
        liarc_vault_save($user['uid'], $user['dek'], $vault);
        break;

    case 'entry_del':
        $vault['entries'][$catId] = array_values(array_filter(
            $vault['entries'][$catId] ?? [], fn($e) => $e['id'] !== $entryId
        ));
        liarc_vault_save($user['uid'], $user['dek'], $vault);
        break;

    case 'cat_add':
        $fields = [];
        for ($i = 0; $i < 12; $i++) {
            $label = trim((string)($_POST['field_label_'.$i] ?? ''));
            if ($label === '') continue;
            $fields[] = ['label' => $label, 'type' => (string)($_POST['field_type_'.$i] ?? 'text')];
        }
        $res = liarc_category_normalize([
            'name' => $_POST['name'] ?? '', 'kind' => $_POST['kind'] ?? '',
            'unit' => $_POST['unit'] ?? '', 'fields' => $fields,
        ]);
        if (isset($res['error'])) {
            liarc_user_unlock($lock);
            liarc_redirect('index', ['error' => $res['error']]);
        }
        $vault['categories'][] = $res['category'];
        liarc_vault_save($user['uid'], $user['dek'], $vault);
        $catId = $res['category']['id'];
        break;

    case 'cat_del':
        // nur selbst angelegte Kategorien (key=null), Standardbereiche bleiben
        $cat = liarc_category_get($vault, $catId);
        if ($cat !== null && ($cat['key'] ?? null) === null) {
            $vault['categories'] = array_values(array_filter($vault['categories'], fn($c) => $c['id'] !== $catId));
            unset($vault['entries'][$catId]);
            liarc_vault_save($user['uid'], $user['dek'], $vault);
            $catId = '';
        }
        break;
}

liarc_user_unlock($lock);
$catId !== '' ? liarc_redirect('index', ['cat' => $catId]) : liarc_redirect();