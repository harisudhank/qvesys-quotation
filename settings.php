<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$lang = current_lang();
$pageTitle = t('settings', $lang);
$activeNav = 'settings';
$settings = db_read('settings');
$user = db_read('user');
if (empty($user) && isset($settings['auth'])) {
    $user = $settings['auth'];
}
$company = $settings['company'];
$quotation = $settings['quotation'];

require __DIR__ . '/includes/header.php';
?>


<div class="card card-pad" style="margin-bottom:20px;">
  <h3><?= t('quotation_settings', $lang) ?></h3>
  <div class="row">
    <div class="field">
      <label style="display:block;"><?= t('number_prefix', $lang) ?></label>
      <input type="text" id="q_prefix" value="<?= h($quotation['prefix']) ?>" oninput="saveQuotationSettingsDebounced()">
    </div>
    <div class="field">
      <label style="display:block;"><?= t('default_validity', $lang) ?></label>
      <input type="number" id="q_validity" value="<?= h((string)$quotation['default_validity_days']) ?>" oninput="saveQuotationSettingsDebounced()">
    </div>
  </div>
  <div class="field">
    <label style="display:block;"><?= t('default_terms', $lang) ?> (English)</label>
    <textarea id="q_terms_en" rows="5" oninput="saveQuotationSettingsDebounced()"><?= h($quotation['default_terms_en']) ?></textarea>
  </div>
  <div class="field">
    <label style="display:block;"><?= t('default_terms', $lang) ?> (தமிழ்)</label>
    <textarea id="q_terms_ta" rows="5" class="lang-ta" oninput="saveQuotationSettingsDebounced()"><?= h($quotation['default_terms_ta']) ?></textarea>
  </div>
</div>

