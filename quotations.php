<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$lang = current_lang();
$showComparativeOnly = isset($_GET['comparative']);

if ($showComparativeOnly) {
    $pageTitle = t('comparative_quotations', $lang);
    $activeNav = 'comparative_quotations';
} else {
    $pageTitle = t('quotations', $lang);
    $activeNav = 'quotations';
}

$quotations = db_read('quotations');
usort($quotations, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

if ($showComparativeOnly) {
    $quotations = array_filter($quotations, fn($q) => !empty($q['is_comparative']));
    $topbarAction = '<a href="quotation-editor.php?comparative=1" class="btn btn-brass">+ ' . t('comparative_quotation', $lang) . '</a>';
} else {
    $quotations = array_filter($quotations, fn($q) => empty($q['is_comparative']));
    $topbarAction = '<a href="quotation-editor.php" class="btn btn-brass">+ ' . t('new_quotation', $lang) . '</a>';
}
require __DIR__ . '/includes/header.php';
?>

<div class="toolbar">
  <div class="search-box">
    <input type="text" id="qSearch" placeholder="<?= t('search', $lang) ?>...">
  </div>
  <div>
    <select id="statusFilter" style="width:auto;">
      <option value=""><?= t('status', $lang) ?>: <?= t('actions', $lang) === '' ? '' : 'All' ?></option>
      <option value="draft"><?= t('draft', $lang) ?></option>
      <option value="sent"><?= t('sent', $lang) ?></option>
      <option value="accepted"><?= t('accepted', $lang) ?></option>
      <option value="rejected"><?= t('rejected', $lang) ?></option>
      <option value="expired"><?= t('expired', $lang) ?></option>
    </select>
  </div>
</div>

<div class="card">
  <?php if (empty($quotations)): ?>
    <div class="empty-state">
      <div class="glyph">▦</div>
      <div><?= t('no_records', $lang) ?></div>
    </div>
  <?php else: ?>
  <table class="grid" id="qTable">
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
    <?php foreach ($quotations as $q): ?>
      <tr data-status="<?= h($q['status'] ?? 'draft') ?>" data-search="<?= h(strtolower(($q['number'] ?? '') . ' ' . ($q['client_snapshot']['name'] ?? ''))) ?>">
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
        <td>
          <select class="status-select" data-id="<?= h($q['id']) ?>" style="width:auto; padding:4px 8px; font-size:11.5px;">
            <?php foreach (['draft', 'sent', 'accepted', 'rejected', 'expired'] as $st): ?>
              <option value="<?= $st ?>" <?= ($q['status'] ?? 'draft') === $st ? 'selected' : '' ?>><?= t($st, $lang) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td style="white-space:nowrap;">
          <a href="quotation-view.php?id=<?= h($q['id']) ?>" class="btn btn-outline btn-sm"><?= t('view', $lang) ?></a>
          <a href="quotation-editor.php?id=<?= h($q['id']) ?>" class="btn btn-outline btn-sm"><?= t('edit', $lang) ?></a>
          <button class="btn btn-outline btn-sm dup-btn" data-id="<?= h($q['id']) ?>"><?= t('duplicate', $lang) ?></button>
          <button class="btn btn-danger btn-sm del-btn" data-id="<?= h($q['id']) ?>"><?= t('delete', $lang) ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<script>
document.getElementById('qSearch')?.addEventListener('input', filterTable);
document.getElementById('statusFilter')?.addEventListener('change', filterTable);
function filterTable() {
  const q = (document.getElementById('qSearch').value || '').toLowerCase();
  const st = document.getElementById('statusFilter').value;
  document.querySelectorAll('#qTable tbody tr').forEach(row => {
    const matchesText = row.dataset.search.includes(q);
    const matchesStatus = !st || row.dataset.status === st;
    row.style.display = (matchesText && matchesStatus) ? '' : 'none';
  });
}

document.querySelectorAll('.status-select').forEach(sel => {
  sel.addEventListener('change', async () => {
    const id = sel.dataset.id;
    await apiCall('api/quotations.php?action=status&id=' + id, 'POST', { status: sel.value });
    sel.closest('tr').dataset.status = sel.value;
    showToast('<?= t('saved_successfully', $lang) ?>');
  });
});

document.querySelectorAll('.dup-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const res = await apiCall('api/quotations.php?action=duplicate&id=' + btn.dataset.id, 'POST', {});
    if (res && res.ok) window.location = 'quotation-editor.php?id=' + res.id;
  });
});

document.querySelectorAll('.del-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!confirm('<?= t('confirm_delete', $lang) ?>')) return;
    await apiCall('api/quotations.php?id=' + btn.dataset.id, 'DELETE');
    btn.closest('tr').remove();
    showToast('<?= t('saved_successfully', $lang) ?>');
  });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
