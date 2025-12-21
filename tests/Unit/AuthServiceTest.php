<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use AuthService;

/**
 * Testy jednostkowe dla serwisu AuthService.
 */
final class AuthServiceTest extends TestCase
{
    private AuthService $authService;

    protected function setUp(): void
    {
        // Inicjalizacja sesji dla testów
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        
        $this->authService = new AuthService();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testCheckReturnsFalseWhenNotLoggedIn(): void
    {
        $this->assertFalse($this->authService->check());
    }

    public function testRoleReturnsNullWhenNotLoggedIn(): void
    {
        $this->assertNull($this->authService->role());
    }

    public function testUserReturnsNullWhenNotLoggedIn(): void
    {
        $this->assertNull($this->authService->user());
    }

    public function testCheckReturnsTrueWhenLoggedIn(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_role'] = 'patient';
        
        // Odtworzenie serwisu z nową sesją
        $authService = new AuthService();
        
        $this->assertTrue($authService->check());
    }

    public function testLogoutClearsSession(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_role'] = 'patient';
        
        $authService = new AuthService();
        $authService->logout();
        
        // Po wylogowaniu sesja powinna być pusta lub zresetowana
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }
}
