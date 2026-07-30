<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$id = $_GET['id'] ?? '';
$q = null;
foreach (db_read('quotations') as $row) {
    if ($row['id'] === $id) { $q = $row; break; }
}
if (!$q) {
    http_response_code(404);
    echo 'Quotation not found.';
    exit;
}
$settings = db_read('settings');

// Use quotation's company snapshot if available, otherwise fall back to settings
if (!empty($q['company_snapshot']) && is_array($q['company_snapshot'])) {
    $settings['company'] = array_merge($settings['company'] ?? [], $q['company_snapshot']);
}

// Preview controls: allow viewing in a different template/language than saved,
// without altering the saved record (handy for comparing styles before sending).
$template = $_GET['template'] ?? $q['template'] ?? 'detailed';
if (!in_array($template, ['simple', 'detailed', 'gst', 'premium'], true)) $template = 'detailed';
$lang = $_GET['lang'] ?? $q['language'] ?? 'en';
if (!in_array($lang, ['en', 'ta'], true)) $lang = 'en';

// Load bill customizations with fallback defaults
$q_settings = $settings['quotation'] ?? [];
$bill_settings = $q['customization'] ?? [];

if (!function_exists('get_custom_setting')) {
    function get_custom_setting($key, $bill, $global, $default) {
        if (isset($_GET[$key])) return $_GET[$key];
        if (isset($bill['customize_'.$key]) && $bill['customize_'.$key] !== '') return $bill['customize_'.$key];
        if (isset($global['customize_'.$key]) && $global['customize_'.$key] !== '') return $global['customize_'.$key];
        return $default;
    }
}

$header_enabled = (bool)get_custom_setting('header_enabled', $bill_settings, $q_settings, true);
$footer_enabled = (bool)get_custom_setting('footer_enabled', $bill_settings, $q_settings, true);
$bank_enabled = (bool)get_custom_setting('bank_enabled', $bill_settings, $q_settings, true);
$font_family = get_custom_setting('font_family', $bill_settings, $q_settings, 'Inter');
$font_size = get_custom_setting('font_size', $bill_settings, $q_settings, '12.3px');
$theme_color = get_custom_setting('theme_color', $bill_settings, $q_settings, '#16223c');
$accent_color = get_custom_setting('accent_color', $bill_settings, $q_settings, '#B8912F');
$custom_header_title = get_custom_setting('header_title', $bill_settings, $q_settings, '');
$custom_footer_content = get_custom_setting('footer_content', $bill_settings, $q_settings, '');
$theme_preset = get_custom_setting('theme_preset', $bill_settings, $q_settings, 'navy');

$c_company_name = get_custom_setting('company_name', $bill_settings, $q_settings, '');
$c_company_tagline = get_custom_setting('company_tagline', $bill_settings, $q_settings, '');
$c_company_address = get_custom_setting('company_address', $bill_settings, $q_settings, '');
$c_company_phone = get_custom_setting('company_phone', $bill_settings, $q_settings, '');
$c_company_email = get_custom_setting('company_email', $bill_settings, $q_settings, '');
$c_company_website = get_custom_setting('company_website', $bill_settings, $q_settings, '');
$c_company_gstin = get_custom_setting('company_gstin', $bill_settings, $q_settings, '');
$c_company_pan = get_custom_setting('company_pan', $bill_settings, $q_settings, '');
$c_bank_name = get_custom_setting('bank_name', $bill_settings, $q_settings, '');
$c_bank_account = get_custom_setting('bank_account', $bill_settings, $q_settings, '');
$c_bank_ifsc = get_custom_setting('bank_ifsc', $bill_settings, $q_settings, '');
$c_bank_branch = get_custom_setting('bank_branch', $bill_settings, $q_settings, '');
$custom_signatory = get_custom_setting('signatory', $bill_settings, $q_settings, '');
$c_logo = get_custom_setting('logo', $bill_settings, $q_settings, '');

if ($c_company_name !== '') $settings['company']['name'] = $c_company_name;
if ($c_company_tagline !== '') $settings['company']['tagline'] = $c_company_tagline;
if ($c_company_address !== '') $settings['company']['address'] = $c_company_address;
if ($c_company_phone !== '') $settings['company']['phone'] = $c_company_phone;
if ($c_company_email !== '') $settings['company']['email'] = $c_company_email;
if ($c_company_website !== '') $settings['company']['website'] = $c_company_website;
if ($c_company_gstin !== '') $settings['company']['gstin'] = $c_company_gstin;
if ($c_company_pan !== '') $settings['company']['pan'] = $c_company_pan;
if ($c_bank_name !== '') $settings['company']['bank_name'] = $c_bank_name;
if ($c_bank_account !== '') $settings['company']['bank_account'] = $c_bank_account;
if ($c_bank_ifsc !== '') $settings['company']['bank_ifsc'] = $c_bank_ifsc;
if ($c_bank_branch !== '') $settings['company']['bank_branch'] = $c_bank_branch;
if ($c_logo !== '') $settings['company']['logo'] = $c_logo;

if (!$bank_enabled) {
    $settings['company']['bank_name'] = '';
}

