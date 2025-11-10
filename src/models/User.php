<?php

declare(strict_types=1);

final class User
{
    public const ROLE_ADMIN = 'administrator';
    public const ROLE_PSYCHOLOGIST = 'psychologist';
    public const ROLE_PATIENT = 'patient';

    public function __construct(
        private int $id,
        private string $email,
        private string $fullName,
        private string $role,
        private string $passwordHash,
        private string $status,
        private DateTimeImmutable $createdAt,
        private array $metadata = []
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAdministrator(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isPsychologist(): bool
    {
        return $this->role === self::ROLE_PSYCHOLOGIST;
    }

    public function isPatient(): bool
    {
        return $this->role === self::ROLE_PATIENT;
    }
}

