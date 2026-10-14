-- Smart Driver Database Schema
-- Database: smart_driver_db

CREATE DATABASE IF NOT EXISTS smart_driver_db;
USE smart_driver_db;

-- --------------------------------------------------------
-- Table: users (Drivers)
-- --------------------------------------------------------
CREATE TABLE users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    driver_license VARCHAR(50) NOT NULL UNIQUE,
    vehicle_plate VARCHAR(20),
    city VARCHAR(100),
    driver_score INT(11) DEFAULT 100,
    status ENUM('active', 'blocked', 'pending') DEFAULT 'pending',
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- Table: admins
-- --------------------------------------------------------
CREATE TABLE admins (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    last_login DATETIME,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- Table: traffic_rules (Violation Types)
-- --------------------------------------------------------
CREATE TABLE traffic_rules (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    description TEXT,
    penalty_amount DECIMAL(10,2) NOT NULL,
    points_deducted INT(11) NOT NULL,
    category ENUM('minor', 'medium', 'severe') DEFAULT 'minor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- Table: violations
-- --------------------------------------------------------
CREATE TABLE violations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    rule_id INT(11) NOT NULL,
    violation_date DATETIME NOT NULL,
    location VARCHAR(255),
    fine_amount DECIMAL(10,2) NOT NULL,
    points_deducted INT(11) NOT NULL,
    paid_status ENUM('unpaid', 'paid', 'overdue') DEFAULT 'unpaid',
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    image_evidence VARCHAR(255),
    description TEXT,
    reported_by ENUM('driver', 'police', 'system') DEFAULT 'driver',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rule_id) REFERENCES traffic_rules(id)
);

-- --------------------------------------------------------
-- Table: fuel_records
-- --------------------------------------------------------
CREATE TABLE fuel_records (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    fuel_amount DECIMAL(10,2) NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    distance_covered DECIMAL(10,2) NOT NULL,
    fuel_efficiency DECIMAL(10,2), -- km per liter
    fuel_type ENUM('petrol', 'diesel', 'electric') DEFAULT 'petrol',
    location VARCHAR(255),
    odometer_reading DECIMAL(10,2),
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: road_reports
-- --------------------------------------------------------
CREATE TABLE road_reports (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    report_type ENUM('construction', 'accident', 'traffic_jam', 'pothole', 'broken_traffic_light', 'other') NOT NULL,
    location VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    description TEXT,
    image_path VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT(11),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- Table: notifications
-- --------------------------------------------------------
CREATE TABLE notifications (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('violation', 'fuel', 'road_report', 'system', 'reward') DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: driver_rewards (Gamification)
-- --------------------------------------------------------
CREATE TABLE driver_rewards (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    badge_name VARCHAR(100) NOT NULL,
    badge_icon VARCHAR(50),
    description TEXT,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: activity_logs
-- --------------------------------------------------------
CREATE TABLE activity_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    admin_id INT(11),
    user_id INT(11),
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- Table: system_settings
-- --------------------------------------------------------
CREATE TABLE system_settings (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- Insert Default Data
-- --------------------------------------------------------

-- Insert Default Admin
INSERT INTO admins (username, email, password_hash, full_name, role, status) VALUES
('admin', 'admin@smartdriver.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'super_admin', 'active');
-- Password: password123

-- Insert Traffic Rules
INSERT INTO traffic_rules (rule_name, description, penalty_amount, points_deducted, category) VALUES
('Speeding (10-20 km/h over)', 'Exceeding speed limit by 10-20 km/h', 200.00, 5, 'minor'),
('Speeding (20-40 km/h over)', 'Exceeding speed limit by 20-40 km/h', 500.00, 10, 'medium'),
('Running Red Light', 'Passing through a red traffic signal', 600.00, 15, 'medium'),
('Driving Without License', 'Operating vehicle without valid driver\'s license', 1000.00, 25, 'severe'),
('Driving Under Influence', 'Operating vehicle while under influence of alcohol/drugs', 2000.00, 30, 'severe'),
('Wrong Lane Driving', 'Driving in the wrong lane or illegal overtaking', 400.00, 10, 'medium'),
('No Seatbelt', 'Driving without wearing a seatbelt', 150.00, 5, 'minor'),
('Using Phone While Driving', 'Using mobile phone while operating vehicle', 300.00, 8, 'medium'),
('Illegal Parking', 'Parking in restricted/no-parking zone', 250.00, 5, 'minor'),
('Not Using Turn Signal', 'Failing to indicate turns or lane changes', 200.00, 5, 'minor'),
('Overloading', 'Carrying more passengers or weight than allowed', 500.00, 10, 'medium'),
('Driving Without Insurance', 'Operating vehicle without valid insurance', 800.00, 15, 'severe');

-- Insert System Settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('fuel_price', '120.00', 'Current fuel price per liter'),
('default_penalty_period', '30', 'Days to pay penalty before overdue'),
('max_driver_score', '100', 'Maximum driver score'),
('min_driver_score', '0', 'Minimum driver score'),
('weekly_bonus_points', '2', 'Points added weekly for no violations'),
('notification_enabled', '1', 'Enable/disable notifications');

-- Insert Sample Driver (for testing)
INSERT INTO users (full_name, email, password_hash, phone, driver_license, vehicle_plate, city, driver_score, status) VALUES
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+251911234567', 'DL123456', 'AA1234', 'Addis Ababa', 100, 'active');
-- Password: password123