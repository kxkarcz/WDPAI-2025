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
        ], 'auth/layout');
    }

    public function login(): void
    {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->setFlash('auth_error', 'Podaj adres e-mail oraz hasło.');
            $this->redirect('/login');
        }

        if (!$this->auth->attempt($email, $password)) {
            $this->setFlash('auth_error', 'Nieprawidłowe dane logowania.');
            $this->redirect('/login');
        }

        $this->redirect('/dashboard');
    }

    public function register(): void
    {
        $fullName = trim($_POST['full_name'] ?? '');
        $emailRaw = trim($_POST['email'] ?? '');
        $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $focusArea = trim($_POST['focus_area'] ?? '');
        $therapistCode = strtoupper(trim($_POST['therapist_code'] ?? ''));

        $this->setFlash('auth_old', [
            'full_name' => $fullName,
            'email' => $emailRaw,
            'focus_area' => $focusArea,
            'therapist_code' => $therapistCode,
        ]);

        if ($fullName === '' || $email === false || strlen($password) < 6 || $therapistCode === '') {
            $this->setFlash('auth_error', 'Uzupełnij wszystkie pola (hasło min. 6 znaków).');
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
