-- ─────────────────────────────────────────────────────────────
-- Scheduling System — Database Schema
-- Import this into your Aiven MySQL database via the Aiven
-- web console (Query Editor) or via mysql CLI:
--   mysql -h HOST -P PORT -u USER -p --ssl-mode=REQUIRED DB < schema.sql
-- ─────────────────────────────────────────────────────────────
-- NOTE: On Aiven, the database is pre-created (default: 'defaultdb').
-- If using a local MySQL for dev, run:
--   CREATE DATABASE IF NOT EXISTS sti_classroom_system;
--   USE sti_classroom_system;
-- ─────────────────────────────────────────────────────────────


CREATE TABLE classrooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    room_name_number VARCHAR(50) NOT NULL,
    room_type ENUM('Lecture', 'Lab', 'Kitchen', 'Bar') NOT NULL,
    capacity INT NOT NULL,
    floor_level INT NOT NULL,
    current_status ENUM('Vacant', 'Occupied', 'Reserved') DEFAULT 'Vacant'
);

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'academic-head', 'program-head', 'faculty') NOT NULL,
    subject_role VARCHAR(50) DEFAULT 'IT',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    faculty_id INT NOT NULL,
    subject_code VARCHAR(20) NOT NULL,
    subject_category VARCHAR(50) DEFAULT 'IT',
    section_name VARCHAR(20) NOT NULL,
    day_of_week ENUM('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (room_id) REFERENCES classrooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE room_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    faculty_id INT NOT NULL,
    request_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    purpose TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Declined') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES classrooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE session_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    faculty_id INT NOT NULL,
    session_date DATE NOT NULL,
    actual_start_time DATETIME NOT NULL,
    actual_end_time DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES classrooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Seed Data: Classrooms
INSERT INTO classrooms (room_name_number, room_type, capacity, floor_level) VALUES
('Lab 1', 'Lab', 35, 1),
('Lab 2', 'Lab', 35, 1),
('Lab 3', 'Lab', 35, 1),
('Lab 4', 'Lab', 35, 1),
('Kitchen', 'Kitchen', 20, 1),
('Bar', 'Bar', 25, 1),
('Room 201', 'Lecture', 45, 2),
('Room 202', 'Lecture', 45, 2),
('Room 203', 'Lecture', 40, 2),
('Room 204', 'Lecture', 40, 2),
('Room 205', 'Lecture', 50, 2),
('Room 301', 'Lecture', 45, 3),
('Room 302', 'Lecture', 45, 3),
('Room 303', 'Lecture', 40, 3),
('Room 304', 'Lecture', 40, 3),
('Room 305', 'Lecture', 45, 3),
('Room 306', 'Lecture', 45, 3),
('Room 307', 'Lecture', 40, 3),
('Room 308', 'Lecture', 40, 3),
('Room 309', 'Lecture', 50, 3),
('Room 310', 'Lecture', 50, 3),
('Tourism Lab', 'Lab', 30, 3);

-- Seed Data: Users
-- Note: 'password' is used as a placeholder hash ($2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi)
-- PLEASE REGENERATE HASHES in production!
INSERT INTO users (first_name, last_name, email, password_hash, role, subject_role) VALUES
('System', 'Administrator', 'admin@sti.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL),
('Academic', 'Head', 'acah@sti.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'IT'),
('Program', 'Head', 'proh@sti.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'IT');

-- Seed Data: Schedules
INSERT INTO schedules (room_id, faculty_id, subject_code, subject_category, section_name, day_of_week, start_time, end_time) VALUES
(1, 2, 'IT101', 'IT', 'BSIT-1A', 'Mon', '08:00:00', '10:00:00'),
(7, 3, 'GE101', 'General Education', 'BSIT-1B', 'Tue', '10:30:00', '12:00:00'),
(5, 3, 'HM101', 'Hospitality', 'BSHM-1A', 'Wed', '13:00:00', '16:00:00'),
(22, 3, 'HM102', 'Hospitality', 'BSTM-1A', 'Thu', '09:00:00', '11:00:00'),
(2, 2, 'IT201', 'IT', 'BSIT-2A', 'Fri', '14:00:00', '16:00:00'),
(12, 3, 'GE102', 'General Education', 'BSIT-2B', 'Mon', '11:00:00', '13:00:00'),
(8, 2, 'IT301', 'IT', 'BSIT-3A', 'Tue', '07:30:00', '09:30:00'),
(6, 2, 'HM201', 'Hospitality', 'BSHM-2A', 'Wed', '16:00:00', '18:00:00'),
(3, 2, 'IT401', 'IT', 'BSIT-4A', 'Sat', '08:00:00', '12:00:00'),
(15, 3, 'GE201', 'General Education', 'BSIT-1A', 'Thu', '14:00:00', '15:30:00');

-- Seed Data: Room Requests
INSERT INTO room_requests (room_id, faculty_id, request_date, start_time, end_time, purpose, status) VALUES
(10, 2, '2026-09-15', '09:00:00', '11:00:00', 'Make-up class for IT101', 'Approved'),
(18, 3, '2026-09-16', '13:00:00', '15:00:00', 'Extra consultation hours', 'Pending'),
(4, 2, '2026-09-17', '10:00:00', '12:00:00', 'Special project presentation', 'Declined'),
(21, 3, '2026-09-18', '15:00:00', '17:00:00', 'Guest speaker seminar', 'Pending');

-- Seed Data: Session Logs
INSERT INTO session_logs (room_id, faculty_id, session_date, actual_start_time, actual_end_time) VALUES
(1, 2, '2026-09-10', '2026-09-10 08:02:00', '2026-09-10 10:05:00'),
(7, 3, '2026-09-10', '2026-09-10 10:25:00', '2026-09-10 12:00:00'),
(5, 3, '2026-09-10', '2026-09-10 13:05:00', NULL),
(22, 3, '2026-09-11', '2026-09-11 08:55:00', '2026-09-11 11:02:00');