// Column toggles & labels
$show_col_sno       = (int)get_custom_setting('show_col_sno',       $bill_settings, $q_settings, 1) !== 0;
$show_col_desc      = (int)get_custom_setting('show_col_desc',      $bill_settings, $q_settings, 1) !== 0;
$show_col_hsn       = (int)get_custom_setting('show_col_hsn',       $bill_settings, $q_settings, 1) !== 0;
$show_col_qty       = (int)get_custom_setting('show_col_qty',       $bill_settings, $q_settings, 1) !== 0;
$show_col_rate      = (int)get_custom_setting('show_col_rate',      $bill_settings, $q_settings, 1) !== 0;
$show_col_discount  = (int)get_custom_setting('show_col_discount',  $bill_settings, $q_settings, 1) !== 0;
$show_col_taxable   = (int)get_custom_setting('show_col_taxable',   $bill_settings, $q_settings, 1) !== 0;
$show_col_tax_percent = (int)get_custom_setting('show_col_tax_percent', $bill_settings, $q_settings, 1) !== 0;
$show_col_gst       = (int)get_custom_setting('show_col_gst',       $bill_settings, $q_settings, 1) !== 0;
$show_col_amount    = (int)get_custom_setting('show_col_amount',    $bill_settings, $q_settings, 1) !== 0;

$lbl_col_sno = get_custom_setting('lbl_col_sno', $bill_settings, $q_settings, '');
$lbl_col_desc = get_custom_setting('lbl_col_desc', $bill_settings, $q_settings, '');
$lbl_col_hsn = get_custom_setting('lbl_col_hsn', $bill_settings, $q_settings, '');
$lbl_col_qty = get_custom_setting('lbl_col_qty', $bill_settings, $q_settings, '');
$lbl_col_rate = get_custom_setting('lbl_col_rate', $bill_settings, $q_settings, '');
$lbl_col_discount = get_custom_setting('lbl_col_discount', $bill_settings, $q_settings, '');
$lbl_col_taxable = get_custom_setting('lbl_col_taxable', $bill_settings, $q_settings, '');
$lbl_col_tax_percent = get_custom_setting('lbl_col_tax_percent', $bill_settings, $q_settings, '');
$lbl_col_gst = get_custom_setting('lbl_col_gst', $bill_settings, $q_settings, '');
$lbl_col_amount = get_custom_setting('lbl_col_amount', $bill_settings, $q_settings, '');

$googleFonts = ['Inter', 'Roboto', 'Poppins', 'Outfit', 'Zilla Slab', 'Playfair Display'];
$fontApiUrl = '';
if (in_array($font_family, $googleFonts, true)) {
    $fontApiUrl = "https://fonts.googleapis.com/css2?family=" . str_replace(' ', '+', $font_family) . ":wght@300;400;500;600;700&display=swap";
}

$watermark_enabled = (int)get_custom_setting('watermark_enabled', $bill_settings, $q_settings, 0) !== 0;
$watermark_text    = get_custom_setting('watermark_text',    $bill_settings, $q_settings, '');
$watermark_opacity = get_custom_setting('watermark_opacity', $bill_settings, $q_settings, '0.07');
$watermark_color   = get_custom_setting('watermark_color',   $bill_settings, $q_settings, '#16223c');
$watermark_size    = get_custom_setting('watermark_size',    $bill_settings, $q_settings, '60px');

$templateFile = __DIR__ . "/templates/quote-{$template}.php";
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<title><?= h($q['number']) ?> — <?= h($settings['company']['name']) ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
<link rel="stylesheet" href="assets/css/print.css?v=<?= time() ?>">
<?php if (!empty($fontApiUrl)): ?>
<link rel="stylesheet" href="<?= $fontApiUrl ?>">
<?php endif; ?>
<style>
:root {
  --bill-primary: <?= h($theme_color) ?>;
  --bill-accent: <?= h($accent_color) ?>;
  --doc-font-size: <?= h($font_size) ?>;
}

/* Custom Font Style and Size */
.doc-page {
  font-family: '<?= h($font_family) ?>', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
}

/* Maintain Tamil text font fallback */
.doc-page.lang-ta, .doc-page.lang-ta * {
  font-family: 'Noto Sans Tamil', '<?= h($font_family) ?>', sans-serif !important;
}

/* Theme Color Overrides (Primary Color) */
.doc-company-name, 
.doc-title-block .doc-doctype, 
.doc-meta-row b,
.doc-sign .line,
.doc-table th {
  color: var(--bill-primary) !important;
}
.doc-table th {
  border-bottom-color: var(--bill-primary) !important;
}
.doc-totals .grand {
  border-top-color: var(--bill-primary) !important;
}
.tpl-simple .doc-header {
  border-bottom-color: var(--bill-primary) !important;
}
.tpl-premium .doc-band {
  background: linear-gradient(135deg, var(--bill-primary), var(--bill-primary)) !important;
}
.tpl-premium .doc-table th {
  background: var(--bill-primary) !important;
  color: #fff !important;
}
.tpl-gst .doc-header {
  border-bottom-color: var(--bill-primary) !important;
}
/* Watermark */
.doc-watermark {
  position: absolute;
  top: 40%; left: 50%;
  transform: translate(-50%, -50%) rotate(-20deg);
  font-family: 'Zilla Slab', serif;
  font-size: <?= h($watermark_size) ?>;
  font-weight: 700;
  color: <?= h($watermark_color) ?>;
  opacity: <?= h($watermark_opacity) ?>;
  pointer-events: none;
  white-space: nowrap;
  z-index: 0;
  user-select: none;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.tpl-premium .doc-band .doc-company-name,
.tpl-premium .doc-band .doc-meta-row b {
  color: #fff !important;
}
.tpl-premium .doc-band .doc-doctype,
.tpl-premium .doc-band .doc-meta-row {
  color: var(--bill-accent) !important;
}

/* Theme Color Overrides (Accent Color) */
.doc-party .lbl {
  color: var(--bill-accent) !important;
}
.tpl-detailed .doc-header {
  border-bottom-color: var(--bill-accent) !important;
}
.tpl-premium .doc-terms b,
.tpl-premium .doc-bank b {
  color: var(--bill-accent) !important;
}

/* Header Enable/Disable Customization */
<?php if (!$header_enabled): ?>
.doc-company-name,
.doc-company-meta,
.doc-seal-watermark,
.tpl-premium .doc-band > div:first-child {
  display: none !important;
}
/* Adjust layout/spacing when company header branding is hidden */
.doc-header {
  margin-bottom: 24px;
}
<?php endif; ?>

/* Footer Enable/Disable Customization */
<?php if (!$footer_enabled): ?>
.doc-footer-grid,
.doc-thankyou,
.doc-note {
  display: none !important;
}
<?php endif; ?>
/* Sidebar Customization */
body { transition: padding-right 0.3s; }
body.sidebar-open { padding-right: 400px; }
.sidebar-panel {
  position: fixed; top: 0; right: -420px; width: 400px; bottom: 0;
  background: #fff; box-shadow: -2px 0 10px rgba(0,0,0,0.1);
  z-index: 1000; transition: right 0.3s;
  display: flex; flex-direction: column;
}
.sidebar-panel.active { right: 0; }
.sidebar-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.sidebar-header h3 { margin: 0; font-size: 18px; }
.sidebar-body { padding: 20px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 15px; }
.sidebar-close { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-muted); }
.scope-toggle { background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); display: flex; gap: 15px; }

