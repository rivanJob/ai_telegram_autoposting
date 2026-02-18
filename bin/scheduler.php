#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$svc = new AutoPoster\Services\SchedulerService();
$count = $svc->run();
AutoPoster\Core\Logger::info('scheduler.completed', ['jobs_created' => $count]);
echo "Created jobs: {$count}" . PHP_EOL;
