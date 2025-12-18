<?php

declare(strict_types=1);

final class AdminController extends AppController
{
    private UserRepository $users;
    private PatientRepository $patients;

    public function __construct()
    {
        parent::__construct();
        $this->users = new UserRepository();
        $this->patients = new PatientRepository();
    }

    public function dashboard(): void
    {
        $this->authorize([User::ROLE_ADMIN]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $styles = ['/styles/admin.css'];
        $scripts = ['/scripts/admin-dashboard.js'];

        $success = $this->getFlash('success');
        $error = $this->getFlash('error');

        $users = $this->users->listUsersOverview();
        $psychologists = $this->users->listPsychologists();
        $patients = $this->patients->listAllWithAssignments();

        $psychologistsOptions = '<option value="">Brak</option>';
        foreach ($psychologists as $psychologist) {
            $psychologistsOptions .= sprintf(
                '<option value="%d">%s (kod: %s)</option>',
                (int) $psychologist['user_id'],
                htmlspecialchars($psychologist['full_name'], ENT_QUOTES),
                htmlspecialchars($psychologist['invite_code'], ENT_QUOTES)
            );
        }

        $patientsOptions = '';
        foreach ($patients as $patient) {
            $patientsOptions .= sprintf(
                '<option value="%d">%s</option>',
                (int) $patient['user_id'],
                htmlspecialchars($patient['full_name'], ENT_QUOTES)
            );
        }

        $usersRows = '';
        foreach ($users as $entry) {
            try {
                $created = (new DateTimeImmutable($entry['created_at']))->format('d.m.Y');
            } catch (Throwable $e) {
                $created = htmlspecialchars($entry['created_at'] ?? '', ENT_QUOTES);
            }

            $role = htmlspecialchars($entry['role'] ?? '', ENT_QUOTES);
            $status = htmlspecialchars($entry['status'] ?? '', ENT_QUOTES);

            $usersRows .= '<tr data-role="' . $role . '" data-status="' . $status . '">';
            $usersRows .= '<td>' . htmlspecialchars($entry['full_name'], ENT_QUOTES) . '<br><small>' . htmlspecialchars($entry['email'], ENT_QUOTES) . '</small></td>';
            $usersRows .= '<td>' . $role . '</td>';
            $usersRows .= '<td>' . $status . '</td>';
            $usersRows .= '<td>' . $created . '</td>';
            $usersRows .= '<td class="data-table__actions">';
            $usersRows .= '<form method="post" action="/admin/users/' . (int) $entry['id'] . '/delete" onsubmit="return confirm(\'Czy na pewno usunąć to konto?\');">';
            $usersRows .= '<button type="submit" class="button button--ghost">Usuń</button>';
            $usersRows .= '</form></td></tr>';
        }

        $successHtml = $success ? '<div class="alert alert--success" data-auto-dismiss="5000">' . htmlspecialchars($success, ENT_QUOTES) . '</div>' : '';
        $errorHtml = $error ? '<div class="alert alert--error" data-auto-dismiss="5000">' . htmlspecialchars($error, ENT_QUOTES) . '</div>' : '';

        $this->render('admin/dashboard', [
            'user' => $user,
            'usersTableHtml' => $usersRows,
            'psychologistsOptionsHtml' => $psychologistsOptions,
            'patientsOptionsHtml' => $patientsOptions,
            'successHtml' => $successHtml,
            'errorHtml' => $errorHtml,
            'styles' => $styles,
            'scripts' => $scripts,
        ]);
    }

    public function createUser(): void
    {
        $this->authorize([User::ROLE_ADMIN]);

        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $fullName = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        if ($email === false || $fullName === '' || strlen($password) < 6) {
            $this->setFlash('error', 'Podaj poprawne dane użytkownika (hasło min. 6 znaków).');
            $this->redirect('/admin/dashboard');
        }

        if (!in_array($role, [User::ROLE_ADMIN, User::ROLE_PSYCHOLOGIST, User::ROLE_PATIENT], true)) {
            $this->setFlash('error', 'Nieprawidłowa rola.');
            $this->redirect('/admin/dashboard');
        }

        $payload = [
            'email' => $email,
            'full_name' => $fullName,
            'password' => $password,
            'role' => $role,
            'status' => $_POST['status'] ?? 'active',
        ];

        if ($role === User::ROLE_PATIENT) {
            $payload['focus_area'] = trim($_POST['focus_area'] ?? '') ?: null;
            $payload['primary_psychologist_id'] = isset($_POST['primary_psychologist_id']) ? (int) $_POST['primary_psychologist_id'] : null;
        }

        if ($role === User::ROLE_PSYCHOLOGIST) {
            $payload['license_number'] = trim($_POST['license_number'] ?? '') ?: null;
            $payload['specialization'] = trim($_POST['specialization'] ?? '') ?: null;
        }

        try {
            $this->users->createUser($payload);
            $this->setFlash('success', 'Utworzono nowe konto.');
        } catch (Throwable $exception) {
            $this->setFlash('error', 'Nie udało się utworzyć konta: ' . $exception->getMessage());
        }

        $this->redirect('/admin/dashboard');
    }

    public function updateUser(string $userId): void
    {
        $this->authorize([User::ROLE_ADMIN]);

        $fullName = trim($_POST['full_name'] ?? '');
        $status = $_POST['status'] ?? null;

        $payload = [
            'full_name' => $fullName !== '' ? $fullName : null,
            'status' => $status ?: null,
        ];

        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 6) {
                $this->setFlash('error', 'Hasło musi mieć co najmniej 6 znaków.');
                $this->redirect('/admin/dashboard');
            }
            $payload['password'] = $_POST['password'];
        }

