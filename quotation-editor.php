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
    foreach (db_read('quotations') as $q) {
        if ($q['id'] === $editId) { $existing = $q; break; }
    }
}

$isComparative = !empty($existing['is_comparative']) || isset($_GET['comparative']);
$pageTitle = $existing ? t('edit', $lang) . ' — ' . $existing['number'] : ($isComparative ? t('comparative_quotation', $lang) : t('new_quotation', $lang));
$activeNav = 'quotations';
$bodyClass = 'page-editor';

$clients = db_read('clients');
$items = db_read('items');
$companies = db_read('companies');
usort($companies, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
$settings = db_read('settings');
$defaultValidity = (int)($settings['quotation']['default_validity_days'] ?? 15);
$defaultTemplate = $settings['quotation']['default_template'] ?? 'detailed';

require __DIR__ . '/includes/header.php';
?>

<form id="quoteForm" onsubmit="return false;">
<input type="hidden" id="q_id" value="<?= h($existing['id'] ?? '') ?>">

<div class="editor-grid">
  <div>

    <div class="card card-pad" style="margin-bottom:18px;">
      <div class="row">
        <div class="field">
          <label><?= t('client', $lang) ?> *</label>
          <select id="q_client_id" required>
            <option value=""><?= t('select_client', $lang) ?></option>
            <?php foreach ($clients as $c): ?>
              <option value="<?= h($c['id']) ?>" <?= ($existing['client_id'] ?? '') === $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint"><a href="clients.php"><?= t('new_client', $lang) ?></a></div>
        </div>
        <div class="field">
          <label><?= t('company', $lang) ?> *</label>
          <select id="q_company_id" required onchange="onCompanyChange()">
            <option value=""><?= t('select_company', $lang) ?></option>
            <?php foreach ($companies as $co): ?>
              <option value="<?= h($co['id']) ?>" data-state_code="<?= h($co['state_code'] ?? '33') ?>" <?= ($existing['company_id'] ?? '') === $co['id'] ? 'selected' : '' ?>><?= h($co['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint"><a href="company-editor.php"><?= t('add_company', $lang) ?></a></div>
          <input type="hidden" id="q_company_state_code" value="<?= h($existing['company_snapshot']['state_code'] ?? $settings['company']['state_code'] ?? '33') ?>">
        </div>
        <div class="field">
          <label><?= t('date', $lang) ?></label>
          <input type="date" id="q_date" value="<?= h($existing['date'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="field">
          <label><?= t('valid_until', $lang) ?></label>
          <input type="date" id="q_valid_until" value="<?= h($existing['valid_until'] ?? date('Y-m-d', strtotime("+{$defaultValidity} days"))) ?>">
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:18px;">
      <div class="card-head" style="flex-wrap:wrap; gap:12px;">
        <h3><?= t('item_description', $lang) ?></h3>
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
          <select id="catalogPick" style="width:180px;">
            <option value=""><?= t('add_from_catalog', $lang) ?></option>
            <?php foreach ($items as $it): ?>
              <option value='<?= json_encode($it, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'><?= h($it['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-outline btn-sm" onclick="addRow()"><?= t('add_item', $lang) ?></button>
        </div>
      </div>
      <div style="overflow-x:auto;">
      <table class="grid" id="itemsTable">
        <thead>
          <tr>
            <th style="width:30px;"><?= t('sno', $lang) ?></th>
            <th style="min-width:200px;"><?= t('item_description', $lang) ?></th>
            <th style="min-width:80px;"><?= t('hsn_sac', $lang) ?></th>
            <th style="min-width:70px;" class="num"><?= t('qty', $lang) ?></th>
            <th style="min-width:70px;"><?= t('unit', $lang) ?></th>
            <th style="min-width:90px;" class="num"><?= t('rate', $lang) ?></th>
            <th style="min-width:70px;" class="num"><?= t('discount', $lang) ?></th>
            <th style="min-width:70px;" class="num th-tax"><?= t('tax', $lang) ?></th>
            <th style="min-width:100px;" class="num"><?= t('amount', $lang) ?></th>
            <th style="width:40px;"></th>
          </tr>
        </thead>
        <tbody id="itemsBody"></tbody>
      </table>
      </div>
    </div>

    <div class="card card-pad" style="margin-bottom:18px;">
      <div class="field">
        <label><?= t('notes', $lang) ?></label>
        <textarea id="q_notes"><?= h($existing['notes'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label><?= t('terms', $lang) ?></label>
        <textarea id="q_terms" rows="5"><?= h($existing['terms'] ?? ($settings['quotation']["default_terms_{$lang}"] ?? '')) ?></textarea>
      </div>
    </div>

    <?php if ($isComparative): ?>
    <div class="card card-pad" style="margin-bottom:18px;">
      <h3>Comparative Quotation Settings</h3>
      <div class="row" style="margin-top:12px;">
        <div class="field">
          <label>Number of Companies</label>
          <select id="comp_num_companies" onchange="updateComparativeConfigFields()">
            <option value="2" <?= ($existing['comparative_config']['num_companies'] ?? 3) == 2 ? 'selected' : '' ?>>2 Companies</option>
            <option value="3" <?= ($existing['comparative_config']['num_companies'] ?? 3) == 3 ? 'selected' : '' ?>>3 Companies</option>
            <option value="4" <?= ($existing['comparative_config']['num_companies'] ?? 4) == 4 ? 'selected' : '' ?>>4 Companies</option>
            <option value="5" <?= ($existing['comparative_config']['num_companies'] ?? 5) == 5 ? 'selected' : '' ?>>5 Companies</option>
          </select>
        </div>
        <div class="field">
          <label>Prime Company</label>
          <select id="comp_prime_company" onchange="recalc()"></select>
        </div>
      </div>

      <div id="company_names_container" style="margin-top:12px; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px;">
        <!-- Populated via JS -->
      </div>

      <div id="price_differences_container" style="margin-top:12px; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px;">
        <!-- Populated via JS -->
      </div>
    </div>
    <?php endif; ?>

  </div>

  <div>
    <div class="card card-pad" style="margin-bottom:18px;">
      <label><?= t('language', $lang) ?></label>
      <div class="lang-switch" style="margin-bottom:16px;">
        <a href="#" data-val="en" class="lang-pick <?= ($existing['language'] ?? $lang) === 'en' ? 'active' : '' ?>">EN</a>
        <a href="#" data-val="ta" class="lang-pick <?= ($existing['language'] ?? $lang) === 'ta' ? 'active' : '' ?>">தமிழ்</a>
      </div>
      <input type="hidden" id="q_language" value="<?= h($existing['language'] ?? $lang) ?>">
      
      <label><?= t('tax_type', $lang) ?? 'Tax Settings' ?></label>
      <div class="template-pick" style="margin-bottom:16px;">
        <label>
          <input type="radio" name="q_is_gst_enabled" value="1" <?= (!isset($existing['is_gst_enabled']) || $existing['is_gst_enabled']) ? 'checked' : '' ?> onchange="toggleGstCols()">
          <span>With GST</span>
        </label>
        <label>
          <input type="radio" name="q_is_gst_enabled" value="0" <?= (isset($existing['is_gst_enabled']) && !$existing['is_gst_enabled']) ? 'checked' : '' ?> onchange="toggleGstCols()">
          <span>Without GST</span>
        </label>
      </div>

      <label><?= t('template_style', $lang) ?></label>
      <div class="template-pick">
        <?php $tplNames = ['simple' => 'Simple', 'detailed' => 'Detailed', 'gst' => 'GST Itemized', 'premium' => 'Premium']; ?>
        <?php foreach ($tplNames as $key => $label): ?>
        <label>
          <input type="radio" name="q_template" value="<?= $key ?>" <?= ($existing['template'] ?? $defaultTemplate) === $key ? 'checked' : '' ?>>
          <span><?= $label ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div id="totalsContainer">
      <div class="totals-box" style="margin-bottom:18px;">
        <div class="line"><span><?= t('subtotal', $lang) ?></span><span id="t_subtotal">₹0.00</span></div>
        <div class="line"><span><?= t('total_discount', $lang) ?></span><span id="t_discount">₹0.00</span></div>
        <div class="line"><span><?= t('taxable_amount', $lang) ?></span><span id="t_taxable">₹0.00</span></div>
        <div class="line" id="row_cgst"><span><?= t('cgst', $lang) ?></span><span id="t_cgst">₹0.00</span></div>
        <div class="line" id="row_sgst"><span><?= t('sgst', $lang) ?></span><span id="t_sgst">₹0.00</span></div>
        <div class="line" id="row_igst" style="display:none;"><span><?= t('igst', $lang) ?></span><span id="t_igst">₹0.00</span></div>
        <div class="line"><span><?= t('round_off', $lang) ?></span><span id="t_round">₹0.00</span></div>
        <div class="line grand"><span><?= t('grand_total', $lang) ?></span><span id="t_total">₹0.00</span></div>
      </div>
    </div>

    <button type="button" class="btn btn-primary btn-block" style="margin-bottom:10px;" onclick="saveQuotation('sent')"><?= t('save', $lang) ?></button>
    <button type="button" class="btn btn-outline btn-block" onclick="saveQuotation('draft')"><?= t('save_draft', $lang) ?></button>
  </div>
</div>
</form>

<script>
const CATALOG_ITEMS = <?= json_encode($items) ?>;
const CLIENTS = <?= json_encode($clients) ?>;
const COMPANIES = <?= json_encode($companies) ?>;
const EXISTING = <?= $existing ? json_encode($existing) : 'null' ?>;
const COMPANY_STATE_CODE = <?= json_encode($settings['company']['state_code'] ?? '33') ?>;
const IS_COMPARATIVE = <?= $isComparative ? 'true' : 'false' ?>;
const ITEM_NAMES = [...new Set(CATALOG_ITEMS.map(i => i.name).filter(Boolean))];
let rowSeq = 0;

function addRow(prefill) {
  rowSeq++;
  const tbody = document.getElementById('itemsBody');
  const tr = document.createElement('tr');
  tr.dataset.rowId = rowSeq;
  const options = ITEM_NAMES.map(n => `<option value="${esc(n)}">`).join('');
  tr.innerHTML = `
    <td>${tbody.children.length + 1}</td>
    <td><input type="text" class="r-name" list="itemNames" value="${prefill?.name ? esc(prefill.name) : ''}" placeholder="<?= t('item_description', $lang) ?>" style="min-width:180px;"></td>
    <td><input type="text" class="r-hsn" value="${prefill?.hsn ? esc(prefill.hsn) : ''}" style="min-width:70px;"></td>
    <td><input type="number" class="r-qty" value="${prefill?.qty || 1}" min="0" step="0.01" style="min-width:60px; text-align:right;"></td>
    <td><input type="text" class="r-unit" value="${prefill?.unit ? esc(prefill.unit) : 'Nos'}" style="min-width:60px;"></td>
    <td><input type="number" class="r-rate" value="${prefill?.rate || 0}" min="0" step="0.01" style="min-width:80px; text-align:right;"></td>
    <td><input type="number" class="r-disc" value="${prefill?.discount_percent || 0}" min="0" max="100" step="0.01" style="min-width:60px; text-align:right;"></td>
    <td class="td-tax"><input type="number" class="r-tax" value="${prefill?.tax_percent ?? 18}" min="0" max="28" step="0.01" style="min-width:60px; text-align:right;"></td>
    <td class="num r-amount" style="min-width:80px;">₹0.00</td>
    <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); recalc();" style="padding:4px 8px;">&times;</button></td>
  `;
  tbody.appendChild(tr);
  
  if (!document.getElementById('itemNames')) {
    const dl = document.createElement('datalist');
    dl.id = 'itemNames';
    dl.innerHTML = options;
    document.body.appendChild(dl);
  }
  
  tr.querySelectorAll('input').forEach(inp => inp.addEventListener('input', recalc));

  tr.querySelector('.r-name').addEventListener('blur', function() {
    const name = this.value.trim();
    const match = CATALOG_ITEMS.find(i => i.name === name);
    if (match) {
      tr.querySelector('.r-hsn').value = match.hsn ?? '';
      tr.querySelector('.r-unit').value = match.unit ?? 'Nos';
      tr.querySelector('.r-rate').value = match.rate ?? 0;
      tr.querySelector('.r-tax').value = match.tax_percent ?? 18;
      recalc();
    }
  });

  recalc();
}

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

document.getElementById('catalogPick').addEventListener('change', function () {
  if (!this.value) return;
  const it = JSON.parse(this.value);
  addRow({ name: it.name, hsn: it.hsn, unit: it.unit, rate: it.rate, tax_percent: it.tax_percent, qty: 1, discount_percent: 0 });
  this.value = '';
});

function fmt(n) {
  return '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function currentInterState() {
  const clientId = document.getElementById('q_client_id').value;
  const client = CLIENTS.find(c => c.id === clientId);
  const companyStateCode = document.getElementById('q_company_state_code').value || COMPANY_STATE_CODE;
  const stateCode = client ? (client.state_code || companyStateCode) : companyStateCode;
  return stateCode !== companyStateCode;
}

function onCompanyChange() {
  const sel = document.getElementById('q_company_id');
  const opt = sel.options[sel.selectedIndex];
  const stateCode = opt?.dataset?.state_code || COMPANY_STATE_CODE;
  document.getElementById('q_company_state_code').value = stateCode;
  recalc();
}

function toggleGstCols() {
  const isGstEnabled = document.querySelector('input[name=q_is_gst_enabled]:checked')?.value === '1';
  document.querySelectorAll('.th-tax, .td-tax').forEach(el => {
    el.style.display = isGstEnabled ? '' : 'none';
  });
  recalc();
}

function recalc() {
  const isGstEnabled = document.querySelector('input[name=q_is_gst_enabled]:checked')?.value === '1';
  const interState = currentInterState();
  
  const rows = Array.from(document.querySelectorAll('#itemsBody tr'));
  rows.forEach((tr, idx) => tr.children[0].textContent = idx + 1);

  const num = IS_COMPARATIVE ? (parseInt(document.getElementById('comp_num_companies')?.value) || 3) : 1;
  const nameSelects = document.querySelectorAll('.comp-name-input');
  const companies = [];
  for (let i = 1; i <= num; i++) {
    const name = nameSelects[i - 1]?.value.trim() || `Company ${i}`;
    companies.push(name);
  }

  const multipliers = IS_COMPARATIVE ? getComparativeMultipliers() : { 1: 1.0 };
  const groups = {};

  if (IS_COMPARATIVE) {
    companies.forEach((name, idx) => {
      groups[name] = { subtotal: 0, discountTotal: 0, taxable: 0, cgst: 0, sgst: 0, igst: 0 };
    });
  } else {
    groups['Standard'] = { subtotal: 0, discountTotal: 0, taxable: 0, cgst: 0, sgst: 0, igst: 0 };
  }

  rows.forEach(tr => {
    const qty = parseFloat(tr.querySelector('.r-qty').value) || 0;
    const rate = parseFloat(tr.querySelector('.r-rate').value) || 0;
    const disc = parseFloat(tr.querySelector('.r-disc').value) || 0;
    const tax = isGstEnabled ? (parseFloat(tr.querySelector('.r-tax').value) || 0) : 0;

    if (IS_COMPARATIVE) {
      companies.forEach((name, idx) => {
        const mult = multipliers[idx + 1] || 1.0;
        const scaledRate = rate * mult;
        const gross = qty * scaledRate;
        const discAmt = gross * disc / 100;
        const lineTaxable = gross - discAmt;
        let lineTax = 0;

        if (interState) {
          const lineIgst = lineTaxable * tax / 100;
          groups[name].igst += lineIgst;
          lineTax = lineIgst;
        } else {
          const half = lineTaxable * (tax / 2) / 100;
          groups[name].cgst += half;
          groups[name].sgst += half;
          lineTax = half * 2;
        }

        groups[name].subtotal += gross;
        groups[name].discountTotal += discAmt;
        groups[name].taxable += lineTaxable;
      });

      // Update the row total column using the prime company rate
      const primeIdx = parseInt(document.getElementById('comp_prime_company')?.value) || 1;
      const primeMult = multipliers[primeIdx] || 1.0;
      const primeRate = rate * primeMult;
      const gross = qty * primeRate;
      const discAmt = gross * disc / 100;
      const lineTaxable = gross - discAmt;
      const taxAmt = lineTaxable * tax / 100;
      tr.querySelector('.r-amount').textContent = fmt(lineTaxable + taxAmt);

    } else {
      const groupName = 'Standard';
      const gross = qty * rate;
      const discAmt = gross * disc / 100;
      const lineTaxable = gross - discAmt;
      let lineTax = 0;

      if (interState) {
        const lineIgst = lineTaxable * tax / 100;
        groups[groupName].igst += lineIgst;
        lineTax = lineIgst;
      } else {
        const half = lineTaxable * (tax / 2) / 100;
        groups[groupName].cgst += half;
        groups[groupName].sgst += half;
        lineTax = half * 2;
      }

      groups[groupName].subtotal += gross;
      groups[groupName].discountTotal += discAmt;
      groups[groupName].taxable += lineTaxable;
      tr.querySelector('.r-amount').textContent = fmt(lineTaxable + lineTax);
    }
  });

  const container = document.getElementById('totalsContainer');
  container.innerHTML = '';

  for (const [name, g] of Object.entries(groups)) {
    const grandRaw = g.taxable + g.cgst + g.sgst + g.igst;
    const grand = Math.round(grandRaw);
    const roundOff = grand - grandRaw;

    const html = `
      <div class="totals-box" style="margin-bottom:18px; border:1px solid var(--line); border-radius:6px; overflow:hidden;">
        ${IS_COMPARATIVE ? `<div style="background:#f8fafc; padding:8px 12px; font-weight:600; border-bottom:1px solid var(--line);">${esc(name)}</div>` : ''}
        <div style="padding:12px;">
          <div class="line"><span><?= t('subtotal', $lang) ?></span><span>${fmt(g.subtotal)}</span></div>
          <div class="line"><span><?= t('total_discount', $lang) ?></span><span>${fmt(g.discountTotal)}</span></div>
          <div class="line"><span><?= t('taxable_amount', $lang) ?></span><span>${fmt(g.taxable)}</span></div>
          ${isGstEnabled ? (!interState ? `
            <div class="line"><span><?= t('cgst', $lang) ?></span><span>${fmt(g.cgst)}</span></div>
            <div class="line"><span><?= t('sgst', $lang) ?></span><span>${fmt(g.sgst)}</span></div>
          ` : `
            <div class="line"><span><?= t('igst', $lang) ?></span><span>${fmt(g.igst)}</span></div>
          `) : ''}
          <div class="line"><span><?= t('round_off', $lang) ?></span><span>${fmt(roundOff)}</span></div>
          <div class="line grand" style="margin-top:8px; padding-top:8px; border-top:1px dashed var(--line);">
            <span><?= t('grand_total', $lang) ?></span><span>${fmt(grand)}</span>
          </div>
        </div>
      </div>
    `;
    container.innerHTML += html;
  }
}
document.getElementById('q_client_id').addEventListener('change', recalc);

document.querySelectorAll('.lang-pick').forEach(a => {
  a.addEventListener('click', (e) => {
    e.preventDefault();
    document.querySelectorAll('.lang-pick').forEach(x => x.classList.remove('active'));
    a.classList.add('active');
    document.getElementById('q_language').value = a.dataset.val;
  });
});

async function saveQuotation(status) {
  const clientId = document.getElementById('q_client_id').value;
  if (!clientId) { showToast('<?= t('select_client', $lang) ?>', true); return; }
  
  const companyId = document.getElementById('q_company_id').value;
  if (!companyId) { showToast('<?= t('select_company', $lang) ?>', true); return; }
  
  let comparativeTitle = '';

  const rows = Array.from(document.querySelectorAll('#itemsBody tr'));
  if (rows.length === 0) { showToast('<?= t('add_item', $lang) ?>', true); return; }

  const items = rows.map(tr => ({
    name: tr.querySelector('.r-name').value.trim(),
    hsn: tr.querySelector('.r-hsn').value.trim(),
    qty: parseFloat(tr.querySelector('.r-qty').value) || 0,
    unit: tr.querySelector('.r-unit').value.trim(),
    rate: parseFloat(tr.querySelector('.r-rate').value) || 0,
    discount_percent: parseFloat(tr.querySelector('.r-disc').value) || 0,
    tax_percent: parseFloat(tr.querySelector('.r-tax').value) || 0,
  }));

  const companyObj = COMPANIES.find(c => c.id === companyId);
  const payload = {
    id: document.getElementById('q_id').value || null,
    client_id: clientId,
    company_id: companyId,
    company_snapshot: companyObj || null,
    comparative_title: comparativeTitle,
    date: document.getElementById('q_date').value,
    valid_until: document.getElementById('q_valid_until').value,
    is_comparative: IS_COMPARATIVE ? 1 : 0,
    items,
    notes: document.getElementById('q_notes').value,
    terms: document.getElementById('q_terms').value,
    template: document.querySelector('input[name=q_template]:checked')?.value || 'detailed',
    language: document.getElementById('q_language').value,
    is_gst_enabled: document.querySelector('input[name=q_is_gst_enabled]:checked')?.value === '1',
    status,
  };

  if (IS_COMPARATIVE) {
    const num = parseInt(document.getElementById('comp_num_companies').value) || 3;
    const nameSelects = document.querySelectorAll('.comp-name-input');
    const companies = [];
    const companyIds = [];
    for (let i = 1; i <= num; i++) {
      const sel = nameSelects[i - 1];
      companies.push(sel?.value.trim() || `Company ${i}`);
      const selectedOpt = sel?.options[sel.selectedIndex];
      companyIds.push(selectedOpt?.dataset?.id || '');
    }
    payload.comparative_config = {
      num_companies: num,
      companies: companies,
      company_ids: companyIds,
      prime_company_index: parseInt(document.getElementById('comp_prime_company').value) || 1,
      diff_prime_to_2: parseInt(document.getElementById('comp_diff_prime_to_2')?.value) || 5,
      diff_2_to_3: parseInt(document.getElementById('comp_diff_2_3')?.value) || 5,
      diff_prime_to_4: parseInt(document.getElementById('comp_diff_prime_to_4')?.value) || 10,
      diff_4_to_5: parseInt(document.getElementById('comp_diff_4_5')?.value) || 5
    };
  }

  const res = await apiCall('api/quotations.php', 'POST', payload);
  if (res.ok) {
    showToast('<?= t('saved_successfully', $lang) ?>');
    setTimeout(() => { window.location = 'quotation-view.php?id=' + res.id; }, 500);
  }
}

function updateComparativeConfigFields() {
  if (!IS_COMPARATIVE) return;

  const config = EXISTING?.comparative_config || {};
  const num = parseInt(document.getElementById('comp_num_companies').value) || 3;
  
  // 1. Company names inputs (dropdown from companies list)
  const namesContainer = document.getElementById('company_names_container');
  let namesHtml = '';
  const companyOptions = COMPANIES.map(co => `<option value="${esc(co.name)}" data-id="${esc(co.id)}" data-state_code="${esc(co.state_code || '33')}">${esc(co.name)}</option>`).join('');
  for (let i = 1; i <= num; i++) {
    const val = config.companies?.[i - 1] || '';
    namesHtml += `
      <div class="field">
        <label>Company ${i} Name</label>
        <select class="comp-name-input" data-idx="${i}" onchange="updatePrimeCompanyOptions(); recalc();">
          <option value="">Select Company ${i}</option>
          ${COMPANIES.map(co => `<option value="${esc(co.name)}" ${val === co.name ? 'selected' : ''}>${esc(co.name)}</option>`).join('')}
          ${val && !COMPANIES.find(co => co.name === val) ? `<option value="${esc(val)}" selected>${esc(val)}</option>` : ''}
        </select>
      </div>
    `;
  }
  namesContainer.innerHTML = namesHtml;

  // 2. Prime company selector & differences dropdowns
  updatePrimeCompanyOptions();

  // 3. Difference dropdowns
  const diffsContainer = document.getElementById('price_differences_container');
  let diffsHtml = '';
  
  // Prime to 2
  const valP2 = config.diff_prime_to_2 ?? 5;
  diffsHtml += generateDiffSelectorHtml('Price Diff: Prime to Company 2', 'comp_diff_prime_to_2', valP2);

  // 2 to 3
  if (num >= 3) {
    const val23 = config.diff_2_to_3 ?? 5;
    diffsHtml += generateDiffSelectorHtml('Price Diff: Company 2 to 3', 'comp_diff_2_3', val23);
  }
  // Prime to 4
  if (num >= 4) {
    const valP4 = config.diff_prime_to_4 ?? 10;
    diffsHtml += generateDiffSelectorHtml('Price Diff: Prime to Company 4', 'comp_diff_prime_to_4', valP4);
  }
  // 4 to 5
  if (num >= 5) {
    const val45 = config.diff_4_to_5 ?? 5;
    diffsHtml += generateDiffSelectorHtml('Price Diff: Company 4 to 5', 'comp_diff_4_5', val45);
  }
  
  diffsContainer.innerHTML = diffsHtml;
  
  // Add listeners to new diff dropdowns
  diffsContainer.querySelectorAll('select').forEach(sel => sel.addEventListener('change', recalc));

  recalc();
}

function generateDiffSelectorHtml(label, id, selectedVal) {
  const options = [2, 3, 5, 8, 10, 12, 15, 20];
  let html = `
    <div class="field">
      <label>${label}</label>
      <select id="${id}">
  `;
  options.forEach(opt => {
    html += `<option value="${opt}" ${parseInt(selectedVal) === opt ? 'selected' : ''}>+${opt}%</option>`;
  });
  html += `
      </select>
    </div>
  `;
  return html;
}

function updatePrimeCompanyOptions() {
  const primeSelect = document.getElementById('comp_prime_company');
  const primeVal = EXISTING?.comparative_config?.prime_company_index || 1;
  const currentSelected = primeSelect.value || primeVal;
  
  const nameInputs = document.querySelectorAll('.comp-name-input');
  let html = '';
  nameInputs.forEach(sel => {
    const idx = sel.dataset.idx;
    const name = sel.value.trim() || `Company ${idx}`;
    html += `<option value="${idx}" ${currentSelected == idx ? 'selected' : ''}>${esc(name)}</option>`;
  });
  primeSelect.innerHTML = html;
}

function getComparativeMultipliers() {
  const p = parseInt(document.getElementById('comp_prime_company')?.value) || 1;
  const d12 = (parseFloat(document.getElementById('comp_diff_prime_to_2')?.value) || 5) / 100;
  const d23 = (parseFloat(document.getElementById('comp_diff_2_3')?.value) || 5) / 100;
  const d14 = (parseFloat(document.getElementById('comp_diff_prime_to_4')?.value) || 10) / 100;
  const d45 = (parseFloat(document.getElementById('comp_diff_4_5')?.value) || 5) / 100;

  const m = { 1: 1.0, 2: 1.0, 3: 1.0, 4: 1.0, 5: 1.0 };

  if (p === 1) {
    m[1] = 1.0;
    m[2] = 1.0 * (1 + d12);
    m[3] = m[2] * (1 + d23);
    m[4] = 1.0 * (1 + d14);
    m[5] = m[4] * (1 + d45);
  } else if (p === 2) {
    m[2] = 1.0;
    m[1] = 1.0 * (1 + d12);
    m[3] = 1.0 * (1 + d23);
    m[4] = 1.0 * (1 + d14);
    m[5] = m[4] * (1 + d45);
  } else if (p === 3) {
    m[3] = 1.0;
    m[2] = 1.0 * (1 + d23);
    m[1] = m[2] * (1 + d12);
    m[4] = 1.0 * (1 + d14);
    m[5] = m[4] * (1 + d45);
  } else if (p === 4) {
    m[4] = 1.0;
    m[5] = 1.0 * (1 + d45);
    m[1] = 1.0 * (1 + d14);
    m[2] = m[1] * (1 + d12);
    m[3] = m[2] * (1 + d23);
  } else if (p === 5) {
    m[5] = 1.0;
    m[4] = 1.0 * (1 + d45);
    m[1] = m[4] * (1 + d14);
    m[2] = m[1] * (1 + d12);
    m[3] = m[2] * (1 + d23);
  }

  return m;
}

// Initial rows
if (EXISTING && EXISTING.items && EXISTING.items.length) {
  EXISTING.items.forEach(it => addRow(it));
} else {
  addRow();
}
if (IS_COMPARATIVE) {
  updateComparativeConfigFields();
}
toggleGstCols();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
