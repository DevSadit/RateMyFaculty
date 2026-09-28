-- =========================================================
-- RateMyFaculty — Seed / Demo Data
-- RMF-016
-- All demo accounts use password: Password123
-- For an existing import with the old hashes, run repair_demo_passwords.sql.
-- =========================================================
USE ratemyfaculty;

-- ---------------------------------------------------------
-- Users (1 admin, 2 faculty, 5 students)
-- ---------------------------------------------------------
INSERT INTO
    users (username, password_hash, role)
VALUES
    (
        'admin1',
        '$2y$10$zZzhxtSI.z/OtF7UG29fAOHf0bv5Ny6Pjv1WgZxXR5iZxHYcac5Sy',
        'admin'
    ),
    (
        'facrahman',
        '$2y$10$zZzhxtSI.z/OtF7UG29fAOHf0bv5Ny6Pjv1WgZxXR5iZxHYcac5Sy',
        'faculty'
    ),
    (
        'facnusrat',
        '$2y$10$zZzhxtSI.z/OtF7UG29fAOHf0bv5Ny6Pjv1WgZxXR5iZxHYcac5Sy',
        'faculty'
    ),
    (
        'stu001',
        '$2y$10$zZzhxtSI.z/OtF7UG29fAOHf0bv5Ny6Pjv1WgZxXR5iZxHYcac5Sy',
        'student'
    ),
    (
        'stu002',
        '$2y$10$zZzhxtSI.z/OtF7UG29fAOHf0bv5Ny6Pjv1WgZxXR5iZxHYcac5Sy',
        'student'
    ),
    (
        'stu003',
        '$2y$10$zZzhxtSI.z/OtF7UG29fAOHf0bv5Ny6Pjv1WgZxXR5iZxHYcac5Sy',
        'student'
    ),
    (
        'stu004',
        '$2y$10$zZzhxtSI.z/OtF7UG29fAOHf0bv5Ny6Pjv1WgZxXR5iZxHYcac5Sy',
        'student'
    ),
    (
        'stu005',
        '$2y$10$zZzhxtSI.z/OtF7UG29fAOHf0bv5Ny6Pjv1WgZxXR5iZxHYcac5Sy',
        'student'
    );

-- ---------------------------------------------------------
-- Faculty profiles
-- ---------------------------------------------------------
INSERT INTO
    faculty (user_id, faculty_code, full_name, designation)
SELECT
    user_id,
    'FAC001',
    'Mr. Rahman',
    'Lecturer'
FROM
    users
WHERE
    username = 'facrahman';

INSERT INTO
    faculty (user_id, faculty_code, full_name, designation)
SELECT
    user_id,
    'FAC002',
    'Ms. Nusrat',
    'Assistant Professor'
FROM
    users
WHERE
    username = 'facnusrat';

-- ---------------------------------------------------------
-- Student profiles
-- ---------------------------------------------------------
INSERT INTO
    students (user_id, student_number, full_name, batch)
SELECT
    user_id,
    'CSE-2024-101',
    'Student One',
    '2024'
FROM
    users
WHERE
    username = 'stu001';

INSERT INTO
    students (user_id, student_number, full_name, batch)
SELECT
    user_id,
    'CSE-2024-102',
    'Student Two',
    '2024'
FROM
    users
WHERE
    username = 'stu002';

INSERT INTO
    students (user_id, student_number, full_name, batch)
SELECT
    user_id,
    'CSE-2024-103',
    'Student Three',
    '2024'
FROM
    users
WHERE
    username = 'stu003';

INSERT INTO
    students (user_id, student_number, full_name, batch)
SELECT
    user_id,
    'CSE-2024-104',
    'Student Four',
    '2024'
FROM
    users
WHERE
    username = 'stu004';

INSERT INTO
    students (user_id, student_number, full_name, batch)
SELECT
    user_id,
    'CSE-2024-105',
    'Student Five',
    '2024'
FROM
    users
WHERE
    username = 'stu005';

-- ---------------------------------------------------------
-- Courses
-- ---------------------------------------------------------
INSERT INTO
    courses (course_code, course_title, credit_hours)
VALUES
    (
        'CSE2222',
        'Database Management System Laboratory',
        1.5
    ),
    ('CSE3111', 'Computer Networks', 3.0);

-- ---------------------------------------------------------
-- Semester — window already CLOSED, so we can demo released
-- vs suppressed results immediately without waiting.
-- ---------------------------------------------------------
INSERT INTO
    semesters (
        semester_name,
        year,
        evaluation_open_at,
        evaluation_close_at,
        is_locked
    )
VALUES
    (
        'Fall',
        2026,
        '2026-08-01 00:00:00',
        '2026-08-31 23:59:59',
        1
    );

-- ---------------------------------------------------------
-- Course offerings
--   Offering A: CSE2222 / Fall 2026 / Section A / Mr. Rahman  -> will get 3 evaluations (released)
--   Offering B: CSE3111 / Fall 2026 / Section A / Ms. Nusrat  -> will get 2 evaluations (suppressed)
-- ---------------------------------------------------------
INSERT INTO
    course_offerings (course_id, semester_id, faculty_id, section)
SELECT
    c.course_id,
    s.semester_id,
    f.faculty_id,
    'A'
FROM
    courses c,
    semesters s,
    faculty f
WHERE
    c.course_code = 'CSE2222'
    AND s.semester_name = 'Fall'
    AND s.year = 2026
    AND f.faculty_code = 'FAC001';

INSERT INTO
    course_offerings (course_id, semester_id, faculty_id, section)
