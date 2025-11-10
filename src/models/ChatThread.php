<?php

declare(strict_types=1);

final class ChatThread
{
    public function __construct(
        private int $id,
        private int $patientId,
        private int $psychologistId,
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

    public function getPsychologistId(): int
    {
        return $this->psychologistId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}


