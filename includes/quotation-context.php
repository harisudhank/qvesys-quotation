<?php
/**
 * Shared setup for rendering a single quotation (view, export, etc.).
 * Reads the id from $_GET['id'], loads the quotation and resolves all
 * company / customization variables used by templates/quote-*.php.
 *
 * Exposes (among others): $q, $settings, $template, $lang, $templateFile,
 * plus all the $custom_* / $show_col_* / $lbl_col_* / $watermark_* vars.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/functions.php';
require_login();

$templatesDir = __DIR__ . '/../templates';

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

// Remember pre-snapshot company defaults for fallback (before snapshot overrides them)
$defaultCompany = $settings['company'] ?? [];

// Snapshot preserves company details at creation time; current settings take
// priority for dynamic fields (name, address, phone, email) so edits reflect
// in existing quotations.
if (!empty($q['company_snapshot']) && is_array($q['company_snapshot'])) {
    $settings['company'] = array_merge($settings['company'] ?? [], $q['company_snapshot']);
}
foreach (['name', 'address', 'phone', 'email'] as $f) {
    if (!empty($defaultCompany[$f])) {
        $settings['company'][$f] = $defaultCompany[$f];
    }
}

// Always use current company details from companies.json instead of snapshot,
// so that edits in company editor reflect in existing quotations too.
// Match by company_id first, then by snapshot name as fallback.
$matchId = $q['company_id'] ?? '';
if (empty($matchId) && !empty($q['company_snapshot']['name'])) {
    foreach (db_read('companies') as $co) {
        if ($co['name'] === $q['company_snapshot']['name']) {
            $matchId = $co['id'];
            break;
        }
    }
}
if (!empty($matchId)) {
    foreach (db_read('companies') as $co) {
        if ($co['id'] === $matchId) {
            foreach (['name','address','phone','email','gstin','pan','tagline','bank_name','bank_account','bank_ifsc','bank_branch','logo','qr_code'] as $f) {
                if (!empty($co[$f])) {
                    $settings['company'][$f] = $co[$f];
                }
            }
            break;
        }
    }
}

// Fallback: if snapshot cleared these fields but no current company was found,
// restore the defaults from settings.json so they aren't blank.
if (empty($settings['company']['logo'])) $settings['company']['logo'] = $defaultCompany['logo'] ?? '';
if (empty($settings['company']['bank_name'])) $settings['company']['bank_name'] = $defaultCompany['bank_name'] ?? '';
if (empty($settings['company']['bank_account'])) $settings['company']['bank_account'] = $defaultCompany['bank_account'] ?? '';
if (empty($settings['company']['bank_ifsc'])) $settings['company']['bank_ifsc'] = $defaultCompany['bank_ifsc'] ?? '';
if (empty($settings['company']['bank_branch'])) $settings['company']['bank_branch'] = $defaultCompany['bank_branch'] ?? '';

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
        global $template;
        $tpl = $template ?? 'detailed';
        // Template-specific key takes priority
        if (isset($_GET[$tpl.'_'.$key])) return $_GET[$tpl.'_'.$key];
        if (isset($_GET[$key])) return $_GET[$key];
        $v = $bill['customize_'.$tpl.'_'.$key] ?? null; if ($v !== null && $v !== '' && $v !== '__removed__') return $v;
        $v = $bill['customize_'.$key] ?? null;         if ($v !== null && $v !== '' && $v !== '__removed__') return $v;
        $v = $global['customize_'.$tpl.'_'.$key] ?? null; if ($v !== null && $v !== '' && $v !== '__removed__') return $v;
        $v = $global['customize_'.$key] ?? null;         if ($v !== null && $v !== '' && $v !== '__removed__') return $v;
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
$custom_subject = get_custom_setting('subject', $bill_settings, $q_settings, 'Quotation for Supply of Goods and Services');
$custom_salutation = get_custom_setting('salutation', $bill_settings, $q_settings, 'Dear Sir / Madam,');
$custom_body_text = get_custom_setting('body_text', $bill_settings, $q_settings, "We are pleased to submit our quotation for the above mentioned requirements as follows:\n\nPlease find below our detailed quotation.");
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
$c_logo_width = get_custom_setting('logo_width', $bill_settings, $q_settings, '');
$c_logo_left = get_custom_setting('logo_left', $bill_settings, $q_settings, '');
$c_logo_top = get_custom_setting('logo_top', $bill_settings, $q_settings, '');
$c_element_positions = get_custom_setting('element_positions', $bill_settings, $q_settings, '');
$c_qr_code = get_custom_setting('qr_code', $bill_settings, $q_settings, '');

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
if ($c_qr_code !== '') $settings['company']['qr_code'] = $c_qr_code;

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

$qr_code_enabled = (int)get_custom_setting('qr_code_enabled', $bill_settings, $q_settings, 1) !== 0;

$templateFile = $templatesDir . "/quote-{$template}.php";
