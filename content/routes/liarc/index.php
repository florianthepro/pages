<?php
declare(strict_types=1);
if (!defined('LIARC_LIB')) {
    require function_exists('app_get_local_script')
        ? app_get_local_script(($liarc_repo ?? 'https://raw.githubusercontent.com/florianthepro/pages/main/content').'/routes/liarc/lib.php', isset($_GET['_refresh']) && $_GET['_refresh'] === '1', 86400)
        : __DIR__.'/lib.php';
}
liarc_boot(get_defined_vars());

// huebsche URLs (/login, /api/...): auf die passende Route weiterreichen
$__pretty = liarc_pretty_route();
if ($__pretty !== null && $__pretty['page'] !== 'index') {
    foreach ($__pretty['get'] as $__k => $__v) $_GET[$__k] = $_GET[$__k] ?? $__v;
    $__f = liarc_repo_file('routes/liarc/'.$__pretty['page'].'.php', 300);
    if ($__f !== null) { require $__f; exit; }
}

liarc_i18n_init();
liarc_set_page('index');

$user = liarc_require_user();
$vault = liarc_vault_load($user['uid'], $user['dek']);
if ($vault === null) { liarc_session_logout(); liarc_redirect('auth', ['v' => 'login']); }

$groups = liarc_groups();
$catKey = (string)($_GET['cat'] ?? '');
// ohne Parameter: letzte Ansicht aus dem Browser-Cookie
if ($catKey === '' && !isset($_GET['g']) && liarc_category((string)($_COOKIE['liarc_view'] ?? '')) !== null) {
    $catKey = (string)$_COOKIE['liarc_view'];
}
$cat = $catKey !== '' ? liarc_category($catKey) : null;
$group = $cat !== null ? liarc_group_of($cat['key']) : (string)($_GET['g'] ?? array_key_first($groups));
if (!isset($groups[$group])) $group = array_key_first($groups);
if ($cat === null) $cat = liarc_category($groups[$group]['cats'][0]);

// aktuelle Ansicht merken, damit "/" ohne sichtbare Parameter reicht
setcookie('liarc_view', $cat['key'], [
    'expires' => liarc_now() + 31536000, 'path' => '/',
    'secure' => liarc_is_https(), 'httponly' => false, 'samesite' => 'Lax',
]);

$entries = $vault['entries'][$cat['key']] ?? [];
$csrf = liarc_csrf_token();
$confirm = h(t('a.sure'));

// eintrag bearbeiten: ?edit=<id> laedt die werte ins formular
$editId = (string)($_GET['edit'] ?? '');
$editEntry = null;
foreach ($entries as $e) if ($e['id'] === $editId) $editEntry = $e;

liarc_head(liarc_cat_name($cat), false, $cat['key']);
?>
<div class="pagehead"><?= ic($cat['icon']) ?><span><?= h(liarc_cat_name($cat)) ?></span></div>
<div class="mobilenav">
<nav class="groupbar">
<?php foreach ($groups as $g => $gd): ?>
    <a href="<?= h(liarc_url('index', ['g' => $g])) ?>" class="group<?= $g === $group ? ' active' : '' ?>">
        <?= ic($gd['icon']) ?><span><?= h(t('g.'.$g)) ?></span>
    </a>
<?php endforeach; ?>
</nav>

<nav class="catbar">
<?php foreach ($groups[$group]['cats'] as $ck): $c = liarc_category($ck); if ($c === null) continue; ?>
    <a href="<?= h(liarc_url('index', ['cat' => $ck])) ?>" class="chip<?= $ck === $cat['key'] ? ' active' : '' ?>">
        <?= ic($c['icon']) ?><span><?= h(liarc_cat_name($c)) ?></span>
    </a>
<?php endforeach; ?>
</nav>
</div>

<?php if (!empty($_GET['error'])): ?><p class="error"><?= h(t((string)$_GET['error'])) ?></p><?php endif; ?>

<?php if ($cat['kind'] === 'series'): ?>
<?php
// zeitraum: 7/30/90/365 tage oder alles
$ranges = ['7' => t('r.7'), '30' => t('r.30'), '90' => t('r.90'), '365' => t('r.365'), 'all' => t('r.all')];
$range = (string)($_GET['r'] ?? '30');
if (!isset($ranges[$range])) $range = '30';
$shown = $entries;
if ($range !== 'all') {
    $cut = liarc_now() - (int)$range * 86400;
    $shown = array_values(array_filter($entries, fn($e) => $e['at'] >= $cut));
}
$stats = liarc_category_stats($cat, $shown);
?>

<nav class="rangebar">
<?php foreach ($ranges as $rk => $rl): ?>
    <a href="<?= h(liarc_url('index', ['cat' => $cat['key'], 'r' => $rk])) ?>" class="chip<?= $rk === $range ? ' active' : '' ?>"><?= h($rl) ?></a>
