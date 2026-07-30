<?php
declare(strict_types=1);
if (!defined('LIARC_LIB')) {
    require function_exists('app_get_local_script')
        ? app_get_local_script(($liarc_repo ?? 'https://raw.githubusercontent.com/florianthepro/pages/main/content').'/routes/liarc/lib.php', isset($_GET['_refresh']) && $_GET['_refresh'] === '1', 86400)
        : __DIR__.'/lib.php';
}
liarc_boot(get_defined_vars());
liarc_i18n_init();
liarc_set_page('devices');

$user = liarc_require_user();
$newToken = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    liarc_csrf_check();
    $do = (string)($_GET['do'] ?? '');
    if ($do === 'revoke') {
        $id = (string)($_GET['id'] ?? '');
        liarc_device_revoke($user['uid'], $id);
        if ($id === ($user['device'] ?? null)) {
            liarc_session_logout();
            liarc_redirect('auth', ['v' => 'login']);
        }
        liarc_redirect('devices');
    }
    if ($do === 'apikey') {
        $name = liarc_cut(trim((string)($_POST['name'] ?? 'API')), 80);
        $newToken = liarc_device_create($user['uid'], $user['dek'], $name !== '' ? $name : 'API', 'api')['token'];
    }
}

$devices = liarc_devices_list($user['uid'], $user['device']);
$username = liarc_username($user['uid']);
$csrf = liarc_csrf_token();

liarc_head(t('nav.devices'));
?>
<div class="pagehead"><?= ic('devices') ?><span><?= h(t('nav.devices')) ?></span></div>
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
                <form method="post" action="<?= h(liarc_url('devices', ['do' => 'revoke', 'id' => $d['id']])) ?>" data-confirm="1" class="inline">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
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
    <form method="post" action="<?= h(liarc_url('devices', ['do' => 'apikey'])) ?>" class="stack">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="text" name="name" maxlength="80" placeholder="<?= h(t('a.name')) ?>">
        <button type="submit" class="iconbtn wide"><?= ic('check', t('a.save')) ?></button>
    </form>
</details>
<?php liarc_foot();