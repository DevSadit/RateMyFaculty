<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/evaluation_window.php';
require_once __DIR__ . '/../includes/release_rule.php';
require_once __DIR__ . '/../includes/csrf.php';

start_app_session();
$user = require_role('faculty');

$stmt = $pdo->prepare('SELECT faculty_id, full_name FROM faculty WHERE user_id = :user_id');
$stmt->execute([':user_id' => $user['user_id']]);
$facultyRow = $stmt->fetch();
if ($facultyRow === false) {
    http_response_code(500);
    die('Faculty profile not found.');
}
$facultyId = (int)$facultyRow['faculty_id'];

$stmt = $pdo->prepare(
    'SELECT co.offering_id, co.section, c.course_code, c.course_title,
            sem.semester_name, sem.year, sem.evaluation_open_at, sem.evaluation_close_at,
            (SELECT COUNT(*) FROM evaluations e WHERE e.offering_id = co.offering_id) AS response_count
     FROM course_offerings co
     JOIN courses c ON c.course_id = co.course_id
     JOIN semesters sem ON sem.semester_id = co.semester_id
     WHERE co.faculty_id = :faculty_id
     ORDER BY sem.year DESC, sem.semester_name, c.course_code, co.section'
);
$stmt->execute([':faculty_id' => $facultyId]);
$offerings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>Faculty Dashboard</title>
    <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/style.css">
    <script src="<?php echo e(BASE_URL); ?>/assets/js/app.js" defer></script>
</head>
<body class="app-page">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <aside class="sidebar" aria-label="Faculty workspace">
        <div class="brand"><span class="brand-mark"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg></span><span>RateMyFaculty</span></div>
        <span class="brand-subtitle">A better learning experience</span>
        <div class="nav-label">Your workspace</div>
        <nav aria-label="Main navigation"><a class="nav-link" href="dashboard.php" aria-current="page"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#grid"></use></svg><span>My offerings</span></a>
            <a class="nav-link" href="summary.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#chart"></use></svg><span>Overall summary</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Faculty workspace<span aria-hidden="true">/</span><strong>My offerings</strong></div><span class="workspace-tag">Faculty portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <p class="eyebrow">My offerings</p>
    <h1>My Course Offerings</h1>
    <p class="page-description">See how your courses are doing and explore feedback as results become available.</p>
        <div class="session-bar"><span class="session-name">Logged in as <strong><?php echo e($facultyRow['full_name']); ?></strong></span>
         <a href="summary.php">My Overall Summary</a>
    <form method="post" action="<?php echo e(BASE_URL); ?>/logout.php" style="display:inline;">
            <?php echo csrf_field(); ?>
            <button type="submit">Log out</button>
        </form></div>

    <div class="table-region" role="region" aria-label="My offerings table" tabindex="0"><table class="data-table">
        <tr><th scope="col">Course</th><th scope="col">Semester</th><th scope="col">Section</th><th scope="col">Window</th><th scope="col">Responses</th><th scope="col">Result Status</th><th scope="col">Action</th></tr>
        <?php foreach ($offerings as $o):
            $windowStatus  = get_window_status($o['evaluation_open_at'], $o['evaluation_close_at']);
            $responseCount = (int)$o['response_count'];
            $released      = is_result_released($windowStatus, $responseCount);
        ?>
            <tr>
                <td><?php echo e($o['course_code'] . ' — ' . $o['course_title']); ?></td>
                <td><?php echo e($o['semester_name'] . ' ' . $o['year']); ?></td>
                <td><?php echo e($o['section']); ?></td>
                <td><span class="status-pill"><?php echo e(window_status_label($windowStatus)); ?></span></td>
                <td><?php echo $responseCount; ?></td>
                <td>
                    <?php if ($windowStatus !== WINDOW_CLOSED): ?>
                        Window still open — results not available yet.
                    <?php elseif (!$released): ?>
                        Not enough responses to release results (minimum <?php echo RELEASE_THRESHOLD; ?> required).
                    <?php else: ?>
                        <span class="status-pill status-ok">Released</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($released): ?>
                        <a href="offering_result.php?offering_id=<?php echo (int)$o['offering_id']; ?>">View Results</a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($offerings)): ?>
            <tr><td colspan="7">You have no course offerings yet.</td></tr>
        <?php endif; ?>
    </table></div>
        </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>
</html>