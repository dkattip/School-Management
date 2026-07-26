-- School Management System Database
CREATE DATABASE IF NOT EXISTS school_management CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE school_management;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- School Settings
CREATE TABLE IF NOT EXISTS school_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_name VARCHAR(255) DEFAULT 'My School',
    school_address TEXT,
    school_phone VARCHAR(20),
    school_email VARCHAR(100),
    school_logo VARCHAR(255),
    school_board VARCHAR(50) DEFAULT 'CBSE',
    academic_year VARCHAR(20) DEFAULT '2025-26',
    school_motto VARCHAR(255),
    max_exam_duration INT DEFAULT 120,
    seb_enabled TINYINT(1) DEFAULT 1,
    webcam_required TINYINT(1) DEFAULT 1,
    theme_color VARCHAR(7) DEFAULT '#4F46E5',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO school_settings (school_name, school_address) VALUES ('Demo School', '123 Education Street, Bangalore, Karnataka');

-- Users
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin','teacher','student') NOT NULL,
    phone VARCHAR(20),
    profile_photo VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Classes
CREATE TABLE IF NOT EXISTS classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_name VARCHAR(50) NOT NULL,
    section VARCHAR(10),
    board VARCHAR(50) DEFAULT 'CBSE',
    class_teacher_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Subjects (CBSE + Karnataka State)
CREATE TABLE IF NOT EXISTS subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20),
    board VARCHAR(50) DEFAULT 'CBSE',
    class_id INT,
    teacher_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Teacher-Subject-Class Mapping (RBAC)
CREATE TABLE IF NOT EXISTS teacher_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    class_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (teacher_id, subject_id, class_id)
) ENGINE=InnoDB;

-- Student-Class Mapping
CREATE TABLE IF NOT EXISTS student_classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    roll_number VARCHAR(20),
    admission_number VARCHAR(50),
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_class (student_id, class_id)
) ENGINE=InnoDB;

-- Exams
CREATE TABLE IF NOT EXISTS exams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_name VARCHAR(100) NOT NULL,
    description TEXT,
    subject_id INT NOT NULL,
    class_id INT NOT NULL,
    created_by INT NOT NULL,
    exam_type ENUM('midterm','final','quiz','assignment','mock') DEFAULT 'quiz',
    assign_mode ENUM('class','selected') DEFAULT 'class',
    total_marks INT DEFAULT 100,
    passing_marks INT DEFAULT 33,
    duration_minutes INT DEFAULT 60,
    start_time DATETIME,
    end_time DATETIME,
    is_active TINYINT(1) DEFAULT 1,
    seb_enabled TINYINT(1) DEFAULT 1,
    webcam_required TINYINT(1) DEFAULT 1,
    shuffle_questions TINYINT(1) DEFAULT 1,
    show_results TINYINT(1) DEFAULT 1,
    max_attempts INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Exam-Student Assignments (for per-student assignment mode)
CREATE TABLE IF NOT EXISTS exam_student_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_exam_student (exam_id, student_id)
) ENGINE=InnoDB;

-- Questions (updated with correct_answer + option_e)
CREATE TABLE IF NOT EXISTS questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('mcq','short_answer','long_answer','true_false','numerical') DEFAULT 'mcq',
    option_a TEXT,
    option_b TEXT,
    option_c TEXT,
    option_d TEXT,
    option_e TEXT,
    correct_answer VARCHAR(10),
    marks DECIMAL(5,2) DEFAULT 1,
    question_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Exam Attempts (updated with tracking columns)
CREATE TABLE IF NOT EXISTS exam_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    start_time DATETIME,
    end_time DATETIME,
    total_score DECIMAL(5,2) DEFAULT 0,
    max_score DECIMAL(5,2) DEFAULT 0,
    percentage DECIMAL(5,2) DEFAULT 0,
    status ENUM('in_progress','completed','timed_out','cancelled') DEFAULT 'in_progress',
    ip_address VARCHAR(45),
    tab_switch_count INT DEFAULT 0,
    tab_switches INT DEFAULT 0,
    submission_reason VARCHAR(50) DEFAULT 'manual',
    last_activity TIMESTAMP NULL,
    submitted_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Student Answers (updated with selected_option + marked_for_review)
CREATE TABLE IF NOT EXISTS student_answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    student_answer TEXT,
    selected_option VARCHAR(10),
    is_correct TINYINT(1) DEFAULT NULL,
    marks_obtained DECIMAL(5,2) DEFAULT 0,
    marked_for_review TINYINT(1) DEFAULT 0,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Exam Violations (SEB monitoring)
CREATE TABLE IF NOT EXISTS exam_violations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT NOT NULL,
    student_id INT NOT NULL,
    reason VARCHAR(255),
    switch_count INT DEFAULT 0,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Webcam Logs
