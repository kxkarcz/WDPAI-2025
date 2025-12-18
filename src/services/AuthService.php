<?php

declare(strict_types=1);

require_once __DIR__ . '/../repository/UserRepository.php';

final class AuthService
{
    private UserRepository $users;
    private ?User $cachedUser = null;

    public function __construct()
    {
        $this->bootSession();
        $this->users = new UserRepository();
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !$user->isActive()) {
            return false;
        }

        if (!password_verify($password, $user->getPasswordHash())) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user->getId();
        $_SESSION['user_role'] = $user->getRole();
        $_SESSION['user_name'] = $user->getFullName();

        $this->cachedUser = $user;

        return true;
    }

    public function logout(): void
    {
        $theme = $_SESSION['theme'] ?? null;

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        session_start();
        if ($theme !== null) {
            $_SESSION['theme'] = $theme;
        }
    }

    public function check(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['user_role']);
    }

    public function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    public function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public function user(): ?User
    {
        if (!$this->check()) {
            return null;
        }

        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $this->cachedUser = $this->users->findById((int) $_SESSION['user_id']);

        return $this->cachedUser;
    }

    private function bootSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $sessionName = Env::get('SESSION_NAME', 'mindgarden_session');
        if (session_name() !== $sessionName) {
            session_name($sessionName);
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

