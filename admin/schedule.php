<?php
require_once __DIR__ . '/_auth.php';
$db = getDB();

$weekType = in_array($_GET['week'] ?? '', ['A','B']) ? $_GET['week'] : 'A';
$otherType = $weekType === 'A' ? 'B' : 'A';

$schedule = getScheduleByDay($db, $weekType);
$periods  = getPeriods($db);
$subjects = getAllSubjects($db);
$periodsMap = [];
foreach ($periods as $p) { $periodsMap[$p['period']] = $p; }

$dayNames = [1=>'Ponedeljak', 2=>'Utorak', 3=>'Sreda', 4=>'Četvrtak', 5=>'Petak'];

// Encode subjects for JS
$subjectsJson = json_encode($subjects);
$periodsJson  = json_encode($periods);
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<?php renderAdminHead('Admin — Raspored ' . $weekType, ['https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js']); ?>
</head>
<body class="min-h-screen">

<!-- NAV -->
<?php renderAdminTopNav('Uredi Raspored', 'max-w-6xl'); ?>
<?php renderAdminSubnav('max-w-6xl'); ?>

<div class="max-w-6xl mx-auto px-4 py-4">

  <!-- Week tabs -->
  <div class="flex items-center gap-3 mb-6">
    <a href="?week=A" class="px-5 py-2 rounded-xl text-sm font-bold transition-all <?= $weekType==='A' ? 'bg-gradient-to-r from-violet-600 to-purple-600 text-white shadow-lg' : 'glass text-slate-400 hover:text-white' ?>">
      📅 Nedelja A
    </a>
    <a href="?week=B" class="px-5 py-2 rounded-xl text-sm font-bold transition-all <?= $weekType==='B' ? 'bg-gradient-to-r from-pink-600 to-rose-600 text-white shadow-lg' : 'glass text-slate-400 hover:text-white' ?>">
      📅 Nedelja B
    </a>
    <div class="ml-auto text-sm text-slate-500">
      Drag & drop da promenite redosled • Kliknite na čas da ga uredite
    </div>
  </div>

  <!-- Schedule grid: 5 day columns -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
    <?php for ($day = 1; $day <= 5; $day++):
      $dayClasses = $schedule[$day] ?? [];
      $byPeriod = [];
      foreach ($dayClasses as $cls) { $byPeriod[$cls['period']] = $cls; }
    ?>
    <div class="card rounded-2xl p-3">
      <!-- Day header -->
      <div class="flex items-center justify-between mb-3">
        <h3 class="font-bold text-sm text-slate-200"><?= $dayNames[$day] ?></h3>
        <button onclick="addSlot(<?= $day ?>)"
                class="text-xs bg-violet-600/30 hover:bg-violet-600/60 text-violet-300 px-2 py-0.5 rounded-lg transition-all">
          + Dodaj
        </button>
      </div>

      <!-- Sortable slots list -->
      <div class="slots-list space-y-1.5 min-h-[200px]"
           id="day-<?= $day ?>"
           data-day="<?= $day ?>"
           data-week="<?= $weekType ?>">

        <?php foreach ($periods as $p):
          $period = (int)$p['period'];
          $cls = $byPeriod[$period] ?? null;
          $color = $cls['color'] ?? '#4b5563';
        ?>
        <div class="slot-card rounded-xl overflow-hidden <?= $cls ? 'schedule-slot-card' : 'empty-slot' ?> <?= $cls && !empty($cls['is_double']) ? 'double-slot' : '' ?>"
             data-period="<?= $period ?>"
             data-day="<?= $day ?>"
             <?php if ($cls): ?>
             data-color="<?= h($color) ?>"
             <?php endif; ?>>

          <?php
          // Check if previous period has dvočas (this slot is continuation)
          $prevCls = $byPeriod[$period - 1] ?? null;
          $isContinuation = $prevCls && !empty($prevCls['is_double']);
          ?>
          <?php if ($cls): ?>
          <div class="p-2">
            <?php if (!empty($cls['is_double'])): ?>
            <div class="text-[9px] bg-violet-500/20 text-violet-300 text-center font-bold rounded-t pb-0.5 -mt-0.5 mb-1 border-b border-violet-500/20">
              🔀 DVOČAS → spaja sa <?= $period+1 ?>. časom
            </div>
            <?php endif; ?>
            <div class="flex items-start gap-2">
              <div class="flex-shrink-0 flex flex-col items-center">
                <div class="slot-period-number text-[10px] font-black"><?= $period ?></div>
                <div class="text-[9px] text-slate-600 mt-0.5">☰</div>
              </div>
              <div class="slot-edit-target flex-1 min-w-0"
                   onclick="editSlot('<?= $weekType ?>', <?= $day ?>, <?= $period ?>, <?= (int)$cls['subject_id'] ?>, '<?= h(addslashes($cls['room']??'')) ?>', '<?= h(addslashes($cls['notes']??'')) ?>', <?= (int)($cls['is_double']??0) ?>)">
                <div class="subject-color-text text-xs font-semibold leading-tight truncate">
                  <?= h($cls['emoji']??'📚') ?> <?= h($cls['subject']??'') ?>
                </div>
                <div class="text-[10px] text-slate-500 truncate"><?= h($cls['teacher']??'') ?></div>
                <div class="text-[10px] text-slate-600"><?= h($p['start_time']) ?>–<?= h($p['end_time']) ?></div>
              </div>
              <button onclick="deleteSlot('<?= $weekType ?>', <?= $day ?>, <?= $period ?>)"
                      class="flex-shrink-0 text-slate-600 hover:text-red-400 text-xs transition-colors ml-1">✕</button>
            </div>
          </div>
          <?php elseif ($isContinuation): ?>
          <!-- Dvočas continuation slot — not independently editable -->
          <div class="p-2 flex items-center gap-2 opacity-40">
            <span class="text-[10px] font-bold text-slate-500"><?= $period ?></span>
            <span class="text-[9px] text-violet-400">🔀 nastavak dvočasa</span>
            <span class="text-[10px] text-slate-600 ml-auto"><?= $p['start_time'] ?></span>
          </div>
          <?php else: ?>
          <!-- Empty slot — click to add -->
          <div class="p-2 flex items-center gap-2 cursor-pointer opacity-30 hover:opacity-70 transition-opacity"
               onclick="addSlotAtPeriod('<?= $weekType ?>', <?= $day ?>, <?= $period ?>)">
            <span class="text-[10px] font-bold text-slate-500"><?= $period ?></span>
            <span class="text-[10px] text-slate-600"><?= $p['start_time'] ?></span>
            <span class="text-[10px] text-slate-700 ml-auto">+ čas</span>
          </div>
          <?php endif; ?>

        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endfor; ?>
  </div>
