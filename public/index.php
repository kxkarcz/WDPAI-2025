<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/core/Autoloader.php';
require_once __DIR__ . '/../Routing.php';

Autoloader::register();

Env::load();

set_exception_handler(function (Throwable $e): void {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    if (class_exists('ErrorController')) {
        (new ErrorController())->serverError();
    } else {
        http_response_code(500);
        echo 'Wystąpił błąd serwera.';
    }
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

$sessionName = Env::get('SESSION_NAME', 'mindgarden_session');
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (session_name() !== $sessionName) {
        session_name($sessionName);
    }
    session_start();
}

Routing::registerRoutes();
Routing::run($_SERVER['REQUEST_URI'] ?? '/', $_SERVER['REQUEST_METHOD'] ?? 'GET');

