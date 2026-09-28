CREATE DATABASE IF NOT EXISTS ratemyfaculty CHARACTER
SET
    utf8mb4 COLLATE utf8mb4_general_ci;

USE ratemyfaculty;

CREATE TABLE
    users (
        user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM ('student', 'faculty', 'admin') NOT NULL,
        is_active TINYINT (1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT uq_users_username UNIQUE (username)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE
    students (
        student_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        student_number VARCHAR(40) NOT NULL,
        full_name VARCHAR(120) NOT NULL,
        batch VARCHAR(30) NULL,
        CONSTRAINT uq_students_user_id UNIQUE (user_id),
        CONSTRAINT uq_students_number UNIQUE (student_number),
        CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE
    faculty (
        faculty_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        faculty_code VARCHAR(40) NULL,
        full_name VARCHAR(120) NOT NULL,
        designation VARCHAR(80) NULL,
        CONSTRAINT uq_faculty_user_id UNIQUE (user_id),
        CONSTRAINT uq_faculty_code UNIQUE (faculty_code),
        CONSTRAINT fk_faculty_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE
    courses (
        course_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(30) NOT NULL,
        course_title VARCHAR(150) NOT NULL,
        credit_hours DECIMAL(3, 1) NULL,
        is_active TINYINT (1) NOT NULL DEFAULT 1,
        CONSTRAINT uq_courses_code UNIQUE (course_code)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE
    semesters (
        semester_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        semester_name VARCHAR(30) NOT NULL,
        year SMALLINT UNSIGNED NOT NULL,
        evaluation_open_at DATETIME NOT NULL,
        evaluation_close_at DATETIME NOT NULL,
        is_locked TINYINT (1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT uq_semesters_name_year UNIQUE (semester_name, year),
        CONSTRAINT chk_semesters_window CHECK (evaluation_open_at < evaluation_close_at)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE
    course_offerings (
        offering_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        course_id INT UNSIGNED NOT NULL,
        semester_id INT UNSIGNED NOT NULL,
        faculty_id INT UNSIGNED NOT NULL,
        section VARCHAR(20) NOT NULL,
        is_active TINYINT (1) NOT NULL DEFAULT 1,
        CONSTRAINT uq_offerings_course_semester_section UNIQUE (course_id, semester_id, section),
        CONSTRAINT fk_offerings_course FOREIGN KEY (course_id) REFERENCES courses (course_id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_offerings_semester FOREIGN KEY (semester_id) REFERENCES semesters (semester_id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_offerings_faculty FOREIGN KEY (faculty_id) REFERENCES faculty (faculty_id) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE
    enrollments (
        enrollment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_id INT UNSIGNED NOT NULL,
        offering_id INT UNSIGNED NOT NULL,
        status ENUM ('enrolled', 'completed') NOT NULL DEFAULT 'enrolled',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT uq_enrollments_student_offering UNIQUE (student_id, offering_id),
        CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_enrollments_offering FOREIGN KEY (offering_id) REFERENCES course_offerings (offering_id) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE
    submission_receipts (
        student_id INT UNSIGNED NOT NULL,
        offering_id INT UNSIGNED NOT NULL,
        submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (student_id, offering_id),
        CONSTRAINT fk_receipts_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT fk_receipts_offering FOREIGN KEY (offering_id) REFERENCES course_offerings (offering_id) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE
    evaluations (
        evaluation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        offering_id INT UNSIGNED NOT NULL,
        teaching_quality TINYINT UNSIGNED NOT NULL,
        communication TINYINT UNSIGNED NOT NULL,
        punctuality TINYINT UNSIGNED NOT NULL,
        helpfulness TINYINT UNSIGNED NOT NULL,
        overall_rating TINYINT UNSIGNED NOT NULL,
        comment VARCHAR(1000) NULL,
        submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_evaluations_offering FOREIGN KEY (offering_id) REFERENCES course_offerings (offering_id) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT chk_eval_teaching_quality CHECK (teaching_quality BETWEEN 1 AND 5),
        CONSTRAINT chk_eval_communication CHECK (communication BETWEEN 1 AND 5),
        CONSTRAINT chk_eval_punctuality CHECK (punctuality BETWEEN 1 AND 5),
        CONSTRAINT chk_eval_helpfulness CHECK (helpfulness BETWEEN 1 AND 5),
        CONSTRAINT chk_eval_overall_rating CHECK (overall_rating BETWEEN 1 AND 5)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE
    audit_logs (
        audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_user_id INT UNSIGNED NOT NULL,
        action VARCHAR(40) NOT NULL,
        entity_type VARCHAR(40) NOT NULL,
        entity_id VARCHAR(64) NOT NULL,
        details VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_audit_admin_user FOREIGN KEY (admin_user_id) REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;