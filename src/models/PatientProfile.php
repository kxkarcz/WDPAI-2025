<?php

declare(strict_types=1);

final class PatientProfile
{
    public function __construct(
        private int $id,
        private int $userId,
        private ?int $primaryPsychologistId,
        private int $treeStage,
        private ?string $avatarUrl,
        private ?string $focusArea,
        private ?string $registrationCodeUsed
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

    public function getPrimaryPsychologistId(): ?int
    {
        return $this->primaryPsychologistId;
    }

    public function getTreeStage(): int
    {
        return $this->treeStage;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function getFocusArea(): ?string
    {
        return $this->focusArea;
    }

    public function getRegistrationCodeUsed(): ?string
    {
        return $this->registrationCodeUsed;
    }
}

