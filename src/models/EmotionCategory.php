<?php

declare(strict_types=1);

final class EmotionCategory
{
    public function __construct(
        private int $id,
        private string $slug,
        private string $name,
        private string $accentColor,
        private array $subcategories = []
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAccentColor(): string
    {
        return $this->accentColor;
    }

    public function getSubcategories(): array
    {
        return $this->subcategories;
    }

    public function withSubcategories(array $subcategories): self
    {
        $clone = clone $this;
        $clone->subcategories = $subcategories;

        return $clone;
    }
}