</style>
</head>
<body class="print-preview">

  <div class="doc-actions no-print">
    <div style="display:flex; gap:10px; align-items:center;">
      <a href="quotations.php" class="btn btn-outline btn-sm">&larr; <?= t('quotations', $lang) ?></a>
      <a href="quotation-editor.php?id=<?= h($q['id']) ?>" class="btn btn-outline btn-sm"><?= t('edit', $lang) ?></a>
      <span class="badge <?= status_badge_class($q['status']) ?>"><?= t($q['status'], $lang) ?></span>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
      <select onchange="switchParam('template', this.value)" style="width:auto;">
        <?php foreach (['simple' => 'Simple', 'detailed' => 'Detailed', 'gst' => 'GST Itemized', 'premium' => 'Premium'] as $k => $lbl): ?>
          <option value="<?= $k ?>" <?= $template === $k ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <div class="lang-switch">
        <a href="#" onclick="switchParam('lang','en');return false;" class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
        <a href="#" onclick="switchParam('lang','ta');return false;" class="<?= $lang === 'ta' ? 'active' : '' ?>">தமிழ்</a>
      </div>
      <button class="btn btn-outline btn-sm" onclick="openSidebar()">Customize</button>
      <button class="btn btn-brass btn-sm" onclick="window.print()"><?= t('print', $lang) ?></button>
    </div>
  </div>

  <div id="preview-container">
    <?php require $templateFile; ?>
  </div>

  <!-- Sidebar UI -->
  <div class="sidebar-panel no-print" id="sidebarPanel">
    <div class="sidebar-header">
      <h3>Quotation Customization & Edit</h3>
      <button class="sidebar-close" onclick="closeSidebar()">&times;</button>
    </div>
    <div class="sidebar-body">
      
      <div class="scope-toggle">
        <label style="display:flex; align-items:center; gap:5px; cursor:pointer;">
          <input type="radio" name="c_scope" value="local" checked onchange="autoSaveCustomization()"> This Bill Only
        </label>
        <label style="display:flex; align-items:center; gap:5px; cursor:pointer;">
          <input type="radio" name="c_scope" value="global" onchange="autoSaveCustomization()"> All Bills
        </label>
      </div>

      <!-- SECTION 1: Quotation Details -->
      <details open style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: #fafafa;">
        <summary style="font-weight: bold; cursor: pointer; user-select: none;">Quotation Fields</summary>
        <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
          <div class="field">
            <label>Quotation Number</label>
            <input type="text" id="q_number" value="<?= h($q['number']) ?>" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>Quotation Date</label>
            <input type="date" id="q_date" value="<?= h($q['date']) ?>" onchange="autoSaveCustomization()">
          </div>
          <div class="field">
            <label>Valid Until</label>
            <input type="date" id="q_valid_until" value="<?= h($q['valid_until']) ?>" onchange="autoSaveCustomization()">
          </div>
          <div class="field">
            <label>Notes</label>
            <textarea id="q_notes" rows="2" style="width:100%; border:1px solid var(--border-color); border-radius:4px; padding:8px; font-family:inherit;" oninput="autoSaveCustomizationDebounced()"><?= h($q['notes']) ?></textarea>
          </div>
          <div class="field">
            <label>Terms & Conditions</label>
            <textarea id="q_terms" rows="2" style="width:100%; border:1px solid var(--border-color); border-radius:4px; padding:8px; font-family:inherit;" oninput="autoSaveCustomizationDebounced()"><?= h($q['terms']) ?></textarea>
          </div>
        </div>
      </details>

      <!-- SECTION 2: Company Header Overrides -->
      <details style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: #fafafa;">
        <summary style="font-weight: bold; cursor: pointer; user-select: none;">Company Header Overrides</summary>
        <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
          <div class="field">
            <label>Company Name</label>
            <input type="text" id="c_company_name" value="<?= h($c_company_name !== '' ? $c_company_name : ($settings['company']['name'] ?? '')) ?>" data-default="<?= h($settings['company']['name'] ?? '') ?>" placeholder="Default: <?= h($settings['company']['name'] ?? '') ?>" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>Tagline</label>
            <input type="text" id="c_company_tagline" value="<?= h($c_company_tagline !== '' ? $c_company_tagline : ($settings['company']['tagline'] ?? '')) ?>" data-default="<?= h($settings['company']['tagline'] ?? '') ?>" placeholder="Default: <?= h($settings['company']['tagline'] ?? '') ?>" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>Address</label>
            <input type="text" id="c_company_address" value="<?= h($c_company_address !== '' ? $c_company_address : ($settings['company']['address'] ?? '')) ?>" data-default="<?= h($settings['company']['address'] ?? '') ?>" placeholder="Default: <?= h($settings['company']['address'] ?? '') ?>" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>Phone</label>
            <input type="text" id="c_company_phone" value="<?= h($c_company_phone !== '' ? $c_company_phone : ($settings['company']['phone'] ?? '')) ?>" data-default="<?= h($settings['company']['phone'] ?? '') ?>" placeholder="Default: <?= h($settings['company']['phone'] ?? '') ?>" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>Email</label>
            <input type="text" id="c_company_email" value="<?= h($c_company_email !== '' ? $c_company_email : ($settings['company']['email'] ?? '')) ?>" data-default="<?= h($settings['company']['email'] ?? '') ?>" placeholder="Default: <?= h($settings['company']['email'] ?? '') ?>" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>Website</label>
            <input type="text" id="c_company_website" value="<?= h($c_company_website !== '' ? $c_company_website : ($settings['company']['website'] ?? '')) ?>" data-default="<?= h($settings['company']['website'] ?? '') ?>" placeholder="Default: <?= h($settings['company']['website'] ?? '') ?>" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>Logo Override</label>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
              <?php if (!empty($c_logo)): ?>
                <img src="<?= h($c_logo) ?>?t=<?= time() ?>" style="height:40px; border:1px solid var(--line); border-radius:4px; padding:2px;">
              <?php elseif (!empty($settings['company']['logo'])): ?>
                <img src="<?= h($settings['company']['logo']) ?>?t=<?= time() ?>" style="height:40px; border:1px solid var(--line); border-radius:4px; padding:2px; opacity:0.5;">
              <?php endif; ?>
              <input type="file" id="c_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" onchange="uploadBillLogo()" style="font-size:12px;">
              <?php if (!empty($c_logo)): ?>
                <button type="button" class="btn btn-outline btn-sm" onclick="removeBillLogo()" style="font-size:11px; padding:2px 8px;">Remove</button>
              <?php endif; ?>
            </div>
            <small style="color:#888; font-size:10px;">Overrides the global logo for this bill only.</small>
          </div>
        </div>
      </details>

      <!-- SECTION 3: Tax & Bank Overrides -->
      <details style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: #fafafa;">
        <summary style="font-weight: bold; cursor: pointer; user-select: none;">Tax & Bank Overrides</summary>
        <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
          <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; font-weight:600;">
            <input type="checkbox" id="c_bank_enabled" <?= $bank_enabled ? 'checked' : '' ?> style="width: auto; margin: 0; cursor: pointer;" onchange="autoSaveCustomization()">
            <?= t('show_bank_details', $lang) ?? 'Show Bank Details' ?>
          </label>
          <div class="field">
            <label>GSTIN</label>
            <input type="text" id="c_company_gstin" value="<?= h($c_company_gstin !== '' ? $c_company_gstin : ($settings['company']['gstin'] ?? '')) ?>" data-default="<?= h($settings['company']['gstin'] ?? '') ?>" placeholder="Default: <?= h($settings['company']['gstin'] ?? '') ?>" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>PAN</label>
            <input type="text" id="c_company_pan" value="<?= h($c_company_pan !== '' ? $c_company_pan : ($settings['company']['pan'] ?? '')) ?>" data-default="<?= h($settings['company']['pan'] ?? '') ?>" placeholder="Default: <?= h($settings['company']['pan'] ?? '') ?>" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>Bank Name</label>
            <input type="text" id="c_bank_name" value="<?= h($c_bank_name !== '' ? $c_bank_name : ($settings['company']['bank_name'] ?? '')) ?>" data-default="<?= h($settings['company']['bank_name'] ?? '') ?>" placeholder="Default: <?= h($settings['company']['bank_name'] ?? '') ?>" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>Account Number</label>
            <input type="text" id="c_bank_account" value="<?= h($c_bank_account !== '' ? $c_bank_account : ($settings['company']['bank_account'] ?? '')) ?>" data-default="<?= h($settings['company']['bank_account'] ?? '') ?>" placeholder="Default: <?= h($settings['company']['bank_account'] ?? '') ?>" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>IFSC Code</label>
            <input type="text" id="c_bank_ifsc" value="<?= h($c_bank_ifsc !== '' ? $c_bank_ifsc : ($settings['company']['bank_ifsc'] ?? '')) ?>" data-default="<?= h($settings['company']['bank_ifsc'] ?? '') ?>" placeholder="Default: <?= h($settings['company']['bank_ifsc'] ?? '') ?>" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>Branch</label>
            <input type="text" id="c_bank_branch" value="<?= h($c_bank_branch !== '' ? $c_bank_branch : ($settings['company']['bank_branch'] ?? '')) ?>" data-default="<?= h($settings['company']['bank_branch'] ?? '') ?>" placeholder="Default: <?= h($settings['company']['bank_branch'] ?? '') ?>" oninput="autoSaveCustomizationDebounced()">
          </div>
        </div>
      </details>

      <!-- SECTION 4: Styling & Toggles -->
      <details style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: #fafafa;">
        <summary style="font-weight: bold; cursor: pointer; user-select: none;">Styling & Toggles</summary>
        <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
          <div style="display:flex; gap:15px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;">
              <input type="checkbox" id="c_header_enabled" <?= $header_enabled ? 'checked' : '' ?> style="width: auto; margin: 0; cursor: pointer;" onchange="autoSaveCustomization()">
              <?= t('enable_header', $lang) ?? 'Enable Header' ?>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;">
              <input type="checkbox" id="c_footer_enabled" <?= $footer_enabled ? 'checked' : '' ?> style="width: auto; margin: 0; cursor: pointer;" onchange="autoSaveCustomization()">
              <?= t('enable_footer', $lang) ?? 'Enable Footer' ?>
            </label>
          </div>
          <div class="field">
            <label>Header Title</label>
            <input type="text" id="c_header_title" value="<?= h($custom_header_title) ?>" placeholder="e.g. TAX INVOICE" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>Footer Content</label>
            <textarea id="c_footer_content" placeholder="e.g. Thank you for your business!" rows="2" style="width:100%; border:1px solid var(--border-color); border-radius:4px; padding:8px; font-family:inherit;" oninput="autoSaveCustomizationDebounced()"><?= h($custom_footer_content) ?></textarea>
          </div>
          <div class="field">
            <label>Authorized Signatory Text</label>
            <input type="text" id="c_signatory" value="<?= h($custom_signatory) ?>" placeholder="Default: AUTHORIZED SIGNATORY" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div style="display:flex; gap:10px;">
            <div class="field" style="flex:1;">
              <label><?= t('font_style', $lang) ?? 'Font Style' ?></label>
              <select id="c_font_family" onchange="autoSaveCustomization()">
                <?php foreach (['Inter', 'Roboto', 'Poppins', 'Outfit', 'Zilla Slab', 'Georgia', 'Playfair Display', 'Courier New'] as $val): ?>
                  <option value="<?= h($val) ?>" <?= $font_family === $val ? 'selected' : '' ?>><?= h($val) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field" style="flex:1;">
              <label><?= t('font_size', $lang) ?? 'Font Size' ?></label>
              <select id="c_font_size" onchange="autoSaveCustomization()">
                <option value="11px" <?= $font_size === '11px' ? 'selected' : '' ?>>Small</option>
                <option value="12.3px" <?= $font_size === '12.3px' ? 'selected' : '' ?>>Medium</option>
                <option value="14px" <?= $font_size === '14px' ? 'selected' : '' ?>>Large</option>
                <option value="16px" <?= $font_size === '16px' ? 'selected' : '' ?>>Extra Large</option>
              </select>
            </div>
          </div>
          <div class="field">
            <label><?= t('theme_preset', $lang) ?? 'Theme Preset' ?></label>
            <select id="c_theme_preset" onchange="applyThemePreset(this.value); autoSaveCustomization();">
              <option value="navy" <?= $theme_preset === 'navy' ? 'selected' : '' ?>>Classic Navy</option>
              <option value="dark" <?= $theme_preset === 'dark' ? 'selected' : '' ?>>Dark Mode</option>
              <option value="green" <?= $theme_preset === 'green' ? 'selected' : '' ?>>Forest Green</option>
              <option value="purple" <?= $theme_preset === 'purple' ? 'selected' : '' ?>>Royal Purple</option>
              <option value="red" <?= $theme_preset === 'red' ? 'selected' : '' ?>>Crimson Red</option>
              <option value="custom" <?= $theme_preset === 'custom' ? 'selected' : '' ?>>Custom Color</option>
            </select>
          </div>
          <div style="display:flex; gap:10px;">
            <div class="field">
              <label><?= t('theme_color', $lang) ?? 'Theme Color' ?></label>
              <div style="display:flex; gap:8px; align-items:center;">
                <input type="color" id="c_theme_color" value="<?= h($theme_color) ?>" style="width:40px; height:30px; padding:0; cursor:pointer;" oninput="onColorChange()">
              </div>
            </div>
            <div class="field">
              <label><?= t('accent_color', $lang) ?? 'Accent Color' ?></label>
              <div style="display:flex; gap:8px; align-items:center;">
                <input type="color" id="c_accent_color" value="<?= h($accent_color) ?>" style="width:40px; height:30px; padding:0; cursor:pointer;" oninput="onColorChange()">
              </div>
            </div>
          </div>
        </div>
      </details>

      <!-- SECTION 5: Table Columns -->
      <details style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: #fafafa;">
        <summary style="font-weight: bold; cursor: pointer; user-select: none;">Table Columns</summary>
        <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 12px;">
          
          <!-- S.No -->
          <div style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <span style="font-weight:600; font-size:12px;">S.No Column</span>
              <label style="display:flex; align-items:center; gap:4px; font-size:11px; cursor:pointer;">
                <input type="checkbox" id="c_show_col_sno" <?= $show_col_sno ? 'checked' : '' ?> onchange="autoSaveCustomization()"> Show
              </label>
            </div>
            <input type="text" id="c_lbl_col_sno" value="<?= h($lbl_col_sno) ?>" placeholder="Default: S.No" oninput="autoSaveCustomizationDebounced()" style="font-size:12px; padding:4px 8px;">
          </div>

          <!-- Description -->
          <div style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <span style="font-weight:600; font-size:12px;">Item / Description</span>
              <label style="display:flex; align-items:center; gap:4px; font-size:11px; cursor:pointer;">
                <input type="checkbox" id="c_show_col_desc" <?= $show_col_desc ? 'checked' : '' ?> onchange="autoSaveCustomization()"> Show
              </label>
            </div>
            <input type="text" id="c_lbl_col_desc" value="<?= h($lbl_col_desc) ?>" placeholder="Default: Item / Description" oninput="autoSaveCustomizationDebounced()" style="font-size:12px; padding:4px 8px;">
          </div>

          <!-- HSN/SAC -->
          <div style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <span style="font-weight:600; font-size:12px;">HSN/SAC</span>
              <label style="display:flex; align-items:center; gap:4px; font-size:11px; cursor:pointer;">
                <input type="checkbox" id="c_show_col_hsn" <?= $show_col_hsn ? 'checked' : '' ?> onchange="autoSaveCustomization()"> Show
              </label>
            </div>
            <input type="text" id="c_lbl_col_hsn" value="<?= h($lbl_col_hsn) ?>" placeholder="Default: HSN/SAC" oninput="autoSaveCustomizationDebounced()" style="font-size:12px; padding:4px 8px;">
          </div>

          <!-- Qty -->
          <div style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <span style="font-weight:600; font-size:12px;">Quantity</span>
              <label style="display:flex; align-items:center; gap:4px; font-size:11px; cursor:pointer;">
                <input type="checkbox" id="c_show_col_qty" <?= $show_col_qty ? 'checked' : '' ?> onchange="autoSaveCustomization()"> Show
              </label>
            </div>
            <input type="text" id="c_lbl_col_qty" value="<?= h($lbl_col_qty) ?>" placeholder="Default: Qty" oninput="autoSaveCustomizationDebounced()" style="font-size:12px; padding:4px 8px;">
          </div>

          <!-- Rate -->
          <div style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <span style="font-weight:600; font-size:12px;">Rate</span>
              <label style="display:flex; align-items:center; gap:4px; font-size:11px; cursor:pointer;">
                <input type="checkbox" id="c_show_col_rate" <?= $show_col_rate ? 'checked' : '' ?> onchange="autoSaveCustomization()"> Show
              </label>
            </div>
            <input type="text" id="c_lbl_col_rate" value="<?= h($lbl_col_rate) ?>" placeholder="Default: Rate" oninput="autoSaveCustomizationDebounced()" style="font-size:12px; padding:4px 8px;">
          </div>

          <!-- Discount -->
          <div style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <span style="font-weight:600; font-size:12px;">Discount</span>
              <label style="display:flex; align-items:center; gap:4px; font-size:11px; cursor:pointer;">
                <input type="checkbox" id="c_show_col_discount" <?= $show_col_discount ? 'checked' : '' ?> onchange="autoSaveCustomization()"> Show
              </label>
            </div>
            <input type="text" id="c_lbl_col_discount" value="<?= h($lbl_col_discount) ?>" placeholder="Default: Discount" oninput="autoSaveCustomizationDebounced()" style="font-size:12px; padding:4px 8px;">
          </div>

          <!-- Taxable Amount -->
          <div style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <span style="font-weight:600; font-size:12px;">Taxable Amount</span>
              <label style="display:flex; align-items:center; gap:4px; font-size:11px; cursor:pointer;">
                <input type="checkbox" id="c_show_col_taxable" <?= $show_col_taxable ? 'checked' : '' ?> onchange="autoSaveCustomization()"> Show
              </label>
            </div>
            <input type="text" id="c_lbl_col_taxable" value="<?= h($lbl_col_taxable) ?>" placeholder="Default: Taxable Amount" oninput="autoSaveCustomizationDebounced()" style="font-size:12px; padding:4px 8px;">
          </div>

          <!-- Tax % -->
          <div style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <span style="font-weight:600; font-size:12px;">Tax Rate (Tax %)</span>
              <label style="display:flex; align-items:center; gap:4px; font-size:11px; cursor:pointer;">
                <input type="checkbox" id="c_show_col_tax_percent" <?= $show_col_tax_percent ? 'checked' : '' ?> onchange="autoSaveCustomization()"> Show
              </label>
            </div>
            <input type="text" id="c_lbl_col_tax_percent" value="<?= h($lbl_col_tax_percent) ?>" placeholder="Default: Tax %" oninput="autoSaveCustomizationDebounced()" style="font-size:12px; padding:4px 8px;">
          </div>

          <!-- GST (CGST/SGST/IGST) -->
          <div style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <span style="font-weight:600; font-size:12px;">GST (CGST/SGST/IGST)</span>
              <label style="display:flex; align-items:center; gap:4px; font-size:11px; cursor:pointer;">
                <input type="checkbox" id="c_show_col_gst" <?= $show_col_gst ? 'checked' : '' ?> onchange="autoSaveCustomization()"> Show
              </label>
            </div>
            <input type="text" id="c_lbl_col_gst" value="<?= h($lbl_col_gst) ?>" placeholder="Default: GST" oninput="autoSaveCustomizationDebounced()" style="font-size:12px; padding:4px 8px;">
          </div>

          <!-- Amount -->
          <div style="border-bottom: 1px solid #eee; padding-bottom: 8px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
              <span style="font-weight:600; font-size:12px;">Amount</span>
              <label style="display:flex; align-items:center; gap:4px; font-size:11px; cursor:pointer;">
                <input type="checkbox" id="c_show_col_amount" <?= $show_col_amount ? 'checked' : '' ?> onchange="autoSaveCustomization()"> Show
              </label>
            </div>
            <input type="text" id="c_lbl_col_amount" value="<?= h($lbl_col_amount) ?>" placeholder="Default: Amount" oninput="autoSaveCustomizationDebounced()" style="font-size:12px; padding:4px 8px;">
          </div>

        </div>
      </details>

      <!-- SECTION 6: Watermark -->
      <details style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: #fafafa;">
        <summary style="font-weight: bold; cursor: pointer; user-select: none;">🖋 Watermark</summary>
        <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 12px;">
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600;">
            <input type="checkbox" id="c_watermark_enabled" <?= $watermark_enabled ? 'checked' : '' ?> onchange="autoSaveCustomization()">
            Enable Watermark
          </label>
          <div class="field">
            <label>Watermark Text</label>
            <input type="text" id="c_watermark_text" value="<?= h($watermark_text) ?>" placeholder="e.g. DRAFT, CONFIDENTIAL, PAID" oninput="autoSaveCustomizationDebounced()">
          </div>
          <div class="field">
            <label>Color</label>
            <div style="display:flex; gap:8px; align-items:center;">
              <input type="color" id="c_watermark_color" value="<?= h($watermark_color) ?>" style="width:40px; height:30px; padding:0; cursor:pointer;" oninput="autoSaveCustomizationDebounced()">
              <span style="font-size:11px; color:#666;">Pick a watermark color</span>
            </div>
          </div>
          <div class="field">
            <label>Opacity — <span id="c_watermark_opacity_val"><?= h($watermark_opacity) ?></span></label>
            <input type="range" id="c_watermark_opacity" min="0.01" max="0.30" step="0.01" value="<?= h($watermark_opacity) ?>" style="width:100%;" oninput="document.getElementById('c_watermark_opacity_val').textContent=this.value; autoSaveCustomizationDebounced()">
            <div style="display:flex; justify-content:space-between; font-size:10px; color:#aaa;"><span>Light</span><span>Visible</span></div>
          </div>
          <div class="field">
            <label>Font Size</label>
            <select id="c_watermark_size" onchange="autoSaveCustomization()">
              <option value="40px" <?= $watermark_size === '40px' ? 'selected' : '' ?>>Small (40px)</option>
              <option value="60px" <?= $watermark_size === '60px' ? 'selected' : '' ?>>Medium (60px)</option>
              <option value="80px" <?= $watermark_size === '80px' ? 'selected' : '' ?>>Large (80px)</option>
              <option value="100px" <?= $watermark_size === '100px' ? 'selected' : '' ?>>Extra Large (100px)</option>
            </select>
          </div>
        </div>
      </details>

    </div>
  </div>

