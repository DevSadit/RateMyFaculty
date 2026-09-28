<?php
declare(strict_types=1);

function get_offering_eligibility(PDO $pdo, int $studentId, int $offeringId): array
{
    $stmt = $pdo->prepare(
        'SELECT co.offering_id, co.section, c.course_code, c.course_title,
                sem.semester_name, sem.year, sem.evaluation_open_at, sem.evaluation_close_at,
                f.full_name AS faculty_name,
                en.status AS enrollment_status,
                (SELECT COUNT(*) FROM submission_receipts sr
                 WHERE sr.student_id = :sid1 AND sr.offering_id = co.offering_id) AS receipt_count
         FROM course_offerings co
         JOIN courses c ON c.course_id = co.course_id
         JOIN semesters sem ON sem.semester_id = co.semester_id
         JOIN faculty f ON f.faculty_id = co.faculty_id
         LEFT JOIN enrollments en ON en.offering_id = co.offering_id AND en.student_id = :sid2
         WHERE co.offering_id = :offering_id'
    );
    $stmt->execute([':sid1' => $studentId, ':sid2' => $studentId, ':offering_id' => $offeringId]);
    $row = $stmt->fetch();

    if ($row === false) {
        return ['eligible' => false, 'reason' => 'That offering does not exist.', 'offering' => null];
    }
    if ($row['enrollment_status'] === null) {
        return ['eligible' => false, 'reason' => 'You are not enrolled in this offering.', 'offering' => $row];
    }
    if ($row['enrollment_status'] !== 'completed') {
        return ['eligible' => false, 'reason' => 'You have not completed this course yet.', 'offering' => $row];
    }

    $windowStatus = get_window_status($row['evaluation_open_at'], $row['evaluation_close_at']);
    if ($windowStatus === WINDOW_UPCOMING) {
        return ['eligible' => false, 'reason' => 'The evaluation window has not opened yet.', 'offering' => $row];
    }
    if ($windowStatus === WINDOW_CLOSED) {
        return ['eligible' => false, 'reason' => 'The evaluation window has closed.', 'offering' => $row];
    }

    if ((int) $row['receipt_count'] > 0) {
        return ['eligible' => false, 'reason' => 'You have already submitted an evaluation for this offering.', 'offering' => $row];
    }

    return ['eligible' => true, 'reason' => '', 'offering' => $row];
}