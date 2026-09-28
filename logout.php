<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

start_app_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Logout must be performed via a POST request.');
}

verify_csrf_token();
logout_user();

header('Location: ' . BASE_URL . '/login.php');
exit;