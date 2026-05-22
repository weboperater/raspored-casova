<?php
require_once __DIR__ . '/_auth.php';
$db = getDB();

// Handle form submissions
$msg = $err = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf(false);
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $short   = trim($_POST['short_name'] ?? substr($name, 0, 4));
        $teacher = trim($_POST['teacher'] ?? '');
        $color   = preg_match('/^#[0-9a-f]{6}$/i', $_POST['color']??'') ? $_POST['color'] : '#6366f1';
        $emoji   = mb_substr(trim($_POST['emoji']??'📚'), 0, 4) ?: '📚';

        if (!$name) { $err = 'Naziv predmeta je obavezan.'; }
        else {
            if ($action === 'create') {
                try {
                    $db->prepare("INSERT INTO subjects (name,short_name,teacher,color,emoji) VALUES (?,?,?,?,?)")
                       ->execute([$name,$short,$teacher,$color,$emoji]);
                    logAdminAction($db, 'create_subject', 'subject', (string)$db->lastInsertId(), [
                        'name' => $name,
                        'short_name' => $short,
                    ]);
                    $msg = "Predmet '$name' dodat.";
                } catch (Exception $e) {
                    $err = 'Predmet sa tim nazivom već postoji.';
                }
            } else {
                if ($id < 1 || !dbRecordExists($db, 'subjects', $id)) {
                    $err = 'Predmet nije pronađen.';
                } else {
                    $db->prepare("UPDATE subjects SET name=?,short_name=?,teacher=?,color=?,emoji=? WHERE id=?")
                       ->execute([$name,$short,$teacher,$color,$emoji,$id]);
                    logAdminAction($db, 'update_subject', 'subject', (string)$id, [
                        'name' => $name,
                        'short_name' => $short,
                    ]);
                    $msg = "Predmet ažuriran.";
                }
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && dbRecordExists($db, 'subjects', $id)) {
            $nameRow = $db->prepare("SELECT name FROM subjects WHERE id=?");
            $nameRow->execute([$id]);
            $delName = $nameRow->fetchColumn();
            // Nullify in schedule
            $db->beginTransaction();
            $db->prepare("UPDATE schedule SET subject_id=NULL WHERE subject_id=?")->execute([$id]);
            $db->prepare("DELETE FROM subjects WHERE id=?")->execute([$id]);
            logAdminAction($db, 'delete_subject', 'subject', (string)$id, [
                'name' => (string)$delName,
            ]);
            $db->commit();
            $msg = "Predmet '$delName' obrisan.";
        } else {
            $err = 'Predmet nije pronađen.';
        }
    }
}

$subjects = getAllSubjects($db);

