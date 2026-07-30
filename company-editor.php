<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$lang = current_lang();
$editId = $_GET['id'] ?? '';
$existing = null;
if ($editId) {
    foreach (db_read('companies') as $co) {
        if ($co['id'] === $editId) { $existing = $co; break; }
    }
}

$pageTitle = $existing ? t('edit', $lang) . ' — ' . h($existing['name']) : t('add_company', $lang);
$activeNav = 'company';
$topbarAction = '<a href="company.php" class="btn btn-outline">← ' . t('back_to_list', $lang) . '</a>';
require __DIR__ . '/includes/header.php';
?>

<div class="card card-pad" style="margin-bottom:20px;">
  <h3><?= t('company_profile', $lang) ?></h3>
  <input type="hidden" id="co_id" value="<?= h($existing['id'] ?? '') ?>">

  <div class="row">
    <div class="field">
      <label style="display:block;"><?= t('company_name', $lang) ?> *</label>
      <input type="text" id="co_name" value="<?= h($existing['name'] ?? '') ?>" required>
    </div>
    <div class="field">
      <label style="display:block;"><?= t('tagline', $lang) ?></label>
      <input type="text" id="co_tagline" value="<?= h($existing['tagline'] ?? '') ?>">
    </div>
  </div>
  <div class="field">
    <label style="display:block;"><?= t('address', $lang) ?></label>
    <textarea id="co_address"><?= h($existing['address'] ?? '') ?></textarea>
  </div>
  <div class="row">
    <div class="field">
      <label style="display:block;"><?= t('phone', $lang) ?></label>
      <input type="text" id="co_phone" value="<?= h($existing['phone'] ?? '') ?>">
    </div>
    <div class="field">
      <label style="display:block;"><?= t('email', $lang) ?></label>
      <input type="email" id="co_email" value="<?= h($existing['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label style="display:block;">Website</label>
      <input type="text" id="co_website" value="<?= h($existing['website'] ?? '') ?>">
    </div>
  </div>
  <div class="row">
    <div class="field">
      <label style="display:block;"><?= t('gstin', $lang) ?></label>
      <input type="text" id="co_gstin" value="<?= h($existing['gstin'] ?? '') ?>">
    </div>
    <div class="field">
      <label style="display:block;"><?= t('pan', $lang) ?></label>
      <input type="text" id="co_pan" value="<?= h($existing['pan'] ?? '') ?>">
    </div>
    <div class="field">
      <label style="display:block;"><?= t('state', $lang) ?></label>
      <input type="text" id="co_state" value="<?= h($existing['state'] ?? 'Tamil Nadu') ?>">
    </div>
    <div class="field">
      <label style="display:block;"><?= t('state_code', $lang) ?></label>
      <input type="text" id="co_state_code" value="<?= h($existing['state_code'] ?? '33') ?>">
    </div>
  </div>

  <h3 style="margin-top:24px;"><?= t('bank_details', $lang) ?></h3>
  <div class="row">
    <div class="field"><label style="display:block;">Bank Name</label><input type="text" id="co_bank_name" value="<?= h($existing['bank_name'] ?? '') ?>"></div>
    <div class="field"><label style="display:block;">Account No.</label><input type="text" id="co_bank_account" value="<?= h($existing['bank_account'] ?? '') ?>"></div>
    <div class="field"><label style="display:block;">IFSC</label><input type="text" id="co_bank_ifsc" value="<?= h($existing['bank_ifsc'] ?? '') ?>"></div>
    <div class="field"><label style="display:block;">Branch</label><input type="text" id="co_bank_branch" value="<?= h($existing['bank_branch'] ?? '') ?>"></div>
  </div>

  <h3 style="margin-top:24px;"><?= t('logo', $lang) ?></h3>
  <div style="display:flex; align-items:center; gap:16px;">
    <?php if (!empty($existing['logo'])): ?>
      <img src="<?= h($existing['logo']) ?>?t=<?= time() ?>" style="height:56px; border:1px solid var(--line); border-radius:6px; padding:4px;">
    <?php endif; ?>
    <input type="file" id="co_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
  </div>

  <h3 style="margin-top:24px;">QR Code</h3>
  <div style="display:flex; align-items:center; gap:16px;">
    <?php if (!empty($existing['qr_code'])): ?>
      <img src="<?= h($existing['qr_code']) ?>?t=<?= time() ?>" style="height:80px; border:1px solid var(--line); border-radius:6px; padding:4px;">
    <?php endif; ?>
    <input type="file" id="co_qr" accept="image/png,image/jpeg,image/webp">
  </div>

  <div style="margin-top:24px; display:flex; gap:12px;">
    <button class="btn btn-primary" onclick="saveCompany()"><?= t('save', $lang) ?></button>
    <a href="company.php" class="btn btn-outline"><?= t('cancel', $lang) ?></a>
  </div>
</div>

<script>
async function saveCompany() {
  const payload = {
    id: document.getElementById('co_id').value || null,
    name: document.getElementById('co_name').value.trim(),
    tagline: document.getElementById('co_tagline').value.trim(),
    address: document.getElementById('co_address').value.trim(),
    phone: document.getElementById('co_phone').value.trim(),
    email: document.getElementById('co_email').value.trim(),
    website: document.getElementById('co_website').value.trim(),
    gstin: document.getElementById('co_gstin').value.trim(),
    pan: document.getElementById('co_pan').value.trim(),
    state: document.getElementById('co_state').value.trim(),
    state_code: document.getElementById('co_state_code').value.trim(),
    bank_name: document.getElementById('co_bank_name').value.trim(),
    bank_account: document.getElementById('co_bank_account').value.trim(),
    bank_ifsc: document.getElementById('co_bank_ifsc').value.trim(),
    bank_branch: document.getElementById('co_bank_branch').value.trim(),
  };
  if (!payload.name) { showToast('<?= t('company_name', $lang) ?> is required', true); return; }

  const res = await apiCall('api/companies.php', 'POST', payload);
  if (res.ok) {
    const coId = res.data?.id || payload.id;
    const logoInput = document.getElementById('co_logo');
    if (logoInput.files.length && coId) {
      const fd = new FormData();
      fd.append('id', coId);
      fd.append('logo', logoInput.files[0]);
      await fetch('api/companies.php?action=logo', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken() }, body: fd });
    }
    const qrInput = document.getElementById('co_qr');
    if (qrInput.files.length && coId) {
      const fd = new FormData();
      fd.append('id', coId);
      fd.append('qr', qrInput.files[0]);
      await fetch('api/companies.php?action=qr', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken() }, body: fd });
    }
    showToast('<?= t('saved_successfully', $lang) ?>');
    setTimeout(() => location.href = 'company.php', 500);
  }
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
