<?php

declare(strict_types=1);

final class Routing
{
    private static array $routes = [];

    public static function get(string $pattern, string $controller, string $action, ?array $roles = []): void
    {
        self::register('GET', $pattern, $controller, $action, $roles);
    }

    public static function post(string $pattern, string $controller, string $action, ?array $roles = []): void
    {
        self::register('POST', $pattern, $controller, $action, $roles);
    }

    public static function put(string $pattern, string $controller, string $action, ?array $roles = []): void
    {
        self::register('PUT', $pattern, $controller, $action, $roles);
    }

    public static function delete(string $pattern, string $controller, string $action, ?array $roles = []): void
    {
        self::register('DELETE', $pattern, $controller, $action, $roles);
    }

    public static function registerRoutes(): void
    {
        // Authentication
        self::get('/', 'SecurityController', 'showLogin', null);
        self::get('login', 'SecurityController', 'showLogin', null);
        // Registration
        self::get('register', 'SecurityController', 'showRegister', null);
        self::post('register', 'SecurityController', 'register', null);
        self::post('login', 'SecurityController', 'login', null);
        self::post('logout', 'SecurityController', 'logout', null);

        // Theme toggle
        self::post('theme/toggle', 'SettingsController', 'toggleTheme', null);

        // Dashboard redirect
        self::get('dashboard', 'DashboardController', 'index', []);

        // Patient routes
        self::get('patient/dashboard', 'PatientController', 'dashboard', [User::ROLE_PATIENT]);
        self::post('patient/moods', 'PatientController', 'logMood', [User::ROLE_PATIENT]);
        self::post('patient/habits', 'PatientController', 'logHabit', [User::ROLE_PATIENT]);
        self::post('patient/habits/create', 'PatientController', 'createHabit', [User::ROLE_PATIENT]);
        self::get('patient/moods', 'PatientController', 'moodHistory', [User::ROLE_PATIENT]);
        self::get('patient/tree', 'PatientController', 'treeState', [User::ROLE_PATIENT]);
        self::get('patient/emotions', 'PatientController', 'emotions', [User::ROLE_PATIENT]);
        self::get('patient/habits', 'PatientController', 'habits', [User::ROLE_PATIENT]);
        self::get('patient/history', 'PatientController', 'history', [User::ROLE_PATIENT]);
        self::get('patient/chat', 'PatientController', 'chat', [User::ROLE_PATIENT]);
        self::get('patient/export', 'PatientController', 'exportMoods', [User::ROLE_PATIENT]);

        // Psychologist routes
        self::get('psychologist/dashboard', 'PsychologistController', 'dashboard', [User::ROLE_PSYCHOLOGIST]);
        self::get('psychologist/patient/{patientId}/moods', 'PsychologistController', 'patientMoodHistory', [User::ROLE_PSYCHOLOGIST]);
        self::get('psychologist/patient/{patientId}/analysis-entries', 'PsychologistController', 'patientAnalysisEntries', [User::ROLE_PSYCHOLOGIST]);
        self::get('psychologist/patient/{patientId}/export', 'PsychologistController', 'exportCsv', [User::ROLE_PSYCHOLOGIST]);
        self::get('psychologist/analysis', 'PsychologistController', 'analysis', [User::ROLE_PSYCHOLOGIST]);
        self::post('psychologist/analysis/entry', 'PsychologistController', 'createAnalysisEntry', [User::ROLE_PSYCHOLOGIST]);
        self::put('psychologist/analysis/entry/{entryId}', 'PsychologistController', 'updateAnalysisEntry', [User::ROLE_PSYCHOLOGIST]);
        self::delete('psychologist/analysis/entry/{entryId}', 'PsychologistController', 'deleteAnalysisEntry', [User::ROLE_PSYCHOLOGIST]);
        self::get('psychologist/chat', 'PsychologistController', 'chat', [User::ROLE_PSYCHOLOGIST]);
        self::post('psychologist/settings/regenerate-code', 'PsychologistController', 'regenerateCode', [User::ROLE_PSYCHOLOGIST]);
        self::post('psychologist/patient/detach', 'PsychologistController', 'detachPatient', [User::ROLE_PSYCHOLOGIST]);
        self::get('psychologist/settings', 'PsychologistController', 'settings', [User::ROLE_PSYCHOLOGIST]);

        // Administrator routes
        self::get('admin/dashboard', 'AdminController', 'dashboard', [User::ROLE_ADMIN]);
        self::post('admin/users', 'AdminController', 'createUser', [User::ROLE_ADMIN]);
        self::post('admin/users/{userId}/update', 'AdminController', 'updateUser', [User::ROLE_ADMIN]);
        self::post('admin/users/{userId}/delete', 'AdminController', 'deleteUser', [User::ROLE_ADMIN]);
        self::post('admin/assignments', 'AdminController', 'assignPatient', [User::ROLE_ADMIN]);

        // API (AJAX)
        self::get('api/patient/moods', 'ApiController', 'patientMoods', [User::ROLE_PATIENT]);
        self::get('api/patient/habits', 'ApiController', 'patientHabits', [User::ROLE_PATIENT]);
        self::get('api/patient/emotions', 'ApiController', 'patientEmotions', [User::ROLE_PATIENT]);
        self::get('api/chat/messages', 'ApiController', 'chatMessages', [User::ROLE_PATIENT, User::ROLE_PSYCHOLOGIST]);
        self::post('api/chat/messages', 'ApiController', 'postChatMessage', [User::ROLE_PATIENT, User::ROLE_PSYCHOLOGIST]);
        self::get('api/emotions', 'ApiController', 'emotionOptions', [User::ROLE_PATIENT, User::ROLE_PSYCHOLOGIST]);
    }

    public static function run(string $requestUri, string $method): void
    {
        $path = trim(parse_url($requestUri, PHP_URL_PATH) ?? '/', '/');

        foreach (self::$routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }

            $controllerName = $route['controller'];
            if (!class_exists($controllerName)) {
                throw new RuntimeException(sprintf('Kontroler %s nie istnieje.', $controllerName));
            }

            $controller = new $controllerName();

            if ($controller instanceof AppController && $route['roles'] !== null) {
                $controller->authorize($route['roles']);
            }

            $params = array_filter(
                $matches,
                static fn ($key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY
            );

            $controller->{$route['action']}(...array_values($params));
            return;
        }

        $errorController = class_exists('ErrorController')
            ? new ErrorController()
            : null;

        if ($errorController instanceof ErrorController) {
            $errorController->notFound();
            return;
        }

        http_response_code(404);
        include __DIR__ . '/public/views/404.php';
    }

    private static function register(string $method, string $pattern, string $controller, string $action, ?array $roles): void
    {
        $regex = self::compilePattern($pattern);

        self::$routes[] = [
            'method' => strtoupper($method),
            'pattern' => $regex,
            'controller' => $controller,
            'action' => $action,
            'roles' => $roles,
            'raw' => trim($pattern, '/'),
        ];
    }

    private static function compilePattern(string $pattern): string
    {
        $trimmed = trim($pattern, '/');

        if ($trimmed === '') {
            return '#^$#';
        }

        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn (array $matches): string => sprintf('(?P<%s>[a-zA-Z0-9_-]+)', $matches[1]),
            $trimmed
        );

        return '#^' . $regex . '$#';
    }
}