CREATE TABLE IF NOT EXISTS webcam_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT NOT NULL,
    student_id INT NOT NULL,
    image_path VARCHAR(255),
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Import Log
CREATE TABLE IF NOT EXISTS import_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    imported_by INT NOT NULL,
    import_type ENUM('file','paste','manual') DEFAULT 'paste',
    questions_count INT DEFAULT 0,
    import_data TEXT,
    status ENUM('success','failed','partial') DEFAULT 'success',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- DEMO DATA
-- =============================================

-- Admin (password: password)
INSERT INTO users (username, email, password, full_name, role) VALUES
('admin', 'admin@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');

-- Teachers (password: password)
INSERT INTO users (username, email, password, full_name, role) VALUES
('teacher_math', 'math@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Priya Sharma', 'teacher'),
('teacher_phy', 'phy@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Prof. Rajesh Kumar', 'teacher'),
('teacher_chem', 'chem@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mrs. Lakshmi Devi', 'teacher'),
('teacher_bio', 'bio@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mr. Suresh Patil', 'teacher'),
('teacher_eng', 'eng@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ms. Anitha Rao', 'teacher'),
('teacher_hindi', 'hindi@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mr. Venkatesh Gowda', 'teacher'),
('teacher_sci', 'sci@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Meena Kulkarni', 'teacher'),
('teacher_sst', 'sst@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mr. Ravi Shankar', 'teacher'),
('teacher_cs', 'cs@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mrs. Deepa Nair', 'teacher');

-- Students (password: password)
INSERT INTO users (username, email, password, full_name, role) VALUES
('student1', 'student1@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Aarav Kumar', 'student'),
('student2', 'student2@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Diya Patel', 'student'),
('student3', 'student3@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ishaan Singh', 'student'),
('student4', 'student4@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ananya Reddy', 'student'),
('student5', 'student5@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Vivaan Sharma', 'student');

-- Classes
INSERT INTO classes (class_name, section, board, class_teacher_id) VALUES
('Class 10', 'A', 'CBSE', 2),
('Class 10', 'B', 'CBSE', 3),
('Class 10', 'A', 'STATE', 4),
('Class 12', 'A', 'CBSE', 5),
('Class 12', 'B', 'CBSE', 6);

-- CBSE Class 10 Subjects
INSERT INTO subjects (subject_name, subject_code, board, class_id, teacher_id) VALUES
('Mathematics', 'MATH10', 'CBSE', 1, 2),
('Science', 'SCI10', 'CBSE', 1, 7),
('English Language', 'ENG10', 'CBSE', 1, 5),
('Hindi', 'HIN10', 'CBSE', 1, 6),
('Social Science', 'SST10', 'CBSE', 1, 8),
('Information Technology', 'IT10', 'CBSE', 1, 9),
('Mathematics', 'MATH10B', 'CBSE', 2, 2),
('Science', 'SCI10B', 'CBSE', 2, 7),
('English Language', 'ENG10B', 'CBSE', 2, 5),
('Hindi', 'HIN10B', 'CBSE', 2, 6),
('Social Science', 'SST10B', 'CBSE', 2, 8);

-- Karnataka State Class 10 Subjects
INSERT INTO subjects (subject_name, subject_code, board, class_id, teacher_id) VALUES
('Mathematics', 'KAR_MATH10', 'STATE', 3, 2),
('Science', 'KAR_SCI10', 'STATE', 3, 7),
('English', 'KAR_ENG10', 'STATE', 3, 5),
('Kannada', 'KAR_KAN10', 'STATE', 3, 6),
('Social Science', 'KAR_SST10', 'STATE', 3, 8);

-- CBSE Class 12 Subjects (PCMB)
INSERT INTO subjects (subject_name, subject_code, board, class_id, teacher_id) VALUES
('Physics', 'PHY12', 'CBSE', 4, 3),
('Chemistry', 'CHEM12', 'CBSE', 4, 4),
('Mathematics', 'MATH12', 'CBSE', 4, 2),
('Biology', 'BIO12', 'CBSE', 4, 7),
('English Core', 'ENG12', 'CBSE', 4, 5),
('Physics', 'PHY12B', 'CBSE', 5, 3),
('Chemistry', 'CHEM12B', 'CBSE', 5, 4),
('Mathematics', 'MATH12B', 'CBSE', 5, 2),
('Biology', 'BIO12B', 'CBSE', 5, 7),
('Computer Science', 'CS12B', 'CBSE', 5, 9);

-- Teacher Assignments (RBAC)
INSERT INTO teacher_assignments (teacher_id, subject_id, class_id) VALUES
(2, 1, 1), (7, 2, 1), (5, 3, 1), (6, 4, 1), (8, 5, 1), (9, 6, 1),
(2, 7, 2), (7, 8, 2), (5, 9, 2), (6, 10, 2), (8, 11, 2),
(2, 12, 3), (7, 13, 3), (5, 14, 3), (6, 15, 3), (8, 16, 3),
(3, 17, 4), (4, 18, 4), (2, 19, 4), (7, 20, 4), (5, 21, 4),
(3, 22, 5), (4, 23, 5), (2, 24, 5), (7, 25, 5), (9, 26, 5);

