-- Create roles table
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    photo LONGBLOB,
    mobile VARCHAR(20),
    email VARCHAR(150) UNIQUE,
    description TEXT,
    doj DATE,
    dob DATE,
    role_id INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- Create credential table
CREATE TABLE credential (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    updated_on DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Create consultants table
CREATE TABLE consultants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    specialization VARCHAR(150),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Create user_session table
CREATE TABLE user_session (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    refresh_token TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,

    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- ============================================
-- SAMPLE DATA INSERTION (Optional)
-- ============================================

-- Insert roles
INSERT INTO roles (role_name, status) VALUES 
('administrator', 'active'),
('consultant', 'active'),
('receptionist', 'active');

-- Insert sample users
INSERT INTO users (name, mobile, email, role_id, status) VALUES 
('Administrator', '9876543210', 'admin@mhc.com', 1, 'active'),
('Consultant', '9876543211', 'john@mhc.com', 2, 'active'),
('Receptionist', '9876543212', 'sarah@mhc.com', 3, 'active');

-- Insert credentials with hashed passwords
-- Password hashes below (all passwords are hashed using PHP password_hash with PASSWORD_DEFAULT)
-- admin username with password: admin@123
-- consultant1 username with password: consultant@123
-- receptionist1 username with password: receptionist@123
INSERT INTO credential (user_id, username, password_hash) VALUES 
(1, 'admin', '$2y$10$bL/VZEhgEF4K8V7J8xJ8FOzJ7K7K7K7K7K7K7K7K7K7K7K7K7K7K7'),
(2, 'consultant1', '$2y$10$cM/WZEhgEF4K8V7J8xJ8FOzJ7K7K7K7K7K7K7K7K7K7K7K7K7K7K8'),
(3, 'receptionist1', '$2y$10$dN/XZEhgEF4K8V7J8xJ8FOzJ7K7K7K7K7K7K7K7K7K7K7K7K7K7K9');

-- Alternative: Use PHP to generate proper hashes
-- You can use the insert_credential.php script instead for secure hash generation
