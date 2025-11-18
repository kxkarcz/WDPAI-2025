CREATE EXTENSION IF NOT EXISTS pgcrypto;

DROP VIEW IF EXISTS v_patient_emotion_timeline;
DROP VIEW IF EXISTS v_psychologist_patient_overview;
DROP VIEW IF EXISTS v_patient_mood_summary;

DROP TABLE IF EXISTS chat_messages CASCADE;
DROP TABLE IF EXISTS chat_threads CASCADE;
DROP TABLE IF EXISTS badges CASCADE;
DROP TABLE IF EXISTS habit_logs CASCADE;
DROP TABLE IF EXISTS habits CASCADE;
DROP TABLE IF EXISTS moods CASCADE;
DROP TABLE IF EXISTS emotion_subcategories CASCADE;
DROP TABLE IF EXISTS emotion_categories CASCADE;
DROP TABLE IF EXISTS patient_psychologist CASCADE;
DROP TABLE IF EXISTS patients CASCADE;
DROP TABLE IF EXISTS psychologists CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS roles CASCADE;

CREATE TABLE roles (
    name VARCHAR(32) PRIMARY KEY
);

INSERT INTO roles (name) VALUES
('administrator'),
('psychologist'),
('patient');

CREATE TABLE users (
    id             SERIAL PRIMARY KEY,
    email          VARCHAR(120) UNIQUE NOT NULL,
    full_name      VARCHAR(120)        NOT NULL,
    role           VARCHAR(32)         NOT NULL REFERENCES roles(name),
    password_hash  TEXT                NOT NULL,
    status         VARCHAR(16)         NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE psychologists (
    id              SERIAL PRIMARY KEY,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    license_number  VARCHAR(60),
    specialization  VARCHAR(120),
    invite_code     VARCHAR(16) UNIQUE NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE patients (
    id                         SERIAL PRIMARY KEY,
    user_id                    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    primary_psychologist_id    INTEGER REFERENCES psychologists(id),
    tree_stage                 INTEGER NOT NULL DEFAULT 1 CHECK (tree_stage BETWEEN 1 AND 5),
    focus_area                 VARCHAR(160),
    avatar_url                 VARCHAR(255),
    registration_code_used     VARCHAR(16),
    created_at                 TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE patient_psychologist (
    patient_id      INTEGER NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
    psychologist_id INTEGER NOT NULL REFERENCES psychologists(id) ON DELETE CASCADE,
    assigned_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (patient_id, psychologist_id)
);

CREATE TABLE emotion_categories (
    id           SERIAL PRIMARY KEY,
    slug         VARCHAR(32) UNIQUE NOT NULL,
    name         VARCHAR(64) NOT NULL,
    accent_color VARCHAR(16) NOT NULL
);

CREATE TABLE emotion_subcategories (
    id           SERIAL PRIMARY KEY,
    category_id  INTEGER NOT NULL REFERENCES emotion_categories(id) ON DELETE CASCADE,
    slug         VARCHAR(32) UNIQUE NOT NULL,
    name         VARCHAR(64) NOT NULL,
    description  TEXT
);

CREATE TABLE moods (
    id                      SERIAL PRIMARY KEY,
    patient_id              INTEGER NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
    mood_date               DATE    NOT NULL DEFAULT CURRENT_DATE,
    mood_level              INTEGER NOT NULL CHECK (mood_level BETWEEN 1 AND 5),
    intensity               INTEGER NOT NULL DEFAULT 5 CHECK (intensity BETWEEN 1 AND 10),
    emotion_category_id     INTEGER NOT NULL REFERENCES emotion_categories(id),
    emotion_subcategory_id  INTEGER REFERENCES emotion_subcategories(id),
    note                    TEXT,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE habits (
    id             SERIAL PRIMARY KEY,
    patient_id     INTEGER NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
    name           VARCHAR(120) NOT NULL,
    description    TEXT,
    frequency_goal INTEGER NOT NULL DEFAULT 5 CHECK (frequency_goal BETWEEN 1 AND 21),
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE habit_logs (
    id         SERIAL PRIMARY KEY,
    habit_id   INTEGER NOT NULL REFERENCES habits(id) ON DELETE CASCADE,
    log_date   DATE    NOT NULL DEFAULT CURRENT_DATE,
    completed  BOOLEAN NOT NULL DEFAULT TRUE,
    mood_level INTEGER CHECK (mood_level BETWEEN 1 AND 5),
    note       TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE badges (
    id          SERIAL PRIMARY KEY,
    patient_id  INTEGER NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
    code        VARCHAR(64) NOT NULL,
    label       VARCHAR(120) NOT NULL,
    description TEXT,
    awarded_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (patient_id, code)
);

CREATE TABLE chat_threads (
    id               SERIAL PRIMARY KEY,
    patient_id       INTEGER NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
    psychologist_id  INTEGER NOT NULL REFERENCES psychologists(id) ON DELETE CASCADE,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (patient_id, psychologist_id)
);

CREATE TABLE chat_messages (
    id              SERIAL PRIMARY KEY,
    thread_id       INTEGER NOT NULL REFERENCES chat_threads(id) ON DELETE CASCADE,
    sender_user_id  INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body            TEXT NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE analysis_entries (
    id              SERIAL PRIMARY KEY,
    psychologist_id INTEGER NOT NULL REFERENCES psychologists(id) ON DELETE CASCADE,
    patient_id      INTEGER NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
    title           VARCHAR(200) NOT NULL,
    content         TEXT NOT NULL,
    entry_date      DATE NOT NULL DEFAULT CURRENT_DATE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_moods_patient_date ON moods(patient_id, mood_date DESC);
CREATE INDEX idx_habit_logs_habit_date ON habit_logs(habit_id, log_date DESC);
CREATE INDEX idx_chat_messages_thread_created_at ON chat_messages(thread_id, created_at);
CREATE INDEX idx_analysis_entries_patient_date ON analysis_entries(patient_id, entry_date DESC);
CREATE INDEX idx_analysis_entries_psychologist_patient ON analysis_entries(psychologist_id, patient_id);

CREATE OR REPLACE VIEW v_patient_mood_summary AS
SELECT
    p.id AS patient_id,
    u.full_name,
    COUNT(m.id)                             AS entries,
    ROUND(AVG(m.mood_level)::numeric, 2)    AS average_level,
    ROUND(AVG(m.intensity)::numeric, 2)     AS average_intensity,
    MAX(m.mood_date)                        AS last_entry
FROM patients p
JOIN users u ON u.id = p.user_id
LEFT JOIN moods m ON m.patient_id = p.id
GROUP BY p.id, u.full_name;

CREATE OR REPLACE VIEW v_psychologist_patient_overview AS
SELECT
    psy.id AS psychologist_id,
    psy_user.full_name AS psychologist_name,
    COUNT(DISTINCT pp.patient_id)              AS patient_count,
    ROUND(AVG(m.mood_level)::numeric, 2)       AS avg_patient_mood,
    ROUND(AVG(m.intensity)::numeric, 2)        AS avg_patient_intensity
FROM psychologists psy
JOIN users psy_user ON psy_user.id = psy.user_id
LEFT JOIN patient_psychologist pp ON pp.psychologist_id = psy.id
LEFT JOIN moods m ON m.patient_id = pp.patient_id
GROUP BY psy.id, psy_user.full_name;

CREATE OR REPLACE VIEW v_patient_emotion_timeline AS
SELECT
    m.id,
    m.patient_id,
    m.mood_date,
    m.mood_level,
    m.intensity,
    ec.slug AS category_slug,
    ec.name AS category_name,
    ec.accent_color,
    es.slug AS subcategory_slug,
    es.name AS subcategory_name,
    m.note,
    m.created_at
FROM moods m
JOIN emotion_categories ec ON ec.id = m.emotion_category_id
LEFT JOIN emotion_subcategories es ON es.id = m.emotion_subcategory_id;

CREATE OR REPLACE FUNCTION calculate_patient_streak(p_patient_id INTEGER)
RETURNS INTEGER
LANGUAGE plpgsql
AS $$
DECLARE
    current_streak INTEGER;
BEGIN
    WITH completed_logs AS (
        SELECT DISTINCT hl.log_date::date AS log_date
        FROM habit_logs hl
        JOIN habits h ON h.id = hl.habit_id
        WHERE h.patient_id = p_patient_id
          AND hl.completed = TRUE
    ),
    streaks AS (
        SELECT log_date,
               log_date - (ROW_NUMBER() OVER (ORDER BY log_date)) * INTERVAL '1 day' AS grp
        FROM completed_logs
    )
    SELECT COALESCE(MAX(counted), 0)
    INTO current_streak
    FROM (
        SELECT COUNT(*) AS counted
        FROM streaks
        GROUP BY grp
    ) grouped;

    RETURN current_streak;
END;
$$;

CREATE OR REPLACE FUNCTION award_streak_badge()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    patient_id INTEGER;
    streak_length INTEGER;
BEGIN
    SELECT h.patient_id INTO patient_id
    FROM habits h
    WHERE h.id = NEW.habit_id;

    IF patient_id IS NULL THEN
        RETURN NEW;
    END IF;

    streak_length := calculate_patient_streak(patient_id);

    IF streak_length >= 7 THEN
        INSERT INTO badges (patient_id, code, label, description)
        VALUES (
            patient_id,
            'streak_7',
            '7 dni regularności',
            'Utrzymuj mikronawyki przez tydzień!'
        )
        ON CONFLICT (patient_id, code) DO NOTHING;
    END IF;

    IF streak_length >= 21 THEN
        INSERT INTO badges (patient_id, code, label, description)
        VALUES (
            patient_id,
            'streak_21',
            '21 dni nawyku',
            'Budujesz trwałą zmianę – ćwiczysz 21 dni z rzędu!'
        )
        ON CONFLICT (patient_id, code) DO NOTHING;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS tg_award_badge ON habit_logs;
CREATE TRIGGER tg_award_badge
AFTER INSERT ON habit_logs
FOR EACH ROW
EXECUTE FUNCTION award_streak_badge();

CREATE OR REPLACE FUNCTION award_welcome_badge()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO badges (patient_id, code, label, description)
    VALUES (NEW.id, 'welcome', 'Pierwszy krok', 'Witamy w MindGarden! Otrzymujesz odznakę powitalną.')
    ON CONFLICT (patient_id, code) DO NOTHING;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS tg_award_welcome ON patients;
CREATE TRIGGER tg_award_welcome
AFTER INSERT ON patients
FOR EACH ROW
EXECUTE FUNCTION award_welcome_badge();

CREATE OR REPLACE FUNCTION award_first_mood_badge()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    existing_count INTEGER;
BEGIN
    SELECT COUNT(*) INTO existing_count FROM moods WHERE patient_id = NEW.patient_id;
    IF existing_count = 1 THEN
        INSERT INTO badges (patient_id, code, label, description)
        VALUES (NEW.patient_id, 'first_mood', 'Pierwszy wpis nastroju', 'Dziękujemy za pierwszy wpis nastroju!')
        ON CONFLICT (patient_id, code) DO NOTHING;
    END IF;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS tg_award_first_mood ON moods;
CREATE TRIGGER tg_award_first_mood
AFTER INSERT ON moods
FOR EACH ROW
EXECUTE FUNCTION award_first_mood_badge();

CREATE OR REPLACE FUNCTION award_habit_goal_badge()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    p_id INTEGER;
    freq_goal INTEGER;
    completed_count INTEGER;
    code_text TEXT;
BEGIN
    SELECT h.patient_id, h.frequency_goal INTO p_id, freq_goal FROM habits h WHERE h.id = NEW.habit_id;
    IF p_id IS NULL THEN
        RETURN NEW;
    END IF;

    SELECT COUNT(*) INTO completed_count FROM habit_logs hl WHERE hl.habit_id = NEW.habit_id AND hl.completed = TRUE;

    IF freq_goal IS NOT NULL AND completed_count >= freq_goal THEN
        code_text := 'habit_goal_' || NEW.habit_id;
        INSERT INTO badges (patient_id, code, label, description)
        VALUES (p_id, code_text, 'Cel nawyku osiągnięty', 'Osiągnąłeś cel dla tego nawyku!')
        ON CONFLICT (patient_id, code) DO NOTHING;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS tg_award_habit_goal ON habit_logs;
CREATE TRIGGER tg_award_habit_goal
AFTER INSERT ON habit_logs
FOR EACH ROW
EXECUTE FUNCTION award_habit_goal_badge();


INSERT INTO emotion_categories (slug, name, accent_color) VALUES
('joy', 'Radość', '#2a9d8f'),
('sadness', 'Smutek', '#457b9d'),
('fear', 'Strach', '#264653'),
('anger', 'Złość', '#e76f51'),
('disgust', 'Wstręt', '#8ab17d');

INSERT INTO emotion_subcategories (category_id, slug, name, description)
SELECT ec.id, data.slug, data.name, data.description
FROM emotion_categories ec
JOIN (
    VALUES
        ('joy', 'gratitude', 'Wdzięczność', 'Docenianie tego, co masz.'),
        ('joy', 'satisfaction', 'Satysfakcja', 'Spokój i spełnienie.'),
        ('joy', 'excitement', 'Ekscytacja', 'Energia i radość.'),
        ('sadness', 'melancholy', 'Przygnębienie', 'Delikatny spadek nastroju.'),
        ('sadness', 'loneliness', 'Samotność', 'Poczucie izolacji.'),
        ('sadness', 'disappointment', 'Rozczarowanie', 'Niespełnione oczekiwania.'),
        ('fear', 'anxiety', 'Niepokój', 'Trudność w wyciszeniu.'),
        ('fear', 'stress', 'Stres', 'Napięcie i presja.'),
        ('fear', 'social-fear', 'Lęk społeczny', 'Niepewność przy innych.'),
        ('anger', 'frustration', 'Frustracja', 'Poczucie blokady.'),
        ('anger', 'irritation', 'Irytacja', 'Drobne napięcia.'),
        ('anger', 'impatience', 'Niecierpliwość', 'Chęć natychmiastowego działania.'),
        ('disgust', 'aversion', 'Niechęć', 'Unikanie sytuacji.'),
        ('disgust', 'shame', 'Wstyd', 'Chęć ukrycia się.'),
        ('disgust', 'resentment', 'Uraza', 'Trudność w przebaczeniu.')
) AS data(category_slug, slug, name, description)
ON ec.slug = data.category_slug;


WITH admin_user AS (
    INSERT INTO users (email, full_name, role, password_hash)
    VALUES ('admin@mindgarden.local', 'Anna Admin', 'administrator', crypt('admin123', gen_salt('bf')))
    RETURNING id
),
psychologist_user AS (
    INSERT INTO users (email, full_name, role, password_hash)
    VALUES ('psy1@mindgarden.local', 'Piotr Psycholog', 'psychologist', crypt('psy123', gen_salt('bf')))
    RETURNING id
),
psychologist_profile AS (
    INSERT INTO psychologists (user_id, license_number, specialization, invite_code)
    SELECT id, 'PTP-2025', 'Terapia ACT', 'DGSKY2'
    FROM psychologist_user
    RETURNING id, invite_code
),
patient_one_user AS (
    INSERT INTO users (email, full_name, role, password_hash)
    VALUES ('patient1@mindgarden.local', 'Paulina Pacjentka', 'patient', crypt('patient123', gen_salt('bf')))
    RETURNING id
),
patient_two_user AS (
    INSERT INTO users (email, full_name, role, password_hash)
    VALUES ('patient2@mindgarden.local', 'Marek Mindful', 'patient', crypt('patient123', gen_salt('bf')))
    RETURNING id
),
patient_one AS (
    INSERT INTO patients (user_id, primary_psychologist_id, tree_stage, focus_area, registration_code_used)
    SELECT patient_one_user.id, psychologist_profile.id, 3, 'Redukcja stresu', psychologist_profile.invite_code
    FROM patient_one_user, psychologist_profile
    RETURNING id
),
patient_two AS (
    INSERT INTO patients (user_id, primary_psychologist_id, tree_stage, focus_area, registration_code_used)
    SELECT patient_two_user.id, psychologist_profile.id, 2, 'Lepszy sen', psychologist_profile.invite_code
    FROM patient_two_user, psychologist_profile
    RETURNING id
)
INSERT INTO patient_psychologist (patient_id, psychologist_id)
SELECT p.id, psychologist_profile.id
FROM psychologist_profile, (SELECT id FROM patient_one UNION ALL SELECT id FROM patient_two) p;

INSERT INTO chat_threads (patient_id, psychologist_id)
SELECT patient_id, psychologist_id
FROM patient_psychologist
ON CONFLICT (patient_id, psychologist_id) DO NOTHING;

INSERT INTO moods (patient_id, mood_date, mood_level, intensity, emotion_category_id, emotion_subcategory_id, note)
VALUES
(
    (SELECT id FROM patients WHERE user_id = (SELECT id FROM users WHERE email = 'patient1@mindgarden.local')),
    CURRENT_DATE,
    4,
    7,
    (SELECT id FROM emotion_categories WHERE slug = 'joy'),
    (SELECT id FROM emotion_subcategories WHERE slug = 'gratitude'),
    'Spacer po lesie'
),
(
    (SELECT id FROM patients WHERE user_id = (SELECT id FROM users WHERE email = 'patient1@mindgarden.local')),
    (CURRENT_DATE - INTERVAL '1 day')::date,
    3,
    5,
    (SELECT id FROM emotion_categories WHERE slug = 'sadness'),
    (SELECT id FROM emotion_subcategories WHERE slug = 'melancholy'),
    'Krótka medytacja w samotności'
),
(
    (SELECT id FROM patients WHERE user_id = (SELECT id FROM users WHERE email = 'patient1@mindgarden.local')),
    (CURRENT_DATE - INTERVAL '2 days')::date,
    2,
    6,
    (SELECT id FROM emotion_categories WHERE slug = 'sadness'),
    (SELECT id FROM emotion_subcategories WHERE slug = 'loneliness'),
    'Zmęczenie po pracy'
),
(
    (SELECT id FROM patients WHERE user_id = (SELECT id FROM users WHERE email = 'patient2@mindgarden.local')),
    CURRENT_DATE,
    5,
    8,
    (SELECT id FROM emotion_categories WHERE slug = 'joy'),
    (SELECT id FROM emotion_subcategories WHERE slug = 'excitement'),
    'Udało się dokończyć zadanie'
),
(
    (SELECT id FROM patients WHERE user_id = (SELECT id FROM users WHERE email = 'patient2@mindgarden.local')),
    (CURRENT_DATE - INTERVAL '1 day')::date,
    4,
    4,
    (SELECT id FROM emotion_categories WHERE slug = 'fear'),
    (SELECT id FROM emotion_subcategories WHERE slug = 'stress'),
    'Wieczorna joga pomogła się wyciszyć'
);

INSERT INTO habits (patient_id, name, description, frequency_goal)
VALUES
(
    (SELECT id FROM patients WHERE user_id = (SELECT id FROM users WHERE email = 'patient1@mindgarden.local')),
    'Poranna medytacja',
    '5 minut uważności każdego poranka',
    5
),
(
    (SELECT id FROM patients WHERE user_id = (SELECT id FROM users WHERE email = 'patient1@mindgarden.local')),
    'Wieczorny dziennik',
    'Podsumowanie dnia i wdzięczności',
    4
),
(
    (SELECT id FROM patients WHERE user_id = (SELECT id FROM users WHERE email = 'patient2@mindgarden.local')),
    'Spacer po pracy',
    'Krótka przechadzka dla oddechu',
    5
);

INSERT INTO habit_logs (habit_id, log_date, completed, mood_level, note)
SELECT h.id, CURRENT_DATE - offs, TRUE, mood_level, note
FROM (VALUES
    ((SELECT id FROM habits WHERE name = 'Poranna medytacja'), 0, 4, 'Udało się skoncentrować'),
    ((SELECT id FROM habits WHERE name = 'Poranna medytacja'), 1, 3, 'Krótka praktyka'),
    ((SELECT id FROM habits WHERE name = 'Poranna medytacja'), 2, 4, 'Dzień rozpoczęty spokojnie'),
    ((SELECT id FROM habits WHERE name = 'Wieczorny dziennik'), 0, 4, 'Ciekawy dzień'),
    ((SELECT id FROM habits WHERE name = 'Spacer po pracy'), 0, 5, 'Spacer z przyjaciółką'),
    ((SELECT id FROM habits WHERE name = 'Spacer po pracy'), 1, 4, 'Krótki marsz')
) AS entries(habit_id, offs, mood_level, note)
JOIN habits h ON h.id = entries.habit_id;

INSERT INTO chat_messages (thread_id, sender_user_id, body)
VALUES
(
    (SELECT ct.id FROM chat_threads ct JOIN patients p ON p.id = ct.patient_id WHERE p.user_id = (SELECT id FROM users WHERE email = 'patient1@mindgarden.local')),
    (SELECT id FROM users WHERE email = 'patient1@mindgarden.local'),
    'Dzień dobry, dziś czułam spory stres w pracy.'
),
(
    (SELECT ct.id FROM chat_threads ct JOIN patients p ON p.id = ct.patient_id WHERE p.user_id = (SELECT id FROM users WHERE email = 'patient1@mindgarden.local')),
    (SELECT id FROM users WHERE email = 'psy1@mindgarden.local'),
    'Dzięki za wiadomość. Przypomnij proszę ćwiczenie oddechu z ostatniej sesji.'
);


DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'patients'
          AND column_name = 'registration_code_used'
    ) THEN
        ALTER TABLE patients ADD COLUMN registration_code_used VARCHAR(16);
    END IF;
END $$;

