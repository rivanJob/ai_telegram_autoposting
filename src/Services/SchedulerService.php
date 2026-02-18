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
        INSERT INTO jobs (channel_id, theme_id, schedule_slot_id, run_at, run_at_key, status, post_type, prompt_snapshot)
        SELECT
            s.channel_id,
            s.theme_id,
            s.id,
            NOW(),
            (FLOOR(EXTRACT(EPOCH FROM (NOW() AT TIME ZONE 'UTC')) / 60))::bigint,
            'NEW',
            COALESCE(sc.post_type_override, s.post_type),
            COALESCE(NULLIF(sc.prompt_override, ''), NULLIF(s.prompt_override, ''), COALESCE(p.body, ''))
        FROM schedule_slots s
        JOIN channels c ON c.id = s.channel_id AND c.is_enabled = TRUE
        JOIN themes t ON t.id = s.theme_id AND t.is_enabled = TRUE
        LEFT JOIN schedule_campaigns sc ON sc.slot_id = s.id
            AND sc.is_enabled = TRUE
            AND CURRENT_DATE BETWEEN sc.starts_on AND sc.ends_on
        LEFT JOIN LATERAL (
            SELECT body
            FROM prompts
            WHERE theme_id = s.theme_id
              AND is_active = TRUE
            ORDER BY version DESC, id DESC
            LIMIT 1
        ) p ON TRUE
        WHERE s.is_enabled = TRUE
          AND s.day_of_week = EXTRACT(DOW FROM NOW())::int
          AND to_char(NOW(),'HH24:MI') = s.slot_time
          AND NOT EXISTS (
              SELECT 1
              FROM schedule_exceptions se
              WHERE se.slot_id = s.id
                AND se.exception_date = CURRENT_DATE
          )
        ON CONFLICT (channel_id, schedule_slot_id, run_at_key) DO NOTHING";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
