<?php
declare(strict_types=1);
if (!defined('LIARC_LIB')) {
    require function_exists('app_get_local_script')
        ? app_get_local_script(($liarc_repo ?? 'https://raw.githubusercontent.com/florianthepro/pages/main/content').'/routes/liarc/lib.php', isset($_GET['_refresh']) && $_GET['_refresh'] === '1', 300)
        : __DIR__.'/lib.php';
}
liarc_boot(get_defined_vars());
liarc_i18n_init();
liarc_set_page('index');

$user = liarc_require_user();
$vault = liarc_vault_load($user['uid'], $user['dek']);
if ($vault === null) { liarc_session_logout(); liarc_redirect('auth', ['v' => 'login']); }

$cats = $vault['categories'];
$catId = (string)($_GET['cat'] ?? '');
$cat = liarc_category_get($vault, $catId) ?? ($cats[0] ?? null);
if ($cat === null) { liarc_redirect(); }
$entries = $vault['entries'][$cat['id']] ?? [];
$stats = liarc_category_stats($cat, $entries);
$csrf = liarc_csrf_token();

liarc_head(liarc_cat_name($cat));
?>
<nav class="catbar">
<?php foreach ($cats as $c): ?>
    <a href="<?= h(liarc_url('index', ['cat' => $c['id']])) ?>" class="cat<?= $c['id'] === $cat['id'] ? ' active' : '' ?>">
        <?= ic($c['icon'] ?? 'folder') ?><span><?= h(liarc_cat_name($c)) ?></span>
    </a>
<?php endforeach; ?>
    <a href="#new" class="cat" data-open-new><?= ic('plus', t('a.new')) ?><span><?= h(t('a.new')) ?></span></a>
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

<form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_add', 'cat' => $cat['id']])) ?>" class="card addrow">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="number" name="value" step="any" inputmode="decimal" placeholder="<?= h(t('a.value')).($cat['unit'] !== '' ? ' · '.h($cat['unit']) : '') ?>" required>
    <input type="datetime-local" name="at" title="<?= h(t('a.time')) ?>">
    <button type="submit" class="iconbtn"><?= ic('check', t('a.save')) ?></button>
</form>

<?php
usort($entries, fn($a, $b) => $b['at'] <=> $a['at']);
if (count($entries) > 0): ?>
<div class="card">
    <table>
    <?php foreach ($entries as $e): ?>
        <tr>
            <td class="dim"><?= h(date('Y-m-d H:i', $e['at'])) ?></td>
            <td><strong><?= h((string)$e['value']) ?></strong> <span class="dim"><?= h($cat['unit']) ?></span></td>
            <td class="dim"><?= h($e['note'] ?? '') ?></td>
            <td class="actions">
                <form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_del', 'cat' => $cat['id'], 'id' => $e['id']])) ?>" data-confirm="1" class="inline">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <button type="submit" class="iconbtn"><?= ic('trash', t('a.delete')) ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php else: /* records */ ?>
<?php
usort($entries, fn($a, $b) => $b['updated'] <=> $a['updated']);
$active = array_values(array_filter($entries, fn($e) => ($e['status'] ?? 'active') === 'active'));
$old = array_values(array_filter($entries, fn($e) => ($e['status'] ?? 'active') === 'old'));
?>
<details class="card slim">
    <summary><?= ic('plus') ?><span><?= h(t('a.new')) ?></span></summary>
    <form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_add', 'cat' => $cat['id']])) ?>" class="stack">
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
            <?php else: ?>
            <input type="text" name="<?= h($n) ?>" maxlength="200" placeholder="<?= h($lbl) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <button type="submit" class="iconbtn wide"><?= ic('check', t('a.save')) ?></button>
    </form>
</details>

<?php
$rows = function (array $list, bool $isOld) use ($cat, $csrf) { ?>
    <table>
        <tr>
            <?php foreach ($cat['fields'] as $f): ?><th><?= h(liarc_field_label($f)) ?></th><?php endforeach; ?>
            <th></th>
        </tr>
        <?php foreach ($list as $e): ?>
        <tr>
            <?php foreach ($cat['fields'] as $f): ?>
            <td><?= h(liarc_field_display($f, (string)($e['fields'][$f['key']] ?? ''))) ?></td>
            <?php endforeach; ?>
            <td class="actions">
                <form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_status', 'cat' => $cat['id'], 'id' => $e['id']])) ?>" class="inline">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <button type="submit" class="iconbtn"><?= $isOld ? ic('restore', t('a.restore')) : ic('archive', t('a.archive')) ?></button>
                </form>
                <form method="post" action="<?= h(liarc_url('data', ['do' => 'entry_del', 'cat' => $cat['id'], 'id' => $e['id']])) ?>" data-confirm="1" class="inline">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <button type="submit" class="iconbtn"><?= ic('trash', t('a.delete')) ?></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php };
?>

<?php if (count($active) > 0): ?>
<div class="card"><?php $rows($active, false); ?></div>
<?php endif; ?>

<?php if (count($old) > 0): ?>
<details class="card slim old">
    <summary><?= ic('archive') ?><span><?= h(t('a.old')) ?> · <?= count($old) ?></span></summary>
    <?php $rows($old, true); ?>
</details>
<?php endif; ?>
<?php endif; ?>

<details class="card slim" id="new">
    <summary><?= ic('plus') ?><span><?= h(t('a.newcat')) ?></span></summary>
    <form method="post" action="<?= h(liarc_url('data', ['do' => 'cat_add'])) ?>" class="stack">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="text" name="name" maxlength="60" placeholder="<?= h(t('a.name')) ?>" required>
        <select name="kind" data-kind-select>
            <option value="records"><?= h(t('a.records')) ?></option>
            <option value="series"><?= h(t('a.series')) ?></option>
        </select>
        <input type="text" name="unit" maxlength="12" placeholder="<?= h(t('a.unit')) ?>" data-kind-series class="hidden">
        <div data-kind-records class="stack">
        <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="row">
                <input type="text" name="field_label_<?= $i ?>" maxlength="40" placeholder="<?= h(t('a.field')) ?>">
                <select name="field_type_<?= $i ?>">
                    <?php foreach (LIARC_FIELD_TYPES as $ft): ?>
                    <option value="<?= h($ft) ?>"><?= h(t('ft.'.$ft)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endfor; ?>
        </div>
        <button type="submit" class="iconbtn wide"><?= ic('check', t('a.save')) ?></button>
    </form>
</details>

<?php if (($cat['key'] ?? null) === null): ?>
<form method="post" action="<?= h(liarc_url('data', ['do' => 'cat_del', 'cat' => $cat['id']])) ?>" data-confirm="1" class="catdel">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <button type="submit" class="iconbtn"><?= ic('trash', t('a.delcat')) ?></button>
</form>
<?php endif; ?>
<?php liarc_foot();