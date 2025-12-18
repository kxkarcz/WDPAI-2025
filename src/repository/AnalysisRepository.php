<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/AnalysisEntry.php';

final class AnalysisRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(
        int $psychologistId,
        int $patientId,
        string $title,
        string $content,
        DateTimeInterface $entryDate
    ): AnalysisEntry {
        $statement = $this->db->prepare(
            <<<SQL
                INSERT INTO analysis_entries (psychologist_id, patient_id, title, content, entry_date)
                VALUES (:psychologist_id, :patient_id, :title, :content, :entry_date)
                RETURNING id, psychologist_id, patient_id, title, content, entry_date, created_at, updated_at
            SQL
        );

        $statement->execute([
            'psychologist_id' => $psychologistId,
            'patient_id' => $patientId,
            'title' => $title,
            'content' => $content,
            'entry_date' => $entryDate->format('Y-m-d'),
        ]);

        $row = $statement->fetch();
        return $this->mapEntry($row);
    }

    public function update(
        int $entryId,
        int $psychologistId,
        string $title,
        string $content,
        DateTimeInterface $entryDate
    ): AnalysisEntry {
        $statement = $this->db->prepare(
            <<<SQL
                UPDATE analysis_entries
                SET title = :title,
                    content = :content,
                    entry_date = :entry_date,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND psychologist_id = :psychologist_id
                RETURNING id, psychologist_id, patient_id, title, content, entry_date, created_at, updated_at
            SQL
        );

        $statement->execute([
            'id' => $entryId,
            'psychologist_id' => $psychologistId,
            'title' => $title,
            'content' => $content,
            'entry_date' => $entryDate->format('Y-m-d'),
        ]);

        $row = $statement->fetch();
        if (!$row) {
            throw new RuntimeException('Wpis analizy nie został znaleziony.');
        }

        return $this->mapEntry($row);
    }

    public function delete(int $entryId, int $psychologistId): bool
    {
        $statement = $this->db->prepare(
            'DELETE FROM analysis_entries WHERE id = :id AND psychologist_id = :psychologist_id'
        );

        $statement->execute([
            'id' => $entryId,
            'psychologist_id' => $psychologistId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function findByPatient(int $psychologistId, int $patientId): array
    {
        $statement = $this->db->prepare(
            <<<SQL
                SELECT id, psychologist_id, patient_id, title, content, entry_date, created_at, updated_at
                FROM analysis_entries
                WHERE psychologist_id = :psychologist_id
                  AND patient_id = :patient_id
                ORDER BY entry_date DESC, created_at DESC
            SQL
        );

        $statement->execute([
            'psychologist_id' => $psychologistId,
            'patient_id' => $patientId,
        ]);

        $rows = $statement->fetchAll();
        return array_map(fn (array $row): AnalysisEntry => $this->mapEntry($row), $rows ?: []);
    }

    public function findByPatientBetween(int $psychologistId, int $patientId, string $startDate, string $endDate): array
    {
        $statement = $this->db->prepare(
            <<<SQL
                SELECT id, psychologist_id, patient_id, title, content, entry_date, created_at, updated_at
                FROM analysis_entries
                WHERE psychologist_id = :psychologist_id
                  AND patient_id = :patient_id
                  AND entry_date BETWEEN :start_date AND :end_date
                ORDER BY entry_date DESC, created_at DESC
            SQL
        );

        $statement->execute([
            'psychologist_id' => $psychologistId,
            'patient_id' => $patientId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $rows = $statement->fetchAll();
        return array_map(fn (array $row): AnalysisEntry => $this->mapEntry($row), $rows ?: []);
    }

    public function findOne(int $entryId, int $psychologistId): ?AnalysisEntry
    {
        $statement = $this->db->prepare(
            <<<SQL
                SELECT id, psychologist_id, patient_id, title, content, entry_date, created_at, updated_at
                FROM analysis_entries
                WHERE id = :id
                  AND psychologist_id = :psychologist_id
                LIMIT 1
            SQL
        );

        $statement->execute([
            'id' => $entryId,
            'psychologist_id' => $psychologistId,
        ]);

        $row = $statement->fetch();
        return $row ? $this->mapEntry($row) : null;
    }

    public function psychologistHasAccess(int $psychologistId, int $patientId): bool
    {
        $statement = $this->db->prepare(
            <<<SQL
                SELECT 1
                FROM patient_psychologist
                WHERE psychologist_id = :psychologist_id
                  AND patient_id = :patient_id
                LIMIT 1
            SQL
        );

        $statement->execute([
            'psychologist_id' => $psychologistId,
            'patient_id' => $patientId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    private function mapEntry(array $row): AnalysisEntry
    {
        return new AnalysisEntry(
            (int) $row['id'],
            (int) $row['psychologist_id'],
            (int) $row['patient_id'],
            $row['title'],
            $row['content'],
            new DateTimeImmutable($row['entry_date']),
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }
}

