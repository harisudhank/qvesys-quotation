<?php
/**
 * CLI Helper Script to set/update username and password hash in data/user.json
 * Usage:
 *   php scripts/update_user.php <username> <password> [display_name]
 */

require_once __DIR__ . '/../includes/db.php';

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

$username = trim($argv[1] ?? '');
$password = $argv[2] ?? '';
$name = trim($argv[3] ?? '');

if (empty($username) || empty($password)) {
    echo "Usage: php scripts/update_user.php <username> <password> [display_name]\n";
    echo "Example: php scripts/update_user.php admin admin123 Administrator\n";
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

db_transaction('user', function ($u) use ($username, $hash, $name) {
    if (!is_array($u)) $u = [];
    $u['username'] = $username;
    $u['password_hash'] = $hash;
    $u['name'] = !empty($name) ? $name : ($u['name'] ?? $username);
    return $u;
});

echo "✓ User credentials updated successfully in data/user.json:\n";
echo "  Username     : " . $username . "\n";
echo "  Password Hash: " . $hash . "\n";
if (!empty($name)) {
    echo "  Display Name : " . $name . "\n";
}
