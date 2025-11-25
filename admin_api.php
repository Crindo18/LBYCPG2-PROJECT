<?php
require_once 'auth_check.php';
requireAdmin();

require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Prevent any output before JSON
ob_start();

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, log them instead
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    // Clear any output buffer
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Clear output buffer before processing
ob_clean();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'bulk_upload_students':
        bulkUploadStudents();
        break;
    case 'bulk_upload_professors':
        bulkUploadProfessors();
        break;
    case 'bulk_upload_courses':
        bulkUploadCourses();
        break;
    case 'get_dashboard_stats':
        getDashboardStats();
        break;
    case 'get_professors_list':
        getProfessorsList();
        break;
    case 'get_students_list':
        getStudentsList();
        break;
    case 'get_student_details':
        getStudentDetails();
        break;
    case 'get_professor_details':
        getProfessorDetails();
        break;
    case 'get_unassigned_students':
        getUnassignedStudents();
        break;
    case 'assign_student_to_adviser':
        assignStudentToAdviser();
        break;
    case 'get_adviser_students':
        getAdviserStudents();
        break;
    case 'remove_student_from_adviser':
        removeStudentFromAdviser();
        break;
    case 'add_single_student':
        addSingleStudent();
        break;
    case 'edit_student':
        editStudent();
        break;
    case 'add_single_professor':
        addSingleProfessor();
        break;
    case 'edit_professor':
        editProfessor();
        break;
    case 'delete_student':
        deleteStudent();
        break;
    case 'delete_professor':
        deleteProfessor();
        break;
    case 'get_course_catalog':
        getCourseCatalog();
        break;
    case 'add_course':
        addCourse();
        break;
    case 'update_course':
        updateCourse();
        break;
    case 'delete_course':
        deleteCourse();
        break;
    case 'get_user_password':
        getUserPassword();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// ============= BULK UPLOAD FUNCTIONS =============

