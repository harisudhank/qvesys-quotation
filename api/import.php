<?php
/**
 * Import clients or items from CSV rows posted as JSON.
 * Expected body: { "records": [ { "name": "...", "gstin": "...", ... }, ... ] }
 * The header columns are mapped flexibly via aliases so the exact spelling of
 * the CSV header does not have to be exact.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}
csrf_check();

$type = $_GET['type'] ?? '';
if (!in_array($type, ['clients', 'items', 'companies'], true)) {
    json_error('Invalid import type.', 400);
}

$body = json_decode(file_get_contents('php://input'), true);
$records = $body['records'] ?? [];
if (!is_array($records)) {
    json_error('Invalid payload.', 400);
}

/** Normalize a header/key: lowercase, spaces/slashes/hyphens -> underscore. */
function normKey($s): string
{
    return strtolower(trim(preg_replace('/[\s\/\-]+/', '_', (string)$s)));
}

$aliases = [
    'clients' => [
        'name'           => ['name', 'client', 'client_name', 'company', 'company_name', 'client name', 'company name'],
        'contact_person' => ['contact_person', 'contact', 'contactperson', 'contact number', 'contactnumber'],
        'gstin'          => ['gstin', 'gst'],
        'phone'          => ['phone', 'mobile', 'contact_number'],
        'email'          => ['email', 'mail'],
        'address'        => ['address', 'addr'],
        'state'          => ['state'],
        'state_code'     => ['state_code', 'statecode', 'state code'],
    ],
    'items' => [
        'name'        => ['name', 'item', 'item_name', 'description', 'item name'],
        'name_ta'     => ['name_ta', 'tamil_name', 'tamilname', 'tamil'],
        'hsn'         => ['hsn', 'hsn_sac', 'hsnsac'],
        'unit'        => ['unit'],
        'rate'        => ['rate', 'price'],
        'tax_percent' => ['tax', 'tax_percent', 'taxpercent', 'gst_percent', 'gst', 'tax %', 'gst %'],
    ],
    'companies' => [
        'name'           => ['name', 'company', 'company_name', 'company name'],
        'gstin'          => ['gstin', 'gst'],
        'phone'          => ['phone', 'mobile', 'contact_number'],
        'email'          => ['email', 'mail'],
        'state'          => ['state'],
    ],
];

// Build a fast alias -> target-field lookup using normalized keys.
$lookup = [];
foreach ($aliases[$type] as $target => $list) {
    foreach ($list as $al) {
        $lookup[normKey($al)] = $target;
    }
}

$imported = 0;
$skipped = 0;
$idPrefix = $type === 'clients' ? 'CL-' : ($type === 'companies' ? 'CO-' : 'IT-');

db_transaction($type, function ($list) use ($records, $lookup, $idPrefix, &$imported, &$skipped) {
    foreach ($records as $rec) {
        if (!is_array($rec)) { $skipped++; continue; }

        $norm = [];
        foreach ($rec as $k => $v) {
            $nk = normKey($k);
            if (!isset($norm[$nk])) {
                $norm[$nk] = is_string($v) ? trim($v) : $v;
            }
        }

        $mapped = [];
        foreach ($norm as $nk => $v) {
            if (isset($lookup[$nk])) {
                $mapped[$lookup[$nk]] = $v;
            }
        }

        $name = trim((string)($mapped['name'] ?? ''));
        if ($name === '') { $skipped++; continue; }

        $record = ['id' => gen_id($idPrefix), 'created_at' => date('c'), 'updated_at' => date('c')];

        if ($type === 'clients') {
            $record['name'] = $name;
            $record['contact_person'] = trim((string)($mapped['contact_person'] ?? ''));
            $record['gstin'] = trim((string)($mapped['gstin'] ?? ''));
            $record['phone'] = trim((string)($mapped['phone'] ?? ''));
            $record['email'] = trim((string)($mapped['email'] ?? ''));
            $record['address'] = trim((string)($mapped['address'] ?? ''));
            $record['state'] = trim((string)($mapped['state'] ?? ''));
            $record['state_code'] = trim((string)($mapped['state_code'] ?? ''));
        } elseif ($type === 'companies') {
            $record['name'] = $name;
            $record['gstin'] = trim((string)($mapped['gstin'] ?? ''));
            $record['phone'] = trim((string)($mapped['phone'] ?? ''));
            $record['email'] = trim((string)($mapped['email'] ?? ''));
            $record['state'] = trim((string)($mapped['state'] ?? ''));
        } else {
            $record['name'] = $name;
            $record['name_ta'] = trim((string)($mapped['name_ta'] ?? ''));
            $record['hsn'] = trim((string)($mapped['hsn'] ?? ''));
            $record['unit'] = trim((string)($mapped['unit'] ?? 'Nos'));
            $record['rate'] = (float)($mapped['rate'] ?? 0);
            $record['tax_percent'] = (float)($mapped['tax_percent'] ?? 18);
        }

        $list[] = $record;
        $imported++;
    }
    return $list;
});

json_response(['ok' => true, 'imported' => $imported, 'skipped' => $skipped]);
