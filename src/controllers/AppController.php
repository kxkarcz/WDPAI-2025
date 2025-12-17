<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/AuthService.php';

class AppController
{
    protected AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    protected function render(string $template, array $variables = [], string $layout = 'layout'): void
    {
        $basePath = __DIR__ . '/../../public/views/' . $template;
        $templatePathPhp = $basePath . '.php';
        $templatePathHtml = $basePath . '.html';

        $layoutBase = __DIR__ . '/../../public/views/' . $layout;
        $layoutPathPhp = $layoutBase . '.php';
        $layoutPathHtml = $layoutBase . '.html';

        $notFoundPath = __DIR__ . '/../../public/views/404.php';

        if (file_exists($templatePathPhp)) {
            $templatePath = $templatePathPhp;
        } elseif (file_exists($templatePathHtml)) {
            $templatePath = $templatePathHtml;
        } else {
            http_response_code(404);
            include $notFoundPath;
            return;
        }

        $layoutPath = null;
        if ($layout) {
            if (file_exists($layoutPathPhp)) {
                $layoutPath = $layoutPathPhp;
            } elseif (file_exists($layoutPathHtml)) {
                $layoutPath = $layoutPathHtml;
            }
        }

        extract($variables, EXTR_SKIP);

        ob_start();
        include $templatePath;
        $content = (string) ob_get_clean();

        $scripts = $scripts ?? [];
        $styles = $styles ?? [];

        if ($layout && $layoutPath && file_exists($layoutPath)) {
            if (str_ends_with($layoutPath, '.php')) {
                include $layoutPath;
                return;
            }

            $layoutHtml = (string) file_get_contents($layoutPath);

            $stylesHtml = '';
            foreach ($styles as $s) {
                $stylesHtml .= '<link rel="stylesheet" href="' . htmlspecialchars($s, ENT_QUOTES) . '">';
            }

            $scriptsHtml = '';
            foreach ($scripts as $sc) {
                $scriptsHtml .= '<script src="' . htmlspecialchars($sc, ENT_QUOTES) . '"></script>';
            }

            $navHtml = '';
            $userHeaderHtml = '';
            $appName = htmlspecialchars(Env::get('APP_NAME', 'MindGarden'), ENT_QUOTES);
            $theme = $_SESSION['theme'] ?? 'light';

            if (isset($user) && $user !== null) {
                $role = $user->getRole();
                $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
                $isActive = static function (string $path) use ($currentPath): string {
                    return str_starts_with($currentPath, $path) ? 'active' : '';
                };

                $navHtml .= '<nav class="app-nav">';
                if ($role === User::ROLE_PATIENT) {
                    $navHtml .= '<a class="app-nav__item ' . $isActive('/patient/dashboard') . '" href="/patient/dashboard">Dashboard</a>';
                    $navHtml .= '<a class="app-nav__item ' . $isActive('/patient/emotions') . '" href="/patient/emotions">Emocje</a>';
                    $navHtml .= '<a class="app-nav__item ' . $isActive('/patient/habits') . '" href="/patient/habits">Nawyki</a>';
                    $navHtml .= '<a class="app-nav__item ' . $isActive('/patient/history') . '" href="/patient/history">Historia</a>';
                    $navHtml .= '<a class="app-nav__item ' . $isActive('/patient/chat') . '" href="/patient/chat">Chat</a>';
                } elseif ($role === User::ROLE_PSYCHOLOGIST) {
                    $navHtml .= '<a class="app-nav__item ' . $isActive('/psychologist/dashboard') . '" href="/psychologist/dashboard">Pacjenci</a>';
                    $navHtml .= '<a class="app-nav__item ' . $isActive('/psychologist/analysis') . '" href="/psychologist/analysis">Analiza</a>';
                    $navHtml .= '<a class="app-nav__item ' . $isActive('/psychologist/chat') . '" href="/psychologist/chat">Chat</a>';
                    $navHtml .= '<a class="app-nav__item ' . $isActive('/psychologist/settings') . '" href="/psychologist/settings">Ustawienia</a>';
                } elseif ($role === User::ROLE_ADMIN) {
                    $navHtml .= '<a class="app-nav__item active" href="/admin/dashboard">Administrator</a>';
                }
                $navHtml .= '</nav>';

                $userHeaderHtml = '<div class="app-header__user"><span>' . htmlspecialchars($user->getFullName(), ENT_QUOTES) . '</span>' .
                    '<form method="post" action="/logout"><button type="submit" class="button button--secondary">Wyloguj</button></form></div>';
            }

            $replacements = [
                '<!--__THEME__*/' => htmlspecialchars($theme, ENT_QUOTES),
                '<!--__APP_NAME__*/' => $appName,
                '<!--__STYLES__-->' => $stylesHtml,
                '<!--__NAV__-->' => $navHtml,
                '<!--__USER_HEADER__-->' => $userHeaderHtml,
                '<!--__CONTENT__-->' => $content,
                '<!--__YEAR__*/' => date('Y'),
                '<!--__SCRIPTS__-->' => $scriptsHtml,
            ];

            $out = strtr($layoutHtml, $replacements);
            echo $out;
            return;
        }

        echo $content;
    }

    protected function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    protected function setFlash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    protected function getFlash(string $key, bool $remove = true): ?string
    {
        if (!isset($_SESSION['flash'][$key])) {
            return null;
        }

        $message = $_SESSION['flash'][$key];

        if ($remove) {
            unset($_SESSION['flash'][$key]);
        }

        return $message;
    }

    protected function clearFlash(): void
    {
        unset($_SESSION['flash']);
    }

    protected function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json') || $this->isAjax();
    }

    protected function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }

    public function authorize(array $roles = []): void
    {
        if (!$this->auth->check()) {
            $this->redirect('/login');
        }

        if ($roles && !in_array($this->auth->role(), $roles, true)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Brak dostępu']);
            exit;
        }
    }
}