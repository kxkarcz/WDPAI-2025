<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Badge.php';

final class BadgeRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function listByPatient(int $patientId): array
    {
        $sql = <<<SQL
            SELECT id, patient_id, code, label, description, awarded_at
            FROM badges
            WHERE patient_id = :patient_id
            ORDER BY awarded_at DESC
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute(['patient_id' => $patientId]);

        return array_map(fn (array $row): Badge => $this->mapBadge($row), $statement->fetchAll());
    }

    private function mapBadge(array $row): Badge
    {
        return new Badge(
            (int) $row['id'],
            (int) $row['patient_id'],
            $row['code'],
            $row['label'],
            $row['description'] ?? null,
            new DateTimeImmutable($row['awarded_at'])
        );
    }
}

