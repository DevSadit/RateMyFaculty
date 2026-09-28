<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/auth.php';



start_app_session();

// Already logged in? Don't show the login form again.
if (is_logged_in()) {
    $user = current_user();
    header('Location: ' . role_dashboard_url($user['role']));
    exit;
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();


    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {


        $errorMessage = 'Invalid username or password.';
    } else {
        $user = attempt_login($pdo, $username, $password);

        if ($user === null) {
            // Deliberately identical message for "no such user" and
            // "wrong password" — don't help an attacker enumerate accounts.
            $errorMessage = 'Invalid username or password.';
        } else {
            login_user($user);
            header('Location: ' . role_dashboard_url($user['role']));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>RateMyFaculty — Login</title>
    <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/style.css">
    <script src="<?php echo e(BASE_URL); ?>/assets/js/app.js" defer></script>
</head>

<body class="login-page">
    <!-- UI REDESIGN: Light split layout; original authentication form is unchanged. -->
    <a class="skip-link" href="#main-content">Skip to login</a>
    <div class="login-layout">
        <aside class="login-story" aria-label="About RateMyFaculty"><div class="brand"><span class="brand-mark"><svg class="icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#book"></use></svg></span><span>RateMyFaculty</span></div><p class="eyebrow">A little feedback. A lasting impact.</p><h2>Better learning<br>starts with <span>you.</span></h2><p>A space for student perspectives, thoughtful reflection, and a stronger academic community.</p><img class="login-art" src="assets/feedback-illustration.svg" width="440" height="270" alt="" aria-hidden="true"><div class="story-footnote"><svg class="icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#book"></use></svg>Made for your academic community</div></aside>
        <main class="login-content" id="main-content" tabindex="-1"><div class="login-card"><span class="card-icon"><svg class="icon" aria-hidden="true" focusable="false"><use href="assets/icons.svg#login"></use></svg></span>
    <h1>Welcome back.</h1>
    <p class="page-description">Log in to your RateMyFaculty account to continue.</p>

    <?php if ($errorMessage !== ''): ?>
        <p role="alert" style="color: red;"><?php echo e($errorMessage); ?></p>
    <?php endif; ?>

    <form class="login-form" method="post" action="login.php">
        <?php echo csrf_field(); ?>
        <div class="field"><label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus></div>

        <div class="field"><label for="password">Password</label>
        <input type="password" id="password" name="password" required></div>

        <button type="submit">Log In <span aria-hidden="true">&rarr;</span></button>
    </form>
<p class="login-note">One place for students, faculty, and administrators.</p></div><footer class="login-footer">RateMyFaculty &nbsp;·&nbsp; Every perspective matters.</footer></main></div>
</body>

</html>