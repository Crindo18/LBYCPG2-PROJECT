<?php
require_once 'auth_check.php';
requireAdmin();

require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Clean any previous output
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

ob_end_clean();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // ... (Keep existing bulk upload cases) ...
    case 'bulk_upload_students': bulkUploadStudents(); exit;
    case 'bulk_upload_professors': bulkUploadProfessors(); exit;
    case 'bulk_upload_courses': bulkUploadCourses(); exit;
    
    // Updated Dashboard Stats
    case 'get_dashboard_stats': getDashboardStats(); exit;
    
    // ... (Keep existing list/edit/delete cases) ...
    case 'get_professors_list': getProfessorsList(); exit;
    case 'get_students_list': getStudentsList(); exit;
    case 'get_student_details': getStudentDetails(); exit;
    case 'get_professor_details': getProfessorDetails(); exit;
    case 'get_unassigned_students': getUnassignedStudents(); exit;
    case 'assign_student_to_adviser': assignStudentToAdviser(); exit;
    case 'get_adviser_students': getAdviserStudents(); exit;
    case 'remove_student_from_adviser': removeStudentFromAdviser(); exit;
    case 'add_single_student': addSingleStudent(); exit;
    case 'edit_student': editStudent(); exit;
    case 'add_single_professor': addSingleProfessor(); exit;
    case 'edit_professor': editProfessor(); exit;
    case 'delete_student': deleteStudent(); exit;
    case 'delete_professor': deleteProfessor(); exit;
    case 'get_course_catalog': getCourseCatalog(); exit;
    case 'add_course': addCourse(); exit;
    case 'update_course': updateCourse(); exit;
    case 'delete_course': deleteCourse(); exit;
    case 'get_user_password': getUserPassword(); exit;
    default: echo json_encode(['success' => false, 'message' => 'Invalid action']); exit;
}

// ============= UPDATED DASHBOARD STATS =============

