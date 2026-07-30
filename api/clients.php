<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login_api();

$method = $_SERVER['REQUEST_METHOD'];

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

if ($method === 'GET') {
    json_response(['ok' => true, 'data' => db_read('clients')]);
}

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    csrf_check();
}

if ($method === 'POST' || $method === 'PUT') {
    $body = read_json_body();
    $name = trim($body['name'] ?? '');
    if ($name === '') {
        json_error('Client name is required.');
    }

    $result = db_transaction('clients', function ($clients) use ($body, $method, &$saved) {
        $id = $body['id'] ?? null;
        $record = [
            'id' => $id ?: gen_id('CL-'),
            'name' => trim($body['name'] ?? ''),
            'contact_person' => trim($body['contact_person'] ?? ''),
            'gstin' => trim($body['gstin'] ?? ''),
            'phone' => trim($body['phone'] ?? ''),
            'email' => trim($body['email'] ?? ''),
            'address' => trim($body['address'] ?? ''),
            'state' => trim($body['state'] ?? ''),
            'state_code' => trim($body['state_code'] ?? ''),
            'updated_at' => date('c'),
        ];

        if ($id) {
            $found = false;
            foreach ($clients as $i => $c) {
                if ($c['id'] === $id) {
                    $c['updated_at'] = date('c');
                    $archivePath = __DIR__ . '/../data/ujson/clients.json';
                    $archive = file_exists($archivePath) ? json_decode(file_get_contents($archivePath), true) : [];
                    if (!is_array($archive)) $archive = [];
                    $archive[] = $c;
                    file_put_contents($archivePath, json_encode($archive, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                    $record['created_at'] = $c['created_at'] ?? date('c');
                    $clients[$i] = $record;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $record['created_at'] = date('c');
                $clients[] = $record;
            }
        } else {
            $record['created_at'] = date('c');
            $clients[] = $record;
        }
        $saved = $record;
        return $clients;
    });

    json_response(['ok' => true, 'data' => $saved]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) json_error('Missing id.');

    $client = null;
    db_transaction('clients', function ($clients) use ($id, &$client) {
        $client = array_values(array_filter($clients, fn($c) => $c['id'] === $id));
        if ($client) {
            $client = $client[0];
            $client['deleted_at'] = date('c');
            $archivePath = __DIR__ . '/../data/djson/clients.json';
            $archive = file_exists($archivePath) ? json_decode(file_get_contents($archivePath), true) : [];
            if (!is_array($archive)) $archive = [];
            $archive[] = $client;
            file_put_contents($archivePath, json_encode($archive, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        return array_values(array_filter($clients, fn($c) => $c['id'] !== $id));
    });

    json_response(['ok' => true]);
}

json_error('Unsupported method.', 405);
