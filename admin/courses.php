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

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $courseId = $action === 'update' ? validate_positive_int($_POST['course_id'] ?? null) : null;
        $courseCode = validate_required_string($_POST['course_code'] ?? '', 30);
        $courseTitle = validate_required_string($_POST['course_title'] ?? '', 150);

        $creditRaw = trim((string) ($_POST['credit_hours'] ?? ''));
        $creditHours = null;
        if ($creditRaw !== '') {
            if (!is_numeric($creditRaw) || (float) $creditRaw <= 0 || (float) $creditRaw > 9.9) {
                $errors[] = 'Credit hours must be a positive number up to 9.9.';
            } else {
                $creditHours = (float) $creditRaw;
            }
        }

        if ($action === 'update' && $courseId === null) {
            $errors[] = 'Invalid course selected for editing.';
        }
        if ($courseCode === null) {
            $errors[] = 'Course code is required (max 30 characters).';
        }
        if ($courseTitle === null) {
            $errors[] = 'Course title is required (max 150 characters).';
        }

        if (empty($errors)) {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO courses (course_code, course_title, credit_hours)
                         VALUES (:code, :title, :credit)'
                    );
                    $stmt->execute([':code' => $courseCode, ':title' => $courseTitle, ':credit' => $creditHours]);
                    $successMessage = 'Course created.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE courses SET course_code = :code, course_title = :title, credit_hours = :credit
                         WHERE course_id = :id'
                    );
                    $stmt->execute([':code' => $courseCode, ':title' => $courseTitle, ':credit' => $creditHours, ':id' => $courseId]);
                    $successMessage = 'Course updated.';
                }
            } catch (PDOException $ex) {
                if ((int) ($ex->errorInfo[1] ?? 0) === 1062) {
                    $errors[] = 'That course code is already used by another course.';
                } else {
                    error_log('[RateMyFaculty] admin/courses.php: ' . $ex->getMessage());
                    $errors[] = 'A server error occurred. Please try again.';
                }
            }
        }
    } elseif ($action === 'toggle_active') {
        $courseId = validate_positive_int($_POST['course_id'] ?? null);
        if ($courseId !== null) {
            $stmt = $pdo->prepare('UPDATE courses SET is_active = 1 - is_active WHERE course_id = :id');
            $stmt->execute([':id' => $courseId]);
            $successMessage = 'Course status updated.';
        }
    }
}

$editCourse = null;
$editId = validate_positive_int($_GET['edit'] ?? null);
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE course_id = :id');
    $stmt->execute([':id' => $editId]);
    $editCourse = $stmt->fetch() ?: null;
}

$courses = $pdo->query('SELECT * FROM courses ORDER BY course_code')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>Admin — Courses</title>
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
            <a class="nav-link" href="courses.php" aria-current="page"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><span>Courses</span></a>
            <a class="nav-link" href="semesters.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#calendar"></use></svg><span>Semesters</span></a>
            <a class="nav-link" href="offerings.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#layers"></use></svg><span>Course offerings</span></a>
            <a class="nav-link" href="enrollments.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#enroll"></use></svg><span>Enrollments</span></a>
            <a class="nav-link" href="progress.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#progress"></use></svg><span>Evaluation progress</span></a>
            <a class="nav-link" href="results.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#chart"></use></svg><span>Results</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Administration<span aria-hidden="true">/</span><strong>Courses</strong></div><span class="workspace-tag">Admin portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <p class="back-link"><a href="<?php echo e(BASE_URL); ?>/admin/dashboard.php">&laquo; Back to Dashboard</a></p>
    <p class="eyebrow">Courses</p>
    <h1>Manage Courses</h1>
    <p class="page-description">Organize the courses at the heart of your academic program.</p>

    <?php foreach ($errors as $err): ?>
        <p role="alert" style="color:red;"><?php echo e($err); ?></p>
    <?php endforeach; ?>
    <?php if ($successMessage !== ''): ?>
        <p role="status" style="color:green;"><?php echo e($successMessage); ?></p>
    <?php endif; ?>

    <section class="panel editor-panel"><h2><?php echo $editCourse ? 'Edit Course' : 'Add New Course'; ?></h2>

    <form class="editor-form" method="post" action="courses.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $editCourse ? 'update' : 'create'; ?>">
        <?php if ($editCourse): ?>
            <input type="hidden" name="course_id" value="<?php echo e((string) $editCourse['course_id']); ?>">
        <?php endif; ?>

        <div class="field"><label for="course_code">Course Code</label>
        <input type="text" id="course_code" name="course_code" maxlength="30" required
            value="<?php echo e($editCourse['course_code'] ?? ($_POST['course_code'] ?? '')); ?>"></div>

        <div class="field"><label for="course_title">Course Title</label>
        <input type="text" id="course_title" name="course_title" maxlength="150" required
            value="<?php echo e($editCourse['course_title'] ?? ($_POST['course_title'] ?? '')); ?>"></div>

        <div class="field"><label for="credit_hours">Credit Hours (optional)</label>
        <input type="text" id="credit_hours" name="credit_hours"
            value="<?php echo e($editCourse['credit_hours'] ?? ($_POST['credit_hours'] ?? '')); ?>"></div>

        <div class="form-actions"><button type="submit"><?php echo $editCourse ? 'Save Changes' : 'Create Course'; ?></button>
        <?php if ($editCourse): ?>
            <a href="courses.php">Cancel edit</a>
        <?php endif; ?>
    </div></form></section>

    <h2>All Courses</h2>
    <div class="table-region" role="region" aria-label="Courses table" tabindex="0"><table class="data-table">
        <tr>
            <th scope="col">Code</th>
            <th scope="col">Title</th>
            <th scope="col">Credits</th>
            <th scope="col">Status</th>
            <th scope="col">Actions</th>
        </tr>
        <?php foreach ($courses as $c): ?>
            <tr>
                <td><?php echo e($c['course_code']); ?></td>
                <td><?php echo e($c['course_title']); ?></td>
                <td><?php echo e($c['credit_hours'] !== null ? (string) $c['credit_hours'] : '—'); ?></td>
                <td><span class="status-pill"><?php echo (int) $c['is_active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
                <td class="actions-cell">
                    <a href="courses.php?edit=<?php echo (int) $c['course_id']; ?>">Edit</a>
                    

                    <form method="post" action="courses.php" style="display:inline;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="course_id" value="<?php echo (int) $c['course_id']; ?>">
                        <button
                            type="submit"><?php echo (int) $c['is_active'] === 1 ? 'Deactivate' : 'Reactivate'; ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table></div>
        </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>

</html>