<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Habit.php';
require_once __DIR__ . '/../models/HabitLog.php';

final class HabitRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function listByPatient(int $patientId): array
    {
        $sql = <<<SQL
            SELECT id, patient_id, name, description, frequency_goal, created_at
            FROM habits
            WHERE patient_id = :patient_id
            ORDER BY created_at DESC
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute(['patient_id' => $patientId]);

        return array_map(fn (array $row): Habit => $this->mapHabit($row), $statement->fetchAll());
    }

    public function listWithProgress(int $patientId): array
    {
        $sql = <<<SQL
            SELECT h.id,
                   h.name,
                   h.description,
                   h.frequency_goal,
                   h.created_at,
                   COALESCE(SUM(CASE WHEN hl.completed THEN 1 ELSE 0 END), 0) AS completed_count,
                   COUNT(hl.id) AS total_logs
            FROM habits h
            LEFT JOIN habit_logs hl ON hl.habit_id = h.id
               AND hl.log_date >= CURRENT_DATE - INTERVAL '14 days'
            WHERE h.patient_id = :patient_id
            GROUP BY h.id
            ORDER BY h.created_at DESC
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute(['patient_id' => $patientId]);

        return $statement->fetchAll();
    }

    public function findOne(int $habitId): ?Habit
    {
        $sql = <<<SQL
            SELECT id, patient_id, name, description, frequency_goal, created_at
            FROM habits
            WHERE id = :habit_id
            LIMIT 1
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute(['habit_id' => $habitId]);
        $row = $statement->fetch();

        return $row ? $this->mapHabit($row) : null;
    }

    public function create(int $patientId, string $name, ?string $description, int $frequencyGoal): Habit
    {
        $sql = <<<SQL
            INSERT INTO habits (patient_id, name, description, frequency_goal)
            VALUES (:patient_id, :name, :description, :frequency_goal)
            RETURNING id, patient_id, name, description, frequency_goal, created_at
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute([
            'patient_id' => $patientId,
            'name' => $name,
            'description' => $description,
            'frequency_goal' => $frequencyGoal,
        ]);

        $row = $statement->fetch();

        return $this->mapHabit($row);
    }

    public function logCompletedHabit(int $habitId, DateTimeInterface $date, bool $completed, ?int $moodLevel, ?string $note): HabitLog
    {
        $sql = <<<SQL
            INSERT INTO habit_logs (habit_id, log_date, completed, mood_level, note)
            VALUES (:habit_id, :log_date, :completed, :mood_level, :note)
            RETURNING id, habit_id, log_date, completed, mood_level, note
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute([
            'habit_id' => $habitId,
            'log_date' => $date->format('Y-m-d'),
            'completed' => $completed,
            'mood_level' => $moodLevel,
            'note' => $note,
        ]);

        $row = $statement->fetch();

        return $this->mapHabitLog($row);
    }

    public function streaks(int $patientId): array
    {
        $sql = <<<SQL
            SELECT h.id,
                   h.name,
                   calculate_patient_streak(h.patient_id) AS streak_length
            FROM habits h
            WHERE h.patient_id = :patient_id
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute(['patient_id' => $patientId]);

        return $statement->fetchAll();
    }

    public function logsByPatient(int $patientId, int $limit = 30): array
    {
        $sql = <<<SQL
            SELECT hl.id,
                   hl.habit_id,
                   hl.log_date,
                   hl.completed,
                   hl.mood_level,
                   hl.note,
                   hl.created_at,
                   h.name AS habit_name
            FROM habit_logs hl
            JOIN habits h ON h.id = hl.habit_id
            WHERE h.patient_id = :patient_id
            ORDER BY hl.log_date DESC, hl.created_at DESC
            LIMIT :limit
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->bindValue('patient_id', $patientId, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    private function mapHabit(array $row): Habit
    {
        return new Habit(
            (int) $row['id'],
            (int) $row['patient_id'],
            $row['name'],
            $row['description'] ?? null,
            (int) $row['frequency_goal'],
            new DateTimeImmutable($row['created_at'])
        );
    }

    private function mapHabitLog(array $row): HabitLog
    {
        return new HabitLog(
            (int) $row['id'],
            (int) $row['habit_id'],
            new DateTimeImmutable($row['log_date']),
            (bool) $row['completed'],
            isset($row['mood_level']) ? (int) $row['mood_level'] : null,
            $row['note'] ?? null
        );
    }
}

