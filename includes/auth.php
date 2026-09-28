<?php
declare(strict_types=1);


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

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
}

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

function role_dashboard_url(string $role): string
{
    return match ($role) {
        'student' => BASE_URL . '/student/dashboard.php',
        'faculty' => BASE_URL . '/faculty/dashboard.php',
        'admin' => BASE_URL . '/admin/dashboard.php',
        default => BASE_URL . '/login.php',
    };
}