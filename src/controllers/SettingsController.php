<?php

declare(strict_types=1);

final class SettingsController extends AppController
{
    public function toggleTheme(): void
    {
        $current = $_SESSION['theme'] ?? 'light';
        $next = $current === 'light' ? 'dark' : 'light';
        $_SESSION['theme'] = $next;

        $this->json([
            'status' => 'ok',
            'theme' => $next,
        ]);
    }
}

