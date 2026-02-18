<?php

declare(strict_types=1);

namespace Autoposter\Guards;

use Autoposter\Core\Env;

final class AuthGuard
{
    public static function requireAuth(): void
    {
        if (empty($_SESSION['admin_user_id'])) {
            header('Location: /admin/login.php');
            exit;
        }

        $createdAt = (int)($_SESSION['created_at'] ?? 0);
        $lastSeen = (int)($_SESSION['last_seen'] ?? 0);
        $now = time();

        if ($now - $lastSeen > Env::int('SESSION_IDLE_TIMEOUT', 1800) || $now - $createdAt > Env::int('SESSION_ABSOLUTE_TIMEOUT', 28800)) {
            session_regenerate_id(true);
            session_unset();
            session_destroy();
            header('Location: /admin/login.php?expired=1');
            exit;
        }

        $_SESSION['last_seen'] = $now;
    }
}
