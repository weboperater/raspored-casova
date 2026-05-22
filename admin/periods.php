<?php
require_once __DIR__ . '/_auth.php';
$db = getDB();

$msg = $err = '';

// ── Handle POST ───────────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf(false);
    $action = $_POST['action'] ?? '';

    // Save school-day config and regenerate all period times
    if ($action === 'generate') {
        $firstClass = trim($_POST['first_class_time'] ?? '08:30');
        $classDur   = max(1, min(120, (int)($_POST['class_duration_min'] ?? 45)));
        $smallBreak = max(0, min(60,  (int)($_POST['small_break_min']    ?? 5)));
        $periodCount= max(1, min(12,  (int)($_POST['period_count']        ?? 8)));

        // Validate time format
        if (!preg_match('/^\d{2}:\d{2}$/', $firstClass)) {
            $err = 'Neispravan format vremena. Koristi HH:MM.';
        } else {
            // Parse big breaks from POST arrays
            $bbAfter    = array_map('intval', (array)($_POST['bb_after']    ?? []));
            $bbDuration = array_map('intval', (array)($_POST['bb_duration'] ?? []));
            $bigBreaks  = [];
            foreach ($bbAfter as $i => $afterPeriod) {
                if ($afterPeriod >= 1 && $afterPeriod < $periodCount && isset($bbDuration[$i]) && $bbDuration[$i] > 0) {
                    $bigBreaks[] = ['after_period' => $afterPeriod, 'duration_min' => $bbDuration[$i]];
                }
            }
            // Remove duplicates (keep last)
            $seen = [];
            $bigBreaks = array_filter($bigBreaks, function($bb) use (&$seen) {
                if (in_array($bb['after_period'], $seen)) return false;
                $seen[] = $bb['after_period'];
                return true;
            });
            $bigBreaks = array_values($bigBreaks);
            usort($bigBreaks, fn($a,$b) => $a['after_period'] <=> $b['after_period']);

            setSetting($db, 'first_class_time',   $firstClass);
            setSetting($db, 'class_duration_min',  (string)$classDur);
            setSetting($db, 'small_break_min',     (string)$smallBreak);
            setSetting($db, 'period_count',        (string)$periodCount);
            setSetting($db, 'big_breaks_json',     json_encode($bigBreaks));

            generatePeriodTimes($db);
            logAdminAction($db, 'generate_periods', 'periods', null, [
                'first_class_time' => $firstClass,
                'class_duration_min' => $classDur,
                'small_break_min' => $smallBreak,
                'period_count' => $periodCount,
            ]);
            $msg = "Termini su generisani i sačuvani ({$periodCount} časova).";
        }
    }

    // Manual override of a single period
    if ($action === 'manual_override') {
        $period    = (int)($_POST['period']     ?? 0);
        $startTime = trim($_POST['start_time']  ?? '');
        $endTime   = trim($_POST['end_time']    ?? '');
        $breakMin  = max(0, (int)($_POST['break_after_min'] ?? 5));
        $breakType = in_array($_POST['break_type'] ?? '', ['small','big']) ? $_POST['break_type'] : 'small';

        if ($period < 1 || !preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            $err = 'Neispravan unos za ručno podešavanje.';
        } else {
            $db->prepare("UPDATE periods SET start_time=?, end_time=?, break_after_min=?, break_type=? WHERE period=?")
               ->execute([$startTime, $endTime, $breakMin, $breakType, $period]);
            logAdminAction($db, 'update_period', 'period', (string)$period, [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'break_after_min' => $breakMin,
                'break_type' => $breakType,
            ]);
            $msg = "Termin za čas {$period} ažuriran.";
        }
    }
}

// ── Load current state ────────────────────────────────────────────────────────
$periods     = getPeriods($db);
$periodsMap  = [];
foreach ($periods as $p) { $periodsMap[$p['period']] = $p; }

$firstClass  = getSetting($db, 'first_class_time',   '08:30');
$classDur    = getSetting($db, 'class_duration_min',  '45');
$smallBreak  = getSetting($db, 'small_break_min',     '5');
$periodCount = getSetting($db, 'period_count',        '8');
$bigBreaks   = getBigBreaks($db);

