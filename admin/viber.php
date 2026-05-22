<?php
require_once __DIR__ . '/_auth.php';
$db = getDB();

$msg = $err = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf(false);
    $action = $_POST['action'] ?? '';

    if ($action === 'save_template') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if (!$name) { $err = 'Naziv šablona je obavezan.'; }
        elseif (!$content) { $err = 'Sadržaj poruke je obavezan.'; }
        else {
            if ($id > 0) {
                if (!dbRecordExists($db, 'viber_templates', $id)) {
                    $err = 'Šablon nije pronađen.';
                } else {
                    $db->prepare("UPDATE viber_templates SET name=?,content=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                       ->execute([$name, $content, $id]);
                    $msg = 'Šablon ažuriran.';
                }
            } else {
                $db->prepare("INSERT INTO viber_templates (name,content) VALUES (?,?)")->execute([$name,$content]);
                $id = (int)$db->lastInsertId();
                $msg = 'Šablon dodat.';
            }
            if (!$err) {
                logAdminAction($db, 'save_template', 'viber_template', (string)$id, [
                    'name' => $name,
                ]);
            }
        }
    }

    if ($action === 'delete_template') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && dbRecordExists($db, 'viber_templates', $id)) {
            $db->prepare("DELETE FROM viber_templates WHERE id=?")->execute([$id]);
            logAdminAction($db, 'delete_template', 'viber_template', (string)$id);
            $msg = 'Šablon obrisan.';
        } else {
            $err = 'Šablon nije pronađen.';
        }
    }
}

$templates = $db->query("SELECT * FROM viber_templates ORDER BY updated_at DESC")->fetchAll();

// Current week info for auto-populating messages
$now         = new DateTime('now', new DateTimeZone('Europe/Belgrade'));
$isoWeek     = (int)$now->format('W');
$isoYear     = (int)$now->format('o');
$className   = h(getSetting($db, 'class_name', 'OG1'));
$currentType = calcWeekType($db, $isoYear, $isoWeek);
$nextType    = $currentType === 'A' ? 'B' : 'A';

// Get next week date range
$nextMon = clone $now;
$nextMon->modify('next monday');
if ($now->format('N') == 1) $nextMon = $now; // already monday
$nextMon->setISODate($isoYear, $isoWeek + 1, 1);
$nextFri = clone $nextMon;
$nextFri->modify('+4 days');
$nextRange = $nextMon->format('d.m') . '–' . $nextFri->format('d.m.Y');
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<?php renderAdminHead('Admin — Viber'); ?>
</head>
<body class="min-h-screen">

<!-- NAV -->
<?php renderAdminTopNav(); ?>
<?php renderAdminSubnav(); ?>

