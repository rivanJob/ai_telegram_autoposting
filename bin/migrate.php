<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = Autoposter\Core\App::db()->getConnection();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (filename text primary key, applied_at timestamptz not null default now())');
$applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$appliedSet = array_flip($applied);

$files = glob(__DIR__ . '/../db/migrations/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if (isset($appliedSet[$name])) {
        continue;
    }

    $sql = file_get_contents($file);
    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations(filename) VALUES(:f)');
        $stmt->execute([':f' => $name]);
        $pdo->commit();
        echo "Applied: $name\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "Migration failed: $name {$e->getMessage()}\n");
        exit(1);
    }
}

echo "Migrations complete\n";