<script>
function switchParam(key, val) {
  const url = new URL(window.location.href);
  url.searchParams.set(key, val);
  window.location.href = url.toString();
}

function openSidebar() {
  document.getElementById('sidebarPanel').classList.add('active');
  document.body.classList.add('sidebar-open');
}

function closeSidebar() {
  document.getElementById('sidebarPanel').classList.remove('active');
  document.body.classList.remove('sidebar-open');
}

const presets = {
  navy: { theme: '#16223c', accent: '#B8912F' },
  dark: { theme: '#222222', accent: '#666666' },
  green: { theme: '#1b4332', accent: '#40916c' },
  purple: { theme: '#312244', accent: '#a663cc' },
  red: { theme: '#780000', accent: '#c1121f' }
};

function applyThemePreset(preset) {
  if (presets[preset]) {
    document.getElementById('c_theme_color').value = presets[preset].theme;
    document.getElementById('c_accent_color').value = presets[preset].accent;
  }
}

function onColorChange() {
  document.getElementById('c_theme_preset').value = 'custom';
  autoSaveCustomizationDebounced();
}

let autoSaveTimer = null;
function autoSaveCustomizationDebounced() {
  clearTimeout(autoSaveTimer);
  autoSaveTimer = setTimeout(autoSaveCustomization, 500);
}

