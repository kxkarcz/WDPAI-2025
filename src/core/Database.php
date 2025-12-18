<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $host = Env::get('DB_HOST', 'db');
        $port = Env::get('DB_PORT', '5432');
        $name = Env::get('DB_NAME', 'db');
        $user = Env::get('DB_USER', 'docker');
        $password = Env::get('DB_PASSWORD', 'docker');

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $attempts = (int) Env::get('DB_CONNECT_RETRIES', '5');
        $delayMs = (int) Env::get('DB_CONNECT_DELAY_MS', '500');

        $lastException = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                self::$connection = new PDO($dsn, $user, $password, $options);
                return self::$connection;
            } catch (PDOException $exception) {
                $lastException = $exception;
                usleep($delayMs * 1000);
            }
        }

        throw $lastException ?? new RuntimeException('Nie udało się nawiązać połączenia z bazą danych.');

        return self::$connection;
    }
}