// Build big-break map for display
$bbMap = [];
foreach ($bigBreaks as $bb) { $bbMap[$bb['after_period']] = $bb['duration_min']; }

// ── Preview calculation (without saving) ──────────────────────────────────────
// Generates a preview array from current settings (used for JS live preview)
$previewJson = json_encode([
    'first_class_time'   => $firstClass,
    'class_duration_min' => (int)$classDur,
    'small_break_min'    => (int)$smallBreak,
    'period_count'       => (int)$periodCount,
    'big_breaks'         => $bigBreaks,
]);
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<?php renderAdminHead('Admin — Termini časova'); ?>
</head>
<body class="periods-page min-h-screen">

<!-- NAV -->
<?php renderAdminTopNav(); ?>
<?php renderAdminSubnav(); ?>

<div class="max-w-4xl mx-auto px-4 py-6 space-y-6">

  <?php if ($msg): ?><div class="bg-emerald-500/20 border border-emerald-500/40 rounded-xl p-3 text-emerald-300 text-sm">✅ <?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="bg-red-500/20 border border-red-500/40 rounded-xl p-3 text-red-300 text-sm">⚠️ <?= h($err) ?></div><?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- ── LEFT: Generator form ──────────────────────────────────────────── -->
    <div class="space-y-5">

      <div class="card rounded-2xl p-5">
        <h2 class="font-bold text-base mb-1">⚙️ Generator termina</h2>
        <p class="text-xs text-slate-500 mb-4">Unesi parametre i klikni "Generiši" — svi termini se automatski izračunavaju.</p>

        <form method="POST" id="gen-form" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="generate">

          <!-- Basic config -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs text-slate-400 mb-1 block">⏰ Početak prvog časa</label>
              <input type="time" name="first_class_time" id="inp-first" value="<?= h($firstClass) ?>" oninput="livePreview()">
            </div>
            <div>
              <label class="text-xs text-slate-400 mb-1 block">📋 Broj časova</label>
              <input type="number" name="period_count" id="inp-count" value="<?= h($periodCount) ?>" min="1" max="12" oninput="livePreview()">
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs text-slate-400 mb-1 block">⏱️ Trajanje časa (min)</label>
              <input type="number" name="class_duration_min" id="inp-dur" value="<?= h($classDur) ?>" min="1" max="120" oninput="livePreview()">
            </div>
            <div>
              <label class="text-xs text-slate-400 mb-1 block">☕ Mali odmor (min)</label>
              <input type="number" name="small_break_min" id="inp-small" value="<?= h($smallBreak) ?>" min="0" max="60" oninput="livePreview()">
            </div>
          </div>

          <!-- Big breaks -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-xs text-slate-400">🔔 Veliki odmori (max 2)</label>
              <button type="button" onclick="addBigBreak()" id="add-bb-btn"
                      class="text-xs bg-amber-500/20 text-amber-400 border border-amber-500/30 px-2.5 py-1 rounded-lg hover:bg-amber-500/30 transition-all">
                + Dodaj
              </button>
            </div>
            <div id="big-breaks-list" class="space-y-2">
              <?php foreach ($bigBreaks as $bb): ?>
              <div class="big-break-row rounded-xl p-2 flex items-center gap-2">
                <span class="text-xs text-amber-400 flex-shrink-0">posle časa</span>
                <input type="number" name="bb_after[]" value="<?= (int)$bb['after_period'] ?>"
                       min="1" max="11" class="w-14 text-center" oninput="livePreview()">
                <span class="text-xs text-amber-400 flex-shrink-0">trajanje</span>
                <input type="number" name="bb_duration[]" value="<?= (int)$bb['duration_min'] ?>"
                       min="1" max="120" class="w-16" oninput="livePreview()">
                <span class="text-xs text-slate-500">min</span>
                <button type="button" onclick="removeBigBreak(this)"
                        class="ml-auto text-red-400 hover:text-red-300 text-sm">✕</button>
              </div>
              <?php endforeach; ?>
            </div>
            <p class="text-xs text-slate-600 mt-1">
              Npr. veliki odmor posle 3. časa, 40 minuta. Može biti 2 velika odmora u danu.
            </p>
          </div>

          <button type="submit"
                  class="w-full bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 text-white font-bold py-3 rounded-xl transition-all">
            ⚡ Generiši i sačuvaj termine
          </button>
        </form>
      </div>

    </div>

    <!-- ── RIGHT: Live preview + current periods ──────────────────────────── -->
    <div class="space-y-5">

      <!-- Live preview -->
      <div class="card rounded-2xl p-5">
        <h2 class="font-bold text-base mb-3">👁️ Pregled (live)</h2>
        <div id="preview-list" class="space-y-1 text-sm">
          <!-- populated by JS -->
        </div>
      </div>

      <!-- Manual override -->
      <div class="card rounded-2xl p-5">
        <h2 class="font-bold text-base mb-1">✏️ Ručno podešavanje</h2>
        <p class="text-xs text-slate-500 mb-3">Koriguj pojedinačni termin bez regenerisanja svih.</p>
        <div class="space-y-1">
          <?php foreach ($periodsMap as $pNum => $p): ?>
          <details class="period-row rounded-xl">
            <summary class="flex items-center gap-3 px-3 py-2 cursor-pointer select-none">
              <span class="text-xs font-bold text-slate-400 w-4"><?= $pNum ?></span>
              <span class="text-sm text-slate-200 font-mono"><?= h($p['start_time']) ?> – <?= h($p['end_time']) ?></span>
              <span class="ml-auto text-xs <?= $p['break_type']==='big' ? 'text-amber-400' : 'text-slate-600' ?>">
                <?= $p['break_type']==='big' ? '🔔 ' : '' ?>odmor <?= $p['break_after_min'] ?>min
              </span>
            </summary>
            <form method="POST" class="px-3 pb-3 pt-2 grid grid-cols-2 gap-2 text-xs">
              <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
              <input type="hidden" name="action" value="manual_override">
              <input type="hidden" name="period" value="<?= $pNum ?>">
              <div>
                <label class="text-slate-500 mb-0.5 block">Početak</label>
                <input type="time" name="start_time" value="<?= h($p['start_time']) ?>">
              </div>
              <div>
                <label class="text-slate-500 mb-0.5 block">Kraj</label>
                <input type="time" name="end_time" value="<?= h($p['end_time']) ?>">
              </div>
              <div>
                <label class="text-slate-500 mb-0.5 block">Odmor posle (min)</label>
                <input type="number" name="break_after_min" value="<?= (int)$p['break_after_min'] ?>" min="0" max="120">
              </div>
              <div>
                <label class="text-slate-500 mb-0.5 block">Tip odmora</label>
                <select name="break_type" class="bg-black/40 border border-white/10 rounded-lg px-2 py-1.5 text-white text-xs w-full focus:outline-none focus:border-violet-500">
                  <option value="small" <?= $p['break_type']==='small'?'selected':'' ?>>Mali odmor</option>
                  <option value="big"   <?= $p['break_type']==='big'  ?'selected':'' ?>>Veliki odmor</option>
                </select>
              </div>
              <div class="col-span-2">
                <button type="submit" class="w-full bg-slate-700 hover:bg-slate-600 text-white text-xs py-1.5 rounded-lg transition-all">
                  Sačuvaj čas <?= $pNum ?>
                </button>
              </div>
            </form>
          </details>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </div>

  <!-- ── INFO BOX: Dvočas ──────────────────────────────────────────────────── -->
  <div class="glass rounded-2xl p-4 border-l-4 border-violet-500/50">
    <h3 class="font-semibold text-sm mb-1">ℹ️ Dvočas — kako postaviti</h3>
    <p class="text-xs text-slate-400">
      Dvočas se podešava u <a href="schedule.php" class="text-violet-400 hover:underline">Raspored editoru</a> —
      klikni na čas i uključi prekidač <strong>"Dvočas"</strong>. Sistem automatski spaja taj čas sa sledećim
      (isti predmet, bez odmora između) i prikazuje ga kao jednu kartu u mobilnom prikazu.
    </p>
  </div>

