<?php

declare(strict_types=1);

namespace Autoposter\Services;

use PDO;

final class SchedulerService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function run(): int
    {
        $sql = "
        INSERT INTO jobs (channel_id, theme_id, schedule_slot_id, run_at, status)
        SELECT s.channel_id, s.theme_id, s.id, NOW(), 'NEW'
        FROM schedule_slots s
        JOIN channels c ON c.id=s.channel_id AND c.is_enabled=TRUE
        JOIN themes t ON t.id=s.theme_id AND t.is_enabled=TRUE
        WHERE s.is_enabled=TRUE
          AND s.day_of_week = EXTRACT(DOW FROM NOW())::int
          AND to_char(NOW(),'HH24:MI') = s.slot_time
        ON CONFLICT (channel_id, schedule_slot_id, run_at_key) DO NOTHING";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
