-- ============================================
-- Smart Queue Management System
-- Sprint 1: Login (Student ID + Parent Mobile) & Basic User Management
-- ============================================

CREATE DATABASE IF NOT EXISTS queue_system;
USE queue_system;

-- Users table
-- Login credentials are simply: student_id + parent_mobile (both must match)
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    student_id VARCHAR(30) NOT NULL UNIQUE,
    parent_mobile VARCHAR(15) NOT NULL,
    role ENUM('student', 'staff', 'admin') NOT NULL DEFAULT 'student',
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed one admin account so the system is usable on first run
-- Login with Student ID: ADMIN001   Parent Mobile: 9999999999
INSERT INTO users (full_name, student_id, parent_mobile, role, status)
VALUES ('System Admin', 'ADMIN001', '9999999999', 'admin', 'active');
