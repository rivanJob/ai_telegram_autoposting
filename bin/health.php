<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Autoposter\Clients\GrokClient;
use Autoposter\Clients\TelegramClient;
use Autoposter\Core\App;

$checks = [];

try {
    App::db()->getConnection()->query('SELECT 1');
    $checks['db'] = 'pass';
} catch (Throwable $e) {
    $checks['db'] = 'fail:' . $e->getMessage();
}

try {
    (new TelegramClient())->getMe();
    $checks['telegram'] = 'pass';
} catch (Throwable $e) {
    $checks['telegram'] = 'fail:' . $e->getMessage();
}

try {
    $checks['grok'] = (new GrokClient())->ping() ? 'pass' : 'fail';
} catch (Throwable $e) {
    $checks['grok'] = 'fail:' . $e->getMessage();
}

header('Content-Type: application/json');
$allChecksPassed = !in_array(false, array_map(static fn (string $result): bool => str_starts_with($result, 'pass'), $checks), true);
echo json_encode(['status' => $allChecksPassed ? 'ok' : 'degraded', 'checks' => $checks], JSON_PRETTY_PRINT);
