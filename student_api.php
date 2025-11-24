<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'auth_check.php';
require_once 'config.php';

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

switch ($action) {
    case 'get_dashboard_data':
        getDashboardData();
        break;
    case 'get_my_booklet':
        getMyBooklet();
        break;
    case 'get_my_gpa':
        getMyGPA();
        break;
    case 'get_study_plan_form':
        getStudyPlanForm();
        break;
    case 'submit_study_plan':
        submitStudyPlan();
        break;
    case 'get_my_study_plans':
        getMyStudyPlans();
        break;
    case 'submit_concern':
        submitConcern();
        break;
    case 'get_my_concerns':
        getMyConcerns();
        break;
    case 'get_adviser_info':
        getAdviserInfo();
        break;
    case 'get_available_courses':
        getAvailableCourses();
        break;
    case 'check_prerequisites':
        checkPrerequisites();
        break;
    case 'upload_grade_screenshot':
        uploadGradeScreenshot();
        break;
    case 'get_my_booklet_editable':
        getMyBookletEditable();
        break;
    case 'submit_grade_edit':
        submitGradeEdit();
        break;
    case 'get_my_edit_requests':
        getMyEditRequests();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getDashboardData() {
    global $conn, $student_id;
    
    // Get student info
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
    
    // Get latest GPA
    $stmt = $conn->prepare("
        SELECT cgpa, term_gpa 
        FROM term_gpa_summary 
        WHERE student_id = ? 
        ORDER BY academic_year DESC, term DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $gpa_result = $stmt->get_result();
    $gpa = $gpa_result->num_rows > 0 ? $gpa_result->fetch_assoc() : ['cgpa' => null, 'term_gpa' => null];
    
    // Get pending study plans count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as pending 
        FROM study_plans 
        WHERE student_id = ? AND cleared = 0
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $pending_plans = $stmt->get_result()->fetch_assoc()['pending'];
    
    // Get total courses taken
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT course_code) as total_courses
        FROM student_advising_booklet 
        WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $total_courses = $stmt->get_result()->fetch_assoc()['total_courses'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'student' => $student,
            'gpa' => $gpa,
            'pending_plans' => $pending_plans,
            'total_courses' => $total_courses
        ]
    ]);
}

