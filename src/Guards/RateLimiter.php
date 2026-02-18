<?php

declare(strict_types=1);

namespace App\Guards;

use App\Core\Database;
use App\Core\Env;

final class RateLimiter
{
    public static function hit(string $scope, string $ip): int
    {
        $db = Database::connection();
        $window = Env::int('LOGIN_RATE_LIMIT_WINDOW', 900);
        $attempts = Env::int('LOGIN_RATE_LIMIT_ATTEMPTS', 5);

        $db->prepare('INSERT INTO rate_limits(scope, ip, attempts, last_attempt_at) VALUES (:scope,:ip,1,NOW())
            ON CONFLICT(scope, ip) DO UPDATE SET attempts = CASE WHEN rate_limits.last_attempt_at < NOW() - (:window || \' seconds\')::interval THEN 1 ELSE rate_limits.attempts + 1 END,
            last_attempt_at = NOW()')
            ->execute(['scope' => $scope, 'ip' => $ip, 'window' => $window]);

        $row = $db->prepare('SELECT attempts FROM rate_limits WHERE scope=:scope AND ip=:ip');
        $row->execute(['scope' => $scope, 'ip' => $ip]);
        $count = (int) ($row->fetch()['attempts'] ?? 0);

        if ($count > $attempts) {
            $seconds = min(Env::int('LOGIN_LOCKOUT_MAX_SECONDS', 3600), (int) pow(2, $count - $attempts));
            return $seconds;
        }

        return 0;
    }

    public static function clear(string $scope, string $ip): void
    {
        Database::connection()->prepare('DELETE FROM rate_limits WHERE scope=:scope AND ip=:ip')->execute(['scope' => $scope, 'ip' => $ip]);
    }
}
