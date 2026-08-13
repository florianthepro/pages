<?php
declare(strict_types=1);
if (!defined('LIARC_LIB')) {
    require function_exists('app_get_local_script')
        ? app_get_local_script(($liarc_repo ?? 'https://raw.githubusercontent.com/florianthepro/pages/main/content').'/routes/liarc/lib.php', isset($_GET['_refresh']) && $_GET['_refresh'] === '1', 86400)
        : __DIR__.'/lib.php';
}
liarc_boot(get_defined_vars());
liarc_i18n_init();

$v = (string)($_GET['v'] ?? 'login');
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$error = null;

if ($v === 'logout' && $post) {
    liarc_csrf_check();
    $u = liarc_user();
    if ($u !== null && !empty($u['device'])) liarc_device_revoke($u['uid'], $u['device']);
    liarc_session_logout();
    liarc_redirect('auth', ['v' => 'login']);
}

if ($v === 'login' && $post) {
    liarc_csrf_check();
    $res = liarc_login((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
    if (isset($res['error'])) $error = $res['error'];
    else { liarc_session_login($res['uid'], $res['dek']); liarc_redirect(); }
}

if ($v === 'register' && $post) {
    liarc_csrf_check();
    if (!liarc_rate_check('register', 5, 3600)) $error = 'auth.err_rate';
    else {
        $res = liarc_register((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
        if (isset($res['error'])) $error = $res['error'];
        else { liarc_session_login($res['uid'], $res['dek']); liarc_redirect(); }
    }
}

if (($v === 'login' || $v === 'register') && liarc_user() !== null) liarc_redirect();

liarc_set_page($v);
$csrf = liarc_csrf_token();

if ($v === 'install') {
    liarc_head(t('i.title'), liarc_user() === null);
    ?>
<div class="card auth-card">
    <div class="logo"><?= ic('liarc') ?><span><?= h(liarc_cfg('title')) ?></span></div>
    <div data-install-ios class="hidden steps">
        <p><?= ic('logout') ?><?= h(t('i.ios_1')) ?></p>
        <p><?= ic('plus') ?><?= h(t('i.ios_2')) ?></p>
        <p><?= ic('liarc') ?><?= h(t('i.ios_3')) ?></p>
    </div>
    <div data-install-android class="hidden steps">
        <p><?= ic('gear') ?><?= h(t('i.android_1')) ?></p>
        <p><?= ic('plus') ?><?= h(t('i.android_2')) ?></p>
        <p><?= ic('liarc') ?><?= h(t('i.android_3')) ?></p>
    </div>
    <div data-install-other class="hidden steps">
        <p><?= ic('plus') ?><?= h(t('i.other')) ?></p>
    </div>
    <p><a href="<?= h(liarc_url('auth', ['v' => 'login'])) ?>"><?= h(t('a.back')) ?></a>
    <?php if (liarc_user() !== null): ?> · <a href="#" data-continue class="dim"><?= h(t('i.continue')) ?></a><?php endif; ?></p>
</div>
    <?php
    liarc_foot();
}

if ($v === 'register') {
    liarc_head(t('auth.register'), true);
    ?>
<div class="card auth-card">
    <div class="logo"><?= ic('liarc') ?><span><?= h(liarc_cfg('title')) ?></span></div>
    <?php if ($error !== null): ?><p class="error"><?= h(t($error)) ?></p><?php endif; ?>
    <form method="post" action="<?= h(liarc_url('auth', ['v' => 'register'])) ?>" class="stack">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="text" name="username" autocomplete="username" pattern="[a-zA-Z0-9._\-]{3,32}" placeholder="<?= h(t('auth.username')) ?>" required>
        <input type="password" name="password" autocomplete="new-password" minlength="<?= LIARC_PASSWORD_MIN ?>" placeholder="<?= h(t('auth.password')) ?>" required>
        <button type="submit"><?= h(t('auth.register')) ?></button>
    </form>
    <p><a href="<?= h(liarc_url('auth', ['v' => 'login'])) ?>"><?= h(t('auth.login')) ?></a></p>
</div>
    <?php
    liarc_foot();
}

liarc_head(t('auth.login'), true);
?>
<div class="card auth-card">
    <div class="logo"><?= ic('liarc') ?><span><?= h(liarc_cfg('title')) ?></span></div>
    <?php if ($error !== null): ?><p class="error"><?= h(t($error)) ?></p><?php endif; ?>
    <p class="dim hidden" data-device-login><?= h(t('auth.device_login')) ?></p>
    <form method="post" action="<?= h(liarc_url('auth', ['v' => 'login'])) ?>" class="stack">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="text" name="username" autocomplete="username" placeholder="<?= h(t('auth.username')) ?>" required>
        <input type="password" name="password" autocomplete="current-password" placeholder="<?= h(t('auth.password')) ?>" required>
        <button type="submit"><?= h(t('auth.login')) ?></button>
    </form>
    <p><a href="<?= h(liarc_url('auth', ['v' => 'register'])) ?>"><?= h(t('auth.register')) ?></a></p>
</div>
<?php
liarc_foot();