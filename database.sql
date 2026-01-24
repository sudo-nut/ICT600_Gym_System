-- ============================================
-- FitLife Gym Membership System - Database Schema
-- ============================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS gym_membership_system;
USE gym_membership_system;

-- ============================================
-- 1. USERS TABLE (Handles both Members and Admins)
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Will store hashed passwords
    phone VARCHAR(20),
    role ENUM('admin', 'member') DEFAULT 'member',
    loyalty_points INT DEFAULT 0, -- Loyalty points for members
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 2. PLANS TABLE (Simple lookup for Gold/Silver plans)
-- ============================================
CREATE TABLE membership_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL, -- e.g., 'Monthly', 'Yearly'
    price DECIMAL(10, 2) NOT NULL,
    duration_days INT NOT NULL -- e.g., 30 or 365
);

-- ============================================
-- 3. SUBSCRIPTIONS TABLE (Tracks if a user is Active or Expired)
-- ============================================
CREATE TABLE subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'expired', 'pending') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES membership_plans(id) ON DELETE CASCADE
);

-- ============================================
-- 4. PAYMENTS TABLE (Admin needs to view this)
-- ============================================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50) DEFAULT 'Online Banking',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- DUMMY DATA (So you have something to show immediately)
-- ============================================

-- Insert membership plans
INSERT INTO membership_plans (name, price, duration_days) VALUES
('Basic Plan', 30.00, 30),
('Premium Plan', 50.00, 30),
('VIP Plan', 550.00, 365);

-- Insert admin user (password: password123)
INSERT INTO users (name, email, password, role, loyalty_points) VALUES
('Admin User', 'admin@gym.com', '$2y$10$YourHashedPasswordHere', 'admin', 0);

-- Note: The password hash above is a placeholder. In a real system, use password_hash('password123', PASSWORD_DEFAULT)
-- For testing, you can insert a real hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

-- ============================================
-- Sample member data (optional - for testing)
-- ============================================
-- Uncomment if you want test members
/*
INSERT INTO users (name, email, password, phone, role, loyalty_points) VALUES
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+60123456789', 'member', 100),
('Jane Smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+60198765432', 'member', 50);

-- Sample subscription for John Doe
INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, status) VALUES
(2, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active');

-- Sample payment for John Doe
INSERT INTO payments (user_id, amount, payment_method) VALUES
(2, 50.00, 'Online Banking');
*/

-- ============================================
-- Display table information (for verification)
-- ============================================
SELECT 'Database schema created successfully!' AS Message;
SELECT COUNT(*) AS 'Plans Count' FROM membership_plans;
SELECT COUNT(*) AS 'Users Count' FROM users;