// Count usage for each subject
$usage = [];
foreach ($db->query("SELECT subject_id, COUNT(*) as cnt FROM schedule WHERE subject_id IS NOT NULL GROUP BY subject_id")->fetchAll() as $row) {
    $usage[$row['subject_id']] = $row['cnt'];
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<?php renderAdminHead('Admin — Predmeti'); ?>
</head>
<body class="min-h-screen">

<!-- NAV -->
<?php renderAdminTopNav(); ?>
<?php renderAdminSubnav(); ?>

<div class="max-w-4xl mx-auto px-4 py-6 space-y-6">

  <?php if ($msg): ?><div class="bg-emerald-500/20 border border-emerald-500/40 rounded-xl p-3 text-emerald-300 text-sm">✅ <?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="bg-red-500/20 border border-red-500/40 rounded-xl p-3 text-red-300 text-sm">⚠️ <?= h($err) ?></div><?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ── Add/Edit form ──────────────────────────────────────────────── -->
    <div class="lg:col-span-1">
      <div class="card rounded-2xl p-5" id="form-card">
        <h2 class="text-base font-bold mb-4" id="form-title">+ Novi predmet</h2>
        <form method="POST" id="subject-form" class="space-y-3">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="create" id="form-action">
          <input type="hidden" name="id" value="" id="form-id">

          <div>
            <label class="text-xs text-slate-400 mb-1 block">Naziv predmeta *</label>
            <input type="text" name="name" id="form-name" required maxlength="80"
                   placeholder="npr. Matematika"
                   class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs text-slate-400 mb-1 block">Skraćenica</label>
              <input type="text" name="short_name" id="form-short" maxlength="6"
                     placeholder="Mat"
                     class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
            </div>
            <div>
              <label class="text-xs text-slate-400 mb-1 block">Emoji</label>
              <input type="text" name="emoji" id="form-emoji" maxlength="4"
                     placeholder="📚"
                     class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
            </div>
          </div>

          <div>
            <label class="text-xs text-slate-400 mb-1 block">Nastavnik</label>
            <input type="text" name="teacher" id="form-teacher" maxlength="80"
                   placeholder="Ime Prezime"
                   class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
          </div>

          <div>
            <label class="text-xs text-slate-400 mb-1 block">Boja predmeta</label>
            <div class="flex items-center gap-3">
              <input type="color" name="color" id="form-color" value="#6366f1"
                     class="w-12 h-10 rounded-lg border border-white/10 bg-transparent cursor-pointer">
              <div class="text-sm text-slate-300" id="color-preview">
                Izgled: <span id="color-swatch" class="px-2 py-0.5 rounded-md text-xs font-bold">Predmet</span>
              </div>
            </div>
            <!-- Quick color palette -->
            <div class="flex flex-wrap gap-1.5 mt-2">
              <?php foreach (['#3b82f6','#22c55e','#f97316','#a855f7','#ef4444','#eab308','#14b8a6','#ec4899','#06b6d4','#8b5cf6','#f43f5e','#84cc16','#d97706','#64748b'] as $c): ?>
              <button type="button" onclick="setColor('<?= $c ?>')"
                      class="color-palette-swatch w-5 h-5 rounded-md transition-transform hover:scale-110 border border-white/20"
                      data-color="<?= $c ?>"></button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="flex gap-2 pt-1">
            <button type="submit" class="flex-1 bg-violet-600 hover:bg-violet-500 text-white font-bold py-2.5 rounded-xl transition-all text-sm" id="submit-btn">
              Dodaj predmet
            </button>
            <button type="button" onclick="resetForm()" id="cancel-btn" class="hidden px-3 bg-white/5 hover:bg-white/10 text-slate-300 rounded-xl transition-all text-sm">
              Otkaži
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Subjects list ──────────────────────────────────────────────── -->
    <div class="lg:col-span-2">
      <div class="card rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-white/10 flex items-center justify-between">
          <h2 class="font-bold">Baza predmeta (<?= count($subjects) ?>)</h2>
          <input type="text" id="search-input" placeholder="Pretraži..." oninput="filterList(this.value)"
                 class="bg-black/30 border border-white/10 rounded-xl px-3 py-1.5 text-white text-sm w-40 focus:outline-none focus:border-violet-500">
        </div>
        <div id="subjects-list">
          <?php foreach ($subjects as $s):
            $cnt = $usage[$s['id']] ?? 0;
          ?>
          <div class="subject-row border-b border-white/5 last:border-b-0 px-4 py-3 flex items-center gap-3 transition-colors"
               data-name="<?= h(strtolower($s['name'])) ?>" data-teacher="<?= h(strtolower($s['teacher']??'')) ?>"
               data-color="<?= h($s['color']) ?>">
            <!-- Color dot -->
            <div class="subject-icon-bg flex-shrink-0 w-9 h-9 rounded-xl flex items-center justify-center text-base">
              <?= h($s['emoji']??'📚') ?>
            </div>
            <!-- Info -->
            <div class="flex-1 min-w-0">
              <div class="subject-color-text font-semibold text-sm truncate">
                <?= h($s['name']) ?>
                <?php if ($s['short_name']): ?>
                <span class="text-xs text-slate-500 font-normal ml-1">(<?= h($s['short_name']) ?>)</span>
                <?php endif; ?>
              </div>
              <div class="text-xs text-slate-500"><?= $s['teacher'] ? h($s['teacher']) : '— nastavnik nije unesen' ?></div>
            </div>
            <!-- Usage badge -->
            <div class="text-xs text-slate-600 flex-shrink-0">
              <?= $cnt ?> <?= $cnt === 1 ? 'čas' : ($cnt < 5 ? 'časa' : 'časova') ?>
            </div>
            <!-- Actions -->
            <div class="flex gap-1 flex-shrink-0">
              <button onclick='editSubject(<?= json_encode($s) ?>)'
                      class="text-xs bg-white/5 hover:bg-white/10 text-slate-300 px-2 py-1 rounded-lg transition-all">
                ✏️
              </button>
              <?php if ($cnt === 0): ?>
              <form method="POST" onsubmit="return confirm('Obriši predmet?')">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button type="submit" class="text-xs bg-red-500/10 hover:bg-red-500/30 text-red-400 px-2 py-1 rounded-lg transition-all">🗑️</button>
              </form>
              <?php else: ?>
              <button class="text-xs text-slate-700 px-2 py-1 rounded-lg cursor-not-allowed" title="Predmet se koristi u rasporedu">🔒</button>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (empty($subjects)): ?>
        <div class="p-8 text-center text-slate-500">Nema predmeta. Dodaj prvi!</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/dynamic-styles.js"></script>
<script>
// Color picker sync
const colorInput = document.getElementById('form-color');
const swatch     = document.getElementById('color-swatch');

function setColor(c) {
  colorInput.value = c;
  swatch.style.background = c + '33';
  swatch.style.color = c;
}
colorInput.addEventListener('input', () => setColor(colorInput.value));
setColor(colorInput.value);

// Edit subject — populate form
function editSubject(s) {
  document.getElementById('form-action').value  = 'update';
  document.getElementById('form-id').value      = s.id;
  document.getElementById('form-name').value    = s.name;
  document.getElementById('form-short').value   = s.short_name || '';
  document.getElementById('form-teacher').value = s.teacher || '';
  document.getElementById('form-emoji').value   = s.emoji || '📚';
  document.getElementById('form-title').textContent = '✏️ Uredi predmet';
  document.getElementById('submit-btn').textContent = 'Sačuvaj izmene';
  document.getElementById('cancel-btn').classList.remove('hidden');
  setColor(s.color || '#6366f1');
  document.getElementById('form-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function resetForm() {
  document.getElementById('subject-form').reset();
  document.getElementById('form-action').value = 'create';
  document.getElementById('form-id').value = '';
  document.getElementById('form-title').textContent = '+ Novi predmet';
  document.getElementById('submit-btn').textContent = 'Dodaj predmet';
  document.getElementById('cancel-btn').classList.add('hidden');
  setColor('#6366f1');
}

// Search/filter
function filterList(q) {
  const lq = q.toLowerCase().trim();
  document.querySelectorAll('.subject-row').forEach(row => {
    const visible = !lq || row.dataset.name.includes(lq) || row.dataset.teacher.includes(lq);
    row.style.display = visible ? '' : 'none';
  });
}
</script>
</body>
</html>
