<?php

declare(strict_types=1);

final class EmotionSubcategory
{
    public function __construct(
        private int $id,
        private int $categoryId,
        private string $slug,
        private string $name,
        private ?string $description = null
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}


