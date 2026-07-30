<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function read_json_body_q(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ---- GET: list all or a single quotation ----
if ($method === 'GET') {
    $quotations = db_read('quotations');
    if (!empty($_GET['id'])) {
        foreach ($quotations as $q) {
            if ($q['id'] === $_GET['id']) json_response(['ok' => true, 'data' => $q]);
        }
        json_error('Quotation not found.', 404);
    }
    json_response(['ok' => true, 'data' => $quotations]);
}

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    csrf_check();
}

// ---- POST action=status : quick status change from list page ----
if ($method === 'POST' && $action === 'status') {
    $id = $_GET['id'] ?? '';
    $body = read_json_body_q();
    $status = $body['status'] ?? '';
    if (!$id || !in_array($status, ['draft', 'sent', 'accepted', 'rejected', 'expired'], true)) {
        json_error('Invalid status update.');
    }
    $updated = null;
    db_transaction('quotations', function ($rows) use ($id, $status, &$updated) {
        foreach ($rows as $i => $q) {
            if ($q['id'] === $id) {
                $rows[$i]['status'] = $status;
                $rows[$i]['updated_at'] = date('c');
                $updated = $rows[$i];
            }
        }
        return $rows;
    });
    if (!$updated) json_error('Quotation not found.', 404);
    json_response(['ok' => true, 'data' => $updated]);
}

// ---- POST action=customize : save per-bill customization ----
if ($method === 'POST' && $action === 'customize') {
    $id = $_GET['id'] ?? '';
    $body = read_json_body_q();
    $customization = $body['customization'] ?? null;
    $quotation_fields = $body['quotation'] ?? null;
    if (!$id) json_error('ID required.');
    $updated = false;
    db_transaction('quotations', function ($rows) use ($id, $customization, $quotation_fields, &$updated) {
        foreach ($rows as &$q) {
            if ($q['id'] === $id) {
                if ($customization !== null) {
                    $q['customization'] = $customization;
                }
                if ($quotation_fields !== null && is_array($quotation_fields)) {
                    foreach ($quotation_fields as $k => $v) {
                        if (in_array($k, ['number', 'date', 'valid_until', 'notes', 'terms'], true)) {
                            $q[$k] = $v;
                        }
                    }
                }
                $updated = true;
            }
        }
        return $rows;
    });
    if (!$updated) json_error('Quotation not found.', 404);
    json_response(['ok' => true]);
}

// ---- POST action=logo : upload a per-bill logo ----
if ($method === 'POST' && $action === 'logo') {
    $id = $_GET['id'] ?? '';
    if (!$id) json_error('Quotation ID required.');
    if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        json_error('No logo file received.');
    }
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg', 'image/webp' => 'webp'];
    $mime = mime_content_type($_FILES['logo']['tmp_name']);
    if (!isset($allowed[$mime])) json_error('Logo must be PNG, JPG, WEBP or SVG.');
    $ext = $allowed[$mime];
    $destDir = __DIR__ . '/../assets/img';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $filename = 'logo_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id) . '.' . $ext;
    move_uploaded_file($_FILES['logo']['tmp_name'], $destDir . '/' . $filename);
    $logoPath = 'assets/img/' . $filename;

    $updated = false;
    db_transaction('quotations', function ($rows) use ($id, $logoPath, &$updated) {
        foreach ($rows as &$q) {
            if ($q['id'] === $id) {
                if (!is_array($q['customization'])) $q['customization'] = [];
                $q['customization']['customize_logo'] = $logoPath;
                $updated = true;
            }
        }
        return $rows;
    });
    if (!$updated) json_error('Quotation not found.', 404);
    json_response(['ok' => true, 'logo' => $logoPath]);
}

// ---- POST action=duplicate : clone as a new draft with a fresh number ----
if ($method === 'POST' && $action === 'duplicate') {
    $id = $_GET['id'] ?? '';
    $source = null;
    foreach (db_read('quotations') as $q) {
        if ($q['id'] === $id) { $source = $q; break; }
    }
    if (!$source) json_error('Quotation not found.', 404);

    $numInfo = next_quotation_number();
    $clone = $source;
    $clone['id'] = gen_id('QT-');
    $clone['number'] = $numInfo['number'];
    $clone['status'] = 'draft';
    $clone['date'] = date('Y-m-d');
    $settings = db_read('settings');
    $validity = (int)($settings['quotation']['default_validity_days'] ?? 15);
    $clone['valid_until'] = date('Y-m-d', strtotime("+{$validity} days"));
    $clone['created_at'] = date('c');
    $clone['updated_at'] = date('c');

    db_transaction('quotations', function ($rows) use ($clone) {
        $rows[] = $clone;
        return $rows;
    });

    json_response(['ok' => true, 'id' => $clone['id']]);
}