-- Student Classes
INSERT INTO student_classes (student_id, class_id, roll_number, admission_number) VALUES
(11, 1, '001', 'ADM2025001'),
(12, 1, '002', 'ADM2025002'),
(13, 1, '003', 'ADM2025003'),
(14, 4, '001', 'ADM2025004'),
(15, 4, '002', 'ADM2025005');

-- Sample Exams
INSERT INTO exams (exam_name, description, subject_id, class_id, created_by, exam_type, total_marks, passing_marks, duration_minutes, start_time, end_time, is_active) VALUES
('Mid-Term Mathematics Class 10', 'Mid-term examination for Mathematics', 1, 1, 2, 'midterm', 50, 17, 60, '2025-08-01 09:00:00', '2026-12-31 23:59:59', 1),
('Physics Unit Test - Class 12', 'Unit test on Mechanics and Thermodynamics', 17, 4, 3, 'quiz', 30, 10, 45, '2025-08-05 10:00:00', '2026-12-31 23:59:59', 1);

-- Sample Questions - Mathematics Exam (using correct_answer column)
INSERT INTO questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, marks, question_order) VALUES
(1, 'What is the value of √144?', 'mcq', '11', '12', '13', '14', 'B', 2, 1),
(1, 'If x + 5 = 12, what is the value of x?', 'mcq', '5', '6', '7', '8', 'C', 2, 2),
(1, 'What is the area of a circle with radius 7 cm? (Use π = 22/7)', 'mcq', '144 cm²', '154 cm²', '164 cm²', '174 cm²', 'B', 3, 3),
(1, 'The HCF of 12 and 18 is:', 'mcq', '3', '6', '12', '18', 'B', 2, 4),
(1, 'Solve: 2x² - 5x + 3 = 0', 'mcq', 'x = 1, x = 3/2', 'x = -1, x = 3/2', 'x = 1, x = -3/2', 'x = -1, x = -3/2', 'A', 3, 5),
(1, 'What is the value of sin 30°?', 'mcq', '1/2', '√3/2', '1/√2', '1', 'A', 2, 6),
(1, 'The sum of angles in a triangle is:', 'mcq', '90°', '180°', '270°', '360°', 'B', 1, 7),
(1, 'What is the formula for the volume of a sphere?', 'mcq', '4/3 πr²', '4/3 πr³', 'πr²h', '2πrh', 'B', 2, 8),
(1, 'If tan θ = 3/4, find sin θ:', 'mcq', '3/5', '4/5', '3/4', '5/3', 'A', 3, 9),
(1, 'The quadratic formula is used to solve:', 'mcq', 'Linear equations', 'Quadratic equations', 'Cubic equations', 'Simultaneous equations', 'B', 1, 10);

-- Sample Questions - Physics Exam
INSERT INTO questions (exam_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, marks, question_order) VALUES
(2, 'What is the SI unit of force?', 'mcq', 'Joule', 'Newton', 'Pascal', 'Watt', 'B', 2, 1),
(2, 'Newton''s first law is also known as:', 'mcq', 'Law of acceleration', 'Law of inertia', 'Law of reaction', 'Law of gravitation', 'B', 2, 2),
(2, 'The dimensional formula of force is:', 'mcq', '[MLT⁻²]', '[ML²T⁻²]', '[MLT⁻¹]', '[ML²T⁻³]', 'A', 3, 3),
(2, 'Work done by a force is zero when:', 'mcq', 'Force is maximum', 'Displacement is zero', 'Angle is 45°', 'Velocity is constant', 'B', 3, 4),
(2, 'The kinetic energy of a body of mass 2kg moving with velocity 4m/s is:', 'mcq', '8J', '16J', '32J', '64J', 'B', 3, 5),
(2, 'According to Newton''s third law, every action has:', 'mcq', 'Same direction reaction', 'No reaction', 'Equal and opposite reaction', 'Double reaction', 'C', 2, 6),
(2, 'The acceleration due to gravity on the moon is approximately:', 'mcq', '9.8 m/s²', '4.9 m/s²', '1.6 m/s²', '6.2 m/s²', 'C', 3, 7),
(2, 'Moment of inertia depends on:', 'mcq', 'Only mass', 'Only distance from axis', 'Both mass and distribution of mass', 'Neither mass nor distance', 'C', 3, 8),
(2, 'The escape velocity from Earth is approximately:', 'mcq', '7.9 km/s', '11.2 km/s', '15.4 km/s', '3.2 km/s', 'B', 3, 9),
(2, 'In an elastic collision, which quantity is conserved?', 'mcq', 'Only momentum', 'Only kinetic energy', 'Both momentum and kinetic energy', 'Neither', 'C', 3, 10);
