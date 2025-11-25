<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'auth_check.php';
require_once 'config.php';

// Basic Auth Check
if (!isAuthenticated() || $_SESSION['user_type'] !== 'student') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

ob_clean();
header('Content-Type: application/json');

$student_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        // Dashboard
        case 'get_dashboard_data': getDashboardData(); break;
        
        // Booklet
        case 'get_my_booklet': getMyBookletEditable(); break; // Redirect to editable version
        case 'get_my_booklet_editable': getMyBookletEditable(); break;
        case 'submit_grade_edit': submitGradeEdit(); break;
        case 'get_my_edit_requests': getMyEditRequests(); break;
        
        // GPA
        case 'get_my_gpa': getMyGPA(); break;
        
        // Advising Form
        case 'get_study_plan_form': getStudyPlanForm(); break;
        case 'submit_study_plan': submitStudyPlan(); break;
        case 'get_my_study_plans': getMyStudyPlans(); break;
        
        // Concerns (RESTORED)
        case 'submit_concern': submitConcern(); break;
        case 'get_my_concerns': getMyConcerns(); break;
        
        // Advisers & Courses
        case 'get_adviser_info': getAdviserInfo(); break;
        case 'get_available_courses': getAvailableCourses(); break;
        case 'check_prerequisites': checkPrerequisites(); break;
        case 'upload_grade_screenshot': uploadGradeScreenshot(); break;
        
        default: 
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ---------------------------------------------------------
// CONCERN FUNCTIONS (Restored)
// ---------------------------------------------------------

