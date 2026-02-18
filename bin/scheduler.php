#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Services\SchedulerService;

require_once __DIR__ . '/../src/Core/bootstrap.php';

$created = (new SchedulerService())->run();
echo "jobs_created={$created}\n";
