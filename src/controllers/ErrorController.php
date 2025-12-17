<?php

declare(strict_types=1);

final class ErrorController extends AppController
{
    public function notFound(): void
    {
        http_response_code(404);
        $this->render('errors/404');
    }
}