function submitConcern() {
    global $conn, $student_id;
    
    $term = $_POST['term'] ?? '';
    $concern = $_POST['concern'] ?? '';
    
    if (empty($term) || empty($concern)) {
        throw new Exception('Please fill in all required fields');
    }
    
    $stmt = $conn->prepare("
        INSERT INTO student_concerns (student_id, term, concern, submission_date)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iss", $student_id, $term, $concern);
    
    if ($stmt->execute()) {
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Concern submitted successfully']);
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }
}

function getMyConcerns() {
    global $conn, $student_id;
    
    $stmt = $conn->prepare("
        SELECT * FROM student_concerns 
        WHERE student_id = ? 
        ORDER BY submission_date DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $concerns = [];
    while ($row = $result->fetch_assoc()) {
        $concerns[] = $row;
    }
    
    ob_clean();
    echo json_encode(['success' => true, 'concerns' => $concerns]);
}

// ---------------------------------------------------------
// DASHBOARD & CORE FUNCTIONS
// ---------------------------------------------------------

function getDashboardData() {
    global $conn, $student_id;
    
    // Student Info
    $stmt = $conn->prepare("SELECT s.*, CONCAT(s.first_name, ' ', s.last_name) as full_name, CONCAT(p.first_name, ' ', p.last_name) as adviser_name, p.email as adviser_email FROM students s LEFT JOIN professors p ON p.id = s.advisor_id WHERE s.id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    
    // Calculate Stats from Booklet
    $stmt = $conn->prepare("SELECT units, grade, is_failed FROM student_advising_booklet WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $total_units = 0;
    $grade_points = 0;
    $failed_units = 0;
    $total_courses = 0;

    while ($row = $result->fetch_assoc()) {
        $total_courses++;
        $units = floatval($row['units']);
        $grade = floatval($row['grade']);
        
        if ($row['is_failed'] == 1 || ($row['grade'] !== null && $grade == 0)) {
            $failed_units += $units;
        }
        
        if ($row['grade'] !== null && $row['grade'] !== '') {
            $total_units += $units;
            $grade_points += ($units * $grade);
        }
    }

    $cgpa = $total_units > 0 ? round($grade_points / $total_units, 3) : 0;
    $student['accumulated_failed_units'] = $failed_units; // Update student record display
    
    // Pending Plans
    $stmt = $conn->prepare("SELECT COUNT(*) as pending FROM academic_advising_forms WHERE student_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $pending = $stmt->get_result()->fetch_assoc()['pending'];
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'student' => $student,
            'gpa' => ['cgpa' => number_format($cgpa, 3), 'term_gpa' => '-'],
            'pending_plans' => $pending,
            'total_courses' => $total_courses
        ]
    ]);
}

// ---------------------------------------------------------
// BOOKLET & EDIT FUNCTIONS
// ---------------------------------------------------------

function getMyBookletEditable() {
    global $conn, $student_id;
    $stmt = $conn->prepare("SELECT b.*, COALESCE(b.approval_status, 'approved') as approval_status FROM student_advising_booklet b WHERE b.student_id = ? ORDER BY b.academic_year ASC, b.term ASC, b.course_code ASC");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $records = [];
    while ($row = $result->fetch_assoc()) $records[] = $row;
    ob_clean();
    echo json_encode(['success' => true, 'records' => $records]);
}

function submitGradeEdit() {
    global $conn, $student_id;
    $record_id = $_POST['record_id'] ?? null;
    $new_grade = $_POST['new_grade'] ?? null;
    $is_failed = $_POST['is_failed'] ?? 0;
    $reason = $_POST['reason'] ?? '';
    
    if (!$record_id) throw new Exception('Record ID missing');

    $stmt = $conn->prepare("SELECT grade FROM student_advising_booklet WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $record_id, $student_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) throw new Exception('Record not found');
    
    $stmt = $conn->prepare("INSERT INTO booklet_edit_requests (student_id, booklet_record_id, field_name, old_value, new_value, reason, status) VALUES (?, ?, 'grade', 'old', ?, ?, 'pending')");
    $val = "$new_grade" . ($is_failed ? " (Failed)" : "");
    $stmt->bind_param("iisss", $student_id, $record_id, $val, $reason);
    
    if ($stmt->execute()) {
        $conn->query("UPDATE student_advising_booklet SET approval_status = 'pending', grade = '$new_grade', is_failed = $is_failed WHERE id = $record_id");
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Request submitted']);
    } else {
        throw new Exception('DB Error');
    }
}

function getMyEditRequests() {
    global $conn, $student_id;
    $stmt = $conn->prepare("SELECT er.*, b.course_code FROM booklet_edit_requests er JOIN student_advising_booklet b ON b.id = er.booklet_record_id WHERE er.student_id = ? ORDER BY er.requested_at DESC");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while($row = $res->fetch_assoc()) $data[] = $row;
    ob_clean();
    echo json_encode(['success' => true, 'requests' => $data]);
}

// ---------------------------------------------------------
// OTHER HELPERS
// ---------------------------------------------------------

function getAdviserInfo() {
    global $conn, $student_id;
    $stmt = $conn->prepare("SELECT p.id, p.id_number, CONCAT(p.first_name, ' ', p.last_name) as name, p.email, p.department FROM students s JOIN professors p ON p.id = s.advisor_id WHERE s.id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if($res->num_rows > 0) {
        echo json_encode(['success'=>true, 'adviser'=>$res->fetch_assoc()]);
    } else {
        echo json_encode(['success'=>false, 'message'=>'No adviser']);
    }
}

// Stub for getMyGPA if called directly (redirects to dashboard logic)
function getMyGPA() { getDashboardData(); }

// Stub for functions not used in current flow but kept for compatibility
function getStudyPlanForm() { echo json_encode(['success'=>false, 'message'=>'Use Advising Form']); }
function submitStudyPlan() { echo json_encode(['success'=>false, 'message'=>'Use Advising Form']); }
function getMyStudyPlans() { echo json_encode(['success'=>false, 'message'=>'Use Advising Form']); }
function getAvailableCourses() { echo json_encode(['success'=>false]); }
function checkPrerequisites() { echo json_encode(['success'=>true]); }
function uploadGradeScreenshot() { echo json_encode(['success'=>true]); }

?>