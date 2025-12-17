<?php

declare(strict_types=1);

final class PsychologistController extends AppController
{
    private PsychologistRepository $psychologists;
    private PatientRepository $patients;
    private MoodRepository $moods;
    private EmotionRepository $emotions;
    private UserRepository $users;
    private ChatRepository $chats;
    private AnalysisRepository $analysis;

    public function __construct()
    {
        parent::__construct();
        $this->psychologists = new PsychologistRepository();
        $this->patients = new PatientRepository();
        $this->moods = new MoodRepository();
        $this->emotions = new EmotionRepository();
        $this->users = new UserRepository();
        $this->chats = new ChatRepository();
        $this->analysis = new AnalysisRepository();
    }

    public function dashboard(): void
    {
        $this->authorize([User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $patients = $this->psychologists->assignedPatients($user->getId());

        $styles = ['/styles/psychologist.css'];
        $scripts = ['/scripts/searchable-select.js', '/scripts/psychologist-dashboard.js'];

        $patientsJson = json_encode($patients, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $patientsListHtml = '';
        if (empty($patients)) {
            $patientsListHtml = '<li class="patient-table__empty">Brak przypisanych pacjentów.</li>';
        } else {
            foreach ($patients as $patient) {
                $patientId = (int) ($patient['patient_id'] ?? 0);
                $patientUserId = (int) ($patient['patient_user_id'] ?? 0);
                $name = htmlspecialchars($patient['full_name'] ?? '', ENT_QUOTES);
                $email = htmlspecialchars($patient['email'] ?? '', ENT_QUOTES);
                $focus = !empty($patient['focus_area']) ? '<p class="patient-row__focus">Cel: ' . htmlspecialchars($patient['focus_area'], ENT_QUOTES) . '</p>' : '';
                $avgLevel = $patient['avg_level'] ?? '—';
                $avgIntensity = $patient['avg_intensity'] ?? '—';
                $lastEmotionCategory = htmlspecialchars($patient['last_emotion_category'] ?? '—', ENT_QUOTES);
                $lastEmotionSub = !empty($patient['last_emotion_subcategory']) ? '<small>(' . htmlspecialchars($patient['last_emotion_subcategory'], ENT_QUOTES) . ')</small>' : '';
                $weeklyCompletion = isset($patient['weekly_completion']) && $patient['weekly_completion'] !== null ? ($patient['weekly_completion'] . '%') : '—';

                $patientsListHtml .= '<li class="patient-row" data-patient-id="' . $patientId . '" data-patient-user-id="' . $patientUserId . '" data-patient-name="' . $name . '">';
                $patientsListHtml .= '<div><h3>' . $name . '</h3><p>' . $email . '</p>' . $focus . '</div>';
                $patientsListHtml .= '<div class="patient-row__stats">';
                $patientsListHtml .= '<span><strong>Średni nastrój:</strong> ' . $avgLevel . '</span>';
                $patientsListHtml .= '<span><strong>Intensywność:</strong> ' . $avgIntensity . '</span>';
                $patientsListHtml .= '<span><strong>Ostatnia emocja:</strong> ' . $lastEmotionCategory . ' ' . $lastEmotionSub . '</span>';
                $patientsListHtml .= '<span><strong>Regularność:</strong> ' . $weeklyCompletion . '</span>';
                $patientsListHtml .= '</div>';
                $patientsListHtml .= '<div class="patient-row__actions">';
                $patientsListHtml .= '<a class="button button--secondary" href="/psychologist/analysis?patient=' . $patientUserId . '">Analiza</a>';
                $patientsListHtml .= '<a class="button button--ghost" href="/psychologist/chat?patient=' . $patientUserId . '">Chat</a>';
                $patientsListHtml .= '<a class="button button--ghost" href="/psychologist/patient/' . $patientId . '/export">Eksport CSV</a>';
                $patientsListHtml .= '<form method="post" action="/psychologist/patient/detach" onsubmit="return confirm(\'Odłączyć pacjenta?\')">';
                $patientsListHtml .= '<input type="hidden" name="patient_user_id" value="' . $patientUserId . '">';
                $patientsListHtml .= '<button type="submit" class="button button--ghost button--danger">Odłącz</button>';
                $patientsListHtml .= '</form>';
                $patientsListHtml .= '</div></li>';
            }
        }

        $success = $this->getFlash('success');
        $error = $this->getFlash('error');
        $successHtml = $success ? '<div class="card card--full alert alert--success">' . htmlspecialchars($success, ENT_QUOTES) . '</div>' : '';
        $errorHtml = $error ? '<div class="card card--full alert alert--error">' . htmlspecialchars($error, ENT_QUOTES) . '</div>' : '';

        $this->render('psychologist/dashboard', [
            'user' => $user,
            'patients' => $patients,
            'patientsJson' => $patientsJson,
            'patientsListHtml' => $patientsListHtml,
            'successHtml' => $successHtml,
            'errorHtml' => $errorHtml,
            'styles' => $styles,
            'scripts' => $scripts,
        ]);
    }

    public function patientMoodHistory(string $patientId): void
    {
        $this->authorize([User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->json(['error' => 'Sesja wygasła.'], 401);
            return;
        }

        try {
            $mode = isset($_GET['mode']) ? strtolower(trim((string) $_GET['mode'])) : 'daily';
            if (!in_array($mode, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
                $mode = 'daily';
            }
            $month = isset($_GET['month']) ? trim((string) $_GET['month']) : null; 
            $anchorRaw = isset($_GET['anchor']) ? trim((string) $_GET['anchor']) : null; 

            $periodLabel = '';
            $anchor = null;
            try {
                if ($anchorRaw !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchorRaw)) {
                    $anchor = $anchorRaw;
                    $anchorDt = new DateTimeImmutable($anchorRaw);
                } elseif ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month)) {
                    $anchorDt = new DateTimeImmutable($month . '-01');
                    $anchor = $anchorDt->format('Y-m-d');
                } else {
                    $anchorDt = new DateTimeImmutable();
                    $anchor = $anchorDt->format('Y-m-d');
                }

                switch ($mode) {
                    case 'weekly':
                        $start = new DateTimeImmutable($anchorDt->format('Y-m-d'));
                        $start = $start->modify('monday this week');
                        $end = $start->modify('+6 days');
                        $periodLabel = sprintf('Tydzień: %s — %s', $start->format('d.m.Y'), $end->format('d.m.Y'));
                        break;
                    case 'monthly':
                        $start = new DateTimeImmutable($anchorDt->format('Y-m-01'));
                        $periodLabel = $start->format('m.Y');
                        break;
                    case 'yearly':
                        $start = new DateTimeImmutable($anchorDt->format('Y-01-01'));
                        $periodLabel = $start->format('Y');
                        break;
                    case 'daily':
                    default:
                        $start = new DateTimeImmutable($anchorDt->format('Y-m-d'));
                        $periodLabel = $start->format('d.m.Y');
                        break;
                }
            } catch (Exception $e) {
                $anchor = null;
            }

            $history = $this->psychologists->patientEmotionTrend($user->getId(), (int) $patientId, $mode, $month, $anchor);

            $this->json([
                'status' => 'ok',
                'history' => $history,
                'periodLabel' => $periodLabel,
                'anchor' => $anchor,
            ]);
        } catch (Throwable $exception) {
            error_log('Error in patientMoodHistory: ' . $exception->getMessage());
            $this->json([
                'status' => 'error',
                'error' => 'Nie udało się pobrać danych analizy. ' . $exception->getMessage(),
            ], 500);
        }
    }

    public function patientAnalysisEntries(string $patientUserId): void
    {
        $this->authorize([User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->json(['error' => 'Sesja wygasła.'], 401);
            return;
        }

        try {
            $patientId = $this->patients->getPatientIdByUserId((int) $patientUserId);
            if ($patientId === null) {
                $this->json(['error' => 'Pacjent nie istnieje.'], 404);
                return;
            }

            $meta = $user->getMetadata();
            $psychologistProfile = $meta['psychologist'] ?? null;
            $psychologistId = $psychologistProfile instanceof PsychologistProfile ? $psychologistProfile->getId() : null;
            if ($psychologistId === null) {
                $this->json(['error' => 'Brak profilu psychologa.'], 403);
                return;
            }

            if (!$this->analysis->psychologistHasAccess($psychologistId, $patientId)) {
                $this->json(['error' => 'Brak dostępu do tego pacjenta.'], 403);
                return;
            }

            $entries = $this->analysis->findByPatient($psychologistId, $patientId);

            $entriesData = [];
            foreach ($entries as $entry) {
                $entriesData[] = [
                    'id' => $entry->getId(),
                    'title' => $entry->getTitle(),
                    'content' => $entry->getContent(),
                    'entry_date' => $entry->getEntryDate()->format('Y-m-d'),
                    'created_at' => $entry->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updated_at' => $entry->getUpdatedAt()->format('Y-m-d H:i:s'),
                ];
            }

            $this->json([
                'status' => 'ok',
                'entries' => $entriesData,
            ]);
        } catch (Throwable $exception) {
            error_log('Error in patientAnalysisEntries: ' . $exception->getMessage());
            $this->json(['status' => 'error', 'error' => 'Nie udało się pobrać wpisów: ' . $exception->getMessage()], 500);
        }
    }

    public function exportCsv(string $patientId): void
    {
        $this->authorize([User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $exportDir = Env::get('CSV_EXPORT_DIR', sys_get_temp_dir());
        $filename = sprintf('mindgarden_patient_%s_%s.csv', $patientId, date('Ymd_His'));
        $path = rtrim($exportDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        try {
            $csvPath = $this->psychologists->exportPatientMoodCsv($user->getId(), (int) $patientId, $path);
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('/psychologist/dashboard');
            return;
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . basename($csvPath) . '"');
        header('Content-Length: ' . filesize($csvPath));
        readfile($csvPath);
        unlink($csvPath);
        exit;
    }

    public function regenerateCode(): void
    {
        $this->authorize([User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        try {
            $code = $this->users->regenerateInviteCode($user->getId());
            $this->setFlash('success', 'Nowy kod terapeuty: ' . $code);
        } catch (Throwable $exception) {
            $this->setFlash('error', 'Nie udało się odświeżyć kodu: ' . $exception->getMessage());
        }

        $this->redirect('/psychologist/settings');
    }

    public function detachPatient(): void
    {
        $this->authorize([User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $patientUserId = isset($_POST['patient_user_id']) ? (int) $_POST['patient_user_id'] : 0;
        if ($patientUserId <= 0) {
            $this->setFlash('error', 'Nieprawidłowy pacjent.');
            $this->redirect('/psychologist/dashboard');
        }

        try {
            $this->users->detachPatientFromPsychologist($user->getId(), $patientUserId);
            $this->setFlash('success', 'Pacjent został odłączony.');
        } catch (Throwable $exception) {
            $this->setFlash('error', 'Nie udało się odłączyć pacjenta: ' . $exception->getMessage());
        }

        $this->redirect('/psychologist/dashboard');
    }

    public function analysis(): void
    {
        $this->authorize([User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $meta = $user->getMetadata();
        $psychologistProfile = $meta['psychologist'] ?? null;
        $psychologistId = $psychologistProfile instanceof PsychologistProfile ? $psychologistProfile->getId() : null;

        if ($psychologistId === null) {
            $this->setFlash('error', 'Brak profilu psychologa.');
            $this->redirect('/psychologist/dashboard');
            return;
        }

        $patients = $this->psychologists->assignedPatients($user->getId());
        $selectedPatientId = isset($_GET['patient']) ? (int) $_GET['patient'] : null;
        $analysisEntries = [];

        if ($selectedPatientId !== null) {
            $patientId = $this->patients->getPatientIdByUserId($selectedPatientId);
            if ($patientId !== null && $this->analysis->psychologistHasAccess($psychologistId, $patientId)) {
                $analysisEntries = $this->analysis->findByPatient($psychologistId, $patientId);
            }
        }

        $emotionOptions = array_map(
            static fn (EmotionCategory $category): array => [
                'slug' => $category->getSlug(),
                'name' => $category->getName(),
                'accentColor' => $category->getAccentColor(),
                'subcategories' => array_map(
                    static fn (EmotionSubcategory $subcategory): array => [
                        'slug' => $subcategory->getSlug(),
                        'name' => $subcategory->getName(),
                    ],
                    $category->getSubcategories()
                ),
            ],
            $this->emotions->all()
        );

        $patientsJson = json_encode($patients, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $emotionOptionsJson = json_encode($emotionOptions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $patientsOptionsHtml = '';
        if (!empty($patients) && is_array($patients)) {
            foreach ($patients as $p) {
                $val = (int) ($p['patient_user_id'] ?? 0);
                $name = htmlspecialchars($p['full_name'] ?? '', ENT_QUOTES);
                $selected = $selectedPatientId !== null && $selectedPatientId === $val ? ' selected' : '';
                $patientsOptionsHtml .= sprintf('<option value="%d"%s>%s</option>', $val, $selected, $name);
            }
        }

        $insightHtml = $selectedPatientId ? '<p>Ładowanie danych...</p>' : '<p>Wybierz pacjenta z listy powyżej, aby zobaczyć trend emocji i intensywności.</p>';

        $addEntryButtonHtml = $selectedPatientId ? '<button class="button button--primary" id="add-analysis-entry-btn">Dodaj wpis</button>' : '';
        $formStyle = 'display: none;';

        if (empty($analysisEntries)) {
            $analysisEntriesHtml = $selectedPatientId ? '<p class="analysis-entries-empty">Brak wpisów analizy. Kliknij "Dodaj wpis", aby utworzyć pierwszy wpis.</p>' : '<p class="analysis-entries-empty">Wybierz pacjenta, aby zobaczyć wpisy analizy.</p>';
        } else {
            $analysisEntriesHtml = '';
            foreach ($analysisEntries as $entry) {
                $entryId = (int) $entry->getId();
                $title = htmlspecialchars($entry->getTitle());
                $entryDate = $entry->getEntryDate()->format('d.m.Y');
                $content = nl2br(htmlspecialchars($entry->getContent()));
                $created = $entry->getCreatedAt()->format('d.m.Y H:i');
                $updatedHtml = '';
                if ($entry->getUpdatedAt() > $entry->getCreatedAt()) {
                    $updatedHtml = '<small>Zaktualizowano: ' . $entry->getUpdatedAt()->format('d.m.Y H:i') . '</small>';
                }

                $analysisEntriesHtml .= '<article class="analysis-entry" data-entry-id="' . $entryId . '">';
                $analysisEntriesHtml .= '<header class="analysis-entry__header"><div><h3>' . $title . '</h3><time>' . $entryDate . '</time></div><div class="analysis-entry__actions">';
                $analysisEntriesHtml .= '<button class="button button--ghost button--small edit-entry-btn" data-entry-id="' . $entryId . '">Edytuj</button>';
                $analysisEntriesHtml .= '<button class="button button--ghost button--small delete-entry-btn" data-entry-id="' . $entryId . '">Usuń</button>';
                $analysisEntriesHtml .= '</div></header>';
                $analysisEntriesHtml .= '<div class="analysis-entry__content">' . $content . '</div>';
                $analysisEntriesHtml .= '<footer class="analysis-entry__footer"><small>Utworzono: ' . $created . '</small>' . $updatedHtml . '</footer>';
                $analysisEntriesHtml .= '</article>';
            }
        }

        $this->render('psychologist/analysis', [
            'user' => $user,
            'patients' => $patients,
            'patientsJson' => $patientsJson,
            'patientsOptionsHtml' => $patientsOptionsHtml,
            'selectedPatientId' => $selectedPatientId,
            'insightHtml' => $insightHtml,
            'addEntryButtonHtml' => $addEntryButtonHtml,
            'formStyle' => $formStyle,
            'analysisEntriesHtml' => $analysisEntriesHtml,
            'analysisEntries' => $analysisEntries,
            'emotionOptionsJson' => $emotionOptionsJson,
            'styles' => ['/styles/psychologist.css'],
            'scripts' => ['/scripts/searchable-select.js', '/scripts/psychologist-dashboard.js'],
        ]);
    }

    public function createAnalysisEntry(): void
    {
        $this->authorize([User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->json(['error' => 'Sesja wygasła.'], 401);
            return;
        }

        $meta = $user->getMetadata();
        $psychologistProfile = $meta['psychologist'] ?? null;
        $psychologistId = $psychologistProfile instanceof PsychologistProfile ? $psychologistProfile->getId() : null;

        if ($psychologistId === null) {
            $this->json(['error' => 'Brak profilu psychologa.'], 403);
            return;
        }

        $patientUserId = isset($_POST['patient_user_id']) ? (int) $_POST['patient_user_id'] : 0;
        $rawInput = [];
        if (!empty($_POST)) {
            $rawInput = $_POST;
        } else {
            $raw = file_get_contents('php://input');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $rawInput = $decoded;
                } else {
                    parse_str($raw, $parsed);
                    if (!empty($parsed)) {
                        $rawInput = $parsed;
                    }
                }
            }
        }

        $title = trim($rawInput['title'] ?? '');
        $content = trim($rawInput['content'] ?? '');
        $dateInput = $rawInput['entry_date'] ?? null;

        try {
            $logKeys = json_encode(array_keys($rawInput));
            error_log('updateAnalysisEntry rawInput keys: ' . $logKeys);
            error_log('updateAnalysisEntry title length: ' . mb_strlen($title));
        } catch (Throwable $e) {
        }

        if ($patientUserId <= 0) {
            $this->json(['error' => 'Wskaż pacjenta.'], 422);
            return;
        }

        if ($title === '') {
            $rawBody = '';
            try {
                $rawBody = (string) file_get_contents('php://input');
            } catch (Throwable $e) {
            }
            $postDump = $_POST;
            $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');

            try {
                error_log('updateAnalysisEntry debug - raw body: ' . substr($rawBody, 0, 1000));
                error_log('updateAnalysisEntry debug - $_POST keys: ' . json_encode(array_keys($postDump)));
                error_log('updateAnalysisEntry debug - Content-Type: ' . $contentType);
            } catch (Throwable $e) {
            }

            $this->json([
                'error' => 'Tytuł jest wymagany.',
                'debug' => [
                    'rawBodySnippet' => mb_substr($rawBody, 0, 1000),
                    'postKeys' => array_values(array_keys($postDump)),
                    'contentType' => $contentType,
                ],
            ], 422);
            return;
        }

        if ($content === '') {
            $this->json(['error' => 'Treść jest wymagana.'], 422);
            return;
        }

        try {
            $entryDate = $dateInput ? new DateTimeImmutable($dateInput) : new DateTimeImmutable();
        } catch (Exception) {
            $this->json(['error' => 'Niepoprawna data.'], 422);
            return;
        }

        $patientId = $this->patients->getPatientIdByUserId($patientUserId);
        if ($patientId === null) {
            $this->json(['error' => 'Pacjent nie istnieje.'], 404);
            return;
        }

        if (!$this->analysis->psychologistHasAccess($psychologistId, $patientId)) {
            $this->json(['error' => 'Brak dostępu do tego pacjenta.'], 403);
            return;
        }

        try {
            $entry = $this->analysis->create($psychologistId, $patientId, $title, $content, $entryDate);
        } catch (Throwable $exception) {
            $this->json(['error' => 'Nie udało się utworzyć wpisu: ' . $exception->getMessage()], 500);
            return;
        }

        $this->json([
            'status' => 'ok',
            'entry' => [
                'id' => $entry->getId(),
                'title' => $entry->getTitle(),
                'content' => $entry->getContent(),
                'entry_date' => $entry->getEntryDate()->format('Y-m-d'),
                'created_at' => $entry->getCreatedAt()->format(DateTimeInterface::ATOM),
            ],
        ]);
    }

    public function updateAnalysisEntry(string $entryId): void
    {
        $this->authorize([User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->json(['error' => 'Sesja wygasła.'], 401);
            return;
        }

        $meta = $user->getMetadata();
        $psychologistProfile = $meta['psychologist'] ?? null;
        $psychologistId = $psychologistProfile instanceof PsychologistProfile ? $psychologistProfile->getId() : null;

        if ($psychologistId === null) {
            $this->json(['error' => 'Brak profilu psychologa.'], 403);
            return;
        }

        $rawInput = [];
        if (!empty($_POST)) {
            $rawInput = $_POST;
        } else {
            $raw = file_get_contents('php://input');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $rawInput = $decoded;
                } else {
                    parse_str($raw, $parsed);
                    if (!empty($parsed)) {
                        $rawInput = $parsed;
                    }
                }
            }
        }

        $title = trim($rawInput['title'] ?? '');
        $content = trim($rawInput['content'] ?? '');
        $dateInput = $rawInput['entry_date'] ?? null;

        try {
            error_log('updateAnalysisEntry (PUT) parsed keys: ' . json_encode(array_keys($rawInput)));
            error_log('updateAnalysisEntry (PUT) title length: ' . mb_strlen($title));
        } catch (Throwable $e) {
        }

        if ($title === '') {
            $this->json(['error' => 'Tytuł jest wymagany.'], 422);
            return;
        }

        if ($content === '') {
            $this->json(['error' => 'Treść jest wymagana.'], 422);
            return;
        }

        try {
            $entryDate = $dateInput ? new DateTimeImmutable($dateInput) : new DateTimeImmutable();
        } catch (Exception) {
            $this->json(['error' => 'Niepoprawna data.'], 422);
            return;
        }

        try {
            $entry = $this->analysis->update((int) $entryId, $psychologistId, $title, $content, $entryDate);
        } catch (RuntimeException $exception) {
            $this->json(['error' => $exception->getMessage()], 404);
            return;
        } catch (Throwable $exception) {
            $this->json(['error' => 'Nie udało się zaktualizować wpisu: ' . $exception->getMessage()], 500);
            return;
        }

        $this->json([
            'status' => 'ok',
            'entry' => [
                'id' => $entry->getId(),
                'title' => $entry->getTitle(),
                'content' => $entry->getContent(),
                'entry_date' => $entry->getEntryDate()->format('Y-m-d'),
                'updated_at' => $entry->getUpdatedAt()->format(DateTimeInterface::ATOM),
            ],
        ]);
    }

    public function deleteAnalysisEntry(string $entryId): void
    {
        $this->authorize([User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->json(['error' => 'Sesja wygasła.'], 401);
            return;
        }

        $meta = $user->getMetadata();
        $psychologistProfile = $meta['psychologist'] ?? null;
        $psychologistId = $psychologistProfile instanceof PsychologistProfile ? $psychologistProfile->getId() : null;

        if ($psychologistId === null) {
            $this->json(['error' => 'Brak profilu psychologa.'], 403);
            return;
        }

        $deleted = $this->analysis->delete((int) $entryId, $psychologistId);
        if (!$deleted) {
            $this->json(['error' => 'Wpis nie został znaleziony lub nie masz uprawnień.'], 404);
            return;
        }

        $this->json(['status' => 'ok']);
    }

    public function chat(): void
    {
        $this->authorize([User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $patients = $this->psychologists->assignedPatients($user->getId());

        $styles = ['/styles/psychologist.css'];
        $scripts = ['/scripts/searchable-select.js', '/scripts/psychologist-dashboard.js'];

        $selectedPatientUserId = isset($_GET['patient']) ? (int) $_GET['patient'] : null;

        $patientsJson = json_encode($patients, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $patientsOptionsHtml = '';
        if (!empty($patients) && is_array($patients)) {
            foreach ($patients as $p) {
                $val = (int) ($p['patient_user_id'] ?? 0);
                $name = htmlspecialchars($p['full_name'] ?? '', ENT_QUOTES);
                $selected = $selectedPatientUserId !== null && $selectedPatientUserId === $val ? ' selected' : '';
                $patientsOptionsHtml .= sprintf('<option value="%d"%s>%s</option>', $val, $selected, $name);
            }
        }

        $placeholderHtml = $selectedPatientUserId ? 'Ładowanie wiadomości...' : 'Wybierz pacjenta z listy powyżej, aby otworzyć konwersację.';
        $composerDisabledAttr = $selectedPatientUserId ? '' : 'disabled';
        $sendButtonDisabledAttr = $selectedPatientUserId ? '' : 'disabled';
        $chatPatientValue = $selectedPatientUserId ? (string) $selectedPatientUserId : '';

        $this->render('psychologist/chat', [
            'user' => $user,
            'patients' => $patients,
            'patientsJson' => $patientsJson,
            'patientsOptionsHtml' => $patientsOptionsHtml,
            'selectedPatientUserId' => $selectedPatientUserId,
            'placeholderHtml' => $placeholderHtml,
            'composerDisabledAttr' => $composerDisabledAttr,
            'sendButtonDisabledAttr' => $sendButtonDisabledAttr,
            'chatPatientValue' => $chatPatientValue,
            'styles' => $styles,
            'scripts' => $scripts,
        ]);
    }

    public function settings(): void
    {
        $this->authorize([User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $metadata = $user->getMetadata();
        $profile = $metadata['psychologist'] ?? null;
        $inviteCode = $profile instanceof PsychologistProfile ? $profile->getInviteCode() : null;

        $styles = ['/styles/psychologist.css'];

        $success = $this->getFlash('success');
        $error = $this->getFlash('error');
        $successHtml = $success ? '<div class="card card--full alert alert--success">' . htmlspecialchars($success, ENT_QUOTES) . '</div>' : '';
        $errorHtml = $error ? '<div class="card card--full alert alert--error">' . htmlspecialchars($error, ENT_QUOTES) . '</div>' : '';

        $inviteCodeEscaped = htmlspecialchars($inviteCode ?? '—', ENT_QUOTES);
        $userFullName = htmlspecialchars($user->getFullName(), ENT_QUOTES);
        $userEmail = htmlspecialchars($user->getEmail(), ENT_QUOTES);

        $this->render('psychologist/settings', [
            'user' => $user,
            'inviteCodeEscaped' => $inviteCodeEscaped,
            'userFullName' => $userFullName,
            'userEmail' => $userEmail,
            'successHtml' => $successHtml,
            'errorHtml' => $errorHtml,
            'styles' => $styles,
        ]);
    }
}

