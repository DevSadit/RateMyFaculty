<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';

start_app_session();
$user = require_role('admin');

// Purely operational counts. Deliberately never joins or selects
// anything from `evaluations` — this page only ever needs to know
// WHETHER a receipt exists, never what the content was.
$stmt = $pdo->query(
    "SELECT co.offering_id, c.course_code, co.section, sem.semester_name, sem.year, f.full_name AS faculty_name,
            COUNT(DISTINCT CASE WHEN en.status = 'completed' THEN en.student_id END) AS eligible_count,
            COUNT(DISTINCT CASE WHEN en.status = 'completed' AND sr.student_id IS NOT NULL THEN en.student_id END) AS submitted_count
     FROM course_offerings co
     JOIN courses c ON c.course_id = co.course_id
     JOIN semesters sem ON sem.semester_id = co.semester_id
     JOIN faculty f ON f.faculty_id = co.faculty_id
     LEFT JOIN enrollments en ON en.offering_id = co.offering_id
     LEFT JOIN submission_receipts sr ON sr.offering_id = co.offering_id AND sr.student_id = en.student_id
     GROUP BY co.offering_id, c.course_code, co.section, sem.semester_name, sem.year, f.full_name
     ORDER BY sem.year DESC, sem.semester_name, c.course_code, co.section"
);
$offerings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>Admin — Evaluation Progress</title>
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
        <header class="topbar"><div class="topbar-path">Administration<span aria-hidden="true">/</span><strong>Evaluation progress</strong></div><span class="workspace-tag">Admin portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <p class="back-link"><a href="<?php echo e(BASE_URL); ?>/admin/dashboard.php">&laquo; Back to Dashboard</a></p>
    <p class="eyebrow">Evaluation progress</p>
    <h1>Evaluation Progress</h1>
    <p class="page-description">Follow participation across course offerings and see what is still pending.</p>
    <p class="context-line"><em>This page shows submission status only — no ratings or comments.</em></p>

    <!-- UI REDESIGN: Focusable table region supports keyboard scrolling on smaller screens. -->
    <div class="table-region" role="region" aria-label="Evaluation progress table" tabindex="0"><table class="data-table">
        <tr><th scope="col">Course</th><th scope="col">Faculty</th><th scope="col">Semester</th><th scope="col">Section</th><th scope="col">Eligible</th><th scope="col">Submitted</th><th scope="col">Pending</th><th scope="col">Response Rate</th><th scope="col">Action</th></tr>
        <?php foreach ($offerings as $o):
            $eligible  = (int)$o['eligible_count'];
            $submitted = (int)$o['submitted_count'];
            $pending   = $eligible - $submitted;
            $rate      = $eligible > 0 ? round(($submitted / $eligible) * 100, 1) : 0.0;
        ?>
            <tr>
                <td><?php echo e($o['course_code']); ?></td>
                <td><?php echo e($o['faculty_name']); ?></td>
                <td><?php echo e($o['semester_name'] . ' ' . $o['year']); ?></td>
                <td><?php echo e($o['section']); ?></td>
                <td><?php echo $eligible; ?></td>
                <td><?php echo $submitted; ?></td>
                <td><?php echo $pending; ?></td>
                <td><?php echo e((string)$rate); ?>%</td>
                <td class="actions-cell"><a href="progress_detail.php?offering_id=<?php echo (int)$o['offering_id']; ?>">View Student List</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($offerings)): ?>
            <tr><td colspan="9">No course offerings yet.</td></tr>
        <?php endif; ?>
    </table></div>
        </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>
</html>