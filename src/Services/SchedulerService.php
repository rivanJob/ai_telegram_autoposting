<?php

declare(strict_types=1);

namespace AutoPoster\Services;

use AutoPoster\Core\Db;

final class SchedulerService
{
    public function run(): int
    {
        $pdo = Db::pdo();
        $sql = "INSERT INTO jobs (channel_id, slot_id, status, run_at, payload, idempotency_key)
                SELECT s.channel_id, s.id, 'NEW', NOW(), jsonb_build_object('slot_id', s.id), CONCAT(s.channel_id, ':', s.id, ':', to_char(NOW(),'YYYYMMDDHH24MI'))
                FROM schedule_slots s
                WHERE s.enabled = true
                ON CONFLICT (idempotency_key) DO NOTHING";
        return $pdo->exec($sql) ?: 0;
    }
}
