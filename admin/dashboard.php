<?php
require_once __DIR__ . '/_auth.php';

$db = getDB();

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf(false);
    $action = $_POST['action'] ?? '';

    if ($action === 'set_week_type') {
        $type = $_POST['base_week_type'] ?? 'A';
        $week = (int)($_POST['base_iso_week'] ?? date('W'));
        $year = (int)($_POST['base_iso_year'] ?? date('o'));
        if (in_array($type, ['A','B']) && $week >= 1 && $week <= 53) {
            setSetting($db, 'base_week_type', $type);
            setSetting($db, 'base_iso_week', (string)$week);
            setSetting($db, 'base_iso_year', (string)$year);
            logAdminAction($db, 'set_week_type', 'settings', 'week_type', [
                'base_week_type' => $type,
                'base_iso_week' => $week,
                'base_iso_year' => $year,
            ]);
            $msg = 'Podešavanje nedelje sačuvano.';
        }
    }

    if ($action === 'change_password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $rep = $_POST['repeat_password'] ?? '';
        $stmt = $db->prepare("SELECT password FROM admins WHERE id=?");
        $stmt->execute([$_SESSION['admin_id']]);
        $adminRow = $stmt->fetch();
        if (!$adminRow || !password_verify($old, $adminRow['password'])) {
            $pwError = 'Stara lozinka nije ispravna.';
        } elseif (!passwordMeetsPolicy($new, (string)($_SESSION['admin_user'] ?? ''))) {
            $pwError = 'Nova lozinka mora imati najmanje 12 znakova, slovo, broj i specijalan znak.';
        } elseif ($new !== $rep) {
            $pwError = 'Lozinke se ne poklapaju.';
        } else {
            $db->prepare("UPDATE admins SET password=? WHERE id=?")->execute([
                password_hash($new, PASSWORD_BCRYPT), $_SESSION['admin_id']
            ]);
            logAdminAction($db, 'change_password', 'admin', (string)$_SESSION['admin_id']);
            $pwMsg = 'Lozinka uspešno promenjena.';
        }
    }

    if ($action === 'update_info') {
        $newClassName = trim($_POST['class_name'] ?? 'OG1');
        $newSchoolName = trim($_POST['school_name'] ?? '');
        setSetting($db, 'class_name', $newClassName);
        setSetting($db, 'school_name', $newSchoolName);
        logAdminAction($db, 'update_info', 'settings', 'school_info', [
            'class_name' => $newClassName,
            'school_name' => $newSchoolName,
        ]);
        $msg = 'Podaci sačuvani.';
    }
}

$now       = new DateTime('now', new DateTimeZone('Europe/Belgrade'));
$isoWeek   = (int)$now->format('W');
$isoYear   = (int)$now->format('o');
$todayDate = $now->format('d.m.Y');
$classNameRaw = getSetting($db, 'class_name', 'OG1');
$schoolNameRaw = getSetting($db, 'school_name', '');
$className = h($classNameRaw);
$schoolName = h($schoolNameRaw);
$currentType = calcWeekType($db, $isoYear, $isoWeek);
$nextType    = $currentType === 'A' ? 'B' : 'A';

$baseWeek = getSetting($db, 'base_iso_week', '10');
$baseYear = getSetting($db, 'base_iso_year', '2026');
$baseType = getSetting($db, 'base_week_type', 'A');

