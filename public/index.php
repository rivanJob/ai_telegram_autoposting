<?php

declare(strict_types=1);

use AutoPoster\Core\Db;
use AutoPoster\Core\Env;
use AutoPoster\Security\Csrf;
use AutoPoster\Security\RateLimiter;
use AutoPoster\Security\Totp;

require dirname(__DIR__) . '/vendor/autoload.php';
Env::load(dirname(__DIR__) . '/.env');

session_name('autoposter_session');
session_set_cookie_params([
    'secure' => Env::get('COOKIE_SECURE', '1') === '1',
    'httponly' => true,
    'samesite' => Env::get('COOKIE_SAMESITE', 'Strict'),
    'path' => '/',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';");

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/health') {
    http_response_code(403);
    echo 'forbidden';
    exit;
}

$adminPath = Env::get('ADMIN_PATH', '/admin');
if (!str_starts_with($path, $adminPath)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$ipAllow = trim((string)Env::get('ADMIN_IP_ALLOWLIST', ''));
if ($ipAllow !== '') {
    $ips = array_map('trim', explode(',', $ipAllow));
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $ips, true)) {
        http_response_code(403);
        exit('IP restricted');
    }
}

$sub = trim(substr($path, strlen($adminPath)), '/') ?: 'dashboard';

if ($sub === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('CSRF validation failed');
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!RateLimiter::check($ip, 'login')) {
        http_response_code(429);
        exit('Too many attempts');
    }

    $stmt = Db::pdo()->prepare('SELECT * FROM admin_users WHERE username = :username AND active = true');
    $stmt->execute(['username' => $_POST['username'] ?? '']);
    $user = $stmt->fetch();

    if (!$user || !password_verify((string)($_POST['password'] ?? ''), (string)$user['password_hash']) || !Totp::verify((string)$user['totp_secret'], (string)($_POST['totp'] ?? ''))) {
        RateLimiter::fail($ip, 'login');
        Db::pdo()->prepare('INSERT INTO audit_log (actor_user_id, action, ip, user_agent, metadata) VALUES (NULL, :action, :ip, :ua, :meta)')
            ->execute(['action' => 'auth.login.fail', 'ip' => $ip, 'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '', 'meta' => json_encode(['username' => $_POST['username'] ?? ''])]);
        exit('Invalid credentials');
    }

    session_regenerate_id(true);
    $_SESSION['uid'] = $user['id'];
    $_SESSION['logged_in_at'] = time();
    $_SESSION['last_seen'] = time();
    RateLimiter::reset($ip, 'login');
    Db::pdo()->prepare('INSERT INTO audit_log (actor_user_id, action, ip, user_agent, metadata) VALUES (:uid, :action, :ip, :ua, :meta)')
        ->execute(['uid' => $user['id'], 'action' => 'auth.login.success', 'ip' => $ip, 'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '', 'meta' => '{}']);
    header('Location: ' . $adminPath . '/dashboard');
    exit;
}

$authRequired = !in_array($sub, ['login'], true);
if ($authRequired && empty($_SESSION['uid'])) {
    header('Location: ' . $adminPath . '/login');
    exit;
}

if (!empty($_SESSION['last_seen']) && (time() - (int)$_SESSION['last_seen']) > (int)Env::get('SESSION_IDLE_TIMEOUT', '1800')) {
    session_destroy();
    header('Location: ' . $adminPath . '/login');
    exit;
}
if (!empty($_SESSION['logged_in_at']) && (time() - (int)$_SESSION['logged_in_at']) > (int)Env::get('SESSION_MAX_LIFETIME', '28800')) {
    session_destroy();
    header('Location: ' . $adminPath . '/login');
    exit;
}
$_SESSION['last_seen'] = time();

$view = dirname(__DIR__) . '/admin/views/' . $sub . '.php';
if (!is_file($view)) {
    $view = dirname(__DIR__) . '/admin/views/dashboard.php';
}

require dirname(__DIR__) . '/admin/views/layout.php';
