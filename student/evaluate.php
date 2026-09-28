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

$stmt = $pdo->prepare('SELECT student_id FROM students WHERE user_id = :user_id');
$stmt->execute([':user_id' => $user['user_id']]);
$studentRow = $stmt->fetch();
if ($studentRow === false) {
    http_response_code(500);
    die('Student profile not found.');
}
$studentId = (int) $studentRow['student_id'];

$offeringId = validate_positive_int($_GET['offering_id'] ?? null);
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
        <title>Cannot Evaluate</title>
        <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/style.css">
        <script src="<?php echo e(BASE_URL); ?>/assets/js/app.js" defer></script>
    </head>

    <body class="error-page">
    <!-- UI REDESIGN: Shared light workspace, static role navigation and accessible content landmark. -->
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
    <h1>Cannot Evaluate</h1>
        <p class="empty-state"><?php echo e($eligibility['reason']); ?></p>
            </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>

    </html>
    <?php
    exit;
}

$offering = $eligibility['offering'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>Evaluate Faculty</title>
    <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/style.css">
    <script src="<?php echo e(BASE_URL); ?>/assets/js/app.js" defer></script>
</head>

<body class="evaluation-page">
    <!-- UI REDESIGN: Shared light workspace, static role navigation and accessible content landmark. -->
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
    <p class="eyebrow">Course evaluation</p>
    <h1>Evaluate: <?php echo e($offering['course_code'] . ' — ' . $offering['course_title']); ?></h1>
    <p class="page-description">Reflect on your experience and share thoughtful, constructive feedback.</p>
    <p>Faculty: <strong><?php echo e($offering['faculty_name']); ?></strong></p>
    <p>Semester: <?php echo e($offering['semester_name'] . ' ' . $offering['year']); ?>, Section
        <?php echo e($offering['section']); ?>
    </p>

    <!-- UI REDESIGN: Existing submission guidance and native rating inputs. -->
    <div class="info-panel">
        <strong>Before you submit:</strong>
        <ul>
            <li>This evaluation is <strong>completely anonymous</strong> — your identity is never stored with your
                ratings or comment.</li>
            <li>You can only submit <strong>once</strong> per course offering.</li>
            <li>Once submitted, your response <strong>cannot be edited, viewed again, or deleted</strong> — by you or by
                Admin.</li>
        </ul>
    </div>

    <form class="panel evaluation-form" method="post" action="submit_evaluation.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="offering_id" value="<?php echo (int) $offeringId; ?>">

        <?php
        $criteria = [
            'teaching_quality' => 'Teaching Quality',
            'communication' => 'Communication',
            'punctuality' => 'Punctuality',
            'helpfulness' => 'Helpfulness',
            'overall_rating' => 'Overall Rating',
        ];
        foreach ($criteria as $field => $label):
            ?>
            <fieldset class="rating-field"><legend><?php echo e($label); ?><span class="rating-hint">1 = Poor &nbsp;·&nbsp; 5 = Excellent</span></legend><div class="rating-options">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label>
                        <input type="radio" name="<?php echo e($field); ?>" value="<?php echo $i; ?>" required>
                        <?php echo $i; ?>
                    </label>
                <?php endfor; ?>
            </div></fieldset>
        <?php endforeach; ?>

        <div class="comment-field"><label for="comment">Optional Comment (max 1000 characters)</label>
        <textarea id="comment" name="comment" rows="5" cols="60" maxlength="1000"></textarea></div>

        <div class="form-actions"><button type="submit">Submit Anonymous Evaluation</button></div>
    </form>
        </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>

</html>