        $roleSpecific = [];
        if (isset($_POST['tree_stage'])) {
            $roleSpecific['tree_stage'] = (int) $_POST['tree_stage'];
        }
        if (isset($_POST['focus_area'])) {
            $roleSpecific['focus_area'] = trim($_POST['focus_area']) ?: null;
        }
        if (isset($_POST['primary_psychologist_id'])) {
            $roleSpecific['primary_psychologist_id'] = (int) $_POST['primary_psychologist_id'];
        }
        if (isset($_POST['license_number'])) {
            $roleSpecific['license_number'] = trim($_POST['license_number']) ?: null;
        }
        if (isset($_POST['specialization'])) {
            $roleSpecific['specialization'] = trim($_POST['specialization']) ?: null;
        }

        if ($roleSpecific !== []) {
            $payload['role_specific'] = $roleSpecific;
        }

        try {
            $this->users->updateUser((int) $userId, $payload);
            $this->setFlash('success', 'Zaktualizowano konto użytkownika.');
        } catch (Throwable $exception) {
            $this->setFlash('error', 'Aktualizacja użytkownika nie powiodła się: ' . $exception->getMessage());
        }

        $this->redirect('/admin/dashboard');
    }

    public function deleteUser(string $userId): void
    {
        $this->authorize([User::ROLE_ADMIN]);

        try {
            $this->users->deleteUser((int) $userId);
            $this->setFlash('success', 'Usunięto konto użytkownika.');
        } catch (Throwable $exception) {
            $this->setFlash('error', 'Nie udało się usunąć użytkownika: ' . $exception->getMessage());
        }

        $this->redirect('/admin/dashboard');
    }

    public function assignPatient(): void
    {
        $this->authorize([User::ROLE_ADMIN]);

        $patientUserId = isset($_POST['patient_user_id']) ? (int) $_POST['patient_user_id'] : 0;
        $psychologistUserId = isset($_POST['psychologist_user_id']) ? (int) $_POST['psychologist_user_id'] : 0;

        if ($patientUserId === 0 || $psychologistUserId === 0) {
            $this->setFlash('error', 'Wybierz pacjenta i psychologa.');
            $this->redirect('/admin/dashboard');
        }

        try {
            $this->users->assignPatientToPsychologistIds($patientUserId, $psychologistUserId);
            $this->setFlash('success', 'Przypisano pacjenta.');
        } catch (Throwable $exception) {
            $this->setFlash('error', 'Nie udało się przypisać pacjenta: ' . $exception->getMessage());
        }

        $this->redirect('/admin/dashboard');
    }
}