</div>

<!-- ── EDIT MODAL ──────────────────────────────────────────────────────────── -->
<div id="slot-modal" class="is-hidden fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-4">
  <div class="bg-[#1e1e35] rounded-2xl border border-white/10 w-full max-w-sm shadow-2xl">
    <div class="p-5">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold text-white" id="modal-title">Uredi čas</h3>
        <button onclick="closeModal()" class="text-slate-400 hover:text-white text-xl leading-none">×</button>
      </div>

      <!-- Subject search -->
      <div class="mb-3">
        <label class="text-xs text-slate-400 mb-1 block">Predmet</label>
        <div class="relative">
          <input type="text" id="subject-search" placeholder="Pretraži predmet..." autocomplete="off"
                 class="w-full bg-black/40 border border-white/15 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500"
                 oninput="filterSubjects(this.value)" onfocus="showDropdown()" onblur="hideDropdown()">
          <div id="subject-dropdown" class="subject-dropdown mt-1 rounded-xl p-1">
            <!-- populated by JS -->
          </div>
        </div>
        <input type="hidden" id="selected-subject-id">
        <div id="selected-subject-preview" class="mt-2 hidden rounded-xl p-2 text-sm font-semibold"></div>
      </div>

      <!-- Room -->
      <div class="mb-3">
        <label class="text-xs text-slate-400 mb-1 block">Učionica (opciono)</label>
        <input type="text" id="slot-room" placeholder="npr. 12" maxlength="20"
               class="w-full bg-black/40 border border-white/15 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
      </div>

      <!-- Notes -->
      <div class="mb-3">
        <label class="text-xs text-slate-400 mb-1 block">Napomena (opciono)</label>
        <input type="text" id="slot-notes" placeholder="npr. doneti udžbenik" maxlength="100"
               class="w-full bg-black/40 border border-white/15 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
      </div>

      <!-- Dvočas toggle -->
      <div class="mb-4 flex items-center justify-between bg-violet-500/10 border border-violet-500/20 rounded-xl px-3 py-2.5">
        <div>
          <div class="text-sm font-semibold text-violet-300">🔀 Dvočas</div>
          <div class="text-xs text-slate-500 mt-0.5">Spaja ovaj čas sa sledećim (bez odmora između)</div>
        </div>
        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-3">
          <input type="checkbox" id="slot-double" class="sr-only peer">
          <div class="w-10 h-5 bg-slate-700 rounded-full peer peer-checked:bg-violet-600
                      after:content-[''] after:absolute after:top-0.5 after:left-0.5
                      after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all
                      peer-checked:after:translate-x-5"></div>
        </label>
      </div>

      <div class="flex gap-3">
        <button onclick="saveSlot()" id="save-btn"
                class="flex-1 bg-violet-600 hover:bg-violet-500 text-white font-bold py-2.5 rounded-xl transition-all text-sm">
          Sačuvaj
        </button>
        <button onclick="closeModal()" class="px-4 bg-white/5 hover:bg-white/10 text-slate-300 rounded-xl transition-all text-sm">
          Otkaži
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── JAVASCRIPT ─────────────────────────────────────────────────────────── -->
<script src="../assets/js/dynamic-styles.js"></script>
<script>
const SUBJECTS  = <?= $subjectsJson ?>;
const PERIODS   = <?= $periodsJson ?>;
const WEEK_TYPE = '<?= $weekType ?>';
const CSRF      = '<?= csrf() ?>';

