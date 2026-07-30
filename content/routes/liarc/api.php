<?php
declare(strict_types=1);
if (!defined('LIARC_LIB')) {
    require function_exists('app_get_local_script')
        ? app_get_local_script(($liarc_repo ?? 'https://raw.githubusercontent.com/florianthepro/pages/main/content').'/routes/liarc/lib.php', isset($_GET['_refresh']) && $_GET['_refresh'] === '1', 86400)
        : __DIR__.'/lib.php';
}
liarc_boot(get_defined_vars());

// JSON-API. Pfad: /api/... (via .htaccess) oder ?_page=api&p=...
// Auth: Authorization: Bearer liarc_<id>_<secret> + X-LIARC-User: <username>
$path = '/'.trim((string)($_GET['p'] ?? ''), '/');
if (str_starts_with($path, '/api/') || $path === '/api') $path = substr($path, 4);
if ($path === '') $path = '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$in = liarc_input();

if ($path === '/auth/login' && $method === 'POST') {
    if (!liarc_rate_check('api-login', LIARC_LOGIN_MAX_TRIES, LIARC_LOGIN_WINDOW)) liarc_json_error('rate_limited', 429);
    $res = liarc_login((string)($in['username'] ?? ''), (string)($in['password'] ?? ''));
    if (isset($res['error'])) liarc_json_error('invalid_credentials', 401);
    $name = liarc_cut(trim((string)($in['device_name'] ?? 'API')), 80);
    $dev = liarc_device_create($res['uid'], $res['dek'], $name !== '' ? $name : 'API', 'api');
    liarc_json(['ok' => true, 'token' => $dev['token'], 'device_id' => $dev['id']]);
}

if ($path === '/auth/device' && $method === 'POST') {
    if (!liarc_rate_check('device-login', 30, LIARC_LOGIN_WINDOW)) liarc_json_error('rate_limited', 429);
    $auth = liarc_token_auth((string)($in['username'] ?? ''), (string)($in['token'] ?? ''));
    if ($auth === null) liarc_json_error('invalid_token', 401);
    liarc_session_login($auth['uid'], $auth['dek'], $auth['device']);
    liarc_json(['ok' => true]);
}

// GET /api -> Index mit Endpunkten und Parametern; ?page=<gruppe|kategorie> -> Struktur
if ($path === '/' && $method === 'GET') {
    liarc_i18n_init();
    $groups = liarc_groups();
    $catInfo = function (string $key) {
        $cat = liarc_category($key);
        return [
            'key' => $key,
            'name' => liarc_cat_name($cat),
            'group' => liarc_group_of($key),
            'icon' => $cat['icon'],
            'kind' => $cat['kind'],
            'unit' => $cat['unit'],
            'me' => !empty($cat['me']),
            'fields' => array_map(fn($f) => ['key' => $f['key'], 'label' => liarc_field_label($f), 'type' => $f['type']], $cat['fields']),
        ];
    };
    $page = trim((string)($_GET['page'] ?? ($in['page'] ?? '')));
    if ($page !== '') {
        if (isset($groups[$page])) {
            liarc_json(['ok' => true, 'group' => ['key' => $page, 'name' => t('g.'.$page), 'icon' => $groups[$page]['icon'],
                'categories' => array_map($catInfo, $groups[$page]['cats'])]]);
        }
        if (liarc_category($page) !== null) {
            liarc_json(['ok' => true, 'category' => $catInfo($page)]);
        }
        liarc_json_error('unknown_page', 404);
    }
    $groupsOut = [];
    foreach ($groups as $g => $gd) $groupsOut[] = ['key' => $g, 'name' => t('g.'.$g), 'categories' => $gd['cats']];
    liarc_json(['ok' => true, 'name' => liarc_cfg('title'),
        'parameters' => ['page' => 'GET /api?page=<group|category>  e.g. health, heart'],
        'auth' => [
            'login' => 'POST /api/auth/login {username, password, device_name?} -> token',
            'headers' => ['Authorization: Bearer <token>', 'X-LIARC-User: <username>'],
        ],
        'endpoints' => [
            'GET /api/me', 'GET /api/devices', 'DELETE /api/devices/{id}',
            'GET /api/groups', 'GET /api/categories',
            'GET /api/categories/{key}', 'GET /api/categories/{key}/stats', 'GET /api/categories/{key}/entries',
            'POST /api/categories/{key}/entries',
            'PATCH /api/categories/{key}/entries/{id}', 'DELETE /api/categories/{key}/entries/{id}',
        ],
        'groups' => $groupsOut]);
}

$user = liarc_api_auth();
if ($user === null) liarc_json_error('unauthorized', 401);

if ($path === '/me' && $method === 'GET') {
    liarc_json(['ok' => true, 'username' => liarc_username($user['uid']), 'device' => $user['device']]);
}

