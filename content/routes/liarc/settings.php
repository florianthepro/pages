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
$error = null;
$ok = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    liarc_csrf_check();
    $error = liarc_change_password(
        $user['uid'], $user['dek'],
        (string)($_POST['old_password'] ?? ''),
        (string)($_POST['new_password'] ?? '')
    );
    if ($error === null) $ok = 'a.saved';
}

$csrf = liarc_csrf_token();
liarc_head(t('nav.settings'));
?>
<div class="pagehead"><?= ic('gear') ?><span><?= h(t('nav.settings')) ?></span></div>
<div class="card">
    <p><?= ic('user') ?><strong><?= h(liarc_username($user['uid'])) ?></strong></p>
</div>

<?php if ($error !== null): ?><p class="error"><?= h(t($error)) ?></p><?php endif; ?>
<?php if ($ok !== null): ?><p class="ok"><?= h(t($ok)) ?></p><?php endif; ?>

<div class="card">
    <form method="post" action="<?= h(liarc_url('settings')) ?>" class="stack">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="password" name="old_password" autocomplete="current-password" placeholder="<?= h(t('st.pw_old')) ?>" required>
        <input type="password" name="new_password" autocomplete="new-password" minlength="<?= LIARC_PASSWORD_MIN ?>" placeholder="<?= h(t('st.pw_new')) ?>" required>
        <button type="submit" class="iconbtn wide"><?= ic('check', t('a.save')) ?></button>
    </form>
</div>

<div class="card langrow">
    <?= ic('globe') ?>
    <?php foreach (LIARC_LANGS as $l): ?>
    <a href="<?= h(liarc_url('settings', ['lang' => $l])) ?>" class="<?= liarc_lang() === $l ? 'active' : '' ?>"><?= h(strtoupper($l)) ?></a>
    <?php endforeach; ?>
</div>
<?php liarc_foot();