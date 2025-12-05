-- Create admins table
CREATE TABLE IF NOT EXISTS admins (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(100) UNIQUE NOT NULL,
  email VARCHAR(255) UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'moderator', 'editor') DEFAULT 'moderator',
  status ENUM('active', 'inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL,
  INDEX idx_username (username)
);

-- Insert default admin user (password: admin123)
INSERT IGNORE INTO admins (username, email, password, role, status) 
VALUES (
  'admin',
  'admin@scrollnovels.local',
  '$2y$10$zBFpMLhP8VhkWBpf9MpOXeM2O1ckVWiQgU3iU9Gq1PzQzvLxu5WrS',
  'admin',
  'active'
);