// ---- POST/PUT: create or update a full quotation ----
if ($method === 'POST' || $method === 'PUT') {
    $body = read_json_body_q();

    $settings = db_read('settings');
    $companyStateCode = $settings['company']['state_code'] ?? '33';
    $isComparative = !empty($body['is_comparative']);

    $clientId = $body['client_id'] ?? '';
    $clients = db_read('clients');
    $client = null;
    foreach ($clients as $c) {
        if ($c['id'] === $clientId) { $client = $c; break; }
    }
    if (!$client) json_error('Please select a valid client.');

    $items = $body['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        json_error('Add at least one item to the quotation.');
    }

    $companyId = $body['company_id'] ?? '';
    $companySnapshot = $body['company_snapshot'] ?? null;
    if (!$companyId && !$isComparative) json_error('Please select a company.');
    if ($companyId && !$companySnapshot) {
        foreach (db_read('companies') as $co) {
            if ($co['id'] === $companyId) { $companySnapshot = $co; break; }
        }
    }
    // Use selected company's state code for GST inter-state calculation
    if (!empty($companySnapshot['state_code'])) {
        $companyStateCode = $companySnapshot['state_code'];
    }

    $interState = ($client['state_code'] ?? $companyStateCode) !== $companyStateCode;
    $comparativeConfig = $body['comparative_config'] ?? [];
    $isGstEnabled = isset($body['is_gst_enabled']) ? (bool)$body['is_gst_enabled'] : true;
    $computed = compute_quotation_totals($items, $interState, $isComparative, $comparativeConfig, $isGstEnabled);

    $id = $body['id'] ?? null;
    $lang = in_array($body['language'] ?? 'en', ['en', 'ta'], true) ? $body['language'] : 'en';
    $template = in_array($body['template'] ?? '', ['simple', 'detailed', 'gst', 'premium'], true) ? $body['template'] : 'detailed';
    $status = in_array($body['status'] ?? 'draft', ['draft', 'sent', 'accepted', 'rejected', 'expired'], true) ? $body['status'] : 'draft';

    $record = [
        'id' => $id ?: gen_id('QT-'),
        'client_id' => $client['id'],
        'client_snapshot' => $client,
        'company_id' => $companyId,
        'company_snapshot' => $companySnapshot,
        'date' => $body['date'] ?: date('Y-m-d'),
        'valid_until' => $body['valid_until'] ?: date('Y-m-d', strtotime('+' . ((int)($settings['quotation']['default_validity_days'] ?? 15)) . ' days')),
        'inter_state' => $interState,
        'notes' => trim($body['notes'] ?? ''),
        'terms' => trim($body['terms'] ?? ($settings['quotation']["default_terms_{$lang}"] ?? '')),
        'template' => $template,
        'language' => $lang,
        'status' => $status,
        'is_gst_enabled' => $isGstEnabled,
        'updated_at' => date('c'),
    ];

    if ($isComparative) {
        $record['is_comparative'] = true;
        $record['comparative_config'] = $comparativeConfig;
        $record['options'] = $computed['options'];
        $record['items'] = $items;
        
        $maxTotal = 0;
        foreach ($computed['options'] as $opt) {
            if ($opt['total'] > $maxTotal) $maxTotal = $opt['total'];
        }
        $record['total'] = $maxTotal;
        $record['subtotal'] = 0;
        $record['discount_total'] = 0;
        $record['taxable_amount'] = 0;
        $record['cgst'] = 0;
        $record['sgst'] = 0;
        $record['igst'] = 0;
        $record['round_off'] = 0;
    } else {
        $record['is_comparative'] = false;
        $record['items'] = $computed['items'];
        $record['subtotal'] = $computed['subtotal'];
        $record['discount_total'] = $computed['discount_total'];
        $record['taxable_amount'] = $computed['taxable_amount'];
        $record['cgst'] = $computed['cgst'];
        $record['sgst'] = $computed['sgst'];
        $record['igst'] = $computed['igst'];
        $record['round_off'] = $computed['round_off'];
        $record['total'] = $computed['total'];
    }

    $saved = null;
    db_transaction('quotations', function ($rows) use ($record, $id, &$saved) {
        if ($id) {
            $found = false;
            foreach ($rows as $i => $q) {
                if ($q['id'] === $id) {
                    $q['updated_at'] = date('c');
                    $archivePath = __DIR__ . '/../data/ujson/quotations.json';
                    $archive = file_exists($archivePath) ? json_decode(file_get_contents($archivePath), true) : [];
                    if (!is_array($archive)) $archive = [];
                    $archive[] = $q;
                    file_put_contents($archivePath, json_encode($archive, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                    $record['number'] = $q['number'];
                    $record['created_at'] = $q['created_at'] ?? date('c');
                    $rows[$i] = $record;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $numInfo = next_quotation_number();
                $record['number'] = $numInfo['number'];
                $record['created_at'] = date('c');
                $rows[] = $record;
            }
        } else {
            $numInfo = next_quotation_number();
            $record['number'] = $numInfo['number'];
            $record['created_at'] = date('c');
            $rows[] = $record;
        }
        $saved = $record;
        return $rows;
    });

    json_response(['ok' => true, 'id' => $saved['id'], 'number' => $saved['number']]);
}

// ---- DELETE ----
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) json_error('Missing id.');

    $quotation = null;
    db_transaction('quotations', function ($rows) use ($id, &$quotation) {
        $quotation = array_values(array_filter($rows, fn($q) => $q['id'] === $id));
        if ($quotation) {
            $quotation = $quotation[0];
            $quotation['deleted_at'] = date('c');
            $archivePath = __DIR__ . '/../data/djson/quotations.json';
            $archive = file_exists($archivePath) ? json_decode(file_get_contents($archivePath), true) : [];
            if (!is_array($archive)) $archive = [];
            $archive[] = $quotation;
            file_put_contents($archivePath, json_encode($archive, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        return array_values(array_filter($rows, fn($q) => $q['id'] !== $id));
    });

    json_response(['ok' => true]);
}

json_error('Unsupported method.', 405);
