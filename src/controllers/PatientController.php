<?php

declare(strict_types=1);

final class PatientController extends AppController
{
    private PatientRepository $patients;
    private MoodRepository $moods;
    private HabitRepository $habits;
    private BadgeRepository $badges;
    private EmotionRepository $emotions;
    private ChatRepository $chats;
    public function __construct()
    {
        parent::__construct();
        $this->patients = new PatientRepository();
        $this->moods = new MoodRepository();
        $this->habits = new HabitRepository();
        $this->badges = new BadgeRepository();
        $this->emotions = new EmotionRepository();
        $this->chats = new ChatRepository();
    }

    public function dashboard(): void
    {
        $this->authorize([User::ROLE_PATIENT]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $profile = $this->patients->findProfileByUserId($user->getId());
        if ($profile === null) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $patientId = $profile->getId();

        $snapshot = $this->patients->dashboardSnapshot($patientId);
        $badges = $this->badges->listByPatient($patientId);
        
        $streaks = $this->habits->streaks($patientId);
        $currentStreak = 0;
        foreach ($streaks as $streak) {
            $currentStreak = max($currentStreak, (int) ($streak['streak_length'] ?? 0));
        }
        $snapshot['current_streak'] = $currentStreak;

        $badgeIcons = [
            'welcome' => '/assets/badges/welcome.svg',
            'first_mood' => '/assets/badges/first_mood.svg',
            'streak_7' => '/assets/badges/streak.svg',
            'streak_21' => '/assets/badges/streak.svg',
        ];
        $defaultIcon = '/assets/badges/default.svg';

        if (empty($badges)) {
            $badgesHtml = '<div class="badge-grid__empty">Brak odznak jeszcze.</div>';
        } else {
            $badgesHtml = '';
            foreach ($badges as $badge) {
                $code = $badge->getCode();
                $label = htmlspecialchars($badge->getLabel(), ENT_QUOTES);
                $desc = $badge->getDescription() ? htmlspecialchars($badge->getDescription(), ENT_QUOTES) : '';
                $date = $badge->getAwardedAt()->format('d.m.Y');
                
                if (str_starts_with($code, 'habit_goal_')) {
                    $icon = '/assets/badges/habit_goal.svg';
                } else {
                    $icon = $badgeIcons[$code] ?? $defaultIcon;
                }
                
                $badgesHtml .= '<div class="badge" tabindex="0">';
                $badgesHtml .= '<div class="badge__icon"><img src="' . $icon . '" alt="' . $label . '"></div>';
                $badgesHtml .= '<div class="badge__label">' . $label . '</div>';
                $badgesHtml .= '<span class="badge__date">' . $date . '</span>';
                if ($desc) {
                    $badgesHtml .= '<div class="badge__tooltip">' . $desc . '</div>';
                }
                $badgesHtml .= '</div>';
            }
        }

        $styles = ['/styles/patient.css'];
        $scripts = ['/scripts/patient-dashboard.js'];

        $this->render('patient/dashboard', [
            'user' => $user,
            'profile' => $profile,
            'snapshot' => $snapshot,
            'badgesHtml' => $badgesHtml,
            'styles' => $styles,
            'scripts' => $scripts,
        ]);
    }

    public function logMood(): void
    {
        $this->authorize([User::ROLE_PATIENT]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $profile = $this->patients->findProfileByUserId($user->getId());
        if ($profile === null) {
            $this->json(['error' => 'Profil pacjenta nie istnieje.'], 404);
            return;
        }

        $patientId = $profile->getId();

        $level = (int) ($_POST['mood_level'] ?? 0);
        $emotion = trim($_POST['dominant_emotion'] ?? '');
        $note = trim($_POST['note'] ?? '') ?: null;
        $dateInput = $_POST['mood_date'] ?? null;
        $intensity = (int) ($_POST['intensity'] ?? 5);
        $categorySlug = strtolower(trim($_POST['emotion_category'] ?? ''));
        $subcategorySlug = isset($_POST['emotion_subcategory']) ? strtolower(trim((string) $_POST['emotion_subcategory'])) : null;
        try {
            $date = $dateInput ? new DateTimeImmutable($dateInput) : new DateTimeImmutable();
        } catch (Exception) {
            $this->json(['error' => 'Niepoprawna data.'], 422);
            return;
        }

        if ($level < 1 || $level > 5 || $categorySlug === '') {
            $this->json(['error' => 'Wprowadź prawidłowy nastrój (1-5) oraz wybierz emocję.'], 422);
            return;
        }

        if ($intensity < 1 || $intensity > 10) {
            $this->json(['error' => 'Intensywność musi być między 1 a 10.'], 422);
            return;
        }

        try {
            $mood = $this->moods->create($patientId, $date, $level, $intensity, $categorySlug, $subcategorySlug ?: null, $note);
        } catch (InvalidArgumentException $exception) {
            $this->json(['error' => $exception->getMessage()], 422);
            return;
        }

        $stage = $this->refreshTreeStage($patientId);

        $response = [
            'status' => 'ok',
            'mood' => [
                'date' => $mood->getDate()->format('Y-m-d'),
                'level' => $mood->getLevel(),
                'intensity' => $mood->getIntensity(),
                'category' => [
                    'slug' => $mood->getCategorySlug(),
                    'name' => $mood->getCategoryName(),
                ],
                'subcategory' => $mood->getSubcategorySlug() ? [
                    'slug' => $mood->getSubcategorySlug(),
                    'name' => $mood->getSubcategoryName(),
                ] : null,
                'note' => $mood->getNote(),
            ],
            'tree_stage' => $stage,
        ];

        if ($this->wantsJson()) {
            $this->json($response);
            return;
        }

        $this->setFlash('success', 'Dodano wpis nastroju.');
        $this->redirect('/patient/emotions');
    }

    public function logHabit(): void
    {
        $this->authorize([User::ROLE_PATIENT]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->json(['error' => 'Sesja wygasła.'], 401);
            return;
        }

        $profile = $this->patients->findProfileByUserId($user->getId());
        if ($profile === null) {
            $this->json(['error' => 'Profil pacjenta nie istnieje.'], 404);
            return;
        }

        $patientId = $profile->getId();
        $habitId = (int) ($_POST['habit_id'] ?? 0);
        $completed = (bool) ($_POST['completed'] ?? true);
        $moodLevel = isset($_POST['mood_level']) ? (int) $_POST['mood_level'] : null;
        $note = trim($_POST['note'] ?? '') ?: null;
        $dateInput = $_POST['log_date'] ?? null;
        try {
            $date = $dateInput ? new DateTimeImmutable($dateInput) : new DateTimeImmutable();
        } catch (Exception) {
            $this->json(['error' => 'Niepoprawna data.'], 422);
            return;
        }

        $habit = $this->habits->findOne($habitId);
        if ($habit === null || $habit->getPatientId() !== $patientId) {
            $this->json(['error' => 'Nie można zarejestrować nawyku.'], 403);
            return;
        }

        if ($moodLevel !== null && ($moodLevel < 1 || $moodLevel > 5)) {
            $this->json(['error' => 'Poziom nastroju musi mieścić się w przedziale 1-5.'], 422);
            return;
        }

        $log = $this->habits->logCompletedHabit($habitId, $date, $completed, $moodLevel, $note);
        $stage = $this->refreshTreeStage($patientId);

        $response = [
            'status' => 'ok',
            'log' => [
                'habit_id' => $log->getHabitId(),
                'date' => $log->getLogDate()->format('Y-m-d'),
                'completed' => $log->isCompleted(),
                'mood_level' => $log->getMoodLevel(),
                'note' => $log->getNote(),
            ],
            'tree_stage' => $stage,
        ];

        if ($this->wantsJson()) {
            $this->json($response);
            return;
        }

        $this->setFlash('success', 'Zapisano postęp mikronawyku.');
        $this->redirect('/patient/habits');
    }

    public function createHabit(): void
    {
        $this->authorize([User::ROLE_PATIENT]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->json(['error' => 'Sesja wygasła.'], 401);
            return;
        }

        $profile = $this->patients->findProfileByUserId($user->getId());
        if ($profile === null) {
            $this->json(['error' => 'Profil pacjenta nie istnieje.'], 404);
            return;
        }

        $patientId = $profile->getId();
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '') ?: null;
        $frequencyGoal = isset($_POST['frequency_goal']) ? (int) $_POST['frequency_goal'] : 5;

        if ($name === '') {
            $this->json(['error' => 'Nazwa nawyku jest wymagana.'], 422);
            return;
        }

        if ($frequencyGoal < 1 || $frequencyGoal > 21) {
            $this->json(['error' => 'Cel częstotliwości musi być między 1 a 21.'], 422);
            return;
        }

        try {
            $habit = $this->habits->create($patientId, $name, $description, $frequencyGoal);
        } catch (Throwable $exception) {
            $this->json(['error' => 'Nie udało się utworzyć nawyku: ' . $exception->getMessage()], 500);
            return;
        }

        $response = [
            'status' => 'ok',
            'habit' => [
                'id' => $habit->getId(),
                'name' => $habit->getName(),
                'description' => $habit->getDescription(),
                'frequency_goal' => $habit->getFrequencyGoal(),
            ],
        ];

        if ($this->wantsJson()) {
            $this->json($response);
            return;
        }

        $this->setFlash('success', 'Utworzono nowy nawyk.');
        $this->redirect('/patient/habits');
    }

    public function emotions(): void
    {
        $this->authorize([User::ROLE_PATIENT]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $profile = $this->patients->findProfileByUserId($user->getId());
        if ($profile === null) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $patientId = $profile->getId();
        $emotionOptions = array_map(
            static function (EmotionCategory $category): array {
                return [
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
                ];
            },
            $this->emotions->all()
        );

        $emotionOptionsJson = json_encode($emotionOptions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $emotionOptionsAttr = htmlspecialchars($emotionOptionsJson, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $styles = ['/styles/patient.css'];
        $scripts = ['/scripts/patient-dashboard.js'];

        $successMsg = $this->getFlash('success');
        $errorMsg = $this->getFlash('error');
        $successHtml = $successMsg ? '<div class="card card--full alert alert--success">' . htmlspecialchars($successMsg, ENT_QUOTES) . '</div>' : '';
        $errorHtml = $errorMsg ? '<div class="card card--full alert alert--error">' . htmlspecialchars($errorMsg, ENT_QUOTES) . '</div>' : '';

        $this->render('patient/emotions', [
            'user' => $user,
            'profile' => $profile,
            'emotionOptionsJson' => $emotionOptionsJson,
            'emotionOptionsAttr' => $emotionOptionsAttr,
            'successHtml' => $successHtml,
            'errorHtml' => $errorHtml,
            'styles' => $styles,
            'scripts' => $scripts,
        ]);
    }

    public function habits(): void
    {
        $this->authorize([User::ROLE_PATIENT]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $profile = $this->patients->findProfileByUserId($user->getId());
        if ($profile === null) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $patientId = $profile->getId();
            $habits = $this->habits->listWithProgress($patientId);

            $styles = ['/styles/patient.css'];
            $scripts = ['/scripts/patient-dashboard.js'];

            $success = $this->getFlash('success');
            $error = $this->getFlash('error');

            // Build habits HTML
            if (empty($habits)) {
                $habitsHtml = '<li class="habit-list__empty">Brak nawyków. Dodaj pierwszy nawyk, aby rozpocząć!</li>';
            } else {
                $habitsHtml = '';
                foreach ($habits as $habit) {
                    $id = (int) ($habit['id'] ?? 0);
                    $name = htmlspecialchars($habit['name'] ?? '', ENT_QUOTES);
                    $desc = !empty($habit['description']) ? '<p>' . htmlspecialchars($habit['description'], ENT_QUOTES) . '</p>' : '';
                    $completed = (int) ($habit['completed_count'] ?? 0);
                    $goal = (int) ($habit['frequency_goal'] ?? 0);

                    $habitsHtml .= '<li class="habit-item" data-habit-id="' . $id . '">';
                    $habitsHtml .= '<div><h3>' . $name . '</h3>' . $desc . '</div>';
                    $habitsHtml .= '<div class="habit-progress"><span>' . $completed . '/' . $goal . ' w ostatnich 14 dniach</span><button class="button button--secondary" data-habit-log>Zapisz postęp</button></div>';
                    $habitsHtml .= '</li>';
                }
            }

            $successHtml = $success ? '<div class="card card--full alert alert--success">' . htmlspecialchars($success, ENT_QUOTES) . '</div>' : '';
            $errorHtml = $error ? '<div class="card card--full alert alert--error">' . htmlspecialchars($error, ENT_QUOTES) . '</div>' : '';

            $this->render('patient/habits', [
                'user' => $user,
                'profile' => $profile,
                'habitsHtml' => $habitsHtml,
                'successHtml' => $successHtml,
                'errorHtml' => $errorHtml,
                'styles' => $styles,
                'scripts' => $scripts,
            ]);
    }

    public function history(): void
    {
        $this->authorize([User::ROLE_PATIENT]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $profile = $this->patients->findProfileByUserId($user->getId());
        if ($profile === null) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $patientId = $profile->getId();
        $moods = $this->moods->timeline($patientId);
        $habitLogs = $this->habits->logsByPatient($patientId);
        $moodHistoryJson = htmlspecialchars(json_encode(array_map(static fn (MoodEntry $entry) => [
            'id' => $entry->getId(),
            'date' => $entry->getDate()->format('Y-m-d'),
            'level' => $entry->getLevel(),
            'intensity' => $entry->getIntensity(),
            'category' => [
                'slug' => $entry->getCategorySlug(),
                'name' => $entry->getCategoryName(),
            ],
            'subcategory' => $entry->getSubcategorySlug() ? [
                'slug' => $entry->getSubcategorySlug(),
                'name' => $entry->getSubcategoryName(),
            ] : null,
        ], $moods), JSON_THROW_ON_ERROR));

        $this->render('patient/history', [
            'user' => $user,
            'profile' => $profile,
            'moods' => $moods,
            'habitLogs' => $habitLogs,
            'moodHistoryJson' => $moodHistoryJson,
            'styles' => ['/styles/patient.css'],
        ]);
    }

    public function chat(): void
    {
        $this->authorize([User::ROLE_PATIENT]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $profile = $this->patients->findProfileByUserId($user->getId());
        if ($profile === null) {
            http_response_code(404);
            $this->render('errors/404');
            return;
        }

        $patientId = $profile->getId();
        $assignment = $this->patients->assignmentDetails($patientId);
        $thread = $this->chats->findThreadForPatient($patientId);

        $this->render('patient/chat', [
            'user' => $user,
            'profile' => $profile,
            'psychologist' => $assignment,
            'chatThreadId' => $thread?->getId(),
            'styles' => ['/styles/patient.css'],
        ]);
    }

    public function moodHistory(): void
    {
        $this->authorize([User::ROLE_PATIENT]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->json(['error' => 'Sesja wygasła.'], 401);
            return;
        }

        $patientId = $this->patients->getPatientIdByUserId($user->getId());
        if ($patientId === null) {
            $this->json(['error' => 'Brak profilu pacjenta.'], 404);
            return;
        }

        $timeline = $this->moods->trend($patientId, 30);

        $this->json([
            'status' => 'ok',
            'timeline' => $timeline,
        ]);
    }

    public function treeState(): void
    {
        $this->authorize([User::ROLE_PATIENT]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->json(['error' => 'Sesja wygasła.'], 401);
            return;
        }

        $patientId = $this->patients->getPatientIdByUserId($user->getId());
        if ($patientId === null) {
            $this->json(['error' => 'Brak profilu pacjenta.'], 404);
            return;
        }

        $snapshot = $this->patients->dashboardSnapshot($patientId);
        $streaks = $this->habits->streaks($patientId);
        $longestStreak = 0;
        foreach ($streaks as $streak) {
            $longestStreak = max($longestStreak, (int) ($streak['streak_length'] ?? 0));
        }

        $stage = $this->computeTreeStage($snapshot, $longestStreak);
        $message = $this->treeMessage($stage, $longestStreak);

        $this->json([
            'status' => 'ok',
            'stage' => $stage,
            'longest_streak' => $longestStreak,
            'message' => $message,
        ]);
    }

    public function exportMoods(): void
    {
        $this->authorize([User::ROLE_PATIENT]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $patientId = $this->patients->getPatientIdByUserId($user->getId());
        if ($patientId === null) {
            $this->setFlash('error', 'Brak profilu pacjenta.');
            $this->redirect('/patient/dashboard');
        }

        $exportDir = Env::get('CSV_EXPORT_DIR', sys_get_temp_dir());
        $filename = sprintf('mindgarden_moods_%s_%s.csv', $patientId, date('Ymd_His'));
        $path = rtrim($exportDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        try {
            $csvPath = $this->moods->exportCsvForPatient($patientId, $path);
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
            $this->redirect('/patient/dashboard');
            return;
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . basename($csvPath) . '"');
        header('Content-Length: ' . filesize($csvPath));
        readfile($csvPath);
        unlink($csvPath);
        exit;
    }

    private function refreshTreeStage(int $patientId): int
    {
        $snapshot = $this->patients->dashboardSnapshot($patientId);
        $streaks = $this->habits->streaks($patientId);

        $longestStreak = 0;
        foreach ($streaks as $streak) {
            $longestStreak = max($longestStreak, (int) ($streak['streak_length'] ?? 0));
        }

        $stage = $this->computeTreeStage($snapshot, $longestStreak);
        $this->patients->updateTreeStage($patientId, $stage);

        return $stage;
    }


    private function computeTreeStage(array $snapshot, int $longestStreak): int
    {
        $averageMood = isset($snapshot['average_mood']) ? (float) $snapshot['average_mood'] : 1.0;
        $averageIntensity = isset($snapshot['average_intensity']) ? (float) $snapshot['average_intensity'] : 5.0;
        $baseStage = (int) ceil($averageMood);
        $bonus = $averageIntensity >= 7 ? 1 : 0;

        if ($longestStreak >= 21) {
            $bonus = 2;
        } elseif ($longestStreak >= 14) {
            $bonus = 1;
        }

        return max(1, min(5, $baseStage + $bonus));
    }

    private function treeMessage(int $stage, int $streak): string
    {
        return match ($stage) {
            5 => 'Twoje drzewko rozkwita! Utrzymaj świetną regularność.',
            4 => $streak >= 7
                ? 'Drzewko rośnie dzięki Twojej wytrwałości. Pamiętaj o mikronawykach!'
                : 'Jesteś blisko kolejnego poziomu. Zadbaj o codzienny wpis nastroju.',
            3 => 'Drzewko nabiera sił. Regularne wpisy poprawią jego kondycję.',
            2 => 'Zacznij podlewać swoje drzewko częściej, drobne kroki robią różnicę.',
            default => 'Zrób pierwszy krok i dodaj wpis, aby obudzić swoje drzewko.',
        };
    }
}

