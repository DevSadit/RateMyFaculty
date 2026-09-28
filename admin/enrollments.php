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

    if ($action === 'create') {
        $studentId = validate_positive_int($_POST['student_id'] ?? null);
        $offeringId = validate_positive_int($_POST['offering_id'] ?? null);

        if ($studentId === null) {
            $errors[] = 'Please select a student.';
        }
        if ($offeringId === null) {
            $errors[] = 'Please select an offering.';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO enrollments (student_id, offering_id, status) VALUES (:sid, :oid, 'enrolled')"
                );
                $stmt->execute([':sid' => $studentId, ':oid' => $offeringId]);
                $successMessage = 'Student enrolled.';
            } catch (PDOException $ex) {
                $sqlState = $ex->errorInfo[1] ?? 0;
                if ((int) $sqlState === 1062) {
                    $errors[] = 'This student is already enrolled in that offering.';
                } elseif ((int) $sqlState === 1452) {
                    $errors[] = 'Selected student or offering no longer exists.';
                } else {
                    error_log('[RateMyFaculty] admin/enrollments.php: ' . $ex->getMessage());
                    $errors[] = 'A server error occurred. Please try again.';
                }
            }
        }
    } elseif ($action === 'mark_completed') {
        $enrollmentId = validate_positive_int($_POST['enrollment_id'] ?? null);
        if ($enrollmentId !== null) {
            $stmt = $pdo->prepare(
                "UPDATE enrollments SET status = 'completed' WHERE enrollment_id = :id AND status = 'enrolled'"
            );
            $stmt->execute([':id' => $enrollmentId]);
            $successMessage = 'Enrollment marked as completed.';
        }
    } elseif ($action === 'delete') {
        $enrollmentId = validate_positive_int($_POST['enrollment_id'] ?? null);
        if ($enrollmentId === null) {
            $errors[] = 'Invalid enrollment.';
        } else {
            $check = $pdo->prepare(
                'SELECT en.student_id, en.offering_id,
                        (SELECT COUNT(*) FROM submission_receipts sr
                         WHERE sr.student_id = en.student_id AND sr.offering_id = en.offering_id) AS receipt_count
                 FROM enrollments en WHERE en.enrollment_id = :id'
            );
            $check->execute([':id' => $enrollmentId]);
            $row = $check->fetch();

            if ($row === false) {
                $errors[] = 'Enrollment not found.';
            } elseif ((int) $row['receipt_count'] > 0) {
                $errors[] = 'This enrollment cannot be deleted — the student has already submitted an evaluation for it.';
            } else {
                $del = $pdo->prepare('DELETE FROM enrollments WHERE enrollment_id = :id');
                $del->execute([':id' => $enrollmentId]);
                $successMessage = 'Enrollment removed.';
            }
        }
    }
}

$studentOptions = $pdo->query(
    "SELECT s.student_id, s.full_name, s.student_number
     FROM students s JOIN users u ON u.user_id = s.user_id
     WHERE u.is_active = 1 ORDER BY s.full_name"
)->fetchAll();

$offeringOptions = $pdo->query(
    "SELECT co.offering_id, c.course_code, co.section, sem.semester_name, sem.year
     FROM course_offerings co
     JOIN courses c ON c.course_id = co.course_id
     JOIN semesters sem ON sem.semester_id = co.semester_id
     WHERE co.is_active = 1
     ORDER BY sem.year DESC, sem.semester_name, c.course_code, co.section"
)->fetchAll();

