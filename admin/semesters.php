<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/authorization.php';
require_once __DIR__ . '/../includes/evaluation_window.php';
require_once __DIR__ . '/../includes/csrf.php';

start_app_session();
$user = require_role('admin');

$errors = [];
$successMessage = '';

/**
 * Converts an HTML <input type="datetime-local"> value ("2026-11-01T09:00")
 * into MySQL DATETIME format. Returns null if the format is invalid.
 */
function parse_datetime_local(string $value): ?string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
    return $dt === false ? null : $dt->format('Y-m-d H:i:s');
}

/** Converts a stored MySQL DATETIME back into datetime-local input format. */
function to_datetime_local(?string $mysqlDatetime): string
{
    if ($mysqlDatetime === null) {
        return '';
    }
    return str_replace(' ', 'T', substr($mysqlDatetime, 0, 16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $semesterId = $action === 'update' ? validate_positive_int($_POST['semester_id'] ?? null) : null;
        $name = validate_required_string($_POST['semester_name'] ?? '', 30);
        $year = validate_positive_int($_POST['year'] ?? null);
        $openAt = parse_datetime_local(trim((string) ($_POST['evaluation_open_at'] ?? '')));
        $closeAt = parse_datetime_local(trim((string) ($_POST['evaluation_close_at'] ?? '')));

        $isLocked = false;
        if ($action === 'update' && $semesterId !== null) {
            $lockStmt = $pdo->prepare('SELECT is_locked FROM semesters WHERE semester_id = :id');
            $lockStmt->execute([':id' => $semesterId]);
            $row = $lockStmt->fetch();
            $isLocked = $row !== false && (int) $row['is_locked'] === 1;
        }

        if ($action === 'update' && $semesterId === null) {
            $errors[] = 'Invalid semester selected for editing.';
        }
        if ($isLocked) {
            $errors[] = 'This semester is locked and cannot be edited.';
        }
        if ($name === null) {
            $errors[] = 'Semester name is required (max 30 characters).';
        }
        if ($year === null) {
            $errors[] = 'A valid year is required.';
        }
        if ($openAt === null || $closeAt === null) {
            $errors[] = 'Both open and close date/time are required and must be valid.';
        } elseif ($openAt >= $closeAt) {
            $errors[] = 'Opening date/time must be earlier than closing date/time.';
        }

        if (empty($errors)) {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO semesters (semester_name, year, evaluation_open_at, evaluation_close_at)
                         VALUES (:name, :year, :open, :close)'
                    );
                    $stmt->execute([':name' => $name, ':year' => $year, ':open' => $openAt, ':close' => $closeAt]);
                    $successMessage = 'Semester created.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE semesters SET semester_name = :name, year = :year,
                         evaluation_open_at = :open, evaluation_close_at = :close
                         WHERE semester_id = :id'
                    );
                    $stmt->execute([':name' => $name, ':year' => $year, ':open' => $openAt, ':close' => $closeAt, ':id' => $semesterId]);
                    $successMessage = 'Semester updated.';
                }
            } catch (PDOException $ex) {
                if ((int) ($ex->errorInfo[1] ?? 0) === 1062) {
                    $errors[] = 'That semester name + year combination already exists.';
                } else {
                    error_log('[RateMyFaculty] admin/semesters.php: ' . $ex->getMessage());
                    $errors[] = 'A server error occurred. Please try again.';
                }
            }
        }
    } elseif ($action === 'toggle_lock') {
        $semesterId = validate_positive_int($_POST['semester_id'] ?? null);
        if ($semesterId !== null) {
            $stmt = $pdo->prepare('UPDATE semesters SET is_locked = 1 - is_locked WHERE semester_id = :id');
            $stmt->execute([':id' => $semesterId]);
            $successMessage = 'Semester lock status updated.';
        }
    }
}

$editSemester = null;
$editId = validate_positive_int($_GET['edit'] ?? null);
if ($editId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM semesters WHERE semester_id = :id');
    $stmt->execute([':id' => $editId]);
    $editSemester = $stmt->fetch() ?: null;
}

