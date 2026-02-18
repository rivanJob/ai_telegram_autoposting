#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\Database;

require_once __DIR__ . '/../src/Core/bootstrap.php';

$db = Database::connection();
$db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (filename VARCHAR(255) PRIMARY KEY, executed_at TIMESTAMP NOT NULL DEFAULT NOW())');

$done = $db->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$doneMap = array_flip($done ?: []);

$files = glob(__DIR__ . '/../db/migrations/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
    $base = basename($file);
    if (isset($doneMap[$base])) {
        echo "skip {$base}\n";
        continue;
    }
    $sql = file_get_contents($file);
    $db->beginTransaction();
    $db->exec($sql);
    $db->prepare('INSERT INTO schema_migrations(filename) VALUES(:f)')->execute(['f' => $base]);
    $db->commit();
    echo "applied {$base}\n";
}
