<?php

declare(strict_types=1);

final class Autoloader
{
    private const DIRECTORIES = [
        __DIR__,
        __DIR__ . '/../controllers',
        __DIR__ . '/../models',
        __DIR__ . '/../repository',
        __DIR__ . '/../services',
        __DIR__ . '/../utils',
    ];

    public static function register(): void
    {
        spl_autoload_register(static function (string $className): void {
            foreach (self::DIRECTORIES as $directory) {
                $file = $directory . '/' . $className . '.php';
                if (is_file($file)) {
                    require_once $file;
                    return;
                }
            }
        });
    }
}

