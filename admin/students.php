<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/csrf.php';

start_app_session();
$user = require_role('admin');

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = validate_required_string($_POST['username'] ?? '', 80);
        $studentNumber = validate_required_string($_POST['student_number'] ?? '', 40);
        $fullName = validate_required_string($_POST['full_name'] ?? '', 120);
        $batch = validate_optional_text($_POST['batch'] ?? '', 30);
        $password = (string) ($_POST['initial_password'] ?? '');

        if ($username === null) {
            $errors[] = 'Username is required (max 80 characters).';
        }
        if ($studentNumber === null) {
            $errors[] = 'Student number is required (max 40 characters).';
        }
        if ($fullName === null) {
            $errors[] = 'Full name is required (max 120 characters).';
        }
        if ($batch === null) {
            $errors[] = 'Batch must be under 30 characters.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Initial password must be at least 8 characters.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $hash = password_hash($password, PASSWORD_BCRYPT);
                $userStmt = $pdo->prepare(
                    "INSERT INTO users (username, password_hash, role) VALUES (:username, :hash, 'student')"
                );
                $userStmt->execute([':username' => $username, ':hash' => $hash]);
                $newUserId = (int) $pdo->lastInsertId();

                $studentStmt = $pdo->prepare(
                    'INSERT INTO students (user_id, student_number, full_name, batch)
                     VALUES (:user_id, :student_number, :full_name, :batch)'
                );
                $studentStmt->execute([
                    ':user_id' => $newUserId,
                    ':student_number' => $studentNumber,
                    ':full_name' => $fullName,
                    ':batch' => ($batch === '' ? null : $batch),
                ]);
                $newStudentId = (int) $pdo->lastInsertId();

                log_admin_action(
                    $pdo,
                    (int) $user['user_id'],
                    'CREATE',
                    'student',
                    (string) $newStudentId,
                    'Created student ' . $studentNumber . ' (' . $fullName . ')'
                );

                $pdo->commit();
                $successMessage = 'Student account created.';
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $msg = $ex->getMessage();
                if (str_contains($msg, 'uq_users_username')) {
                    $errors[] = 'That username is already taken.';
                } elseif (str_contains($msg, 'uq_students_number')) {
                    $errors[] = 'That student number is already in use.';
                } else {
                    error_log('[RateMyFaculty] admin/students.php create: ' . $msg);
                    $errors[] = 'A server error occurred. Please try again.';
                }
            }
        }
    } elseif ($action === 'update') {
        $studentId = validate_positive_int($_POST['student_id'] ?? null);
        $username = validate_required_string($_POST['username'] ?? '', 80);
        $studentNumber = validate_required_string($_POST['student_number'] ?? '', 40);
        $fullName = validate_required_string($_POST['full_name'] ?? '', 120);
        $batch = validate_optional_text($_POST['batch'] ?? '', 30);

        if ($studentId === null) {
            $errors[] = 'Invalid student selected.';
        }
        if ($username === null) {
            $errors[] = 'Username is required (max 80 characters).';
        }
        if ($studentNumber === null) {
            $errors[] = 'Student number is required (max 40 characters).';
        }
        if ($fullName === null) {
            $errors[] = 'Full name is required (max 120 characters).';
        }
        if ($batch === null) {
            $errors[] = 'Batch must be under 30 characters.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $lookup = $pdo->prepare('SELECT user_id FROM students WHERE student_id = :id');
                $lookup->execute([':id' => $studentId]);
                $row = $lookup->fetch();
                if ($row === false) {
                    throw new RuntimeException('Student not found.');
                }
                $userId = (int) $row['user_id'];

                $pdo->prepare('UPDATE users SET username = :username WHERE user_id = :user_id')
                    ->execute([':username' => $username, ':user_id' => $userId]);

                $pdo->prepare(
                    'UPDATE students SET student_number = :number, full_name = :name, batch = :batch
                     WHERE student_id = :id'
                )->execute([
                            ':number' => $studentNumber,
                            ':name' => $fullName,
                            ':batch' => ($batch === '' ? null : $batch),
                            ':id' => $studentId,
                        ]);

                log_admin_action(
                    $pdo,
                    (int) $user['user_id'],
                    'UPDATE',
                    'student',
                    (string) $studentId,
                    'Updated student ' . $studentNumber . ' (' . $fullName . ')'
                );

                $pdo->commit();
                $successMessage = 'Student updated.';
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $msg = $ex->getMessage();
                if (str_contains($msg, 'uq_users_username')) {
                    $errors[] = 'That username is already taken.';
                } elseif (str_contains($msg, 'uq_students_number')) {
                    $errors[] = 'That student number is already in use.';
                } else {
                    error_log('[RateMyFaculty] admin/students.php update: ' . $msg);
                    $errors[] = 'A server error occurred. Please try again.';
                }
            } catch (RuntimeException $ex) {
                $pdo->rollBack();
                $errors[] = $ex->getMessage();
            }
        }
    } elseif ($action === 'toggle_active') {
        $studentId = validate_positive_int($_POST['student_id'] ?? null);
        if ($studentId !== null) {
            $stmt = $pdo->prepare(
                'UPDATE users u JOIN students s ON s.user_id = u.user_id
                 SET u.is_active = 1 - u.is_active WHERE s.student_id = :id'
            );
            $stmt->execute([':id' => $studentId]);

            $statusStmt = $pdo->prepare(
                'SELECT u.is_active, s.student_number FROM users u
                 JOIN students s ON s.user_id = u.user_id WHERE s.student_id = :id'
            );
            $statusStmt->execute([':id' => $studentId]);
            $row = $statusStmt->fetch();

            $newState = $row !== false && (int) $row['is_active'] === 1 ? 'REACTIVATE' : 'DEACTIVATE';
            log_admin_action(
                $pdo,
                (int) $user['user_id'],
                $newState,
                'student',
                (string) $studentId,
                ($row['student_number'] ?? '') . ' ' . strtolower($newState) . 'd'
            );

            $successMessage = 'Student status updated.';
        }
    }
}

