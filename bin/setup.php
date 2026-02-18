<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Autoposter\Core\App;

$pdo = App::db()->getConnection();
$requiredTables = ['channels','themes','prompts','schedule_slots','jobs','admin_users','audit_log','settings'];
$results = [];
foreach ($requiredTables as $table) {
    $stmt = $pdo->prepare("SELECT to_regclass(:table)");
    $stmt->execute([':table' => $table]);
    $results["table_$table"] = $stmt->fetchColumn() ? 'pass' : 'fail';
}

ob_start();
require __DIR__ . '/health.php';
$health = json_decode(ob_get_clean(), true);

foreach (($health['checks'] ?? []) as $k => $v) {
    $results[$k] = str_starts_with((string)$v, 'pass') ? 'pass' : 'fail';
}

echo "Setup diagnostics:\n";
foreach ($results as $name => $result) {
    echo str_pad($name, 24) . " : $result\n";
}