let modalState = { weekType:'', day:0, period:0 };
const dayNames = ['','Ponedeljak','Utorak','Sreda','Četvrtak','Petak'];

// ── Sortable drag-drop per day ────────────────────────────────────────────
document.querySelectorAll('.slots-list').forEach(list => {
  Sortable.create(list, {
    animation: 150,
    handle: '.slot-card',
    ghostClass: 'sortable-ghost',
    chosenClass: 'sortable-chosen',
    filter: '.empty-slot, .double-slot',
    onEnd(evt) {
      if (evt.oldIndex === evt.newIndex) return;
      const day = parseInt(list.dataset.day);
      // Get period numbers from data-period attribute of moved items
      const items = [...list.querySelectorAll('.slot-card[data-period]')];
      const periods = items.map(el => parseInt(el.dataset.period));
      // The dragged item and drop target
      const p1 = parseInt(evt.item.dataset.period);
      // Find what period is now at the target position
      const targetEl = items[evt.newIndex];
      const p2 = parseInt(targetEl?.dataset.period ?? 0);
      if (p1 && p2 && p1 !== p2) {
        if (!confirmAdminAction('Zameniti mesta ovim časovima?', [
          `Nedelja ${WEEK_TYPE}`,
          `${dayNames[day] || 'Dan'}: ${p1}. ↔ ${p2}. čas`,
        ])) {
          reloadPage();
          return;
        }
        apiCall('swap_slots', { week_type: WEEK_TYPE, day, period1: p1, period2: p2 })
          .then(r => {
            if (r.ok) return; // visual already updated by Sortable
            notifyAdminError(r.error || 'Zamena časova nije sačuvana.');
            reloadPage();
          })
          .catch(err => {
            notifyAdminError(err.message || 'Zamena časova nije sačuvana.');
            reloadPage();
          });
      }
    }
  });
});

