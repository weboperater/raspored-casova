<?php
/**
 * Shared admin navigation helpers.
 */

function adminNavItems(): array {
    return [
        ['dashboard.php', '🏠 Dashboard'],
        ['schedule.php',  '📅 Raspored'],
        ['periods.php',   '⏰ Termini'],
        ['subjects.php',  '📚 Predmeti'],
        ['viber.php',     '💬 Viber'],
        ['audit.php',     '🧾 Izmene'],
        ['help.php',      '📖 Uputstvo'],
    ];
}

function renderAdminHead(string $title, array $extraScripts = []): void {
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#0f0f1a">
    <meta name="robots" content="noindex, nofollow">
    <title><?= h($title) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔔</text></svg>">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/tailwind-local.css">
    <?php foreach ($extraScripts as $src): ?>
    <script src="<?= h((string)$src) ?>"></script>
    <?php endforeach; ?>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <?php
}

function renderAdminTopNav(
    string $title = 'Admin Panel',
    string $maxWidthClass = 'max-w-4xl',
    string $className = '',
    bool $showUser = false
): void {
    ?>
    <nav class="glass sticky top-0 z-50 border-b border-white/10">
      <div class="<?= h($maxWidthClass) ?> mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <a href="../index.php" class="text-slate-400 hover:text-white text-sm">← Raspored</a>
          <span class="text-slate-600">|</span>
          <span class="font-bold text-violet-400"><?= h($title) ?></span>
          <?php if ($className !== ''): ?>
          <span class="text-slate-500 text-sm hidden sm:inline">– <?= h($className) ?></span>
          <?php endif; ?>
        </div>
        <div class="flex items-center gap-3">
          <button type="button" class="theme-toggle" data-theme-toggle aria-label="Tema"></button>
          <?php if ($showUser): ?>
          <span class="text-sm text-slate-500 hidden sm:inline"><?= h($_SESSION['admin_user'] ?? '') ?></span>
          <?php endif; ?>
          <a href="logout.php" class="text-xs text-slate-400 hover:text-red-400 transition-colors">
            <?= $showUser ? 'Odjavi se' : 'Odjavi' ?>
          </a>
        </div>
      </div>
    </nav>
    <?php
}

function renderAdminSubnav(string $maxWidthClass = 'max-w-4xl'): void {
    $current = basename($_SERVER['PHP_SELF'] ?? '');
    ?>
    <div class="border-b border-white/10 bg-black/20">
      <div class="<?= h($maxWidthClass) ?> mx-auto px-4 flex gap-1 overflow-x-auto py-1">
        <?php foreach (adminNavItems() as [$href, $label]): ?>
        <a href="<?= h($href) ?>"
           class="<?= $current === $href ? 'bg-violet-600 text-white' : 'text-slate-400 hover:text-white hover:bg-white/5' ?>
                  text-sm px-3 py-1.5 rounded-lg transition-all whitespace-nowrap">
          <?= h($label) ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
}
