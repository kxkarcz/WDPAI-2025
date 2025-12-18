<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/PatientProfile.php';

final class PatientRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findProfileByUserId(int $userId): ?PatientProfile
    {
        $sql = <<<SQL
            SELECT id,
                   user_id,
                   primary_psychologist_id,
                   tree_stage,
                   avatar_url,
                   focus_area,
                   registration_code_used
            FROM patients
            WHERE user_id = :user_id
            LIMIT 1
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();

        return $row ? $this->mapProfile($row) : null;
    }

    public function getPatientIdByUserId(int $userId): ?int
    {
        $statement = $this->db->prepare('SELECT id FROM patients WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $id = $statement->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public function updateTreeStage(int $patientId, int $stage): void
    {
        $statement = $this->db->prepare(
            'UPDATE patients SET tree_stage = :stage WHERE id = :patient_id'
        );
        $statement->execute([
            'stage' => $stage,
            'patient_id' => $patientId,
        ]);
    }


    public function dashboardSnapshot(int $patientId): array
    {
        $sql = 'SELECT * FROM v_patient_mood_summary WHERE patient_id = :patient_id';
        $statement = $this->db->prepare($sql);
        $statement->execute(['patient_id' => $patientId]);
        $summary = $statement->fetch() ?: [];

        $streakSql = 'SELECT calculate_patient_streak(:patient_id) AS current_streak';
        $streakStmt = $this->db->prepare($streakSql);
        $streakStmt->execute(['patient_id' => $patientId]);
        $streak = $streakStmt->fetchColumn();

        $summary['current_streak'] = (int)$streak;
        return $summary;
    }


    public function badgeProgression(int $patientId): array
    {
        $sql = <<<SQL
            SELECT DATE(awarded_at) AS date,
                   COUNT(*)         AS badges
            FROM badges
            WHERE patient_id = :patient_id
            GROUP BY DATE(awarded_at)
            ORDER BY DATE(awarded_at)
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute(['patient_id' => $patientId]);

        return $statement->fetchAll();
    }

    public function listAllWithAssignments(): array
    {
        $sql = <<<SQL
            SELECT p.id AS patient_id,
                   u.id AS user_id,
                   u.full_name,
                   u.email,
                   p.tree_stage,
                   p.focus_area,
                   psy_user.full_name AS psychologist_name
            FROM patients p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN psychologists psy ON psy.id = p.primary_psychologist_id
            LEFT JOIN users psy_user ON psy_user.id = psy.user_id
            ORDER BY u.full_name
        SQL;

        return $this->db->query($sql)->fetchAll();
    }

    public function assignmentDetails(int $patientId): ?array
    {
        $sql = <<<SQL
            SELECT psy.id AS psychologist_id,
                   psy_user.id AS psychologist_user_id,
                   psy_user.full_name AS psychologist_name,
                   psy_user.email AS psychologist_email,
                   psy.invite_code
            FROM patients p
            LEFT JOIN patient_psychologist pp ON pp.patient_id = p.id
            LEFT JOIN psychologists psy ON psy.id = pp.psychologist_id
            LEFT JOIN users psy_user ON psy_user.id = psy.user_id
            WHERE p.id = :patient_id
            LIMIT 1
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute(['patient_id' => $patientId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    private function mapProfile(array $row): PatientProfile
    {
        return new PatientProfile(
            (int) $row['id'],
            (int) $row['user_id'],
            isset($row['primary_psychologist_id']) ? (int) $row['primary_psychologist_id'] : null,
            (int) $row['tree_stage'],
            $row['avatar_url'] ?? null,
            $row['focus_area'] ?? null,
            $row['registration_code_used'] ?? null
        );
    }
}

