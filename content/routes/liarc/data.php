<?php
declare(strict_types=1);
if (!defined('LIARC_LIB')) {
    require function_exists('app_get_local_script')
        ? app_get_local_script(($liarc_repo ?? 'https://raw.githubusercontent.com/florianthepro/pages/main/content').'/routes/liarc/lib.php', isset($_GET['_refresh']) && $_GET['_refresh'] === '1', 86400)
        : __DIR__.'/lib.php';
}
liarc_boot(get_defined_vars());
liarc_i18n_init();

$user = liarc_require_user();

// Export: kompletter Datenbestand als JSON-Download (nur lesend, per Link)
if (($_GET['do'] ?? '') === 'export' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $vault = liarc_vault_load($user['uid'], $user['dek']);
    if ($vault === null) liarc_redirect();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="liarc-export-'.date('Y-m-d').'.json"');
    echo json_encode(['liarc' => 1, 'exported' => date('c'),
        'username' => liarc_username($user['uid']), 'entries' => $vault['entries'] ?? []], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Schreib-Endpunkte des Webinterfaces (nur POST, mit CSRF)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') liarc_redirect();
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
if ($cat === null && $do !== 'import') liarc_redirect();

$lock = liarc_user_lock($user['uid']);
$vault = liarc_vault_load($user['uid'], $user['dek']);
if ($vault === null) { liarc_user_unlock($lock); liarc_redirect(); }

switch ($do) {
    case 'entry_add':
    case 'entry_save':
        $res = liarc_entry_build($cat, $_POST);
        if (isset($res['error'])) {
            liarc_user_unlock($lock);
            liarc_redirect('index', ['cat' => $catKey, 'error' => $res['error']]);
        }
        // mit id: bestehenden eintrag aktualisieren (id/erstellt/status/ich bleiben)
        $updated = false;
        if ($entryId !== '' && isset($vault['entries'][$catKey])) {
            foreach ($vault['entries'][$catKey] as &$e) {
                if ($e['id'] === $entryId) {
                    $keep = ['id' => $e['id'], 'created' => $e['created'],
                        'status' => $e['status'] ?? null, 'me' => $e['me'] ?? null];
                    $e = $res['entry'];
                    $e['id'] = $keep['id'];
                    $e['created'] = $keep['created'];
                    if ($keep['status'] !== null) $e['status'] = $keep['status'];
                    if (!empty($keep['me'])) $e['me'] = true;
                    $updated = true;
                    break;
                }
            }
            unset($e);
        }
        if (!$updated) $vault['entries'][$catKey][] = $res['entry'];
        liarc_vault_save($user['uid'], $user['dek'], $vault);
        break;

    case 'import':
        // ersetzt alle eintraege durch einen frueheren export (backup .bak bleibt)
        $raw = '';
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $raw = (string)file_get_contents($_FILES['file']['tmp_name']);
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || (int)($data['liarc'] ?? 0) !== 1 || !is_array($data['entries'] ?? null)) {
            liarc_user_unlock($lock);
            liarc_redirect('settings', ['error' => 'err.import']);
        }
        $clean = [];
        foreach ($data['entries'] as $ck => $list) {
            if (liarc_category((string)$ck) === null || !is_array($list)) continue;
            $clean[$ck] = array_values(array_filter($list, 'is_array'));
        }
        $vault['entries'] = $clean;
        liarc_vault_save($user['uid'], $user['dek'], $vault);
        liarc_user_unlock($lock);
        liarc_redirect('settings', ['ok' => '1']);

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