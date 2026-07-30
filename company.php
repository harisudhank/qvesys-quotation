<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$lang = current_lang();
$pageTitle = t('companies', $lang);
$activeNav = 'company';
$companies = db_read('companies');
usort($companies, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

$topbarAction = '<a href="company-editor.php" class="btn btn-brass">+ ' . t('add_company', $lang) . '</a>';
require __DIR__ . '/includes/header.php';
?>

<div class="toolbar">
  <div class="search-box">
    <input type="text" id="coSearch" placeholder="<?= t('search', $lang) ?>...">
  </div>
  <div class="toolbar-actions no-print">
    <div class="dropdown">
      <button class="btn btn-outline">⬇ <?= t('export', $lang) ?> ▾</button>
      <div class="dropdown-menu">
        <a href="export.php?table=companies&type=excel">📊 <?= t('export_excel', $lang) ?></a>
        <a href="export.php?table=companies&type=word">📝 <?= t('export_word', $lang) ?></a>
        <a href="export.php?table=companies&type=pdf">📄 <?= t('export_pdf', $lang) ?></a>
      </div>
    </div>
    <button class="btn btn-outline" onclick="openImportModal('companies')">⬆ <?= t('import', $lang) ?></button>
  </div>
</div>

<div class="card">
  <?php if (empty($companies)): ?>
    <div class="empty-state">
      <div class="glyph">🏢</div>
      <div><?= t('no_records', $lang) ?></div>
      <a href="company-editor.php" class="btn btn-brass" style="margin-top:14px;">+ <?= t('add_company', $lang) ?></a>
    </div>
  <?php else: ?>
  <table class="grid" id="coTable">
    <thead>
      <tr>
        <th><?= t('company_name', $lang) ?></th>
        <th><?= t('gstin', $lang) ?></th>
        <th><?= t('phone', $lang) ?></th>
        <th><?= t('email', $lang) ?></th>
        <th><?= t('state', $lang) ?></th>
        <th><?= t('actions', $lang) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($companies as $co): ?>
      <tr data-search="<?= h(strtolower($co['name'] . ' ' . ($co['gstin'] ?? '') . ' ' . ($co['email'] ?? '') . ' ' . ($co['phone'] ?? ''))) ?>">
        <td><strong><?= h($co['name']) ?></strong><?php if (!empty($co['tagline'])): ?><br><span style="font-size:12px;color:var(--slate)"><?= h($co['tagline']) ?></span><?php endif; ?></td>
        <td><?= h($co['gstin'] ?? '') ?></td>
        <td><?= h($co['phone'] ?? '') ?></td>
        <td><?= h($co['email'] ?? '') ?></td>
        <td><?= h($co['state'] ?? '') ?></td>
        <td>
          <a href="company-editor.php?id=<?= h($co['id']) ?>" class="btn btn-outline btn-sm"><?= t('edit', $lang) ?></a>
          <button class="btn btn-danger btn-sm" onclick="deleteCompany('<?= h($co['id']) ?>')"><?= t('delete', $lang) ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Import Modal -->
<div class="modal-backdrop" id="importModal">
  <div class="modal">
    <div class="modal-head">
      <h3><?= t('import', $lang) ?></h3>
      <button class="modal-close" onclick="closeModal('importModal')">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="importType">
      <div class="import-sample">
        <img id="importSampleImg" src="assets/img/import-companies-sample.svg" alt="sample format">
        <div class="cap"><?= t('sample_format', $lang) ?></div>
      </div>
      <p class="hint"><?= t('import_hint', $lang) ?></p>
      <div class="import-file-row">
        <input type="file" id="importFile" accept=".csv,.xlsx,.xls,text/csv">
        <a id="importDownload" class="btn btn-outline btn-sm" href="export.php?table=companies&type=excel&sample=1" download>⤓ <?= t('download_sample', $lang) ?></a>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('importModal')"><?= t('cancel', $lang) ?></button>
      <button class="btn btn-primary" onclick="submitImport(document.getElementById('importType').value, 'importFile')"><?= t('import', $lang) ?></button>
    </div>
  </div>
</div>

<script>
document.getElementById('coSearch')?.addEventListener('input', () => {
  const q = document.getElementById('coSearch').value.toLowerCase();
  document.querySelectorAll('#coTable tbody tr').forEach(r => {
    r.style.display = r.dataset.search.includes(q) ? '' : 'none';
  });
});

async function deleteCompany(id) {
  if (!confirm('<?= t('confirm_delete', $lang) ?>')) return;
  const res = await apiCall('api/companies.php?id=' + id, 'DELETE');
  if (res.ok) { showToast('<?= t('saved_successfully', $lang) ?>'); setTimeout(() => location.reload(), 400); }
}

/* ---------- Import ---------- */
window.__lblImported = '<?= t('imported_count', $lang) ?>';
window.__lblSkipped = '<?= t('import_skipped', $lang) ?>';

function openImportModal(type) {
  document.getElementById('importType').value = type;
  document.getElementById('importSampleImg').src =
    'assets/img/import-' + type + '-sample.svg';
  document.getElementById('importFile').value = '';
  document.getElementById('importDownload').href =
    'export.php?table=' + type + '&type=excel&sample=1';
  openModal('importModal');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
