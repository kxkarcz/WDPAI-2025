<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Env.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PatientProfile.php';
require_once __DIR__ . '/../models/PsychologistProfile.php';
require_once __DIR__ . '/ChatRepository.php';

final class UserRepository
{
    private PDO $db;
    private ChatRepository $chat;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->chat = new ChatRepository();
    }

    public function findByEmail(string $email): ?User
    {
        $sql = <<<SQL
            SELECT u.*,
                   p.id           AS patient_id,
                   p.tree_stage   AS patient_tree_stage,
                   p.focus_area   AS patient_focus_area,
                   p.avatar_url   AS patient_avatar_url,
                   p.registration_code_used,
                   p.primary_psychologist_id,
                   psy.id         AS psychologist_id,
                   psy.license_number,
                   psy.specialization,
                   psy.invite_code
            FROM users u
            LEFT JOIN patients p ON p.user_id = u.id
            LEFT JOIN psychologists psy ON psy.user_id = u.id
            WHERE LOWER(u.email) = LOWER(:email)
            LIMIT 1
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute(['email' => $email]);
        $row = $statement->fetch();

        return $row ? $this->hydrateUser($row) : null;
    }

    public function findById(int $id): ?User
    {
        $sql = <<<SQL
            SELECT u.*,
                   p.id           AS patient_id,
                   p.tree_stage   AS patient_tree_stage,
                   p.focus_area   AS patient_focus_area,
                   p.avatar_url   AS patient_avatar_url,
                   p.registration_code_used,
                   p.primary_psychologist_id,
                   psy.id         AS psychologist_id,
                   psy.license_number,
                   psy.specialization,
                   psy.invite_code
            FROM users u
            LEFT JOIN patients p ON p.user_id = u.id
            LEFT JOIN psychologists psy ON psy.user_id = u.id
            WHERE u.id = :id
            LIMIT 1
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ? $this->hydrateUser($row) : null;
    }

    public function allByRole(string $role): array
    {
        $sql = <<<SQL
            SELECT u.* FROM users u
            WHERE u.role = :role
            ORDER BY u.full_name
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute(['role' => $role]);

        return array_map(fn (array $row): User => $this->hydrateUser($row), $statement->fetchAll());
    }

    public function createUser(array $payload): User
    {
        $this->db->beginTransaction();

        try {
            $passwordHash = password_hash($payload['password'], PASSWORD_BCRYPT);

            $userInsert = <<<SQL
                INSERT INTO users (email, full_name, role, password_hash, status)
                VALUES (:email, :full_name, :role, :password_hash, :status)
                RETURNING id
            SQL;

            $statement = $this->db->prepare($userInsert);
            $statement->execute([
                'email' => $payload['email'],
                'full_name' => $payload['full_name'],
                'role' => $payload['role'],
                'password_hash' => $passwordHash,
                'status' => $payload['status'] ?? 'active',
            ]);

            $userId = (int) $statement->fetchColumn();

            if ($payload['role'] === User::ROLE_PATIENT) {
                $primaryPsychologistId = null;
                if (!empty($payload['primary_psychologist_id'])) {
                    $primaryPsychologistId = $this->resolvePsychologistId((int) $payload['primary_psychologist_id']);
                    if ($primaryPsychologistId === null) {
                        throw new InvalidArgumentException('Nie znaleziono psychologa o podanym identyfikatorze.');
                    }
                }

                $patientInsert = <<<SQL
                    INSERT INTO patients (user_id, primary_psychologist_id, tree_stage, focus_area, avatar_url, registration_code_used)
                    VALUES (:user_id, :primary_psychologist_id, :tree_stage, :focus_area, :avatar_url, :registration_code_used)
                SQL;

                $this->db->prepare($patientInsert)->execute([
                    'user_id' => $userId,
                    'primary_psychologist_id' => $primaryPsychologistId,
                    'tree_stage' => $payload['tree_stage'] ?? 1,
                    'focus_area' => $payload['focus_area'] ?? null,
                    'avatar_url' => $payload['avatar_url'] ?? null,
                    'registration_code_used' => $payload['registration_code_used'] ?? null,
                ]);

                if ($primaryPsychologistId !== null) {
                    $this->assignPatientToPsychologistIds(
                        $userId,
                        (int) $payload['primary_psychologist_id']
                    );
                }
            }

            if ($payload['role'] === User::ROLE_PSYCHOLOGIST) {
                $inviteCode = $payload['invite_code'] ?? $this->generateUniqueInviteCode();
                $psychologistInsert = <<<SQL
                    INSERT INTO psychologists (user_id, license_number, specialization, invite_code)
                    VALUES (:user_id, :license_number, :specialization, :invite_code)
                SQL;

                $this->db->prepare($psychologistInsert)->execute([
                    'user_id' => $userId,
                    'license_number' => $payload['license_number'] ?? null,
                    'specialization' => $payload['specialization'] ?? null,
                    'invite_code' => $inviteCode,
                ]);
            }

            $this->db->commit();

            return $this->findById($userId) ?? throw new RuntimeException('Nie udało się utworzyć użytkownika.');
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function updateUser(int $userId, array $payload): User
    {
        $fields = [
            'full_name' => $payload['full_name'] ?? null,
            'status' => $payload['status'] ?? null,
        ];

        $set = [];
        $bind = ['id' => $userId];

        foreach ($fields as $column => $value) {
            if ($value === null) {
                continue;
            }
            $set[] = sprintf('%s = :%s', $column, $column);
            $bind[$column] = $value;
        }

        if (!empty($payload['password'])) {
            $set[] = 'password_hash = :password_hash';
            $bind['password_hash'] = password_hash($payload['password'], PASSWORD_BCRYPT);
        }

        if ($set !== []) {
            $sql = sprintf('UPDATE users SET %s WHERE id = :id', implode(', ', $set));
            $this->db->prepare($sql)->execute($bind);
        }

        if (!empty($payload['role_specific'])) {
            $this->updateRoleSpecific($userId, $payload['role_specific']);
        }

        return $this->findById($userId) ?? throw new RuntimeException('Aktualizacja użytkownika nie powiodła się.');
    }

    public function deleteUser(int $userId): void
    {
        $sql = 'DELETE FROM users WHERE id = :id';
        $this->db->prepare($sql)->execute(['id' => $userId]);
    }

    public function listUsersOverview(): array
    {
        $sql = <<<SQL
            SELECT u.id,
                   u.full_name,
                   u.email,
                   u.role,
                   u.status,
                   u.created_at
            FROM users u
            ORDER BY u.role, u.full_name
        SQL;

        return $this->db->query($sql)->fetchAll();
    }

    public function listPsychologists(): array
    {
        $sql = <<<SQL
            SELECT psy.id AS psychologist_id,
                   u.id   AS user_id,
                   u.full_name,
                   psy.specialization,
                   psy.invite_code
            FROM psychologists psy
            JOIN users u ON psy.user_id = u.id
            ORDER BY u.full_name
        SQL;

        return $this->db->query($sql)->fetchAll();
    }

    public function findPsychologistByInviteCode(string $code): ?array
    {
        $statement = $this->db->prepare(
            <<<SQL
                SELECT psy.id AS psychologist_id,
                       psy.invite_code,
                       u.id   AS user_id,
                       u.full_name,
                       u.email
                FROM psychologists psy
                JOIN users u ON u.id = psy.user_id
                WHERE UPPER(psy.invite_code) = UPPER(:code)
                LIMIT 1
            SQL
        );
        $statement->execute(['code' => $code]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    public function regenerateInviteCode(int $psychologistUserId): string
    {
        $psychologistId = $this->resolvePsychologistId($psychologistUserId);
        if ($psychologistId === null) {
            throw new InvalidArgumentException('Nie znaleziono psychologa.');
        }

        $code = $this->generateUniqueInviteCode();

        $this->db->prepare('UPDATE psychologists SET invite_code = :code WHERE id = :id')
            ->execute([
                'code' => $code,
                'id' => $psychologistId,
            ]);

        return $code;
    }

    public function detachPatientFromPsychologist(int $psychologistUserId, int $patientUserId): void
    {
        $patientId = $this->resolvePatientId($patientUserId);
        $psychologistId = $this->resolvePsychologistId($psychologistUserId);

        if ($patientId === null || $psychologistId === null) {
            throw new InvalidArgumentException('Nie udało się odłączyć pacjenta.');
        }

        $this->db->prepare('DELETE FROM patient_psychologist WHERE patient_id = :patient_id AND psychologist_id = :psychologist_id')
            ->execute([
                'patient_id' => $patientId,
                'psychologist_id' => $psychologistId,
            ]);

        $this->db->prepare(
            'UPDATE patients SET primary_psychologist_id = NULL WHERE id = :patient_id AND primary_psychologist_id = :psychologist_id'
        )->execute([
            'patient_id' => $patientId,
            'psychologist_id' => $psychologistId,
        ]);
    }

    public function assignPatientToPsychologistIds(int $patientUserId, int $psychologistUserId): void
    {
        $patientId = $this->resolvePatientId($patientUserId);
        $psychologistId = $this->resolvePsychologistId($psychologistUserId);

        if ($patientId === null || $psychologistId === null) {
            throw new InvalidArgumentException('Nie udało się przypisać pacjenta.');
        }

        $sql = <<<SQL
            INSERT INTO patient_psychologist (patient_id, psychologist_id)
            VALUES (:patient_id, :psychologist_id)
            ON CONFLICT DO NOTHING
        SQL;

        $this->db->prepare($sql)->execute([
            'patient_id' => $patientId,
            'psychologist_id' => $psychologistId,
        ]);

        $this->db->prepare('UPDATE patients SET primary_psychologist_id = :psychologist_id WHERE id = :patient_id')
            ->execute([
                'patient_id' => $patientId,
                'psychologist_id' => $psychologistId,
            ]);

        $this->chat->ensureThread($patientId, $psychologistId);
    }

    private function hydrateUser(array $row): User
    {
        $createdAt = isset($row['created_at'])
            ? new DateTimeImmutable($row['created_at'])
            : new DateTimeImmutable();

        $metadata = [];

        if (isset($row['patient_id'])) {
            $metadata['patient'] = new PatientProfile(
                (int) $row['patient_id'],
                (int) ($row['id'] ?? 0),
                isset($row['primary_psychologist_id']) ? (int) $row['primary_psychologist_id'] : null,
                isset($row['patient_tree_stage']) ? (int) $row['patient_tree_stage'] : 1,
                $row['patient_avatar_url'] ?? null,
                $row['patient_focus_area'] ?? null,
                $row['registration_code_used'] ?? null
            );
        }

        if (isset($row['psychologist_id'])) {
            $metadata['psychologist'] = new PsychologistProfile(
                (int) $row['psychologist_id'],
                (int) ($row['id'] ?? 0),
                $row['license_number'] ?? null,
                $row['specialization'] ?? null,
                $row['invite_code'] ?? 'N/A'
            );
        }

        return new User(
            (int) $row['id'],
            $row['email'],
            $row['full_name'],
            $row['role'],
            $row['password_hash'],
            $row['status'] ?? 'active',
            $createdAt,
            $metadata
        );
    }

    private function updateRoleSpecific(int $userId, array $payload): void
    {
        $user = $this->findById($userId);

        if ($user === null) {
            return;
        }

        if ($user->isPatient()) {
            $sql = <<<SQL
                UPDATE patients
                SET tree_stage = COALESCE(:tree_stage, tree_stage),
                    focus_area = COALESCE(:focus_area, focus_area),
                    avatar_url = COALESCE(:avatar_url, avatar_url)
                WHERE user_id = :user_id
            SQL;

            $this->db->prepare($sql)->execute([
                'tree_stage' => $payload['tree_stage'] ?? null,
                'focus_area' => $payload['focus_area'] ?? null,
                'avatar_url' => $payload['avatar_url'] ?? null,
                'user_id' => $userId,
            ]);

            if (!empty($payload['primary_psychologist_id'])) {
                $this->assignPatientToPsychologistIds(
                    $userId,
                    (int) $payload['primary_psychologist_id']
                );
            }
        }

        if ($user->isPsychologist()) {
            $sql = <<<SQL
                UPDATE psychologists
                SET license_number = COALESCE(:license_number, license_number),
                    specialization = COALESCE(:specialization, specialization)
                WHERE user_id = :user_id
            SQL;

            $this->db->prepare($sql)->execute([
                'license_number' => $payload['license_number'] ?? null,
                'specialization' => $payload['specialization'] ?? null,
                'user_id' => $userId,
            ]);
        }
    }

    private function resolvePatientId(int $userId): ?int
    {
        $statement = $this->db->prepare('SELECT id FROM patients WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $id = $statement->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function resolvePsychologistId(int $userId): ?int
    {
        $statement = $this->db->prepare('SELECT id FROM psychologists WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $id = $statement->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function generateUniqueInviteCode(int $length = 6): string
    {
        do {
            $code = strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(6))), 0, $length));
        } while ($this->inviteCodeExists($code));

        return $code;
    }

    private function inviteCodeExists(string $code): bool
    {
        $statement = $this->db->prepare('SELECT 1 FROM psychologists WHERE invite_code = :code LIMIT 1');
        $statement->execute(['code' => $code]);

        return (bool) $statement->fetchColumn();
    }
}

