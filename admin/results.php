<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/evaluation_window.php';
require_once __DIR__ . '/../includes/release_rule.php';

start_app_session();
$user = require_role('admin');

$stmt = $pdo->query(
    'SELECT co.offering_id, co.section, c.course_code, c.course_title,
            sem.semester_name, sem.year, sem.evaluation_open_at, sem.evaluation_close_at,
            f.full_name AS faculty_name,
            (SELECT COUNT(*) FROM evaluations e WHERE e.offering_id = co.offering_id) AS response_count
     FROM course_offerings co
     JOIN courses c ON c.course_id = co.course_id
     JOIN semesters sem ON sem.semester_id = co.semester_id
     JOIN faculty f ON f.faculty_id = co.faculty_id
     ORDER BY sem.year DESC, sem.semester_name, c.course_code, co.section'
);
$offerings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>Admin — Results</title>
    <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/style.css">
    <script src="<?php echo e(BASE_URL); ?>/assets/js/app.js" defer></script>
</head>
<body class="app-page">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <aside class="sidebar" aria-label="Administration">
        <div class="brand"><span class="brand-mark"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg></span><span>RateMyFaculty</span></div>
        <span class="brand-subtitle">A better learning experience</span>
        <div class="nav-label">Workspace</div>
        <nav aria-label="Main navigation"><a class="nav-link" href="dashboard.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#grid"></use></svg><span>Overview</span></a>
            <a class="nav-link" href="students.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#students"></use></svg><span>Students</span></a>
            <a class="nav-link" href="faculty.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#faculty"></use></svg><span>Faculty</span></a>
            <a class="nav-link" href="courses.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><span>Courses</span></a>
            <a class="nav-link" href="semesters.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#calendar"></use></svg><span>Semesters</span></a>
            <a class="nav-link" href="offerings.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#layers"></use></svg><span>Course offerings</span></a>
            <a class="nav-link" href="enrollments.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#enroll"></use></svg><span>Enrollments</span></a>
            <a class="nav-link" href="progress.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#progress"></use></svg><span>Evaluation progress</span></a>
            <a class="nav-link" href="results.php" aria-current="page"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#chart"></use></svg><span>Results</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Administration<span aria-hidden="true">/</span><strong>Results</strong></div><span class="workspace-tag">Admin portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <p class="back-link"><a href="<?php echo e(BASE_URL); ?>/admin/dashboard.php">&laquo; Back to Dashboard</a></p>
    <p class="eyebrow">Results</p>
    <h1>Evaluation Results</h1>
    <p class="page-description">Explore result availability and released feedback across your campus.</p>

    <div class="table-region" role="region" aria-label="Results table" tabindex="0"><table class="data-table">
        <tr><th scope="col">Course</th><th scope="col">Faculty</th><th scope="col">Semester</th><th scope="col">Section</th><th scope="col">Window</th><th scope="col">Responses</th><th scope="col">Result Status</th><th scope="col">Action</th></tr>
        <?php foreach ($offerings as $o):
            $windowStatus  = get_window_status($o['evaluation_open_at'], $o['evaluation_close_at']);
            $responseCount = (int)$o['response_count'];
            $released      = is_result_released($windowStatus, $responseCount);
        ?>
            <tr>
                <td><?php echo e($o['course_code'] . ' — ' . $o['course_title']); ?></td>
                <td><?php echo e($o['faculty_name']); ?></td>
                <td><?php echo e($o['semester_name'] . ' ' . $o['year']); ?></td>
                <td><?php echo e($o['section']); ?></td>
                <td><?php echo e(window_status_label($windowStatus)); ?></td>
                <td><?php echo $responseCount; ?></td>
                <td>
                    <?php if ($windowStatus !== WINDOW_CLOSED): ?>
                        Window open — hidden
                    <?php elseif (!$released): ?>
                        Below threshold — hidden
                    <?php else: ?>
                        <span class="status-pill status-ok">Released</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($released): ?>
                        <a href="result_detail.php?offering_id=<?php echo (int)$o['offering_id']; ?>">View Results</a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($offerings)): ?>
            <tr><td colspan="8">No course offerings yet.</td></tr>
        <?php endif; ?>
    </table></div>
        </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>
</html>