$semesters = $pdo->query('SELECT * FROM semesters ORDER BY year DESC, semester_name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title>Admin — Semesters</title>
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
            <a class="nav-link" href="semesters.php" aria-current="page"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#calendar"></use></svg><span>Semesters</span></a>
            <a class="nav-link" href="offerings.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#layers"></use></svg><span>Course offerings</span></a>
            <a class="nav-link" href="enrollments.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#enroll"></use></svg><span>Enrollments</span></a>
            <a class="nav-link" href="progress.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#progress"></use></svg><span>Evaluation progress</span></a>
            <a class="nav-link" href="results.php"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#chart"></use></svg><span>Results</span></a></nav>
        <div class="sidebar-note"><svg class="icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#book"></use></svg><strong>Every perspective matters.</strong>Thoughtful feedback. Better teaching.<br>A stronger academic community.</div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div class="topbar-path">Administration<span aria-hidden="true">/</span><strong>Semesters</strong></div><span class="workspace-tag">Admin portal</span></header>
        <main class="main-content" id="main-content" tabindex="-1">
    <p class="back-link"><a href="<?php echo e(BASE_URL); ?>/admin/dashboard.php">&laquo; Back to Dashboard</a></p>
    <p class="eyebrow">Semesters</p>
    <h1>Manage Semesters</h1>
    <p class="page-description">Set the academic calendar and define when students can share feedback.</p>

    <?php foreach ($errors as $err): ?>
        <p role="alert" style="color:red;"><?php echo e($err); ?></p>
    <?php endforeach; ?>
    <?php if ($successMessage !== ''): ?>
        <p role="status" style="color:green;"><?php echo e($successMessage); ?></p>
    <?php endif; ?>

    <?php $locked = $editSemester !== null && (int) $editSemester['is_locked'] === 1; ?>

    <!-- UI REDESIGN: Unified management form panel. -->
    <section class="panel editor-panel"><h2><?php echo $editSemester ? 'Edit Semester' : 'Add New Semester'; ?></h2>
    <?php if ($locked): ?>
        <p class="context-line"><em>This semester is locked — unlock it below before editing.</em></p>
    <?php else: ?>

        <form class="editor-form" method="post" action="semesters.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $editSemester ? 'update' : 'create'; ?>">

            <?php if ($editSemester): ?>
                <input type="hidden" name="semester_id" value="<?php echo e((string) $editSemester['semester_id']); ?>">
            <?php endif; ?>

            <div class="field"><label for="semester_name">Semester Name</label>
            <input type="text" id="semester_name" name="semester_name" maxlength="30" required
                value="<?php echo e($editSemester['semester_name'] ?? ($_POST['semester_name'] ?? '')); ?>"></div>

            <div class="field"><label for="year">Year</label>
            <input type="number" id="year" name="year" min="2000" max="2100" required
                value="<?php echo e($editSemester['year'] ?? ($_POST['year'] ?? '')); ?>"></div>

            <div class="field"><label for="evaluation_open_at">Evaluation Opens</label>
            <input type="datetime-local" id="evaluation_open_at" name="evaluation_open_at" required
                value="<?php echo e(to_datetime_local($editSemester['evaluation_open_at'] ?? null)); ?>"></div>

            <div class="field"><label for="evaluation_close_at">Evaluation Closes</label>
            <input type="datetime-local" id="evaluation_close_at" name="evaluation_close_at" required
                value="<?php echo e(to_datetime_local($editSemester['evaluation_close_at'] ?? null)); ?>"></div>

            <div class="form-actions"><button type="submit"><?php echo $editSemester ? 'Save Changes' : 'Create Semester'; ?></button>
            <?php if ($editSemester): ?>
                <a href="semesters.php">Cancel edit</a>
            <?php endif; ?>
        </div></form>
    <?php endif; ?></section>

    <h2>All Semesters</h2>
    <!-- UI REDESIGN: Focusable table region supports keyboard scrolling on smaller screens. -->
    <div class="table-region" role="region" aria-label="Semesters table" tabindex="0"><table class="data-table">
        <tr>
            <th scope="col">Name</th>
            <th scope="col">Year</th>
            <th scope="col">Opens</th>
            <th scope="col">Closes</th>
            <th scope="col">Window Status</th>
            <th scope="col">Locked?</th>
            <th scope="col">Actions</th>
        </tr>
        <?php foreach ($semesters as $s): ?>
            <?php $status = get_window_status($s['evaluation_open_at'], $s['evaluation_close_at']); ?>
            <tr>
                <td><?php echo e($s['semester_name']); ?></td>
                <td><?php echo e((string) $s['year']); ?></td>
                <td><?php echo e($s['evaluation_open_at']); ?></td>
                <td><?php echo e($s['evaluation_close_at']); ?></td>
                <td><?php echo e(window_status_label($status)); ?></td>
                <td><?php echo (int) $s['is_locked'] === 1 ? 'Yes' : 'No'; ?></td>
                <td>
                    <?php if ((int) $s['is_locked'] !== 1): ?>
                        <a href="semesters.php?edit=<?php echo (int) $s['semester_id']; ?>">Edit</a>
                        
                    <?php endif; ?>

                    <form method="post" action="semesters.php" style="display:inline;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_lock">

                        <input type="hidden" name="semester_id" value="<?php echo (int) $s['semester_id']; ?>">
                        <button type="submit"><?php echo (int) $s['is_locked'] === 1 ? 'Unlock' : 'Lock'; ?></button>
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