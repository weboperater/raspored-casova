<?php
/**
 * Admin Login
 */
require_once __DIR__ . '/../lib/security.php';
startSecureSession();
ensureCsrfToken();

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php'); exit;
}

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/_nav.php';
$db = getDB();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(false);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $accessCode = $_POST['access_code'] ?? '';

    if ($username && $password) {
        $lockSeconds = loginRateLimitRemainingSeconds($db, $username);
        if ($lockSeconds > 0) {
            $error = 'Previše neuspešnih pokušaja. Pokušajte ponovo za ' . ceil($lockSeconds / 60) . ' min.';
        } else {
            $stmt = $db->prepare("SELECT id, username, password FROM admins WHERE username = ?");
            $stmt->execute([strtolower($username)]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password']) && verifyAdminAccessCode($accessCode)) {
                clearFailedLoginAttempts($db, $username);
                recordLoginAttempt($db, $username, true);
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_user'] = $admin['username'];
                $_SESSION['last_activity_at'] = time();
                rotateCsrfToken();
                header('Location: dashboard.php'); exit;
            }

            recordLoginAttempt($db, $username, false);
            $error = 'Pogrešno korisničko ime ili lozinka.';
            sleep(1);
        }
    }
    if (!$error) {
        $error = 'Pogrešno korisničko ime ili lozinka.';
        sleep(1);
    }
}

$redirect = !empty($_GET['redirect']);
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<?php renderAdminHead('Admin Login — Raspored'); ?>
</head>
<body class="login-page flex items-center justify-center min-h-screen p-4">
<div class="w-full max-w-sm">
  <div class="flex justify-center mb-4">
    <button type="button" class="theme-toggle" data-theme-toggle aria-label="Tema"></button>
  </div>
  <div class="text-center mb-8">
    <div class="text-4xl mb-2">📅</div>
    <h1 class="text-2xl font-bold text-white">Admin Panel</h1>
    <p class="text-slate-400 text-sm mt-1">Raspored Časova — Razredna Starešina</p>
  </div>

  <?php if ($redirect): ?>
  <div class="bg-amber-500/20 border border-amber-500/40 rounded-xl p-3 mb-4 text-amber-300 text-sm text-center">
    Molimo prijavite se za pristup admin panelu.
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="bg-red-500/20 border border-red-500/40 rounded-xl p-3 mb-4 text-red-300 text-sm text-center">
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST" class="space-y-4">
    <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
    <div>
      <label class="block text-sm text-slate-400 mb-1">Korisničko ime</label>
      <input type="text" name="username" autocomplete="username" required
             class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500"
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    </div>
    <div>
      <label class="block text-sm text-slate-400 mb-1">Lozinka</label>
      <input type="password" name="password" autocomplete="current-password" required
             class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500"
             placeholder="••••••••">
    </div>
    <?php if (adminAccessCodeEnabled()): ?>
    <div>
      <label class="block text-sm text-slate-400 mb-1">Admin kod</label>
      <input type="password" name="access_code" autocomplete="one-time-code" required
             class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500"
             placeholder="Dodatni pristupni kod">
    </div>
    <?php endif; ?>
    <button type="submit"
            class="w-full bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-500 hover:to-purple-500 text-white font-bold py-3 rounded-xl transition-all">
      Prijavi se
    </button>
  </form>

  <div class="mt-6 text-center">
    <a href="../index.php" class="text-slate-500 text-sm hover:text-slate-300 transition-colors">
      ← Nazad na raspored
    </a>
  </div>
</div>
</body>
</html>