function bulkUploadStudents() {
    global $conn;
    
    if (!isset($_FILES['csv_file'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        return;
    }
    
    $file = $_FILES['csv_file'];
    $filename = $file['name'];
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    
    if ($ext !== 'csv') {
        echo json_encode(['success' => false, 'message' => 'Please upload a CSV file']);
        return;
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        echo json_encode(['success' => false, 'message' => 'Could not read file']);
        return;
    }
    
    // Skip header row
    fgetcsv($handle);
    
    $total = 0;
    $success = 0;
    $failed = 0;
    $errors = [];
    
    while (($data = fgetcsv($handle)) !== false) {
        $total++;
        
        // Expected CSV format: id_number, first_name, middle_name, last_name, college, department, program, specialization, phone, email, guardian_name, guardian_phone
        if (count($data) < 12) {
            $failed++;
            $errors[] = "Row $total: Incomplete data";
            continue;
        }
        
        $id_number = trim($data[0]);
        $first_name = trim($data[1]);
        $middle_name = trim($data[2]);
        $last_name = trim($data[3]);
        $college = trim($data[4]);
        $department = trim($data[5]);
        $program = trim($data[6]);
        $specialization = trim($data[7]);
        $phone = trim($data[8]);
        $email = trim($data[9]);
        $guardian_name = trim($data[10]);
        $guardian_phone = trim($data[11]);
        
        // Generate default password (id_number)
        $password = password_hash($id_number, PASSWORD_DEFAULT);
        
        try {
            $conn->begin_transaction();
            
            // Insert into user_login_info
            $stmt = $conn->prepare("INSERT INTO user_login_info (id_number, username, password, user_type) VALUES (?, ?, ?, 'student')");
            $stmt->bind_param("sss", $id_number, $id_number, $password);
            $stmt->execute();
            $user_id = $conn->insert_id;
            
            // Insert into students
            $stmt = $conn->prepare("INSERT INTO students (id, id_number, first_name, middle_name, last_name, college, department, program, specialization, phone_number, email, parent_guardian_name, parent_guardian_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssssssssss", $user_id, $id_number, $first_name, $middle_name, $last_name, $college, $department, $program, $specialization, $phone, $email, $guardian_name, $guardian_phone);
            $stmt->execute();
            
            $conn->commit();
            $success++;
        } catch (Exception $e) {
            $conn->rollback();
            $failed++;
            $errors[] = "Row $total (ID: $id_number): " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    // Log upload history
    $admin_id = $_SESSION['user_id'];
    $error_log = implode("\n", $errors);
    $stmt = $conn->prepare("INSERT INTO bulk_upload_history (uploaded_by, upload_type, filename, total_records, successful_records, failed_records, error_log) VALUES (?, 'students', ?, ?, ?, ?, ?)");
    $stmt->bind_param("isiiss", $admin_id, $filename, $total, $success, $failed, $error_log);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => "Upload completed: $success successful, $failed failed out of $total records",
        'total' => $total,
        'successful' => $success,
        'failed' => $failed,
        'errors' => $errors
    ]);
}

function bulkUploadProfessors() {
    global $conn;
    
    if (!isset($_FILES['csv_file'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        return;
    }
    
    $file = $_FILES['csv_file'];
    $filename = $file['name'];
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    
    if ($ext !== 'csv') {
        echo json_encode(['success' => false, 'message' => 'Please upload a CSV file']);
        return;
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        echo json_encode(['success' => false, 'message' => 'Could not read file']);
        return;
    }
    
    // Skip header row
    fgetcsv($handle);
    
    $total = 0;
    $success = 0;
    $failed = 0;
    $errors = [];
    
    while (($data = fgetcsv($handle)) !== false) {
        $total++;
        
        // Expected CSV format: id_number, first_name, middle_name, last_name, department, email
        if (count($data) < 6) {
            $failed++;
            $errors[] = "Row $total: Incomplete data";
            continue;
        }
        
        $id_number = trim($data[0]);
        $first_name = trim($data[1]);
        $middle_name = trim($data[2]);
        $last_name = trim($data[3]);
        $department = trim($data[4]);
        $email = trim($data[5]);
        
        // Generate default password (id_number)
        $password = password_hash($id_number, PASSWORD_DEFAULT);
        
        try {
            $conn->begin_transaction();
            
            // Insert into user_login_info
            $stmt = $conn->prepare("INSERT INTO user_login_info (id_number, username, password, user_type) VALUES (?, ?, ?, 'professor')");
            $stmt->bind_param("sss", $id_number, $id_number, $password);
            $stmt->execute();
            $user_id = $conn->insert_id;
            
            // Insert into professors
            $stmt = $conn->prepare("INSERT INTO professors (id, id_number, first_name, middle_name, last_name, department, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssss", $user_id, $id_number, $first_name, $middle_name, $last_name, $department, $email);
            $stmt->execute();
            
            $conn->commit();
            $success++;
        } catch (Exception $e) {
            $conn->rollback();
            $failed++;
            $errors[] = "Row $total (ID: $id_number): " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    // Log upload history
    $admin_id = $_SESSION['user_id'];
    $error_log = implode("\n", $errors);
    $stmt = $conn->prepare("INSERT INTO bulk_upload_history (uploaded_by, upload_type, filename, total_records, successful_records, failed_records, error_log) VALUES (?, 'professors', ?, ?, ?, ?, ?)");
    $stmt->bind_param("isiiss", $admin_id, $filename, $total, $success, $failed, $error_log);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => "Upload completed: $success successful, $failed failed out of $total records",
        'total' => $total,
        'successful' => $success,
        'failed' => $failed,
        'errors' => $errors
    ]);
}

function bulkUploadCourses() {
    global $conn;
    
    if (!isset($_FILES['csv_file'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        return;
    }
    
    $file = $_FILES['csv_file'];
    $filename = $file['name'];
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    
    if ($ext !== 'csv') {
        echo json_encode(['success' => false, 'message' => 'Please upload a CSV file']);
        return;
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        echo json_encode(['success' => false, 'message' => 'Could not read file']);
        return;
    }
    
    // Skip header row
    fgetcsv($handle);
    
    $total = 0;
    $success = 0;
    $failed = 0;
    $errors = [];
    
    // Helper function to normalize term names
    function normalizeTermName($term) {
        $term = trim($term);
        $termMap = [
            'FIRST TERM' => 'Term 1',
            'SECOND TERM' => 'Term 2',
            'THIRD TERM' => 'Term 3',
            'FOURTH TERM' => 'Term 4',
            'FIFTH TERM' => 'Term 5',
            'SIXTH TERM' => 'Term 6',
            'SEVENTH TERM' => 'Term 7',
            'EIGHTH TERM' => 'Term 8',
            'NINTH TERM' => 'Term 9',
            'TENTH TERM' => 'Term 10',
            'ELEVENTH TERM' => 'Term 11',
            'TWELFTH TERM' => 'Term 12',
            'Term 1' => 'Term 1',
            'Term 2' => 'Term 2',
            'Term 3' => 'Term 3',
            'Term 4' => 'Term 4',
            'Term 5' => 'Term 5',
            'Term 6' => 'Term 6',
            'Term 7' => 'Term 7',
            'Term 8' => 'Term 8',
            'Term 9' => 'Term 9',
            'Term 10' => 'Term 10',
            'Term 11' => 'Term 11',
            'Term 12' => 'Term 12'
        ];
        return $termMap[strtoupper($term)] ?? $term;
    }
    
    // Helper function to normalize course type
    function normalizeCourseType($type) {
        $type = trim(strtolower($type));
        $typeMap = [
            'major' => 'major',
            'minor' => 'minor',
            'elective' => 'elective',
            'general education' => 'general_education',
            'general_education' => 'general_education',
            'gen ed' => 'general_education'
        ];
        return $typeMap[$type] ?? 'major';
    }
    
    while (($data = fgetcsv($handle)) !== false) {
        $total++;
        
        // Expected CSV format: course_code, course_name, units, program, term, course_type, prerequisites
        if (count($data) < 7) {
            $failed++;
            $errors[] = "Row $total: Incomplete data (expected 7 columns, got " . count($data) . ")";
            continue;
        }
        
        $course_code = trim($data[0]);
        $course_name = trim($data[1]);
        $units = intval(trim($data[2]));
        $program = trim($data[3]);
        $term_raw = trim($data[4]);
        $course_type_raw = trim($data[5]);
        $prerequisites = trim($data[6]);
        
        // Normalize term and course type
        $term = normalizeTermName($term_raw);
        $course_type = normalizeCourseType($course_type_raw);
        
        // Skip if course code is empty
        if (empty($course_code)) {
            $failed++;
            $errors[] = "Row $total: Empty course code";
            continue;
        }
        
        try {
            $stmt = $conn->prepare("INSERT INTO course_catalog (course_code, course_name, units, program, term, course_type, prerequisites) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE course_name = ?, units = ?, program = ?, term = ?, course_type = ?, prerequisites = ?");
            $stmt->bind_param("ssissssssisss", $course_code, $course_name, $units, $program, $term, $course_type, $prerequisites, $course_name, $units, $program, $term, $course_type, $prerequisites);
            $stmt->execute();
            $success++;
        } catch (Exception $e) {
            $failed++;
            $errors[] = "Row $total (Code: $course_code): " . $e->getMessage();
        }
    }
    
    fclose($handle);
    
    // Log upload history
    $admin_id = $_SESSION['user_id'];
    $error_log = implode("\n", $errors);
    $stmt = $conn->prepare("INSERT INTO bulk_upload_history (uploaded_by, upload_type, filename, total_records, successful_records, failed_records, error_log) VALUES (?, 'courses', ?, ?, ?, ?, ?)");
    $stmt->bind_param("isiiss", $admin_id, $filename, $total, $success, $failed, $error_log);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => "Upload completed: $success successful, $failed failed out of $total records",
        'total' => $total,
        'successful' => $success,
        'failed' => $failed,
        'errors' => $errors
    ]);
}

// ============= DASHBOARD STATS =============

function getDashboardStats() {
    global $conn;
    
    // Total students
    $result = $conn->query("SELECT COUNT(*) as total FROM students");
    $total_students = $result->fetch_assoc()['total'];
    
    // Total professors
    $result = $conn->query("SELECT COUNT(*) as total FROM professors");
    $total_professors = $result->fetch_assoc()['total'];
    
    // Students cleared for advising
    $result = $conn->query("SELECT COUNT(*) as total FROM students WHERE advising_cleared = TRUE");
    $cleared_students = $result->fetch_assoc()['total'];
    
    // Students with adviser assigned
    $result = $conn->query("SELECT COUNT(*) as total FROM students WHERE advisor_id IS NOT NULL");
    $assigned_students = $result->fetch_assoc()['total'];
    
    // Students at risk (>15 failed units)
    $result = $conn->query("SELECT COUNT(*) as total FROM students WHERE accumulated_failed_units >= 15");
    $at_risk_students = $result->fetch_assoc()['total'];
    
    // Students critical (>25 failed units)
    $result = $conn->query("SELECT COUNT(*) as total FROM students WHERE accumulated_failed_units >= 25");
    $critical_students = $result->fetch_assoc()['total'];
    
    // Program distribution
    $result = $conn->query("SELECT program, COUNT(*) as count FROM students GROUP BY program");
    $program_distribution = [];
    while ($row = $result->fetch_assoc()) {
        $program_distribution[] = $row;
    }
    
    // Professor advising progress
    $result = $conn->query("
        SELECT 
            p.id_number,
            CONCAT(p.first_name, ' ', p.last_name) as name,
            p.department,
            COUNT(s.id) as total_advisees,
            SUM(CASE WHEN s.advising_cleared = TRUE THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN s.advising_cleared = FALSE THEN 1 ELSE 0 END) as pending
        FROM professors p
        LEFT JOIN students s ON s.advisor_id = p.id
        GROUP BY p.id
        ORDER BY p.id_number
    ");
    $professor_progress = [];
    while ($row = $result->fetch_assoc()) {
        $row['completion_rate'] = $row['total_advisees'] > 0 ? round(($row['completed'] / $row['total_advisees']) * 100) : 0;
        $professor_progress[] = $row;
    }
    
    // Determine the most recent study plan window to drive enrollment insights
    $latestPlanResult = $conn->query("
        SELECT academic_year, term 
        FROM study_plans 
        WHERE status IN ('pending', 'approved')
        ORDER BY updated_at DESC 
        LIMIT 1
    ");
    $latestPlan = $latestPlanResult && $latestPlanResult->num_rows > 0
        ? $latestPlanResult->fetch_assoc()
        : null;
    
    $current_enrollment = [];
    $planned_enrollment = [];
    
    if ($latestPlan) {
        $academicYear = $latestPlan['academic_year'];
        $term = $latestPlan['term'];
        
        // Current term enrollment stats
        $stmt = $conn->prepare("
            SELECT cs.subject_code, COUNT(DISTINCT sp.student_id) as student_count
            FROM study_plans sp
            JOIN current_subjects cs ON cs.study_plan_id = sp.id
            WHERE sp.academic_year = ? 
              AND sp.term = ? 
              AND sp.status IN ('pending', 'approved')
            GROUP BY cs.subject_code
            ORDER BY student_count DESC
            LIMIT 5
        ");
        $stmt->bind_param("ss", $academicYear, $term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $current_enrollment[] = $row;
        }
        $stmt->close();
        
        // Planned enrollment stats
        $stmt = $conn->prepare("
            SELECT ps.subject_code, COUNT(DISTINCT sp.student_id) as student_count
            FROM study_plans sp
            JOIN planned_subjects ps ON ps.study_plan_id = sp.id
            WHERE sp.academic_year = ? 
              AND sp.term = ? 
              AND sp.status IN ('pending', 'approved')
            GROUP BY ps.subject_code
            ORDER BY student_count DESC
            LIMIT 5
        ");
        $stmt->bind_param("ss", $academicYear, $term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $planned_enrollment[] = $row;
        }
        $stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_students' => $total_students,
            'total_professors' => $total_professors,
            'cleared_students' => $cleared_students,
            'assigned_students' => $assigned_students,
            'at_risk_students' => $at_risk_students,
            'critical_students' => $critical_students,
            'program_distribution' => $program_distribution,
            'professor_progress' => $professor_progress,
            'current_enrollment' => $current_enrollment,
            'planned_enrollment' => $planned_enrollment
        ]
    ]);
}

// ============= STUDENT MANAGEMENT =============

function getStudentsList() {
    global $conn;
    
    $search = $_GET['search'] ?? '';
    $program = $_GET['program'] ?? '';
    
    $query = "
        SELECT 
            s.id,
            s.id_number,
            CONCAT(s.first_name, ' ', s.middle_name, ' ', s.last_name) as full_name,
            s.program,
            s.department,
            s.email,
            s.phone_number,
            s.accumulated_failed_units,
            CONCAT(p.first_name, ' ', p.last_name) as adviser_name,
            s.advising_cleared
        FROM students s
        LEFT JOIN professors p ON p.id = s.advisor_id
        WHERE 1=1
    ";
    
    if ($search) {
        $search = $conn->real_escape_string($search);
        $query .= " AND (s.id_number LIKE '%$search%' OR s.first_name LIKE '%$search%' OR s.last_name LIKE '%$search%' OR s.email LIKE '%$search%')";
    }
    
    if ($program) {
        $program = $conn->real_escape_string($program);
        $query .= " AND s.program = '$program'";
    }
    
    $query .= " ORDER BY s.id_number";
    
    $result = $conn->query($query);
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
}

function getStudentDetails() {
    global $conn;
    
    $student_id = $_GET['student_id'];
    
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'student' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
}

function getProfessorDetails() {
    global $conn;
    
    $professor_id = $_GET['professor_id'];
    
    $stmt = $conn->prepare("SELECT * FROM professors WHERE id = ?");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'professor' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Professor not found']);
    }
}

function addSingleStudent() {
    global $conn;
    
    $id_number = $_POST['id_number'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'];
    $college = $_POST['college'];
    $department = $_POST['department'];
    $program = $_POST['program'];
    $specialization = $_POST['specialization'] ?? 'N/A';
    $phone = $_POST['phone_number'];
    $email = $_POST['email'];
    $guardian_name = $_POST['guardian_name'];
    $guardian_phone = $_POST['guardian_phone'];
    
    // Generate random temporary password
    $temp_password = generateRandomPassword(12);
    $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
    
    try {
        $conn->begin_transaction();
        
        // Insert into user_login_info
        $stmt = $conn->prepare("INSERT INTO user_login_info (id_number, username, password, user_type) VALUES (?, ?, ?, 'student')");
        $stmt->bind_param("sss", $id_number, $id_number, $password_hash);
        $stmt->execute();
        $user_id = $conn->insert_id;
        
        // Insert into students table
        $stmt = $conn->prepare("INSERT INTO students (id, id_number, first_name, middle_name, last_name, college, department, program, specialization, phone_number, email, parent_guardian_name, parent_guardian_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssssssssss", $user_id, $id_number, $first_name, $middle_name, $last_name, $college, $department, $program, $specialization, $phone, $email, $guardian_name, $guardian_phone);
        $stmt->execute();
        
        $conn->commit();
        
        // Send email with temporary password
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = MAILER_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAILER_USERNAME;
            $mail->Password = MAILER_PASSWORD;
            $mail->SMTPSecure = MAILER_ENCRYPTION;
            $mail->Port = MAILER_PORT;
            
            // Recipients
            $mail->setFrom(MAILER_FROM_EMAIL, MAILER_FROM_NAME);
            $mail->addAddress($email, "$first_name $last_name");
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to Academic Advising System - Your Account Details';
            $mail->Body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #6a1b9a; color: white; padding: 20px; text-align: center; }
                        .content { background: #f9f9f9; padding: 30px; }
                        .credentials { background: white; padding: 20px; border-left: 4px solid #6a1b9a; margin: 20px 0; }
                        .password { font-size: 24px; font-weight: bold; color: #6a1b9a; letter-spacing: 2px; }
                        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Welcome to Academic Advising System</h1>
                        </div>
                        <div class='content'>
                            <p>Dear <strong>$first_name $last_name</strong>,</p>
                            <p>Your student account has been created successfully. Below are your login credentials:</p>
                            
                            <div class='credentials'>
                                <p><strong>ID Number / Username:</strong> $id_number</p>
                                <p><strong>Temporary Password:</strong></p>
                                <p class='password'>$temp_password</p>
                            </div>
                            
                            <p><strong>Important:</strong> Please log in and change your password immediately.</p>
                            <p>You can access the system at: <a href='" . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/login.php'>Login Here</a></p>
                            
                            <p>If you have any questions, please contact your academic adviser or the admin office.</p>
                        </div>
                        <div class='footer'>
                            <p>Academic Advising System - De La Salle University</p>
                            <p>This is an automated message. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $mail->send();
            echo json_encode(['success' => true, 'message' => 'Student added successfully and credentials sent to email']);
        } catch (PHPMailerException $e) {
            // Student was added but email failed
            echo json_encode(['success' => true, 'message' => 'Student added successfully but email failed to send. Please provide credentials manually.', 'email_error' => $mail->ErrorInfo]);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function editStudent() {
    global $conn;
    
    $student_id = $_POST['student_id'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'];
    $college = $_POST['college'];
    $department = $_POST['department'];
    $program = $_POST['program'];
    $specialization = $_POST['specialization'] ?? 'N/A';
    $phone = $_POST['phone_number'];
    $email = $_POST['email'];
    $guardian_name = $_POST['guardian_name'];
    $guardian_phone = $_POST['guardian_phone'];
    
    try {
        $stmt = $conn->prepare("UPDATE students SET first_name = ?, middle_name = ?, last_name = ?, college = ?, department = ?, program = ?, specialization = ?, phone_number = ?, email = ?, parent_guardian_name = ?, parent_guardian_number = ? WHERE id = ?");
        $stmt->bind_param("sssssssssssi", $first_name, $middle_name, $last_name, $college, $department, $program, $specialization, $phone, $email, $guardian_name, $guardian_phone, $student_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteStudent() {
    global $conn;
    
    $student_id = $_POST['student_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM user_login_info WHERE id = (SELECT id FROM students WHERE id = ?)");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============= PROFESSOR MANAGEMENT =============

function getProfessorsList() {
    global $conn;
    
    $search = $_GET['search'] ?? '';
    $query = "
        SELECT 
            p.id,
            p.id_number,
            CONCAT(p.first_name, ' ', p.middle_name, ' ', p.last_name) as full_name,
            p.department,
            p.email,
            COUNT(s.id) as advisee_count,
            COALESCE(SUM(CASE WHEN s.advising_cleared = 1 THEN 1 ELSE 0 END), 0) as completed_advisees,
            COALESCE(SUM(CASE WHEN s.advising_cleared = 0 AND s.id IS NOT NULL THEN 1 ELSE 0 END), 0) as pending_advisees
        FROM professors p
        LEFT JOIN students s ON s.advisor_id = p.id
        WHERE 1=1
    ";
    
    if ($search) {
        $safeSearch = $conn->real_escape_string($search);
        $query .= "
            AND (
                p.id_number LIKE '%$safeSearch%' OR
                p.first_name LIKE '%$safeSearch%' OR
                p.last_name LIKE '%$safeSearch%' OR
                p.email LIKE '%$safeSearch%'
            )
        ";
    }
    
    $query .= " GROUP BY p.id ORDER BY p.id_number";
    
    $result = $conn->query($query);
    
    $professors = [];
    while ($row = $result->fetch_assoc()) {
        $professors[] = $row;
    }
    
    echo json_encode(['success' => true, 'professors' => $professors]);
}

function addSingleProfessor() {
    global $conn;
    
    $id_number = $_POST['id_number'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'];
    $department = $_POST['department'];
    $email = $_POST['email'];
    
    // Generate random temporary password
    $temp_password = generateRandomPassword(12);
    $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
    
    try {
        $conn->begin_transaction();
        
        // Insert into user_login_info
        $stmt = $conn->prepare("INSERT INTO user_login_info (id_number, username, password, user_type) VALUES (?, ?, ?, 'professor')");
        $stmt->bind_param("sss", $id_number, $id_number, $password_hash);
        $stmt->execute();
        $user_id = $conn->insert_id;
        
        // Insert into professors table
        $stmt = $conn->prepare("INSERT INTO professors (id, id_number, first_name, middle_name, last_name, department, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssss", $user_id, $id_number, $first_name, $middle_name, $last_name, $department, $email);
        $stmt->execute();
        
        $conn->commit();
        
        // Send email with temporary password
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = MAILER_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAILER_USERNAME;
            $mail->Password = MAILER_PASSWORD;
            $mail->SMTPSecure = MAILER_ENCRYPTION;
            $mail->Port = MAILER_PORT;
            
            // Recipients
            $mail->setFrom(MAILER_FROM_EMAIL, MAILER_FROM_NAME);
            $mail->addAddress($email, "$first_name $last_name");
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to Academic Advising System - Your Account Details';
            $mail->Body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #6a1b9a; color: white; padding: 20px; text-align: center; }
                        .content { background: #f9f9f9; padding: 30px; }
                        .credentials { background: white; padding: 20px; border-left: 4px solid #6a1b9a; margin: 20px 0; }
                        .password { font-size: 24px; font-weight: bold; color: #6a1b9a; letter-spacing: 2px; }
                        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Welcome to Academic Advising System</h1>
                        </div>
                        <div class='content'>
                            <p>Dear <strong>Prof. $first_name $last_name</strong>,</p>
                            <p>Your professor account has been created successfully. Below are your login credentials:</p>
                            
                            <div class='credentials'>
                                <p><strong>ID Number / Username:</strong> $id_number</p>
                                <p><strong>Temporary Password:</strong></p>
                                <p class='password'>$temp_password</p>
                            </div>
                            
                            <p><strong>Important:</strong> Please log in and change your password immediately.</p>
                            <p>You can access the system at: <a href='" . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/login.php'>Login Here</a></p>
                            
                            <p>If you have any questions, please contact the admin office.</p>
                        </div>
                        <div class='footer'>
                            <p>Academic Advising System - De La Salle University</p>
                            <p>This is an automated message. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $mail->send();
            echo json_encode(['success' => true, 'message' => 'Professor added successfully and credentials sent to email']);
        } catch (PHPMailerException $e) {
            // Professor was added but email failed
            echo json_encode(['success' => true, 'message' => 'Professor added successfully but email failed to send. Please provide credentials manually.', 'email_error' => $mail->ErrorInfo]);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function editProfessor() {
    global $conn;
    
    $professor_id = $_POST['professor_id'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'] ?? '';
    $last_name = $_POST['last_name'];
    $department = $_POST['department'];
    $email = $_POST['email'];
    
    try {
        $stmt = $conn->prepare("UPDATE professors SET first_name = ?, middle_name = ?, last_name = ?, department = ?, email = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $first_name, $middle_name, $last_name, $department, $email, $professor_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Professor updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteProfessor() {
    global $conn;
    
    $professor_id = $_POST['professor_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM user_login_info WHERE id = (SELECT id FROM professors WHERE id = ?)");
        $stmt->bind_param("i", $professor_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Professor deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============= ADVISING ASSIGNMENT =============

function getUnassignedStudents() {
    global $conn;
    
    $program = $_GET['program'] ?? '';
    
    $query = "
        SELECT 
            id,
            id_number,
            CONCAT(first_name, ' ', last_name) as full_name,
            program,
            email
        FROM students
        WHERE advisor_id IS NULL
    ";
    
    if ($program) {
        $program = $conn->real_escape_string($program);
        $query .= " AND program = '$program'";
    }
    
    $query .= " ORDER BY id_number";
    
    $result = $conn->query($query);
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
}

function getAdviserStudents() {
    global $conn;
    
    $professor_id = $_GET['professor_id'];
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            id_number,
            CONCAT(first_name, ' ', last_name) as full_name,
            program,
            email,
            advising_cleared,
            accumulated_failed_units
        FROM students
        WHERE advisor_id = ?
        ORDER BY id_number
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
}

function assignStudentToAdviser() {
    global $conn;
    
    $student_id = $_POST['student_id'];
    $professor_id = $_POST['professor_id'];
    
    try {
        $stmt = $conn->prepare("UPDATE students SET advisor_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $professor_id, $student_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Student assigned successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function removeStudentFromAdviser() {
    global $conn;
    
    $student_id = $_POST['student_id'];
    
    try {
        $stmt = $conn->prepare("UPDATE students SET advisor_id = NULL WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Student removed from adviser']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============= COURSE MANAGEMENT =============

function getCourseCatalog() {
    global $conn;
    
    $program = $_GET['program'] ?? '';
    $search = $_GET['search'] ?? '';
    $term = $_GET['term'] ?? '';
    
    $query = "SELECT *, 
              CASE 
                  WHEN program = '0' THEN 'All Programs (Shared)'
                  ELSE program
              END as display_program
              FROM course_catalog WHERE is_active = 1";
    
    if ($program) {
        $program = $conn->real_escape_string($program);
        // Include both program-specific courses AND shared courses (program='0')
        $query .= " AND (program = '$program' OR program = '0')";
    }
    
    if ($search) {
        $search = $conn->real_escape_string($search);
        $query .= " AND (course_code LIKE '%$search%' OR course_name LIKE '%$search%')";
    }
    
    if ($term) {
        $term = $conn->real_escape_string($term);
        $query .= " AND term = '$term'";
    }
    
    $query .= " ORDER BY 
        CAST(SUBSTRING(term, 6) AS UNSIGNED), 
        course_code";
    
    $result = $conn->query($query);
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    
    echo json_encode(['success' => true, 'courses' => $courses]);
}

function addCourse() {
    global $conn;
    
    $course_code = $_POST['course_code'];
    $course_name = $_POST['course_name'];
    $units = $_POST['units'];
    $program = $_POST['program'];
    $term = $_POST['term'];
    $course_type = $_POST['course_type'];
    $prerequisites = $_POST['prerequisites'] ?? '';
    
    try {
        $stmt = $conn->prepare("INSERT INTO course_catalog (course_code, course_name, units, program, term, course_type, prerequisites) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissss", $course_code, $course_name, $units, $program, $term, $course_type, $prerequisites);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Course added successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateCourse() {
    global $conn;
    
    $course_id = $_POST['course_id'];
    $course_code = $_POST['course_code'];
    $course_name = $_POST['course_name'];
    $units = $_POST['units'];
    $program = $_POST['program'];
    $term = $_POST['term'];
    $course_type = $_POST['course_type'];
    $prerequisites = $_POST['prerequisites'] ?? '';
    
    try {
        $stmt = $conn->prepare("UPDATE course_catalog SET course_code = ?, course_name = ?, units = ?, program = ?, term = ?, course_type = ?, prerequisites = ? WHERE id = ?");
        $stmt->bind_param("ssissssi", $course_code, $course_name, $units, $program, $term, $course_type, $prerequisites, $course_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteCourse() {
    global $conn;
    
    $course_id = $_POST['course_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM course_catalog WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Course deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ============= USER PASSWORD MANAGEMENT =============

function getUserPassword() {
    global $conn;
    
    $user_id = $_GET['user_id'];
    $user_type = $_GET['user_type'];
    
    try {
        // Get the id_number which is the default password
        if ($user_type === 'student') {
            $stmt = $conn->prepare("SELECT id_number FROM students WHERE id = ?");
        } else {
            $stmt = $conn->prepare("SELECT id_number FROM professors WHERE id = ?");
        }
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'password' => $row['id_number']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>