$enrollments = $pdo->query(
    "SELECT en.enrollment_id, en.status, en.student_id, en.offering_id,
            s.full_name AS student_name, s.student_number,
            c.course_code, co.section, sem.semester_name, sem.year,
            (SELECT COUNT(*) FROM submission_receipts sr
             WHERE sr.student_id = en.student_id AND sr.offering_id = en.offering_id) AS has_receipt
     FROM enrollments en
     JOIN students s ON s.student_id = en.student_id
     JOIN course_offerings co ON co.offering_id = en.offering_id
     JOIN courses c ON c.course_id = co.course_id
     JOIN semesters sem ON sem.semester_id = co.semester_id
     ORDER BY sem.year DESC, sem.semester_name, c.course_code, co.section, s.full_name"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>Admin — Enrollments</title>
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
            <a class="nav-link" href="offerings.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#layers"></use></svg><span>Course offerings</span></a>
            <a class="nav-link" href="enrollments.php" aria-current="page"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#enroll"></use></svg><span>Enrollments</span></a>
            <a class="nav-link" href="progress.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#progress"></use></svg><span>Evaluation progress</span></a>
            <a class="nav-link" href="results.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#chart"></use></svg><span>Results</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Administration<span aria-hidden="true">/</span><strong>Enrollments</strong></div><span class="workspace-tag">Admin portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <p class="back-link"><a href="<?php echo e(BASE_URL); ?>/admin/dashboard.php">&laquo; Back to Dashboard</a></p>
    <p class="eyebrow">Enrollments</p>
    <h1>Manage Enrollments</h1>
    <p class="page-description">Connect students to course offerings and manage completion status.</p>

    <?php foreach ($errors as $err): ?>
        <p role="alert" style="color:red;"><?php echo e($err); ?></p><?php endforeach; ?>
    <?php if ($successMessage !== ''): ?>
        <p role="status" style="color:green;"><?php echo e($successMessage); ?></p><?php endif; ?>

    <section class="panel editor-panel"><h2>Add Enrollment</h2>
    <form class="editor-form" method="post" action="enrollments.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="create">

        <div class="field"><label for="student_id">Student</label>
        <select id="student_id" name="student_id" required>
            <option value="">-- select student --</option>
            <?php foreach ($studentOptions as $s): ?>
                <option value="<?php echo (int) $s['student_id']; ?>">
                    <?php echo e($s['student_number'] . ' — ' . $s['full_name']); ?>
                </option>
            <?php endforeach; ?>
        </select></div>

        <div class="field"><label for="offering_id">Offering</label>
        <select id="offering_id" name="offering_id" required>
            <option value="">-- select offering --</option>
            <?php foreach ($offeringOptions as $o): ?>
                <option value="<?php echo (int) $o['offering_id']; ?>">
                    <?php echo e($o['course_code'] . ' Sec ' . $o['section'] . ' — ' . $o['semester_name'] . ' ' . $o['year']); ?>
                </option>
            <?php endforeach; ?>
        </select></div>

        <div class="form-actions"><button type="submit">Enroll Student</button>
    </div></form></section>

    <h2>All Enrollments</h2>
    <div class="table-region" role="region" aria-label="Enrollments table" tabindex="0"><table class="data-table">
        <tr>
            <th scope="col">Student</th>
            <th scope="col">Course</th>
            <th scope="col">Semester</th>
            <th scope="col">Status</th>
            <th scope="col">Submitted?</th>
            <th scope="col">Actions</th>
        </tr>
        <?php foreach ($enrollments as $en): ?>
            <tr>
                <td><?php echo e($en['student_number'] . ' — ' . $en['student_name']); ?></td>
                <td><?php echo e($en['course_code'] . ' Sec ' . $en['section']); ?></td>
                <td><?php echo e($en['semester_name'] . ' ' . $en['year']); ?></td>
                <td><?php echo e(ucfirst($en['status'])); ?></td>
                <td><?php echo (int) $en['has_receipt'] > 0 ? 'Yes' : 'No'; ?></td>
                <td>
                    <?php if ($en['status'] === 'enrolled'): ?>

                        <form method="post" action="enrollments.php" style="display:inline;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="mark_completed">

                            <input type="hidden" name="enrollment_id" value="<?php echo (int) $en['enrollment_id']; ?>">
                            <button type="submit">Mark Completed</button>
                        </form>
                    <?php endif; ?>
                    <?php if ((int) $en['has_receipt'] === 0): ?>

                        <form method="post" action="enrollments.php" style="display:inline;"
                            onsubmit="return confirm('Remove this enrollment?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">

                            <input type="hidden" name="enrollment_id" value="<?php echo (int) $en['enrollment_id']; ?>">
                            <button type="submit">Delete</button>
                        </form>
                    <?php else: ?>
                        <em>Locked (submitted)</em>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table></div>
        </main>
        <footer class="page-footer"><span>RateMyFaculty</span><span>Better learning starts with listening.</span></footer>
    </div>
</body>

</html>