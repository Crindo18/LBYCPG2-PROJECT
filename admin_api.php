<?php
require_once 'auth_check.php';
requireAdmin();

require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

ob_clean();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // ... (Keep existing bulk upload cases) ...
    case 'bulk_upload_students': bulkUploadStudents(); break;
    case 'bulk_upload_professors': bulkUploadProfessors(); break;
    case 'bulk_upload_courses': bulkUploadCourses(); break;
    
    // Updated Dashboard Stats
    case 'get_dashboard_stats': getDashboardStats(); break;
    
    // ... (Keep existing list/edit/delete cases) ...
    case 'get_professors_list': getProfessorsList(); break;
    case 'get_students_list': getStudentsList(); break;
    case 'get_student_details': getStudentDetails(); break;
    case 'get_professor_details': getProfessorDetails(); break;
    case 'get_unassigned_students': getUnassignedStudents(); break;
    case 'assign_student_to_adviser': assignStudentToAdviser(); break;
    case 'get_adviser_students': getAdviserStudents(); break;
    case 'remove_student_from_adviser': removeStudentFromAdviser(); break;
    case 'add_single_student': addSingleStudent(); break;
    case 'edit_student': editStudent(); break;
    case 'add_single_professor': addSingleProfessor(); break;
    case 'edit_professor': editProfessor(); break;
    case 'delete_student': deleteStudent(); break;
    case 'delete_professor': deleteProfessor(); break;
    case 'get_course_catalog': getCourseCatalog(); break;
    case 'add_course': addCourse(); break;
    case 'update_course': updateCourse(); break;
    case 'delete_course': deleteCourse(); break;
    case 'get_user_password': getUserPassword(); break;
    default: echo json_encode(['success' => false, 'message' => 'Invalid action']); break;
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
// ... Stubbing the rest for valid syntax, ensure you copy full logic ...
function addSingleStudent() { echo json_encode(['success'=>false,'message'=>'Not implemented in snippet']); }
function editStudent() { echo json_encode(['success'=>false,'message'=>'Not implemented in snippet']); }
function deleteStudent() { echo json_encode(['success'=>false,'message'=>'Not implemented in snippet']); }
function addSingleProfessor() { echo json_encode(['success'=>false,'message'=>'Not implemented in snippet']); }
function editProfessor() { echo json_encode(['success'=>false,'message'=>'Not implemented in snippet']); }
function deleteProfessor() { echo json_encode(['success'=>false,'message'=>'Not implemented in snippet']); }
function getCourseCatalog() { global $conn; $res=$conn->query("SELECT * FROM course_catalog"); $data=[]; while($r=$res->fetch_assoc())$data[]=$r; echo json_encode(['success'=>true,'courses'=>$data]); }
function addCourse() { echo json_encode(['success'=>false,'message'=>'Not implemented in snippet']); }
function updateCourse() { echo json_encode(['success'=>false,'message'=>'Not implemented in snippet']); }
function deleteCourse() { echo json_encode(['success'=>false,'message'=>'Not implemented in snippet']); }
function getUserPassword() { echo json_encode(['success'=>false,'message'=>'Not implemented in snippet']); }

?>