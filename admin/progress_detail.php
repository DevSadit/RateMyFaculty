<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';

start_app_session();
$user = require_role('admin');

$offeringId = validate_positive_int($_GET['offering_id'] ?? null);
if ($offeringId === null) {
    header('Location: progress.php');
    exit;
}

$offeringStmt = $pdo->prepare(
    'SELECT co.offering_id, c.course_code, c.course_title, co.section,
            sem.semester_name, sem.year, f.full_name AS faculty_name
     FROM course_offerings co
     JOIN courses c ON c.course_id = co.course_id
     JOIN semesters sem ON sem.semester_id = co.semester_id
     JOIN faculty f ON f.faculty_id = co.faculty_id
     WHERE co.offering_id = :id'
);
$offeringStmt->execute([':id' => $offeringId]);
$offering = $offeringStmt->fetch();
if ($offering === false) {
    http_response_code(404);
    die('Offering not found.');
}

// Only student identity + whether a receipt exists. This query never
// touches `evaluations` — it CANNOT reveal what any student answered,
// only that they answered (or didn't).
$stmt = $pdo->prepare(
    "SELECT s.student_number, s.full_name,
            CASE WHEN sr.student_id IS NOT NULL THEN 'Submitted' ELSE 'Pending' END AS submission_status
     FROM enrollments en
     JOIN students s ON s.student_id = en.student_id
     LEFT JOIN submission_receipts sr ON sr.student_id = en.student_id AND sr.offering_id = en.offering_id
     WHERE en.offering_id = :offering_id AND en.status = 'completed'
     ORDER BY s.full_name"
);
$stmt->execute([':offering_id' => $offeringId]);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>Student Submission Status</title>
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
        <nav aria-label="Main navigation"><a class="nav-link" href="dashboard.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#grid"></use></svg><span>Overview</span></a>
            <a class="nav-link" href="students.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#students"></use></svg><span>Students</span></a>
            <a class="nav-link" href="faculty.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#faculty"></use></svg><span>Faculty</span></a>
            <a class="nav-link" href="courses.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><span>Courses</span></a>
            <a class="nav-link" href="semesters.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#calendar"></use></svg><span>Semesters</span></a>
            <a class="nav-link" href="offerings.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#layers"></use></svg><span>Course offerings</span></a>
            <a class="nav-link" href="enrollments.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#enroll"></use></svg><span>Enrollments</span></a>
            <a class="nav-link" href="progress.php" aria-current="page"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#progress"></use></svg><span>Evaluation progress</span></a>
            <a class="nav-link" href="results.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#chart"></use></svg><span>Results</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Administration<span aria-hidden="true">/</span><strong>Submission status</strong></div><span class="workspace-tag">Admin portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <p class="back-link"><a href="progress.php">&laquo; Back to Progress</a></p>
    <p class="eyebrow">Submission status</p>
    <h1><?php echo e($offering['course_code'] . ' — ' . $offering['course_title']); ?></h1>
    <p class="page-description">A closer look at participation in this course offering.</p>
    <p class="context-line"><?php echo e($offering['semester_name'] . ' ' . $offering['year'] . ', Section ' . $offering['section'] . ' — ' . $offering['faculty_name']); ?></p>
    <p class="context-line"><em>Submission status only — this page never shows what any student answered.</em></p>

    <!-- UI REDESIGN: Focusable table region supports keyboard scrolling on smaller screens. -->
    <div class="table-region" role="region" aria-label="Submission status table" tabindex="0"><table class="progress-detail-table">
        <tr><th scope="col">Student #</th><th scope="col">Name</th><th scope="col">Status</th></tr>
        <?php foreach ($students as $s): ?>
            <tr>
                <td><?php echo e($s['student_number']); ?></td>
                <td><?php echo e($s['full_name']); ?></td>
                <td><?php echo e($s['submission_status']); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($students)): ?>
            <tr><td colspan="3">No students have completed this offering yet.</td></tr>
        <?php endif; ?>
    </table></div>
        </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>
</html>