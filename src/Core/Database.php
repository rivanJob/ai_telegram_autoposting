<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s',
            Env::get('DB_HOST', '127.0.0.1'),
            Env::get('DB_PORT', '5432'),
            Env::get('DB_DATABASE', 'autoposter')
        );

        self::$pdo = new PDO($dsn, Env::get('DB_USERNAME', 'autoposter'), Env::get('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$pdo;
    }
}
