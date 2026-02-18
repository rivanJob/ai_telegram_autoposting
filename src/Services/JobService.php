<?php

declare(strict_types=1);

namespace AutoPoster\Services;

use AutoPoster\Core\Db;
use PDO;

final class JobService
{
    public function fetchForWorker(): ?array
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        $stmt = $pdo->query("SELECT * FROM jobs WHERE status = 'NEW' AND run_at <= NOW() ORDER BY run_at ASC FOR UPDATE SKIP LOCKED LIMIT 1");
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            $pdo->commit();
            return null;
        }
        $pdo->prepare("UPDATE jobs SET status='RUNNING', started_at=NOW(), attempts=attempts+1 WHERE id=:id")->execute(['id' => $job['id']]);
        $pdo->commit();
        return $job;
    }

    public function markDone(int $id, array $payload, array $telegram): void
    {
        Db::pdo()->prepare("UPDATE jobs SET status='DONE', finished_at=NOW(), parsed_payload=:payload, telegram_response=:tres WHERE id=:id")
            ->execute(['id' => $id, 'payload' => json_encode($payload), 'tres' => json_encode($telegram),]);
    }

    public function markError(int $id, string $error, ?string $rawLlm = null): void
    {
        Db::pdo()->prepare("UPDATE jobs SET status='ERROR', finished_at=NOW(), last_error=:err, raw_llm_response = COALESCE(:raw, raw_llm_response) WHERE id=:id")
            ->execute(['id' => $id, 'err' => $error, 'raw' => $rawLlm,]);
    }
}
