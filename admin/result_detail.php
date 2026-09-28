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

$offeringId = validate_positive_int($_GET['offering_id'] ?? null);
if ($offeringId === null) {
    header('Location: results.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT co.offering_id, co.section, c.course_code, c.course_title,
            sem.semester_name, sem.year, sem.evaluation_open_at, sem.evaluation_close_at,
            f.full_name AS faculty_name,
            (SELECT COUNT(*) FROM evaluations e WHERE e.offering_id = co.offering_id) AS response_count
     FROM course_offerings co
     JOIN courses c ON c.course_id = co.course_id
     JOIN semesters sem ON sem.semester_id = co.semester_id
     JOIN faculty f ON f.faculty_id = co.faculty_id
     WHERE co.offering_id = :offering_id'
);
$stmt->execute([':offering_id' => $offeringId]);
$offering = $stmt->fetch();
if ($offering === false) {
    http_response_code(404);
    die('Offering not found.');
}

$windowStatus = get_window_status($offering['evaluation_open_at'], $offering['evaluation_close_at']);
$responseCount = (int) $offering['response_count'];
$released = is_result_released($windowStatus, $responseCount);

$averages = null;
$comments = [];

// Content is only ever queried once BOTH the window-closed AND
// threshold checks pass — same rule, same function, as Faculty's page.
if ($released) {
    $avgStmt = $pdo->prepare(
        'SELECT AVG(teaching_quality) AS avg_teaching, AVG(communication) AS avg_communication,
                AVG(punctuality) AS avg_punctuality, AVG(helpfulness) AS avg_helpfulness,
                AVG(overall_rating) AS avg_overall
         FROM evaluations WHERE offering_id = :offering_id'
    );
    $avgStmt->execute([':offering_id' => $offeringId]);
    $averages = $avgStmt->fetch();

    // Deliberately selects ONLY the comment text — no student_id, no
    // enrollment_id, nothing that could be paired with an identity,
    // because the evaluations table has no such column to select.
    $commentStmt = $pdo->prepare(
        "SELECT comment FROM evaluations
         WHERE offering_id = :offering_id AND comment IS NOT NULL AND comment <> ''
         ORDER BY evaluation_id"
    );
    $commentStmt->execute([':offering_id' => $offeringId]);
    $comments = $commentStmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>Offering Results</title>
    <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/style.css">
    <script src="<?php echo e(BASE_URL); ?>/assets/js/app.js" defer></script>
</head>

<body class="result-page">
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
            <a class="nav-link" href="progress.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#progress"></use></svg><span>Evaluation progress</span></a>
            <a class="nav-link" href="results.php" aria-current="page"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#chart"></use></svg><span>Results</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Administration<span aria-hidden="true">/</span><strong>Offering results</strong></div><span class="workspace-tag">Admin portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <p class="back-link"><a href="results.php">&laquo; Back to Results</a></p>
    <p class="eyebrow">Offering results</p>
    <h1><?php echo e($offering['course_code'] . ' — ' . $offering['course_title']); ?></h1>
    <p class="page-description">Student feedback, brought together in one clear view.</p>
    <p class="context-line"><?php echo e($offering['semester_name'] . ' ' . $offering['year'] . ', Section ' . $offering['section'] . ' — ' . $offering['faculty_name']); ?></p>

    <?php if (!$released): ?>
        <p>
            <?php if ($windowStatus !== WINDOW_CLOSED): ?>
                Results are not available while the evaluation window is still open.
            <?php else: ?>
                Results are not available — fewer than <?php echo RELEASE_THRESHOLD; ?> responses were submitted.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <h2>Summary</h2>
        <p class="metric-line">Total responses: <strong><?php echo $responseCount; ?></strong></p>
        <!-- UI REDESIGN: Focusable table region supports keyboard scrolling on smaller screens. -->
    <div class="table-region" role="region" aria-label="Offering results table" tabindex="0"><table class="summary-table">
            <tr>
                <th scope="col">Criterion</th>
                <th scope="col">Average</th>
            </tr>
            <tr>
                <td>Teaching Quality</td>
                <td><?php echo e(number_format((float) $averages['avg_teaching'], 2)); ?></td>
            </tr>
            <tr>
                <td>Communication</td>
                <td><?php echo e(number_format((float) $averages['avg_communication'], 2)); ?></td>
            </tr>
            <tr>
                <td>Punctuality</td>
                <td><?php echo e(number_format((float) $averages['avg_punctuality'], 2)); ?></td>
            </tr>
            <tr>
                <td>Helpfulness</td>
                <td><?php echo e(number_format((float) $averages['avg_helpfulness'], 2)); ?></td>
            </tr>
            <tr>
                <td>Overall Rating</td>
                <td><?php echo e(number_format((float) $averages['avg_overall'], 2)); ?></td>
            </tr>
        </table></div>

        <h2>Anonymous Comments</h2>
        <p class="context-line"><em>No student identity is shown alongside these — by design, none is stored.</em></p>
        <?php if (empty($comments)): ?>
            <p class="empty-state">No written comments were submitted.</p>
        <?php else: ?>
            <ul class="comment-list">
                <?php foreach ($comments as $c): ?>
                    <li><?php echo e($c); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
        </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>

</html>