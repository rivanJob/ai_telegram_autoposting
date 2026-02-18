<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class SchedulerService
{
    public function run(): int
    {
        $db = Database::connection();
        $sql = "SELECT s.id as slot_id, s.channel_id, s.theme_id, s.time_of_day, p.body as prompt_text, c.target as channel_target
                FROM schedule_slots s
                JOIN prompts p ON p.theme_id = s.theme_id AND p.is_active=true
                JOIN channels c ON c.id = s.channel_id
                WHERE s.enabled=true";
        $rows = $db->query($sql)->fetchAll();
        $created = 0;
        foreach ($rows as $row) {
            $scheduledAt = (new \DateTimeImmutable('today ' . $row['time_of_day']))->format('Y-m-d H:i:s');
            $stmt = $db->prepare("INSERT INTO jobs(channel_id, slot_id, theme_id, scheduled_at, run_after, status, attempt, prompt_text, channel_target, created_at, updated_at)
             VALUES(:channel,:slot,:theme,:scheduled,:scheduled,'NEW',0,:prompt,:target,NOW(),NOW()) ON CONFLICT(channel_id, slot_id, scheduled_at) DO NOTHING");
            $stmt->execute([
                'channel' => $row['channel_id'],
                'slot' => $row['slot_id'],
                'theme' => $row['theme_id'],
                'scheduled' => $scheduledAt,
                'prompt' => $row['prompt_text'],
                'target' => $row['channel_target'],
            ]);
            $created += $stmt->rowCount();
        }
        return $created;
    }
}
