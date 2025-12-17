<?php

declare(strict_types=1);

final class ApiController extends AppController
{
    private PatientRepository $patients;
    private MoodRepository $moods;
    private HabitRepository $habits;
    private EmotionRepository $emotions;
    private ChatRepository $chats;
    private UserRepository $users;

    public function __construct()
    {
        parent::__construct();
        $this->patients = new PatientRepository();
        $this->moods = new MoodRepository();
        $this->habits = new HabitRepository();
        $this->emotions = new EmotionRepository();
        $this->chats = new ChatRepository();
        $this->users = new UserRepository();
    }

    public function patientMoods(): void
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

        $timeline = $this->moods->timeline($patientId, 30);

        $this->json([
            'status' => 'ok',
            'timeline' => array_map(static function (MoodEntry $entry): array {
                return [
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
                    'note' => $entry->getNote(),
                ];
            }, $timeline),
        ]);
    }

    public function patientHabits(): void
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

        $habits = $this->habits->listWithProgress($patientId);

        $this->json([
            'status' => 'ok',
            'habits' => $habits,
        ]);
    }

    public function patientEmotions(): void
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

        $distribution = $this->moods->distribution($patientId);

        $this->json([
            'status' => 'ok',
            'emotions' => $distribution,
        ]);
    }

    public function emotionOptions(): void
    {
        $this->authorize([User::ROLE_PATIENT, User::ROLE_PSYCHOLOGIST]);

        $options = array_map(
            static function (EmotionCategory $category): array {
                return [
                    'slug' => $category->getSlug(),
                    'name' => $category->getName(),
                    'accentColor' => $category->getAccentColor(),
                    'subcategories' => array_map(
                        static fn (EmotionSubcategory $subcategory): array => [
                            'slug' => $subcategory->getSlug(),
                            'name' => $subcategory->getName(),
                            'description' => $subcategory->getDescription(),
                        ],
                        $category->getSubcategories()
                    ),
                ];
            },
            $this->emotions->all()
        );

        $this->json([
            'status' => 'ok',
            'emotions' => $options,
        ]);
    }

    public function chatMessages(): void
    {
        $this->authorize([User::ROLE_PATIENT, User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->json(['error' => 'Sesja wygasła.'], 401);
            return;
        }

        $afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : null;
        $limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 50;

        if ($user->isPatient()) {
            $patientId = $this->patients->getPatientIdByUserId($user->getId());
            if ($patientId === null) {
                $this->json(['error' => 'Brak profilu pacjenta.'], 404);
                return;
            }

            $thread = $this->chats->findThreadForPatient($patientId);
            if ($thread === null) {
                $this->json([
                    'status' => 'ok',
                    'messages' => [],
                    'thread_id' => null,
                ]);
                return;
            }

            $assignment = $this->patients->assignmentDetails($patientId);
            $messages = $this->chats->fetchMessages($thread->getId(), $afterId, $limit);

            $this->json([
                'status' => 'ok',
                'thread_id' => $thread->getId(),
                'participants' => [
                    'patient' => [
                        'user_id' => $user->getId(),
                        'name' => $user->getFullName(),
                    ],
                    'psychologist' => $assignment ? [
                        'user_id' => (int) ($assignment['psychologist_user_id'] ?? 0),
                        'name' => $assignment['psychologist_name'] ?? 'Psycholog',
                    ] : null,
                ],
                'messages' => $this->formatMessages($messages),
            ]);
            return;
        }

        if ($user->isPsychologist()) {
            $patientUserId = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
            if ($patientUserId <= 0) {
                $this->json(['error' => 'Wskaż pacjenta.'], 422);
                return;
            }

            $patientId = $this->patients->getPatientIdByUserId($patientUserId);
            $meta = $user->getMetadata();
            $psychologistProfile = $meta['psychologist'] ?? null;
            $psychologistId = $psychologistProfile instanceof PsychologistProfile ? $psychologistProfile->getId() : null;
            if ($patientId === null || $psychologistId === null) {
                $this->json(['error' => 'Pacjent lub psycholog nie istnieje.'], 404);
                return;
            }

            $thread = $this->chats->findThreadForPsychologist($psychologistId, $patientId);
            if ($thread === null) {
                $thread = $this->chats->ensureThread($patientId, $psychologistId);
            }

            $patientUser = $this->users->findById($patientUserId);
            $messages = $this->chats->fetchMessages($thread->getId(), $afterId, $limit);

            $this->json([
                'status' => 'ok',
                'thread_id' => $thread->getId(),
                'participants' => [
                    'psychologist' => [
                        'user_id' => $user->getId(),
                        'name' => $user->getFullName(),
                    ],
                    'patient' => $patientUser ? [
                        'user_id' => $patientUser->getId(),
                        'name' => $patientUser->getFullName(),
                    ] : null,
                ],
                'messages' => $this->formatMessages($messages),
            ]);
            return;
        }

        $this->json(['error' => 'Brak uprawnień.'], 403);
    }

    public function postChatMessage(): void
    {
        $this->authorize([User::ROLE_PATIENT, User::ROLE_PSYCHOLOGIST]);

        $user = $this->auth->user();
        if ($user === null) {
            $this->json(['error' => 'Sesja wygasła.'], 401);
            return;
        }

        $body = trim($_POST['message'] ?? '');
        if ($body === '') {
            $this->json(['error' => 'Wiadomość nie może być pusta.'], 422);
            return;
        }

        if ($user->isPatient()) {
            $patientId = $this->patients->getPatientIdByUserId($user->getId());
            if ($patientId === null) {
                $this->json(['error' => 'Brak profilu pacjenta.'], 404);
                return;
            }

            $thread = $this->chats->findThreadForPatient($patientId);
            if ($thread === null) {
                $assignment = $this->patients->assignmentDetails($patientId);
                if (!$assignment || empty($assignment['psychologist_id'])) {
                    $this->json(['error' => 'Brak przypisanego psychologa.'], 403);
                    return;
                }
                $thread = $this->chats->ensureThread($patientId, (int) $assignment['psychologist_id']);
            }

            $message = $this->chats->appendMessage($thread->getId(), $user->getId(), $body);
            $this->json([
                'status' => 'ok',
                'message' => $this->formatMessage($message),
            ]);
            return;
        }

        if ($user->isPsychologist()) {
            $patientUserId = isset($_POST['patient_id']) ? (int) $_POST['patient_id'] : 0;
            if ($patientUserId <= 0) {
                $this->json(['error' => 'Wskaż pacjenta.'], 422);
                return;
            }

            $patientId = $this->patients->getPatientIdByUserId($patientUserId);
            $meta = $user->getMetadata();
            $psychologistProfile = $meta['psychologist'] ?? null;
            $psychologistId = $psychologistProfile instanceof PsychologistProfile ? $psychologistProfile->getId() : null;
            if ($patientId === null || $psychologistId === null) {
                $this->json(['error' => 'Pacjent lub psycholog nie istnieje.'], 404);
                return;
            }

            $thread = $this->chats->findThreadForPsychologist($psychologistId, $patientId)
                ?? $this->chats->ensureThread($patientId, $psychologistId);

            $message = $this->chats->appendMessage($thread->getId(), $user->getId(), $body);
            $this->json([
                'status' => 'ok',
                'message' => $this->formatMessage($message),
            ]);
            return;
        }

        $this->json(['error' => 'Brak uprawnień.'], 403);
    }

    private function formatMessages(array $messages): array
    {
        return array_map(fn (ChatMessage $message): array => $this->formatMessage($message), $messages);
    }

    private function formatMessage(ChatMessage $message): array
    {
        return [
            'id' => $message->getId(),
            'thread_id' => $message->getThreadId(),
            'sender_user_id' => $message->getSenderUserId(),
            'body' => $message->getBody(),
            'created_at' => $message->getCreatedAt()->format(DateTimeInterface::ATOM),
        ];
    }
}

