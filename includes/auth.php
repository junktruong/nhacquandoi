<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

function session_boot(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'path' => BASE_URL === '' ? '/' : BASE_URL . '/',
        ]);
        session_start();
    }
}

function is_admin(): bool {
    session_boot();
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void {
    if (!is_admin()) redirect('/admin/login.php');
}

function login_admin(string $username, string $password): bool {
    session_boot();
    $pdo = db();

    $st = $pdo->prepare("SELECT id, username, password_hash FROM admins WHERE username = :u LIMIT 1");
    $st->execute([':u' => $username]);
    $row = $st->fetch();
    if (!$row) return false;
    if (!password_verify($password, (string)$row['password_hash'])) return false;

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$row['id'];
    $_SESSION['admin_user'] = (string)$row['username'];
    return true;
}

function logout_admin(): void {
    session_boot();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}
