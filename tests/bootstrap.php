<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/core/Autoloader.php';

Autoloader::register();

// Ładowanie zmiennych środowiskowych dla testów
if (file_exists(__DIR__ . '/../.env')) {
    Env::load(__DIR__ . '/../.env');
}
