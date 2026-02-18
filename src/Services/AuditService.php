<?php

declare(strict_types=1);

namespace Autoposter\Services;

use Autoposter\Core\Logger;
use PDO;

final class AuditService
{
    public function __construct(private readonly PDO $pdo, private readonly Logger $logger)
    {
    }

    public function log(?int $adminUserId, string $action, string $status, array $meta = []): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO audit_log (admin_user_id, action, status, ip, user_agent, meta) VALUES (:uid,:action,:status,:ip,:ua,:meta::jsonb)');
        $stmt->execute([
            ':uid' => $adminUserId,
            ':action' => $action,
            ':status' => $status,
            ':ip' => $meta['ip'] ?? null,
            ':ua' => $meta['user_agent'] ?? null,
            ':meta' => json_encode($meta, JSON_UNESCAPED_SLASHES),
        ]);

        $this->logger->info('audit', ['admin_user_id' => $adminUserId, 'action' => $action, 'status' => $status]);
    }
}