// Count classes per week
$cntA = $db->query("SELECT COUNT(*) FROM schedule WHERE week_type='A'")->fetchColumn();
$cntB = $db->query("SELECT COUNT(*) FROM schedule WHERE week_type='B'")->fetchColumn();
$cntSubjects = $db->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
$cntTemplates = $db->query("SELECT COUNT(*) FROM viber_templates")->fetchColumn();
$visibleClassCount = $currentType === 'A' ? $cntA : $cntB;
$auditLogs = getRecentAdminAuditLogs($db, 10);
$auditCount = (int)$db->query("SELECT COUNT(*) FROM admin_audit_log")->fetchColumn();
$dbIntegrity = (string)$db->query("PRAGMA integrity_check")->fetchColumn();
$dbSize = is_file(DB_PATH) ? filesize(DB_PATH) : 0;
$backupFiles = glob(dirname(__DIR__) . '/_backup/sqlite/schedule-*.db.gz') ?: [];
usort($backupFiles, fn($a, $b) => filemtime($b) <=> filemtime($a));
$latestBackup = $backupFiles[0] ?? null;
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<?php renderAdminHead('Admin — Dashboard'); ?>
</head>
<body class="min-h-screen">

<!-- NAV -->
<?php renderAdminTopNav('Admin Panel', 'max-w-4xl', $classNameRaw, true); ?>
<?php renderAdminSubnav(); ?>

