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
$facultyId = (int) $facultyRow['faculty_id'];

$stmt = $pdo->prepare(
    'SELECT co.offering_id, sem.evaluation_open_at, sem.evaluation_close_at,
            (SELECT COUNT(*) FROM evaluations e WHERE e.offering_id = co.offering_id) AS response_count
     FROM course_offerings co
     JOIN semesters sem ON sem.semester_id = co.semester_id
     WHERE co.faculty_id = :faculty_id'
);
$stmt->execute([':faculty_id' => $facultyId]);
$allOfferings = $stmt->fetchAll();

$releasedOfferingIds = [];
foreach ($allOfferings as $o) {
    $windowStatus = get_window_status($o['evaluation_open_at'], $o['evaluation_close_at']);
    if (is_result_released($windowStatus, (int) $o['response_count'])) {
        $releasedOfferingIds[] = (int) $o['offering_id'];
    }
}

$summary = null;
if (!empty($releasedOfferingIds)) {
    $placeholders = implode(',', array_fill(0, count($releasedOfferingIds), '?'));
    $sql = "SELECT COUNT(*) AS total_evaluations,
                   AVG(teaching_quality) AS avg_teaching, AVG(communication) AS avg_communication,
                   AVG(punctuality) AS avg_punctuality, AVG(helpfulness) AS avg_helpfulness,
                   AVG(overall_rating) AS avg_overall
            FROM evaluations WHERE offering_id IN ($placeholders)";
    $sumStmt = $pdo->prepare($sql);
    $sumStmt->execute($releasedOfferingIds);
    $summary = $sumStmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>My Overall Summary</title>
    <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/style.css">
    <script src="<?php echo e(BASE_URL); ?>/assets/js/app.js" defer></script>
</head>

<body class="result-page">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <aside class="sidebar" aria-label="Faculty workspace">
        <div class="brand"><span class="brand-mark"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg></span><span>RateMyFaculty</span></div>
        <span class="brand-subtitle">A better learning experience</span>
        <div class="nav-label">Your workspace</div>
        <nav aria-label="Main navigation"><a class="nav-link" href="dashboard.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#grid"></use></svg><span>My offerings</span></a>
            <a class="nav-link" href="summary.php" aria-current="page"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#chart"></use></svg><span>Overall summary</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Faculty workspace<span aria-hidden="true">/</span><strong>Overall summary</strong></div><span class="workspace-tag">Faculty portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <p class="back-link"><a href="dashboard.php">&laquo; Back to My Offerings</a></p>
    <p class="eyebrow">Overall summary</p>
    <h1>Overall Summary — <?php echo e($facultyRow['full_name']); ?></h1>
    <p class="page-description">A broader view of student feedback across your released course offerings.</p>
    <p>Includes only evaluations from released offerings (semester closed and <?php echo RELEASE_THRESHOLD; ?>+
        responses).</p>

    <?php if ($summary === null || (int) $summary['total_evaluations'] === 0): ?>
        <p class="empty-state">No released results are available yet.</p>
    <?php else: ?>
        <p class="metric-line">Released offerings included: <strong><?php echo count($releasedOfferingIds); ?></strong></p>
        <p class="metric-line">Total evaluations included: <strong><?php echo (int) $summary['total_evaluations']; ?></strong></p>
    <div class="table-region" role="region" aria-label="Overall summary table" tabindex="0"><table class="summary-table">
            <tr>
                <th scope="col">Criterion</th>
                <th scope="col">Average</th>
            </tr>
            <tr>
                <td>Teaching Quality</td>
                <td><?php echo e(number_format((float) $summary['avg_teaching'], 2)); ?></td>
            </tr>
            <tr>
                <td>Communication</td>
                <td><?php echo e(number_format((float) $summary['avg_communication'], 2)); ?></td>
            </tr>
            <tr>
                <td>Punctuality</td>
                <td><?php echo e(number_format((float) $summary['avg_punctuality'], 2)); ?></td>
            </tr>
            <tr>
                <td>Helpfulness</td>
                <td><?php echo e(number_format((float) $summary['avg_helpfulness'], 2)); ?></td>
            </tr>
            <tr>
                <td>Overall Rating</td>
                <td><?php echo e(number_format((float) $summary['avg_overall'], 2)); ?></td>
            </tr>
        </table></div>
    <?php endif; ?>
        </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>

</html>