<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/EmotionCategory.php';
require_once __DIR__ . '/../models/EmotionSubcategory.php';

final class EmotionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(): array
    {
        $categories = $this->fetchCategories();
        $subcategories = $this->fetchSubcategories();

        $grouped = [];
        foreach ($subcategories as $subcategory) {
            $grouped[$subcategory->getCategoryId()][] = $subcategory;
        }

        return array_map(
            static function (EmotionCategory $category) use ($grouped): EmotionCategory {
                $subs = $grouped[$category->getId()] ?? [];
                return $category->withSubcategories($subs);
            },
            $categories
        );
    }

    public function findCategoryBySlug(string $slug): ?EmotionCategory
    {
        $statement = $this->db->prepare(
            'SELECT id, slug, name, accent_color FROM emotion_categories WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        return new EmotionCategory(
            (int) $row['id'],
            $row['slug'],
            $row['name'],
            $row['accent_color']
        );
    }

    public function findSubcategoryBySlug(string $slug): ?EmotionSubcategory
    {
        $statement = $this->db->prepare(
            'SELECT id, category_id, slug, name, description FROM emotion_subcategories WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        return new EmotionSubcategory(
            (int) $row['id'],
            (int) $row['category_id'],
            $row['slug'],
            $row['name'],
            $row['description'] ?? null
        );
    }

    private function fetchCategories(): array
    {
        $statement = $this->db->query('SELECT id, slug, name, accent_color FROM emotion_categories ORDER BY id');
        $rows = $statement->fetchAll();

        return array_map(
            static fn (array $row): EmotionCategory => new EmotionCategory(
                (int) $row['id'],
                $row['slug'],
                $row['name'],
                $row['accent_color']
            ),
            $rows ?: []
        );
    }

    private function fetchSubcategories(): array
    {
        $statement = $this->db->query(
            'SELECT id, category_id, slug, name, description FROM emotion_subcategories ORDER BY category_id, id'
        );
        $rows = $statement->fetchAll();

        return array_map(
            static fn (array $row): EmotionSubcategory => new EmotionSubcategory(
                (int) $row['id'],
                (int) $row['category_id'],
                $row['slug'],
                $row['name'],
                $row['description'] ?? null
            ),
            $rows ?: []
        );
    }
}


