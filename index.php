<?php
/**
 * Raspored Časova — Student View (mobile-first)
 * OG1 | Auto-detects today, auto-scrolls to current day section
 */
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/db.php';

sendSecurityHeaders();

$db = getDB();

// ── Date / Week logic ─────────────────────────────────────────────────────────
$now       = new DateTime('now', new DateTimeZone('Europe/Belgrade'));
$isoWeek   = (int)$now->format('W');
$isoYear   = (int)$now->format('o');
$todayDow  = (int)$now->format('N'); // 1=Mon … 7=Sun
$todayDate = $now->format('d.m.Y');
$todayTime = $now->format('H:i');

// Allow URL param ?week=B to preview the other week (student toggle)
$requestedType = $_GET['week'] ?? null;
$currentType   = calcWeekType($db, $isoYear, $isoWeek);
$viewType      = ($requestedType === 'A' || $requestedType === 'B') ? $requestedType : $currentType;
$otherType     = $viewType === 'A' ? 'B' : 'A';
$viewingOther  = $viewType !== $currentType;

$classNameText = getSetting($db, 'class_name', 'OG1');
$schoolNameText = getSetting($db, 'school_name', 'Srednja škola');
$className   = h($classNameText);
$schoolName  = h($schoolNameText);

$schedule  = getScheduleByDay($db, $viewType);
$periods   = getPeriods($db);
$periodsMap = [];
foreach ($periods as $p) { $periodsMap[$p['period']] = $p; }

// Day meta
$dayNames = [1 => 'Ponedeljak', 2 => 'Utorak', 3 => 'Sreda', 4 => 'Četvrtak', 5 => 'Petak'];
$dayEmoji = [1 => '🌅', 2 => '🔥', 3 => '💧', 4 => '⚡', 5 => '🎉'];

// Build date strings for Mon-Fri of the displayed week.
// When previewing the other A/B week, show the next calendar week.
$displayWeekStart = clone $now;
$displayWeekStart->setISODate($isoYear, $isoWeek, 1);
if ($viewingOther) {
    $displayWeekStart->modify('+7 days');
}
$weekDates = [];
for ($d = 1; $d <= 5; $d++) {
    $dt = clone $displayWeekStart;
    $dt->modify('+' . ($d - 1) . ' days');
    $weekDates[$d] = $dt->format('d.m.Y');
}
$weekRange = ($weekDates[1] ?? '') . '–' . ($weekDates[5] ?? '');

// Current class detection (only relevant when viewing current week)
$currentPeriod = null;
$nextPeriod    = null;
if (!$viewingOther && $todayDow >= 1 && $todayDow <= 5) {
    $nowMins = (int)$now->format('H') * 60 + (int)$now->format('i');
    foreach ($periodsMap as $pNum => $p) {
        [$sh, $sm] = explode(':', $p['start_time']);
        [$eh, $em] = explode(':', $p['end_time']);
        $pStart = (int)$sh * 60 + (int)$sm;
        $pEnd   = (int)$eh * 60 + (int)$em;
        if ($nowMins >= $pStart && $nowMins <= $pEnd) {
            $currentPeriod = $pNum;
        }
        if ($nowMins < $pStart && $nextPeriod === null) {
            $nextPeriod = $pNum;
        }
    }
}

// Pass data to JS as JSON
$jsSchedule = [];
foreach ($periodsMap as $pNum => $p) {
    $jsSchedule[$pNum] = [
        'start' => $p['start_time'],
        'end'   => $p['end_time'],
    ];
}
$jsData = json_encode([
    'todayDow'    => $viewingOther ? 0 : $todayDow,
    'viewingOther'=> $viewingOther,
    'periods'     => $jsSchedule,
]);
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0f0f1a">
<meta name="robots" content="noindex, nofollow">
<title>Raspored — <?= $className ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔔</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="assets/js/theme.js"></script>
<link rel="stylesheet" href="assets/css/tailwind-local.css">
<link rel="stylesheet" href="assets/css/public.css">
<link rel="stylesheet" href="assets/css/theme.css">
</head>
<body class="min-h-screen">

