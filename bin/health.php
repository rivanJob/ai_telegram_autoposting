#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$checks = [];
try {
    AutoPoster\Core\Db::pdo()->query('SELECT 1');
    $checks['db'] = 'ok';
} catch (Throwable $e) { $checks['db'] = 'fail'; }

try {
    $tg = new AutoPoster\Clients\TelegramClient();
    $checks['telegram'] = (($tg->getMe()['ok'] ?? false) ? 'ok' : 'fail');
} catch (Throwable $e) { $checks['telegram'] = 'fail'; }

try {
    $g = new AutoPoster\Clients\GrokClient();
    $g->generate('{"type":"text","text":"ping"}');
    $checks['grok'] = 'ok';
} catch (Throwable $e) { $checks['grok'] = 'fail'; }

echo json_encode($checks, JSON_PRETTY_PRINT) . PHP_EOL;
exit(in_array('fail', $checks, true) ? 1 : 0);
