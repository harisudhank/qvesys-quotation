<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login_api();

$method = $_SERVER['REQUEST_METHOD'];

function read_json_body_items(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($method === 'GET') {
    json_response(['ok' => true, 'data' => db_read('items')]);
}

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    csrf_check();
}

if ($method === 'POST' || $method === 'PUT') {
    $body = read_json_body_items();
    $name = trim($body['name'] ?? '');
    if ($name === '') {
        json_error('Item name is required.');
    }

    $saved = null;
    db_transaction('items', function ($items) use ($body, &$saved) {
        $id = $body['id'] ?? null;
        $record = [
            'id' => $id ?: gen_id('IT-'),
            'name' => trim($body['name'] ?? ''),
            'name_ta' => trim($body['name_ta'] ?? ''),
            'hsn' => trim($body['hsn'] ?? ''),
            'unit' => trim($body['unit'] ?? 'Nos'),
            'rate' => (float)($body['rate'] ?? 0),
            'tax_percent' => (float)($body['tax_percent'] ?? 18),
            'updated_at' => date('c'),
        ];

        if ($id) {
            $found = false;
            foreach ($items as $i => $c) {
                if ($c['id'] === $id) {
                    $c['updated_at'] = date('c');
                    $archivePath = __DIR__ . '/../data/ujson/items.json';
                    $archive = file_exists($archivePath) ? json_decode(file_get_contents($archivePath), true) : [];
                    if (!is_array($archive)) $archive = [];
                    $archive[] = $c;
                    file_put_contents($archivePath, json_encode($archive, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                    $record['created_at'] = $c['created_at'] ?? date('c');
                    $items[$i] = $record;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $record['created_at'] = date('c');
                $items[] = $record;
            }
        } else {
            $record['created_at'] = date('c');
            $items[] = $record;
        }
        $saved = $record;
        return $items;
    });

    json_response(['ok' => true, 'data' => $saved]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) json_error('Missing id.');

    $item = null;
    db_transaction('items', function ($items) use ($id, &$item) {
        $item = array_values(array_filter($items, fn($c) => $c['id'] === $id));
        if ($item) {
            $item = $item[0];
            $item['deleted_at'] = date('c');
            $archivePath = __DIR__ . '/../data/djson/items.json';
            $archive = file_exists($archivePath) ? json_decode(file_get_contents($archivePath), true) : [];
            if (!is_array($archive)) $archive = [];
            $archive[] = $item;
            file_put_contents($archivePath, json_encode($archive, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        return array_values(array_filter($items, fn($c) => $c['id'] !== $id));
    });

    json_response(['ok' => true]);
}

json_error('Unsupported method.', 405);
