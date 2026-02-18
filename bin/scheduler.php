<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = Autoposter\Core\App::db()->getConnection();
$created = (new Autoposter\Services\SchedulerService($pdo))->run();
Autoposter\Core\App::logger()->info('scheduler_run', ['created_jobs' => $created]);
echo "Created jobs: $created\n";
