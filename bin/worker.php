<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Autoposter\Clients\GrokClient;
use Autoposter\Clients\TelegramClient;
use Autoposter\Core\App;
use Autoposter\Core\Env;
use Autoposter\Services\JobService;

$pdo = App::db()->getConnection();
$jobService = new JobService($pdo);
$grok = new GrokClient();
$telegram = new TelegramClient();
$logger = App::logger();

while (true) {
    try {
        $jobService->requeueStaleRunning(Env::int('WORKER_STALE_RUNNING_MINUTES', 30));
        break;
    } catch (Throwable $e) {
        $logger->error('worker_bootstrap_failed', ['error' => $e->getMessage()]);
        sleep(5);
    }
}

while (true) {
    try {
        $job = $jobService->lockNextNewJob();
    } catch (Throwable $e) {
        $logger->error('worker_poll_failed', ['error' => $e->getMessage()]);
        sleep(5);
        continue;
    }

    if (!$job) {
        sleep(2);
        continue;
    }

    try {
        $postType = (string)($job['post_type'] ?? 'text');
        $prompt = (string)($job['prompt_snapshot'] ?: sprintf('Generate Telegram post as strict JSON for post type %s', $postType));
        $grokResult = $grok->generate($prompt);
        $channel = $pdo->prepare('SELECT chat_id FROM channels WHERE id=:id');
        $channel->execute([':id' => $job['channel_id']]);
        $chatId = (string)$channel->fetchColumn();
        $telegramResp = $telegram->publish($chatId, $grokResult['json']);
        $jobService->markDone((int)$job['id'], $grokResult['json'], $telegramResp, $grokResult['raw']);
        $logger->info('job_done', ['job_id' => $job['id']]);
    } catch (Throwable $e) {
        $jobService->markError((int)$job['id'], $e->getMessage());
        $logger->error('job_error', ['job_id' => $job['id'], 'error' => $e->getMessage()]);
    }
}
