-- Run this SQL if you prefer manual import. Adjust the password_hash accordingly.
INSERT INTO users (username, email, password_hash, role, created_at) VALUES
('admin', 'admin@example.com', '$2y$10$replace_with_bcrypt_hash_here', 'admin', NOW());

-- If you want to create a real hash first, run PHP:
-- php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"
