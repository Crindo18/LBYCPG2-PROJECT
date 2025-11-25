<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

ob_clean();
header('Content-Type: application/json');

$student_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'submit_advising_form') {
    submitAdvisingForm();
} elseif ($action === 'get_advising_forms') {
    getAdvisingForms();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function submitAdvisingForm() {
    global $conn, $student_id;
    
    try {
        // Validate required fields
        $required_fields = ['academic_year', 'term', 'max_units', 'total_enrolled_units'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Handle file uploads
        $uploadDir = 'uploads/advising_forms/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Upload grade screenshot
        $gradeTargetPath = null;
        if (isset($_FILES['grade_screenshot']) && $_FILES['grade_screenshot']['error'] === UPLOAD_ERR_OK) {
            $gradeScreenshot = $_FILES['grade_screenshot'];
            $gradeExt = strtolower(pathinfo($gradeScreenshot['name'], PATHINFO_EXTENSION));
            $gradeFilename = $student_id . '_' . time() . '_grade.' . $gradeExt;
            $gradeTargetPath = $uploadDir . $gradeFilename;
            
            if (!move_uploaded_file($gradeScreenshot['tmp_name'], $gradeTargetPath)) {
                throw new Exception('Failed to save grade screenshot');
            }
        }
        
        // Parse current courses
        $currentCourses = json_decode($_POST['current_courses'], true);
        if (!$currentCourses || !is_array($currentCourses)) {
            throw new Exception('Invalid current courses data');
        }
        
        // Extract integer from Term
        $termStr = $_POST['term'];
        $termNum = (int) filter_var($termStr, FILTER_SANITIZE_NUMBER_INT);
        if ($termNum == 0 && is_numeric($termStr)) $termNum = (int)$termStr;
        
        $conn->begin_transaction();
        
        // 1. Insert Form Request
        $formDataArray = [
            'academic_year' => $_POST['academic_year'],
            'term' => $_POST['term'],
            'current_year_failed_units' => $_POST['current_year_failed_units'] ?? 0,
            'overall_failed_units' => $_POST['overall_failed_units'] ?? 0,
            'previous_term_gpa' => $_POST['previous_term_gpa'] ?? 0,
            'cumulative_gpa' => $_POST['cumulative_gpa'] ?? 0,
            'trimestral_honors' => $_POST['trimestral_honors'] ?? '',
            'max_course_load_units' => $_POST['max_units'],
            'total_enrolled_units' => $_POST['total_enrolled_units'],
            'additional_notes' => $_POST['additional_notes'] ?? '',
            'request_meeting' => $_POST['request_meeting'] ?? 0
        ];
        $formDataJson = json_encode($formDataArray);

        $stmt = $conn->prepare("INSERT INTO academic_advising_forms (student_id, form_data, grades_screenshot, booklet_file, status, submitted_at) VALUES (?, ?, ?, NULL, 'pending', NOW())");
        $stmt->bind_param("iss", $student_id, $formDataJson, $gradeTargetPath);
        if (!$stmt->execute()) throw new Exception('Failed to insert form');
        $formId = $conn->insert_id;
        
        // 2. Insert Courses into advising_form_courses & student_advising_booklet
        $stmtFormCourse = $conn->prepare("INSERT INTO advising_form_courses (form_id, course_type, course_code, units) VALUES (?, 'current', ?, ?)");
        
        // CRITICAL FIX HERE: Added correct number of type characters ("isisss")
        $stmtBooklet = $conn->prepare("
            INSERT INTO student_advising_booklet 
            (student_id, academic_year, term, course_code, course_name, units, grade, is_failed, approval_status, remarks, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NULL, 0, 'pending', 'Enrolled', NOW())
        ");

        foreach ($currentCourses as $course) {
            // Add to Form
            $stmtFormCourse->bind_param("isi", $formId, $course['code'], $course['units']);
            $stmtFormCourse->execute();
            
            // Add to Booklet
            // Fixed: "isisss" matches (int, string, int, string, string, string)
            // Note: units passed as string/int is fine with 's'
            $stmtBooklet->bind_param(
                "isisss", 
                $student_id, 
                $_POST['academic_year'], 
                $termNum, 
                $course['code'], 
                $course['name'], 
                $course['units']
            );
            if (!$stmtBooklet->execute()) throw new Exception("Booklet insert failed: " . $stmtBooklet->error);
        }
        
        if (isset($_POST['overall_failed_units'])) {
            $stmt = $conn->prepare("UPDATE students SET accumulated_failed_units = ? WHERE id = ?");
            $stmt->bind_param("ii", $_POST['overall_failed_units'], $student_id);
            $stmt->execute();
        }
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Academic advising form submitted successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getAdvisingForms() {
    global $conn, $student_id;
    try {
        $stmt = $conn->prepare("SELECT * FROM academic_advising_forms WHERE student_id = ? ORDER BY submitted_at DESC");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $forms = [];
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['form_data'])) {
                $jsonData = json_decode($row['form_data'], true);
                if (is_array($jsonData)) $row = array_merge($row, $jsonData);
            }
            $row['adviser_feedback'] = $row['adviser_comments'] ?? '';
            $row['submission_date'] = $row['submitted_at'];
            $row['cleared'] = ($row['status'] === 'approved') ? 1 : 0;
            $forms[] = $row;
        }
        echo json_encode(['success' => true, 'forms' => $forms]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>