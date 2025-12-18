<?php

declare(strict_types=1);

final class SecurityController extends AppController
{
    private UserRepository $users;

    public function __construct()
    {
        parent::__construct();
        $this->users = new UserRepository();
    }

    public function showLogin(): void
    {
        if ($this->auth->check()) {
            $this->redirect('/dashboard');
        }

        $error = $this->getFlash('auth_error');
        $success = $this->getFlash('auth_success');
        
        $errorHtml = $error ? '<div class="alert alert--error">' . htmlspecialchars($error) . '</div>' : '';
        $successHtml = $success ? '<div class="alert alert--success">' . htmlspecialchars($success) . '</div>' : '';

        $this->render('auth/login', [
            'errorHtml' => $errorHtml,
            'successHtml' => $successHtml,
            'csrfField' => $this->csrfField(),
        ], 'auth/layout');
    }

    public function showRegister(): void
    {
        if ($this->auth->check()) {
            $this->redirect('/dashboard');
        }

        $error = $this->getFlash('auth_error');
        $old = $this->getFlash('auth_old', false) ?? [];
        $code = $_GET['code'] ?? ($old['therapist_code'] ?? '');
        
        $errorHtml = $error ? '<div class="alert alert--error">' . htmlspecialchars($error) . '</div>' : '';

        $oldJson = json_encode($old, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->render('auth/register', [
            'errorHtml' => $errorHtml,
            'old' => $old,
            'oldJson' => $oldJson,
            'prefillCode' => $code,
            'csrfField' => $this->csrfField(),
        ], 'auth/layout');
    }

    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_TIME = 900; // 15 minutes in seconds

    public function login(): void
    {
        $this->requireCsrf();

        $attempts = $_SESSION['login_attempts'] ?? 0;
        $lockoutUntil = $_SESSION['login_lockout'] ?? 0;

        if ($lockoutUntil > time()) {
            $remainingMinutes = ceil(($lockoutUntil - time()) / 60);
            $this->setFlash('auth_error', "Zbyt wiele nieudanych prób. Spróbuj ponownie za {$remainingMinutes} min.");
            $this->redirect('/login');
        }

        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->setFlash('auth_error', 'Podaj adres e-mail oraz hasło.');
            $this->redirect('/login');
        }

        if (!$this->auth->attempt($email, $password)) {
            $_SESSION['login_attempts'] = $attempts + 1;

            if ($_SESSION['login_attempts'] >= self::MAX_LOGIN_ATTEMPTS) {
                $_SESSION['login_lockout'] = time() + self::LOCKOUT_TIME;
                $_SESSION['login_attempts'] = 0;
                $this->setFlash('auth_error', 'Zbyt wiele nieudanych prób. Konto zablokowane na 15 minut.');
            } else {
                $remaining = self::MAX_LOGIN_ATTEMPTS - $_SESSION['login_attempts'];
                $this->setFlash('auth_error', "Nieprawidłowe dane logowania. Pozostało prób: {$remaining}.");
            }
            $this->redirect('/login');
        }

        unset($_SESSION['login_attempts'], $_SESSION['login_lockout']);

        $this->redirect('/dashboard');
    }

    public function register(): void
    {
        $this->requireCsrf();

        $fullName = trim($_POST['full_name'] ?? '');
        $emailRaw = trim($_POST['email'] ?? '');
        $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $focusArea = trim($_POST['focus_area'] ?? '');
        $therapistCode = strtoupper(trim($_POST['therapist_code'] ?? ''));

        $fullName = mb_substr($fullName, 0, 100);
        $emailRaw = mb_substr($emailRaw, 0, 255);
        $focusArea = mb_substr($focusArea, 0, 200);
        $therapistCode = mb_substr($therapistCode, 0, 16);

        $this->setFlash('auth_old', [
            'full_name' => $fullName,
            'email' => $emailRaw,
            'focus_area' => $focusArea,
            'therapist_code' => $therapistCode,
        ]);

        $errors = [];
        if ($fullName === '' || mb_strlen($fullName) < 2) {
            $errors[] = 'imię i nazwisko (min. 2 znaki)';
        }
        if ($email === false || mb_strlen($emailRaw) > 255) {
            $errors[] = 'poprawny adres e-mail';
        }
        if (strlen($password) < 6 || strlen($password) > 72) {
            $errors[] = 'hasło (6-72 znaków)';
        }
        if ($therapistCode === '') {
            $errors[] = 'kod terapeuty';
        }

        if (!empty($errors)) {
            $this->setFlash('auth_error', 'Uzupełnij: ' . implode(', ', $errors) . '.');
            $this->redirect('/register');
        }

        $psychologist = $this->users->findPsychologistByInviteCode($therapistCode);
        if ($psychologist === null) {
            $this->setFlash('auth_error', 'Kod terapeuty jest nieprawidłowy lub wygasł.');
            $this->redirect('/register');
        }

        try {
            $user = $this->users->createUser([
                'email' => $email,
                'full_name' => $fullName,
                'password' => $password,
                'role' => User::ROLE_PATIENT,
                'status' => 'active',
                'focus_area' => $focusArea !== '' ? $focusArea : null,
                'primary_psychologist_id' => $psychologist['user_id'],
                'registration_code_used' => $therapistCode,
            ]);
        } catch (Throwable $exception) {
            $this->setFlash('auth_error', 'Rejestracja nie powiodła się: ' . $exception->getMessage());
            $this->redirect('/register');
        }

        $this->setFlash('auth_old', []);

        if ($this->auth->attempt($user->getEmail(), $password)) {
            $this->redirect('/patient/dashboard');
        }

        $this->setFlash('auth_success', 'Konto zostało utworzone. Zaloguj się.');
        $this->redirect('/login');
    }

    public function logout(): void
    {
        $this->auth->logout();
        $this->redirect('/login');
    }
}