if ($path === '/devices' && $method === 'GET') {
    liarc_json(['ok' => true, 'devices' => liarc_devices_list($user['uid'], $user['device'])]);
}

if (preg_match('#^/devices/([A-Za-z0-9_-]+)$#', $path, $m) && $method === 'DELETE') {
    liarc_json(['ok' => liarc_device_revoke($user['uid'], $m[1])]);
}

$vault = liarc_vault_load($user['uid'], $user['dek']);
if ($vault === null) liarc_json_error('vault_unreadable', 500);

$catOut = function (string $key) use ($vault) {
    $cat = liarc_category($key);
    $cat['name'] = liarc_cat_name($cat);
    $cat['group'] = liarc_group_of($key);
    return $cat + ['stats' => liarc_category_stats($cat, $vault['entries'][$key] ?? [])];
};

if ($path === '/groups' && $method === 'GET') {
    liarc_i18n_init();
    $out = [];
    foreach (liarc_groups() as $g => $gd) {
        $out[] = ['key' => $g, 'name' => t('g.'.$g), 'icon' => $gd['icon'], 'categories' => $gd['cats']];
    }
    liarc_json(['ok' => true, 'groups' => $out]);
}

if ($path === '/categories' && $method === 'GET') {
    liarc_i18n_init();
    liarc_json(['ok' => true, 'categories' => array_map($catOut, array_keys(liarc_categories()))]);
}

if (preg_match('#^/categories/([a-z0-9_-]+)(/.*)?$#', $path, $m)) {
    $catKey = $m[1];
    $sub = $m[2] ?? '';
    $cat = liarc_category($catKey);
    if ($cat === null) liarc_json_error('category_not_found', 404);
    $entries = $vault['entries'][$catKey] ?? [];

    if ($sub === '' && $method === 'GET') {
        liarc_i18n_init();
        liarc_json(['ok' => true, 'category' => $catOut($catKey)]);
    }

    if ($sub === '/stats' && $method === 'GET') {
        $out = ['ok' => true, 'stats' => liarc_category_stats($cat, $entries)];
        if ($cat['kind'] === 'series') $out['points'] = liarc_series_points($entries);
        liarc_json($out);
    }

    if ($sub === '/entries' && $method === 'GET') {
        liarc_json(['ok' => true, 'entries' => $entries]);
    }

    if ($sub === '/entries' && $method === 'POST') {
        $res = liarc_entry_build($cat, $in);
        if (isset($res['error'])) liarc_json_error($res['error'], 422);
        $lock = liarc_user_lock($user['uid']);
        $vault = liarc_vault_load($user['uid'], $user['dek']);
        $vault['entries'][$catKey][] = $res['entry'];
        liarc_vault_save($user['uid'], $user['dek'], $vault);
        liarc_user_unlock($lock);
        liarc_json(['ok' => true, 'entry' => $res['entry']], 201);
    }

    if (preg_match('#^/entries/([A-Za-z0-9_-]+)$#', $sub, $em)) {
        $entryId = $em[1];
        if ($method === 'PATCH') {
            $lock = liarc_user_lock($user['uid']);
            $vault = liarc_vault_load($user['uid'], $user['dek']);
            $found = false;
            if (!empty($in['me'])) {
                $found = liarc_entry_set_me($vault, $catKey, $entryId);
            }
            if (isset($vault['entries'][$catKey])) {
                foreach ($vault['entries'][$catKey] as &$e) {
                    if ($e['id'] === $entryId) {
                        if (isset($in['status']) && in_array($in['status'], ['active', 'old'], true)) $e['status'] = $in['status'];
                        if (isset($in['note'])) $e['note'] = liarc_cut((string)$in['note'], 200);
                        $e['updated'] = liarc_now();
                        $found = true;
                        break;
                    }
                }
                unset($e);
            }
            if ($found) liarc_vault_save($user['uid'], $user['dek'], $vault);
            liarc_user_unlock($lock);
            $found ? liarc_json(['ok' => true]) : liarc_json_error('entry_not_found', 404);
        }
        if ($method === 'DELETE') {
            $lock = liarc_user_lock($user['uid']);
            $vault = liarc_vault_load($user['uid'], $user['dek']);
            $before = count($vault['entries'][$catKey] ?? []);
            $vault['entries'][$catKey] = array_values(array_filter(
                $vault['entries'][$catKey] ?? [], fn($e) => $e['id'] !== $entryId
            ));
            $found = count($vault['entries'][$catKey]) < $before;
            if ($found) liarc_vault_save($user['uid'], $user['dek'], $vault);
            liarc_user_unlock($lock);
            $found ? liarc_json(['ok' => true]) : liarc_json_error('entry_not_found', 404);
        }
    }
}

liarc_json_error('not_found', 404);