<div class="max-w-4xl mx-auto px-4 py-6 space-y-6">

  <?php if ($msg): ?><div class="bg-emerald-500/20 border border-emerald-500/40 rounded-xl p-3 text-emerald-300 text-sm">✅ <?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="bg-red-500/20 border border-red-500/40 rounded-xl p-3 text-red-300 text-sm">⚠️ <?= h($err) ?></div><?php endif; ?>

  <!-- ── COMPOSE / SEND PANEL ─────────────────────────────────────────── -->
  <div class="card rounded-2xl p-5">
    <h2 class="text-base font-bold mb-2">💬 Sastavi i pošalji poruku</h2>
    <p class="text-xs text-slate-500 mb-4">
      Trenutna nedelja: <strong class="text-violet-400">Nedelja <?= $currentType ?></strong> &nbsp;·&nbsp;
      Sledeća: <strong class="text-pink-400">Nedelja <?= $nextType ?></strong> (<?= $nextRange ?>)
    </p>

    <!-- Message composer -->
    <div class="mb-3">
      <label class="text-xs text-slate-400 mb-1 block">Poruka za roditelje / učenike</label>
      <textarea id="compose-area" rows="10"
                class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white text-sm focus:outline-none focus:border-violet-500 font-mono resize-y"
                placeholder="Napiši poruku ili izaberi šablon ispod..."></textarea>
    </div>

    <!-- Quick variables insert -->
    <div class="flex flex-wrap gap-2 mb-4">
      <span class="text-xs text-slate-500 self-center">Ubaci:</span>
      <?php $vars = [
        ['[RAZRED]', $className],
        ['[NEDELJA_A/B]', 'Nedelja ' . $nextType],
        ['[DATUMI]', $nextRange],
        ['[DATUM_DANAS]', $now->format('d.m.Y')],
      ]; ?>
      <?php foreach ($vars as [$var, $val]): ?>
      <button onclick="insertVar(<?= json_encode($var) ?>, <?= json_encode($val) ?>)"
              class="text-xs copy-btn rounded-lg px-2.5 py-1 text-slate-300 transition-all">
        <?= h($var) ?>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Character count + send buttons -->
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <div class="text-xs text-slate-500">
        <span id="char-count">0</span> znakova
      </div>
      <div class="flex gap-2">
        <button onclick="copyMessage()"
                class="copy-btn text-sm text-slate-300 px-4 py-2 rounded-xl transition-all font-medium">
          📋 Kopiraj
        </button>
        <button onclick="sendViber()"
                class="viber-btn text-white text-sm font-bold px-5 py-2 rounded-xl transition-all">
          💜 Pošalji na Viber
        </button>
      </div>
    </div>

    <!-- Viber info box -->
    <div class="mt-4 bg-purple-900/20 border border-purple-700/30 rounded-xl p-3">
      <div class="text-xs text-purple-300 font-semibold mb-1">ℹ️ Kako poslati na Viber grupu:</div>
      <ol class="text-xs text-slate-400 space-y-1 list-decimal list-inside">
        <li>Klikni <strong>Pošalji na Viber</strong> — na mobilnom otvara Viber app</li>
        <li>Na računaru: klikni <strong>Kopiraj</strong>, otvori Viber i nalepi (Ctrl+V)</li>
        <li>U Viberu izaberi grupu roditelja i pošalji</li>
      </ol>
    </div>
  </div>

  <!-- ── TEMPLATES SECTION ─────────────────────────────────────────────── -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Template list -->
    <div>
      <h2 class="font-bold mb-3">📝 Sačuvani šabloni (<?= count($templates) ?>)</h2>
      <?php if (empty($templates)): ?>
      <div class="card rounded-2xl p-6 text-center text-slate-500">Nema šablona. Sačuvaj prvi!</div>
      <?php endif; ?>
      <div class="space-y-2">
        <?php foreach ($templates as $t): ?>
        <div class="template-card card rounded-xl p-3 transition-colors">
          <div class="flex items-start justify-between gap-2 mb-2">
            <div>
              <div class="font-semibold text-sm text-slate-200"><?= h($t['name']) ?></div>
              <div class="text-xs text-slate-600"><?= date('d.m.Y H:i', strtotime($t['updated_at'])) ?></div>
            </div>
            <div class="flex gap-1 flex-shrink-0">
              <button onclick="loadTemplate(<?= json_encode($t['content']) ?>)"
                      class="text-xs bg-violet-600/30 hover:bg-violet-600/60 text-violet-300 px-2.5 py-1 rounded-lg transition-all">
                ↗ Koristi
              </button>
              <button onclick="editTemplate(<?= json_encode($t) ?>)"
                      class="text-xs copy-btn text-slate-300 px-2 py-1 rounded-lg transition-all">✏️</button>
              <form method="POST" class="inline-form" onsubmit="return confirm('Obriši šablon?')">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="action" value="delete_template">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                <button type="submit" class="text-xs bg-red-500/10 hover:bg-red-500/30 text-red-400 px-2 py-1 rounded-lg">🗑️</button>
              </form>
            </div>
          </div>
          <!-- Preview -->
          <div class="text-xs text-slate-500 leading-relaxed line-clamp-2 whitespace-pre-line"><?= h(substr($t['content'], 0, 120)) ?>...</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Template form -->
    <div>
      <h2 class="font-bold mb-3" id="tpl-form-title">+ Novi šablon</h2>
      <div class="card rounded-2xl p-5">
        <form method="POST" class="space-y-3">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="save_template">
          <input type="hidden" name="id" id="tpl-id" value="0">

          <div>
            <label class="text-xs text-slate-400 mb-1 block">Naziv šablona</label>
            <input type="text" name="name" id="tpl-name" required maxlength="80"
                   placeholder="npr. Izmena rasporeda"
                   class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
          </div>

          <div>
            <label class="text-xs text-slate-400 mb-1 block">Tekst poruke</label>
            <textarea name="content" id="tpl-content" rows="10" required
                      placeholder="Dragi roditelji,&#10;&#10;..."
                      class="w-full bg-black/30 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500 font-mono resize-y"></textarea>
          </div>

          <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-violet-600 hover:bg-violet-500 text-white font-bold py-2 rounded-xl text-sm transition-all" id="tpl-submit">
              Sačuvaj šablon
            </button>
            <button type="button" onclick="resetTemplateForm()"
                    id="tpl-cancel" class="hidden px-3 copy-btn text-slate-300 rounded-xl text-sm transition-all">
              Otkaži
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

