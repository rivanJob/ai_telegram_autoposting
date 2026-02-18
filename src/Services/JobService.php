<?php

declare(strict_types=1);

namespace Autoposter\Services;

use PDO;

final class JobService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function lockNextNewJob(): ?array
    {
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->query("SELECT * FROM jobs WHERE status='NEW' AND run_at <= NOW() ORDER BY run_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED");
        $job = $stmt->fetch();
        if (!$job) {
            $this->pdo->commit();
            return null;
        }

        $this->pdo->prepare("UPDATE jobs SET status='RUNNING', started_at=NOW(), attempts=attempts+1 WHERE id=:id")
            ->execute([':id' => $job['id']]);
        $this->pdo->commit();
        return $job;
    }

    public function markDone(int $id, array $grokJson, array $telegramResp, string $rawLlm): void
    {
        $this->pdo->prepare("UPDATE jobs SET status='DONE', finished_at=NOW(), parsed_payload=:parsed::jsonb, llm_raw=:raw, telegram_raw=:tg::jsonb WHERE id=:id")
            ->execute([':id' => $id, ':parsed' => json_encode($grokJson), ':raw' => $rawLlm, ':tg' => json_encode($telegramResp)]);
    }

    public function markError(int $id, string $error, ?string $rawLlm = null): void
    {
        $this->pdo->prepare("UPDATE jobs SET status='ERROR', finished_at=NOW(), last_error=:e, llm_raw=COALESCE(:raw,llm_raw) WHERE id=:id")
            ->execute([':id' => $id, ':e' => mb_substr($error, 0, 4000), ':raw' => $rawLlm]);
    }

    public function requeueStaleRunning(int $minutes): int
    {
        $stmt = $this->pdo->prepare("UPDATE jobs SET status='ERROR', finished_at=NOW(), last_error='stale running auto reset' WHERE status='RUNNING' AND started_at < NOW() - (:m || ' minutes')::interval");
        $stmt->execute([':m' => $minutes]);
        return $stmt->rowCount();
    }
}
