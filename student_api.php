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
        case 'get_dashboard_data': getDashboardData(); break;
        case 'get_my_booklet': getMyBooklet(); break;
        case 'get_my_gpa': getMyGPA(); break;
        case 'get_study_plan_form': getStudyPlanForm(); break;
        case 'submit_study_plan': submitStudyPlan(); break;
        case 'get_my_study_plans': getMyStudyPlans(); break;
        case 'submit_concern': submitConcern(); break;
        case 'get_my_concerns': getMyConcerns(); break;
        case 'get_adviser_info': getAdviserInfo(); break;
        case 'get_available_courses': getAvailableCourses(); break;
        case 'check_prerequisites': checkPrerequisites(); break;
        case 'upload_grade_screenshot': uploadGradeScreenshot(); break;
        case 'get_my_booklet_editable': getMyBookletEditable(); break;
        case 'submit_grade_edit': submitGradeEdit(); break;
        case 'get_my_edit_requests': getMyEditRequests(); break;
        default: echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ---------------------------------------------------------
// CORE DASHBOARD FUNCTION (UPDATED)
// ---------------------------------------------------------
function getDashboardData() {
    global $conn, $student_id;
    
    // 1. Get Student Info
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            CONCAT(p.first_name, ' ', p.last_name) as adviser_name,
            p.email as adviser_email
        FROM students s
        LEFT JOIN professors p ON p.id = s.advisor_id
        WHERE s.id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    
    // 2. DYNAMIC CALCULATION: Get stats directly from Booklet instead of static summary table
    $stmt = $conn->prepare("
        SELECT academic_year, term, units, grade, is_failed 
        FROM student_advising_booklet 
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $total_units_taken = 0;
    $total_grade_points = 0;
    $total_failed_units = 0;
    $total_courses = 0;

    // Variables to track "Latest Term"
    $latest_year = '';
    $latest_term = 0;
    $term_units = 0;
    $term_points = 0;

    while ($row = $result->fetch_assoc()) {
        $total_courses++;
        $units = floatval($row['units']);
        $gradeStr = $row['grade'];
        $is_failed = $row['is_failed'];

        // Check if this is the latest term we've seen so far
        // Logic: If Year is greater, OR Year is same but Term is greater
        $is_current_row_newer = ($row['academic_year'] > $latest_year) || 
                                ($row['academic_year'] == $latest_year && $row['term'] > $latest_term);

        if ($is_current_row_newer) {
            $latest_year = $row['academic_year'];
            $latest_term = $row['term'];
            // Reset term counters because we found a newer term
            $term_units = 0;
            $term_points = 0;
        }

        // Count Failures
        if ($is_failed == 1 || ($gradeStr !== null && floatval($gradeStr) == 0.0)) {
            $total_failed_units += $units;
        }

        // Calculate GPA (only if grade is present)
        if ($gradeStr !== null && $gradeStr !== '') {
            $gradeVal = floatval($gradeStr);
            
            // Add to Cumulative
            $total_units_taken += $units;
            $total_grade_points += ($units * $gradeVal);

            // Add to Term (if it matches latest)
            if ($row['academic_year'] == $latest_year && $row['term'] == $latest_term) {
                $term_units += $units;
                $term_points += ($units * $gradeVal);
            }
        }
    }

    // Final GPA Math
    $cgpa = $total_units_taken > 0 ? round($total_grade_points / $total_units_taken, 3) : 0;
    $term_gpa = $term_units > 0 ? round($term_points / $term_units, 3) : 0;

    $gpa_data = [
        'cgpa' => number_format($cgpa, 3),
        'term_gpa' => number_format($term_gpa, 3)
    ];

    // Overwrite student failure count with the real count from booklet
    $student['accumulated_failed_units'] = $total_failed_units;
    
    // 3. Get Pending Plans Count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as pending 
        FROM academic_advising_forms 
        WHERE student_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $pending_plans = $stmt->get_result()->fetch_assoc()['pending'];
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'student' => $student,
            'gpa' => $gpa_data,
            'pending_plans' => $pending_plans,
            'total_courses' => $total_courses
        ]
    ]);
}

// ---------------------------------------------------------
// BOOKLET & EDIT FUNCTIONS (Required for Booklet Page)
// ---------------------------------------------------------

function getMyBookletEditable() {
    global $conn, $student_id;
    $stmt = $conn->prepare("
        SELECT b.*, COALESCE(b.approval_status, 'approved') as approval_status
        FROM student_advising_booklet b
        WHERE b.student_id = ? 
        ORDER BY b.academic_year ASC, b.term ASC, b.course_code ASC
    ");
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Invalid Request Method');

    $record_id = $_POST['record_id'] ?? null;
    $new_grade = $_POST['new_grade'] ?? null;
    $is_failed = $_POST['is_failed'] ?? 0;
    $reason = $_POST['reason'] ?? '';
    
    if (!$record_id) throw new Exception('Record ID missing');

    $stmt = $conn->prepare("SELECT course_code, grade FROM student_advising_booklet WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $record_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) throw new Exception('Record not found');
    $record = $result->fetch_assoc();
    $old_grade = $record['grade'] ?? 'N/A';
    
    $stmt = $conn->prepare("INSERT INTO booklet_edit_requests (student_id, booklet_record_id, field_name, old_value, new_value, reason, status) VALUES (?, ?, 'grade', ?, ?, ?, 'pending')");
    $new_val_str = $new_grade . ($is_failed == 1 ? ' (Failed)' : '');
    
    $stmt->bind_param("iisss", $student_id, $record_id, $old_grade, $new_val_str, $reason);
    
    if ($stmt->execute()) {
        $stmt = $conn->prepare("UPDATE student_advising_booklet SET approval_status = 'pending', previous_grade = grade, grade = ?, is_failed = ?, edit_requested_at = NOW(), modified_by = 'student' WHERE id = ?");
        $stmt->bind_param("dii", $new_grade, $is_failed, $record_id);
        $stmt->execute();
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Edit request submitted']);
    } else {
        throw new Exception('Failed to submit request');
    }
}

function getMyEditRequests() {
    global $conn, $student_id;
    $stmt = $conn->prepare("SELECT er.*, b.course_code, b.course_name FROM booklet_edit_requests er JOIN student_advising_booklet b ON b.id = er.booklet_record_id WHERE er.student_id = ? ORDER BY er.requested_at DESC");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $requests = [];
    while ($row = $result->fetch_assoc()) $requests[] = $row;
    ob_clean();
    echo json_encode(['success' => true, 'requests' => $requests]);
}

// --- Stubs for other functions to prevent errors if called ---
function getMyBooklet() { getMyBookletEditable(); }
function getMyGPA() { 
    // Redirect to dashboard logic if specific GPA endpoint called
    getDashboardData();
}
function getStudyPlanForm() {}
function submitStudyPlan() {}
function getMyStudyPlans() {}
function submitConcern() {}
function getMyConcerns() {}
function getAdviserInfo() {
    global $conn, $student_id;
    $stmt = $conn->prepare("SELECT p.id, p.id_number, CONCAT(p.first_name, ' ', p.last_name) as name, p.email, p.department FROM students s JOIN professors p ON p.id = s.advisor_id WHERE s.id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if($res->num_rows > 0) echo json_encode(['success'=>true, 'adviser'=>$res->fetch_assoc()]);
    else echo json_encode(['success'=>false, 'message'=>'No adviser']);
}
function getAvailableCourses() {}
function checkPrerequisites() {}
function uploadGradeScreenshot() {}
?>