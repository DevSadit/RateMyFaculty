<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/evaluation_window.php';
require_once __DIR__ . '/../includes/eligibility.php';
require_once __DIR__ . '/../includes/csrf.php';

start_app_session();
$user = require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}
verify_csrf_token();

$stmt = $pdo->prepare('SELECT student_id FROM students WHERE user_id = :user_id');
$stmt->execute([':user_id' => $user['user_id']]);
$studentRow = $stmt->fetch();
if ($studentRow === false) {
    http_response_code(500);
    die('Student profile not found.');
}
$studentId = (int) $studentRow['student_id'];

$offeringId = validate_positive_int($_POST['offering_id'] ?? null);
if ($offeringId === null) {
    header('Location: dashboard.php');
    exit;
}

$eligibility = get_offering_eligibility($pdo, $studentId, $offeringId);
if (!$eligibility['eligible']) {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
        <title>Submission Rejected</title>
        <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/style.css">
        <script src="<?php echo e(BASE_URL); ?>/assets/js/app.js" defer></script>
    </head>

    <body class="error-page">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <aside class="sidebar" aria-label="Student workspace">
        <div class="brand"><span class="brand-mark"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg></span><span>RateMyFaculty</span></div>
        <span class="brand-subtitle">A better learning experience</span>
        <div class="nav-label">Your workspace</div>
        <nav aria-label="Main navigation"><a class="nav-link" href="dashboard.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#grid"></use></svg><span>My courses</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Student workspace<span aria-hidden="true">/</span><strong>Course evaluation</strong></div><span class="workspace-tag">Student portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
        <p class="back-link"><a href="dashboard.php">&laquo; Back to My Courses</a></p>
        <p class="eyebrow">Evaluation unavailable</p>
    <h1>Submission Rejected</h1>
        <p class="empty-state"><?php echo e($eligibility['reason']); ?></p>
            </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>

    </html>
    <?php
    exit;
}

$criteriaFields = ['teaching_quality', 'communication', 'punctuality', 'helpfulness', 'overall_rating'];
$ratings = [];
$formErrors = [];

foreach ($criteriaFields as $field) {
    $value = validate_rating($_POST[$field] ?? null);
    if ($value === null) {
        $formErrors[] = 'All five ratings must be between 1 and 5.';
        break;
    }
    $ratings[$field] = $value;
}

$comment = validate_optional_text($_POST['comment'] ?? '', 1000);
if ($comment === null) {
    $formErrors[] = 'Comment must be 1000 characters or fewer.';
}

if (!empty($formErrors)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
        <title>Submission Rejected</title>
        <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/style.css">
        <script src="<?php echo e(BASE_URL); ?>/assets/js/app.js" defer></script>
    </head>

    <body class="error-page">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <aside class="sidebar" aria-label="Student workspace">
        <div class="brand"><span class="brand-mark"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg></span><span>RateMyFaculty</span></div>
        <span class="brand-subtitle">A better learning experience</span>
        <div class="nav-label">Your workspace</div>
        <nav aria-label="Main navigation"><a class="nav-link" href="dashboard.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#grid"></use></svg><span>My courses</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Student workspace<span aria-hidden="true">/</span><strong>Course evaluation</strong></div><span class="workspace-tag">Student portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
        <p class="back-link"><a href="evaluate.php?offering_id=<?php echo (int) $offeringId; ?>">&laquo; Back to form</a></p>
        <p class="eyebrow">Evaluation unavailable</p>
    <h1>Submission Rejected</h1>
        <?php foreach ($formErrors as $err): ?>
            <p class="empty-state"><?php echo e($err); ?></p><?php endforeach; ?>
            </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>

    </html>
    <?php
    exit;
}

try {
    $pdo->beginTransaction();

    $receiptStmt = $pdo->prepare(
        'INSERT INTO submission_receipts (student_id, offering_id) VALUES (:student_id, :offering_id)'
    );
    $receiptStmt->execute([':student_id' => $studentId, ':offering_id' => $offeringId]);

    $evalStmt = $pdo->prepare(
        'INSERT INTO evaluations
            (offering_id, teaching_quality, communication, punctuality, helpfulness, overall_rating, comment)
         VALUES
            (:offering_id, :teaching_quality, :communication, :punctuality, :helpfulness, :overall_rating, :comment)'
    );
    $evalStmt->execute([
        ':offering_id' => $offeringId,
        ':teaching_quality' => $ratings['teaching_quality'],
        ':communication' => $ratings['communication'],
        ':punctuality' => $ratings['punctuality'],
        ':helpfulness' => $ratings['helpfulness'],
        ':overall_rating' => $ratings['overall_rating'],
        ':comment' => ($comment === '' ? null : $comment),
    ]);

    $pdo->commit();
} catch (PDOException $ex) {
    $pdo->rollBack();

    $sqlState = $ex->errorInfo[1] ?? 0;
    if ((int) $sqlState === 1062) {
        header('Location: dashboard.php?already=1');
        exit;
    }

    error_log('[RateMyFaculty] submit_evaluation.php: ' . $ex->getMessage());
    http_response_code(500);
    die('A server error occurred while submitting your evaluation. Please try again.');
}

header('Location: dashboard.php?submitted=1&offering=' . $offeringId);
exit;