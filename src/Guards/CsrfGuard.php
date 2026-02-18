<?php

declare(strict_types=1);

namespace App\Guards;

use App\Core\Env;

final class CsrfGuard
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf']) || ($_SESSION['csrf_exp'] ?? 0) < time()) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_exp'] = time() + Env::int('CSRF_TOKEN_TTL', 7200);
        }
        return $_SESSION['csrf'];
    }

    public static function validate(?string $token): bool
    {
        if (!$token || empty($_SESSION['csrf']) || empty($_SESSION['csrf_exp'])) {
            return false;
        }
        if ((int) $_SESSION['csrf_exp'] < time()) {
            return false;
        }
        return hash_equals((string) $_SESSION['csrf'], $token);
    }
}
