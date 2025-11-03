<?php

// TODO Controllery -> singleton
//URL: /dashboard/5432
//URL: /dashboard/ REGEXEM 

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/DashboardController.php';

class Routing {
    public static $routes = [
        "login" => [
            "controller" => "SecurityController",
            "action" => "login"
        ],
        "register" => [
            "controller" => "SecurityController",
            "action" => "register"
        ],
         "dashboard" => [
            "controller" => "DashboardController",
            "action" => "index"
        ]
        ];

public static function run(string $path) {
        $path = trim($path, '/');

        if (preg_match('/^dashboard\/(\d+)$/', $path, $matches)) {
            $controllerName = self::$routes['dashboard']['controller'];
            $actionName = self::$routes['dashboard']['action'];
            $controller = new $controllerName();
            $id = (int)$matches[1];
            $controller->$actionName($id);
            return;
        }

        if ($path === 'dashboard') {
            $controllerName = self::$routes['dashboard']['controller'];
            $actionName = self::$routes['dashboard']['action'];
            $controller = new $controllerName();
            $controller->$actionName(null);
            return;
        }

        if (array_key_exists($path, self::$routes)) {
            $controllerName = self::$routes[$path]['controller'];
            $actionName = self::$routes[$path]['action'];
            $controller = new $controllerName();
            $controller->$actionName();
            return;
        }

        include 'public/views/404.php';
    }
}