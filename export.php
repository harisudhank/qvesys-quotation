<?php
/**
 * Export a table (clients | items) to Excel (CSV), Word (.doc) or PDF.
 * Usage: export.php?table=clients&type=excel
 *        export.php?table=items&type=word
 *        export.php?table=clients&type=pdf
 * Add &sample=1 to download an empty header-only CSV as an import template.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

use Dompdf\Dompdf;

// Detect AJAX/fetch request (send JSON 401 instead of redirect)
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    || !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
    || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

if ($isAjax) {
    require_login_api();
} else {
    require_login();
}

$table = $_GET['table'] ?? '';
$type  = strtolower($_GET['type'] ?? '');
$sample = isset($_GET['sample']);

if (!in_array($table, ['clients', 'items', 'companies'], true)) {
    http_response_code(400);
    echo 'Invalid table.';
    exit;
}
if (!in_array($type, ['excel', 'word', 'pdf'], true)) {
    http_response_code(400);
    echo 'Invalid type.';
    exit;
}

$settings = db_read('settings');
$company = $settings['company']['name'] ?? 'QVESYS Quotation';
$title = $table === 'clients' ? 'Clients' : ($table === 'companies' ? 'Companies' : 'Items & Rates');

$headers = [];
$rows = [];

if ($table === 'clients') {
    $headers = ['Name', 'Contact Person', 'GSTIN', 'Phone', 'Email', 'State'];
    $data = $sample ? [] : db_read('clients');
    foreach ($data as $r) {
        $rows[] = [
            $r['name'] ?? '',
            $r['contact_person'] ?? '',
            $r['gstin'] ?? '',
            $r['phone'] ?? '',
            $r['email'] ?? '',
            $r['state'] ?? '',
        ];
    }
}
elseif ($table === 'companies') {
    $headers = ['Name', 'GSTIN', 'Phone', 'Email', 'State'];
    $data = $sample ? [] : db_read('companies');
    foreach ($data as $r) {
        $rows[] = [
            $r['name'] ?? '',
            $r['gstin'] ?? '',
            $r['phone'] ?? '',
            $r['email'] ?? '',
            $r['state'] ?? '',
        ];
    }
}
else {
    $headers = ['Name', 'HSN/SAC', 'Unit', 'Rate', 'Tax %'];
    $data = $sample ? [] : db_read('items');
    foreach ($data as $r) {
        $rows[] = [
            $r['name'] ?? '',
            $r['hsn'] ?? '',
            $r['unit'] ?? '',
            $r['rate'] ?? 0,
            $r['tax_percent'] ?? 0,
        ];
    }
}

$filename = $table . ($sample ? '_sample' : '') . '_' . date('Ymd');

/* ---------- Excel (CSV) ---------- */
if ($type === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

/* ---------- Word (.doc, HTML based) ---------- */
if ($type === 'word') {
    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.doc"');
    header('Cache-Control: max-age=0');
    echo buildHtmlTable($company, $title, $headers, $rows);
    exit;
}

/* ---------- PDF (real PDF via dompdf) ---------- */
if ($type === 'pdf') {
    require_once __DIR__ . '/vendor/autoload.php';

    $html = buildPdfHtml($company, $title, $headers, $rows);

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    header('Cache-Control: max-age=0');
    $dompdf->stream($filename . '.pdf', ['Attachment' => true]);
    exit;
}

function buildHtmlTable(string $company, string $title, array $headers, array $rows): string
{
    $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
    $html .= '<head><meta charset="utf-8"><title>' . h($title) . '</title>';
    $html .= '<style>';
    $html .= 'body { font-family: Calibri, sans-serif; font-size: 11pt; color: #17233F; margin: 20px; }';
    $html .= 'h2 { font-size: 16pt; margin: 0 0 4px; }';
    $html .= 'h3 { font-size: 12pt; margin: 0 0 18px; color: #555; }';
    $html .= 'table { border-collapse: collapse; width: 100%; }';
    $html .= 'th { background: #17233F; color: #fff; font-size: 10pt; text-align: left; padding: 8px 10px; border: 1px solid #17233F; }';
    $html .= 'td { padding: 7px 10px; border: 1px solid #ccc; font-size: 10pt; }';
    $html .= 'tr:nth-child(even) td { background: #f5f5f5; }';
    $html .= '.footer { margin-top: 16px; font-size: 8pt; color: #999; }';
    $html .= '</style></head><body>';
    $html .= '<h2>' . h($company) . '</h2>';
    $html .= '<h3>' . h($title) . '</h3>';
    $html .= '<table>';
    $html .= '<thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . h($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . h((string)$cell) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<p class="footer">Generated on ' . date('d M Y, h:i A') . '</p>';
    $html .= '</body></html>';
    return $html;
}

function buildPdfHtml(string $company, string $title, array $headers, array $rows): string
{
    $th = '';
    foreach ($headers as $header) {
        $th .= '<th style="background:#17233F;color:#fff;padding:8px;text-align:left;border:1px solid #999;">' . h($header) . '</th>';
    }

    $tbody = '';
    foreach ($rows as $row) {
        $tbody .= '<tr>';
        foreach ($row as $cell) {
            $tbody .= '<td style="padding:6px 8px;border:1px solid #ccc;">' . h((string)$cell) . '</td>';
        }
        $tbody .= '</tr>';
    }

    return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
      body { font-family: sans-serif; color: #17233F; margin: 0; padding: 20px; }
      h2 { margin: 0 0 4px; font-size: 16pt; }
      h3 { margin: 0 0 16px; color: #555; font-size: 12pt; }
      table { border-collapse: collapse; width: 100%; font-size: 9pt; }
      .footer { margin-top: 14px; font-size: 8pt; color: #888; }
    </style></head><body>
    <h2>' . h($company) . '</h2>
    <h3>' . h($title) . '</h3>
    <table><thead><tr>' . $th . '</tr></thead><tbody>' . $tbody . '</tbody></table>
    <p class="footer">Generated on ' . date('d M Y, h:i A') . '</p>
    </body></html>';
}
