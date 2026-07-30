<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login_api();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function read_json_body_co(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($method === 'GET') {
    json_response(['ok' => true, 'data' => db_read('companies')]);
}

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    csrf_check();
}

if ($method === 'POST' || $method === 'PUT') {
    if ($action === 'logo') {
        $id = $_POST['id'] ?? '';
        if (!$id) json_error('Missing company id for logo upload.');

        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            json_error('No logo file received.');
        }
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg', 'image/webp' => 'webp'];
        $mime = mime_content_type($_FILES['logo']['tmp_name']);
        if (!isset($allowed[$mime])) json_error('Logo must be PNG, JPG, WEBP or SVG.');
        $ext = $allowed[$mime];
        $destDir = __DIR__ . '/../assets/img';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $filename = 'company_' . $id . '.' . $ext;
        move_uploaded_file($_FILES['logo']['tmp_name'], $destDir . '/' . $filename);

        $saved = null;
        db_transaction('companies', function ($companies) use ($id, $filename, &$saved) {
            foreach ($companies as $i => $c) {
                if ($c['id'] === $id) {
                    $companies[$i]['logo'] = 'assets/img/' . $filename;
                    $companies[$i]['updated_at'] = date('c');
                    $saved = $companies[$i];
                    break;
                }
            }
            return $companies;
        });

        json_response(['ok' => true, 'data' => $saved]);
    }

    if ($action === 'qr') {
        $id = $_POST['id'] ?? '';
        if (!$id) json_error('Missing company id for QR upload.');

        if (empty($_FILES['qr']) || $_FILES['qr']['error'] !== UPLOAD_ERR_OK) {
            json_error('No QR file received.');
        }
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $mime = mime_content_type($_FILES['qr']['tmp_name']);
        if (!isset($allowed[$mime])) json_error('QR must be PNG, JPG or WEBP.');
        $ext = $allowed[$mime];
        $destDir = __DIR__ . '/../assets/img';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $filename = 'qr_' . $id . '.' . $ext;
        move_uploaded_file($_FILES['qr']['tmp_name'], $destDir . '/' . $filename);

        $saved = null;
        db_transaction('companies', function ($companies) use ($id, $filename, &$saved) {
            foreach ($companies as $i => $c) {
                if ($c['id'] === $id) {
                    $companies[$i]['qr_code'] = 'assets/img/' . $filename;
                    $companies[$i]['updated_at'] = date('c');
                    $saved = $companies[$i];
                    break;
                }
            }
            return $companies;
        });

        json_response(['ok' => true, 'data' => $saved]);
    }

    $body = read_json_body_co();
    $name = trim($body['name'] ?? '');
    if ($name === '') {
        json_error('Company name is required.');
    }

    $result = db_transaction('companies', function ($companies) use ($body, &$saved) {
        $id = $body['id'] ?? null;
        $record = [
            'id' => $id ?: gen_id('CO-'),
            'name' => trim($body['name'] ?? ''),
            'tagline' => trim($body['tagline'] ?? ''),
            'address' => trim($body['address'] ?? ''),
            'phone' => trim($body['phone'] ?? ''),
            'email' => trim($body['email'] ?? ''),
            'website' => trim($body['website'] ?? ''),
            'gstin' => trim($body['gstin'] ?? ''),
            'pan' => trim($body['pan'] ?? ''),
            'state' => trim($body['state'] ?? ''),
            'state_code' => trim($body['state_code'] ?? ''),
            'bank_name' => trim($body['bank_name'] ?? ''),
            'bank_account' => trim($body['bank_account'] ?? ''),
            'bank_ifsc' => trim($body['bank_ifsc'] ?? ''),
            'bank_branch' => trim($body['bank_branch'] ?? ''),
            'updated_at' => date('c'),
        ];

        if ($id) {
            $found = false;
            foreach ($companies as $i => $c) {
                if ($c['id'] === $id) {
                    $c['updated_at'] = date('c');
                    $archivePath = __DIR__ . '/../data/ujson/companies.json';
                    if (!is_dir(dirname($archivePath))) mkdir(dirname($archivePath), 0755, true);
                    $archive = file_exists($archivePath) ? json_decode(file_get_contents($archivePath), true) : [];
                    if (!is_array($archive)) $archive = [];
                    $archive[] = $c;
                    file_put_contents($archivePath, json_encode($archive, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                    $record['created_at'] = $c['created_at'] ?? date('c');
                    $record['logo'] = $c['logo'] ?? '';
                    $companies[$i] = $record;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $record['created_at'] = date('c');
                $record['logo'] = '';
                $companies[] = $record;
            }
        } else {
            $record['created_at'] = date('c');
            $record['logo'] = '';
            $companies[] = $record;
        }
        $saved = $record;
        return $companies;
    });

    json_response(['ok' => true, 'data' => $saved]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) json_error('Missing id.');

    $company = null;
    db_transaction('companies', function ($companies) use ($id, &$company) {
        $company = array_values(array_filter($companies, fn($c) => $c['id'] === $id));
        if ($company) {
            $company = $company[0];
            $company['deleted_at'] = date('c');
            $archivePath = __DIR__ . '/../data/djson/companies.json';
            if (!is_dir(dirname($archivePath))) mkdir(dirname($archivePath), 0755, true);
            $archive = file_exists($archivePath) ? json_decode(file_get_contents($archivePath), true) : [];
            if (!is_array($archive)) $archive = [];
            $archive[] = $company;
            file_put_contents($archivePath, json_encode($archive, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        return array_values(array_filter($companies, fn($c) => $c['id'] !== $id));
    });

    json_response(['ok' => true]);
}

json_error('Unsupported method.', 405);
