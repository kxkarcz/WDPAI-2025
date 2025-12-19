<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use User;
use DateTimeImmutable;

/**
 * Testy jednostkowe dla modelu User.
 */
final class UserTest extends TestCase
{
    public function testUserCanBeCreatedFromArray(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01 12:00:00');
        
        $user = new User(
            1,
            'test@example.com',
            'Jan Kowalski',
            'patient',
            'hashed_password',
            'active',
            $createdAt
        );

        $this->assertSame(1, $user->getId());
        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertSame('Jan Kowalski', $user->getFullName());
        $this->assertSame('patient', $user->getRole());
        $this->assertSame('active', $user->getStatus());
    }

    public function testUserRoleConstants(): void
    {
        $this->assertSame('administrator', User::ROLE_ADMIN);
        $this->assertSame('psychologist', User::ROLE_PSYCHOLOGIST);
        $this->assertSame('patient', User::ROLE_PATIENT);
    }

    public function testUserIsActive(): void
    {
        $createdAt = new DateTimeImmutable('2025-01-01');
        
        $activeUser = new User(1, 'a@b.com', 'Test', 'patient', 'hash', 'active', $createdAt);
        $inactiveUser = new User(2, 'b@c.com', 'Test2', 'patient', 'hash', 'inactive', $createdAt);

        $this->assertSame('active', $activeUser->getStatus());
        $this->assertSame('inactive', $inactiveUser->getStatus());
    }

    public function testUserGettersReturnCorrectValues(): void
    {
        $createdAt = new DateTimeImmutable('2025-06-15 10:30:00');
        $metadata = ['patient_id' => 5, 'tree_stage' => 3];
        
        $user = new User(
            42,
            'psycholog@mindgarden.pl',
            'Dr Anna Nowak',
            User::ROLE_PSYCHOLOGIST,
            'bcrypt_hash_here',
            'active',
            $createdAt,
            $metadata
        );

        $this->assertSame(42, $user->getId());
        $this->assertSame('psycholog@mindgarden.pl', $user->getEmail());
        $this->assertSame('Dr Anna Nowak', $user->getFullName());
        $this->assertSame(User::ROLE_PSYCHOLOGIST, $user->getRole());
        $this->assertSame('bcrypt_hash_here', $user->getPasswordHash());
        $this->assertSame($createdAt, $user->getCreatedAt());
        $this->assertSame($metadata, $user->getMetadata());
    }
}
