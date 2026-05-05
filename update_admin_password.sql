-- Update admin password to use hashed password
-- Default admin credentials: admin@crochet.com / admin123
-- Run this in phpMyAdmin or via command line

USE e_reserve_db;

-- Update admin password to hashed version (password: admin123)
UPDATE users 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE email = 'admin@crochet.com';

-- Verify the update
SELECT id, name, email, role, LEFT(password, 30) as password_preview FROM users WHERE email = 'admin@crochet.com';
