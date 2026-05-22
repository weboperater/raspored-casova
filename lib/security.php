<?php
/**
 * Shared security helpers for admin sessions, CSRF protection and login safety.
 */

const ADMIN_SESSION_LIFETIME_SECONDS = 28800; // 8 hours
const ADMIN_LOGIN_WINDOW_SECONDS = 900;       // 15 minutes
const ADMIN_LOGIN_MAX_FAILURES = 5;

function sendSecurityHeaders(): void {
    if (headers_sent()) {
        return;
    }

    $isHttps = isHttpsRequest();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; object-src 'none'");

    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function sendAdminNoStoreHeaders(): void {
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function isHttpsRequest(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? null) === '443') {
        return true;
    }
    if (defined('APP_TRUST_PROXY') && APP_TRUST_PROXY === true) {
        $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $proto === 'https';
    }
    return false;
}

function enforceProductionHttps(): void {
    if (!defined('APP_ENV') || APP_ENV !== 'production' || isHttpsRequest()) {
        return;
    }

    if (headers_sent()) {
        return;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if ($host === '') {
        return;
    }

    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

function startSecureSession(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    enforceProductionHttps();
    sendSecurityHeaders();
    sendAdminNoStoreHeaders();

    session_set_cookie_params([
        'lifetime' => ADMIN_SESSION_LIFETIME_SECONDS,
        'path'     => '/',
        'secure'   => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function ensureCsrfToken(): void {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function rotateCsrfToken(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf(): string {
    ensureCsrfToken();
    return htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function verifyCsrf(bool $jsonResponse = true): void {
    ensureCsrfToken();
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        if ($jsonResponse) {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(['error' => 'Invalid CSRF token']));
        }
        exit('Invalid CSRF token');
    }
}

function destroyAdminSession(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        startSecureSession();
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => (bool)$params['secure'],
            'httponly' => (bool)$params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

function requireAdminSession(): void {
    if (empty($_SESSION['admin_id'])) {
        redirectToLogin();
    }

    $now = time();
    $lastActivity = (int)($_SESSION['last_activity_at'] ?? 0);
    if ($lastActivity > 0 && ($now - $lastActivity) > ADMIN_SESSION_LIFETIME_SECONDS) {
        destroyAdminSession();
        redirectToLogin();
    }

    $_SESSION['last_activity_at'] = $now;
    ensureCsrfToken();
}

function redirectToLogin(): void {
    header('Location: ' . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/index.php?redirect=1');
    exit;
}

function clientIpAddress(): string {
    if (defined('APP_TRUST_PROXY') && APP_TRUST_PROXY === true) {
        $forwardedFor = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwardedFor !== '') {
            $parts = array_map('trim', explode(',', $forwardedFor));
            if (filter_var($parts[0] ?? '', FILTER_VALIDATE_IP)) {
                return $parts[0];
            }
        }
    }

    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function loginAttemptIdentifier(string $username): string {
    return hash('sha256', strtolower(trim($username)) . '|' . clientIpAddress());
}

function loginRateLimitRemainingSeconds(PDO $db, string $username): int {
    $identifier = loginAttemptIdentifier($username);
    $stmt = $db->prepare("
        SELECT COUNT(*) AS failures, MIN(strftime('%s', attempted_at)) AS first_failure
        FROM login_attempts
        WHERE identifier = ?
          AND success = 0
          AND attempted_at >= datetime('now', '-' || ? || ' seconds')
    ");
    $stmt->execute([$identifier, ADMIN_LOGIN_WINDOW_SECONDS]);
    $row = $stmt->fetch();

    if (!$row || (int)$row['failures'] < ADMIN_LOGIN_MAX_FAILURES) {
        return 0;
    }

    $firstFailure = (int)$row['first_failure'];
    return max(1, ($firstFailure + ADMIN_LOGIN_WINDOW_SECONDS) - time());
}

function recordLoginAttempt(PDO $db, string $username, bool $success): void {
    $db->prepare("
        DELETE FROM login_attempts
        WHERE attempted_at < datetime('now', '-1 day')
    ")->execute();

    $db->prepare("
        INSERT INTO login_attempts (identifier, username, ip_address, success)
        VALUES (?, ?, ?, ?)
    ")->execute([
        loginAttemptIdentifier($username),
        strtolower(trim($username)),
        clientIpAddress(),
        $success ? 1 : 0,
    ]);
}

function clearFailedLoginAttempts(PDO $db, string $username): void {
    $db->prepare("
        DELETE FROM login_attempts
        WHERE identifier = ? AND success = 0
    ")->execute([loginAttemptIdentifier($username)]);
}

function adminAccessCodeEnabled(): bool {
    return defined('ADMIN_ACCESS_CODE_HASH') && trim((string)ADMIN_ACCESS_CODE_HASH) !== '';
}

function verifyAdminAccessCode(string $accessCode): bool {
    if (!adminAccessCodeEnabled()) {
        return true;
    }
    return password_verify($accessCode, (string)ADMIN_ACCESS_CODE_HASH);
}

function passwordMeetsPolicy(string $password, string $username = ''): bool {
    if (strlen($password) < 12) {
        return false;
    }
    if ($username !== '' && stripos($password, $username) !== false) {
        return false;
    }
    return (bool)preg_match('/[a-z]/i', $password)
        && (bool)preg_match('/\d/', $password)
        && (bool)preg_match('/[^a-zA-Z\d]/', $password);
}
