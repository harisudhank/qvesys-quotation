<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login_api();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function read_json_body_c(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($method === 'GET') {
    $company = db_read('company');
    if (empty($company)) {
        $settings = db_read('settings');
        $company = $settings['company'] ?? [];
    }
    json_response(['ok' => true, 'data' => $company]);
}

if ($method === 'POST') {
    csrf_check();

    if ($action === 'logo') {
        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            json_error('No logo file received.');
        }
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg', 'image/webp' => 'webp'];
        $mime = mime_content_type($_FILES['logo']['tmp_name']);
        if (!isset($allowed[$mime])) json_error('Logo must be PNG, JPG, WEBP or SVG.');
        $ext = $allowed[$mime];
        $destDir = __DIR__ . '/../assets/img';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $filename = 'logo.' . $ext;
        move_uploaded_file($_FILES['logo']['tmp_name'], $destDir . '/' . $filename);

        db_transaction('company', function ($c) use ($filename) {
            if (!is_array($c)) $c = [];
            $c['logo'] = 'assets/img/' . $filename;
            return $c;
        });
        
        // Also update settings for backward compatibility with templates
        db_transaction('settings', function ($s) use ($filename) {
            $s['company']['logo'] = 'assets/img/' . $filename;
            return $s;
        });
        
        json_response(['ok' => true, 'logo' => 'assets/img/' . $filename]);
    }

    $body = read_json_body_c();
    if (isset($body['company']) && is_array($body['company'])) {
        db_transaction('company', function ($c) use ($body) {
            if (!is_array($c)) $c = [];
            return array_merge($c, $body['company']);
        });
        
        // Also update settings for backward compatibility with templates
        db_transaction('settings', function ($s) use ($body) {
            if (!isset($s['company']) || !is_array($s['company'])) $s['company'] = [];
            $s['company'] = array_merge($s['company'], $body['company']);
            return $s;
        });
    }
    json_response(['ok' => true]);
}

json_error('Unsupported method.', 405);
