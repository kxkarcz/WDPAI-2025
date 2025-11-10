<?php

declare(strict_types=1);

final class PsychologistProfile
{
    public function __construct(
        private int $id,
        private int $userId,
        private ?string $licenseNumber,
        private ?string $specialization,
        private string $inviteCode
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getLicenseNumber(): ?string
    {
        return $this->licenseNumber;
    }

    public function getSpecialization(): ?string
    {
        return $this->specialization;
    }

    public function getInviteCode(): string
    {
        return $this->inviteCode;
    }
}