function getVal(id) {
  const el = document.getElementById(id);
  if (!el) return '';
  const val = el.value;
  const def = el.getAttribute('data-default');
  if (def !== null && val === def) return '';
  return val;
}

async function autoSaveCustomization() {
  const scope = document.querySelector('input[name="c_scope"]:checked').value;
  const customization = {
    customize_header_enabled: document.getElementById('c_header_enabled').checked ? 1 : 0,
    customize_footer_enabled: document.getElementById('c_footer_enabled').checked ? 1 : 0,
    customize_bank_enabled: document.getElementById('c_bank_enabled').checked ? 1 : 0,
    customize_header_title: document.getElementById('c_header_title').value,
    customize_footer_content: document.getElementById('c_footer_content').value,
    customize_company_name: getVal('c_company_name'),
    customize_company_tagline: getVal('c_company_tagline'),
    customize_company_address: getVal('c_company_address'),
    customize_company_phone: getVal('c_company_phone'),
    customize_company_email: getVal('c_company_email'),
    customize_company_website: getVal('c_company_website'),
    customize_company_gstin: getVal('c_company_gstin'),
    customize_company_pan: getVal('c_company_pan'),
    customize_bank_name: getVal('c_bank_name'),
    customize_bank_account: getVal('c_bank_account'),
    customize_bank_ifsc: getVal('c_bank_ifsc'),
    customize_bank_branch: getVal('c_bank_branch'),
    customize_signatory: document.getElementById('c_signatory').value,
    customize_font_family: document.getElementById('c_font_family').value,
    customize_font_size: document.getElementById('c_font_size').value,
    customize_theme_preset: document.getElementById('c_theme_preset').value,
    customize_theme_color: document.getElementById('c_theme_color').value,
    customize_accent_color: document.getElementById('c_accent_color').value,
    
    // Column preferences
    customize_show_col_sno: document.getElementById('c_show_col_sno').checked ? 1 : 0,
    customize_show_col_desc: document.getElementById('c_show_col_desc').checked ? 1 : 0,
    customize_show_col_hsn: document.getElementById('c_show_col_hsn').checked ? 1 : 0,
    customize_show_col_qty: document.getElementById('c_show_col_qty').checked ? 1 : 0,
    customize_show_col_rate: document.getElementById('c_show_col_rate').checked ? 1 : 0,
    customize_show_col_discount: document.getElementById('c_show_col_discount').checked ? 1 : 0,
    customize_show_col_taxable: document.getElementById('c_show_col_taxable').checked ? 1 : 0,
    customize_show_col_tax_percent: document.getElementById('c_show_col_tax_percent').checked ? 1 : 0,
    customize_show_col_gst: document.getElementById('c_show_col_gst').checked ? 1 : 0,
    customize_show_col_amount: document.getElementById('c_show_col_amount').checked ? 1 : 0,
    
    customize_lbl_col_sno: document.getElementById('c_lbl_col_sno').value,
    customize_lbl_col_desc: document.getElementById('c_lbl_col_desc').value,
    customize_lbl_col_hsn: document.getElementById('c_lbl_col_hsn').value,
    customize_lbl_col_qty: document.getElementById('c_lbl_col_qty').value,
    customize_lbl_col_rate: document.getElementById('c_lbl_col_rate').value,
    customize_lbl_col_discount: document.getElementById('c_lbl_col_discount').value,
    customize_lbl_col_taxable: document.getElementById('c_lbl_col_taxable').value,
    customize_lbl_col_tax_percent: document.getElementById('c_lbl_col_tax_percent').value,
    customize_lbl_col_gst: document.getElementById('c_lbl_col_gst').value,
    customize_lbl_col_amount: document.getElementById('c_lbl_col_amount').value,
    
    // Watermark
    customize_watermark_enabled: document.getElementById('c_watermark_enabled').checked ? 1 : 0,
    customize_watermark_text:    document.getElementById('c_watermark_text').value,
    customize_watermark_color:   document.getElementById('c_watermark_color').value,
    customize_watermark_opacity: document.getElementById('c_watermark_opacity').value,
    customize_watermark_size:    document.getElementById('c_watermark_size').value,
  };

  const quotationFields = {
    number: document.getElementById('q_number').value,
    date: document.getElementById('q_date').value,
    valid_until: document.getElementById('q_valid_until').value,
    notes: document.getElementById('q_notes').value,
    terms: document.getElementById('q_terms').value
  };

  const csrf = document.querySelector('meta[name="csrf-token"]').content;

  if (scope === 'global') {
    // Save style customizations globally
    const resSettings = await fetch('api/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ quotation: customization })
    });
    if (!resSettings.ok) {
      alert('Global settings save failed: ' + await resSettings.text());
      return;
    }

    // Save quotation fields locally
    const resQuo = await fetch('api/quotations.php?action=customize&id=<?= h($q['id']) ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ quotation: quotationFields })
    });
    if (!resQuo.ok) {
      alert('Local quotation save failed: ' + await resQuo.text());
      return;
    }
  } else {
    // Save both style customizations and quotation fields locally
    const res = await fetch('api/quotations.php?action=customize&id=<?= h($q['id']) ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ customization, quotation: quotationFields })
    });
    if (!res.ok) {
      alert('Save failed: ' + await res.text());
      return;
    }
  }

  refreshPreview();
}

