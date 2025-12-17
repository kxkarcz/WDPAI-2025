<?php

declare(strict_types=1);

final class AnalysisEntry
{
    public function __construct(
        private int $id,
        private int $psychologistId,
        private int $patientId,
        private string $title,
        private string $content,
        private DateTimeImmutable $entryDate,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPsychologistId(): int
    {
        return $this->psychologistId;
    }

    public function getPatientId(): int
    {
        return $this->patientId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getEntryDate(): DateTimeImmutable
    {
        return $this->entryDate;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

