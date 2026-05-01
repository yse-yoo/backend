<?php

namespace App\Lib;

use Dotenv\Dotenv;
use PDO;

final class Database
{
    private static ?PDO $pdo = null;
    private static bool $envLoaded = false;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::loadEnv();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            self::env('MYSQL_HOST', 'mysql'),
            self::env('MYSQL_PORT', '3306'),
            self::env('MYSQL_DATABASE', 'pos'),
            self::env('MYSQL_CHARSET', 'utf8mb4')
        );

        self::$pdo = new PDO($dsn, self::env('MYSQL_USER', 'docker'), self::env('MYSQL_PASSWORD', 'docker'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }

    private static function loadEnv(): void
    {
        if (self::$envLoaded) {
            return;
        }

        Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();
        self::$envLoaded = true;
    }

    private static function env(string $key, string $default): string
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }
}