// ── Modal helpers ─────────────────────────────────────────────────────────
function openModal(weekType, day, period, subjectId, room, notes, isNew, isDouble) {
  const p = PERIODS.find(p => p.period == period) || {};
  const pNext = PERIODS.find(p => p.period == period + 1);
  modalState = { weekType, day, period, isNew: !!isNew, originalDouble: !!isDouble };

  // Show time range, and for dvočas also the next period end time
  const timeRange = (p.start_time||'') + '–' + (p.end_time||'');
  document.getElementById('modal-title').textContent =
    (isNew ? '+ Novi čas — ' : 'Uredi čas — ') + dayNames[day] + ', ' + timeRange;
  document.getElementById('slot-room').value  = room || '';
  document.getElementById('slot-notes').value = notes || '';
  document.getElementById('selected-subject-id').value = subjectId || '';
  document.getElementById('subject-search').value = '';

  // Dvočas toggle
  const doubleChk = document.getElementById('slot-double');
  doubleChk.checked = !!isDouble;
  // Hide dvočas toggle if last period (no next period)
  doubleChk.closest('.flex.items-center.justify-between').style.display =
    pNext ? '' : 'none';

  if (subjectId) {
    const sub = SUBJECTS.find(s => s.id == subjectId);
    if (sub) showSelectedSubject(sub);
    else hideSelectedSubject();
  } else {
    hideSelectedSubject();
  }

  renderDropdown(SUBJECTS);
  document.getElementById('slot-modal').classList.remove('is-hidden');
}

function closeModal() {
  document.getElementById('slot-modal').classList.add('is-hidden');
}

function editSlot(weekType, day, period, subjectId, room, notes, isDouble) {
  openModal(weekType, day, period, subjectId, room, notes, false, isDouble);
}
function addSlot(day) {
  // Find first empty period
  const occupiedPeriods = [...document.querySelectorAll(`#day-${day} .slot-card[data-period]`)]
    .filter(el => el.querySelector('.text-xs.font-semibold'))
    .map(el => parseInt(el.dataset.period));
  const allPeriods = PERIODS.map(p => p.period);
  const freePeriod = allPeriods.find(p => !occupiedPeriods.includes(p)) || 1;
  openModal(WEEK_TYPE, day, freePeriod, null, '', '', true);
}
function addSlotAtPeriod(weekType, day, period) {
  openModal(weekType, day, period, null, '', '', true);
}

// ── Subject search/select ─────────────────────────────────────────────────
function renderDropdown(subs) {
  const dd = document.getElementById('subject-dropdown');
  replaceChildren(dd);
  if (subs.length === 0) {
    dd.appendChild(createNode('div', 'text-xs text-slate-500 px-3 py-2', 'Nema rezultata'));
  } else {
    subs.forEach(s => dd.appendChild(createSubjectOption(s)));
  }
  window.applyDynamicStyles?.(dd);
}

function createSubjectOption(subject) {
  const option = createNode(
    'div',
    'subject-dropdown-option flex items-center gap-2 px-3 py-2 hover:bg-white/5 rounded-lg cursor-pointer text-sm'
  );
  option.dataset.color = subject.color || '';
  option.addEventListener('mousedown', () => selectSubject(subject.id));

  option.appendChild(createNode('span', '', subject.emoji || '📚'));

  const content = createNode('div');
  content.appendChild(createNode('div', 'font-semibold leading-tight', subject.name || ''));
  content.appendChild(createNode('div', 'text-xs text-slate-500 leading-none', subject.teacher || '—'));
  option.appendChild(content);

  return option;
}

function filterSubjects(q) {
  const filtered = q.trim()
    ? SUBJECTS.filter(s =>
        s.name.toLowerCase().includes(q.toLowerCase()) ||
        (s.teacher||'').toLowerCase().includes(q.toLowerCase()) ||
        (s.short_name||'').toLowerCase().includes(q.toLowerCase())
      )
    : SUBJECTS;
  renderDropdown(filtered);
  showDropdown();
}

function showDropdown() {
  document.getElementById('subject-dropdown').classList.add('open');
}
function hideDropdown() {
  setTimeout(() => document.getElementById('subject-dropdown').classList.remove('open'), 200);
}

function selectSubject(id) {
  const sub = SUBJECTS.find(s => s.id === id);
  if (!sub) return;
  document.getElementById('selected-subject-id').value = id;
  document.getElementById('subject-search').value = sub.name;
  showSelectedSubject(sub);
  hideDropdown();
}