SELECT
    c.course_id,
    s.semester_id,
    f.faculty_id,
    'A'
FROM
    courses c,
    semesters s,
    faculty f
WHERE
    c.course_code = 'CSE3111'
    AND s.semester_name = 'Fall'
    AND s.year = 2026
    AND f.faculty_code = 'FAC002';

-- ---------------------------------------------------------
-- Enrollments — all 5 students completed Offering A (CSE2222);
-- students 1-2 completed Offering B (CSE3111).
-- ---------------------------------------------------------
INSERT INTO
    enrollments (student_id, offering_id, status)
SELECT
    st.student_id,
    co.offering_id,
    'completed'
FROM
    students st,
    course_offerings co
    JOIN courses c ON c.course_id = co.course_id
WHERE
    c.course_code = 'CSE2222'
    AND st.student_number IN (
        'CSE-2024-101',
        'CSE-2024-102',
        'CSE-2024-103',
        'CSE-2024-104',
        'CSE-2024-105'
    );

INSERT INTO
    enrollments (student_id, offering_id, status)
SELECT
    st.student_id,
    co.offering_id,
    'completed'
FROM
    students st,
    course_offerings co
    JOIN courses c ON c.course_id = co.course_id
WHERE
    c.course_code = 'CSE3111'
    AND st.student_number IN ('CSE-2024-101', 'CSE-2024-102');

-- ---------------------------------------------------------
-- Submission receipts + evaluations for Offering A (CSE2222)
-- 3 responses -> RELEASED once window is closed (it already is).
-- Receipts and evaluations are inserted with NO link between them.
-- ---------------------------------------------------------
INSERT INTO
    submission_receipts (student_id, offering_id)
SELECT
    st.student_id,
    co.offering_id
FROM
    students st,
    course_offerings co
    JOIN courses c ON c.course_id = co.course_id
WHERE
    c.course_code = 'CSE2222'
    AND st.student_number = 'CSE-2024-101';

INSERT INTO
    submission_receipts (student_id, offering_id)
SELECT
    st.student_id,
    co.offering_id
FROM
    students st,
    course_offerings co
    JOIN courses c ON c.course_id = co.course_id
WHERE
    c.course_code = 'CSE2222'
    AND st.student_number = 'CSE-2024-102';

INSERT INTO
    submission_receipts (student_id, offering_id)
SELECT
    st.student_id,
    co.offering_id
FROM
    students st,
    course_offerings co
    JOIN courses c ON c.course_id = co.course_id
WHERE
    c.course_code = 'CSE2222'
    AND st.student_number = 'CSE-2024-103';

INSERT INTO
    evaluations (
        offering_id,
        teaching_quality,
        communication,
        punctuality,
        helpfulness,
        overall_rating,
        comment
    )
SELECT
    co.offering_id,
    5,
    4,
    5,
    5,
    5,
    'Very clear explanations and helpful in lab sessions.'
FROM
    course_offerings co
    JOIN courses c ON c.course_id = co.course_id
WHERE
    c.course_code = 'CSE2222';

INSERT INTO
    evaluations (
        offering_id,
        teaching_quality,
        communication,
        punctuality,
        helpfulness,
        overall_rating,
        comment
    )
SELECT
    co.offering_id,
    4,
    4,
    3,
    4,
    4,
    'Good course overall, sometimes started a bit late.'
FROM
    course_offerings co
    JOIN courses c ON c.course_id = co.course_id
WHERE
    c.course_code = 'CSE2222';

INSERT INTO
    evaluations (
        offering_id,
        teaching_quality,
        communication,
        punctuality,
        helpfulness,
        overall_rating,
        comment
    )
SELECT
    co.offering_id,
    5,
    5,
    5,
    5,
    5,
    NULL
FROM
    course_offerings co
    JOIN courses c ON c.course_id = co.course_id
WHERE
    c.course_code = 'CSE2222';

-- ---------------------------------------------------------
-- Submission receipts + evaluations for Offering B (CSE3111)
-- Only 2 responses -> stays SUPPRESSED even though window is closed.
-- ---------------------------------------------------------
INSERT INTO
    submission_receipts (student_id, offering_id)
SELECT
    st.student_id,
    co.offering_id
FROM
    students st,
    course_offerings co
    JOIN courses c ON c.course_id = co.course_id
WHERE
    c.course_code = 'CSE3111'
    AND st.student_number = 'CSE-2024-101';

INSERT INTO
    submission_receipts (student_id, offering_id)
SELECT
    st.student_id,
    co.offering_id
FROM
    students st,
    course_offerings co
    JOIN courses c ON c.course_id = co.course_id
WHERE
    c.course_code = 'CSE3111'
    AND st.student_number = 'CSE-2024-102';

INSERT INTO
    evaluations (
        offering_id,
        teaching_quality,
        communication,
        punctuality,
        helpfulness,
        overall_rating,
        comment
    )
SELECT
    co.offering_id,
    4,
    3,
    4,
    4,
    4,
    'Decent networking course.'
FROM
    course_offerings co
    JOIN courses c ON c.course_id = co.course_id
WHERE
    c.course_code = 'CSE3111';

INSERT INTO
    evaluations (
        offering_id,
        teaching_quality,
        communication,
        punctuality,
        helpfulness,
        overall_rating,
        comment
    )
SELECT
    co.offering_id,
    3,
    3,
    4,
    3,
    3,
    NULL
FROM
    course_offerings co
    JOIN courses c ON c.course_id = co.course_id
WHERE
    c.course_code = 'CSE3111';