<!-- ── STICKY HEADER ──────────────────────────────────────────────────────── -->
<header class="sticky-header glass border-b border-white/10">
  <div class="max-w-lg mx-auto px-4 py-3 flex items-center justify-between">
    <div>
      <div class="text-xs text-slate-400 leading-none"><?= $schoolName ?></div>
      <div class="text-lg font-bold leading-tight">
        📅 Razred <span class="text-violet-400"><?= $className ?></span>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <button type="button" class="theme-toggle" data-theme-toggle aria-label="Tema"></button>
      <!-- Week type badge -->
      <span class="<?= $viewType === 'A' ? 'week-badge-a' : 'week-badge-b' ?> text-white text-xs font-bold px-3 py-1 rounded-full">
        Nedelja <?= $viewType ?>
      </span>
      <?php if ($viewingOther): ?>
      <a href="?" class="text-xs bg-amber-500/20 text-amber-400 border border-amber-500/30 px-2 py-1 rounded-lg">
        ← Danas
      </a>
      <?php endif; ?>
    </div>
  </div>
  <!-- Current time bar -->
  <div id="time-bar" class="h-0.5 bg-gradient-to-r from-violet-500 via-pink-500 to-amber-500"></div>
</header>

<!-- ── WEEK TOGGLE + INFO BANNER ─────────────────────────────────────────── -->
<div class="max-w-lg mx-auto px-4 pt-3">
  <div class="glass rounded-2xl p-3 flex items-center justify-between mb-4">
    <div class="text-sm text-slate-300">
      <span class="text-slate-500 text-xs">Danas</span><br>
      <span class="font-semibold"><?= $dayNames[$todayDow] ?? 'Vikend' ?>, <?= $todayDate ?></span>
      <?php if ($viewingOther): ?>
      <span class="ml-2 text-xs text-amber-400 pulse-soft">• Gledaš drugu nedelju</span>
      <?php endif; ?>
    </div>
    <div class="flex gap-2">
      <a href="?week=A"
         class="text-xs px-3 py-1.5 rounded-xl font-bold transition-all <?= $viewType === 'A' ? 'week-badge-a text-white shadow-lg' : 'glass text-slate-400' ?>">
        A
      </a>
      <a href="?week=B"
         class="text-xs px-3 py-1.5 rounded-xl font-bold transition-all <?= $viewType === 'B' ? 'week-badge-b text-white shadow-lg' : 'glass text-slate-400' ?>">
        B
      </a>
    </div>
  </div>

  <!-- Current class indicator -->
  <?php
  $todayClasses = $schedule[$todayDow] ?? [];
  if (!$viewingOther && $currentPeriod !== null && !empty($todayClasses)):
      $currentClass = null;
      foreach ($todayClasses as $cls) {
          if ($cls['period'] == $currentPeriod) { $currentClass = $cls; break; }
      }
      if ($currentClass):
          $p = $periodsMap[$currentPeriod];
          [$sh, $sm] = explode(':', $p['start_time']);
          [$eh, $em] = explode(':', $p['end_time']);
          $startMins = (int)$sh*60+(int)$sm;
          $endMins   = (int)$eh*60+(int)$em;
          $nowMins2  = (int)$now->format('H')*60+(int)$now->format('i');
          $pct = min(100, max(0, round(($nowMins2-$startMins)/($endMins-$startMins)*100)));
  ?>
  <div class="dynamic-border-left glass rounded-2xl p-3 mb-4 border-l-4" data-color="<?= h($currentClass['color'] ?? '#6366f1') ?>">
    <div class="text-xs text-slate-400 mb-1">🔴 Trenutni čas (<?= h($p['start_time']) ?>–<?= h($p['end_time']) ?>)</div>
    <div class="flex items-center justify-between">
      <div>
        <span class="dynamic-color-text text-base font-bold">
          <?= h($currentClass['emoji'] ?? '📚') ?> <?= h($currentClass['subject'] ?? '') ?>
        </span>
        <?php if (!empty($currentClass['teacher'])): ?>
        <div class="text-xs text-slate-400"><?= h($currentClass['teacher']) ?></div>
        <?php endif; ?>
      </div>
      <div class="text-right">
        <div class="text-sm font-bold text-amber-400" id="time-remaining">–</div>
        <div class="text-xs text-slate-500">ostalo</div>
      </div>
    </div>
    <div class="mt-2 bg-white/10 rounded-full h-1.5 overflow-hidden">
      <div class="dynamic-progress-bg progress-bar h-full rounded-full" id="class-progress"
           data-color="<?= h($currentClass['color'] ?? '#6366f1') ?>" data-progress="<?= $pct ?>"></div>
    </div>
  </div>
  <script>
  (function(){
    const endH = <?= (int)$eh ?>, endM = <?= (int)$em ?>;
    const startH = <?= (int)$sh ?>, startM = <?= (int)$sm ?>;
    const startMins = startH*60+startM;
    const endMins   = endH*60+endM;
    function update(){
      const now = new Date();
      const nowMins = now.getHours()*60+now.getMinutes() + now.getSeconds()/60;
      const rem = Math.max(0, endMins - nowMins);
      const remH = Math.floor(rem/60);
      const remM = Math.round(rem%60);
      document.getElementById('time-remaining').textContent =
        (remH>0 ? remH+'h ' : '') + remM + 'min';
      const pct = Math.min(100, Math.max(0, (nowMins-startMins)/(endMins-startMins)*100));
      document.getElementById('class-progress').style.width = pct + '%';
    }
    update(); setInterval(update, 30000);
  })();
  </script>
  <?php endif; endif; ?>

  <!-- Next class indicator -->
  <?php
  if (!$viewingOther && $currentPeriod === null && $nextPeriod !== null && !empty($todayClasses)):
      $nextClass = null;
      foreach ($todayClasses as $cls) {
          if ($cls['period'] == $nextPeriod) { $nextClass = $cls; break; }
      }
      if ($nextClass):
          $pn = $periodsMap[$nextPeriod];
  ?>
  <div class="glass rounded-2xl p-3 mb-4 border-l-4 border-emerald-500/60">
    <div class="text-xs text-emerald-400 mb-1">⏭️ Sledeći čas — <?= h($pn['start_time']) ?></div>
    <div class="font-semibold">
      <?= h($nextClass['emoji'] ?? '📚') ?> <?= h($nextClass['subject'] ?? '') ?>
      <?php if (!empty($nextClass['teacher'])): ?>
      <span class="text-sm text-slate-400 font-normal ml-1"><?= h($nextClass['teacher']) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; endif; ?>
