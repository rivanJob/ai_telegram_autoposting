#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = AutoPoster\Core\Db::pdo();
foreach (glob(dirname(__DIR__) . '/db/migrations/*.sql') as $file) {
    echo "Applying " . basename($file) . PHP_EOL;
    $pdo->exec((string)file_get_contents($file));
}
echo "Migrations completed" . PHP_EOL;
