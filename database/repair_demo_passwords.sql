UPDATE ratemyfaculty.users
SET password_hash = '$2y$10$zZzhxtSI.z/OtF7UG29fAOHf0bv5Ny6Pjv1WgZxXR5iZxHYcac5Sy'
WHERE username IN (
    'admin1', 'facrahman', 'facnusrat',
    'stu001', 'stu002', 'stu003', 'stu004', 'stu005'
)
AND BINARY password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.YeYUdmc1eYxdBWFPKgQwZ1z5vXqM0OYK6';
