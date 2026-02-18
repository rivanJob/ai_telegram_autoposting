<?php

declare(strict_types=1);

namespace AutoPoster\Security;

use AutoPoster\Core\Db;

final class RateLimiter
{
    public static function check(string $ip, string $action): bool
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT attempts, locked_until FROM auth_rate_limits WHERE ip = :ip AND action = :action');
        $stmt->execute(['ip' => $ip, 'action' => $action]);
        $row = $stmt->fetch();
        if (!$row) {
            return true;
        }
        if (!empty($row['locked_until']) && strtotime((string)$row['locked_until']) > time()) {
            return false;
        }
        return true;
    }

    public static function fail(string $ip, string $action): void
    {
        $pdo = Db::pdo();
        $pdo->prepare("INSERT INTO auth_rate_limits (ip, action, attempts, locked_until, updated_at) VALUES (:ip,:action,1,NULL,NOW()) ON CONFLICT (ip, action) DO UPDATE SET attempts = auth_rate_limits.attempts + 1, locked_until = CASE WHEN auth_rate_limits.attempts >= 4 THEN NOW() + ((power(2, LEAST(auth_rate_limits.attempts, 8)))::text || ' minutes')::interval ELSE NULL END, updated_at = NOW()")
            ->execute(['ip' => $ip, 'action' => $action]);
    }

    public static function reset(string $ip, string $action): void
    {
        Db::pdo()->prepare('DELETE FROM auth_rate_limits WHERE ip = :ip AND action = :action')->execute(['ip' => $ip, 'action' => $action]);
    }
}
