<?php
require_once __DIR__ . '/../includes/db.php';

const API_KEY = 'qvesys989403';

$key = $_GET['api_key'] ?? '';
if ($key !== API_KEY) {
    json_error('Invalid API key.', 401);
}

$quotations = db_read('quotations');
$clients = db_read('clients');
$items = db_read('items');

$totalQuotations = count($quotations);
$totalValue = array_sum(array_column($quotations, 'total'));
$accepted = count(array_filter($quotations, fn($q) => ($q['status'] ?? '') === 'accepted'));
$pending = count(array_filter($quotations, fn($q) => in_array($q['status'] ?? '', ['draft', 'sent'], true)));
$totalClients = count($clients);
$totalItems = count($items);

json_response([
    'ok' => true,
    'data' => [
        'total_quotations' => $totalQuotations,
        'total_value' => $totalValue,
        'accepted' => $accepted,
        'pending' => $pending,
        'total_clients' => $totalClients,
        'total_items' => $totalItems,
    ],
]);
