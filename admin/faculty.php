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
        $fullName = validate_required_string($_POST['full_name'] ?? '', 120);
        $facultyCode = validate_optional_text($_POST['faculty_code'] ?? '', 40);
        $designation = validate_optional_text($_POST['designation'] ?? '', 80);
        $password = (string) ($_POST['initial_password'] ?? '');

        if ($username === null) {
            $errors[] = 'Username is required (max 80 characters).';
        }
        if ($fullName === null) {
            $errors[] = 'Full name is required (max 120 characters).';
        }
        if ($facultyCode === null) {
            $errors[] = 'Faculty code must be under 40 characters.';
        }
        if ($designation === null) {
            $errors[] = 'Designation must be under 80 characters.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Initial password must be at least 8 characters.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $hash = password_hash($password, PASSWORD_BCRYPT);
                $userStmt = $pdo->prepare(
                    "INSERT INTO users (username, password_hash, role) VALUES (:username, :hash, 'faculty')"
                );
                $userStmt->execute([':username' => $username, ':hash' => $hash]);
                $newUserId = (int) $pdo->lastInsertId();

                $facultyStmt = $pdo->prepare(
                    'INSERT INTO faculty (user_id, faculty_code, full_name, designation)
                     VALUES (:user_id, :code, :name, :designation)'
                );
                $facultyStmt->execute([
                    ':user_id' => $newUserId,
                    ':code' => ($facultyCode === '' ? null : $facultyCode),
                    ':name' => $fullName,
                    ':designation' => ($designation === '' ? null : $designation),
                ]);
                $newFacultyId = (int) $pdo->lastInsertId();

                log_admin_action(
                    $pdo,
                    (int) $user['user_id'],
                    'CREATE',
                    'faculty',
                    (string) $newFacultyId,
                    'Created faculty ' . $fullName
                );

                $pdo->commit();
                $successMessage = 'Faculty account created.';
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $msg = $ex->getMessage();
                if (str_contains($msg, 'uq_users_username')) {
                    $errors[] = 'That username is already taken.';
                } elseif (str_contains($msg, 'uq_faculty_code')) {
                    $errors[] = 'That faculty code is already in use.';
                } else {
                    error_log('[RateMyFaculty] admin/faculty.php create: ' . $msg);
                    $errors[] = 'A server error occurred. Please try again.';
                }
            }
        }
    } elseif ($action === 'update') {
        $facultyId = validate_positive_int($_POST['faculty_id'] ?? null);
        $username = validate_required_string($_POST['username'] ?? '', 80);
        $fullName = validate_required_string($_POST['full_name'] ?? '', 120);
        $facultyCode = validate_optional_text($_POST['faculty_code'] ?? '', 40);
        $designation = validate_optional_text($_POST['designation'] ?? '', 80);

        if ($facultyId === null) {
            $errors[] = 'Invalid faculty member selected.';
        }
        if ($username === null) {
            $errors[] = 'Username is required (max 80 characters).';
        }
        if ($fullName === null) {
            $errors[] = 'Full name is required (max 120 characters).';
        }
        if ($facultyCode === null) {
            $errors[] = 'Faculty code must be under 40 characters.';
        }
        if ($designation === null) {
            $errors[] = 'Designation must be under 80 characters.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $lookup = $pdo->prepare('SELECT user_id FROM faculty WHERE faculty_id = :id');
                $lookup->execute([':id' => $facultyId]);
                $row = $lookup->fetch();
                if ($row === false) {
                    throw new RuntimeException('Faculty member not found.');
                }
                $userId = (int) $row['user_id'];

                $pdo->prepare('UPDATE users SET username = :username WHERE user_id = :user_id')
                    ->execute([':username' => $username, ':user_id' => $userId]);

                $pdo->prepare(
                    'UPDATE faculty SET faculty_code = :code, full_name = :name, designation = :designation
                     WHERE faculty_id = :id'
                )->execute([
                            ':code' => ($facultyCode === '' ? null : $facultyCode),
                            ':name' => $fullName,
                            ':designation' => ($designation === '' ? null : $designation),
                            ':id' => $facultyId,
                        ]);

                log_admin_action(
                    $pdo,
                    (int) $user['user_id'],
                    'UPDATE',
                    'faculty',
                    (string) $facultyId,
                    'Updated faculty ' . $fullName
                );

                $pdo->commit();
                $successMessage = 'Faculty updated.';
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $msg = $ex->getMessage();
                if (str_contains($msg, 'uq_users_username')) {
                    $errors[] = 'That username is already taken.';
                } elseif (str_contains($msg, 'uq_faculty_code')) {
                    $errors[] = 'That faculty code is already in use.';
                } else {
                    error_log('[RateMyFaculty] admin/faculty.php update: ' . $msg);
                    $errors[] = 'A server error occurred. Please try again.';
                }
            } catch (RuntimeException $ex) {
                $pdo->rollBack();
                $errors[] = $ex->getMessage();
            }
        }
    } elseif ($action === 'toggle_active') {
        $facultyId = validate_positive_int($_POST['faculty_id'] ?? null);
        if ($facultyId !== null) {
            $stmt = $pdo->prepare(
                'UPDATE users u JOIN faculty f ON f.user_id = u.user_id
                 SET u.is_active = 1 - u.is_active WHERE f.faculty_id = :id'
            );
            $stmt->execute([':id' => $facultyId]);

            $statusStmt = $pdo->prepare(
                'SELECT u.is_active, f.full_name FROM users u
                 JOIN faculty f ON f.user_id = u.user_id WHERE f.faculty_id = :id'
            );
            $statusStmt->execute([':id' => $facultyId]);
            $row = $statusStmt->fetch();

            $newState = $row !== false && (int) $row['is_active'] === 1 ? 'REACTIVATE' : 'DEACTIVATE';
            log_admin_action(
                $pdo,
                (int) $user['user_id'],
                $newState,
                'faculty',
                (string) $facultyId,
                ($row['full_name'] ?? '') . ' ' . strtolower($newState) . 'd'
            );

            $successMessage = 'Faculty status updated.';
        }
    }
}

