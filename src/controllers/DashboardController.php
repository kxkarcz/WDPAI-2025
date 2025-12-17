<?php

declare(strict_types=1);

final class DashboardController extends AppController
{
    public function index(): void
    {
        if (!$this->auth->check()) {
            $this->redirect('/login');
        }

        switch ($this->auth->role()) {
            case User::ROLE_ADMIN:
                $this->redirect('/admin/dashboard');
                break;
            case User::ROLE_PSYCHOLOGIST:
                $this->redirect('/psychologist/dashboard');
                break;
            case User::ROLE_PATIENT:
                $this->redirect('/patient/dashboard');
                break;
            default:
                http_response_code(403);
                $this->render('errors/403', ['title' => 'Brak dostępu']);
        }
    }
}