<div class="max-w-4xl mx-auto px-4 py-6 space-y-6">

  <?php if (!empty($msg)): ?>
  <div class="bg-emerald-500/20 border border-emerald-500/40 rounded-xl p-3 text-emerald-300 text-sm">✅ <?= h($msg) ?></div>
  <?php endif; ?>

  <!-- Student-visible status -->
  <div class="card rounded-2xl p-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500 font-bold">Vidljivo učenicima</p>
        <h1 class="text-2xl font-bold mt-1"><?= $className ?> · Nedelja <?= h($currentType) ?></h1>
        <p class="text-slate-400 text-sm mt-1">
          Danas je <?= h($todayDate) ?> · javni prikaz trenutno koristi <?= h((string)$visibleClassCount) ?> časova iz rasporeda <?= h($currentType) ?>.
        </p>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 shrink-0">
        <a href="../index.php" target="_blank" rel="noopener"
           class="bg-violet-600 hover:bg-violet-500 text-white text-sm px-4 py-2 rounded-lg text-center transition-all">
          Otvori javno
        </a>
        <a href="../index.php?week=A" target="_blank" rel="noopener"
           class="bg-slate-700 hover:bg-slate-600 text-white text-sm px-4 py-2 rounded-lg text-center transition-all">
          Pregled A
        </a>
        <a href="../index.php?week=B" target="_blank" rel="noopener"
           class="bg-slate-700 hover:bg-slate-600 text-white text-sm px-4 py-2 rounded-lg text-center transition-all">
          Pregled B
        </a>
      </div>
    </div>
  </div>

  <!-- Stats row -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    <?php $stats = [
      ['Nedelja ' . $currentType, 'Ova nedelja', 'text-violet-400', '📅'],
      ['Nedelja ' . $nextType,    'Sledeća ned.', 'text-pink-400',   '🗓️'],
      [$cntSubjects . ' pred.',   'Baza predmeta', 'text-emerald-400', '📚'],
      [$cntTemplates . ' temp.',  'Viber šabloni', 'text-amber-400',  '💬'],
    ]; ?>
    <?php foreach ($stats as [$val, $lbl, $color, $emoji]): ?>
    <div class="card rounded-xl p-4 text-center">
      <div class="text-2xl"><?= $emoji ?></div>
      <div class="<?= $color ?> font-bold text-sm mt-1"><?= h($val) ?></div>
      <div class="text-slate-500 text-xs"><?= h($lbl) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Operational status -->
  <div class="card rounded-2xl p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <h2 class="text-base font-bold">🛡️ Sistemsko stanje</h2>
      <a href="../docs/production-security.md" class="text-xs text-violet-400 hover:text-violet-300">Checklist →</a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div class="bg-black/20 border border-white/10 rounded-xl p-3">
        <div class="text-xs text-slate-500 mb-1">Baza</div>
        <div class="<?= $dbIntegrity === 'ok' ? 'text-emerald-400' : 'text-red-400' ?> font-bold text-sm">
          <?= $dbIntegrity === 'ok' ? 'Ispravna' : 'Problem' ?>
        </div>
        <div class="text-xs text-slate-500 mt-1"><?= number_format($dbSize / 1024, 1) ?> KB</div>
      </div>
      <div class="bg-black/20 border border-white/10 rounded-xl p-3">
        <div class="text-xs text-slate-500 mb-1">Poslednji backup</div>
        <?php if ($latestBackup): ?>
        <div class="text-emerald-400 font-bold text-sm"><?= h(date('d.m.Y H:i', filemtime($latestBackup))) ?></div>
        <div class="text-xs text-slate-500 mt-1"><?= h(basename($latestBackup)) ?></div>
        <?php else: ?>
        <div class="text-amber-400 font-bold text-sm">Nije pronađen</div>
        <div class="text-xs text-slate-500 mt-1">Pokrenuti backup pre produkcije</div>
        <?php endif; ?>
      </div>
      <div class="bg-black/20 border border-white/10 rounded-xl p-3">
        <div class="text-xs text-slate-500 mb-1">Istorija izmena</div>
        <div class="text-sky-400 font-bold text-sm"><?= $auditCount ?> zapisa</div>
        <div class="text-xs text-slate-500 mt-1">Pregled u sekciji Izmene</div>
      </div>
    </div>
  </div>

  <!-- Quick links -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
    <a href="schedule.php?week=A"
       class="card rounded-xl p-4 hover:bg-white/8 transition-all block">
      <div class="text-lg mb-1">📅 Nedelja A</div>
      <div class="text-slate-400 text-sm"><?= $cntA ?> časova &nbsp;·&nbsp; Uredi raspored</div>
      <div class="text-violet-400 text-xs mt-2">→ Otvori editor</div>
    </a>
    <a href="schedule.php?week=B"
       class="card rounded-xl p-4 hover:bg-white/8 transition-all block">
      <div class="text-lg mb-1">📅 Nedelja B</div>
      <div class="text-slate-400 text-sm"><?= $cntB ?> časova &nbsp;·&nbsp; Uredi raspored</div>
      <div class="text-pink-400 text-xs mt-2">→ Otvori editor</div>
    </a>
    <a href="viber.php"
       class="card rounded-xl p-4 hover:bg-white/8 transition-all block">
      <div class="text-lg mb-1">💬 Viber</div>
      <div class="text-slate-400 text-sm">Pošalji obaveštenje roditeljima</div>
      <div class="text-purple-400 text-xs mt-2">→ Šabloni i slanje</div>
    </a>
  </div>

  <!-- Week type configuration -->
  <div class="card rounded-2xl p-5">
    <h2 class="text-base font-bold mb-4">🔄 Konfiguracija naizmenične nedelje</h2>
    <div class="bg-slate-800/50 rounded-xl p-3 mb-4 text-sm">
      <div class="text-slate-300">
        Trenutna nedelja (ISO <?= $isoWeek ?>/<?= $isoYear ?>):
        <span class="font-bold text-violet-400">Nedelja <?= $currentType ?></span>
      </div>
      <div class="text-slate-400 text-xs mt-1">
        Sledeća nedelja: <strong>Nedelja <?= $nextType ?></strong>
      </div>
      <div class="text-slate-500 text-xs mt-1">
        Referentna nedelja: ISO <?= $baseWeek ?>/<?= $baseYear ?> = tip <?= $baseType ?>
      </div>
    </div>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="set_week_type">
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="text-xs text-slate-400 mb-1 block">ISO nedelja</label>
          <input type="number" name="base_iso_week" value="<?= h($baseWeek) ?>" min="1" max="53"
                 class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
        </div>
        <div>
          <label class="text-xs text-slate-400 mb-1 block">Godina</label>
          <input type="number" name="base_iso_year" value="<?= h($baseYear) ?>" min="2024" max="2030"
                 class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
        </div>
        <div>
          <label class="text-xs text-slate-400 mb-1 block">Tip te nedelje</label>
          <select name="base_week_type"
                  class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
            <option value="A" <?= $baseType==='A'?'selected':'' ?>>A</option>
            <option value="B" <?= $baseType==='B'?'selected':'' ?>>B</option>
          </select>
        </div>
      </div>
      <button type="submit" class="bg-violet-600 hover:bg-violet-500 text-white text-sm px-4 py-2 rounded-lg transition-all">
        Sačuvaj podešavanje
      </button>
    </form>
  </div>

  <!-- School info -->
  <div class="card rounded-2xl p-5">
    <h2 class="text-base font-bold mb-4">🏫 Podaci škole i razreda</h2>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="update_info">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs text-slate-400 mb-1 block">Naziv razreda</label>
          <input type="text" name="class_name" value="<?= $className ?>" maxlength="20"
                 class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
        </div>
        <div>
          <label class="text-xs text-slate-400 mb-1 block">Naziv škole</label>
          <input type="text" name="school_name" value="<?= $schoolName ?>" maxlength="80"
                 class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
        </div>
      </div>
      <button type="submit" class="bg-slate-700 hover:bg-slate-600 text-white text-sm px-4 py-2 rounded-lg transition-all">
        Sačuvaj
      </button>
    </form>
  </div>

  <!-- Change password -->
  <div class="card rounded-2xl p-5">
    <h2 class="text-base font-bold mb-4">🔐 Promena lozinke</h2>
    <?php if (!empty($pwError)): ?>
    <div class="bg-red-500/20 border border-red-500/40 rounded-xl p-3 mb-3 text-red-300 text-sm"><?= h($pwError) ?></div>
    <?php endif; ?>
    <?php if (!empty($pwMsg)): ?>
    <div class="bg-emerald-500/20 border border-emerald-500/40 rounded-xl p-3 mb-3 text-emerald-300 text-sm">✅ <?= h($pwMsg) ?></div>
    <?php endif; ?>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="change_password">
      <input type="password" name="old_password" placeholder="Stara lozinka"
             class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
      <div class="grid grid-cols-2 gap-3">
        <input type="password" name="new_password" placeholder="Nova lozinka (min 12)"
               class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
        <input type="password" name="repeat_password" placeholder="Ponovi novu"
               class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
      </div>
      <button type="submit" class="bg-slate-700 hover:bg-slate-600 text-white text-sm px-4 py-2 rounded-lg transition-all">
        Promeni lozinku
      </button>
    </form>
  </div>

  <!-- Recent activity -->
  <div class="card rounded-2xl p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <h2 class="text-base font-bold">🧾 Poslednje admin izmene</h2>
      <a href="audit.php" class="text-xs text-violet-400 hover:text-violet-300">Sve izmene →</a>
    </div>
    <?php if (!$auditLogs): ?>
      <div class="text-slate-500 text-sm">Još nema zabeleženih izmena.</div>
    <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($auditLogs as $log): ?>
          <?php
            $details = json_decode((string)($log['details'] ?? ''), true);
            $detailText = is_array($details) && $details
                ? implode(' · ', array_map(
                    fn($key, $value) => $key . ': ' . (is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE)),
                    array_keys($details),
                    array_values($details)
                ))
                : '';
          ?>
          <div class="bg-black/20 border border-white/10 rounded-xl px-3 py-2">
            <div class="flex items-center justify-between gap-3">
              <span class="text-sm text-slate-200"><?= h($log['action']) ?></span>
              <span class="text-xs text-slate-500"><?= h($log['created_at']) ?></span>
            </div>
            <div class="text-xs text-slate-500 mt-1">
              <?= h($log['admin_user'] ?: 'admin') ?>
              <?php if ($log['entity_type']): ?>
                · <?= h($log['entity_type']) ?><?= $log['entity_id'] ? ':' . h($log['entity_id']) : '' ?>
              <?php endif; ?>
            </div>
            <?php if ($detailText): ?>
              <div class="text-xs text-slate-400 mt-1"><?= h($detailText) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
