<?php

declare(strict_types=1);

final class MoodEntry
{
    public function __construct(
        private int $id,
        private int $patientId,
        private DateTimeImmutable $date,
        private int $level,
        private int $intensity,
        private string $categorySlug,
        private string $categoryName,
        private ?string $subcategorySlug,
        private ?string $subcategoryName,
        private ?string $note
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

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getIntensity(): int
    {
        return $this->intensity;
    }

    public function getCategorySlug(): string
    {
        return $this->categorySlug;
    }

    public function getCategoryName(): string
    {
        return $this->categoryName;
    }

    public function getSubcategorySlug(): ?string
    {
        return $this->subcategorySlug;
    }

    public function getSubcategoryName(): ?string
    {
        return $this->subcategoryName;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }
}

