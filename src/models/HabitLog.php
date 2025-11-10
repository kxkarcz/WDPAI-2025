<?php

declare(strict_types=1);

final class HabitLog
{
    public function __construct(
        private int $id,
        private int $habitId,
        private DateTimeImmutable $logDate,
        private bool $completed,
        private ?int $moodLevel,
        private ?string $note
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getHabitId(): int
    {
        return $this->habitId;
    }

    public function getLogDate(): DateTimeImmutable
    {
        return $this->logDate;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function getMoodLevel(): ?int
    {
        return $this->moodLevel;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }
}

