#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$rows = [];
$rows[] = ['env', is_file(dirname(__DIR__) . '/.env') ? 'PASS' : 'FAIL'];
try { AutoPoster\Core\Db::pdo()->query('SELECT COUNT(*) FROM admin_users'); $rows[]=['db_tables','PASS']; } catch(Throwable $e){ $rows[]=['db_tables','FAIL']; }
try { $ok=(new AutoPoster\Clients\TelegramClient())->getMe()['ok'] ?? false; $rows[]=['telegram',$ok?'PASS':'FAIL']; } catch(Throwable $e){ $rows[]=['telegram','FAIL']; }
try { (new AutoPoster\Clients\GrokClient())->generate('{"type":"text","text":"setup check"}'); $rows[]=['grok','PASS (expected via wg0)']; } catch(Throwable $e){ $rows[]=['grok','FAIL']; }

foreach ($rows as [$k,$v]) { echo str_pad($k,16) . $v . PHP_EOL; }
