<?php
declare(strict_types=1);

/**
 * RMF-020 / RMF-021: session lifecycle and login logic.
 * Requires $pdo to already be available (config/database.php)
 * when attempt_login() is called.
 */

/**
 * Starts the session with basic hardened cookie params.
 * Call this once at the top of every page, before touching $_SESSION.
 * (Idle-timeout and secure-cookie-over-HTTPS are added in RMF-092.)
 */
function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Looks up a username, verifies the password, and confirms the
 * account is active. Returns the user row on success, or null on
 * ANY failure (unknown username, wrong password, inactive account) —
 * the caller must not distinguish between these cases in its message.
 */
function attempt_login(PDO $pdo, string $username, string $password): ?array
{
    $stmt = $pdo->prepare(
        'SELECT user_id, username, password_hash, role, is_active
         FROM users
         WHERE username = :username
         LIMIT 1'
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user === false) {
        return null;
    }
    if ((int) $user['is_active'] !== 1) {
        return null;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }

    return $user;
}

/**
 * Establishes the logged-in session for a verified user.
 * Regenerates the session ID first, so a session ID that existed
 * before login (e.g. handed out to an anonymous visitor) can never
 * be reused to hijack the now-authenticated session.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
}

/**
 * Fully destroys the session's authentication state.
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Returns the currently logged-in user's basic session info, or
 * null if nobody is logged in.
 */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/**
 * Where a given role should land after logging in.
 */
function role_dashboard_url(string $role): string
{
    return match ($role) {
        'student' => BASE_URL . '/student/dashboard.php',
        'faculty' => BASE_URL . '/faculty/dashboard.php',
        'admin' => BASE_URL . '/admin/dashboard.php',
        default => BASE_URL . '/login.php',
    };
}