<?php

declare(strict_types=1);

namespace App\Services;

use App\Clients\GrokClient;
use App\Clients\TelegramClient;
use App\Core\Database;
use App\Core\Env;

final class JobService
{
    public function processNext(): bool
    {
        $db = Database::connection();
        $db->beginTransaction();
        $row = $db->query("SELECT * FROM jobs WHERE status='NEW' AND run_after <= NOW() ORDER BY scheduled_at ASC FOR UPDATE SKIP LOCKED LIMIT 1")->fetch();
        if (!$row) {
            $db->commit();
            return false;
        }

        $db->prepare("UPDATE jobs SET status='RUNNING', started_at=NOW(), updated_at=NOW() WHERE id=:id")->execute(['id' => $row['id']]);
        $db->commit();

        try {
            $grok = new GrokClient();
            $telegram = new TelegramClient();
            $result = $grok->generate((string) $row['prompt_text']);
            $tele = $telegram->publish((string) $row['channel_target'], $result['json']);

            $db->prepare("UPDATE jobs SET status='DONE', raw_llm_response=:raw, parsed_json=:json, telegram_response=:tg, finished_at=NOW(), updated_at=NOW() WHERE id=:id")
                ->execute([
                    'id' => $row['id'],
                    'raw' => $result['raw'],
                    'json' => json_encode($result['json'], JSON_UNESCAPED_UNICODE),
                    'tg' => json_encode($tele, JSON_UNESCAPED_UNICODE),
                ]);
        } catch (\Throwable $e) {
            $attempt = ((int) $row['attempt']) + 1;
            $max = Env::int('WORKER_MAX_ATTEMPTS', 5);
            $backoff = Env::int('WORKER_BASE_BACKOFF_SECONDS', 60) * (2 ** max(0, $attempt - 1));
            $status = $attempt >= $max ? 'ERROR' : 'NEW';
            $db->prepare("UPDATE jobs SET status=:status, attempt=:attempt, last_error=:err, run_after=NOW() + (:backoff || ' seconds')::interval, updated_at=NOW() WHERE id=:id")
                ->execute([
                    'id' => $row['id'],
                    'status' => $status,
                    'attempt' => $attempt,
                    'err' => mb_substr($e->getMessage(), 0, 2000),
                    'backoff' => $backoff,
                ]);
        }

        return true;
    }

    public function recoverDeadLetters(): void
    {
        $threshold = Env::int('WORKER_DEAD_LETTER_MINUTES', 30);
        Database::connection()->exec("UPDATE jobs SET status='ERROR', last_error='Recovered stale RUNNING job', updated_at=NOW() WHERE status='RUNNING' AND started_at < NOW() - INTERVAL '{$threshold} minutes'");
    }
}
