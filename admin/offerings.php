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
        $offeringId = $action === 'update' ? validate_positive_int($_POST['offering_id'] ?? null) : null;
        $courseId = validate_positive_int($_POST['course_id'] ?? null);
        $semesterId = validate_positive_int($_POST['semester_id'] ?? null);
        $facultyId = validate_positive_int($_POST['faculty_id'] ?? null);
        $section = validate_required_string($_POST['section'] ?? '', 20);

        if ($action === 'update' && $offeringId === null) {
            $errors[] = 'Invalid offering selected for editing.';
        }
        if ($courseId === null) {
            $errors[] = 'Please select a course.';
        }
        if ($semesterId === null) {
            $errors[] = 'Please select a semester.';
        }
        if ($facultyId === null) {
            $errors[] = 'Please select a faculty member.';
        }
        if ($section === null) {
            $errors[] = 'Section is required (max 20 characters).';
        }

        if (empty($errors)) {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO course_offerings (course_id, semester_id, faculty_id, section)
                         VALUES (:course_id, :semester_id, :faculty_id, :section)'
                    );
                    $stmt->execute([
                        ':course_id' => $courseId,
                        ':semester_id' => $semesterId,
                        ':faculty_id' => $facultyId,
                        ':section' => $section,
                    ]);
                    $successMessage = 'Course offering created.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE course_offerings
                         SET course_id = :course_id, semester_id = :semester_id,
                             faculty_id = :faculty_id, section = :section
                         WHERE offering_id = :id'
                    );
                    $stmt->execute([
                        ':course_id' => $courseId,
                        ':semester_id' => $semesterId,
                        ':faculty_id' => $facultyId,
                        ':section' => $section,
                        ':id' => $offeringId,
                    ]);
                    $successMessage = 'Course offering updated.';
                }
            } catch (PDOException $ex) {
                $sqlState = $ex->errorInfo[1] ?? 0;
                if ((int) $sqlState === 1062) {
                    $errors[] = 'An offering for that course, semester, and section already exists.';
                } elseif ((int) $sqlState === 1452) {
                    $errors[] = 'Selected course, semester, or faculty no longer exists.';
                } else {
                    error_log('[RateMyFaculty] admin/offerings.php: ' . $ex->getMessage());
                    $errors[] = 'A server error occurred. Please try again.';
                }
            }
        }
    } elseif ($action === 'toggle_active') {
        $offeringId = validate_positive_int($_POST['offering_id'] ?? null);
        if ($offeringId !== null) {
            $stmt = $pdo->prepare('UPDATE course_offerings SET is_active = 1 - is_active WHERE offering_id = :id');
            $stmt->execute([':id' => $offeringId]);
            $successMessage = 'Offering status updated.';
        }
    }
}

$editOffering = null;
$editId = validate_positive_int($_GET['edit'] ?? null);
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM course_offerings WHERE offering_id = :id');
    $stmt->execute([':id' => $editId]);
    $editOffering = $stmt->fetch() ?: null;
}

$currentCourseId = $editOffering['course_id'] ?? 0;
$currentFacultyId = $editOffering['faculty_id'] ?? 0;

$courseOptions = $pdo->prepare(
    'SELECT course_id, course_code, course_title, is_active FROM courses
     WHERE is_active = 1 OR course_id = :current ORDER BY course_code'
);
$courseOptions->execute([':current' => $currentCourseId]);
$courseOptions = $courseOptions->fetchAll();

$semesterOptions = $pdo->query('SELECT semester_id, semester_name, year, is_locked FROM semesters ORDER BY year DESC, semester_name')->fetchAll();

$facultyOptions = $pdo->prepare(
    'SELECT f.faculty_id, f.full_name, f.faculty_code, u.is_active
     FROM faculty f JOIN users u ON u.user_id = f.user_id
     WHERE u.is_active = 1 OR f.faculty_id = :current
     ORDER BY f.full_name'
);
$facultyOptions->execute([':current' => $currentFacultyId]);
$facultyOptions = $facultyOptions->fetchAll();