$editStudent = null;
$editId = validate_positive_int($_GET['edit'] ?? null);
if ($editId !== null) {
    $stmt = $pdo->prepare(
        'SELECT s.*, u.username, u.is_active FROM students s
         JOIN users u ON u.user_id = s.user_id WHERE s.student_id = :id'
    );
    $stmt->execute([':id' => $editId]);
    $editStudent = $stmt->fetch() ?: null;
}

$students = $pdo->query(
    'SELECT s.student_id, s.student_number, s.full_name, s.batch, u.username, u.is_active
     FROM students s JOIN users u ON u.user_id = s.user_id
     ORDER BY s.full_name'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>Admin — Students</title>
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
            <a class="nav-link" href="students.php" aria-current="page"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#students"></use></svg><span>Students</span></a>
            <a class="nav-link" href="faculty.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#faculty"></use></svg><span>Faculty</span></a>
            <a class="nav-link" href="courses.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><span>Courses</span></a>
            <a class="nav-link" href="semesters.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#calendar"></use></svg><span>Semesters</span></a>
            <a class="nav-link" href="offerings.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#layers"></use></svg><span>Course offerings</span></a>
            <a class="nav-link" href="enrollments.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#enroll"></use></svg><span>Enrollments</span></a>
            <a class="nav-link" href="progress.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#progress"></use></svg><span>Evaluation progress</span></a>
            <a class="nav-link" href="results.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#chart"></use></svg><span>Results</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Administration<span aria-hidden="true">/</span><strong>Students</strong></div><span class="workspace-tag">Admin portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <p class="back-link"><a href="<?php echo e(BASE_URL); ?>/admin/dashboard.php">&laquo; Back to Dashboard</a></p>
    <p class="eyebrow">Students</p>
    <h1>Manage Students</h1>
    <p class="page-description">A clear view of your students, their accounts, and academic details.</p>

    <?php foreach ($errors as $err): ?>
        <p role="alert" style="color:red;"><?php echo e($err); ?></p><?php endforeach; ?>
    <?php if ($successMessage !== ''): ?>
        <p role="status" style="color:green;"><?php echo e($successMessage); ?></p><?php endif; ?>

    <section class="panel editor-panel"><h2><?php echo $editStudent ? 'Edit Student' : 'Add New Student'; ?></h2>

    <form class="editor-form" method="post" action="students.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $editStudent ? 'update' : 'create'; ?>">

        <?php if ($editStudent): ?>
            <input type="hidden" name="student_id" value="<?php echo e((string) $editStudent['student_id']); ?>">
        <?php endif; ?>

        <div class="field"><label for="username">Username</label>
        <input type="text" id="username" name="username" maxlength="80" required
            value="<?php echo e($editStudent['username'] ?? ($_POST['username'] ?? '')); ?>"></div>

        <div class="field"><label for="student_number">Student Number</label>
        <input type="text" id="student_number" name="student_number" maxlength="40" required
            value="<?php echo e($editStudent['student_number'] ?? ($_POST['student_number'] ?? '')); ?>"></div>

        <div class="field"><label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" maxlength="120" required
            value="<?php echo e($editStudent['full_name'] ?? ($_POST['full_name'] ?? '')); ?>"></div>

        <div class="field"><label for="batch">Batch (optional)</label>
        <input type="text" id="batch" name="batch" maxlength="30"
            value="<?php echo e($editStudent['batch'] ?? ($_POST['batch'] ?? '')); ?>"></div>

        <?php if (!$editStudent): ?>
            <div class="field"><label for="initial_password">Initial Password (min 8 characters)</label>
            <input type="password" id="initial_password" name="initial_password" minlength="8" required></div>
        <?php else: ?>
            <p class="context-line"><em>Your existing password stays unchanged when you edit this profile.</em></p>
        <?php endif; ?>

        <div class="form-actions"><button type="submit"><?php echo $editStudent ? 'Save Changes' : 'Create Student'; ?></button>
        <?php if ($editStudent): ?><a href="students.php">Cancel edit</a><?php endif; ?>
    </div></form></section>

    <h2>All Students</h2>
    <div class="table-region" role="region" aria-label="Students table" tabindex="0"><table class="data-table">
        <tr>
            <th scope="col">Username</th>
            <th scope="col">Student #</th>
            <th scope="col">Name</th>
            <th scope="col">Batch</th>
            <th scope="col">Status</th>
            <th scope="col">Actions</th>
        </tr>
        <?php foreach ($students as $s): ?>
            <tr>
                <td><?php echo e($s['username']); ?></td>
                <td><?php echo e($s['student_number']); ?></td>
                <td><?php echo e($s['full_name']); ?></td>
                <td><?php echo e($s['batch'] ?? '—'); ?></td>
                <td><span class="status-pill"><?php echo (int) $s['is_active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
                <td class="actions-cell">
                    <a href="students.php?edit=<?php echo (int) $s['student_id']; ?>">Edit</a>
                    


                    <form method="post" action="students.php" style="display:inline;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_active">


                        <input type="hidden" name="student_id" value="<?php echo (int) $s['student_id']; ?>">
                        <button
                            type="submit"><?php echo (int) $s['is_active'] === 1 ? 'Deactivate' : 'Reactivate'; ?></button>
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