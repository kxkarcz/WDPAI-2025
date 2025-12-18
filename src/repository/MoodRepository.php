<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/MoodEntry.php';
require_once __DIR__ . '/EmotionRepository.php';

final class MoodRepository
{
    private PDO $db;
    private EmotionRepository $emotions;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->emotions = new EmotionRepository();
    }

    public function create(
        int $patientId,
        DateTimeInterface $date,
        int $level,
        int $intensity,
        string $categorySlug,
        ?string $subcategorySlug,
        ?string $note
    ): MoodEntry {
        $category = $this->emotions->findCategoryBySlug($categorySlug);
        if ($category === null) {
            throw new InvalidArgumentException('Nieprawidłowa kategoria emocji.');
        }

        $subcategoryId = null;
        if ($subcategorySlug !== null && $subcategorySlug !== '') {
            $subcategory = $this->emotions->findSubcategoryBySlug($subcategorySlug);
            if ($subcategory === null || $subcategory->getCategoryId() !== $category->getId()) {
                throw new InvalidArgumentException('Nieprawidłowa rozbudowana emocja.');
            }
            $subcategoryId = $subcategory->getId();
        }

        $statement = $this->db->prepare(
            <<<SQL
                INSERT INTO moods (patient_id, mood_date, mood_level, intensity, emotion_category_id, emotion_subcategory_id, note)
                VALUES (:patient_id, :mood_date, :mood_level, :intensity, :category_id, :subcategory_id, :note)
                RETURNING id
            SQL
        );

        $statement->execute([
            'patient_id' => $patientId,
            'mood_date' => $date->format('Y-m-d'),
            'mood_level' => $level,
            'intensity' => $intensity,
            'category_id' => $category->getId(),
            'subcategory_id' => $subcategoryId,
            'note' => $note,
        ]);

        $id = (int) $statement->fetchColumn();

        return $this->findById($id) ?? throw new RuntimeException('Nie udało się utworzyć wpisu nastroju.');
    }

    public function findById(int $id): ?MoodEntry
    {
        $statement = $this->db->prepare(
            <<<SQL
                SELECT m.id,
                       m.patient_id,
                       m.mood_date,
                       m.mood_level,
                       m.intensity,
                       ec.slug   AS category_slug,
                       ec.name   AS category_name,
                       es.slug   AS subcategory_slug,
                       es.name   AS subcategory_name,
                       m.note
                FROM moods m
                JOIN emotion_categories ec ON ec.id = m.emotion_category_id
                LEFT JOIN emotion_subcategories es ON es.id = m.emotion_subcategory_id
                WHERE m.id = :id
                LIMIT 1
            SQL
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ? $this->mapMood($row) : null;
    }

    public function timeline(int $patientId, int $limit = 14): array
    {
        $sql = <<<SQL
            SELECT m.id,
                   m.patient_id,
                   m.mood_date,
                   m.mood_level,
                   m.intensity,
                   ec.slug   AS category_slug,
                   ec.name   AS category_name,
                   es.slug   AS subcategory_slug,
                   es.name   AS subcategory_name,
                   m.note
            FROM moods m
            JOIN emotion_categories ec ON ec.id = m.emotion_category_id
            LEFT JOIN emotion_subcategories es ON es.id = m.emotion_subcategory_id
            WHERE m.patient_id = :patient_id
            ORDER BY m.mood_date DESC, m.created_at DESC
            LIMIT :limit
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->bindValue('patient_id', $patientId, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): MoodEntry => $this->mapMood($row), $statement->fetchAll());
    }

    public function distribution(int $patientId, int $days = 30): array
    {
        $sql = <<<SQL
            SELECT ec.slug AS category_slug,
                   ec.name AS category_name,
                   COUNT(*) AS total,
                   ROUND(AVG(m.intensity)::numeric, 2) AS average_intensity
            FROM moods m
            JOIN emotion_categories ec ON ec.id = m.emotion_category_id
            WHERE patient_id = :patient_id
              AND m.mood_date >= CURRENT_DATE - (:days || ' days')::interval
            GROUP BY ec.slug, ec.name
            ORDER BY total DESC
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute([
            'patient_id' => $patientId,
            'days' => $days,
        ]);

        return $statement->fetchAll();
    }

    public function trend(int $patientId, int $days = 14): array
    {
        $sql = <<<SQL
            SELECT m.mood_date::date AS date,
                   ROUND(AVG(m.mood_level)::numeric, 2) AS average_mood,
                   ROUND(AVG(m.intensity)::numeric, 2) AS average_intensity
            FROM moods m
            WHERE patient_id = :patient_id
              AND m.mood_date >= CURRENT_DATE - (:days || ' days')::interval
            GROUP BY m.mood_date
            ORDER BY m.mood_date
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute([
            'patient_id' => $patientId,
            'days' => $days,
        ]);

        return $statement->fetchAll();
    }

    public function exportCsvForPatient(int $patientId, string $path): string
    {
        $sql = <<<SQL
            SELECT m.mood_date,
                   m.mood_level,
                   m.intensity,
                   ec.name AS category_name,
                   es.name AS subcategory_name,
                   m.note
            FROM moods m
            JOIN emotion_categories ec ON ec.id = m.emotion_category_id
            LEFT JOIN emotion_subcategories es ON es.id = m.emotion_subcategory_id
            WHERE m.patient_id = :patient_id
            ORDER BY m.mood_date DESC
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute(['patient_id' => $patientId]);
        $rows = $statement->fetchAll();

        if ($rows === []) {
            throw new RuntimeException('Brak wpisów do eksportu.');
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Nie można utworzyć katalogu eksportu.');
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new RuntimeException('Nie można utworzyć pliku CSV.');
        }

        fputcsv($handle, ['Data', 'Poziom nastroju', 'Intensywność', 'Kategoria emocji', 'Szczegółowa emocja', 'Notatka']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['mood_date'],
                $row['mood_level'],
                $row['intensity'],
                $row['category_name'],
                $row['subcategory_name'],
                $row['note'],
            ]);
        }

        fclose($handle);

        return $path;
    }

    private function mapMood(array $row): MoodEntry
    {
        return new MoodEntry(
            (int) $row['id'],
            (int) $row['patient_id'],
            new DateTimeImmutable($row['mood_date']),
            (int) $row['mood_level'],
            (int) $row['intensity'],
            $row['category_slug'],
            $row['category_name'],
            $row['subcategory_slug'] ?? null,
            $row['subcategory_name'] ?? null,
            $row['note'] ?? null
        );
    }
}

