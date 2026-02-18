#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Clients\GrokClient;
use App\Clients\TelegramClient;
use App\Core\Database;

require_once __DIR__ . '/../src/Core/bootstrap.php';

$checks = [];

try {
    Database::connection()->query('SELECT 1');
    $checks[] = ['name' => 'db', 'ok' => true, 'message' => 'connected'];
} catch (Throwable $e) {
    $checks[] = ['name' => 'db', 'ok' => false, 'message' => 'failed'];
}

try {
    $me = (new TelegramClient())->getMe();
    $checks[] = ['name' => 'telegram', 'ok' => (bool) ($me['ok'] ?? false), 'message' => $me['description'] ?? 'ok'];
} catch (Throwable $e) {
    $checks[] = ['name' => 'telegram', 'ok' => false, 'message' => 'failed'];
}

try {
    (new GrokClient())->generate('Return JSON object with key "type":"text" and key "text":"health"');
    $checks[] = ['name' => 'grok', 'ok' => true, 'message' => 'reachable (verify wg0 route separately)'];
} catch (Throwable $e) {
    $checks[] = ['name' => 'grok', 'ok' => false, 'message' => 'failed'];
}

echo json_encode(['checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
