<?php
require_once __DIR__ . '/_auth.php';
$db = getDB();

$selectedAction = trim((string)($_GET['action'] ?? ''));
$selectedEntity = trim((string)($_GET['entity_type'] ?? ''));

$actions = getAdminAuditFilterValues($db, 'action');
$entityTypes = getAdminAuditFilterValues($db, 'entity_type');
$logs = getAdminAuditLogs($db, 100, $selectedAction, $selectedEntity);

function auditDetailsText(?string $json): string {
    if (!$json) {
        return '';
    }
    $details = json_decode($json, true);
    if (!is_array($details) || !$details) {
        return '';
    }

    $parts = [];
    foreach ($details as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $parts[] = $key . ': ' . (string)$value;
        } else {
            $parts[] = $key . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    return implode(' · ', $parts);
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<?php renderAdminHead('Admin — Istorija izmena'); ?>
</head>
<body class="min-h-screen">

<!-- NAV -->
<?php renderAdminTopNav(); ?>
<?php renderAdminSubnav(); ?>

<div class="max-w-4xl mx-auto px-4 py-6 space-y-6">
  <div>
    <h1 class="text-2xl font-bold">Istorija izmena</h1>
    <p class="text-slate-400 text-sm mt-1">Poslednjih 100 admin akcija, sa osnovnim filterima.</p>
  </div>

  <div class="card rounded-2xl p-5">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div>
        <label class="text-xs text-slate-400 mb-1 block">Akcija</label>
        <select name="action" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
          <option value="">Sve akcije</option>
          <?php foreach ($actions as $action): ?>
          <option value="<?= h($action) ?>" <?= $selectedAction === $action ? 'selected' : '' ?>><?= h($action) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-xs text-slate-400 mb-1 block">Tip</label>
        <select name="entity_type" class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet-500">
          <option value="">Svi tipovi</option>
          <?php foreach ($entityTypes as $entityType): ?>
          <option value="<?= h($entityType) ?>" <?= $selectedEntity === $entityType ? 'selected' : '' ?>><?= h($entityType) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex items-end gap-2">
        <button type="submit" class="bg-violet-600 hover:bg-violet-500 text-white text-sm px-4 py-2 rounded-lg transition-all">Filtriraj</button>
        <a href="audit.php" class="bg-slate-700 hover:bg-slate-600 text-white text-sm px-4 py-2 rounded-lg transition-all">Reset</a>
      </div>
    </form>
  </div>

  <div class="card rounded-2xl p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <h2 class="text-base font-bold">Zapisi</h2>
      <span class="text-xs text-slate-500"><?= count($logs) ?> prikazano</span>
    </div>

    <?php if (!$logs): ?>
      <div class="text-slate-500 text-sm">Nema zapisa za izabrani filter.</div>
    <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($logs as $log): ?>
          <?php $detailText = auditDetailsText($log['details'] ?? null); ?>
          <div class="bg-black/20 border border-white/10 rounded-xl px-3 py-3">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-sm font-semibold text-slate-100"><?= h($log['action']) ?></div>
                <div class="text-xs text-slate-500 mt-1">
                  <?= h($log['admin_user'] ?: 'admin') ?>
                  <?php if ($log['entity_type']): ?>
                    · <?= h($log['entity_type']) ?><?= $log['entity_id'] ? ':' . h($log['entity_id']) : '' ?>
                  <?php endif; ?>
                  <?php if ($log['ip_address']): ?>
                    · <?= h($log['ip_address']) ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="text-xs text-slate-500 whitespace-nowrap"><?= h($log['created_at']) ?></div>
            </div>
            <?php if ($detailText): ?>
              <div class="text-xs text-slate-400 mt-2"><?= h($detailText) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
