#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Logger;
use App\Services\JobService;

require_once __DIR__ . '/../src/Core/bootstrap.php';

$logger = new Logger(Env::get('LOG_FILE', __DIR__ . '/../storage/app.log'));
$service = new JobService();
$service->recoverDeadLetters();

$once = in_array('--once', $argv, true);
do {
    $worked = $service->processNext();
    if (!$worked) {
        sleep(2);
    }
} while (!$once);

$logger->info('worker iteration finished', ['once' => $once]);