function getMyBooklet() {
    global $conn, $student_id;
    
    $stmt = $conn->prepare("
        SELECT * 
        FROM student_advising_booklet 
        WHERE student_id = ? 
        ORDER BY academic_year DESC, term DESC, course_code
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    echo json_encode(['success' => true, 'records' => $records]);
}

function getMyGPA() {
    global $conn, $student_id;
    
    $stmt = $conn->prepare("
        SELECT * 
        FROM term_gpa_summary 
        WHERE student_id = ? 
        ORDER BY academic_year DESC, term DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $terms = [];
    while ($row = $result->fetch_assoc()) {
        $terms[] = $row;
    }
    
    echo json_encode(['success' => true, 'terms' => $terms]);
}

function getStudyPlanForm() {
    global $conn, $student_id;
    
    // Get student program
    $stmt = $conn->prepare("SELECT program FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $program = $stmt->get_result()->fetch_assoc()['program'];
    
    // Get available courses for student's program
    $stmt = $conn->prepare("
        SELECT course_code, course_name, units, term, prerequisites
        FROM course_catalog 
        WHERE program = ? AND is_active = 1
        ORDER BY term, course_code
    ");
    $stmt->bind_param("s", $program);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    
    echo json_encode(['success' => true, 'courses' => $courses]);
}

function submitStudyPlan() {
    global $conn, $student_id;
    
    $term = $_POST['term'] ?? '';
    $academic_year = $_POST['academic_year'] ?? '';
    $certified = isset($_POST['certified']) ? 1 : 0;
    $wants_meeting = isset($_POST['wants_meeting']) ? 1 : 0;
    $planned_subjects = json_decode($_POST['planned_subjects'] ?? '[]', true);
    
    if (empty($term) || empty($academic_year) || empty($planned_subjects)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    try {
        $conn->begin_transaction();
        
        // Insert study plan
        $stmt = $conn->prepare("
            INSERT INTO study_plans (student_id, term, academic_year, certified, wants_meeting, submission_date)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("issii", $student_id, $term, $academic_year, $certified, $wants_meeting);
        $stmt->execute();
        $plan_id = $conn->insert_id;
        
        // Insert planned subjects
        $stmt = $conn->prepare("
            INSERT INTO planned_subjects (study_plan_id, subject_code, subject_name, units)
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($planned_subjects as $subject) {
            $stmt->bind_param("issi", $plan_id, $subject['code'], $subject['name'], $subject['units']);
            $stmt->execute();
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Study plan submitted successfully',
            'plan_id' => $plan_id
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getMyStudyPlans() {
    global $conn, $student_id;
    
    $stmt = $conn->prepare("
        SELECT 
            sp.*,
            CASE 
                WHEN sp.cleared = 1 THEN 'Cleared'
                WHEN sp.adviser_feedback IS NOT NULL THEN 'Reviewed'
                ELSE 'Pending'
            END as status_text
        FROM study_plans sp
        WHERE sp.student_id = ?
        ORDER BY sp.submission_date DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $plans = [];
    while ($row = $result->fetch_assoc()) {
        // Get planned subjects for this plan
        $stmt2 = $conn->prepare("
            SELECT * FROM planned_subjects WHERE study_plan_id = ?
        ");
        $stmt2->bind_param("i", $row['id']);
        $stmt2->execute();
        $subjects_result = $stmt2->get_result();
        
        $subjects = [];
        while ($subject = $subjects_result->fetch_assoc()) {
            $subjects[] = $subject;
        }
        
        $row['subjects'] = $subjects;
        $plans[] = $row;
    }
    
    echo json_encode(['success' => true, 'plans' => $plans]);
}

function submitConcern() {
    global $conn, $student_id;
    
    $term = $_POST['term'] ?? '';
    $concern = $_POST['concern'] ?? '';
    
    if (empty($term) || empty($concern)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO student_concerns (student_id, term, concern, submission_date)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iss", $student_id, $term, $concern);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Concern submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit concern']);
    }
}

function getMyConcerns() {
    global $conn, $student_id;
    
    $stmt = $conn->prepare("
        SELECT * 
        FROM student_concerns 
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
    
    echo json_encode(['success' => true, 'concerns' => $concerns]);
}

function getAdviserInfo() {
    global $conn, $student_id;
    
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.id_number,
            CONCAT(p.first_name, ' ', p.last_name) as name,
            p.email,
            p.department
        FROM students s
        JOIN professors p ON p.id = s.advisor_id
        WHERE s.id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $adviser = $result->fetch_assoc();
        echo json_encode(['success' => true, 'adviser' => $adviser]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No adviser assigned']);
    }
}

function getAvailableCourses() {
    global $conn, $student_id;
    
    $term = $_GET['term'] ?? '';
    
    // Get student program
    $stmt = $conn->prepare("SELECT program FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $program = $stmt->get_result()->fetch_assoc()['program'];
    
    $query = "
        SELECT course_code, course_name, units, term, prerequisites, course_type
        FROM course_catalog 
        WHERE program = ? AND is_active = 1
    ";
    
    $params = [$program];
    $types = "s";
    
    if ($term) {
        $query .= " AND term = ?";
        $params[] = $term;
        $types .= "s";
    }
    
    $query .= " ORDER BY term, course_code";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    
    echo json_encode(['success' => true, 'courses' => $courses]);
}

function checkPrerequisites() {
    global $conn, $student_id;
    
    $course_code = $_GET['course_code'] ?? '';
    
    // Get course prerequisites
    $stmt = $conn->prepare("
        SELECT prerequisites FROM course_catalog WHERE course_code = ?
    ");
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Course not found']);
        return;
    }
    
    $prerequisites = $result->fetch_assoc()['prerequisites'];
    
    // Get student's completed courses
    $stmt = $conn->prepare("
        SELECT course_code, is_failed 
        FROM student_advising_booklet 
        WHERE student_id = ? AND is_failed = 0
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $completed = [];
    while ($row = $result->fetch_assoc()) {
        $completed[] = $row['course_code'];
    }
    
    echo json_encode([
        'success' => true,
        'prerequisites' => $prerequisites,
        'completed' => $completed,
        'eligible' => true // Simplified - can add complex checking later
    ]);
}

function uploadGradeScreenshot() {
    global $conn, $student_id;
    
    $plan_id = $_POST['plan_id'] ?? 0;
    
    if (!isset($_FILES['screenshot'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        return;
    }
    
    $file = $_FILES['screenshot'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG and PNG files allowed']);
        return;
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        echo json_encode(['success' => false, 'message' => 'File too large (max 5MB)']);
        return;
    }
    
    // Create uploads directory if not exists
    $upload_dir = __DIR__ . '/uploads/grades/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'grade_' . $student_id . '_' . $plan_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update study plan with screenshot path
        $stmt = $conn->prepare("UPDATE study_plans SET grade_screenshot = ? WHERE id = ? AND student_id = ?");
        $stmt->bind_param("sii", $filename, $plan_id, $student_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Screenshot uploaded successfully',
                'filename' => $filename
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
}

function getMyBookletEditable() {
    global $conn, $student_id;
    
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            COALESCE(b.approval_status, 'approved') as approval_status
        FROM student_advising_booklet b
        WHERE b.student_id = ? 
        ORDER BY b.academic_year DESC, b.term DESC, b.course_code
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    echo json_encode(['success' => true, 'records' => $records]);
}

function submitGradeEdit() {
    global $conn, $student_id;
    
    $record_id = $_POST['record_id'];
    $new_grade = $_POST['new_grade'];
    $is_failed = $_POST['is_failed'];
    $reason = $_POST['reason'];
    
    // Verify record belongs to student
    $stmt = $conn->prepare("SELECT course_code, grade FROM student_advising_booklet WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $record_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        return;
    }
    
    $record = $result->fetch_assoc();
    $old_grade = $record['grade'];
    $course_code = $record['course_code'];
    
    // Create edit request
    $stmt = $conn->prepare("
        INSERT INTO booklet_edit_requests 
        (student_id, booklet_record_id, field_name, old_value, new_value, reason, status)
        VALUES (?, ?, 'grade', ?, ?, ?, 'pending')
    ");
    $stmt->bind_param("iisss", $student_id, $record_id, $old_grade, $new_grade, $reason);
    
    if ($stmt->execute()) {
        // Update booklet record to pending approval
        $stmt = $conn->prepare("
            UPDATE student_advising_booklet 
            SET approval_status = 'pending', 
                previous_grade = grade,
                grade = ?,
                is_failed = ?,
                edit_requested_at = NOW(),
                modified_by = 'student'
            WHERE id = ?
        ");
        $stmt->bind_param("dii", $new_grade, $is_failed, $record_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Edit request submitted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
    }
}

function getMyEditRequests() {
    global $conn, $student_id;
    
    $stmt = $conn->prepare("
        SELECT 
            er.*,
            b.course_code,
            b.course_name
        FROM booklet_edit_requests er
        JOIN student_advising_booklet b ON b.id = er.booklet_record_id
        WHERE er.student_id = ?
        ORDER BY er.requested_at DESC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    
    echo json_encode(['success' => true, 'requests' => $requests]);
}