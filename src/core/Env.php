<?php

declare(strict_types=1);

final class Env
{
    private const DEFAULT_PATH = __DIR__ . '/../../.env';

    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        $variables = self::load();

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        return $variables[$key] ?? $default;
    }

    public static function load(?string $path = null): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = $path ?? self::DEFAULT_PATH;

        if (!is_file($path)) {
            $examplePath = $path . '.example';
            $path = is_file($examplePath) ? $examplePath : null;
        }

        $variables = [];

        if ($path && is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#')) {
                    continue;
                }

                [$key, $value] = array_pad(explode('=', $line, 2), 2, null);
                if ($key === null) {
                    continue;
                }

                $variables[trim($key)] = $value !== null ? trim($value) : null;
            }
        }

        self::$cache = $variables;

        return $variables;
    }
}