$offerings = $pdo->query(
    'SELECT co.offering_id, co.section, co.is_active, c.course_code, c.course_title,
            s.semester_name, s.year, f.full_name AS faculty_name
     FROM course_offerings co
     JOIN courses c ON c.course_id = co.course_id
     JOIN semesters s ON s.semester_id = co.semester_id
     JOIN faculty f ON f.faculty_id = co.faculty_id
     ORDER BY s.year DESC, s.semester_name, c.course_code, co.section'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>Admin — Course Offerings</title>
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
            <a class="nav-link" href="offerings.php" aria-current="page"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#layers"></use></svg><span>Course offerings</span></a>
            <a class="nav-link" href="enrollments.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#enroll"></use></svg><span>Enrollments</span></a>
            <a class="nav-link" href="progress.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#progress"></use></svg><span>Evaluation progress</span></a>
            <a class="nav-link" href="results.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#chart"></use></svg><span>Results</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Administration<span aria-hidden="true">/</span><strong>Course offerings</strong></div><span class="workspace-tag">Admin portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <p class="back-link"><a href="<?php echo e(BASE_URL); ?>/admin/dashboard.php">&laquo; Back to Dashboard</a></p>
    <p class="eyebrow">Course offerings</p>
    <h1>Manage Course Offerings</h1>
    <p class="page-description">Bring courses, faculty, and semesters together in one place.</p>

    <?php foreach ($errors as $err): ?>
        <p role="alert" style="color:red;"><?php echo e($err); ?></p><?php endforeach; ?>
    <?php if ($successMessage !== ''): ?>
        <p role="status" style="color:green;"><?php echo e($successMessage); ?></p><?php endif; ?>

    <section class="panel editor-panel"><h2><?php echo $editOffering ? 'Edit Offering' : 'Add New Offering'; ?></h2>

    <form class="editor-form" method="post" action="offerings.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $editOffering ? 'update' : 'create'; ?>">


        <?php if ($editOffering): ?>
            <input type="hidden" name="offering_id" value="<?php echo e((string) $editOffering['offering_id']); ?>">
        <?php endif; ?>

        <div class="field"><label for="course_id">Course</label>
        <select id="course_id" name="course_id" required>
            <option value="">-- select course --</option>
            <?php foreach ($courseOptions as $c): ?>
                <option value="<?php echo (int) $c['course_id']; ?>" <?php echo $currentCourseId == $c['course_id'] ? 'selected' : ''; ?>>
                    <?php echo e($c['course_code'] . ' — ' . $c['course_title']); ?>
                    <?php echo (int) $c['is_active'] === 0 ? ' (inactive)' : ''; ?>
                </option>
            <?php endforeach; ?>
        </select></div>

        <div class="field"><label for="semester_id">Semester</label>
        <select id="semester_id" name="semester_id" required>
            <option value="">-- select semester --</option>
            <?php foreach ($semesterOptions as $s): ?>
                <option value="<?php echo (int) $s['semester_id']; ?>" <?php echo ($editOffering['semester_id'] ?? 0) == $s['semester_id'] ? 'selected' : ''; ?>>
                    <?php echo e($s['semester_name'] . ' ' . $s['year']); ?>
                    <?php echo (int) $s['is_locked'] === 1 ? ' (locked)' : ''; ?>
                </option>
            <?php endforeach; ?>
        </select></div>

        <div class="field"><label for="faculty_id">Faculty</label>
        <select id="faculty_id" name="faculty_id" required>
            <option value="">-- select faculty --</option>
            <?php foreach ($facultyOptions as $f): ?>
                <option value="<?php echo (int) $f['faculty_id']; ?>" <?php echo $currentFacultyId == $f['faculty_id'] ? 'selected' : ''; ?>>
                    <?php echo e($f['full_name']); ?>     <?php echo (int) $f['is_active'] === 0 ? ' (inactive)' : ''; ?>
                </option>
            <?php endforeach; ?>
        </select></div>

        <div class="field"><label for="section">Section</label>
        <input type="text" id="section" name="section" maxlength="20" required
            value="<?php echo e($editOffering['section'] ?? ($_POST['section'] ?? '')); ?>"></div>

        <div class="form-actions"><button type="submit"><?php echo $editOffering ? 'Save Changes' : 'Create Offering'; ?></button>
        <?php if ($editOffering): ?><a href="offerings.php">Cancel edit</a><?php endif; ?>
    </div></form></section>

    <h2>All Offerings</h2>
    <div class="table-region" role="region" aria-label="Course offerings table" tabindex="0"><table class="data-table">
        <tr>
            <th scope="col">Course</th>
            <th scope="col">Section</th>
            <th scope="col">Semester</th>
            <th scope="col">Faculty</th>
            <th scope="col">Status</th>
            <th scope="col">Actions</th>
        </tr>
        <?php foreach ($offerings as $o): ?>
            <tr>
                <td><?php echo e($o['course_code']); ?></td>
                <td><?php echo e($o['section']); ?></td>
                <td><?php echo e($o['semester_name'] . ' ' . $o['year']); ?></td>
                <td><?php echo e($o['faculty_name']); ?></td>
                <td><span class="status-pill"><?php echo (int) $o['is_active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
                <td class="actions-cell">
                    <a href="offerings.php?edit=<?php echo (int) $o['offering_id']; ?>">Edit</a>
                    

                    <form method="post" action="offerings.php" style="display:inline;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_active">

                        <input type="hidden" name="offering_id" value="<?php echo (int) $o['offering_id']; ?>">
                        <button
                            type="submit"><?php echo (int) $o['is_active'] === 1 ? 'Deactivate' : 'Reactivate'; ?></button>
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