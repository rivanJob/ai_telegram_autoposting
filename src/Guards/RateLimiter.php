<?php

declare(strict_types=1);

namespace Autoposter\Guards;

use PDO;

final class RateLimiter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function allow(string $scope, string $key, int $maxAttempts = 5, int $windowSeconds = 900): bool
    {
        $this->pdo->prepare('DELETE FROM rate_limits WHERE last_attempt_at < NOW() - (:seconds || " seconds")::interval')
            ->execute([':seconds' => $windowSeconds]);

        $stmt = $this->pdo->prepare('SELECT attempts FROM rate_limits WHERE scope=:scope AND key_hash=:key');
        $stmt->execute([':scope' => $scope, ':key' => hash('sha256', $key)]);
        $row = $stmt->fetch();
        return !$row || (int)$row['attempts'] < $maxAttempts;
    }

    public function hit(string $scope, string $key): void
    {
        $hashed = hash('sha256', $key);
        $sql = 'INSERT INTO rate_limits(scope,key_hash,attempts,last_attempt_at) VALUES(:scope,:key,1,NOW())
                ON CONFLICT(scope,key_hash) DO UPDATE SET attempts=rate_limits.attempts+1,last_attempt_at=NOW()';
        $this->pdo->prepare($sql)->execute([':scope' => $scope, ':key' => $hashed]);
    }

    public function clear(string $scope, string $key): void
    {
        $this->pdo->prepare('DELETE FROM rate_limits WHERE scope=:scope AND key_hash=:key')
            ->execute([':scope' => $scope, ':key' => hash('sha256', $key)]);
    }
}