</div>

<!-- ── SCHEDULE DAYS ──────────────────────────────────────────────────────── -->
<div class="max-w-lg mx-auto px-4 pb-24 space-y-4" id="schedule-container">
<?php for ($day = 1; $day <= 5; $day++):
  $isToday = !$viewingOther && $day === $todayDow;
  $dayClasses = $schedule[$day] ?? [];
  $dayLabel = $dayNames[$day];
  $dayEmj   = $dayEmoji[$day];
  $dayDate  = $weekDates[$day] ?? '';
?>
<section id="day-<?= $day ?>" class="day-section">

  <!-- Day header -->
  <div class="flex items-center gap-3 mb-2 px-1">
    <div class="text-xl"><?= $dayEmj ?></div>
    <div class="flex-1">
      <h2 class="text-base font-bold <?= $isToday ? 'text-amber-400' : 'text-slate-200' ?>">
        <?= $dayLabel ?>
        <?php if ($isToday): ?>
        <span class="ml-2 text-xs bg-amber-500 text-black font-bold px-2 py-0.5 rounded-full">DANAS</span>
        <?php endif; ?>
      </h2>
      <div class="text-xs text-slate-500"><?= h($dayDate) ?></div>
    </div>
    <div class="text-xs text-slate-500"><?= count($dayClasses) ?> časova</div>
  </div>

  <?php if (empty($dayClasses)): ?>
  <!-- No classes -->
  <div class="glass rounded-2xl p-6 text-center <?= $isToday ? 'today-glow' : '' ?>">
    <div class="text-3xl mb-2">😴</div>
    <div class="text-slate-400 font-medium">Slobodan dan!</div>
    <div class="text-xs text-slate-600 mt-1">Nema nastave</div>
  </div>

  <?php else: ?>
  <!-- Classes list -->
  <div class="space-y-2 <?= $isToday ? 'today-glow rounded-2xl p-1' : '' ?>">
    <?php
    $firstPeriod = min(array_column($dayClasses, 'period'));
    $lastPeriod  = max(array_column($dayClasses, 'period'));
    $classByPeriod = [];
    foreach ($dayClasses as $cls) { $classByPeriod[$cls['period']] = $cls; }

    for ($slot = $firstPeriod; $slot <= $lastPeriod; $slot++):
      // Skip dvočas continuation slot — it's merged into the previous card
      $prevCls = $classByPeriod[$slot - 1] ?? null;
      if ($prevCls && !empty($prevCls['is_double'])) continue;

      $cls      = $classByPeriod[$slot] ?? null;
      $p        = $periodsMap[$slot] ?? ['start_time'=>'','end_time'=>'','break_after_min'=>5,'break_type'=>'small'];
      $isDouble = $cls && !empty($cls['is_double']);
      $pNext    = $isDouble ? ($periodsMap[$slot + 1] ?? null) : null;
      // For dvočas: end time is the end of next period
      $displayEnd = $pNext ? $pNext['end_time'] : $p['end_time'];
      // Break info: after dvočas use next period's break, else current period's
      $breakPeriodNum = $isDouble ? ($slot + 1) : $slot;
      $breakInfo  = $periodsMap[$breakPeriodNum] ?? $p;
      $isBigBreak = ($breakInfo['break_type'] ?? 'small') === 'big' && ($breakInfo['break_after_min'] ?? 0) > 0;
      // Current period: also highlight during dvočas continuation
      $isCurrent  = !$viewingOther && $isToday &&
                    ($slot == $currentPeriod || ($isDouble && $slot + 1 == $currentPeriod));

      if ($cls):
        $bgColor = $cls['color'] ?? '#6366f1';
    ?>
    <div class="dynamic-card class-card rounded-xl overflow-hidden <?= $isCurrent ? 'ring-2 ring-amber-400' : '' ?>"
         data-color="<?= h($bgColor) ?>"
         data-subject="<?= h(($cls['emoji'] ?? '📚') . ' ' . ($cls['subject'] ?? '')) ?>"
         data-teacher="<?= h($cls['teacher'] ?? '') ?>"
         data-time="<?= h($p['start_time'] . '–' . $displayEnd) ?>">
      <?php if ($isCurrent): ?><div class="current-class-bar"></div><?php endif; ?>
      <div class="p-3 flex items-center gap-3">
        <!-- Period badge (shows both periods for dvočas) -->
        <div class="dynamic-badge-bg flex-shrink-0 w-9 <?= $isDouble ? 'h-12' : 'h-9' ?> rounded-xl flex flex-col items-center justify-center">
          <?php if ($isDouble): ?>
          <div class="dynamic-color-text text-[9px] font-black leading-none"><?= $slot ?>–<?= $slot+1 ?></div>
          <div class="text-[8px] text-violet-300 leading-none mt-0.5">2×</div>
          <?php else: ?>
          <div class="dynamic-color-text text-xs font-black leading-none"><?= $slot ?></div>
          <?php endif; ?>
          <div class="text-[9px] text-slate-400 leading-none mt-0.5"><?= explode(':',$p['start_time'])[0] ?>:<?= explode(':',$p['start_time'])[1] ?? '00' ?></div>
        </div>
        <!-- Subject info -->
        <div class="flex-1 min-w-0">
          <div class="font-semibold text-sm leading-tight flex items-center gap-1.5 flex-wrap">
            <span><?= h($cls['emoji'] ?? '📚') ?></span>
            <span class="dynamic-color-text truncate"><?= h($cls['subject'] ?? '') ?></span>
            <?php if ($isCurrent): ?>
            <span class="text-[10px] bg-amber-400 text-black px-1.5 py-0.5 rounded-full font-bold">🔴 SADA</span>
            <?php endif; ?>
            <?php if ($isDouble): ?>
            <span class="text-[10px] bg-violet-500/30 text-violet-300 px-1.5 py-0.5 rounded-full font-bold">🔀 dvočas</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($cls['teacher'])): ?>
          <div class="text-xs text-slate-400 mt-0.5 truncate"><?= h($cls['teacher']) ?></div>
          <?php endif; ?>
        </div>
        <!-- Time -->
        <div class="text-right flex-shrink-0">
          <div class="text-xs text-slate-400"><?= h($p['start_time']) ?></div>
          <div class="text-xs text-slate-600">–<?= h($displayEnd) ?></div>
          <?php if (!empty($cls['room'])): ?>
          <div class="text-xs text-slate-500 mt-0.5">📍<?= h($cls['room']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!empty($cls['notes'])): ?>
      <div class="px-3 pb-2 text-xs text-slate-400 italic">💬 <?= h($cls['notes']) ?></div>
      <?php endif; ?>
    </div>

    <?php
    // Big break divider — show only when a big break follows and there's a next class
    $nextSlot = $slot + ($isDouble ? 2 : 1);
    $hasNextClass = false;
    for ($ns = $nextSlot; $ns <= $lastPeriod; $ns++) {
        if (isset($classByPeriod[$ns])) { $hasNextClass = true; break; }
    }
    if ($isBigBreak && $hasNextClass):
    ?>
    <div class="flex items-center gap-2 px-1 my-1">
      <div class="flex-1 border-t border-dashed border-amber-500/30"></div>
      <div class="text-[10px] text-amber-500/70 font-semibold flex items-center gap-1 flex-shrink-0">
        🔔 Veliki odmor <?= (int)($breakInfo['break_after_min'] ?? 0) ?> min
      </div>
      <div class="flex-1 border-t border-dashed border-amber-500/30"></div>
    </div>
    <?php endif; ?>

    <?php else: /* free period between classes */ ?>
    <div class="free-period rounded-xl p-2 flex items-center gap-2 opacity-40">
      <div class="w-9 h-9 rounded-xl bg-slate-800 flex items-center justify-center">
        <span class="text-xs font-bold text-slate-500"><?= $slot ?></span>
      </div>
      <div class="text-xs text-slate-600">
        <?= h($p['start_time']) ?> — <?= h($p['end_time']) ?> &nbsp;·&nbsp; slobodan čas
      </div>
    </div>
    <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