</div>

<script>
const composeArea = document.getElementById('compose-area');

// Char counter
composeArea.addEventListener('input', () => {
  document.getElementById('char-count').textContent = composeArea.value.length;
});

// Load template into composer
function loadTemplate(content) {
  composeArea.value = content;
  composeArea.dispatchEvent(new Event('input'));
  composeArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
  composeArea.focus();
}

// Insert variable
function insertVar(placeholder, value) {
  const area = composeArea;
  const start = area.selectionStart;
  const end   = area.selectionEnd;
  area.value = area.value.substring(0, start) + value + area.value.substring(end);
  area.selectionStart = area.selectionEnd = start + value.length;
  area.dispatchEvent(new Event('input'));
  area.focus();
}

// Copy message
function copyMessage() {
  const text = composeArea.value.trim();
  if (!text) { alert('Poruka je prazna.'); return; }
  navigator.clipboard?.writeText(text).then(() => {
    const btn = event.target;
    const orig = btn.textContent;
    btn.textContent = '✅ Kopirano!';
    setTimeout(() => btn.textContent = orig, 2000);
  }).catch(() => alert('Nije moguće kopirati automatski.'));
}

// Send to Viber
function sendViber() {
  const text = composeArea.value.trim();
  if (!text) { alert('Napiši poruku pre slanja.'); return; }
  const encoded = encodeURIComponent(text);
  const isMobile = /Android|iPhone|iPad/i.test(navigator.userAgent);
  if (isMobile) {
    window.location.href = 'viber://forward?text=' + encoded;
    setTimeout(() => {
      navigator.clipboard?.writeText(text);
    }, 1500);
  } else {
    navigator.clipboard?.writeText(text).then(() => {
      alert('📋 Poruka kopirana u clipboard!\n\nOtvori Viber na računaru, izaberi grupu roditelja i nalepi poruku (Ctrl+V).');
    }).catch(() => {
      // Fallback: show text in prompt for manual copy
      prompt('Kopiraj ovu poruku i nalepi u Viber:', text);
    });
  }
}

// Edit template
function editTemplate(t) {
  document.getElementById('tpl-id').value = t.id;
  document.getElementById('tpl-name').value = t.name;
  document.getElementById('tpl-content').value = t.content;
  document.getElementById('tpl-form-title').textContent = '✏️ Uredi šablon';
  document.getElementById('tpl-submit').textContent = 'Sačuvaj izmene';
  document.getElementById('tpl-cancel').classList.remove('hidden');
  document.getElementById('tpl-form-title').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function resetTemplateForm() {
  document.getElementById('tpl-id').value = '0';
  document.getElementById('tpl-name').value = '';
  document.getElementById('tpl-content').value = '';
  document.getElementById('tpl-form-title').textContent = '+ Novi šablon';
  document.getElementById('tpl-submit').textContent = 'Sačuvaj šablon';
  document.getElementById('tpl-cancel').classList.add('hidden');
}
</script>
</body>
</html>
