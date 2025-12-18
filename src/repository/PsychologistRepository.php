<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';

final class PsychologistRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }


    public function assignedPatients(int $psychologistUserId): array
    {
        $sql = 'SELECT * FROM v_psychologist_patient_overview WHERE psychologist_id = (
            SELECT id FROM psychologists WHERE user_id = :user_id
        )';
        $statement = $this->db->prepare($sql);
        $statement->execute(['user_id' => $psychologistUserId]);
        return $statement->fetchAll() ?: [];
    }


    public function patientEmotionTrend(int $psychologistUserId, int $patientId, string $mode = 'daily', ?string $yearMonth = null, ?string $anchorDate = null): array
    {
        $limit = null;
        $params = [
            'psychologist_user_id' => $psychologistUserId,
            'patient_id' => $patientId,
        ];

        $monthWhereForM = '';
        $monthWhereForM2 = '';

        if ($anchorDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchorDate)) {
            try {
                $anchor = new DateTimeImmutable($anchorDate);
                switch ($mode) {
                    case 'weekly':
                        $start = new DateTimeImmutable($anchor->format('Y-m-d'));
                        $start = $start->modify('monday this week');
                        $end = $start->modify('+7 days'); 
                        break;
                    case 'monthly':
                        $start = new DateTimeImmutable($anchor->format('Y-m-01'));
                        $end = $start->modify('first day of next month');
                        break;
                    case 'yearly':
                        $start = new DateTimeImmutable($anchor->format('Y-01-01'));
                        $end = new DateTimeImmutable(((int)$anchor->format('Y') + 1) . '-01-01');
                        break;
                    case 'daily':
                    default:
                        $start = new DateTimeImmutable($anchor->format('Y-m-d'));
                        $end = $start->modify('+1 day');
                        break;
                }

                $params['start_date'] = $start->format('Y-m-d');
                $params['end_date'] = $end->format('Y-m-d');
                $monthWhereForM = ' AND m.mood_date >= :start_date AND m.mood_date < :end_date';
                $monthWhereForM2 = ' AND m2.mood_date >= :start_date AND m2.mood_date < :end_date';
            } catch (Exception $e) {
            }
        } elseif ($yearMonth !== null && preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            try {
                $start = new DateTimeImmutable($yearMonth . '-01');
                $end = $start->modify('first day of next month');
                $params['start_date'] = $start->format('Y-m-d');
                $params['end_date'] = $end->format('Y-m-d');
                $monthWhereForM = ' AND m.mood_date >= :start_date AND m.mood_date < :end_date';
                $monthWhereForM2 = ' AND m2.mood_date >= :start_date AND m2.mood_date < :end_date';
            } catch (Exception $e) {
            }
        }

        if ($mode === 'yearly') {
            $sql = "SELECT t.period, ROUND(t.average_mood::numeric, 2) AS average_mood, ROUND(t.average_intensity::numeric, 2) AS average_intensity, d.dominant_category FROM (";
            $sql .= "  SELECT date_trunc('month', m.mood_date)::date AS period, m.patient_id, AVG(m.mood_level) AS average_mood, AVG(m.intensity) AS average_intensity FROM moods m JOIN patient_psychologist pp ON pp.patient_id = m.patient_id JOIN psychologists psy ON pp.psychologist_id = psy.id WHERE psy.user_id = :psychologist_user_id AND m.patient_id = :patient_id";
            $sql .= $monthWhereForM;
            $sql .= " GROUP BY period, m.patient_id ) t LEFT JOIN ( SELECT DISTINCT ON (period, patient_id) period, patient_id, category_name AS dominant_category FROM ( SELECT date_trunc('month', m2.mood_date)::date AS period, m2.patient_id, ec.name AS category_name, COUNT(*) AS cnt FROM moods m2 JOIN emotion_categories ec ON ec.id = m2.emotion_category_id WHERE m2.patient_id = :patient_id";
            $sql .= $monthWhereForM2;
            $sql .= " GROUP BY period, m2.patient_id, ec.name ) sub ORDER BY period, patient_id, cnt DESC ) d ON d.period = t.period AND d.patient_id = t.patient_id ORDER BY t.period DESC";
            if ($limit !== null) {
                $sql .= ' LIMIT ' . (int) $limit;
            }
        } else {
            $sql = <<<SQL
SELECT m.mood_date::date AS period,
       ROUND(AVG(m.mood_level)::numeric, 2) AS average_mood,
       ROUND(AVG(m.intensity)::numeric, 2) AS average_intensity,
       (
           SELECT ec.name
           FROM moods inner_m
           JOIN emotion_categories ec ON ec.id = inner_m.emotion_category_id
           WHERE inner_m.patient_id = m.patient_id
             AND inner_m.mood_date = m.mood_date
           GROUP BY ec.name
           ORDER BY COUNT(*) DESC
           LIMIT 1
       ) AS dominant_category
FROM moods m
JOIN patient_psychologist pp ON pp.patient_id = m.patient_id
JOIN psychologists psy ON pp.psychologist_id = psy.id
WHERE psy.user_id = :psychologist_user_id
  AND m.patient_id = :patient_id
  {$monthWhereForM}
GROUP BY period, m.patient_id
ORDER BY period DESC
SQL;
            if ($limit !== null) {
                $sql .= ' LIMIT ' . (int) $limit;
            }
        }

        try {
            error_log('patientEmotionTrend SQL: ' . $sql);
            $statement = $this->db->prepare($sql);
            $statement->execute($params);

            $rows = $statement->fetchAll() ?: [];

            $result = [];
            foreach ($rows as $r) {
                $result[] = [
                    'date' => $r['period'],
                    'average_mood' => $r['average_mood'],
                    'average_intensity' => $r['average_intensity'],
                    'dominant_category' => $r['dominant_category'],
                ];
            }

            return $result;
        } catch (PDOException $e) {
            error_log('SQL Error in patientEmotionTrend: ' . $e->getMessage());
            throw new RuntimeException('Nie udało się pobrać danych analizy: ' . $e->getMessage(), 0, $e);
        }
    }

    public function exportPatientMoodCsv(int $psychologistUserId, int $patientId, string $path): string
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
            JOIN patient_psychologist pp ON pp.patient_id = m.patient_id
            JOIN psychologists psy ON psy.id = pp.psychologist_id
            WHERE psy.user_id = :psychologist_user_id
              AND m.patient_id = :patient_id
            ORDER BY m.mood_date DESC
        SQL;

        $statement = $this->db->prepare($sql);
        $statement->execute([
            'psychologist_user_id' => $psychologistUserId,
            'patient_id' => $patientId,
        ]);

        $rows = $statement->fetchAll();

        if ($rows === []) {
            throw new RuntimeException('Brak danych do eksportu.');
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
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
}

