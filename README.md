![MindGarden](public/assets/logo-leaf.svg)

# MindGarden — platforma mikronawyków dla zdrowia psychicznego

MindGarden to kompletna aplikacja webowa wspierająca pacjentów, psychologów i administratorów w pracy nad dobrostanem psychicznym poprzez mikronawyki, monitorowanie emocji i wsparcie psychologów. Projekt działa w architekturze Docker (nginx + php-fpm + PostgreSQL) i implementuje pełny przepływ MVC z autoryzacją ról, eksportem CSV oraz integracją AJAX/Chart.js.

## Spis treści
- [Najważniejsze funkcje](#najważniejsze-funkcje)
- [Architektura](#architektura)
- [Szybki start](#szybki-start)
- [Uruchamianie testów](#uruchamianie-testów)
- [Zmienne środowiskowe](#zmienne-środowiskowe)
- [Struktura bazy danych](#struktura-bazy-danych)
- [Role i uprawnienia](#role-i-uprawnienia)
- [Scenariusz testowy](#scenariusz-testowy)
- [Lista zrealizowanych funkcji](#lista-zrealizowanych-funkcji)

## Najważniejsze funkcje
- zielony, mobilny UI z trybem ciemnym,
- role (`patient`, `psychologist`, `administrator`) z osobnymi dashboardami i autoryzacją,
- rejestracja pacjenta z kodem terapeuty i automatycznym przypisaniem do psychologa,
- interaktywne koło emocji (kategorie + podopcje) z suwakiem intensywności 1–10,
- śledzenie nastrojów, mikronawyków, odznak i historii emocji (timeline + wykres Chart.js),
- czat pacjent–psycholog w czasie rzeczywistym (fetch + long polling) z historią rozmów,
- panel psychologa: lista pacjentów, analiza trendów (mood + intensity), eksport CSV, zarządzanie kodem zaproszenia i chatem,
- panel administratora: pełny CRUD użytkowników, przypisania pacjentów,
- baza danych w 3NF
- transakcje przy tworzeniu/aktualizacji użytkownika oraz retry łączy do Postgresa (`DB_CONNECT_RETRIES`, `DB_CONNECT_DELAY_MS`).

## Architektura
- Kontenery: `nginx`, `php-fpm (php:8.3-fpm-alpine)`, `postgres:16-alpine`, `pgAdmin`.
- MVC: kontrolery (`Security`, `Dashboard`, `Patient`, `Psychologist`, `Admin`, `Api`, `Settings`), modele, repozytoria, widoki w `public/views`.
- AJAX/Chart.js obsługiwane z `public/scripts`.

## Diagramy:
- ERD: 
  - PNG: [`docs/erd.png`](docs/erd.png) (wygenerowany w pgAdmin 4)
  - Źródło: pgAdmin 4 → Tools → Generate ERD (baza: `db` - domyślna nazwa, lub `mindgarden` jeśli zmieniona w konfiguracji)
  - Schemat SQL: [`database/init.sql`](database/init.sql)
- Architektura: [`docs/architecture.drawio`](docs/architecture.drawio) / [`docs/architecture.svg`](docs/architecture.svg)

## Screenshots
Zrzuty ekranu aplikacji znajdują się w folderze [`docs/screenshots/`](docs/screenshots/).

1. **Strona logowania** - `login.png`
2. **Strona rejestracji** - `register.png`
3. **Dashboard pacjenta** - `patient-dashboard.png`
4. **Koło emocji** - `emotion-wheel.png`
5. **Historia nastrojów pacjenta** - `patient-history.png`
6. **Chat pacjent-psycholog** - `chat.png`
7. **Dashboard psychologa** - `psychologist-dashboard.png`
8. **Analiza psychologa** - `psychologist-analysis.png`
9. **Panel administratora** - `admin-dashboard.png`
10. **Panel zarządzania użytkownikami** - `admin-user-list.png`
11. **Tryb ciemny** - `dark-mode.png`
12. **Widok mobilny** - `mobile-view.png`
13. **Strona błędu 404** - `error-404.png`


## Szybki start
1. Sklonuj projekt i przejdź do katalogu.
2. Utwórz plik `.env` na podstawie `.env.example` (domyślne wartości są zgodne z docker-compose; dostępne są także `DB_CONNECT_RETRIES` i `DB_CONNECT_DELAY_MS`).
3. Uruchom kontenery:
   ```bash
   docker-compose up --build
   ```
4. Aplikacja będzie dostępna pod `http://localhost:8080`.
5. PgAdmin jest dostępny pod `http://localhost:5050` (login: `admin@example.com`, hasło: `admin`).

## Uruchamianie testów

Projekt zawiera testy jednostkowe napisane w PHPUnit. Aby uruchomić testy w środowisku Docker:

### Wymagania wstępne
- Uruchomione kontenery Docker (`docker-compose up -d`)
- Zainstalowane zależności Composer (wykonane automatycznie przy pierwszym uruchomieniu)

### Uruchamianie testów

**Wszystkie testy jednostkowe:**
```bash
docker-compose exec php vendor/bin/phpunit --testsuite Unit
```

**Konkretny plik testowy:**
```bash
docker-compose exec php vendor/bin/phpunit tests/Unit/AuthServiceTest.php
```

**Wszystkie testy (Unit + Integration):**
```bash
docker-compose exec php vendor/bin/phpunit
```

### Uwagi
- Testy wymagają działającej bazy danych PostgreSQL (kontener `db` musi być uruchomiony)
- Wszystkie testy są wykonywane w kontenerze `php`
- Composer jest automatycznie dostępny w kontenerze PHP (zainstalowany w Dockerfile)

## Zmienne środowiskowe
Plik `.env.example` zawiera m.in.:
- `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`
- `SESSION_NAME`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
- `CSV_EXPORT_DIR` (domyślnie `/tmp`)

## Struktura bazy danych
- **Tabele (14):** `roles`, `users`, `psychologists`, `patients`, `patient_psychologist`, `emotion_categories`, `emotion_subcategories`, `moods`, `habits`, `habit_logs`, `badges`, `chat_threads`, `chat_messages`, `analysis_entries`
- **Widoki SQL (3):**
  - `v_patient_mood_summary` - podsumowanie nastrojów, nawyków i odznak pacjenta
  - `v_psychologist_patient_overview` - przegląd pacjentów przypisanych do psychologa
  - `v_patient_emotion_timeline` - timeline emocji pacjenta z kategoriami i podkategoriami
- **Funkcje (5):**
  - `calculate_patient_streak(patient_id integer)` - oblicza aktualną serię dni z ukończonymi nawykami
  - `award_streak_badge()` - przyznaje odznaki za serie (7 i 21 dni)
  - `award_welcome_badge()` - przyznaje odznakę powitalną nowym pacjentom
  - `award_first_mood_badge()` - przyznaje odznakę za pierwszy wpis nastroju
  - `award_habit_goal_badge()` - przyznaje odznakę za osiągnięcie celu nawyku
- **Triggery (4):**
  - `tg_award_badge` (na `habit_logs`, AFTER INSERT) - wywołuje `award_streak_badge()`
  - `tg_award_welcome` (na `patients`, AFTER INSERT) - wywołuje `award_welcome_badge()`
  - `tg_award_first_mood` (na `moods`, BEFORE INSERT) - wywołuje `award_first_mood_badge()`
  - `tg_award_habit_goal` (na `habit_logs`, AFTER INSERT) - wywołuje `award_habit_goal_badge()`
- **Referencje (klucz główny - klucz obcy):** wszystkie tabele używają relacji z kluczami obcymi (np. `users.id` → `patients.user_id`, `patients.id` → `moods.patient_id`, `psychologists.id` → `patient_psychologist.psychologist_id`). Zapytania z JOIN są używane w repozytoriach do łączenia danych z wielu tabel (np. `UserRepository::findByEmail`, `PatientRepository::listAllWithAssignments`, `PsychologistRepository::assignedPatients`, `MoodRepository`, `ChatRepository`)
- **Normalizacja:** baza danych spełnia 3 postacie normalne (3NF) - eliminacja redundancji, zależności funkcyjnych i zależności tranzytywnych
- **Transakcje:** `UserRepository::createUser`, `UserRepository::updateUser` (poziom izolacji SERIALIZABLE)
- **Dane startowe:** tworzone w `database/init.sql` (kontener Postgres) - kategorie emocji, podkategorie, przykładowi użytkownicy

## Role i uprawnienia
| Rola | Widok startowy | Uprawnienia |
|------|----------------|-------------|
| Pacjent | `/patient/dashboard` | interaktywne koło emocji, logowanie nastroju/intensywności, mikronawyki, drzewko, odznaki, eksport CSV, chat z psychologiem |
| Psycholog | `/psychologist/dashboard` | lista pacjentów, analiza trendów (mood + intensity), eksport CSV, chat, regeneracja kodu zaproszenia, odłączanie pacjentów |
| Administrator | `/admin/dashboard` | CRUD wszystkich użytkowników (pacjenci/psychologowie/admini), przypisywanie pacjentów, podgląd kodów zaproszeń |

## Scenariusz testowy

### 1. Logowanie i weryfikacja ról
- **Logowanie jako Pacjent**: `patient1@mindgarden.local` / `patient123`
  - Sprawdź przekierowanie do `/patient/dashboard`
  - Zweryfikuj, że widzisz tylko funkcje dostępne dla pacjenta
- **Logowanie jako Psycholog**: `psy1@mindgarden.local` / `psy123`
  - Sprawdź przekierowanie do `/psychologist/dashboard`
  - Zweryfikuj listę przypisanych pacjentów
- **Logowanie jako Administrator**: `admin@mindgarden.local` / `admin123`
  - Sprawdź przekierowanie do `/admin/dashboard`
  - Zweryfikuj pełny dostęp do zarządzania użytkownikami

### 2. Test błędów autoryzacji (401/403)
- **401 Unauthorized**:
  - Wyloguj się i spróbuj otworzyć `/patient/dashboard` bezpośrednio w przeglądarce
  - Sprawdź przekierowanie do `/login`
  - Wykonaj żądanie AJAX bez aktywnej sesji (np. dodanie nastroju) - sprawdź odpowiedź 401
- **403 Forbidden**:
  - Zaloguj się jako pacjent i spróbuj otworzyć `/admin/dashboard` - sprawdź stronę błędu 403
  - Zaloguj się jako pacjent i spróbuj otworzyć `/psychologist/dashboard` - sprawdź stronę błędu 403
  - Zaloguj się jako psycholog i spróbuj otworzyć `/admin/dashboard` - sprawdź stronę błędu 403
  - Zaloguj się jako pacjent i spróbuj wyeksportować dane innego pacjenta - sprawdź odpowiedź 403

### 3. CRUD - Operacje na użytkownikach (Administrator)
- **CREATE (Utworzenie)**:
  - Zaloguj się jako administrator
  - Utwórz nowe konto pacjenta (email, hasło, imię i nazwisko, rola: `patient`)
  - Utwórz nowe konto psychologa (email, hasło, imię i nazwisko, rola: `psychologist`, numer licencji, specjalizacja)
  - Utwórz nowe konto administratora (email, hasło, imię i nazwisko, rola: `administrator`)
- **READ (Odczyt)**:
  - Sprawdź listę wszystkich użytkowników w panelu administratora
  - Zweryfikuj wyświetlanie danych: imię, nazwisko, email, rola, status, data utworzenia
  - Sprawdź listę pacjentów z przypisaniami do psychologów
- **UPDATE (Aktualizacja)**:
  - Zaktualizuj dane użytkownika (zmień imię i nazwisko)
  - Zmień hasło użytkownika
  - Zaktualizuj status użytkownika (active/inactive)
  - Dla pacjenta: zaktualizuj `tree_stage`, `focus_area`, przypisz psychologa
  - Dla psychologa: zaktualizuj `license_number`, `specialization`
- **DELETE (Usunięcie)**:
  - Usuń testowe konto użytkownika (potwierdź operację)
  - Zweryfikuj, że konto zostało usunięte z listy

### 4. CRUD - Operacje na danych pacjenta
- **Rejestracja nowego pacjenta**:
  - Wyloguj się i przejdź do `/register`
  - Zarejestruj nowego pacjenta z kodem zaproszenia `DGSKY2`
  - Zweryfikuj automatyczne przypisanie do psychologa
- **Dodawanie nastrojów (CREATE)**:
  - Zaloguj się jako pacjent
  - Przejdź do koła emocji i wybierz kategorię (np. "Radość")
  - Wybierz podkategorię (np. "Wdzięczność")
  - Ustaw intensywność na suwaku (1-10)
  - Dodaj wpis nastroju
  - Sprawdź aktualizację wykresu Chart.js i timeline
- **Przeglądanie historii nastrojów (READ)**:
  - Przejdź do `/patient/history`
  - Sprawdź wyświetlanie wszystkich wpisów nastrojów
- **Mikronawyki (CREATE/READ)**:
  - Dodaj nowy mikronawyk (nazwa, opis, cel częstotliwości)
  - Sprawdź listę nawyków na dashboardzie
  - Odnotuj nawyk (zaznacz jako wykonany)
  - Sprawdź postęp w realizacji celu

### 5. Test widoków SQL
- **v_patient_mood_summary**:
  - Zaloguj się jako pacjent
  - Sprawdź dashboard - dane pochodzą z widoku `v_patient_mood_summary`
  - Zweryfikuj wyświetlanie: średni nastrój, średnia intensywność, liczba wpisów, aktywne nawyki, odznaki
- **v_psychologist_patient_overview**:
  - Zaloguj się jako psycholog
  - Sprawdź dashboard - dane pochodzą z widoku `v_psychologist_patient_overview`
  - Zweryfikuj wyświetlanie: liczba pacjentów, średni nastrój pacjentów, średnia intensywność
- **v_patient_emotion_timeline**:
  - Zaloguj się jako pacjent
  - Przejdź do `/patient/history`
  - Sprawdź timeline emocji - dane pochodzą z widoku `v_patient_emotion_timeline`
  - Zweryfikuj wyświetlanie kategorii, podkategorii, dat, poziomów nastroju

### 6. Test wyzwalaczy (triggery)
- **tg_award_welcome** (trigger na `patients` AFTER INSERT):
  - Zarejestruj nowego pacjenta
  - Sprawdź w dashboardzie pacjenta, że automatycznie otrzymał odznakę "Pierwszy krok" (welcome)
  - Zweryfikuj w bazie danych (pgAdmin): `SELECT * FROM badges WHERE patient_id = X AND code = 'welcome'`
- **tg_award_first_mood** (trigger na `moods` BEFORE INSERT):
  - Zaloguj się jako nowy pacjent (bez wcześniejszych wpisów nastroju)
  - Dodaj pierwszy wpis nastroju
  - Sprawdź, że automatycznie otrzymał odznakę "Pierwszy wpis nastroju" (first_mood)
  - Zweryfikuj w bazie danych: `SELECT * FROM badges WHERE patient_id = X AND code = 'first_mood'`
- **tg_award_badge** (trigger na `habit_logs` AFTER INSERT - serie):
  - Zaloguj się jako pacjent
  - Utwórz mikronawyk
  - Odnotuj nawyk przez 7 kolejnych dni
  - Sprawdź, że po 7 dniach automatycznie otrzymał odznakę "7 dni regularności" (streak_7)
  - Kontynuuj odnotowywanie przez 21 dni
  - Sprawdź, że po 21 dniach automatycznie otrzymał odznakę "21 dni nawyku" (streak_21)
  - Zweryfikuj w bazie danych: `SELECT * FROM badges WHERE patient_id = X AND code IN ('streak_7', 'streak_21')`
- **tg_award_habit_goal** (trigger na `habit_logs` AFTER INSERT - cel nawyku):
  - Zaloguj się jako pacjent
  - Utwórz mikronawyk z celem częstotliwości (np. 5 razy)
  - Odnotuj nawyk tyle razy, ile wynosi cel (np. 5 razy)
  - Sprawdź, że automatycznie otrzymał odznakę "Cel nawyku osiągnięty" (habit_goal_X)
  - Zweryfikuj w bazie danych: `SELECT * FROM badges WHERE patient_id = X AND code LIKE 'habit_goal_%'`

### 7. Funkcjonalności psychologa
- **Lista pacjentów**:
  - Zaloguj się jako psycholog
  - Sprawdź listę przypisanych pacjentów
  - Zweryfikuj wyświetlanie danych z widoku `v_psychologist_patient_overview`
- **Analiza trendów**:
  - Wybierz pacjenta z listy
  - Przejdź do analizy (`/psychologist/analysis`)
  - Sprawdź wykresy trendów nastroju i intensywności
  - Zmień zakres czasowy (tygodniowy, miesięczny, roczny)
- **Eksport CSV**:
  - Wybierz pacjenta
  - Kliknij "Eksportuj CSV"
  - Sprawdź, że plik został wygenerowany i zawiera dane pacjenta
- **Zarządzanie kodem zaproszenia**:
  - Przejdź do ustawień psychologa (`/psychologist/settings`)
  - Wygeneruj nowy kod zaproszenia
  - Sprawdź, że stary kod przestał działać, a nowy działa
- **Chat z pacjentem**:
  - Otwórz czat z pacjentem
  - Odczytaj wiadomość od pacjenta
  - Wyślij odpowiedź
  - Sprawdź historię rozmowy
- **Odłączanie pacjenta**:
  - Odłącz testowego pacjenta z listy
  - Sprawdź, że pacjent nie widzi już psychologa w czacie

### 8. Funkcjonalności pacjenta
- **Dashboard**:
  - Sprawdź podsumowanie z widoku `v_patient_mood_summary`
  - Zweryfikuj wyświetlanie aktualnej serii (streak)
  - Sprawdź postęp drzewka (tree_stage)
- **Koło emocji**:
  - Wybierz kategorię emocji
  - Wybierz podkategorię
  - Ustaw intensywność (1-10)
  - Dodaj notatkę (opcjonalnie)
  - Dodaj wpis i sprawdź aktualizację wykresu
- **Historia nastrojów**:
  - Przejdź do `/patient/history`
  - Sprawdź timeline z widoku `v_patient_emotion_timeline`
  - Zweryfikuj filtrowanie po datach
- **Mikronawyki**:
  - Dodaj nowy nawyk
  - Odnotuj wykonanie nawyku
  - Sprawdź postęp w realizacji celu
  - Zweryfikuj aktualizację serii (streak)
- **Odznaki**:
  - Sprawdź listę otrzymanych odznak
  - Zweryfikuj, że odznaki są przyznawane automatycznie przez triggery
- **Chat z psychologiem**:
  - Otwórz czat z psychologiem
  - Wyślij wiadomość
  - Sprawdź historię rozmowy
- **Eksport CSV**:
  - Wyeksportuj swoje dane do CSV
  - Sprawdź zawartość pliku

### 9. Przypisywanie pacjentów (Administrator)
- Zaloguj się jako administrator
- Wybierz pacjenta z listy
- Wybierz psychologa z listy
- Przypisz pacjenta do psychologa
- Sprawdź, że przypisanie zostało utworzone w tabeli `patient_psychologist`
- Zweryfikuj, że pacjent widzi psychologa w czacie

## Lista zrealizowanych funkcji
- kompletna warstwa Docker + automatyczne seedowanie Postgres,
- modularne MVC z autoloadingiem (Autoloader),
- obsługa sesji i roli, ochrona paneli, flash messages,
- dynamiczne dashboardy (AJAX, interaktywne koło emocji, dualne wykresy Chart.js, tryb ciemny),
- chat pacjent–psycholog, eksport CSV, transakcje, odznaki i trigger streaków,
- dokumentacja + diagramy + scenariusz testowy.

## Checklista wymagań projektowych

### Technologie
- [x] Docker (docker-compose.yml, nginx, php-fpm, postgres)
- [x] GIT (publiczne repozytorium GitHub)
- [x] HTML5 (widoki w public/views/)
- [x] CSS (responsywność, media queries, tryb ciemny)
- [x] JavaScript z Fetch API (AJAX, Chart.js)
- [x] PHP obiektowy (MVC, SOLID, bez frameworka)
- [x] PostgreSQL (relacje, widoki, triggery, funkcje)

### Architektura
- [x] MVC (controllers, models, repository, views)
- [x] Bezpieczeństwo (CSRF, bcrypt, prepared statements, session regeneration)

### Baza danych
- [x] Relacja 1:N (users→patients, patients→moods)
- [x] Relacja N:M (patient_psychologist)
- [x] Relacja 1:1 (users↔patients, users↔psychologists)
- [x] 3 widoki SQL (v_patient_mood_summary, v_psychologist_patient_overview, v_patient_emotion_timeline)
- [x] 4 triggery (tg_award_badge, tg_award_welcome, tg_award_first_mood, tg_award_habit_goal)
- [x] 5 funkcji (calculate_patient_streak + funkcje odznak)
- [x] Transakcje z poziomem izolacji SERIALIZABLE
- [x] Spełnione 3 postacie normalne (3NF)
- [x] Eksport SQL (database/init.sql)

### Dokumentacja
- [x] README.md z instrukcją uruchomienia
- [x] Diagram ERD (docs/erd.png)
- [x] Diagram architektury (docs/architecture.drawio, docs/architecture.svg)
- [x] Screenshots (docs/screenshots/)
- [x] Plik .env.example
- [x] Scenariusz testowy

### Testy
- [x] PHPUnit (tests/Unit/UserTest.php, tests/Unit/AuthServiceTest.php)
- [x] Testy integracyjne (tests/integration_test.ps1, tests/integration_test.sh)
- [x] Obsługa błędów (strony 400, 403, 404, 500)

### Funkcjonalności
- [x] Logowanie i rejestracja z kodem terapeuty
- [x] 3 role użytkowników (patient, psychologist, administrator)
- [x] Interaktywne koło emocji z subcategoriami
- [x] Śledzenie nastrojów i mikronawyków
- [x] System odznak (badges) z triggerami
- [x] Chat pacjent–psycholog w czasie rzeczywistym
- [x] Eksport danych do CSV
- [x] Panel administratora (CRUD użytkowników)
- [x] Tryb ciemny (dark mode)
- [x] Responsywny design (mobile-first)

