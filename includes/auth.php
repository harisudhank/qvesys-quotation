<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

/** Redirect to login if not authenticated. Call at top of every protected page. */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/** Same as require_login but for API endpoints (JSON 401 instead of redirect). */
function require_login_api(): void
{
    if (!is_logged_in()) {
        json_error('Not authenticated. Please log in again.', 401);
    }
}

function attempt_login(string $username, string $password): bool
{
    $userData = db_read('user');
    if (empty($userData)) {
        $settings = db_read('settings');
        $userData = $settings['auth'] ?? null;
    }
    if (!$userData) return false;

    // Handle single user object or array of users
    $users = isset($userData['username']) ? [$userData] : $userData;

    foreach ($users as $u) {
        if (isset($u['username'], $u['password_hash'])) {
            if (hash_equals($u['username'], $username) && password_verify($password, $u['password_hash'])) {
                $_SESSION['user'] = [
                    'username' => $u['username'],
                    'name' => $u['name'] ?? $u['username'],
                    'logged_in_at' => date('c'),
                ];
                return true;
            }
        }
    }
    return false;
}

function do_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** CSRF token helpers (used by all state-changing forms/API calls). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$token)) {
        json_error('Invalid or expired session token. Please refresh the page.', 419);
    }
}
