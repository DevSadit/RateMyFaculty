<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/csrf.php';

start_app_session();
$user = require_role('admin');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/style.css">
    <script src="<?php echo e(BASE_URL); ?>/assets/js/app.js" defer></script>
</head>

<body class="app-page">
    <!-- UI REDESIGN: Shared light workspace, static role navigation and accessible content landmark. -->
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <aside class="sidebar" aria-label="Administration">
        <div class="brand"><span class="brand-mark"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg></span><span>RateMyFaculty</span></div>
        <span class="brand-subtitle">A better learning experience</span>
        <div class="nav-label">Workspace</div>
        <nav aria-label="Main navigation"><a class="nav-link" href="dashboard.php" aria-current="page"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#grid"></use></svg><span>Overview</span></a>
            <a class="nav-link" href="students.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#students"></use></svg><span>Students</span></a>
            <a class="nav-link" href="faculty.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#faculty"></use></svg><span>Faculty</span></a>
            <a class="nav-link" href="courses.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><span>Courses</span></a>
            <a class="nav-link" href="semesters.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#calendar"></use></svg><span>Semesters</span></a>
            <a class="nav-link" href="offerings.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#layers"></use></svg><span>Course offerings</span></a>
            <a class="nav-link" href="enrollments.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#enroll"></use></svg><span>Enrollments</span></a>
            <a class="nav-link" href="progress.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#progress"></use></svg><span>Evaluation progress</span></a>
            <a class="nav-link" href="results.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#chart"></use></svg><span>Results</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Administration<span aria-hidden="true">/</span><strong>Overview</strong></div><span class="workspace-tag">Admin portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <!-- UI REDESIGN: Welcome panel and linked management cards. -->
    <section class="welcome-panel"><p class="eyebrow">Your academic workspace</p>
    <h1>Your campus, connected.</h1>
    <p class="page-description">Manage your academic community and keep every evaluation on track.</p>
    <p>Welcome, <?php echo e($user['username']); ?> (role: <?php echo e($user['role']); ?>)</p>
    </section><h2>Manage your campus</h2>


    <ul class="dashboard-links">
        <li><a href="<?php echo e(BASE_URL); ?>/admin/students.php"><span class="card-icon"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#students"></use></svg></span><span class="card-title">Manage Students</span><span class="card-description">Student accounts, profiles, and academic details.</span><span class="card-action">Manage students <span aria-hidden="true">&rarr;</span></span></a></li>
        <li><a href="<?php echo e(BASE_URL); ?>/admin/faculty.php"><span class="card-icon"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#faculty"></use></svg></span><span class="card-title">Manage Faculty</span><span class="card-description">Faculty profiles and account information.</span><span class="card-action">Manage faculty <span aria-hidden="true">&rarr;</span></span></a></li>
        <li><a href="<?php echo e(BASE_URL); ?>/admin/courses.php"><span class="card-icon"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg></span><span class="card-title">Manage Courses</span><span class="card-description">Your course catalog, organized and up to date.</span><span class="card-action">Explore courses <span aria-hidden="true">&rarr;</span></span></a></li>
        <li><a href="<?php echo e(BASE_URL); ?>/admin/semesters.php"><span class="card-icon"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#calendar"></use></svg></span><span class="card-title">Manage Semesters</span><span class="card-description">Academic terms and evaluation windows.</span><span class="card-action">Manage semesters <span aria-hidden="true">&rarr;</span></span></a></li>
        <li><a href="<?php echo e(BASE_URL); ?>/admin/offerings.php"><span class="card-icon"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#layers"></use></svg></span><span class="card-title">Manage Course Offerings</span><span class="card-description">Course sections and faculty assignments.</span><span class="card-action">View offerings <span aria-hidden="true">&rarr;</span></span></a></li>
        <li><a href="<?php echo e(BASE_URL); ?>/admin/enrollments.php"><span class="card-icon"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#enroll"></use></svg></span><span class="card-title">Manage Enrollments</span><span class="card-description">Student enrollments and course completion.</span><span class="card-action">View enrollments <span aria-hidden="true">&rarr;</span></span></a></li>
        <li><a href="<?php echo e(BASE_URL); ?>/admin/progress.php"><span class="card-icon"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#progress"></use></svg></span><span class="card-title">Evaluation Progress</span><span class="card-description">Participation and submission status at a glance.</span><span class="card-action">Track progress <span aria-hidden="true">&rarr;</span></span></a></li>
        <li><a href="<?php echo e(BASE_URL); ?>/admin/results.php"><span class="card-icon"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#chart"></use></svg></span><span class="card-title">Results Dashboard</span><span class="card-description">Released ratings and anonymous feedback.</span><span class="card-action">Explore results <span aria-hidden="true">&rarr;</span></span></a></li>
    </ul>

    <div class="dashboard-signout"><form class="signout-form" method="post" action="<?php echo e(BASE_URL); ?>/logout.php">
        <?php echo csrf_field(); ?>
        <button type="submit">Log out</button>
    </form></div>
        </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>

</html>