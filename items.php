<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$lang = current_lang();
$pageTitle = t('items', $lang);
$activeNav = 'items';
$items = db_read('items');
usort($items, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

$settings = db_read('settings');
$qs = $settings['quotation'] ?? [];

$itemColDefs = [
  ['key' => 'name', 'default' => 'Item Name'],
  ['key' => 'hsn',  'default' => 'HSN/SAC'],
  ['key' => 'unit', 'default' => 'Unit'],
  ['key' => 'rate', 'default' => 'Rate'],
  ['key' => 'tax',  'default' => 'Tax'],
];
$itemCols = [];
foreach ($itemColDefs as $def) {
  $k = $def['key'];
  $itemCols[$k] = [
    'show' => ($qs["customize_items_show_col_{$k}"] ?? 1) != 0,
    'label' => $qs["customize_items_lbl_col_{$k}"] !== '' && $qs["customize_items_lbl_col_{$k}"] !== null
      ? $qs["customize_items_lbl_col_{$k}"] : $def['default'],
  ];
}

$topbarAction = '<button class="btn btn-brass" onclick="openItemModal()">+ ' . t('add', $lang) . '</button>';
require __DIR__ . '/includes/header.php';
?>

<div class="toolbar">
  <div class="search-box">
    <input type="text" id="iSearch" placeholder="<?= t('search', $lang) ?>...">
  </div>
  <div class="toolbar-actions no-print">
    <div class="dropdown">
      <button class="btn btn-outline">⬇ <?= t('export', $lang) ?> ▾</button>
      <div class="dropdown-menu">
        <a href="export.php?table=items&type=excel">📊 <?= t('export_excel', $lang) ?></a>
        <a href="export.php?table=items&type=word">📝 <?= t('export_word', $lang) ?></a>
        <a href="export.php?table=items&type=pdf">📄 <?= t('export_pdf', $lang) ?></a>
      </div>
    </div>
    <button class="btn btn-outline" onclick="openImportModal('items')">⬆ <?= t('import', $lang) ?></button>
  </div>
</div>

<div class="card">
  <?php if (empty($items)): ?>
    <div class="empty-state">
      <div class="glyph">◫</div>
      <div><?= t('no_records', $lang) ?></div>
      <button class="btn btn-brass" style="margin-top:14px;" onclick="openItemModal()">+ <?= t('add', $lang) ?></button>
    </div>
  <?php else: ?>
  <table class="grid" id="iTable">
    <thead>
      <tr>
        <?php if ($itemCols['name']['show']): ?><th><?= h($itemCols['name']['label']) ?></th><?php endif; ?>
        <?php if ($itemCols['hsn']['show']): ?><th><?= h($itemCols['hsn']['label']) ?></th><?php endif; ?>
        <?php if ($itemCols['unit']['show']): ?><th><?= h($itemCols['unit']['label']) ?></th><?php endif; ?>
        <?php if ($itemCols['rate']['show']): ?><th class="num"><?= h($itemCols['rate']['label']) ?></th><?php endif; ?>
        <?php if ($itemCols['tax']['show']): ?><th class="num"><?= h($itemCols['tax']['label']) ?></th><?php endif; ?>
        <th><?= t('actions', $lang) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr data-search="<?= h(strtolower($it['name'] . ' ' . ($it['hsn'] ?? ''))) ?>">
        <?php if ($itemCols['name']['show']): ?><td><strong><?= h($it['name']) ?></strong><?php if (!empty($it['name_ta'])): ?><br><span class="lang-ta" style="font-size:12px;color:var(--slate)"><?= h($it['name_ta']) ?></span><?php endif; ?></td><?php endif; ?>
        <?php if ($itemCols['hsn']['show']): ?><td><?= h($it['hsn'] ?? '') ?></td><?php endif; ?>
        <?php if ($itemCols['unit']['show']): ?><td><?= h($it['unit'] ?? '') ?></td><?php endif; ?>
        <?php if ($itemCols['rate']['show']): ?><td class="num"><?= format_currency((float)($it['rate'] ?? 0)) ?></td><?php endif; ?>
        <?php if ($itemCols['tax']['show']): ?><td class="num"><?= h((string)($it['tax_percent'] ?? 0)) ?>%</td><?php endif; ?>
        <td>
          <button class="btn btn-outline btn-sm" onclick='openItemModal(<?= json_encode($it, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= t('edit', $lang) ?></button>
          <button class="btn btn-danger btn-sm" onclick="deleteItem('<?= h($it['id']) ?>')"><?= t('delete', $lang) ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Add/Edit Item Modal -->
<div class="modal-backdrop" id="itemModal">
  <div class="modal">
    <div class="modal-head">
      <h3 id="itemModalTitle"><?= t('add', $lang) ?></h3>
      <button class="modal-close" onclick="closeModal('itemModal')">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="i_id">
      <div class="field">
        <label><?= t('item_name', $lang) ?> (English) *</label>
        <input type="text" id="i_name" required>
      </div>
      <div class="field">
        <label><?= t('item_name', $lang) ?> (தமிழ்)</label>
        <input type="text" id="i_name_ta" class="lang-ta">
      </div>
      <div class="row">
        <div class="field">
          <label><?= t('hsn_sac', $lang) ?></label>
          <input type="text" id="i_hsn">
        </div>
        <div class="field">
          <label><?= t('unit', $lang) ?></label>
          <input type="text" id="i_unit" value="Nos">
        </div>
      </div>
      <div class="row">
        <div class="field">
          <label><?= t('rate', $lang) ?> (₹) *</label>
          <input type="number" id="i_rate" step="0.01" min="0" required>
        </div>
        <div class="field">
          <label><?= t('tax', $lang) ?></label>
          <select id="i_tax">
            <option value="0">0%</option>
            <option value="5">5%</option>
            <option value="12">12%</option>
            <option value="18" selected>18%</option>
            <option value="28">28%</option>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('itemModal')"><?= t('cancel', $lang) ?></button>
      <button class="btn btn-primary" onclick="saveItem()"><?= t('save', $lang) ?></button>
    </div>
  </div>
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
        <img id="importSampleImg" src="assets/img/import-items-sample.svg" alt="sample format">
        <div class="cap"><?= t('sample_format', $lang) ?></div>
      </div>
      <p class="hint"><?= t('import_hint', $lang) ?></p>
      <div class="import-file-row">
        <input type="file" id="importFile" accept=".csv,text/csv">
        <a id="importDownload" class="btn btn-outline btn-sm" href="export.php?table=items&type=excel&sample=1" download>⤓ <?= t('download_sample', $lang) ?></a>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('importModal')"><?= t('cancel', $lang) ?></button>
      <button class="btn btn-primary" onclick="submitImport(document.getElementById('importType').value, 'importFile')"><?= t('import', $lang) ?></button>
    </div>
  </div>
</div>

<script>
document.getElementById('iSearch')?.addEventListener('input', () => {
  const q = document.getElementById('iSearch').value.toLowerCase();
  document.querySelectorAll('#iTable tbody tr').forEach(r => {
    r.style.display = r.dataset.search.includes(q) ? '' : 'none';
  });
});

function openItemModal(it) {
  document.getElementById('itemModalTitle').textContent = it ? '<?= t('edit', $lang) ?>' : '<?= t('add', $lang) ?>';
  document.getElementById('i_id').value = it ? it.id : '';
  document.getElementById('i_name').value = it ? it.name : '';
  document.getElementById('i_name_ta').value = it ? (it.name_ta || '') : '';
  document.getElementById('i_hsn').value = it ? (it.hsn || '') : '';
  document.getElementById('i_unit').value = it ? (it.unit || 'Nos') : 'Nos';
  document.getElementById('i_rate').value = it ? it.rate : '';
  document.getElementById('i_tax').value = it ? it.tax_percent : '18';
  openModal('itemModal');
}

async function saveItem() {
  const payload = {
    id: document.getElementById('i_id').value || null,
    name: document.getElementById('i_name').value.trim(),
    name_ta: document.getElementById('i_name_ta').value.trim(),
    hsn: document.getElementById('i_hsn').value.trim(),
    unit: document.getElementById('i_unit').value.trim(),
    rate: parseFloat(document.getElementById('i_rate').value || 0),
    tax_percent: parseFloat(document.getElementById('i_tax').value || 0),
  };
  if (!payload.name) { showToast('<?= t('item_name', $lang) ?> is required', true); return; }
  const res = await apiCall('api/items.php', 'POST', payload);
  if (res.ok) { showToast('<?= t('saved_successfully', $lang) ?>'); setTimeout(() => location.reload(), 500); }
}

async function deleteItem(id) {
  if (!confirm('<?= t('confirm_delete', $lang) ?>')) return;
  const res = await apiCall('api/items.php?id=' + id, 'DELETE');
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