</div>

<!-- ── JAVASCRIPT ─────────────────────────────────────────────────────────── -->
<script>
let bbCount = <?= count($bigBreaks) ?>;

function addBigBreak() {
  if (bbCount >= 2) { alert('Maksimalno 2 velika odmora po danu.'); return; }
  const list = document.getElementById('big-breaks-list');
  const div = document.createElement('div');
  div.className = 'big-break-row rounded-xl p-2 flex items-center gap-2';
  div.innerHTML = `
    <span class="text-xs text-amber-400 flex-shrink-0">posle časa</span>
    <input type="number" name="bb_after[]" value="3" min="1" max="11"
           class="w-14 text-center bg-black border border-white/10 rounded-lg px-2 py-1.5 text-white text-sm" oninput="livePreview()">
    <span class="text-xs text-amber-400 flex-shrink-0">trajanje</span>
    <input type="number" name="bb_duration[]" value="20" min="1" max="120"
           class="w-16 bg-black border border-white/10 rounded-lg px-2 py-1.5 text-white text-sm" oninput="livePreview()">
    <span class="text-xs text-slate-500">min</span>
    <button type="button" onclick="removeBigBreak(this)"
            class="ml-auto text-red-400 hover:text-red-300 text-sm">✕</button>`;
  list.appendChild(div);
  bbCount++;
  document.getElementById('add-bb-btn').disabled = bbCount >= 2;
  livePreview();
}

