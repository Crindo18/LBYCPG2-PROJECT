-- Updated Database Schema for Academic Record and Advising System
-- Includes: Course Catalog, Email Templates, Grade Screenshots, Failed Units Tracking

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
    email VARCHAR(100),
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
    email VARCHAR(100) NOT NULL,
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
    accumulated_failed_units INT DEFAULT 0,
    must_change_password BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id) REFERENCES user_login_info(id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES professors(id) ON DELETE SET NULL
);

-- NEW: Course Catalog (Program-specific courses with 12-term system)
CREATE TABLE course_catalog (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(200) NOT NULL,
    units INT NOT NULL,
    program VARCHAR(100) NOT NULL,
    term VARCHAR(20) NOT NULL,
    course_type ENUM('major', 'minor', 'elective', 'general_education') DEFAULT 'major',
    prerequisites TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_program (program),
    INDEX idx_term (term)
);

-- NEW: Course Prerequisites
CREATE TABLE course_prerequisites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    prerequisite_course_code VARCHAR(20) NOT NULL,
    prerequisite_type ENUM('hard', 'soft', 'co-requisite') NOT NULL,
    FOREIGN KEY (course_id) REFERENCES course_catalog(id) ON DELETE CASCADE
);

-- Program profiles (Updated - removed since hardcoding 3 programs)
CREATE TABLE program_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    program_name VARCHAR(100) NOT NULL UNIQUE,
    program_code VARCHAR(20) NOT NULL UNIQUE,
    total_units INT NOT NULL,
    description TEXT,
    department VARCHAR(100) NOT NULL DEFAULT 'The Department of Electronics, Computer, and Electrical Engineering (DECE)',
    max_failed_units INT DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Student academic records (for booklet) - UPDATED
CREATE TABLE student_advising_booklet (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    term INT NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    course_name VARCHAR(200),
    units INT NOT NULL,
    grade DECIMAL(3,2),
    is_failed BOOLEAN DEFAULT FALSE,
    remarks VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_student_year (student_id, academic_year, term)
);

-- NEW: Term GPA Summary
CREATE TABLE term_gpa_summary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    term INT NOT NULL,
    term_gpa DECIMAL(3,2),
    cgpa DECIMAL(3,2),
    total_units_taken INT DEFAULT 0,
    total_units_passed INT DEFAULT 0,
    total_units_failed INT DEFAULT 0,
    accumulated_failed_units INT DEFAULT 0,
    trimestral_honors VARCHAR(50),
    adviser_signature VARCHAR(255),
    signature_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_term (student_id, academic_year, term)
);

-- Study plans - UPDATED
CREATE TABLE study_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    term VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    grade_screenshot VARCHAR(255),
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    certified BOOLEAN DEFAULT FALSE,
    wants_meeting BOOLEAN DEFAULT FALSE,
    selected_schedule_id INT,
    cleared BOOLEAN DEFAULT FALSE,
    adviser_feedback TEXT,
    screenshot_reupload_requested BOOLEAN DEFAULT FALSE,
    reupload_reason TEXT,
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
    study_plan_id INT,
    term VARCHAR(50) NOT NULL,
    concern TEXT NOT NULL,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (study_plan_id) REFERENCES study_plans(id) ON DELETE SET NULL
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

-- NEW: Email Templates for Advisers
CREATE TABLE email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    professor_id INT NOT NULL,
    template_name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (professor_id) REFERENCES professors(id) ON DELETE CASCADE
);

-- NEW: Email Queue
CREATE TABLE email_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    from_professor_id INT NOT NULL,
    to_student_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    send_immediately BOOLEAN DEFAULT TRUE,
    scheduled_send_time TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_professor_id) REFERENCES professors(id) ON DELETE CASCADE,
    FOREIGN KEY (to_student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_send_time)
);

-- NEW: Bulk Upload History (for tracking CSV uploads)
CREATE TABLE bulk_upload_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    uploaded_by INT NOT NULL,
    upload_type ENUM('students', 'professors', 'courses') NOT NULL,
    filename VARCHAR(255) NOT NULL,
    total_records INT DEFAULT 0,
    successful_records INT DEFAULT 0,
    failed_records INT DEFAULT 0,
    error_log TEXT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES admin(id) ON DELETE CASCADE
);

