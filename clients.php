<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$lang = current_lang();
$pageTitle = t('clients', $lang);
$activeNav = 'clients';
$clients = db_read('clients');
usort($clients, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

$settings = db_read('settings');
$qs = $settings['quotation'] ?? [];

$clientColDefs = [
  ['key' => 'name',           'default' => 'Client Name'],
  ['key' => 'contact_person', 'default' => 'Contact Person'],
  ['key' => 'email',          'default' => 'Email'],
  ['key' => 'phone',          'default' => 'Phone'],
  ['key' => 'address',        'default' => 'Address'],
  ['key' => 'state',          'default' => 'State'],
  ['key' => 'state_code',     'default' => 'State Code'],
  ['key' => 'gstin',          'default' => 'GSTIN'],
];
$clientCols = [];
foreach ($clientColDefs as $def) {
  $k = $def['key'];
  $clientCols[$k] = [
    'show' => ($qs["customize_clients_show_col_{$k}"] ?? 1) != 0,
    'label' => $qs["customize_clients_lbl_col_{$k}"] !== '' && $qs["customize_clients_lbl_col_{$k}"] !== null
      ? $qs["customize_clients_lbl_col_{$k}"] : $def['default'],
  ];
}

$topbarAction = '<button class="btn btn-brass" onclick="openClientModal()" data-tip="Add a new client">+ ' . t('add', $lang) . '</button>';
require __DIR__ . '/includes/header.php';
?>

<div class="toolbar">
  <div class="search-box">
    <input type="text" id="cSearch" placeholder="<?= t('search', $lang) ?>..." data-tip="Search clients by name, contact or email">
  </div>
  <div class="toolbar-actions no-print">
    <div class="dropdown">
      <button class="btn btn-outline" data-tip="Export clients to a file">⬇ <?= t('export', $lang) ?> ▾</button>
      <div class="dropdown-menu">
        <a href="export.php?table=clients&type=excel" data-tip="Download as Excel (.xlsx)">📊 <?= t('export_excel', $lang) ?></a>
        <a href="export.php?table=clients&type=word" data-tip="Download as Word (.docx)">📝 <?= t('export_word', $lang) ?></a>
        <a href="export.php?table=clients&type=pdf" data-tip="Download as PDF">📄 <?= t('export_pdf', $lang) ?></a>
      </div>
    </div>
    <button class="btn btn-outline" onclick="openImportModal('clients')" data-tip="Import clients from a CSV or Excel file">⬆ <?= t('import', $lang) ?></button>
  </div>
</div>

<div class="card">
  <?php if (empty($clients)): ?>
    <div class="empty-state">
      <div class="glyph">☰</div>
      <div><?= t('no_records', $lang) ?></div>
      <button class="btn btn-brass" style="margin-top:14px;" onclick="openClientModal()" data-tip="Add a new client">+ <?= t('add', $lang) ?></button>
    </div>
  <?php else: ?>
  <table class="grid" id="cTable">
    <thead>
      <tr>
        <?php if ($clientCols['name']['show']): ?><th><?= h($clientCols['name']['label']) ?></th><?php endif; ?>
        <?php if ($clientCols['contact_person']['show']): ?><th><?= h($clientCols['contact_person']['label']) ?></th><?php endif; ?>
        <?php if ($clientCols['email']['show']): ?><th><?= h($clientCols['email']['label']) ?></th><?php endif; ?>
        <?php if ($clientCols['phone']['show']): ?><th><?= h($clientCols['phone']['label']) ?></th><?php endif; ?>
        <?php if ($clientCols['address']['show']): ?><th><?= h($clientCols['address']['label']) ?></th><?php endif; ?>
        <?php if ($clientCols['state']['show']): ?><th><?= h($clientCols['state']['label']) ?></th><?php endif; ?>
        <?php if ($clientCols['state_code']['show']): ?><th><?= h($clientCols['state_code']['label']) ?></th><?php endif; ?>
        <?php if ($clientCols['gstin']['show']): ?><th><?= h($clientCols['gstin']['label']) ?></th><?php endif; ?>
        <th><?= t('actions', $lang) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($clients as $c): ?>
      <tr data-search="<?= h(strtolower($c['name'] . ' ' . ($c['contact_person'] ?? '') . ' ' . ($c['email'] ?? ''))) ?>">
        <?php if ($clientCols['name']['show']): ?><td><strong><?= h($c['name']) ?></strong></td><?php endif; ?>
        <?php if ($clientCols['contact_person']['show']): ?><td><?= h($c['contact_person'] ?? '') ?></td><?php endif; ?>
        <?php if ($clientCols['email']['show']): ?><td><?= h($c['email'] ?? '') ?></td><?php endif; ?>
        <?php if ($clientCols['phone']['show']): ?><td><?= h($c['phone'] ?? '') ?></td><?php endif; ?>
        <?php if ($clientCols['address']['show']): ?><td><?= h($c['address'] ?? '') ?></td><?php endif; ?>
        <?php if ($clientCols['state']['show']): ?><td><?= h($c['state'] ?? '') ?></td><?php endif; ?>
        <?php if ($clientCols['state_code']['show']): ?><td><?= h($c['state_code'] ?? '') ?></td><?php endif; ?>
        <?php if ($clientCols['gstin']['show']): ?><td><?= h($c['gstin'] ?? '') ?></td><?php endif; ?>
        <td>
          <button class="btn btn-outline btn-sm" onclick='openClientModal(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' data-tip="Edit this client"><?= t('edit', $lang) ?></button>
          <button class="btn btn-danger btn-sm" onclick="deleteClient('<?= h($c['id']) ?>')" data-tip="Delete this client"><?= t('delete', $lang) ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Add/Edit Client Modal -->
<div class="modal-backdrop" id="clientModal">
  <div class="modal">
    <div class="modal-head">
      <h3 id="clientModalTitle"><?= t('add', $lang) ?> <?= t('client', $lang) ?></h3>
      <button class="modal-close" onclick="closeModal('clientModal')" data-tip="Close">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="c_id">
      <div class="field">
        <label><?= t('client_name', $lang) ?> *</label>
        <input type="text" id="c_name" required>
      </div>
      <div class="row">
        <div class="field">
          <label><?= t('contact_person', $lang) ?></label>
          <input type="text" id="c_contact_person">
        </div>
        <div class="field">
          <label><?= t('phone', $lang) ?></label>
          <input type="text" id="c_phone">
        </div>
      </div>
      <div class="field">
        <label><?= t('email', $lang) ?></label>
        <input type="email" id="c_email">
      </div>
      <div class="field">
        <label><?= t('address', $lang) ?></label>
        <textarea id="c_address"></textarea>
      </div>
      <div class="row">
        <div class="field">
          <label><?= t('state', $lang) ?></label>
          <input type="text" id="c_state" value="Tamil Nadu">
        </div>
        <div class="field">
          <label><?= t('state_code', $lang) ?></label>
          <input type="text" id="c_state_code" value="33">
        </div>
      </div>
      <div class="field">
        <label><?= t('gstin', $lang) ?></label>
        <input type="text" id="c_gstin">
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('clientModal')" data-tip="Discard changes and close"><?= t('cancel', $lang) ?></button>
      <button class="btn btn-primary" onclick="saveClient()" data-tip="Save the client details"><?= t('save', $lang) ?></button>
    </div>
  </div>
</div>

<!-- Import Modal -->
<div class="modal-backdrop" id="importModal">
  <div class="modal">
    <div class="modal-head">
      <h3><?= t('import', $lang) ?></h3>
      <button class="modal-close" onclick="closeModal('importModal')" data-tip="Close">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="importType">
      <div class="import-sample">
        <img id="importSampleImg" src="assets/img/import-clients-sample.svg" alt="sample format">
        <div class="cap"><?= t('sample_format', $lang) ?></div>
      </div>
      <p class="hint"><?= t('import_hint', $lang) ?></p>
      <div class="import-file-row">
        <input type="file" id="importFile" accept=".csv,.xlsx,.xls,text/csv" data-tip="Choose a CSV or Excel file to import">
        <a id="importDownload" class="btn btn-outline btn-sm" href="export.php?table=clients&type=excel&sample=1" download data-tip="Download a sample template file">⤓ <?= t('download_sample', $lang) ?></a>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('importModal')" data-tip="Discard and close"><?= t('cancel', $lang) ?></button>
      <button class="btn btn-primary" onclick="submitImport(document.getElementById('importType').value, 'importFile')" data-tip="Start importing the chosen file"><?= t('import', $lang) ?></button>
    </div>
  </div>
</div>

<script>
document.getElementById('cSearch')?.addEventListener('input', () => {
  const q = document.getElementById('cSearch').value.toLowerCase();
  document.querySelectorAll('#cTable tbody tr').forEach(r => {
    r.style.display = r.dataset.search.includes(q) ? '' : 'none';
  });
});

function openClientModal(c) {
  document.getElementById('clientModalTitle').textContent = c ? '<?= t('edit', $lang) ?>' : '<?= t('add', $lang) ?> <?= t('client', $lang) ?>';
  document.getElementById('c_id').value = c ? c.id : '';
  document.getElementById('c_name').value = c ? c.name : '';
  document.getElementById('c_contact_person').value = c ? (c.contact_person || '') : '';
  document.getElementById('c_phone').value = c ? (c.phone || '') : '';
  document.getElementById('c_email').value = c ? (c.email || '') : '';
  document.getElementById('c_address').value = c ? (c.address || '') : '';
  document.getElementById('c_state').value = c ? (c.state || 'Tamil Nadu') : 'Tamil Nadu';
  document.getElementById('c_state_code').value = c ? (c.state_code || '33') : '33';
  document.getElementById('c_gstin').value = c ? (c.gstin || '') : '';
  openModal('clientModal');
}

async function saveClient() {
  const payload = {
    id: document.getElementById('c_id').value || null,
    name: document.getElementById('c_name').value.trim(),
    contact_person: document.getElementById('c_contact_person').value.trim(),
    phone: document.getElementById('c_phone').value.trim(),
    email: document.getElementById('c_email').value.trim(),
    address: document.getElementById('c_address').value.trim(),
    state: document.getElementById('c_state').value.trim(),
    state_code: document.getElementById('c_state_code').value.trim(),
    gstin: document.getElementById('c_gstin').value.trim(),
  };
  if (!payload.name) { showToast('<?= t('client_name', $lang) ?> is required', true); return; }
  const res = await apiCall('api/clients.php', 'POST', payload);
  if (res.ok) { showToast('<?= t('saved_successfully', $lang) ?>'); setTimeout(() => location.reload(), 500); }
}

async function deleteClient(id) {
  if (!confirm('<?= t('confirm_delete', $lang) ?>')) return;
  const res = await apiCall('api/clients.php?id=' + id, 'DELETE');
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