<div class="card card-pad" style="margin-bottom:20px;">
  <h3><?= t('bill_customization', $lang) ?></h3>

  <!-- Toggles -->
  <div class="row" style="margin-bottom:12px;">
    <div class="field">
      <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;">
        <input type="checkbox" id="c_header_enabled" <?= ($quotation['customize_header_enabled'] ?? 1) ? 'checked' : '' ?> style="width: auto; margin: 0; cursor: pointer;" onchange="autoSaveCustomization()">
        <?= t('enable_header', $lang) ?>
      </label>
    </div>
    <div class="field">
      <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;">
        <input type="checkbox" id="c_footer_enabled" <?= ($quotation['customize_footer_enabled'] ?? 1) ? 'checked' : '' ?> style="width: auto; margin: 0; cursor: pointer;" onchange="autoSaveCustomization()">
        <?= t('enable_footer', $lang) ?>
      </label>
    </div>
  </div>

  <!-- SECTION: Company Header Overrides -->
  <details open style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: #fafafa; margin-bottom:12px;">
    <summary style="font-weight: bold; cursor: pointer; user-select: none;">Company Header Overrides</summary>
    <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
      <div class="field">
        <label>Company Name</label>
        <input type="text" id="c_company_name" value="<?= h($quotation['customize_company_name'] ?? '') ?>" placeholder="Leave blank for default" oninput="autoSaveCustomizationDebounced()">
      </div>
      <div class="field">
        <label>Tagline</label>
        <input type="text" id="c_company_tagline" value="<?= h($quotation['customize_company_tagline'] ?? '') ?>" placeholder="Leave blank for default" oninput="autoSaveCustomizationDebounced()">
      </div>
      <div class="field">
        <label>Address</label>
        <input type="text" id="c_company_address" value="<?= h($quotation['customize_company_address'] ?? '') ?>" placeholder="Leave blank for default" oninput="autoSaveCustomizationDebounced()">
      </div>
      <div class="row">
        <div class="field">
          <label>Phone</label>
          <input type="text" id="c_company_phone" value="<?= h($quotation['customize_company_phone'] ?? '') ?>" placeholder="Leave blank for default" oninput="autoSaveCustomizationDebounced()">
        </div>
        <div class="field">
          <label>Email</label>
          <input type="text" id="c_company_email" value="<?= h($quotation['customize_company_email'] ?? '') ?>" placeholder="Leave blank for default" oninput="autoSaveCustomizationDebounced()">
        </div>
      </div>
      <div class="field">
        <label>Website</label>
        <input type="text" id="c_company_website" value="<?= h($quotation['customize_company_website'] ?? '') ?>" placeholder="Leave blank for default" oninput="autoSaveCustomizationDebounced()">
      </div>
      <div class="field">
        <label>Logo Override</label>
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
          <?php if (!empty($quotation['customize_logo'])): ?>
            <img src="<?= h($quotation['customize_logo']) ?>?t=<?= time() ?>" style="height:40px; border:1px solid var(--line); border-radius:4px; padding:2px;">
          <?php elseif (!empty($company['logo'])): ?>
            <img src="<?= h($company['logo']) ?>?t=<?= time() ?>" style="height:40px; border:1px solid var(--line); border-radius:4px; padding:2px; opacity:0.5;">
          <?php endif; ?>
          <input type="file" id="c_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" onchange="uploadBillLogo()" style="font-size:12px;">
          <?php if (!empty($quotation['customize_logo'])): ?>
            <button type="button" class="btn btn-outline btn-sm" onclick="removeBillLogo()" style="font-size:11px; padding:2px 8px;">Remove</button>
          <?php endif; ?>
        </div>
        <small style="color:#888; font-size:10px;">Overrides the global logo for all bills.</small>
      </div>
    </div>
  </details>

  <!-- SECTION: Tax & Bank Overrides -->
  <details style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: #fafafa; margin-bottom:12px;">
    <summary style="font-weight: bold; cursor: pointer; user-select: none;">Tax & Bank Overrides</summary>
    <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
      <div class="row">
        <div class="field">
          <label>GSTIN</label>
          <input type="text" id="c_company_gstin" value="<?= h($quotation['customize_company_gstin'] ?? '') ?>" placeholder="Leave blank for default" oninput="autoSaveCustomizationDebounced()">
        </div>
        <div class="field">
          <label>PAN</label>
          <input type="text" id="c_company_pan" value="<?= h($quotation['customize_company_pan'] ?? '') ?>" placeholder="Leave blank for default" oninput="autoSaveCustomizationDebounced()">
        </div>
      </div>
      <div class="row">
        <div class="field">
          <label>Bank Name</label>
          <input type="text" id="c_bank_name" value="<?= h($quotation['customize_bank_name'] ?? '') ?>" placeholder="Leave blank for default" oninput="autoSaveCustomizationDebounced()">
        </div>
        <div class="field">
          <label>Account Number</label>
          <input type="text" id="c_bank_account" value="<?= h($quotation['customize_bank_account'] ?? '') ?>" placeholder="Leave blank for default" oninput="autoSaveCustomizationDebounced()">
        </div>
      </div>
      <div class="row">
        <div class="field">
          <label>IFSC Code</label>
          <input type="text" id="c_bank_ifsc" value="<?= h($quotation['customize_bank_ifsc'] ?? '') ?>" placeholder="Leave blank for default" oninput="autoSaveCustomizationDebounced()">
        </div>
        <div class="field">
          <label>Branch</label>
          <input type="text" id="c_bank_branch" value="<?= h($quotation['customize_bank_branch'] ?? '') ?>" placeholder="Leave blank for default" oninput="autoSaveCustomizationDebounced()">
        </div>
      </div>
    </div>
  </details>

  <!-- SECTION: Styling & Toggles -->
  <details style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: #fafafa; margin-bottom:12px;">
    <summary style="font-weight: bold; cursor: pointer; user-select: none;">Styling & Toggles</summary>
    <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
      <div class="field">
        <label>Header Title</label>
        <input type="text" id="c_header_title" value="<?= h($quotation['customize_header_title'] ?? '') ?>" placeholder="e.g. TAX INVOICE" oninput="autoSaveCustomizationDebounced()">
      </div>
      <div class="field">
        <label>Footer Content</label>
        <textarea id="c_footer_content" rows="2" placeholder="e.g. Thank you for your business!" style="width:100%; border:1px solid var(--border-color); border-radius:4px; padding:8px; font-family:inherit;" oninput="autoSaveCustomizationDebounced()"><?= h($quotation['customize_footer_content'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label>Authorized Signatory Text</label>
        <input type="text" id="c_signatory" value="<?= h($quotation['customize_signatory'] ?? '') ?>" placeholder="Default: AUTHORIZED SIGNATORY" oninput="autoSaveCustomizationDebounced()">
      </div>
      <div class="row">
        <div class="field">
          <label><?= t('font_style', $lang) ?></label>
          <select id="c_font_family" onchange="autoSaveCustomization()">
            <?php foreach ([
              'Inter' => 'Inter (Modern Sans)',
              'Roboto' => 'Roboto (Classic Sans)',
              'Poppins' => 'Poppins (Rounded Sans)',
              'Outfit' => 'Outfit (Minimalist)',
              'Zilla Slab' => 'Zilla Slab (Serif Ledger)',
              'Georgia' => 'Georgia (Classic Serif)',
              'Playfair Display' => 'Playfair Display (Elegant Serif)',
              'Courier New' => 'Courier New (Monospace)'
            ] as $val => $lbl): ?>
              <option value="<?= h($val) ?>" <?= ($quotation['customize_font_family'] ?? 'Inter') === $val ? 'selected' : '' ?>><?= h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= t('font_size', $lang) ?></label>
          <select id="c_font_size" onchange="autoSaveCustomization()">
            <?php foreach ([
              '11px' => 'Small (11px)',
              '12.3px' => 'Medium (12.3px) [Default]',
              '14px' => 'Large (14px)',
              '16px' => 'Extra Large (16px)'
            ] as $val => $lbl): ?>
              <option value="<?= h($val) ?>" <?= ($quotation['customize_font_size'] ?? '12.3px') === $val ? 'selected' : '' ?>><?= h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row">
        <div class="field">
          <label><?= t('theme_preset', $lang) ?></label>
          <select id="c_theme_preset" onchange="applyThemePreset(this.value); autoSaveCustomization();">
            <option value="navy" <?= ($quotation['customize_theme_preset'] ?? 'navy') === 'navy' ? 'selected' : '' ?>><?= t('preset_classic', $lang) ?></option>
            <option value="dark" <?= ($quotation['customize_theme_preset'] ?? 'navy') === 'dark' ? 'selected' : '' ?>><?= t('preset_dark', $lang) ?></option>
            <option value="green" <?= ($quotation['customize_theme_preset'] ?? 'navy') === 'green' ? 'selected' : '' ?>><?= t('preset_green', $lang) ?></option>
            <option value="purple" <?= ($quotation['customize_theme_preset'] ?? 'navy') === 'purple' ? 'selected' : '' ?>><?= t('preset_purple', $lang) ?></option>
            <option value="red" <?= ($quotation['customize_theme_preset'] ?? 'navy') === 'red' ? 'selected' : '' ?>><?= t('preset_red', $lang) ?></option>
            <option value="custom" <?= ($quotation['customize_theme_preset'] ?? 'navy') === 'custom' ? 'selected' : '' ?>><?= t('preset_custom', $lang) ?></option>
          </select>
        </div>
        <div class="field">
          <label><?= t('theme_color', $lang) ?></label>
          <div style="display:flex; gap:8px; align-items:center;">
            <input type="color" id="c_theme_color" value="<?= h($quotation['customize_theme_color'] ?? '#16223c') ?>" style="width:50px; height:38px; padding:2px; cursor:pointer;" oninput="onColorChange()">
            <input type="text" id="c_theme_color_hex" value="<?= h($quotation['customize_theme_color'] ?? '#16223c') ?>" style="width:100px;" oninput="onHexChange('c_theme_color', this.value)">
          </div>
        </div>
        <div class="field">
          <label><?= t('accent_color', $lang) ?></label>
          <div style="display:flex; gap:8px; align-items:center;">
            <input type="color" id="c_accent_color" value="<?= h($quotation['customize_accent_color'] ?? '#B8912F') ?>" style="width:50px; height:38px; padding:2px; cursor:pointer;" oninput="onColorChange()">
            <input type="text" id="c_accent_color_hex" value="<?= h($quotation['customize_accent_color'] ?? '#B8912F') ?>" style="width:100px;" oninput="onHexChange('c_accent_color', this.value)">
          </div>
        </div>
      </div>
    </div>
  </details>

  <!-- SECTION: Table Columns -->
  <details style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: #fafafa; margin-bottom:12px;">
    <summary style="font-weight: bold; cursor: pointer; user-select: none;">Table Columns</summary>
    <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 12px;">
      <?php
      $columns = [
        ['key' => 'sno',          'label' => 'S.No',                'default' => 'S.No'],
        ['key' => 'desc',         'label' => 'Item / Description',  'default' => 'Item / Description'],
        ['key' => 'hsn',          'label' => 'HSN/SAC',             'default' => 'HSN/SAC'],
        ['key' => 'qty',          'label' => 'Quantity',            'default' => 'Qty'],
        ['key' => 'rate',         'label' => 'Rate',                'default' => 'Rate'],
        ['key' => 'discount',     'label' => 'Discount',            'default' => 'Discount'],
        ['key' => 'taxable',      'label' => 'Taxable Amount',      'default' => 'Taxable Amount'],
        ['key' => 'tax_percent',  'label' => 'Tax Rate (Tax %)',    'default' => 'Tax %'],
        ['key' => 'gst',          'label' => 'GST (CGST/SGST/IGST)','default' => 'GST'],
        ['key' => 'amount',       'label' => 'Amount',              'default' => 'Amount'],
      ];
      foreach ($columns as $col):
        $k = $col['key'];
        $show = ($quotation["customize_show_col_{$k}"] ?? 1) ? 'checked' : '';
        $lbl = h($quotation["customize_lbl_col_{$k}"] ?? '');
      ?>
      <div style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
          <span style="font-weight:600; font-size:12px;"><?= $col['label'] ?></span>
          <label style="display:flex; align-items:center; gap:4px; font-size:11px; cursor:pointer;">
            <input type="checkbox" id="c_show_col_<?= $k ?>" <?= $show ?> onchange="autoSaveCustomization()"> Show
          </label>
        </div>
        <input type="text" id="c_lbl_col_<?= $k ?>" value="<?= $lbl ?>" placeholder="Default: <?= h($col['default']) ?>" oninput="autoSaveCustomizationDebounced()" style="font-size:12px; padding:4px 8px; width:100%; box-sizing:border-box;">
      </div>
      <?php endforeach; ?>
    </div>
  </details>

  <!-- SECTION: Clients Table Columns -->
  <details style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: #fafafa; margin-bottom:12px;">
    <summary style="font-weight: bold; cursor: pointer; user-select: none;">Clients Table Columns</summary>
    <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 12px;">
      <?php
      $clientColumns = [
        ['key' => 'name',           'label' => 'Client Name',      'default' => 'Client Name'],
        ['key' => 'contact_person', 'label' => 'Contact Person',   'default' => 'Contact Person'],
        ['key' => 'email',          'label' => 'Email',            'default' => 'Email'],
        ['key' => 'phone',          'label' => 'Phone',            'default' => 'Phone'],
        ['key' => 'address',        'label' => 'Address',          'default' => 'Address'],
        ['key' => 'state',          'label' => 'State',            'default' => 'State'],
        ['key' => 'state_code',     'label' => 'State Code',       'default' => 'State Code'],
        ['key' => 'gstin',          'label' => 'GSTIN',            'default' => 'GSTIN'],
      ];
      foreach ($clientColumns as $col):
        $k = $col['key'];
        $show = ($quotation["customize_clients_show_col_{$k}"] ?? 1) ? 'checked' : '';
        $lbl = h($quotation["customize_clients_lbl_col_{$k}"] ?? '');
      ?>
      <div style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
          <span style="font-weight:600; font-size:12px;"><?= $col['label'] ?></span>
          <label style="display:flex; align-items:center; gap:4px; font-size:11px; cursor:pointer;">
            <input type="checkbox" id="c_clients_show_col_<?= $k ?>" <?= $show ?> onchange="autoSaveCustomization()"> Show
          </label>
        </div>
        <input type="text" id="c_clients_lbl_col_<?= $k ?>" value="<?= $lbl ?>" placeholder="Default: <?= h($col['default']) ?>" oninput="autoSaveCustomizationDebounced()" style="font-size:12px; padding:4px 8px; width:100%; box-sizing:border-box;">
      </div>
      <?php endforeach; ?>
    </div>
  </details>

  <!-- SECTION: Items Table Columns -->
  <details style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: #fafafa; margin-bottom:12px;">
    <summary style="font-weight: bold; cursor: pointer; user-select: none;">Items Table Columns</summary>
    <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 12px;">
      <?php
      $itemColumns = [
        ['key' => 'name',     'label' => 'Item Name',  'default' => 'Item Name'],
        ['key' => 'hsn',      'label' => 'HSN/SAC',    'default' => 'HSN/SAC'],
        ['key' => 'unit',     'label' => 'Unit',        'default' => 'Unit'],
        ['key' => 'rate',     'label' => 'Rate',        'default' => 'Rate'],
        ['key' => 'tax',      'label' => 'Tax',         'default' => 'Tax'],
      ];
      foreach ($itemColumns as $col):
        $k = $col['key'];
        $show = ($quotation["customize_items_show_col_{$k}"] ?? 1) ? 'checked' : '';
        $lbl = h($quotation["customize_items_lbl_col_{$k}"] ?? '');
      ?>
      <div style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
          <span style="font-weight:600; font-size:12px;"><?= $col['label'] ?></span>
          <label style="display:flex; align-items:center; gap:4px; font-size:11px; cursor:pointer;">
            <input type="checkbox" id="c_items_show_col_<?= $k ?>" <?= $show ?> onchange="autoSaveCustomization()"> Show
          </label>
        </div>
        <input type="text" id="c_items_lbl_col_<?= $k ?>" value="<?= $lbl ?>" placeholder="Default: <?= h($col['default']) ?>" oninput="autoSaveCustomizationDebounced()" style="font-size:12px; padding:4px 8px; width:100%; box-sizing:border-box;">
      </div>
      <?php endforeach; ?>
    </div>
  </details>

  <!-- SECTION: Watermark -->
  <details style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: #fafafa;">
    <summary style="font-weight: bold; cursor: pointer; user-select: none;">Watermark</summary>
    <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 12px;">
      <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600;">
        <input type="checkbox" id="c_watermark_enabled" <?= ($quotation['customize_watermark_enabled'] ?? 0) ? 'checked' : '' ?> onchange="autoSaveCustomization()">
        Enable Watermark
      </label>
      <div class="field">
        <label>Watermark Text</label>
        <input type="text" id="c_watermark_text" value="<?= h($quotation['customize_watermark_text'] ?? '') ?>" placeholder="e.g. DRAFT, CONFIDENTIAL, PAID" oninput="autoSaveCustomizationDebounced()">
      </div>
      <div class="field">
        <label>Color</label>
        <div style="display:flex; gap:8px; align-items:center;">
          <input type="color" id="c_watermark_color" value="<?= h($quotation['customize_watermark_color'] ?? '#16223c') ?>" style="width:40px; height:30px; padding:0; cursor:pointer;" oninput="autoSaveCustomizationDebounced()">
          <span style="font-size:11px; color:#666;">Pick a watermark color</span>
        </div>
      </div>
      <div class="field">
        <label>Opacity — <span id="c_watermark_opacity_val"><?= h($quotation['customize_watermark_opacity'] ?? '0.07') ?></span></label>
        <input type="range" id="c_watermark_opacity" min="0.01" max="0.30" step="0.01" value="<?= h($quotation['customize_watermark_opacity'] ?? '0.07') ?>" style="width:100%;" oninput="document.getElementById('c_watermark_opacity_val').textContent=this.value; autoSaveCustomizationDebounced()">
        <div style="display:flex; justify-content:space-between; font-size:10px; color:#aaa;"><span>Light</span><span>Visible</span></div>
      </div>
      <div class="field">
        <label>Font Size</label>
        <select id="c_watermark_size" onchange="autoSaveCustomization()">
          <?php foreach (['40px' => 'Small (40px)', '60px' => 'Medium (60px)', '80px' => 'Large (80px)', '100px' => 'Extra Large (100px)'] as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= ($quotation['customize_watermark_size'] ?? '60px') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </details>

</div>

<div class="card card-pad">
  <h3>Account & Login Credentials</h3>
  <div class="row">
    <div class="field">
      <label><?= t('username', $lang) ?></label>
      <input type="text" id="acc_username" value="<?= h($user['username'] ?? 'admin') ?>">
    </div>
    <div class="field">
      <label>Display Name</label>
      <input type="text" id="acc_name" value="<?= h($user['name'] ?? 'Admin') ?>">
    </div>
  </div>
  <div class="row" style="margin-top:12px;">
    <div class="field">
      <label><?= t('current_password', $lang) ?> <small style="color:var(--text-muted);">(Required to authorize changes)</small></label>
      <input type="password" id="p_current">
    </div>
    <div class="field">
      <label><?= t('new_password', $lang) ?> <small style="color:var(--text-muted);">(Leave blank to keep current)</small></label>
      <input type="password" id="p_new">
    </div>
  </div>
  <button class="btn btn-outline" onclick="saveAccountCredentials()"><?= t('save', $lang) ?></button>
</div>


<script>

async function saveQuotationSettings() {
  const quotation = {
    prefix: document.getElementById('q_prefix').value.trim(),
    default_validity_days: parseInt(document.getElementById('q_validity').value || 15),
    default_terms_en: document.getElementById('q_terms_en').value,
    default_terms_ta: document.getElementById('q_terms_ta').value,
  };
  await apiCall('api/settings.php', 'POST', { quotation });
}

let quotationSaveTimer = null;
function saveQuotationSettingsDebounced() {
  clearTimeout(quotationSaveTimer);
  quotationSaveTimer = setTimeout(saveQuotationSettings, 400);
}

const themePresets = {
  navy: { primary: '#16223c', accent: '#B8912F' },
  dark: { primary: '#1e293b', accent: '#64748b' },
  green: { primary: '#1F6F54', accent: '#10b981' },
  purple: { primary: '#581c87', accent: '#a855f7' },
  red: { primary: '#991b1b', accent: '#ef4444' }
};

function applyThemePreset(preset) {
  if (themePresets[preset]) {
    const colors = themePresets[preset];
    document.getElementById('c_theme_color').value = colors.primary;
    document.getElementById('c_theme_color_hex').value = colors.primary;
    document.getElementById('c_accent_color').value = colors.accent;
    document.getElementById('c_accent_color_hex').value = colors.accent;
  }
}

let autoSaveTimer = null;

function autoSaveCustomizationDebounced() {
  clearTimeout(autoSaveTimer);
  autoSaveTimer = setTimeout(autoSaveCustomization, 400);
}

function onColorChange() {
  document.getElementById('c_theme_color_hex').value = document.getElementById('c_theme_color').value;
  document.getElementById('c_accent_color_hex').value = document.getElementById('c_accent_color').value;
  document.getElementById('c_theme_preset').value = 'custom';
  autoSaveCustomizationDebounced();
}

function onHexChange(pickerId, hexValue) {
  if (/^#[0-9A-F]{6}$/i.test(hexValue)) {
    document.getElementById(pickerId).value = hexValue;
    document.getElementById('c_theme_preset').value = 'custom';
    autoSaveCustomizationDebounced();
  }
}

async function autoSaveCustomization() {
  const v = id => { const e = document.getElementById(id); return e ? (e.type === 'checkbox' ? (e.checked ? 1 : 0) : e.value) : null; };
  const quotation = {};
  const fields = [
    'customize_header_enabled', 'customize_footer_enabled', 'customize_header_title', 'customize_footer_content',
    'customize_company_name', 'customize_company_tagline', 'customize_company_address',
    'customize_company_phone', 'customize_company_email', 'customize_company_website',
    'customize_company_gstin', 'customize_company_pan',
    'customize_bank_name', 'customize_bank_account', 'customize_bank_ifsc', 'customize_bank_branch',
    'customize_signatory', 'customize_font_family', 'customize_font_size',
    'customize_theme_preset', 'customize_theme_color', 'customize_accent_color',
    'customize_show_col_sno', 'customize_show_col_desc', 'customize_show_col_hsn', 'customize_show_col_qty',
    'customize_show_col_rate', 'customize_show_col_discount', 'customize_show_col_taxable',
    'customize_show_col_tax_percent', 'customize_show_col_gst', 'customize_show_col_amount',
    'customize_lbl_col_sno', 'customize_lbl_col_desc', 'customize_lbl_col_hsn', 'customize_lbl_col_qty',
    'customize_lbl_col_rate', 'customize_lbl_col_discount', 'customize_lbl_col_taxable',
    'customize_lbl_col_tax_percent', 'customize_lbl_col_gst', 'customize_lbl_col_amount',
    'customize_watermark_enabled', 'customize_watermark_text', 'customize_watermark_color',
    'customize_watermark_opacity', 'customize_watermark_size',
    'customize_clients_show_col_name', 'customize_clients_show_col_contact_person',
    'customize_clients_show_col_email', 'customize_clients_show_col_phone',
    'customize_clients_show_col_address', 'customize_clients_show_col_state',
    'customize_clients_show_col_state_code', 'customize_clients_show_col_gstin',
    'customize_clients_lbl_col_name', 'customize_clients_lbl_col_contact_person',
    'customize_clients_lbl_col_email', 'customize_clients_lbl_col_phone',
    'customize_clients_lbl_col_address', 'customize_clients_lbl_col_state',
    'customize_clients_lbl_col_state_code', 'customize_clients_lbl_col_gstin',
    'customize_items_show_col_name', 'customize_items_show_col_hsn', 'customize_items_show_col_unit',
    'customize_items_show_col_rate', 'customize_items_show_col_tax',
    'customize_items_lbl_col_name', 'customize_items_lbl_col_hsn', 'customize_items_lbl_col_unit',
    'customize_items_lbl_col_rate', 'customize_items_lbl_col_tax',
  ];
  const mapping = {
    'customize_header_enabled': 'c_header_enabled', 'customize_footer_enabled': 'c_footer_enabled',
    'customize_header_title': 'c_header_title', 'customize_footer_content': 'c_footer_content',
    'customize_company_name': 'c_company_name', 'customize_company_tagline': 'c_company_tagline',
    'customize_company_address': 'c_company_address', 'customize_company_phone': 'c_company_phone',
    'customize_company_email': 'c_company_email', 'customize_company_website': 'c_company_website',
    'customize_company_gstin': 'c_company_gstin', 'customize_company_pan': 'c_company_pan',
    'customize_bank_name': 'c_bank_name', 'customize_bank_account': 'c_bank_account',
    'customize_bank_ifsc': 'c_bank_ifsc', 'customize_bank_branch': 'c_bank_branch',
    'customize_signatory': 'c_signatory', 'customize_font_family': 'c_font_family',
    'customize_font_size': 'c_font_size', 'customize_theme_preset': 'c_theme_preset',
    'customize_theme_color': 'c_theme_color', 'customize_accent_color': 'c_accent_color',
    'customize_show_col_sno': 'c_show_col_sno', 'customize_show_col_desc': 'c_show_col_desc',
    'customize_show_col_hsn': 'c_show_col_hsn', 'customize_show_col_qty': 'c_show_col_qty',
    'customize_show_col_rate': 'c_show_col_rate', 'customize_show_col_discount': 'c_show_col_discount',
    'customize_show_col_taxable': 'c_show_col_taxable', 'customize_show_col_tax_percent': 'c_show_col_tax_percent',
    'customize_show_col_gst': 'c_show_col_gst', 'customize_show_col_amount': 'c_show_col_amount',
    'customize_lbl_col_sno': 'c_lbl_col_sno', 'customize_lbl_col_desc': 'c_lbl_col_desc',
    'customize_lbl_col_hsn': 'c_lbl_col_hsn', 'customize_lbl_col_qty': 'c_lbl_col_qty',
    'customize_lbl_col_rate': 'c_lbl_col_rate', 'customize_lbl_col_discount': 'c_lbl_col_discount',
    'customize_lbl_col_taxable': 'c_lbl_col_taxable', 'customize_lbl_col_tax_percent': 'c_lbl_col_tax_percent',
    'customize_lbl_col_gst': 'c_lbl_col_gst', 'customize_lbl_col_amount': 'c_lbl_col_amount',
    'customize_watermark_enabled': 'c_watermark_enabled', 'customize_watermark_text': 'c_watermark_text',
    'customize_watermark_color': 'c_watermark_color', 'customize_watermark_opacity': 'c_watermark_opacity',
    'customize_watermark_size': 'c_watermark_size',
    'customize_clients_show_col_name': 'c_clients_show_col_name',
    'customize_clients_show_col_contact_person': 'c_clients_show_col_contact_person',
    'customize_clients_show_col_email': 'c_clients_show_col_email',
    'customize_clients_show_col_phone': 'c_clients_show_col_phone',
    'customize_clients_show_col_address': 'c_clients_show_col_address',
    'customize_clients_show_col_state': 'c_clients_show_col_state',
    'customize_clients_show_col_state_code': 'c_clients_show_col_state_code',
    'customize_clients_show_col_gstin': 'c_clients_show_col_gstin',
    'customize_clients_lbl_col_name': 'c_clients_lbl_col_name',
    'customize_clients_lbl_col_contact_person': 'c_clients_lbl_col_contact_person',
    'customize_clients_lbl_col_email': 'c_clients_lbl_col_email',
    'customize_clients_lbl_col_phone': 'c_clients_lbl_col_phone',
    'customize_clients_lbl_col_address': 'c_clients_lbl_col_address',
    'customize_clients_lbl_col_state': 'c_clients_lbl_col_state',
    'customize_clients_lbl_col_state_code': 'c_clients_lbl_col_state_code',
    'customize_clients_lbl_col_gstin': 'c_clients_lbl_col_gstin',
    'customize_items_show_col_name': 'c_items_show_col_name',
    'customize_items_show_col_hsn': 'c_items_show_col_hsn',
    'customize_items_show_col_unit': 'c_items_show_col_unit',
    'customize_items_show_col_rate': 'c_items_show_col_rate',
    'customize_items_show_col_tax': 'c_items_show_col_tax',
    'customize_items_lbl_col_name': 'c_items_lbl_col_name',
    'customize_items_lbl_col_hsn': 'c_items_lbl_col_hsn',
    'customize_items_lbl_col_unit': 'c_items_lbl_col_unit',
    'customize_items_lbl_col_rate': 'c_items_lbl_col_rate',
    'customize_items_lbl_col_tax': 'c_items_lbl_col_tax',
  };
  for (const key of fields) {
    const val = v(mapping[key]);
    if (val !== null) quotation[key] = val;
  }
  await apiCall('api/settings.php', 'POST', { quotation });
}



async function uploadBillLogo() {
  const fileInput = document.getElementById('c_logo');
  if (!fileInput.files.length) return;
  const fd = new FormData();
  fd.append('logo', fileInput.files[0]);
  const res = await fetch('api/settings.php?action=bill_logo', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken() }, body: fd });
  const data = await res.json();
  if (data.ok) { showToast('<?= t('saved_successfully', $lang) ?>'); setTimeout(() => location.reload(), 500); }
  else showToast(data.error || 'Upload failed', true);
}

async function removeBillLogo() {
  const res = await apiCall('api/settings.php', 'POST', { quotation: { customize_logo: '' } });
  if (res.ok) { showToast('<?= t('saved_successfully', $lang) ?>'); setTimeout(() => location.reload(), 500); }
}

async function saveAccountCredentials() {
  const username = document.getElementById('acc_username').value.trim();
  const name = document.getElementById('acc_name').value.trim();
  const current_password = document.getElementById('p_current').value;
  const new_password = document.getElementById('p_new').value;

  if (!username) {
    showToast('Username cannot be empty', true);
    return;
  }
  if (!current_password) {
    showToast('Please enter your current password', true);
    return;
  }

  const res = await apiCall('api/settings.php?action=account', 'POST', {
    username,
    name,
    current_password,
    new_password
  });
  if (res.ok) {
    showToast('<?= t('saved_successfully', $lang) ?>');
    document.getElementById('p_current').value = '';
    document.getElementById('p_new').value = '';
  } else {
    showToast(res.error || 'Failed to update credentials', true);
  }
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