function showSelectedSubject(sub) {
  const el = document.getElementById('selected-subject-preview');
  el.style.background = sub.color + '22';
  el.style.border = '1px solid ' + sub.color + '44';
  el.style.color = sub.color;
  replaceChildren(el);
  el.appendChild(document.createTextNode(`${sub.emoji || '📚'} ${sub.name || ''} `));

  const teacher = createNode(
    'span',
    'selected-subject-teacher',
    `\u00a0·\u00a0${sub.teacher || '—'}`
  );
  el.appendChild(teacher);
  el.classList.remove('hidden');
}
function hideSelectedSubject() {
  document.getElementById('selected-subject-preview').classList.add('hidden');
}

function createNode(tagName, className = '', text = '') {
  const el = document.createElement(tagName);
  if (className) el.className = className;
  if (text !== '') el.textContent = text;
  return el;
}

function replaceChildren(el) {
  while (el.firstChild) {
    el.removeChild(el.firstChild);
  }
}

// ── Save slot ─────────────────────────────────────────────────────────────
async function saveSlot() {
  const subjectId = document.getElementById('selected-subject-id').value;
  if (!subjectId) { alert('Izaberi predmet.'); return; }
  const willBeDouble = document.getElementById('slot-double').checked;
  if (willBeDouble && !modalState.originalDouble && nextPeriodHasClass(modalState.day, modalState.period)) {
    if (!confirmAdminAction('Uključiti dvočas?', [
      `${dayNames[modalState.day]}: ${modalState.period}. i ${modalState.period + 1}. čas`,
      'Sledeći čas će biti obrisan jer postaje nastavak dvočasa.',
    ])) {
      return;
    }
  }

  const btn = document.getElementById('save-btn');
  btn.textContent = 'Čuvam...';
  btn.disabled = true;

  try {
    const r = await apiCall('update_slot', {
      week_type:  modalState.weekType,
      day:        modalState.day,
      period:     modalState.period,
      subject_id: subjectId,
      room:       document.getElementById('slot-room').value,
      notes:      document.getElementById('slot-notes').value,
      is_double:  document.getElementById('slot-double').checked ? '1' : '0',
    });

    if (r.ok) { reloadPage(); }
    else { notifyAdminError(r.error || 'Čas nije sačuvan.'); btn.textContent='Sačuvaj'; btn.disabled=false; }
  } catch (err) {
    notifyAdminError(err.message || 'Čas nije sačuvan.');
    btn.textContent='Sačuvaj';
    btn.disabled=false;
  }
}

// ── Delete slot ───────────────────────────────────────────────────────────
async function deleteSlot(weekType, day, period) {
  if (!confirmAdminAction('Obrisati ovaj čas?', [
    `Nedelja ${weekType}`,
    `${dayNames[day] || 'Dan'}: ${period}. čas`,
    'Ova akcija se ne može poništiti iz editora.',
  ])) return;
  try {
    const r = await apiCall('delete_slot', { week_type: weekType, day, period });
    if (r.ok) reloadPage();
    else notifyAdminError(r.error || 'Čas nije obrisan.');
  } catch (err) {
    notifyAdminError(err.message || 'Čas nije obrisan.');
  }
}

function nextPeriodHasClass(day, period) {
  const nextSlot = document.querySelector(`#day-${day} .slot-card[data-period="${period + 1}"]`);
  return !!nextSlot?.querySelector('.slot-edit-target');
}

function confirmAdminAction(title, lines = []) {
  const body = lines.filter(Boolean).join('\n');
  return confirm(body ? `${title}\n\n${body}` : title);
}

// ── API helper ────────────────────────────────────────────────────────────
async function apiCall(action, data) {
  const body = new URLSearchParams({ action, csrf_token: CSRF, ...data });
  const resp = await fetch('api.php', { method:'POST', body });
  let payload = {};
  try {
    payload = await resp.json();
  } catch (err) {
    payload = {};
  }
  if (!resp.ok && !payload.error) {
    payload.error = resp.status === 400 ? 'Neispravan zahtev.' : 'Server nije sačuvao izmenu.';
  }
  return payload;
}

function notifyAdminError(message) {
  alert('Greška: ' + message);
}

function reloadPage() { window.location.reload(); }

// Close modal on backdrop click
document.getElementById('slot-modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
</body>
</html>
