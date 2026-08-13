<?php
declare(strict_types=1);
if (!defined('LIARC_LIB')) {
    require function_exists('app_get_local_script')
        ? app_get_local_script(($liarc_repo ?? 'https://raw.githubusercontent.com/florianthepro/pages/main/content').'/routes/liarc/lib.php', isset($_GET['_refresh']) && $_GET['_refresh'] === '1', 86400)
        : __DIR__.'/lib.php';
}
liarc_boot(get_defined_vars());
liarc_i18n_init();
liarc_set_page('settings');

$user = liarc_require_user();
$error = isset($_GET['error']) ? (string)$_GET['error'] : null;
$ok = isset($_GET['ok']) ? 'a.saved' : null;
$newToken = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    liarc_csrf_check();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'password') {
        $error = liarc_change_password(
            $user['uid'], $user['dek'],
            (string)($_POST['old_password'] ?? ''),
            (string)($_POST['new_password'] ?? '')
        );
        if ($error === null) $ok = 'a.saved';
    }
    if ($act === 'revoke') {
        $id = (string)($_POST['id'] ?? '');
        liarc_device_revoke($user['uid'], $id);
        if ($id === ($user['device'] ?? null)) {
            liarc_session_logout();
            liarc_redirect('auth', ['v' => 'login']);
        }
        $ok = 'a.saved';
    }
    if ($act === 'apikey') {
        $name = liarc_cut(trim((string)($_POST['name'] ?? 'API')), 80);
        $newToken = liarc_device_create($user['uid'], $user['dek'], $name !== '' ? $name : 'API', 'api')['token'];
    }
    if ($act === 'delaccount') {
        if (liarc_account_delete($user['uid'], (string)($_POST['pw'] ?? ''))) {
            liarc_session_logout();
            liarc_redirect('auth', ['v' => 'register']);
        }
        $error = 'auth.err_login';
    }
    if ($act === 'logout') {
        if (!empty($user['device'])) liarc_device_revoke($user['uid'], $user['device']);
        liarc_session_logout();
        liarc_redirect('auth', ['v' => 'login']);
    }
}

$devices = liarc_devices_list($user['uid'], $user['device']);
$username = liarc_username($user['uid']);
$csrf = liarc_csrf_token();
$confirm = h(t('a.sure'));

liarc_head(t('nav.settings'));
?>
<div class="pagehead"><?= ic('gear') ?><span><?= h(t('nav.settings')) ?></span></div>
<?php if ($error !== null): ?><p class="error"><?= h(t($error)) ?></p><?php endif; ?>
<?php if ($ok !== null): ?><p class="ok"><?= h(t($ok)) ?></p><?php endif; ?>

<div class="hsec"><?= h(t('auth.username')) ?></div>
<div class="card">
    <p><?= ic('user') ?> <strong><?= h($username) ?></strong></p>
    <form method="post" action="<?= h(liarc_url('settings')) ?>" class="stack">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="password">
        <input type="password" name="old_password" autocomplete="current-password" placeholder="<?= h(t('st.pw_old')) ?>" required>
        <input type="password" name="new_password" autocomplete="new-password" minlength="<?= LIARC_PASSWORD_MIN ?>" placeholder="<?= h(t('st.pw_new')) ?>" required>
        <button type="submit" class="iconbtn wide"><?= ic('check', t('a.save')) ?></button>
    </form>
</div>

<div class="hsec"><?= h(t('nav.devices')) ?></div>
<?php if ($newToken !== null): ?>
<div class="card token-card">
    <p><?= ic('key') ?><?= h(t('d.token_once')) ?></p>
    <code class="token"><?= h($newToken) ?></code>
    <pre class="dim">Authorization: Bearer <?= h($newToken) ?>
X-LIARC-User: <?= h($username) ?></pre>
</div>
<?php endif; ?>
<div class="card">
    <table>
    <?php foreach ($devices as $d): ?>
        <tr>
            <td><?= ic($d['type'] === 'api' ? 'key' : 'devices') ?></td>
            <td><?= h($d['name']) ?><?= $d['current'] ? ' <span class="tag">'.h(t('d.current')).'</span>' : '' ?></td>
            <td class="dim"><?= h(date('Y-m-d H:i', $d['last_seen'])) ?></td>
            <td class="actions">
                <form method="post" action="<?= h(liarc_url('settings')) ?>" data-confirm="<?= $confirm ?>" class="inline">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="revoke">
                    <input type="hidden" name="id" value="<?= h($d['id']) ?>">
                    <button type="submit" class="iconbtn"><?= ic('trash', t('a.delete')) ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (count($devices) === 0): ?><tr><td class="dim"><?= h(t('a.empty')) ?></td></tr><?php endif; ?>
    </table>
</div>
<details class="card slim">
    <summary><?= ic('key') ?><span><?= h(t('d.apikey')) ?></span></summary>
    <form method="post" action="<?= h(liarc_url('settings')) ?>" class="stack">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="apikey">
        <input type="text" name="name" maxlength="80" placeholder="<?= h(t('a.name')) ?>">
        <button type="submit" class="iconbtn wide"><?= ic('check', t('a.save')) ?></button>
    </form>
</details>

<div class="hsec"><?= h(t('st.lang')) ?></div>
<div class="card langrow">
    <?= ic('globe') ?>
    <?php foreach (LIARC_LANGS as $l): ?>
    <a href="<?= h(liarc_url('settings', ['lang' => $l])) ?>" class="<?= liarc_lang() === $l ? 'active' : '' ?>"><?= h(strtoupper($l)) ?></a>
    <?php endforeach; ?>
</div>

<div class="hsec"><?= h(t('st.export')) ?></div>
<div class="card">
    <div class="row">
        <a href="<?= h(liarc_url('data', ['do' => 'export'])) ?>" class="iconbtn wide" download><?= ic('download', t('st.export')) ?><span class="btlabel"><?= h(t('st.export')) ?></span></a>
    </div>
    <p></p>
    <form method="post" action="<?= h(liarc_url('data', ['do' => 'import'])) ?>" enctype="multipart/form-data" class="row" data-confirm="<?= h(t('st.import_sure')) ?>">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="file" name="file" accept=".json" required>
        <button type="submit" class="iconbtn"><?= ic('upload', t('st.import')) ?></button>
    </form>
</div>

<div class="card">
    <form method="post" action="<?= h(liarc_url('settings')) ?>">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="iconbtn wide"><?= ic('logout', t('nav.logout')) ?><span class="btlabel"><?= h(t('nav.logout')) ?></span></button>
    </form>
</div>

<details class="card slim">
    <summary><?= ic('trash') ?><span><?= h(t('st.delaccount')) ?></span></summary>
    <form method="post" action="<?= h(liarc_url('settings')) ?>" class="stack" data-confirm="<?= h(t('st.delaccount_sure')) ?>">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="delaccount">
        <input type="password" name="pw" autocomplete="current-password" placeholder="<?= h(t('auth.password')) ?>" required>
        <button type="submit" class="iconbtn wide"><?= ic('trash', t('st.delaccount')) ?><span class="btlabel"><?= h(t('st.delaccount')) ?></span></button>
    </form>
</details>
<?php liarc_foot();