</section>
<?php endfor; ?>
</div>

<!-- ── BOTTOM SHARE BAR ───────────────────────────────────────────────────── -->
<div class="fixed bottom-0 left-0 right-0 glass border-t border-white/10 z-50">
  <div class="max-w-lg mx-auto px-4 py-3 flex items-center justify-between gap-3">
    <div class="text-xs text-slate-500">
      <?= $schoolName ?> • <?= $className ?> • Nedelja <?= $viewType ?>
    </div>
    <div class="flex gap-2">
      <!-- Copy schedule -->
      <button onclick="copySchedule()" id="copy-btn"
              class="glass text-xs text-slate-300 px-3 py-1.5 rounded-xl border border-white/10 hover:border-white/20 transition-all">
        📋 Kopiraj
      </button>
      <!-- Viber share -->
      <button onclick="shareViber()"
              class="viber-share-btn text-xs font-bold px-4 py-1.5 rounded-xl text-white transition-all">
        💬 Viber
      </button>
    </div>
  </div>
</div>

<!-- ── JAVASCRIPT ─────────────────────────────────────────────────────────── -->
<script src="assets/js/dynamic-styles.js"></script>
<script>
const DATA = <?= $jsData ?>;
const SHARE_META = {
  className: <?= json_encode($classNameText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  weekType: <?= json_encode($viewType, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  weekRange: <?= json_encode($weekRange, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
};

// ── Auto-scroll to today ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const todayEl = document.getElementById('day-' + DATA.todayDow);
  if (todayEl && !DATA.viewingOther) {
    setTimeout(() => {
      todayEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 300);
  }

  // Time bar (day progress 08:00–16:00)
  function updateTimeBar() {
    const now = new Date();
    const mins = now.getHours()*60 + now.getMinutes();
    const pct = Math.min(100, Math.max(0, (mins - 480) / (480) * 100)); // 8h-16h
    document.getElementById('time-bar').style.width = pct + '%';
  }
  updateTimeBar();
  setInterval(updateTimeBar, 60000);
});

// ── Copy schedule to clipboard ────────────────────────────────────────────
function copySchedule() {
  const text = buildScheduleShareText('copy');
  writeClipboard(text).then(() => {
    const btn = document.getElementById('copy-btn');
    btn.textContent = '✅ Kopirano!';
    setTimeout(() => { btn.textContent = '📋 Kopiraj'; }, 2000);
  }).catch(() => showManualCopy(text));
}

// ── Viber share ───────────────────────────────────────────────────────────
function shareViber() {
  const text = buildScheduleShareText('viber');
  const encoded = encodeURIComponent(text);
  const isMobile = /Android|iPhone|iPad/i.test(navigator.userAgent);

  if (isMobile) {
    window.location.href = 'viber://forward?text=' + encoded;
    setTimeout(() => writeClipboard(text).catch(() => {}), 1500);
    return;
  }

  writeClipboard(text).then(() => {
    alert('📋 Raspored je kopiran u clipboard!\n\nOtvori Viber na računaru i nalepi poruku (Ctrl+V).');
  }).catch(() => showManualCopy(text));
}

function buildScheduleShareText(mode) {
  const dayNames = ['', 'Ponedeljak', 'Utorak', 'Sreda', 'Četvrtak', 'Petak'];
  const isViber = mode === 'viber';
  let text = isViber
    ? `📅 Raspored – Razred ${SHARE_META.className}, Nedelja ${SHARE_META.weekType}\n`
    : `📅 Raspored časova — Razred ${SHARE_META.className} — Nedelja ${SHARE_META.weekType}\n`;
  text += `📆 ${SHARE_META.weekRange}\n\n`;

  document.querySelectorAll('.day-section').forEach((section, i) => {
    const dayNum = i + 1;
    const cards = section.querySelectorAll('.class-card');
    if (cards.length === 0) return;
    text += isViber ? `*${dayNames[dayNum]}*\n` : `${dayNames[dayNum]}:\n`;
    cards.forEach(card => {
      const subject = card.dataset.subject?.trim();
      const time    = card.dataset.time?.trim();
      const teacher = card.dataset.teacher?.trim();
      if (!subject) return;
      text += isViber
        ? `  ${subject} – ${time || ''}\n`
        : `  · ${subject}${teacher ? ' (' + teacher + ')' : ''} — ${time || ''}\n`;
    });
    text += '\n';
  });

  return text.trim() + '\n';
}

function writeClipboard(text) {
  if (navigator.clipboard?.writeText) {
    return navigator.clipboard.writeText(text);
  }
  return Promise.reject(new Error('Clipboard API unavailable'));
}

function showManualCopy(text) {
  window.prompt('Kopiraj tekst ručno:', text);
}
</script>

</body>
</html>
