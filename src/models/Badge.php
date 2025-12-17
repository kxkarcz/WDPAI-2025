<?php

declare(strict_types=1);

final class Badge
{
    public function __construct(
        private int $id,
        private int $patientId,
        private string $code,
        private string $label,
        private ?string $description,
        private DateTimeImmutable $awardedAt
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPatientId(): int
    {
        return $this->patientId;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getAwardedAt(): DateTimeImmutable
    {
        return $this->awardedAt;
    }
}

