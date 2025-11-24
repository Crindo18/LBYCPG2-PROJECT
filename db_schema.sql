-- Database Schema for Academic Record and Advising System

CREATE DATABASE IF NOT EXISTS academic_advising;
USE academic_advising;

-- All User Login Information
CREATE TABLE user_login_info (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_number VARCHAR(10) UNIQUE,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('admin', 'professor', 'student') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin table
CREATE TABLE admin (
    id INT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    department VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id) REFERENCES user_login_info(id) ON DELETE CASCADE
);

-- Professors table
CREATE TABLE professors (
    id INT PRIMARY KEY,
    id_number INT UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    must_change_password BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id) REFERENCES user_login_info(id) ON DELETE CASCADE
);

-- Students table
CREATE TABLE students (
    id INT PRIMARY KEY,
    id_number INT UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    college VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    program VARCHAR(100) NOT NULL,
    specialization VARCHAR(100),
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    parent_guardian_name VARCHAR(200) NOT NULL,
    parent_guardian_number VARCHAR(20) NOT NULL,
    advisor_id INT,
    advising_cleared BOOLEAN DEFAULT FALSE,
    must_change_password BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id) REFERENCES user_login_info(id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES professors(id) ON DELETE SET NULL
);

-- Program profiles (checklist templates)
CREATE TABLE program_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    program_name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100),
    total_units INT NOT NULL,
    description TEXT,
    department VARCHAR(100) NOT NULL,
    checklist_file VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Student academic records (for booklet)
CREATE TABLE student_advising_booklet (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    term INT NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    course_name VARCHAR(200),
    units INT NOT NULL,
    grade DECIMAL(3,2),
    term_gpa DECIMAL(3,2),
    cgpa DECIMAL(3,2),
    accumulated_failure INT DEFAULT 0,
    trimestral_honors VARCHAR(100),
    adviser_note TEXT,
    adviser_signature VARCHAR(255),
    signature_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Study plans
CREATE TABLE study_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    term VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    certified BOOLEAN DEFAULT FALSE,
    wants_meeting BOOLEAN DEFAULT FALSE,
    selected_schedule_id INT,
    cleared BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Current term subjects
CREATE TABLE current_subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    study_plan_id INT NOT NULL,
    subject_code VARCHAR(20) NOT NULL,
    subject_name VARCHAR(200),
    units INT NOT NULL,
    FOREIGN KEY (study_plan_id) REFERENCES study_plans(id) ON DELETE CASCADE
);

-- Prerequisites for current subjects
CREATE TABLE current_subject_prerequisites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    current_subject_id INT NOT NULL,
    prerequisite_code VARCHAR(20) NOT NULL,
    prerequisite_type ENUM('hard', 'soft', 'co-requisite') NOT NULL,
    FOREIGN KEY (current_subject_id) REFERENCES current_subjects(id) ON DELETE CASCADE
);

-- Planned subjects for next term
CREATE TABLE planned_subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    study_plan_id INT NOT NULL,
    subject_code VARCHAR(20) NOT NULL,
    subject_name VARCHAR(200),
    units INT NOT NULL,
    FOREIGN KEY (study_plan_id) REFERENCES study_plans(id) ON DELETE CASCADE
);

-- Prerequisites for planned subjects
CREATE TABLE planned_subject_prerequisites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    planned_subject_id INT NOT NULL,
    prerequisite_code VARCHAR(20) NOT NULL,
    prerequisite_type ENUM('hard', 'soft', 'co-requisite') NOT NULL,
    FOREIGN KEY (planned_subject_id) REFERENCES planned_subjects(id) ON DELETE CASCADE
);

-- Student concerns
CREATE TABLE student_concerns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    term VARCHAR(50) NOT NULL,
    concern TEXT NOT NULL,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- Professor advising schedules
CREATE TABLE advising_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    professor_id INT NOT NULL,
    available_date DATE NOT NULL,
    available_time TIME NOT NULL,
    is_booked BOOLEAN DEFAULT FALSE,
    booked_by INT,
    FOREIGN KEY (professor_id) REFERENCES professors(id) ON DELETE CASCADE,
    FOREIGN KEY (booked_by) REFERENCES students(id) ON DELETE SET NULL
);

-- Advising deadlines
CREATE TABLE advising_deadlines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    professor_id INT NOT NULL,
    deadline_date DATE NOT NULL,
    term VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES professors(id) ON DELETE CASCADE
);

-- Insert default admin (username: admin, password: password)
INSERT INTO user_login_info (id_number, username, password, user_type) 
VALUES (NULL, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO admin (id, username, department) 
VALUES (LAST_INSERT_ID(), 'admin', 'Administration');

-- Sample student data
INSERT INTO user_login_info (id_number, username, password, user_type) 
VALUES ('12012345', '12012345', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

INSERT INTO students (id, id_number, first_name, middle_name, last_name, college, department, program, specialization, phone_number, email, parent_guardian_name, parent_guardian_number) 
VALUES (LAST_INSERT_ID(), '12012345', 'Juan', 'Santos', 'Dela Cruz', 'Gokongwei College of Engineering', 'Department of Electronics, Computer, and Electrical Engineering', 'BS Computer Engineering', 'N/A', '+63 917 123 4567', 'juan_delacruz@dlsu.edu.ph', 'Maria Dela Cruz', '+63 918 765 4321');