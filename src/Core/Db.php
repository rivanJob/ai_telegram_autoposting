<?php

declare(strict_types=1);

namespace Autoposter\Core;

use PDO;

final class Db
{
    private ?PDO $pdo = null;

    public function getConnection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', '5432');
        $db = Env::get('DB_NAME', 'autoposter');
        $user = Env::get('DB_USER', 'autoposter');
        $pass = Env::get('DB_PASS', '');

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $db);

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $this->pdo;
    }
}