$editFaculty = null;
$editId = validate_positive_int($_GET['edit'] ?? null);
if ($editId !== null) {
    $stmt = $pdo->prepare(
        'SELECT f.*, u.username, u.is_active FROM faculty f
         JOIN users u ON u.user_id = f.user_id WHERE f.faculty_id = :id'
    );
    $stmt->execute([':id' => $editId]);
    $editFaculty = $stmt->fetch() ?: null;
}

$facultyList = $pdo->query(
    'SELECT f.faculty_id, f.faculty_code, f.full_name, f.designation, u.username, u.is_active
     FROM faculty f JOIN users u ON u.user_id = f.user_id
     ORDER BY f.full_name'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>Admin — Faculty</title>
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
            <a class="nav-link" href="faculty.php" aria-current="page"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#faculty"></use></svg><span>Faculty</span></a>
            <a class="nav-link" href="courses.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><span>Courses</span></a>
            <a class="nav-link" href="semesters.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#calendar"></use></svg><span>Semesters</span></a>
            <a class="nav-link" href="offerings.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#layers"></use></svg><span>Course offerings</span></a>
            <a class="nav-link" href="enrollments.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#enroll"></use></svg><span>Enrollments</span></a>
            <a class="nav-link" href="progress.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#progress"></use></svg><span>Evaluation progress</span></a>
            <a class="nav-link" href="results.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#chart"></use></svg><span>Results</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Administration<span aria-hidden="true">/</span><strong>Faculty</strong></div><span class="workspace-tag">Admin portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <p class="back-link"><a href="<?php echo e(BASE_URL); ?>/admin/dashboard.php">&laquo; Back to Dashboard</a></p>
    <p class="eyebrow">Faculty</p>
    <h1>Manage Faculty</h1>
    <p class="page-description">Manage faculty profiles and keep your teaching community up to date.</p>

    <?php foreach ($errors as $err): ?>
        <p role="alert" style="color:red;"><?php echo e($err); ?></p><?php endforeach; ?>
    <?php if ($successMessage !== ''): ?>
        <p role="status" style="color:green;"><?php echo e($successMessage); ?></p><?php endif; ?>

    <!-- UI REDESIGN: Unified management form panel. -->
    <section class="panel editor-panel"><h2><?php echo $editFaculty ? 'Edit Faculty' : 'Add New Faculty'; ?></h2>

    <form class="editor-form" method="post" action="faculty.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $editFaculty ? 'update' : 'create'; ?>">

        <?php if ($editFaculty): ?>
            <input type="hidden" name="faculty_id" value="<?php echo e((string) $editFaculty['faculty_id']); ?>">
        <?php endif; ?>

        <div class="field"><label for="username">Username</label>
        <input type="text" id="username" name="username" maxlength="80" required
            value="<?php echo e($editFaculty['username'] ?? ($_POST['username'] ?? '')); ?>"></div>

        <div class="field"><label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" maxlength="120" required
            value="<?php echo e($editFaculty['full_name'] ?? ($_POST['full_name'] ?? '')); ?>"></div>

        <div class="field"><label for="faculty_code">Faculty Code (optional)</label>
        <input type="text" id="faculty_code" name="faculty_code" maxlength="40"
            value="<?php echo e($editFaculty['faculty_code'] ?? ($_POST['faculty_code'] ?? '')); ?>"></div>

        <div class="field"><label for="designation">Designation (optional)</label>
        <input type="text" id="designation" name="designation" maxlength="80"
            value="<?php echo e($editFaculty['designation'] ?? ($_POST['designation'] ?? '')); ?>"></div>

        <?php if (!$editFaculty): ?>
            <div class="field"><label for="initial_password">Initial Password (min 8 characters)</label>
            <input type="password" id="initial_password" name="initial_password" minlength="8" required></div>
        <?php else: ?>
            <p class="context-line"><em>Your existing password stays unchanged when you edit this profile.</em></p>
        <?php endif; ?>

        <div class="form-actions"><button type="submit"><?php echo $editFaculty ? 'Save Changes' : 'Create Faculty'; ?></button>
        <?php if ($editFaculty): ?><a href="faculty.php">Cancel edit</a><?php endif; ?>
    </div></form></section>

    <h2>All Faculty</h2>
    <!-- UI REDESIGN: Focusable table region supports keyboard scrolling on smaller screens. -->
    <div class="table-region" role="region" aria-label="Faculty table" tabindex="0"><table class="data-table">
        <tr>
            <th scope="col">Username</th>
            <th scope="col">Code</th>
            <th scope="col">Name</th>
            <th scope="col">Designation</th>
            <th scope="col">Status</th>
            <th scope="col">Actions</th>
        </tr>
        <?php foreach ($facultyList as $f): ?>
            <tr>
                <td><?php echo e($f['username']); ?></td>
                <td><?php echo e($f['faculty_code'] ?? '—'); ?></td>
                <td><?php echo e($f['full_name']); ?></td>
                <td><?php echo e($f['designation'] ?? '—'); ?></td>
                <td><span class="status-pill"><?php echo (int) $f['is_active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
                <td class="actions-cell">
                    <a href="faculty.php?edit=<?php echo (int) $f['faculty_id']; ?>">Edit</a>
                    


                    <form method="post" action="faculty.php" style="display:inline;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_active">

                        <input type="hidden" name="faculty_id" value="<?php echo (int) $f['faculty_id']; ?>">
                        <button
                            type="submit"><?php echo (int) $f['is_active'] === 1 ? 'Deactivate' : 'Reactivate'; ?></button>
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