<?php
/**
 * Admin AJAX API — JSON endpoint
 * All mutating requests require CSRF token in header or POST body
 */
require_once __DIR__ . '/_auth.php';

if (defined('APP_ENV') && APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string)($method === 'POST'
    ? (filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW) ?? '')
    : (filter_input(INPUT_GET, 'action', FILTER_UNSAFE_RAW) ?? ''));
$action = trim($action);

$readActions = ['get_schedule', 'get_periods', 'get_subjects', 'get_templates'];
$writeActions = [
    'update_slot', 'delete_slot', 'swap_slots', 'toggle_double',
    'create_subject', 'update_subject', 'delete_subject',
    'save_template', 'delete_template',
];

if (in_array($action, $readActions, true) && $method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (in_array($action, $writeActions, true) && $method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

function apiPostText(string $key, int $maxLength = 255): string {
    $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
    return mb_substr(trim((string)($value ?? '')), 0, $maxLength);
}

function apiPostInt(string $key): int {
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    return $value === false || $value === null ? 0 : (int)$value;
}

function apiGetText(string $key, string $default = ''): string {
    $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
    $value = trim((string)($value ?? $default));
    return $value === '' ? $default : $value;
}

function apiPostWeekType(): string {
    $weekType = apiPostText('week_type', 1);
    if (!in_array($weekType, ['A', 'B'], true)) {
        throw new InvalidArgumentException('Invalid week type');
    }
    return $weekType;
}

function apiPostDay(): int {
    $day = apiPostInt('day');
    if ($day < 1 || $day > 5) {
        throw new InvalidArgumentException('Invalid day');
    }
    return $day;
}

function periodExists(PDO $db, int $period): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM periods WHERE period=?");
    $stmt->execute([$period]);
    return (int)$stmt->fetchColumn() > 0;
}

function apiPostPeriod(PDO $db, string $key = 'period'): int {
    $period = apiPostInt($key);
    if ($period < 1 || !periodExists($db, $period)) {
        throw new InvalidArgumentException('Invalid period');
    }
    return $period;
}

function apiPostExistingId(PDO $db, string $table, string $key = 'id'): int {
    $id = apiPostInt($key);
    if ($id < 1 || !dbRecordExists($db, $table, $id)) {
        throw new InvalidArgumentException('Invalid id');
    }
    return $id;
}

try {
    switch ($action) {

    // ── GET schedule for a week ───────────────────────────────────────
    case 'get_schedule':
        $weekType = apiGetText('week', 'A');
        if (!in_array($weekType, ['A','B'])) { throw new InvalidArgumentException('Invalid week type'); }
        $rows = getScheduleByDay($db, $weekType);
        echo json_encode(['ok'=>true, 'schedule'=>$rows, 'periods'=>getPeriods($db)]);
        break;

    // ── Update a single schedule slot ─────────────────────────────────
    case 'update_slot':
        verifyCsrf();
        $weekType  = apiPostWeekType();
        $day       = apiPostDay();
        $period    = apiPostPeriod($db);
        $subjectId = apiPostInt('subject_id') > 0 ? apiPostInt('subject_id') : null;
        $room      = apiPostText('room', 20);
        $notes     = apiPostText('notes', 100);
        $isDouble  = apiPostInt('is_double') > 0 ? 1 : 0;

        if ($subjectId !== null && !dbRecordExists($db, 'subjects', $subjectId)) {
            throw new InvalidArgumentException('Invalid subject');
        }
        if ($isDouble && !periodExists($db, $period + 1)) {
            throw new InvalidArgumentException('Dvočas nije moguć na poslednjem času.');
        }

        if ($subjectId === null) {
            $db->prepare("DELETE FROM schedule WHERE week_type=? AND day=? AND period=?")
               ->execute([$weekType, $day, $period]);
        } else {
            $db->prepare("
                INSERT INTO schedule (week_type, day, period, subject_id, room, notes, is_double)
                VALUES (?,?,?,?,?,?,?)
                ON CONFLICT(week_type,day,period) DO UPDATE SET
                  subject_id=excluded.subject_id,
                  room=excluded.room,
                  notes=excluded.notes,
                  is_double=excluded.is_double
            ")->execute([$weekType, $day, $period, $subjectId, $room, $notes, $isDouble]);

            // If is_double is ON and next period exists — clear it (it becomes continuation)
            if ($isDouble) {
                $db->prepare("DELETE FROM schedule WHERE week_type=? AND day=? AND period=?")
                   ->execute([$weekType, $day, $period + 1]);
            }
        }
        logAdminAction($db, 'update_slot', 'schedule', "$weekType-$day-$period", [
            'week_type' => $weekType,
            'day' => $day,
            'period' => $period,
            'subject_id' => $subjectId,
            'room' => $room,
            'is_double' => $isDouble,
        ]);
        echo json_encode(['ok'=>true]);
        break;

    // ── Delete a schedule slot ────────────────────────────────────────
    case 'delete_slot':
        verifyCsrf();
        $weekType = apiPostWeekType();
        $day      = apiPostDay();
        $period   = apiPostPeriod($db);
        $db->prepare("DELETE FROM schedule WHERE week_type=? AND day=? AND period=?")
           ->execute([$weekType, $day, $period]);
        logAdminAction($db, 'delete_slot', 'schedule', "$weekType-$day-$period", [
            'week_type' => $weekType,
            'day' => $day,
            'period' => $period,
        ]);
        echo json_encode(['ok'=>true]);
        break;

    // ── Reorder / drag-drop: swap two slots ───────────────────────────
    case 'swap_slots':
        verifyCsrf();
        $weekType = apiPostWeekType();
        $day      = apiPostDay();
        $p1       = apiPostPeriod($db, 'period1');
        $p2       = apiPostPeriod($db, 'period2');
        if ($p1 === $p2) {
            throw new InvalidArgumentException('Invalid swap');
        }

        // Get both slots
        $stmt = $db->prepare("SELECT * FROM schedule WHERE week_type=? AND day=? AND period=?");
        $stmt->execute([$weekType, $day, $p1]); $slot1 = $stmt->fetch();
        $stmt->execute([$weekType, $day, $p2]); $slot2 = $stmt->fetch();

        if (($slot1 && !empty($slot1['is_double'])) || ($slot2 && !empty($slot2['is_double']))) {
            throw new InvalidArgumentException('Dvočas se ne pomera drag & drop akcijom.');
        }

        $db->beginTransaction();
        // Remove both
        $del = $db->prepare("DELETE FROM schedule WHERE week_type=? AND day=? AND period=?");
        $del->execute([$weekType, $day, $p1]);
        $del->execute([$weekType, $day, $p2]);

        // Re-insert swapped
        $ins = $db->prepare("INSERT INTO schedule (week_type,day,period,subject_id,room,notes,is_double) VALUES (?,?,?,?,?,?,?)");
        if ($slot1) $ins->execute([$weekType,$day,$p2,$slot1['subject_id'],$slot1['room'],$slot1['notes'],$slot1['is_double']]);
        if ($slot2) $ins->execute([$weekType,$day,$p1,$slot2['subject_id'],$slot2['room'],$slot2['notes'],$slot2['is_double']]);
        $db->commit();
        logAdminAction($db, 'swap_slots', 'schedule', "$weekType-$day-$p1-$p2", [
            'week_type' => $weekType,
            'day' => $day,
            'period1' => $p1,
            'period2' => $p2,
        ]);
        echo json_encode(['ok'=>true]);
        break;

    // ── Toggle double period (dvočas) ────────────────────────────────
    case 'toggle_double':
        verifyCsrf();
        $weekType = apiPostWeekType();
        $day      = apiPostDay();
        $period   = apiPostPeriod($db);
        $enable   = apiPostInt('enable') > 0 ? 1 : 0;

        if ($enable && !periodExists($db, $period + 1)) {
            throw new InvalidArgumentException('Dvočas nije moguć na poslednjem času.');
        }

        $db->prepare("UPDATE schedule SET is_double=? WHERE week_type=? AND day=? AND period=?")
           ->execute([$enable, $weekType, $day, $period]);

        // When enabling dvočas: clear the continuation slot (next period)
        if ($enable) {
            $db->prepare("DELETE FROM schedule WHERE week_type=? AND day=? AND period=?")
               ->execute([$weekType, $day, $period + 1]);
        }
        logAdminAction($db, 'toggle_double', 'schedule', "$weekType-$day-$period", [
            'week_type' => $weekType,
            'day' => $day,
            'period' => $period,
            'enabled' => $enable,
        ]);
        echo json_encode(['ok'=>true]);
        break;

    // ── Get all periods ───────────────────────────────────────────────
    case 'get_periods':
        echo json_encode(['ok'=>true, 'periods'=>getPeriods($db)]);
        break;

    // ── Get all subjects ──────────────────────────────────────────────
    case 'get_subjects':
        echo json_encode(['ok'=>true, 'subjects'=>getAllSubjects($db)]);
        break;

    // ── Create subject ────────────────────────────────────────────────
    case 'create_subject':
        verifyCsrf();
        $name    = apiPostText('name', 80);
        $short   = apiPostText('short_name', 6);
        $teacher = apiPostText('teacher', 80);
        $rawColor = apiPostText('color', 7);
        $color   = preg_match('/^#[0-9a-f]{6}$/i', $rawColor) ? $rawColor : '#6366f1';
        $emoji   = mb_substr(apiPostText('emoji', 8) ?: '📚', 0, 4);
        if (!$name) throw new InvalidArgumentException('Name required');
        $db->prepare("INSERT INTO subjects (name,short_name,teacher,color,emoji) VALUES (?,?,?,?,?)")
           ->execute([$name,$short,$teacher,$color,$emoji]);
        $id = (string)$db->lastInsertId();
        logAdminAction($db, 'create_subject', 'subject', $id, [
            'name' => $name,
            'short_name' => $short,
        ]);
        echo json_encode(['ok'=>true, 'id'=>$id]);
        break;

    // ── Update subject ────────────────────────────────────────────────
    case 'update_subject':
        verifyCsrf();
        $id      = apiPostExistingId($db, 'subjects');
        $name    = apiPostText('name', 80);
        $short   = apiPostText('short_name', 6);
        $teacher = apiPostText('teacher', 80);
        $rawColor = apiPostText('color', 7);
        $color   = preg_match('/^#[0-9a-f]{6}$/i', $rawColor) ? $rawColor : '#6366f1';
        $emoji   = mb_substr(apiPostText('emoji', 8) ?: '📚', 0, 4);
        if (!$name) throw new InvalidArgumentException('Name required');
        $db->prepare("UPDATE subjects SET name=?,short_name=?,teacher=?,color=?,emoji=? WHERE id=?")
           ->execute([$name,$short,$teacher,$color,$emoji,$id]);
        logAdminAction($db, 'update_subject', 'subject', (string)$id, [
            'name' => $name,
            'short_name' => $short,
        ]);
        echo json_encode(['ok'=>true]);
        break;

    // ── Delete subject ────────────────────────────────────────────────
    case 'delete_subject':
        verifyCsrf();
        $id = apiPostExistingId($db, 'subjects');
        // First remove from schedule
        $db->beginTransaction();
        $db->prepare("UPDATE schedule SET subject_id=NULL WHERE subject_id=?")->execute([$id]);
        $db->prepare("DELETE FROM subjects WHERE id=?")->execute([$id]);
        logAdminAction($db, 'delete_subject', 'subject', (string)$id);
        $db->commit();
        echo json_encode(['ok'=>true]);
        break;

    // ── Viber templates ───────────────────────────────────────────────
    case 'get_templates':
        $rows = $db->query("SELECT * FROM viber_templates ORDER BY created_at DESC")->fetchAll();
        echo json_encode(['ok'=>true, 'templates'=>$rows]);
        break;

    case 'save_template':
        verifyCsrf();
        $id      = apiPostInt('id');
        $name    = apiPostText('name', 80);
        $content = apiPostText('content', 5000);
        if (!$name || !$content) throw new InvalidArgumentException('Name and content required');
        if ($id > 0) {
            if (!dbRecordExists($db, 'viber_templates', $id)) {
                throw new InvalidArgumentException('Invalid template');
            }
            $db->prepare("UPDATE viber_templates SET name=?,content=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
               ->execute([$name,$content,$id]);
        } else {
            $db->prepare("INSERT INTO viber_templates (name,content) VALUES (?,?)")->execute([$name,$content]);
            $id = $db->lastInsertId();
        }
        logAdminAction($db, 'save_template', 'viber_template', (string)$id, [
            'name' => $name,
        ]);
        echo json_encode(['ok'=>true, 'id'=>$id]);
        break;

    case 'delete_template':
        verifyCsrf();
        $id = apiPostExistingId($db, 'viber_templates');
        $db->prepare("DELETE FROM viber_templates WHERE id=?")->execute([$id]);
        logAdminAction($db, 'delete_template', 'viber_template', (string)$id);
        echo json_encode(['ok'=>true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error'=>'Unknown action']);
    }

} catch (InvalidArgumentException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error'=> 'Invalid request']);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Admin API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error'=> 'Request failed']);
}
