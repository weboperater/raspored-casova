<?php
/**
 * Raspored Časova OG1 — Database Layer
 * SQLite + PDO | Full init + seed data from both schedule images
 */

$envPath = dirname(__DIR__) . '/config/env.php';
if (is_file($envPath)) {
    require_once $envPath;
}

define('DB_PATH', dirname(__DIR__) . '/data/schedule.db');
define('DATA_DIR', dirname(__DIR__) . '/data');

function getDB(): PDO {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');
    initDB($db);
    return $db;
}

function initDB(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key   TEXT PRIMARY KEY,
        value TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS admins (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        username   TEXT UNIQUE NOT NULL,
        password   TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS periods (
        period         INTEGER PRIMARY KEY,
        start_time     TEXT NOT NULL,
        end_time       TEXT NOT NULL,
        break_after_min INTEGER DEFAULT 5,
        break_type     TEXT DEFAULT 'small'
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS subjects (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        name       TEXT NOT NULL UNIQUE,
        short_name TEXT,
        teacher    TEXT,
        color      TEXT DEFAULT '#6366f1',
        emoji      TEXT DEFAULT '📚',
        sort_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS schedule (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        week_type  TEXT NOT NULL CHECK(week_type IN ('A','B')),
        day        INTEGER NOT NULL CHECK(day BETWEEN 1 AND 5),
        period     INTEGER NOT NULL CHECK(period BETWEEN 1 AND 12),
        subject_id INTEGER REFERENCES subjects(id) ON DELETE SET NULL,
        room       TEXT,
        notes      TEXT,
        is_double  INTEGER DEFAULT 0,
        UNIQUE(week_type, day, period)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS viber_templates (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        name       TEXT NOT NULL,
        content    TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        identifier   TEXT NOT NULL,
        username     TEXT NOT NULL,
        ip_address   TEXT NOT NULL,
        success      INTEGER NOT NULL DEFAULT 0,
        attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_identifier_time
        ON login_attempts(identifier, attempted_at)");

    $db->exec("CREATE TABLE IF NOT EXISTS admin_audit_log (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_id    INTEGER,
        admin_user  TEXT,
        action      TEXT NOT NULL,
        entity_type TEXT,
        entity_id   TEXT,
        details     TEXT,
        ip_address  TEXT,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_admin_audit_log_created
        ON admin_audit_log(created_at)");

    // ── SETTINGS ──────────────────────────────────────────────────────
    $defaults = [
        'class_name'        => 'OG1',
        'school_name'       => 'Srednja škola',
        'base_iso_week'     => '10',   // ISO week 10 of 2026 = type A
        'base_iso_year'     => '2026',
        'base_week_type'    => 'A',    // week 10 = A
        'initialized'       => '0',
    ];
    $stmt = $db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    foreach ($defaults as $k => $v) { $stmt->execute([$k, $v]); }

    // ── MIGRATIONS (idempotent — run every load, cheap PRAGMA checks) ────
    $pcols = array_column($db->query("PRAGMA table_info(periods)")->fetchAll(), 'name');
    if (!in_array('break_after_min', $pcols)) {
        $db->exec("ALTER TABLE periods ADD COLUMN break_after_min INTEGER DEFAULT 5");
        $db->exec("ALTER TABLE periods ADD COLUMN break_type TEXT DEFAULT 'small'");
        // Restore the 40-minute big break after period 3 (matches original schedule)
        $db->exec("UPDATE periods SET break_after_min=40, break_type='big' WHERE period=3");
    }
    $scols = array_column($db->query("PRAGMA table_info(schedule)")->fetchAll(), 'name');
    if (!in_array('is_double', $scols)) {
        $db->exec("ALTER TABLE schedule ADD COLUMN is_double INTEGER DEFAULT 0");
    }
    migrateSchedulePeriodLimit($db);

    // New settings for period configurator
    $newSettings = [
        'first_class_time'   => '08:30',
        'class_duration_min' => '45',
        'small_break_min'    => '5',
        'big_breaks_json'    => '[{"after_period":3,"duration_min":40}]',
        'period_count'       => '8',
    ];
    $sm = $db->prepare("INSERT OR IGNORE INTO settings (key,value) VALUES (?,?)");
    foreach ($newSettings as $k => $v) { $sm->execute([$k, $v]); }

    // Skip seed if already done
    $init = $db->query("SELECT value FROM settings WHERE key='initialized'")->fetchColumn();
    if ($init === '1') return;

    // ── ADMIN USER ────────────────────────────────────────────────────
    $initialAdminUsername = defined('ADMIN_INITIAL_USERNAME') ? trim((string)ADMIN_INITIAL_USERNAME) : '';
    $initialAdminPassword = defined('ADMIN_INITIAL_PASSWORD') ? (string)ADMIN_INITIAL_PASSWORD : '';
    if ($initialAdminUsername !== '' && $initialAdminPassword !== '') {
        $db->prepare("INSERT OR IGNORE INTO admins (username, password) VALUES (?,?)")
           ->execute([
               strtolower($initialAdminUsername),
               password_hash($initialAdminPassword, PASSWORD_BCRYPT),
           ]);
    }

    // ── PERIODS ─────────────────────────────────────────────────────────
    // [period, start, end, break_after_min, break_type]
    $periods = [
        [1, '08:30', '09:15',  5, 'small'],
        [2, '09:20', '10:05',  5, 'small'],
        [3, '10:10', '10:55', 40, 'big'],   // veliki odmor
        [4, '11:35', '12:20',  5, 'small'],
        [5, '12:25', '13:10',  5, 'small'],
        [6, '13:15', '14:00',  5, 'small'],
        [7, '14:05', '14:50',  5, 'small'],
        [8, '14:55', '15:40',  0, 'small'], // last period, no break
    ];
    $stmt = $db->prepare("INSERT OR IGNORE INTO periods (period, start_time, end_time, break_after_min, break_type) VALUES (?,?,?,?,?)");
    foreach ($periods as $p) { $stmt->execute($p); }

    // ── SUBJECTS ──────────────────────────────────────────────────────
    // [name, short, teacher, color, emoji]
    $subjects = [
        ['Matematika',                   'Mat',  'Goran Taseski',          '#3b82f6', '🔢'],
        ['Srpski jezik i književnost',   'SJK',  'Danka',                  '#22c55e', '📖'],
        ['Geografija',                   'Geo',  'Ana Tomašević Petrović', '#f97316', '🌍'],
        ['Engleski jezik',               'Eng',  'Jelena Spasić',          '#a855f7', '🇬🇧'],
        ['Fizika',                       'Fiz',  'Biljana Šomođa',         '#ef4444', '⚡'],
        ['Hemija',                       'Hem',  'Miloš Kozić',            '#eab308', '🧪'],
        ['Istorija',                     'Ist',  'Valentina Erić',         '#b45309', '🏛️'],
        ['Biologija',                    'Bio',  'Sanja Blagojević',       '#14b8a6', '🌿'],
        ['Muzička kultura',              'Muz',  'Ivana Bogić',            '#ec4899', '🎵'],
        ['Likovna kultura',              'Lik',  'Nebojša Špica',          '#8b5cf6', '🎨'],
        ['JMK',                          'JMK',  'Zorana Rajanović',       '#64748b', '📰'],
        ['Latinski jezik',               'Lat',  'Irina Vojvodić',         '#d97706', '🏺'],
        ['ČOS',                          'ČOS',  'Dorotea',                '#6b7280', '💬'],
        ['Fizičko vaspitanje',           'Fiz.V','',                       '#84cc16', '⚽'],
        ['Verska nastava',               'Ver',  '',                       '#7c3aed', '✝️'],
        ['Drugi strani jezik',           'DSJ',  '',                       '#06b6d4', '🗣️'],
        ['UD',                           'UD',   'Nebojša Špica',          '#f43f5e', '🎭'],
        ['Računarstvo i informatika',    'Inf',  'Predrag Pavlović',       '#0ea5e9', '💻'],
        ['Umetnost i dizajn',            'U&D',  'Nikola Špica',           '#d946ef', '🖌️'],
    ];
    $stmt = $db->prepare("INSERT OR IGNORE INTO subjects (name, short_name, teacher, color, emoji) VALUES (?,?,?,?,?)");
    foreach ($subjects as $s) { $stmt->execute($s); }

    // Build subject name→id map
    $sMap = [];
    foreach ($db->query("SELECT id, name FROM subjects")->fetchAll() as $row) {
        $sMap[$row['name']] = $row['id'];
    }

    // ── SCHEDULE WEEK A (from first image) ────────────────────────────
    // [week_type, day(1=Mon…5=Fri), period, subject_name]
    $scheduleA = [
        // Ponedeljak
        ['A', 1, 4, 'ČOS'],
        ['A', 1, 5, 'Fizika'],
        ['A', 1, 6, 'Fizičko vaspitanje'],
        ['A', 1, 7, 'JMK'],
        ['A', 1, 8, 'Biologija'],
        // Utorak
        ['A', 2, 1, 'Matematika'],
        ['A', 2, 2, 'Drugi strani jezik'],
        ['A', 2, 3, 'Srpski jezik i književnost'],
        ['A', 2, 4, 'Geografija'],
        // Sreda
        ['A', 3, 1, 'Srpski jezik i književnost'],
        ['A', 3, 2, 'Geografija'],
        ['A', 3, 3, 'Geografija'],
        ['A', 3, 4, 'Srpski jezik i književnost'],
        ['A', 3, 5, 'JMK'],
        ['A', 3, 6, 'Matematika'],
        ['A', 3, 7, 'Matematika'],
        ['A', 3, 8, 'Hemija'],
        // Četvrtak
        ['A', 4, 2, 'Latinski jezik'],
        ['A', 4, 3, 'Engleski jezik'],
        ['A', 4, 4, 'Istorija'],
        ['A', 4, 5, 'Muzička kultura'],
        ['A', 4, 6, 'Verska nastava'],
        ['A', 4, 7, 'Likovna kultura'],
        ['A', 4, 8, 'UD'],
        // Petak
        ['A', 5, 1, 'Matematika'],
        ['A', 5, 2, 'Drugi strani jezik'],
        ['A', 5, 3, 'Istorija'],
        ['A', 5, 4, 'Engleski jezik'],
        ['A', 5, 5, 'Hemija'],
        ['A', 5, 6, 'Fizika'],
        ['A', 5, 7, 'Latinski jezik'],
        ['A', 5, 8, 'Biologija'],
    ];

    // ── SCHEDULE WEEK B (from second image) ───────────────────────────
    $scheduleB = [
        // Ponedeljak
        ['B', 1, 1, 'Drugi strani jezik'],
        ['B', 1, 2, 'Drugi strani jezik'],
        ['B', 1, 3, 'Hemija'],
        ['B', 1, 4, 'Hemija'],
        ['B', 1, 5, 'Fizika'],
        ['B', 1, 6, 'JMK'],
        ['B', 1, 7, 'Matematika'],
        ['B', 1, 8, 'Biologija'],
        // Utorak
        ['B', 2, 1, 'Srpski jezik i književnost'],
        ['B', 2, 2, 'Srpski jezik i književnost'],
        ['B', 2, 3, 'ČOS'],
        ['B', 2, 4, 'Engleski jezik'],
        ['B', 2, 5, 'Geografija'],
        ['B', 2, 6, 'Matematika'],
        // Sreda
        ['B', 3, 4, 'Geografija'],
        ['B', 3, 5, 'Umetnost i dizajn'],
        ['B', 3, 6, 'Likovna kultura'],
        ['B', 3, 7, 'Matematika'],
        ['B', 3, 8, 'Računarstvo i informatika'],
        // Četvrtak
        ['B', 4, 1, 'Istorija'],
        ['B', 4, 3, 'Srpski jezik i književnost'],
        ['B', 4, 4, 'Muzička kultura'],
        ['B', 4, 5, 'Latinski jezik'],
        ['B', 4, 6, 'Matematika'],
        // Petak
        ['B', 5, 2, 'Srpski jezik i književnost'],
        ['B', 5, 3, 'Engleski jezik'],
        ['B', 5, 4, 'Istorija'],
        ['B', 5, 5, 'Fizičko vaspitanje'],
        ['B', 5, 6, 'Latinski jezik'],
        ['B', 5, 7, 'Fizika'],
        ['B', 5, 8, 'Biologija'],
    ];

    $stmt = $db->prepare("INSERT OR IGNORE INTO schedule (week_type, day, period, subject_id) VALUES (?,?,?,?)");
    foreach (array_merge($scheduleA, $scheduleB) as $entry) {
        $sid = $sMap[$entry[3]] ?? null;
        $stmt->execute([$entry[0], $entry[1], $entry[2], $sid]);
    }

    // ── VIBER TEMPLATES ───────────────────────────────────────────────
    $templates = [
        ['Izmena rasporeda',
         "Dragi roditelji i učenici,\n\nObaveštavamo vas da je došlo do izmene u rasporedu časova za narednu nedelju.\n\n[Unesite detalje izmene]\n\nSa poštovanjem,\nRazredna starešina"],
        ['Vanredni čas',
         "Dragi roditelji,\n\nObaveštavamo vas da je zakazan vanredni čas iz [predmeta] u [datum] u [vreme].\n\nSa poštovanjem,\nRazredna starešina"],
        ['Otkazivanje nastave',
         "Dragi roditelji i učenici,\n\nObaveštavamo vas da je nastava [dan], [datum] otkazana zbog [razlog].\n\nSa poštovanjem,\nRazredna starešina"],
    ];
    $stmt = $db->prepare("INSERT OR IGNORE INTO viber_templates (name, content) VALUES (?,?)");
    foreach ($templates as $t) { $stmt->execute($t); }

    // Mark initialized
    $db->prepare("UPDATE settings SET value='1' WHERE key='initialized'")->execute();
}

// ── HELPERS ───────────────────────────────────────────────────────────────────

function migrateSchedulePeriodLimit(PDO $db): void {
    $sql = (string)$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='schedule'")->fetchColumn();
    if (!str_contains($sql, 'period BETWEEN 1 AND 8')) {
        return;
    }

    $db->exec('PRAGMA foreign_keys = OFF');
    $db->beginTransaction();
    try {
        $db->exec("CREATE TABLE schedule_new (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            week_type  TEXT NOT NULL CHECK(week_type IN ('A','B')),
            day        INTEGER NOT NULL CHECK(day BETWEEN 1 AND 5),
            period     INTEGER NOT NULL CHECK(period BETWEEN 1 AND 12),
            subject_id INTEGER REFERENCES subjects(id) ON DELETE SET NULL,
            room       TEXT,
            notes      TEXT,
            is_double  INTEGER DEFAULT 0,
            UNIQUE(week_type, day, period)
        )");
        $db->exec("INSERT INTO schedule_new (id, week_type, day, period, subject_id, room, notes, is_double)
            SELECT id, week_type, day, period, subject_id, room, notes, is_double FROM schedule");
        $db->exec("DROP TABLE schedule");
        $db->exec("ALTER TABLE schedule_new RENAME TO schedule");
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $db->exec('PRAGMA foreign_keys = ON');
        throw $e;
    }
    $db->exec('PRAGMA foreign_keys = ON');
}

function getSetting(PDO $db, string $key, string $default = ''): string {
    $row = $db->prepare("SELECT value FROM settings WHERE key=?");
    $row->execute([$key]);
    return $row->fetchColumn() ?: $default;
}

function setSetting(PDO $db, string $key, string $value): void {
    $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?,?)")->execute([$key, $value]);
}

function dbRecordExists(PDO $db, string $table, int $id): bool {
    $allowedTables = ['subjects', 'viber_templates'];
    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Invalid table');
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM $table WHERE id=?");
    $stmt->execute([$id]);
    return (int)$stmt->fetchColumn() > 0;
}

function logAdminAction(
    PDO $db,
    string $action,
    ?string $entityType = null,
    ?string $entityId = null,
    array $details = []
): void {
    $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
    $adminUser = isset($_SESSION['admin_user']) ? (string)$_SESSION['admin_user'] : null;
    $ipAddress = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $detailsJson = $details === []
        ? null
        : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $db->prepare("
        INSERT INTO admin_audit_log (admin_id, admin_user, action, entity_type, entity_id, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([$adminId, $adminUser, $action, $entityType, $entityId, $detailsJson, $ipAddress]);
}

function getRecentAdminAuditLogs(PDO $db, int $limit = 10): array {
    $limit = max(1, min(50, $limit));
    return $db->query("
        SELECT admin_user, action, entity_type, entity_id, details, created_at
        FROM admin_audit_log
        ORDER BY datetime(created_at) DESC, id DESC
        LIMIT $limit
    ")->fetchAll();
}

function getAdminAuditLogs(PDO $db, int $limit = 100, string $action = '', string $entityType = ''): array {
    $limit = max(1, min(500, $limit));
    $where = [];
    $params = [];

    if ($action !== '') {
        $where[] = 'action = ?';
        $params[] = $action;
    }
    if ($entityType !== '') {
        $where[] = 'entity_type = ?';
        $params[] = $entityType;
    }

    $sql = "
        SELECT admin_user, action, entity_type, entity_id, details, ip_address, created_at
        FROM admin_audit_log
    ";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY datetime(created_at) DESC, id DESC LIMIT $limit";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getAdminAuditFilterValues(PDO $db, string $column): array {
    if (!in_array($column, ['action', 'entity_type'], true)) {
        throw new InvalidArgumentException('Invalid audit filter column');
    }

    return array_values(array_filter($db->query("
        SELECT DISTINCT $column AS value
        FROM admin_audit_log
        WHERE $column IS NOT NULL AND $column != ''
        ORDER BY $column
    ")->fetchAll(PDO::FETCH_COLUMN)));
}

/**
 * Determine week type (A or B) for any ISO week/year.
 * Uses the base_iso_week/year/type stored in settings.
 */
function calcWeekType(PDO $db, int $isoYear, int $isoWeek): string {
    $baseWeek = (int)getSetting($db, 'base_iso_week', '10');
    $baseYear = (int)getSetting($db, 'base_iso_year', '2026');
    $baseType = getSetting($db, 'base_week_type', 'A');

    $base = new DateTime();
    $base->setISODate($baseYear, $baseWeek, 1);
    $cur  = new DateTime();
    $cur->setISODate($isoYear, $isoWeek, 1);

    $diff = (int)round(($cur->getTimestamp() - $base->getTimestamp()) / (7 * 86400));
    $sameType = ($diff % 2 === 0);
    if ($sameType) return $baseType;
    return $baseType === 'A' ? 'B' : 'A';
}

function getScheduleByDay(PDO $db, string $weekType): array {
    $stmt = $db->prepare("
        SELECT s.day, s.period, s.room, s.notes, s.is_double,
               sub.name AS subject, sub.short_name, sub.teacher, sub.color, sub.emoji, sub.id AS subject_id,
               p.start_time, p.end_time, p.break_after_min, p.break_type
        FROM schedule s
        LEFT JOIN subjects sub ON sub.id = s.subject_id
        LEFT JOIN periods p ON p.period = s.period
        WHERE s.week_type = ?
        ORDER BY s.day, s.period
    ");
    $stmt->execute([$weekType]);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $result[$row['day']][] = $row;
    }
    return $result;
}

function getAllSubjects(PDO $db): array {
    return $db->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
}

function getPeriods(PDO $db): array {
    return $db->query("SELECT * FROM periods ORDER BY period")->fetchAll();
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Auto-generate period start/end times from school day settings.
 * Reads: first_class_time, class_duration_min, small_break_min, big_breaks_json, period_count
 * Writes: updates all rows in `periods` table.
 */
function generatePeriodTimes(PDO $db): void {
    $firstClass  = getSetting($db, 'first_class_time', '08:30');
    $classDur    = max(1, (int)getSetting($db, 'class_duration_min', '45'));
    $smallBreak  = max(0, (int)getSetting($db, 'small_break_min', '5'));
    $count       = max(1, min(12, (int)getSetting($db, 'period_count', '8')));

    // Parse big breaks: [{"after_period":3,"duration_min":40}, ...]
    $bbJson   = getSetting($db, 'big_breaks_json', '[]');
    $bbList   = json_decode($bbJson, true) ?: [];
    $bigBreak = []; // period_number => duration_min
    foreach ($bbList as $bb) {
        if (isset($bb['after_period'], $bb['duration_min'])) {
            $bigBreak[(int)$bb['after_period']] = max(0, (int)$bb['duration_min']);
        }
    }

    [$h, $m]    = explode(':', $firstClass);
    $currentMin = (int)$h * 60 + (int)$m;

    $db->beginTransaction();
    $db->exec("DELETE FROM periods");

    $ins = $db->prepare(
        "INSERT INTO periods (period, start_time, end_time, break_after_min, break_type) VALUES (?,?,?,?,?)"
    );

    for ($p = 1; $p <= $count; $p++) {
        $startStr = sprintf('%02d:%02d', intdiv($currentMin, 60), $currentMin % 60);
        $currentMin += $classDur;
        $endStr   = sprintf('%02d:%02d', intdiv($currentMin, 60), $currentMin % 60);

        $isLast    = ($p === $count);
        $breakDur  = $isLast ? 0 : ($bigBreak[$p] ?? $smallBreak);
        $breakType = (!$isLast && isset($bigBreak[$p])) ? 'big' : 'small';

        $ins->execute([$p, $startStr, $endStr, $breakDur, $breakType]);
        $currentMin += $breakDur;
    }

    $db->commit();
}

/**
 * Return big_breaks_json decoded as array.
 */
function getBigBreaks(PDO $db): array {
    $json = getSetting($db, 'big_breaks_json', '[]');
    return json_decode($json, true) ?: [];
}