function removeBigBreak(btn) {
  btn.closest('.big-break-row').remove();
  bbCount = Math.max(0, bbCount - 1);
  document.getElementById('add-bb-btn').disabled = false;
  livePreview();
}

function livePreview() {
  const first   = document.getElementById('inp-first').value || '08:30';
  const dur     = Math.max(1, parseInt(document.getElementById('inp-dur').value)   || 45);
  const small_  = Math.max(0, parseInt(document.getElementById('inp-small').value) || 5);
  const count   = Math.max(1, Math.min(12, parseInt(document.getElementById('inp-count').value) || 8));

  // Parse big breaks from form
  const bbAfters    = [...document.querySelectorAll('[name="bb_after[]"]')].map(el => parseInt(el.value)||0);
  const bbDurations = [...document.querySelectorAll('[name="bb_duration[]"]')].map(el => parseInt(el.value)||0);
  const bigBreaks   = {};
  bbAfters.forEach((ap, i) => { if (ap >= 1 && bbDurations[i] > 0) bigBreaks[ap] = bbDurations[i]; });

  // Calculate
  const [hh, mm] = first.split(':').map(Number);
  let cur = hh * 60 + mm;
  const items = [];

  for (let p = 1; p <= count; p++) {
    const startStr = fmt(cur);
    cur += dur;
    const endStr   = fmt(cur);
    const isLast   = (p === count);
    const breakDur = isLast ? 0 : (bigBreaks[p] ?? small_);
    const isBig    = !isLast && bigBreaks[p] != null;
    items.push({ p, startStr, endStr, breakDur, isBig });
    cur += breakDur;
  }

  const list = document.getElementById('preview-list');
  list.innerHTML = items.map(it => `
    <div class="flex items-center gap-2 px-1">
      <span class="text-slate-500 text-xs w-4 text-right">${it.p}</span>
      <span class="font-mono text-xs text-slate-200">${it.startStr} – ${it.endStr}</span>
      <span class="text-xs ml-auto ${it.isBig ? 'text-amber-400' : 'text-slate-600'}">
        ${it.isBig ? '🔔 Veliki odmor ' : 'odmor '} ${it.breakDur > 0 ? it.breakDur+'min' : '—'}
      </span>
    </div>
  `).join('');
}

function fmt(mins) {
  return String(Math.floor(mins / 60)).padStart(2,'0') + ':' + String(mins % 60).padStart(2,'0');
}

// Run on load
livePreview();
</script>
</body>
</html>
