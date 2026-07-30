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
$cat = $catKey !== '' ? liarc_category($catKey) : null;
$group = $cat !== null ? liarc_group_of($cat['key']) : (string)($_GET['g'] ?? array_key_first($groups));
if (!isset($groups[$group])) $group = array_key_first($groups);
if ($cat === null) $cat = liarc_category($groups[$group]['cats'][0]);

$entries = $vault['entries'][$cat['key']] ?? [];
$stats = liarc_category_stats($cat, $entries);
$csrf = liarc_csrf_token();

liarc_head(liarc_cat_name($cat));
?>
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

<?php if (!empty($_GET['error'])): ?><p class="error"><?= h(t((string)$_GET['error'])) ?></p><?php endif; ?>

<?php if ($cat['kind'] === 'series'): ?>

<?php if ($stats['count'] > 0): ?>
<div class="statgrid">
    <div class="stat"><span class="k"><?= h(t('s.latest')) ?></span><span class="v"><?= h((string)$stats['latest']['value']) ?><em><?= h($cat['unit']) ?></em></span></div>
    <div class="stat"><span class="k"><?= h(t('s.avg')) ?></span><span class="v"><?= h((string)$stats['avg']) ?></span></div>
    <div class="stat"><span class="k"><?= h(t('s.min')) ?></span><span class="v"><?= h((string)$stats['min']) ?></span></div>
    <div class="stat"><span class="k"><?= h(t('s.max')) ?></span><span class="v"><?= h((string)$stats['max']) ?></span></div>
</div>
<div class="card">
    <div class="chart" data-chart></div>
    <script type="application/json" data-chart-data><?= json_encode(liarc_series_points($entries)) ?></script>
</div>
<?php endif; ?>

<form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_add', 'cat' => $cat['key']])) ?>" class="card addrow">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="number" name="value" step="any" inputmode="decimal" placeholder="<?= h(t('a.value')).($cat['unit'] !== '' ? ' · '.h($cat['unit']) : '') ?>" required>
    <input type="datetime-local" name="at">
    <button type="submit" class="iconbtn accent"><?= ic('check', t('a.save')) ?></button>
</form>

<?php
usort($entries, fn($a, $b) => $b['at'] <=> $a['at']);
if (count($entries) > 0): ?>
<div class="card list">
<?php foreach ($entries as $e): ?>
    <div class="rowitem">
        <div class="rowmain">
            <div class="p"><?= h((string)$e['value']) ?> <span class="dim"><?= h($cat['unit']) ?></span></div>
            <div class="s"><?= h(date('Y-m-d H:i', $e['at'])) ?><?= ($e['note'] ?? '') !== '' ? ' · '.h($e['note']) : '' ?></div>
        </div>
        <div class="rowact">
            <form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_del', 'cat' => $cat['key'], 'id' => $e['id']])) ?>" data-confirm="1" class="inline">
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

$row = function (array $e, bool $isOld) use ($cat, $csrf) {
    $primary = '';
    $secondary = [];
    foreach ($cat['fields'] as $f) {
        $v = (string)($e['fields'][$f['key']] ?? '');
        if ($v === '') continue;
        if ($f['type'] === 'secret') {
            $part = '<button type="button" class="secret" data-secret="'.h($v).'">•••</button>';
        } else {
            $part = h(liarc_field_display($f, $v));
        }
        if ($primary === '' && $f['type'] !== 'secret') { $primary = $part; continue; }
        $secondary[] = '<span class="fl">'.h(liarc_field_label($f)).'</span> '.$part;
    }
    if ($primary === '') $primary = '·';
    ?>
    <div class="rowitem<?= !empty($e['me']) ? ' me' : '' ?>">
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
            <form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_status', 'cat' => $cat['key'], 'id' => $e['id']])) ?>" class="inline">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <button type="submit" class="iconbtn"><?= $isOld ? ic('restore', t('a.restore')) : ic('archive', t('a.archive')) ?></button>
            </form>
            <form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_del', 'cat' => $cat['key'], 'id' => $e['id']])) ?>" data-confirm="1" class="inline">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <button type="submit" class="iconbtn"><?= ic('trash', t('a.delete')) ?></button>
            </form>
        </div>
    </div>
<?php };
?>

<details class="card slim">
    <summary><?= ic('plus') ?><span><?= h(t('a.new')) ?></span></summary>
    <form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_add', 'cat' => $cat['key']])) ?>" class="stack">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <?php foreach ($cat['fields'] as $f): $n = 'field_'.$f['key']; $lbl = liarc_field_label($f); ?>
            <?php if ($f['type'] === 'note'): ?>
            <textarea name="<?= h($n) ?>" rows="2" maxlength="2000" placeholder="<?= h($lbl) ?>"></textarea>
            <?php elseif ($f['type'] === 'date'): ?>
            <input type="date" name="<?= h($n) ?>" title="<?= h($lbl) ?>">
            <?php elseif ($f['type'] === 'number'): ?>
            <input type="number" step="any" inputmode="decimal" name="<?= h($n) ?>" placeholder="<?= h($lbl) ?>">
            <?php elseif ($f['type'] === 'phone'): ?>
            <input type="tel" name="<?= h($n) ?>" maxlength="40" placeholder="<?= h($lbl) ?>">
            <?php elseif ($f['type'] === 'secret'): ?>
            <input type="password" name="<?= h($n) ?>" maxlength="200" placeholder="<?= h($lbl) ?>" autocomplete="off">
            <?php else: ?>
            <input type="text" name="<?= h($n) ?>" maxlength="200" placeholder="<?= h($lbl) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <button type="submit" class="iconbtn wide accent"><?= ic('check', t('a.save')) ?></button>
    </form>
</details>

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