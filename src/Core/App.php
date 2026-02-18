<?php

declare(strict_types=1);

namespace Autoposter\Core;

use Autoposter\Services\AuditService;

final class App
{
    private static ?Db $db = null;
    private static ?Logger $logger = null;
    private static ?AuditService $audit = null;

    public static function boot(string $basePath): void
    {
        Env::load($basePath);
    }

    public static function db(): Db
    {
        if (!self::$db) {
            self::$db = new Db();
        }

        return self::$db;
    }

    public static function logger(): Logger
    {
        if (!self::$logger) {
            self::$logger = new Logger(Env::get('LOG_PATH', __DIR__ . '/../../logs/app.log'));
        }

        return self::$logger;
    }

    public static function audit(): AuditService
    {
        if (!self::$audit) {
            self::$audit = new AuditService(self::db()->getConnection(), self::logger());
        }

        return self::$audit;
    }
}