-- Insert default admin (username: admin, password: password)
INSERT INTO user_login_info (id_number, username, password, user_type) 
VALUES (NULL, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO admin (id, username, department, email) 
VALUES (LAST_INSERT_ID(), 'admin', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 'admin@dlsu.edu.ph');

-- Insert the 3 program profiles
INSERT INTO program_profiles (program_name, program_code, total_units, description, department) VALUES
('BS Computer Engineering', 'BSCpE', 180, 'Bachelor of Science in Computer Engineering', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)'),
('BS Electronics and Communications Engineering', 'BSECE', 180, 'Bachelor of Science in Electronics and Communications Engineering', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)'),
('BS Electrical Engineering', 'BSEE', 180, 'Bachelor of Science in Electrical Engineering', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)');

-- Sample student data
INSERT INTO user_login_info (id_number, username, password, user_type) 
VALUES ('12012345', '12012345', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

INSERT INTO students (id, id_number, first_name, middle_name, last_name, college, department, program, specialization, phone_number, email, parent_guardian_name, parent_guardian_number) 
VALUES (LAST_INSERT_ID(), '12012345', 'Juan', 'Santos', 'Dela Cruz', 'Gokongwei College of Engineering', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 'BS Computer Engineering', 'N/A', '+63 917 123 4567', 'juan_delacruz@dlsu.edu.ph', 'Maria Dela Cruz', '+63 918 765 4321');

-- Sample professor data
INSERT INTO user_login_info (id_number, username, password, user_type) 
VALUES ('10012345', '10012345', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'professor');

INSERT INTO professors (id, id_number, first_name, middle_name, last_name, department, email) 
VALUES (LAST_INSERT_ID(), '10012345', 'Maria', 'Santos', 'Garcia', 'The Department of Electronics, Computer, and Electrical Engineering (DECE)', 'maria.garcia@dlsu.edu.ph');

-- Sample courses for BSCpE (Computer Engineering)
INSERT INTO course_catalog (course_code, course_name, units, program, term, course_type, prerequisites) VALUES
-- Term 1
('FNDMATH', 'Fundamental Mathematics', 3, 'BS Computer Engineering', 'Term 1', 'major', ''),
('PROLOGI', 'Programming Logic', 3, 'BS Computer Engineering', 'Term 1', 'major', ''),
('LBYCPA1', 'Computer Programming Lab 1', 1, 'BS Computer Engineering', 'Term 1', 'major', 'PROLOGI(C)'),
-- Term 2
('CALENG1', 'Calculus for Engineers 1', 3, 'BS Computer Engineering', 'Term 2', 'major', 'FNDMATH(H)'),
('CSSWENG', 'Software Engineering', 3, 'BS Computer Engineering', 'Term 2', 'major', ''),
('CSALGCM', 'Design and Analysis of Algorithms', 3, 'BS Computer Engineering', 'Term 2', 'major', 'PROLOGI(H)'),
-- Term 3
('CSMCPRO', 'Microprocessors', 3, 'BS Computer Engineering', 'Term 3', 'major', ''),
('CSNETWK', 'Computer Networks', 3, 'BS Computer Engineering', 'Term 3', 'major', ''),
('CSARCH2', 'Computer Architecture 2', 3, 'BS Computer Engineering', 'Term 3', 'major', ''),
-- Term 10 (example advanced course)
('REMETHS', 'Methods of Research for CpE', 3, 'BS Computer Engineering', 'Term 10', 'major', 'ENGDATA(H),GEPCOMM(H),LOGDSGN(H)'),
-- Term 11
('DSIGPRO', 'Digital Signal Processing Lecture', 3, 'BS Computer Engineering', 'Term 11', 'major', 'FDCNSYS(H),EMBDSYS(S)'),
-- Term 12
('CSINPRO', 'Internship Program', 3, 'BS Computer Engineering', 'Term 12', 'major', '');