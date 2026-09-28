<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/evaluation_window.php';
require_once __DIR__ . '/../includes/csrf.php';

start_app_session();
$user = require_role('student');

$stmt = $pdo->prepare('SELECT student_id, full_name FROM students WHERE user_id = :user_id');
$stmt->execute([':user_id' => $user['user_id']]);
$studentRow = $stmt->fetch();
if ($studentRow === false) {
    http_response_code(500);
    die('Student profile not found.');
}
$studentId = (int)$studentRow['student_id'];

// Deliberately selects NOTHING from the `evaluations` table — RMF-050 forbids
// showing any rating/comment content here, and RMF-053 forbids read-back.
$stmt = $pdo->prepare(
    'SELECT en.status AS enrollment_status,
            co.offering_id, co.section,
            c.course_code, c.course_title,
            sem.semester_name, sem.year, sem.evaluation_open_at, sem.evaluation_close_at,
            f.full_name AS faculty_name,
            (SELECT COUNT(*) FROM submission_receipts sr
             WHERE sr.student_id = :sid1 AND sr.offering_id = co.offering_id) AS receipt_count
     FROM enrollments en
     JOIN course_offerings co ON co.offering_id = en.offering_id
     JOIN courses c ON c.course_id = co.course_id
     JOIN semesters sem ON sem.semester_id = co.semester_id
     JOIN faculty f ON f.faculty_id = co.faculty_id
     WHERE en.student_id = :sid2
     ORDER BY sem.year DESC, sem.semester_name, c.course_code'
);
$stmt->execute([':sid1' => $studentId, ':sid2' => $studentId]);
$rows = $stmt->fetchAll();

$justSubmitted = isset($_GET['submitted']) && $_GET['submitted'] === '1';
$alreadyNotice = isset($_GET['already']) && $_GET['already'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>My Courses</title>
    <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/style.css">
    <script src="<?php echo e(BASE_URL); ?>/assets/js/app.js" defer></script>
</head>
<body class="app-page">
    <!-- UI REDESIGN: Shared light workspace, static role navigation and accessible content landmark. -->
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <aside class="sidebar" aria-label="Student workspace">
        <div class="brand"><span class="brand-mark"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg></span><span>RateMyFaculty</span></div>
        <span class="brand-subtitle">A better learning experience</span>
        <div class="nav-label">Your workspace</div>
        <nav aria-label="Main navigation"><a class="nav-link" href="dashboard.php" aria-current="page"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#grid"></use></svg><span>My courses</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Student workspace<span aria-hidden="true">/</span><strong>My courses</strong></div><span class="workspace-tag">Student portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <p class="eyebrow">My courses</p>
    <h1>My Courses</h1>
    <p class="page-description">Your courses. Your perspective. Help shape a better learning experience.</p>
        <div class="session-bar"><span class="session-name">Logged in as <strong><?php echo e($studentRow['full_name']); ?></strong></span>
    <form method="post" action="<?php echo e(BASE_URL); ?>/logout.php" style="display:inline;">
            <?php echo csrf_field(); ?>
            <button type="submit">Log out</button>
        </form></div>

    <?php if ($justSubmitted): ?>
        <p role="status" style="color:green;">Submitted successfully. Your feedback is anonymous and cannot be edited or viewed again.</p>
    <?php endif; ?>
    <?php if ($alreadyNotice): ?>
        <p role="alert" style="color:red;">You had already submitted an evaluation for that offering.</p>
    <?php endif; ?>

    <!-- UI REDESIGN: Focusable table region supports keyboard scrolling on smaller screens. -->
    <div class="table-region" role="region" aria-label="My courses table" tabindex="0"><table class="data-table">
        <tr>
            <th scope="col">Course</th><th scope="col">Faculty</th><th scope="col">Semester</th><th scope="col">Section</th>
            <th scope="col">Enrollment</th><th scope="col">Window</th><th scope="col">Status</th><th scope="col">Action</th>
        </tr>
        <?php foreach ($rows as $r):
            $windowStatus = get_window_status($r['evaluation_open_at'], $r['evaluation_close_at']);
            $hasReceipt   = (int)$r['receipt_count'] > 0;

            if ($r['enrollment_status'] !== 'completed') {
                $overallStatus = 'Not completed';
                $canEvaluate = false;
            } elseif ($hasReceipt) {
                $overallStatus = 'Submitted';
                $canEvaluate = false;
            } elseif ($windowStatus === WINDOW_UPCOMING) {
                $overallStatus = 'Upcoming';
                $canEvaluate = false;
            } elseif ($windowStatus === WINDOW_OPEN) {
                $overallStatus = 'Available now';
                $canEvaluate = true;
            } else {
                $overallStatus = 'Closed';
                $canEvaluate = false;
            }
        ?>
            <tr>
                <td><?php echo e($r['course_code'] . ' — ' . $r['course_title']); ?></td>
                <td><?php echo e($r['faculty_name']); ?></td>
                <td><?php echo e($r['semester_name'] . ' ' . $r['year']); ?></td>
                <td><?php echo e($r['section']); ?></td>
                <td><span class="status-pill"><?php echo $r['enrollment_status'] === 'completed' ? 'Completed' : 'Not completed'; ?></span></td>
                <td><span class="status-pill"><?php echo e(window_status_label($windowStatus)); ?></span></td>
                <td><span class="status-pill"><?php echo e($overallStatus); ?></span></td>
                <td>
                    <?php if ($canEvaluate): ?>
                        <a href="evaluate.php?offering_id=<?php echo (int)$r['offering_id']; ?>">Evaluate</a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
            <tr><td colspan="8">You have no course enrollments yet.</td></tr>
        <?php endif; ?>
    </table></div>
        </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>
</html>