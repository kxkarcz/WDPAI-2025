<?php

declare(strict_types=1);

final class ErrorController extends AppController
{
    public function badRequest(): void
    {
        http_response_code(400);
        $this->render('errors/400', [], 'layout');
    }

    public function forbidden(): void
    {
        http_response_code(403);
        $this->render('errors/403', [], 'layout');
    }

    public function notFound(): void
    {
        http_response_code(404);
        $this->render('errors/404', [], 'layout');
    }

    public function serverError(): void
    {
        http_response_code(500);
        $this->render('errors/500', [], 'layout');
    }
}