function getDashboardStats() {
    global $conn;
    
    // 1. Basic Counts (Students, Profs, Clearance)
    $total_students = $conn->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'];
    $total_professors = $conn->query("SELECT COUNT(*) as total FROM professors")->fetch_assoc()['total'];
    
    // "Cleared" logic depends on your business rule. 
    // If clearing happens via 'academic_advising_forms' status='approved', we check that.
    // Or if it flags the 'students' table directly. Assuming students table flag for now:
    $cleared_students = $conn->query("SELECT COUNT(*) as total FROM students WHERE advising_cleared = 1")->fetch_assoc()['total'];
    $assigned_students = $conn->query("SELECT COUNT(*) as total FROM students WHERE advisor_id IS NOT NULL")->fetch_assoc()['total'];
    
    $at_risk_students = $conn->query("SELECT COUNT(*) as total FROM students WHERE accumulated_failed_units >= 15")->fetch_assoc()['total'];
    $critical_students = $conn->query("SELECT COUNT(*) as total FROM students WHERE accumulated_failed_units >= 25")->fetch_assoc()['total'];
    
    // 2. Program Distribution
    $result = $conn->query("SELECT program, COUNT(*) as count FROM students GROUP BY program");
    $program_distribution = [];
    while ($row = $result->fetch_assoc()) {
        $program_distribution[] = $row;
    }
    
    // 3. Professor Progress (Updated to look at new Forms table)
    // Counts how many forms are "approved" vs "pending" per professor
    $result = $conn->query("
        SELECT 
            p.id_number,
            CONCAT(p.first_name, ' ', p.last_name) as name,
            p.department,
            COUNT(DISTINCT s.id) as total_advisees,
            COUNT(DISTINCT CASE WHEN aaf.status = 'approved' THEN s.id END) as completed,
            COUNT(DISTINCT CASE WHEN aaf.status = 'pending' THEN s.id END) as pending
        FROM professors p
        LEFT JOIN students s ON s.advisor_id = p.id
        LEFT JOIN academic_advising_forms aaf ON aaf.student_id = s.id
        GROUP BY p.id
        ORDER BY p.id_number
    ");
    
    $professor_progress = [];
    while ($row = $result->fetch_assoc()) {
        $row['completion_rate'] = $row['total_advisees'] > 0 ? round(($row['completed'] / $row['total_advisees']) * 100) : 0;
        $professor_progress[] = $row;
    }
    
    // 4. Current Enrollment (Connected to new Tables)
    // Find the most recent Term submitted in the system
    $latestPlanResult = $conn->query("
        SELECT academic_year, term 
        FROM study_plans 
        ORDER BY submission_date DESC 
        LIMIT 1
    ");
    $latestPlan = ($latestPlanResult && $latestPlanResult->num_rows > 0) ? $latestPlanResult->fetch_assoc() : null;
    
    $current_enrollment = [];
    $planned_enrollment = []; // Optional, depends if form has 'planned' courses
    
    if ($latestPlan) {
        $academicYear = $latestPlan['academic_year'];
        $term = $latestPlan['term'];
        
        // Fetch course counts from the NEW 'advising_form_courses' table
        // Linked via 'academic_advising_forms'
        $stmt = $conn->prepare("
            SELECT cs.subject_code, COUNT(DISTINCT sp.student_id) as student_count
            FROM study_plans sp
            JOIN current_subjects cs ON cs.study_plan_id = sp.id
            WHERE sp.academic_year = ? 
              AND sp.term = ?
            GROUP BY cs.subject_code
            ORDER BY student_count DESC
            LIMIT 5
        ");
        $stmt->bind_param("si", $academicYear, $term); // Assuming term is stored as INT or String depending on DB. Adjust "si" to "ss" if term is string.
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

function bulkUploadStudents() { echo json_encode(['success'=>false,'message'=>'Not implemented']); }
function bulkUploadProfessors() { echo json_encode(['success'=>false,'message'=>'Not implemented']); }
function bulkUploadCourses() { echo json_encode(['success'=>false,'message'=>'Not implemented']); }
function getProfessorsList() { 
    global $conn; 
    $result = $conn->query("SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as full_name, COUNT(s.id) as advisee_count FROM professors p LEFT JOIN students s ON s.advisor_id = p.id GROUP BY p.id ORDER BY p.id_number"); 
    $data = []; 
    while($r=$result->fetch_assoc())$data[]=$r; 
    echo json_encode(['success'=>true,'professors'=>$data]); 
}
function getStudentsList() { 
    global $conn; 
    $q = "SELECT s.*, CONCAT(s.first_name, ' ', s.last_name) as full_name, CONCAT(p.first_name, ' ', p.last_name) as adviser_name FROM students s LEFT JOIN professors p ON p.id = s.advisor_id ORDER BY s.id_number"; 
    $res = $conn->query($q); 
    $data=[]; 
    while($r=$res->fetch_assoc())$data[]=$r; 
    echo json_encode(['success'=>true,'students'=>$data]); 
}
// ... Add all other CRUD functions from your original file here ...
// (getStudentDetails, getProfessorDetails, addSingleStudent, etc.)
function getStudentDetails() { global $conn; $id=$_GET['student_id']; $stmt=$conn->prepare("SELECT * FROM students WHERE id=?"); $stmt->bind_param("i",$id); $stmt->execute(); echo json_encode(['success'=>true, 'student'=>$stmt->get_result()->fetch_assoc()]); }
function getProfessorDetails() { global $conn; $id=$_GET['professor_id']; $stmt=$conn->prepare("SELECT * FROM professors WHERE id=?"); $stmt->bind_param("i",$id); $stmt->execute(); echo json_encode(['success'=>true, 'professor'=>$stmt->get_result()->fetch_assoc()]); }
function getUnassignedStudents() { global $conn; $res=$conn->query("SELECT * FROM students WHERE advisor_id IS NULL"); $data=[]; while($r=$res->fetch_assoc())$data[]=$r; echo json_encode(['success'=>true,'students'=>$data]); }
function assignStudentToAdviser() { global $conn; $sid=$_POST['student_id']; $pid=$_POST['professor_id']; $conn->query("UPDATE students SET advisor_id=$pid WHERE id=$sid"); echo json_encode(['success'=>true]); }
function getAdviserStudents() { global $conn; $pid=$_GET['professor_id']; $res=$conn->query("SELECT * FROM students WHERE advisor_id=$pid"); $data=[]; while($r=$res->fetch_assoc())$data[]=$r; echo json_encode(['success'=>true,'students'=>$data]); }
function removeStudentFromAdviser() { global $conn; $sid=$_POST['student_id']; $conn->query("UPDATE students SET advisor_id=NULL WHERE id=$sid"); echo json_encode(['success'=>true]); }

// Helper function to send email notification
function sendAccountEmail($email, $name, $idNumber, $userType) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAILER_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAILER_USERNAME;
        $mail->Password = MAILER_PASSWORD;
        $mail->SMTPSecure = MAILER_ENCRYPTION;
        $mail->Port = MAILER_PORT;
        $mail->setFrom(MAILER_FROM_EMAIL, MAILER_FROM_NAME);
        $mail->addAddress($email, $name);
        $mail->Subject = 'Academic Advising System - Account Created';
        
        $portalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/login.php";
        
        $mail->isHTML(true);
        $mail->Body = "
            <h2>Welcome to the Academic Advising System</h2>
            <p>Dear {$name},</p>
            <p>Your {$userType} account has been successfully created. Here are your login credentials:</p>
            <div style='background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <strong>ID Number:</strong> {$idNumber}<br>
                <strong>Default Password:</strong> {$idNumber}
            </div>
            <p><strong>Important:</strong> Please change your password after your first login for security purposes.</p>
            <p>You can access the system at: <a href='{$portalUrl}'>{$portalUrl}</a></p>
            <p>If you have any questions, please contact the system administrator.</p>
            <p>Best regards,<br>Academic Advising System</p>
        ";
        $mail->AltBody = "Welcome to the Academic Advising System\n\nDear {$name},\n\nYour {$userType} account has been created.\n\nID Number: {$idNumber}\nDefault Password: {$idNumber}\n\nPlease change your password after your first login.\n\nPortal: {$portalUrl}";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . $e->getMessage());
        return false;
    }
}

function addSingleStudent() {
    global $conn;
    
    try {
        $idNumber = $_POST['id_number'];
        $firstName = $_POST['first_name'];
        $middleName = $_POST['middle_name'] ?? '';
        $lastName = $_POST['last_name'];
        $college = $_POST['college'];
        $department = $_POST['department'];
        $program = $_POST['program'];
        $specialization = $_POST['specialization'] ?? 'N/A';
        $phone = $_POST['phone_number'];
        $email = $_POST['email'];
        $guardianName = $_POST['guardian_name'];
        $guardianPhone = $_POST['guardian_phone'];
        
        // Check if ID already exists in students table
        $stmt = $conn->prepare("SELECT id FROM students WHERE id_number = ?");
        $stmt->bind_param("i", $idNumber);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Student ID already exists']);
            return;
        }
        
        // Check if ID already exists in user_login_info table
        $stmt = $conn->prepare("SELECT id FROM user_login_info WHERE id_number = ?");
        $stmt->bind_param("s", $idNumber);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'ID number already exists in system']);
            return;
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        // Step 1: Insert into students table
        $stmt = $conn->prepare("INSERT INTO students (id_number, first_name, middle_name, last_name, college, department, program, specialization, phone_number, email, parent_guardian_name, parent_guardian_number, advising_cleared, accumulated_failed_units) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
        $stmt->bind_param("isssssssssss", $idNumber, $firstName, $middleName, $lastName, $college, $department, $program, $specialization, $phone, $email, $guardianName, $guardianPhone);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert into students table: ' . $stmt->error);
        }
        
        // Step 2: Hash password and insert into user_login_info table
        $passwordHash = password_hash($idNumber, PASSWORD_DEFAULT);
        $userType = 'student';
        
        $stmt = $conn->prepare("INSERT INTO user_login_info (id_number, username, password, user_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $idNumber, $idNumber, $passwordHash, $userType);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert into user_login_info table: ' . $stmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Step 3: Send email notification
        $fullName = trim("$firstName $middleName $lastName");
        $emailSent = sendAccountEmail($email, $fullName, $idNumber, 'student');
        
        if ($emailSent) {
            echo json_encode(['success' => true, 'message' => 'Student added successfully. Login credentials sent to email.']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Student added successfully, but email notification failed. Default password is: ' . $idNumber]);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function editStudent() {
    global $conn;
    
    $studentId = $_POST['student_id'];
    $firstName = $_POST['first_name'];
    $middleName = $_POST['middle_name'] ?? '';
    $lastName = $_POST['last_name'];
    $college = $_POST['college'];
    $department = $_POST['department'];
    $program = $_POST['program'];
    $specialization = $_POST['specialization'] ?? 'N/A';
    $phone = $_POST['phone_number'];
    $email = $_POST['email'];
    $guardianName = $_POST['guardian_name'];
    $guardianPhone = $_POST['guardian_phone'];
    
    $stmt = $conn->prepare("UPDATE students SET first_name=?, middle_name=?, last_name=?, college=?, department=?, program=?, specialization=?, phone_number=?, email=?, parent_guardian_name=?, parent_guardian_number=? WHERE id=?");
    $stmt->bind_param("sssssssssssi", $firstName, $middleName, $lastName, $college, $department, $program, $specialization, $phone, $email, $guardianName, $guardianPhone, $studentId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
}

function deleteStudent() {
    global $conn;
    
    try {
        $studentId = $_POST['student_id'];
        
        // Get student's id_number first
        $stmt = $conn->prepare("SELECT id_number FROM students WHERE id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            return;
        }
        
        $student = $result->fetch_assoc();
        $idNumber = $student['id_number'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        // Delete from students table
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $studentId);
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete from students table');
        }
        
        // Delete from user_login_info table
        $stmt = $conn->prepare("DELETE FROM user_login_info WHERE id_number = ? AND user_type = 'student'");
        $stmt->bind_param("s", $idNumber);
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete from user_login_info table');
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function addSingleProfessor() {
    global $conn;
    
    try {
        $idNumber = $_POST['id_number'];
        $firstName = $_POST['first_name'];
        $middleName = $_POST['middle_name'] ?? '';
        $lastName = $_POST['last_name'];
        $department = $_POST['department'];
        $email = $_POST['email'];
        
        // Check if ID already exists in professors table
        $stmt = $conn->prepare("SELECT id FROM professors WHERE id_number = ?");
        $stmt->bind_param("i", $idNumber);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Professor ID already exists']);
            return;
        }
        
        // Check if ID already exists in user_login_info table
        $stmt = $conn->prepare("SELECT id FROM user_login_info WHERE id_number = ?");
        $stmt->bind_param("s", $idNumber);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'ID number already exists in system']);
            return;
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        // Step 1: Insert into professors table
        $stmt = $conn->prepare("INSERT INTO professors (id_number, first_name, middle_name, last_name, department, email) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $idNumber, $firstName, $middleName, $lastName, $department, $email);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert into professors table: ' . $stmt->error);
        }
        
        // Step 2: Hash password and insert into user_login_info table
        $passwordHash = password_hash($idNumber, PASSWORD_DEFAULT);
        $userType = 'professor';
        
        $stmt = $conn->prepare("INSERT INTO user_login_info (id_number, username, password, user_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $idNumber, $idNumber, $passwordHash, $userType);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert into user_login_info table: ' . $stmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Step 3: Send email notification
        $fullName = trim("$firstName $middleName $lastName");
        $emailSent = sendAccountEmail($email, $fullName, $idNumber, 'professor');
        
        if ($emailSent) {
            echo json_encode(['success' => true, 'message' => 'Professor added successfully. Login credentials sent to email.']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Professor added successfully, but email notification failed. Default password is: ' . $idNumber]);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function editProfessor() {
    global $conn;
    
    $professorId = $_POST['professor_id'];
    $firstName = $_POST['first_name'];
    $middleName = $_POST['middle_name'] ?? '';
    $lastName = $_POST['last_name'];
    $department = $_POST['department'];
    $email = $_POST['email'];
    
    $stmt = $conn->prepare("UPDATE professors SET first_name=?, middle_name=?, last_name=?, department=?, email=? WHERE id=?");
    $stmt->bind_param("sssssi", $firstName, $middleName, $lastName, $department, $email, $professorId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Professor updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
}

function deleteProfessor() {
    global $conn;
    
    try {
        $professorId = $_POST['professor_id'];
        
        // Check if professor has assigned students
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE advisor_id = ?");
        $stmt->bind_param("i", $professorId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete professor with assigned advisees. Please reassign students first.']);
            return;
        }
        
        // Get professor's id_number first
        $stmt = $conn->prepare("SELECT id_number FROM professors WHERE id = ?");
        $stmt->bind_param("i", $professorId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Professor not found']);
            return;
        }
        
        $professor = $result->fetch_assoc();
        $idNumber = $professor['id_number'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        // Delete from professors table
        $stmt = $conn->prepare("DELETE FROM professors WHERE id = ?");
        $stmt->bind_param("i", $professorId);
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete from professors table');
        }
        
        // Delete from user_login_info table
        $stmt = $conn->prepare("DELETE FROM user_login_info WHERE id_number = ? AND user_type = 'professor'");
        $stmt->bind_param("s", $idNumber);
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete from user_login_info table');
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Professor deleted successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
function getCourseCatalog() { 
    global $conn; 
    $res = $conn->query("SELECT * FROM course_catalog"); 
    $data = []; 
    while($r = $res->fetch_assoc()) {
        $data[] = $r;
    }
    echo json_encode(['success' => true, 'courses' => $data]); 
}

function addCourse() { 
    echo json_encode(['success' => false, 'message' => 'Not implemented in snippet']); 
}

function updateCourse() { 
    echo json_encode(['success' => false, 'message' => 'Not implemented in snippet']); 
}

function deleteCourse() { 
    echo json_encode(['success' => false, 'message' => 'Not implemented in snippet']); 
}

function getUserPassword() { 
    echo json_encode(['success' => false, 'message' => 'Not implemented in snippet']); 
}