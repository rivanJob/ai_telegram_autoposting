<?php

declare(strict_types=1);

use App\Core\Env;
use Dotenv\Dotenv;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
if (file_exists($root . '/.env')) {
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

date_default_timezone_set(Env::get('APP_TIMEZONE', 'UTC'));

set_exception_handler(static function (Throwable $e): void {
    http_response_code(500);
    echo 'Internal Server Error';
});
