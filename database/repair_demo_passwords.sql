-- Repair demo accounts imported from the original seed.sql.
-- Run this file once in phpMyAdmin's Import or SQL tab; do not reimport seed.sql.
-- The corrected demo password is Password123 (case-sensitive).
-- Only named demo accounts still using the original broken hash are updated.
-- Accounts with changed passwords are preserved; running this again is harmless.

UPDATE ratemyfaculty.users
SET password_hash = '$2y$10$zZzhxtSI.z/OtF7UG29fAOHf0bv5Ny6Pjv1WgZxXR5iZxHYcac5Sy'
WHERE username IN (
    'admin1', 'facrahman', 'facnusrat',
    'stu001', 'stu002', 'stu003', 'stu004', 'stu005'
)
AND BINARY password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.YeYUdmc1eYxdBWFPKgQwZ1z5vXqM0OYK6';
