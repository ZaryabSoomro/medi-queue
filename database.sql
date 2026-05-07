-- ============================================
-- Hospital Queue Management System - Database
-- ============================================

CREATE DATABASE IF NOT EXISTS queue_system;
USE queue_system;

-- Users table (patients + admins)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('patient', 'admin') DEFAULT 'patient',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctors table
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    specialty VARCHAR(100) NOT NULL,
    room VARCHAR(20) NOT NULL,
    available TINYINT(1) DEFAULT 1,
    avg_time_minutes INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Queue tokens table
CREATE TABLE tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_number VARCHAR(10) NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    status ENUM('waiting', 'in_progress', 'completed', 'cancelled', 'emergency') DEFAULT 'waiting',
    priority INT DEFAULT 0,
    notes TEXT,
    queue_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    called_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (patient_id) REFERENCES users(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    token_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id),
    FOREIGN KEY (token_id) REFERENCES tokens(id)
);

-- ============================================
-- Sample Data
-- ============================================

INSERT INTO users (name, email, phone, password, role) VALUES
('Admin Staff', 'admin@clinic.com', '03001234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Ahmed Khan', 'ahmed@gmail.com', '03111234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient'),
('Sara Ali', 'sara@gmail.com', '03221234567', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient');

-- Note: default password for all is "password"

INSERT INTO doctors (name, specialty, room, avg_time_minutes) VALUES
('Dr. Ayesha Siddiqui', 'General Physician', 'Room 101', 8),
('Dr. Usman Malik', 'Cardiologist', 'Room 205', 15),
('Dr. Fatima Noor', 'Dermatologist', 'Room 108', 10),
('Dr. Bilal Ahmed', 'Orthopedic', 'Room 312', 20),
('Dr. Zara Hassan', 'Gynecologist', 'Room 115', 12);
