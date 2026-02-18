<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AuditService
{
    public static function log(?int $adminId, string $action, string $ip, string $userAgent, array $meta = []): void
    {
        Database::connection()->prepare('INSERT INTO audit_log(admin_user_id, action, ip, user_agent, meta, created_at) VALUES (:admin,:action,:ip,:ua,:meta,NOW())')
            ->execute([
                'admin' => $adminId,
                'action' => $action,
                'ip' => $ip,
                'ua' => mb_substr($userAgent, 0, 512),
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            ]);
    }
}