async function uploadBillLogo() {
  const fileInput = document.getElementById('c_logo');
  if (!fileInput.files.length) return;
  const fd = new FormData();
  fd.append('logo', fileInput.files[0]);
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const res = await fetch('api/quotations.php?action=logo&id=<?= h($q['id']) ?>', {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrf },
    body: fd
  });
  const data = await res.json();
  if (data.ok) { refreshPreview(); } else { alert(data.error || 'Upload failed'); }
}

async function removeBillLogo() {
  const scope = document.querySelector('input[name="c_scope"]:checked').value;
  const customization = { customize_logo: '' };
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  if (scope === 'global') {
    await fetch('api/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ quotation: customization })
    });
  } else {
    await fetch('api/quotations.php?action=customize&id=<?= h($q['id']) ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ customization })
    });
  }
  refreshPreview();
}

async function refreshPreview() {
  try {
    const res = await fetch(window.location.href);
    const html = await res.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    
    // Extract the new doc-page content
    const newDocPage = doc.querySelector('.doc-page');
    const oldDocPage = document.querySelector('.doc-page');
    
    if (newDocPage && oldDocPage) {
      oldDocPage.replaceWith(newDocPage);
    }
    
    // Also update dynamic style tags (like fonts and CSS variables)
    const newStyles = doc.querySelectorAll('style');
    const oldStyles = document.querySelectorAll('style');
    if (newStyles.length > 0 && oldStyles.length > 0) {
      oldStyles[0].replaceWith(newStyles[0]);
    }
  } catch (e) {
    console.error('Live preview refresh failed', e);
  }
}

// Preserve sidebar state if page was refreshed
if (window.location.search.includes('sidebar=1')) {
  openSidebar();
}
</script>
</body>
</html>