<?php endforeach; ?>
</nav>

<?php if ($stats['count'] > 0): ?>
<div class="statgrid">
    <div class="stat"><span class="k"><?= h(t('s.latest')) ?></span><span class="v"><?= h((string)$stats['latest']['value']) ?><em><?= h($cat['unit']) ?></em></span></div>
    <div class="stat"><span class="k"><?= h(t('s.avg')) ?></span><span class="v"><?= h((string)$stats['avg']) ?></span></div>
    <div class="stat"><span class="k"><?= h(t('s.min')) ?></span><span class="v"><?= h((string)$stats['min']) ?></span></div>
    <div class="stat"><span class="k"><?= h(t('s.max')) ?></span><span class="v"><?= h((string)$stats['max']) ?></span></div>
</div>
<div class="card">
    <div class="chart" data-chart></div>
    <script type="application/json" data-chart-data><?= json_encode(liarc_series_points($shown)) ?></script>
</div>
<?php elseif (count($entries) > 0): ?>
<p class="dim"><?= h(t('r.empty')) ?></p>
<?php endif; ?>

<form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_save', 'cat' => $cat['key'], 'id' => $editEntry['id'] ?? ''])) ?>" class="card addrow<?= $editEntry !== null ? ' editing' : '' ?>">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="number" name="value" step="any" inputmode="decimal" value="<?= $editEntry !== null ? h((string)$editEntry['value']) : '' ?>" placeholder="<?= h(t('a.value')).($cat['unit'] !== '' ? ' · '.h($cat['unit']) : '') ?>" required>
    <input type="datetime-local" name="at" value="<?= $editEntry !== null ? h(date('Y-m-d\TH:i', $editEntry['at'])) : '' ?>">
    <button type="submit" class="iconbtn accent"><?= ic('check', t('a.save')) ?></button>
    <?php if ($editEntry !== null): ?>
    <a href="<?= h(liarc_url('index', ['cat' => $cat['key'], 'r' => $range])) ?>" class="iconbtn"><?= ic('x', t('a.cancel')) ?></a>
    <?php endif; ?>
</form>

<?php
usort($shown, fn($a, $b) => $b['at'] <=> $a['at']);
if (count($shown) > 0): ?>
<div class="card list">
<?php foreach ($shown as $e): ?>
    <div class="rowitem<?= $e['id'] === $editId ? ' me' : '' ?>">
        <div class="rowmain">
            <div class="p"><?= h((string)$e['value']) ?> <span class="dim"><?= h($cat['unit']) ?></span></div>
            <div class="s"><?= h(date('Y-m-d H:i', $e['at'])) ?><?= ($e['note'] ?? '') !== '' ? ' · '.h($e['note']) : '' ?></div>
        </div>
        <div class="rowact">
            <a href="<?= h(liarc_url('index', ['cat' => $cat['key'], 'r' => $range, 'edit' => $e['id']])) ?>" class="iconbtn"><?= ic('edit', t('a.edit')) ?></a>
            <form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_del', 'cat' => $cat['key'], 'id' => $e['id']])) ?>" data-confirm="<?= $confirm ?>" class="inline">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <button type="submit" class="iconbtn"><?= ic('trash', t('a.delete')) ?></button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: /* records */ ?>
<?php
usort($entries, fn($a, $b) => $b['updated'] <=> $a['updated']);
if (!empty($cat['me'])) {
    usort($entries, fn($a, $b) => (int)($b['me'] ?? false) <=> (int)($a['me'] ?? false));
}
$active = array_values(array_filter($entries, fn($e) => ($e['status'] ?? 'active') === 'active'));
$old = array_values(array_filter($entries, fn($e) => ($e['status'] ?? 'active') === 'old'));

