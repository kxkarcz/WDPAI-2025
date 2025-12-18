<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/ChatThread.php';
require_once __DIR__ . '/../models/ChatMessage.php';

final class ChatRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function ensureThread(int $patientId, int $psychologistId): ChatThread
    {
        $statement = $this->db->prepare(
            <<<SQL
                INSERT INTO chat_threads (patient_id, psychologist_id)
                VALUES (:patient_id, :psychologist_id)
                ON CONFLICT (patient_id, psychologist_id) DO UPDATE
                    SET patient_id = EXCLUDED.patient_id
                RETURNING id, patient_id, psychologist_id, created_at
            SQL
        );

        $statement->execute([
            'patient_id' => $patientId,
            'psychologist_id' => $psychologistId,
        ]);

        return $this->mapThread($statement->fetch());
    }

    public function findThreadForPatient(int $patientId): ?ChatThread
    {
        $statement = $this->db->prepare(
            'SELECT id, patient_id, psychologist_id, created_at FROM chat_threads WHERE patient_id = :patient_id LIMIT 1'
        );
        $statement->execute(['patient_id' => $patientId]);
        $row = $statement->fetch();

        return $row ? $this->mapThread($row) : null;
    }

    public function findThreadForPsychologist(int $psychologistId, int $patientId): ?ChatThread
    {
        $statement = $this->db->prepare(
            'SELECT id, patient_id, psychologist_id, created_at FROM chat_threads WHERE patient_id = :patient_id AND psychologist_id = :psychologist_id LIMIT 1'
        );
        $statement->execute([
            'patient_id' => $patientId,
            'psychologist_id' => $psychologistId,
        ]);

        $row = $statement->fetch();

        return $row ? $this->mapThread($row) : null;
    }

    public function fetchMessages(int $threadId, ?int $afterId = null, int $limit = 50): array
    {
        $sql = <<<SQL
            SELECT id, thread_id, sender_user_id, body, created_at
            FROM chat_messages
            WHERE thread_id = :thread_id
        SQL;

        $params = ['thread_id' => $threadId];

        if ($afterId !== null) {
            $sql .= ' AND id > :after_id';
            $params['after_id'] = $afterId;
        }

        $sql .= ' ORDER BY created_at ASC, id ASC LIMIT :limit';

        $statement = $this->db->prepare($sql);
        $statement->bindValue('thread_id', $threadId, PDO::PARAM_INT);
        if ($afterId !== null) {
            $statement->bindValue('after_id', $afterId, PDO::PARAM_INT);
        }
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll();

        return array_map(fn (array $row): ChatMessage => $this->mapMessage($row), $rows ?: []);
    }

    public function appendMessage(int $threadId, int $senderUserId, string $body): ChatMessage
    {
        $statement = $this->db->prepare(
            <<<SQL
                INSERT INTO chat_messages (thread_id, sender_user_id, body)
                VALUES (:thread_id, :sender_user_id, :body)
                RETURNING id, thread_id, sender_user_id, body, created_at
            SQL
        );

        $statement->execute([
            'thread_id' => $threadId,
            'sender_user_id' => $senderUserId,
            'body' => $body,
        ]);

        return $this->mapMessage($statement->fetch());
    }

    public function userHasAccessToThread(int $userId, int $threadId): bool
    {
        $sql = <<<SQL
            SELECT 1
            FROM chat_threads ct
            JOIN patients p ON p.id = ct.patient_id
            JOIN psychologists psy ON psy.id = ct.psychologist_id
            WHERE ct.id = :thread_id
              AND (p.user_id = :user_id OR psy.user_id = :user_id)
            LIMIT 1
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute([
            'thread_id' => $threadId,
            'user_id' => $userId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    private function mapThread(array $row): ChatThread
    {
        return new ChatThread(
            (int) $row['id'],
            (int) $row['patient_id'],
            (int) $row['psychologist_id'],
            new DateTimeImmutable($row['created_at'])
        );
    }

    private function mapMessage(array $row): ChatMessage
    {
        return new ChatMessage(
            (int) $row['id'],
            (int) $row['thread_id'],
            (int) $row['sender_user_id'],
            $row['body'],
            new DateTimeImmutable($row['created_at'])
        );
    }
}


