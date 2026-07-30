<?php
/**
 * Simple JSON flat-file "database" layer with exclusive-lock safe writes.
 * Every table is one JSON file inside /data. Safe for small/medium business
 * traffic (single-server, file-locking based concurrency control).
 */

define('DATA_DIR', __DIR__ . '/../data');

function db_path(string $table): string
{
    $safe = preg_replace('/[^a-z_]/', '', strtolower($table));
    return DATA_DIR . '/' . $safe . '.json';
}

/** Read a JSON table. Returns array (empty array if file missing/corrupt). */
function db_read(string $table)
{
    $path = db_path($table);
    if (!file_exists($path)) {
        return [];
    }
    $fp = fopen($path, 'r');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Overwrite a JSON table atomically (write to temp file then rename). */
function db_write(string $table, $data): bool
{
    $path = db_path($table);
    $tmp = $path . '.tmp.' . uniqid();
    $fp = fopen($tmp, 'w');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return rename($tmp, $path);
}

/**
 * Read-modify-write a table under an exclusive lock, so concurrent requests
 * (e.g. two staff creating quotations at the same time) cannot clobber each
 * other or double-allocate the same quotation number.
 */
function db_transaction(string $table, callable $mutator)
{
    $path = db_path($table);
    if (!file_exists($path)) {
        db_write($table, []);
    }
    $fp = fopen($path, 'r+');
    if (!$fp) throw new RuntimeException("Cannot open $table for transaction");
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];

    $result = $mutator($data);
    $newData = is_array($result) ? $result : $data;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $result;
}

/** Generate a short unique id. */
function gen_id(string $prefix = ''): string
{
    return $prefix . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_response(['ok' => false, 'error' => $message], $status);
}
