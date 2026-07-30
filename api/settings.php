<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login_api();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function read_json_body_s(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($method === 'GET') {
    $settings = db_read('settings');
    $user = db_read('user');
    unset($user['password_hash']);
    $settings['auth'] = $user;
    json_response(['ok' => true, 'data' => $settings]);
}

if ($method === 'POST') {
    csrf_check();

    if ($action === 'password' || $action === 'account') {
        $body = read_json_body_s();
        $current = $body['current_password'] ?? '';
        $newUsername = trim($body['username'] ?? '');
        $newName = trim($body['name'] ?? '');
        $newPassword = $body['new_password'] ?? '';

        $user = db_read('user');
        if (empty($user)) {
            $settings = db_read('settings');
            $user = $settings['auth'] ?? [];
        }

        if (!password_verify($current, $user['password_hash'] ?? '')) {
            json_error('Current password is incorrect.');
        }

        if ($newPassword !== '' && strlen($newPassword) < 6) {
            json_error('New password must be at least 6 characters.');
        }

        db_transaction('user', function ($u) use ($newUsername, $newName, $newPassword) {
            if (!is_array($u)) $u = [];
            if ($newUsername !== '') {
                $u['username'] = $newUsername;
            }
            if ($newName !== '') {
                $u['name'] = $newName;
            }
            if ($newPassword !== '') {
                $u['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            return $u;
        });

        $updated = db_read('user');
        $_SESSION['user']['username'] = $updated['username'] ?? 'admin';
        $_SESSION['user']['name'] = $updated['name'] ?? $_SESSION['user']['username'];

        json_response(['ok' => true]);
    }

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

        db_transaction('settings', function ($s) use ($filename) {
            $s['company']['logo'] = 'assets/img/' . $filename;
            return $s;
        });
        json_response(['ok' => true, 'logo' => 'assets/img/' . $filename]);
    }

    if ($action === 'bill_logo') {
        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            json_error('No logo file received.');
        }
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/svg+xml' => 'svg', 'image/webp' => 'webp'];
        $mime = mime_content_type($_FILES['logo']['tmp_name']);
        if (!isset($allowed[$mime])) json_error('Logo must be PNG, JPG, WEBP or SVG.');
        $ext = $allowed[$mime];
        $destDir = __DIR__ . '/../assets/img';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $filename = 'bill_logo.' . $ext;
        move_uploaded_file($_FILES['logo']['tmp_name'], $destDir . '/' . $filename);
        $logoPath = 'assets/img/' . $filename;

        db_transaction('settings', function ($s) use ($logoPath) {
            $s['quotation']['customize_logo'] = $logoPath;
            return $s;
        });
        json_response(['ok' => true, 'logo' => $logoPath]);
    }

    // Default: update company + quotation settings
    $body = read_json_body_s();
    db_transaction('settings', function ($s) use ($body) {
        if (isset($body['company']) && is_array($body['company'])) {
            $s['company'] = array_merge($s['company'], $body['company']);
        }
        if (isset($body['quotation']) && is_array($body['quotation'])) {
            $s['quotation'] = array_merge($s['quotation'], $body['quotation']);
        }
        return $s;
    });
    json_response(['ok' => true]);
}

json_error('Unsupported method.', 405);
