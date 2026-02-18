#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$jobs = new AutoPoster\Services\JobService();
$grok = new AutoPoster\Clients\GrokClient();
$telegram = new AutoPoster\Clients\TelegramClient();
$poll = (int)AutoPoster\Core\Env::get('WORKER_POLL_SECONDS', '5');

while (true) {
    $job = $jobs->fetchForWorker();
    if (!$job) {
        sleep($poll);
        continue;
    }

    try {
        $generated = $grok->generate('Generate Telegram content from payload: ' . json_encode($job['payload']));
        $payload = $generated['json'];
        $payload['channel_id'] = $job['channel_id'];
        $tg = $telegram->publish($payload);
        $jobs->markDone((int)$job['id'], $payload, $tg);
        AutoPoster\Core\Logger::info('job.done', ['job_id' => $job['id']]);
    } catch (Throwable $e) {
        $jobs->markError((int)$job['id'], $e->getMessage());
        AutoPoster\Core\Logger::error('job.error', ['job_id' => $job['id'], 'error' => $e->getMessage()]);
    }
}