$row = function (array $e, bool $isOld) use ($cat, $csrf, $confirm, $editId) {
    $primary = '';
    $secondary = [];
    foreach ($cat['fields'] as $f) {
        $v = (string)($e['fields'][$f['key']] ?? '');
        if ($v === '') continue;
        if ($f['type'] === 'secret') {
            $part = '<button type="button" class="secret" data-secret="'.h($v).'">•••</button>'
                .'<button type="button" class="iconbtn copy" data-copy="'.h($v).'">'.ic('copy', t('a.copy')).'</button>';
        } else {
            $part = h(liarc_field_display($f, $v));
        }
        if ($primary === '' && $f['type'] !== 'secret') { $primary = $part; continue; }
        $secondary[] = '<span class="fl">'.h(liarc_field_label($f)).'</span> '.$part;
    }
    if ($primary === '') $primary = '·';
    $secondary[] = '<span class="fl">'.h(date('Y-m-d', $e['updated'])).'</span>';
    ?>
    <div class="rowitem<?= !empty($e['me']) || $e['id'] === $editId ? ' me' : '' ?>" data-find>
        <div class="rowmain">
            <div class="p"><?= $primary ?><?= !empty($e['me']) ? ' <span class="tag">'.h(t('a.me')).'</span>' : '' ?></div>
            <?php if (count($secondary) > 0): ?><div class="s"><?= implode(' · ', $secondary) ?></div><?php endif; ?>
        </div>
        <div class="rowact">
            <?php if (!empty($cat['me']) && !$isOld): ?>
            <form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_me', 'cat' => $cat['key'], 'id' => $e['id']])) ?>" class="inline">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <button type="submit" class="iconbtn<?= !empty($e['me']) ? ' on' : '' ?>"><?= ic('user', t('a.me')) ?></button>
            </form>
            <?php endif; ?>
            <a href="<?= h(liarc_url('index', ['cat' => $cat['key'], 'edit' => $e['id']])) ?>" class="iconbtn"><?= ic('edit', t('a.edit')) ?></a>
            <form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_status', 'cat' => $cat['key'], 'id' => $e['id']])) ?>" class="inline">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <button type="submit" class="iconbtn"><?= $isOld ? ic('restore', t('a.restore')) : ic('archive', t('a.archive')) ?></button>
            </form>
            <form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_del', 'cat' => $cat['key'], 'id' => $e['id']])) ?>" data-confirm="<?= $confirm ?>" class="inline">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <button type="submit" class="iconbtn"><?= ic('trash', t('a.delete')) ?></button>
            </form>
        </div>
    </div>
<?php };
?>

<details class="card slim"<?= $editEntry !== null ? ' open' : '' ?>>
    <summary><?= $editEntry !== null ? ic('edit') : ic('plus') ?><span><?= h($editEntry !== null ? t('a.edit') : t('a.new')) ?></span></summary>
    <form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_save', 'cat' => $cat['key'], 'id' => $editEntry['id'] ?? ''])) ?>" class="stack">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <?php foreach ($cat['fields'] as $f): $n = 'field_'.$f['key']; $lbl = liarc_field_label($f);
            $val = $editEntry !== null ? (string)($editEntry['fields'][$f['key']] ?? '') : ''; ?>
            <?php if ($f['type'] === 'note'): ?>
            <textarea name="<?= h($n) ?>" rows="2" maxlength="2000" placeholder="<?= h($lbl) ?>"><?= h($val) ?></textarea>
            <?php elseif ($f['type'] === 'date'): ?>
            <input type="date" name="<?= h($n) ?>" value="<?= h($val) ?>" title="<?= h($lbl) ?>">
            <?php elseif ($f['type'] === 'number'): ?>
            <input type="number" step="any" inputmode="decimal" name="<?= h($n) ?>" value="<?= h($val) ?>" placeholder="<?= h($lbl) ?>">
            <?php elseif ($f['type'] === 'phone'): ?>
            <input type="tel" name="<?= h($n) ?>" maxlength="40" value="<?= h($val) ?>" placeholder="<?= h($lbl) ?>">
            <?php elseif ($f['type'] === 'secret'): ?>
            <input type="text" name="<?= h($n) ?>" maxlength="200" value="<?= h($val) ?>" placeholder="<?= h($lbl) ?>" autocomplete="off" spellcheck="false">
            <?php else: ?>
            <input type="text" name="<?= h($n) ?>" maxlength="200" value="<?= h($val) ?>" placeholder="<?= h($lbl) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="row">
            <button type="submit" class="iconbtn wide accent"><?= ic('check', t('a.save')) ?></button>
            <?php if ($editEntry !== null): ?>
            <a href="<?= h(liarc_url('index', ['cat' => $cat['key']])) ?>" class="iconbtn"><?= ic('x', t('a.cancel')) ?></a>
            <?php endif; ?>
        </div>
    </form>
</details>

<?php if (count($active) > 5): ?>
<div class="findbar"><?= ic('search') ?><input type="search" data-find-input placeholder="<?= h(t('a.search')) ?>"></div>
<?php endif; ?>

<?php if (count($active) > 0): ?>
<div class="card list"><?php foreach ($active as $e) $row($e, false); ?></div>
<?php endif; ?>

<?php if (count($old) > 0): ?>
<details class="card slim old">
    <summary><?= ic('archive') ?><span><?= h(t('a.old')) ?> · <?= count($old) ?></span></summary>
    <div class="list"><?php foreach ($old as $e) $row($e, true); ?></div>
</details>
<?php endif; ?>

<?php endif; ?>
<?php liarc_foot();