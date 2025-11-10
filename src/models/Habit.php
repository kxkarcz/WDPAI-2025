<?php

declare(strict_types=1);

final class Habit
{
    public function __construct(
        private int $id,
        private int $patientId,
        private string $name,
        private ?string $description,
        private int $frequencyGoal,
        private DateTimeImmutable $createdAt
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getFrequencyGoal(): int
    {
        return $this->frequencyGoal;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}

