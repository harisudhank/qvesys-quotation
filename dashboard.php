<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$lang = current_lang();
$pageTitle = t('dashboard', $lang);
$activeNav = 'dashboard';

$quotations = db_read('quotations');
usort($quotations, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

$total = count($quotations);
$totalValue = array_sum(array_column($quotations, 'total'));
$accepted = count(array_filter($quotations, fn($q) => ($q['status'] ?? '') === 'accepted'));
$pending = count(array_filter($quotations, fn($q) => in_array($q['status'] ?? '', ['draft', 'sent'], true)));

$recent = array_slice($quotations, 0, 8);

$topbarAction = '<div style="display:flex; gap:8px;"><a href="quotation-editor.php" class="btn btn-brass">+ ' . t('new_quotation', $lang) . '</a><a href="quotation-editor.php?comparative=1" class="btn btn-brass">+ ' . t('comparative_quotation', $lang) . '</a></div>';

require __DIR__ . '/includes/header.php';
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="label"><?= t('total_quotations', $lang) ?></div>
    <div class="value"><?= $total ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><?= t('total_value', $lang) ?></div>
    <div class="value"><?= format_currency($totalValue) ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><?= t('accepted', $lang) ?></div>
    <div class="value" style="color:var(--emerald)"><?= $accepted ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><?= t('pending', $lang) ?></div>
    <div class="value" style="color:var(--brass)"><?= $pending ?></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h3><?= t('recent_quotations', $lang) ?></h3>
    <a href="quotations.php" class="btn btn-outline btn-sm"><?= t('view', $lang) ?> <?= t('quotations', $lang) ?></a>
  </div>
  <?php if (empty($recent)): ?>
    <div class="empty-state">
      <div class="glyph">▦</div>
      <div><?= t('no_records', $lang) ?></div>
      <div style="display:flex; gap:8px; justify-content:center; margin-top:14px;">
        <a href="quotation-editor.php" class="btn btn-brass">+ <?= t('new_quotation', $lang) ?></a>
        <a href="quotation-editor.php?comparative=1" class="btn btn-brass">+ <?= t('comparative_quotation', $lang) ?></a>
      </div>
    </div>
  <?php else: ?>
  <table class="grid">
    <thead>
      <tr>
        <th><?= t('quotation_no', $lang) ?></th>
        <th><?= t('client', $lang) ?></th>
        <th><?= t('date', $lang) ?></th>
        <th class="num"><?= t('amount', $lang) ?></th>
        <th><?= t('status', $lang) ?></th>
        <th><?= t('actions', $lang) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($recent as $q): ?>
      <tr>
        <td><strong><?= h($q['number']) ?></strong></td>
        <td>
          <?php if (!empty($q['is_comparative'])): ?>
            <details style="cursor:pointer;">
              <summary style="font-weight:600; outline:none; list-style:none; display:flex; align-items:center; gap:4px; user-select:none;">
                <?= h($q['client_snapshot']['name'] ?? 'Comparative Quotation') ?>
                <span style="font-size:9px; color:#888;">▼</span>
              </summary>
              <div style="font-size:11px; margin-top:4px; padding-left:8px; border-left:2px solid var(--border-color); display:flex; flex-direction:column; gap:3px;">
                <?php foreach (array_keys($q['options'] ?? []) as $comp): ?>
                  <a href="quotation-view.php?id=<?= h($q['id']) ?>&company=<?= urlencode($comp) ?>" style="color:var(--primary-color); font-weight:600; text-decoration:none;">• <?= h($comp) ?></a>
                <?php endforeach; ?>
              </div>
            </details>
          <?php else: ?>
            <?= h($q['client_snapshot']['name'] ?? '') ?>
          <?php endif; ?>
        </td>
        <td><?= h($q['date']) ?></td>
        <td class="num"><?= format_currency((float)($q['total'] ?? 0)) ?></td>
        <td><span class="badge <?= status_badge_class($q['status'] ?? 'draft') ?>"><?= t($q['status'] ?? 'draft', $lang) ?></span></td>
        <td>
          <a href="quotation-view.php?id=<?= h($q['id']) ?>" class="btn btn-outline btn-sm"><?= t('view', $lang) ?></a>
          <a href="quotation-editor.php?id=<?= h($q['id']) ?>" class="btn btn-outline btn-sm"><?= t('edit', $lang) ?></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
