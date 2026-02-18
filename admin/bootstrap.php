<?php

declare(strict_types=1);

use Autoposter\Core\App;
use Autoposter\Core\Env;

require_once __DIR__ . '/../vendor/autoload.php';
App::boot(dirname(__DIR__));

$secure = Env::bool('SESSION_SECURE_COOKIE', true);
session_name(Env::get('SESSION_NAME', 'autoposter_session'));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' https: data:;");

$allowlist = array_filter(array_map('trim', explode(',', Env::get('ADMIN_IP_ALLOWLIST', ''))));
if ($allowlist) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, $allowlist, true)) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
