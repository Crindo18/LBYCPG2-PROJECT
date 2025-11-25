<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'auth_check.php';
require_once 'config.php';

if (!isAuthenticated() || $_SESSION['user_type'] !== 'professor') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

ob_clean();
header('Content-Type: application/json');

$professor_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_dashboard_stats':
        getDashboardStats();
        break;
    case 'get_recent_plans':
        getRecentPlans();
        break;
    case 'get_recent_advising_forms':
        getRecentAdvisingForms();
        break;
    case 'get_attention_students':
        getAttentionStudents();
        break;
    case 'get_my_advisees':
        getMyAdvisees();
        break;
    case 'get_student_details':
        getStudentDetails();
        break;
    case 'get_study_plans':
        getStudyPlans();
        break;
    case 'get_study_plan_details':
        getStudyPlanDetails();
        break;
    case 'get_student_booklet':
        getStudentBooklet();
        break;
    case 'get_student_gpa':
        getStudentGPA();
        break;
    case 'get_student_study_plans':
        getStudentStudyPlans();
        break;
    case 'get_student_concerns':
        getStudentConcerns();
        break;
    case 'clear_student':
        clearStudent();
        break;
    case 'unclear_student':
        unclearStudent();
        break;
    case 'approve_study_plan':
        approveStudyPlan();
        break;
    case 'reject_study_plan':
        rejectStudyPlan();
        break;
    case 'add_feedback':
        addFeedback();
        break;
    case 'request_screenshot_reupload':
        requestScreenshotReupload();
        break;
    // NEW ACTIONS FOR ACADEMIC ADVISING TAB
    case 'set_deadline':
        setAdvisingDeadline();
        break;
    case 'get_current_deadline':
        getCurrentDeadline();
        break;
    case 'get_students':
        getStudentsForAdvising();
        break;
    case 'get_submission_details':
        getSubmissionDetails();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// ==================== EXISTING FUNCTIONS ====================

function getDashboardStats() {
    global $conn, $professor_id;
    
    // Total advisees
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM students WHERE advisor_id = ?");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $total_advisees = $stmt->get_result()->fetch_assoc()['total'];
    
    // Pending advising forms
    $stmt = $conn->prepare("
        SELECT COUNT(*) as pending 
        FROM academic_advising_forms aaf
        JOIN students s ON s.id = aaf.student_id
        WHERE s.advisor_id = ? AND aaf.status = 'pending'
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $pending_forms = $stmt->get_result()->fetch_assoc()['pending'];
    
    // Cleared students
    $stmt = $conn->prepare("SELECT COUNT(*) as cleared FROM students WHERE advisor_id = ? AND advising_cleared = 1");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $cleared_students = $stmt->get_result()->fetch_assoc()['cleared'];
    
    // At-risk students
    $stmt = $conn->prepare("SELECT COUNT(*) as at_risk FROM students WHERE advisor_id = ? AND accumulated_failed_units >= 15");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $at_risk_students = $stmt->get_result()->fetch_assoc()['at_risk'];
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_advisees' => $total_advisees,
            'pending_forms' => $pending_forms,
            'cleared_students' => $cleared_students,
            'at_risk_students' => $at_risk_students
        ]
    ]);
}


function getRecentPlans() {
    global $conn, $professor_id;

    $stmt = $conn->prepare("
        SELECT
            sp.id as plan_id,
            sp.submission_date as created_at,
            s.id,
            s.id_number,
            s.program,
            CONCAT(s.first_name, ' ', s.last_name) as student_name
        FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        WHERE s.advisor_id = ?
        ORDER BY sp.submission_date DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $plans = [];
    while ($row = $result->fetch_assoc()) {
        $plans[] = $row;
    }

    echo json_encode(['success' => true, 'plans' => $plans]);
}

function getRecentAdvisingForms() {
    global $conn, $professor_id;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                aaf.id as form_id,
                aaf.student_id,
                s.id_number,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.program,
                aaf.form_data,
                aaf.status,
                aaf.submitted_at
            FROM academic_advising_forms aaf
            JOIN students s ON s.id = aaf.student_id
            WHERE s.advisor_id = ?
            ORDER BY aaf.submitted_at DESC
            LIMIT 10
        ");
        
        $stmt->bind_param("i", $professor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $forms = [];
        while ($row = $result->fetch_assoc()) {
            // Parse JSON form data
            $formData = json_decode($row['form_data'], true);
            
            $form = [
                'form_id' => $row['form_id'],
                'student_id' => $row['student_id'],
                'id_number' => $row['id_number'],
                'student_name' => $row['student_name'],
                'program' => $row['program'],
                'academic_year' => $formData['academic_year'] ?? 'N/A',
                'term' => $formData['term'] ?? 'N/A',
                'status' => $row['status'],
                'submitted_at' => $row['submitted_at']
            ];
            
            $forms[] = $form;
        }
        
        echo json_encode([
            'success' => true,
            'forms' => $forms
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error loading forms: ' . $e->getMessage()
        ]);
    }
}


function getAttentionStudents() {
    global $conn, $professor_id;

    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.id_number,
            s.program,
            s.accumulated_failed_units,
            s.advising_cleared,
            CONCAT(s.first_name, ' ', s.last_name) as full_name
        FROM students s
        WHERE s.advisor_id = ?
        AND (s.accumulated_failed_units >= 15 OR s.advising_cleared = 0)
        ORDER BY s.accumulated_failed_units DESC
        LIMIT 10
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

function getMyAdvisees() {
    global $conn, $professor_id;

    $search = $_GET['search'] ?? '';
    $program = $_GET['program'] ?? '';

    $query = "
        SELECT
            s.id,
            s.id_number,
            s.program,
            s.email,
            s.phone_number,
            s.accumulated_failed_units,
            s.advising_cleared,
            CONCAT(s.first_name, ' ', s.last_name) as full_name
        FROM students s
        WHERE s.advisor_id = ?
    ";

    $params = [$professor_id];
    $types = "i";

    if ($search) {
        $query .= " AND (s.id_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "ssss";
    }

    if ($program) {
        $query .= " AND s.program = ?";
        $params[] = $program;
        $types .= "s";
    }

    $query .= " ORDER BY s.last_name, s.first_name";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    echo json_encode(['success' => true, 'students' => $students]);
}

function getStudentDetails() {
    global $conn, $professor_id;

    $student_id = $_GET['student_id'];

    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ? AND advisor_id = ?");
    $stmt->bind_param("ii", $student_id, $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stmt = $conn->prepare("SELECT COUNT(*) as plan_count FROM study_plans WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $plan_count = $stmt->get_result()->fetch_assoc()['plan_count'];

        $row['plan_count'] = $plan_count;

        echo json_encode(['success' => true, 'student' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
}

function getStudyPlans() {
    global $conn, $professor_id;

    $status = $_GET['status'] ?? 'all';
    $student_id = $_GET['student_id'] ?? null;

    $query = "
        SELECT
            sp.id,
            sp.student_id,
            sp.academic_year,
            sp.term,
            sp.adviser_feedback,
            sp.submission_date as created_at,
            s.id_number,
            s.program,
            CONCAT(s.first_name, ' ', s.last_name) as student_name
        FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        WHERE s.advisor_id = ?
    ";

    $params = [$professor_id];
    $types = "i";

    if ($student_id) {
        $query .= " AND sp.student_id = ?";
        $params[] = $student_id;
        $types .= "i";
    }

    $query .= " ORDER BY sp.submission_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $plans = [];
    while ($row = $result->fetch_assoc()) {
        $plans[] = $row;
    }

    echo json_encode(['success' => true, 'plans' => $plans]);
}

function getStudyPlanDetails() {
    global $conn, $professor_id;

    $plan_id = $_GET['plan_id'];

    $stmt = $conn->prepare("
        SELECT
            sp.*,
            s.id_number,
            s.program,
            s.accumulated_failed_units,
            CONCAT(s.first_name, ' ', s.last_name) as student_name
        FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        WHERE sp.id = ? AND s.advisor_id = ?
    ");
    $stmt->bind_param("ii", $plan_id, $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($plan = $result->fetch_assoc()) {
        $stmt = $conn->prepare("SELECT * FROM planned_subjects WHERE study_plan_id = ?");
        $stmt->bind_param("i", $plan_id);
        $stmt->execute();
        $subjects_result = $stmt->get_result();

        $subjects = [];
        while ($row = $subjects_result->fetch_assoc()) {
            $subjects[] = $row;
        }

        $plan['subjects'] = $subjects;

        echo json_encode(['success' => true, 'plan' => $plan]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Study plan not found']);
    }
}

function getStudentBooklet() {
    global $conn, $professor_id;

    $student_id = $_GET['student_id'];

    $stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND advisor_id = ?");
    $stmt->bind_param("ii", $student_id, $professor_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM student_advising_booklet WHERE student_id = ? ORDER BY academic_year, term, course_code");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }

    echo json_encode(['success' => true, 'records' => $records]);
}

function getStudentGPA() {
    global $conn, $professor_id;

    $student_id = $_GET['student_id'];

    $stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND advisor_id = ?");
    $stmt->bind_param("ii", $student_id, $professor_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM term_gpa_summary WHERE student_id = ? ORDER BY academic_year, term");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $terms = [];
    while ($row = $result->fetch_assoc()) {
        $terms[] = $row;
    }

    echo json_encode(['success' => true, 'terms' => $terms]);
}

function getStudentStudyPlans() {
    global $conn, $professor_id;

    $student_id = $_GET['student_id'];

    $stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND advisor_id = ?");
    $stmt->bind_param("ii", $student_id, $professor_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM study_plans WHERE student_id = ? ORDER BY submission_date DESC");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $plans = [];
    while ($row = $result->fetch_assoc()) {
        $plans[] = $row;
    }

    echo json_encode(['success' => true, 'plans' => $plans]);
}

function getStudentConcerns() {
    global $conn, $professor_id;

    $student_id = $_GET['student_id'];

    $stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND advisor_id = ?");
    $stmt->bind_param("ii", $student_id, $professor_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM student_concerns WHERE student_id = ? ORDER BY submission_date DESC");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $concerns = [];
    while ($row = $result->fetch_assoc()) {
        $concerns[] = $row;
    }

    echo json_encode(['success' => true, 'concerns' => $concerns]);
}

function clearStudent() {
    global $conn, $professor_id;

    $student_id = $_POST['student_id'];

    $stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND advisor_id = ?");
    $stmt->bind_param("ii", $student_id, $professor_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $stmt = $conn->prepare("UPDATE students SET advising_cleared = 1 WHERE id = ?");
    $stmt->bind_param("i", $student_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Student cleared']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to clear student']);
    }
}

function unclearStudent() {
    global $conn, $professor_id;

    $student_id = $_POST['student_id'];

    $stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND advisor_id = ?");
    $stmt->bind_param("ii", $student_id, $professor_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $stmt = $conn->prepare("UPDATE students SET advising_cleared = 0 WHERE id = ?");
    $stmt->bind_param("i", $student_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Clearance revoked']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to revoke']);
    }
}

function approveStudyPlan() {
    global $conn, $professor_id;

    $plan_id = $_POST['plan_id'];
    $feedback = $_POST['feedback'] ?? '';

    // Verify ownership
    $stmt = $conn->prepare("
        SELECT sp.id FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        WHERE sp.id = ? AND s.advisor_id = ?
    ");
    $stmt->bind_param("ii", $plan_id, $professor_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    // Update study plan
    $stmt = $conn->prepare("UPDATE study_plans SET cleared = 1, adviser_feedback = ? WHERE id = ?");
    $stmt->bind_param("si", $feedback, $plan_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Study plan approved']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to approve plan']);
    }
}

function rejectStudyPlan() {
    global $conn, $professor_id;

    $plan_id = $_POST['plan_id'];
    $feedback = $_POST['feedback'] ?? '';

    if (empty($feedback)) {
        echo json_encode(['success' => false, 'message' => 'Feedback is required when rejecting']);
        return;
    }

    // Verify ownership
    $stmt = $conn->prepare("
        SELECT sp.id FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        WHERE sp.id = ? AND s.advisor_id = ?
    ");
    $stmt->bind_param("ii", $plan_id, $professor_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    // Update study plan - mark as not cleared and add feedback
    $stmt = $conn->prepare("UPDATE study_plans SET cleared = 0, adviser_feedback = ? WHERE id = ?");
    $stmt->bind_param("si", $feedback, $plan_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Study plan rejected']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject plan']);
    }
}

function addFeedback() {
    global $conn, $professor_id;

    $plan_id = $_POST['plan_id'];
    $feedback = $_POST['feedback'] ?? '';

    // Verify ownership
    $stmt = $conn->prepare("
        SELECT sp.id FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        WHERE sp.id = ? AND s.advisor_id = ?
    ");
    $stmt->bind_param("ii", $plan_id, $professor_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $stmt = $conn->prepare("UPDATE study_plans SET adviser_feedback = ? WHERE id = ?");
    $stmt->bind_param("si", $feedback, $plan_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Feedback added']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add feedback']);
    }
}

function requestScreenshotReupload() {
    global $conn, $professor_id;

    $plan_id = $_POST['plan_id'];
    $reason = $_POST['reason'] ?? '';

    // Verify ownership
    $stmt = $conn->prepare("
        SELECT sp.id FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        WHERE sp.id = ? AND s.advisor_id = ?
    ");
    $stmt->bind_param("ii", $plan_id, $professor_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $stmt = $conn->prepare("UPDATE study_plans SET screenshot_reupload_requested = 1, reupload_reason = ? WHERE id = ?");
    $stmt->bind_param("si", $reason, $plan_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Reupload requested']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to request reupload']);
    }
}

// ==================== NEW FUNCTIONS FOR ACADEMIC ADVISING TAB ====================

function setAdvisingDeadline() {
    global $conn, $professor_id;

    $term = $_POST['term'] ?? '';
    $deadline_date = $_POST['deadline_date'] ?? '';

    if (empty($term) || empty($deadline_date)) {
        echo json_encode(['success' => false, 'message' => 'Term and deadline date are required']);
        return;
    }

    try {
        // Check if deadline exists for this term
        $stmt = $conn->prepare("SELECT id FROM advising_deadlines WHERE term = ? AND professor_id = ?");
        $stmt->bind_param("si", $term, $professor_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update existing
            $stmt = $conn->prepare("UPDATE advising_deadlines SET deadline_date = ?, updated_at = NOW() WHERE term = ? AND professor_id = ?");
            $stmt->bind_param("ssi", $deadline_date, $term, $professor_id);
            $stmt->execute();
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO advising_deadlines (professor_id, term, deadline_date, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iss", $professor_id, $term, $deadline_date);
            $stmt->execute();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Deadline set successfully',
            'term' => $term,
            'deadline' => date('F d, Y', strtotime($deadline_date))
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function getCurrentDeadline() {
    global $conn, $professor_id;

    try {
        $stmt = $conn->prepare("
            SELECT term, deadline_date
            FROM advising_deadlines
            WHERE professor_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $professor_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                'success' => true,
                'deadline' => [
                    'term' => $row['term'],
                    'deadline_date' => date('F d, Y', strtotime($row['deadline_date']))
                ]
            ]);
        } else {
            echo json_encode(['success' => true, 'deadline' => null]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function getStudentsForAdvising() {
    global $conn, $professor_id;

    $filter = $_GET['filter'] ?? 'all';
    $search = $_GET['search'] ?? '';

    try {
        $sql = "
            SELECT
                s.id,
                s.id_number,
                CONCAT(s.first_name, ' ', s.last_name) as name,
                s.program,
                s.advising_cleared,
                sp.submission_date,
                sp.wants_meeting,
                sp.cleared as plan_cleared,
                sp.id as plan_id
            FROM students s
            LEFT JOIN (
                SELECT student_id, MAX(id) as latest_plan
                FROM study_plans
                WHERE academic_year = (SELECT MAX(academic_year) FROM study_plans)
                GROUP BY student_id
            ) latest ON s.id = latest.student_id
            LEFT JOIN study_plans sp ON sp.id = latest.latest_plan
            WHERE s.advisor_id = ?
        ";

        $params = [$professor_id];
        $types = "i";

        // Add filter conditions
        if ($filter === 'completed') {
            $sql .= " AND s.advising_cleared = 1";
        } elseif ($filter === 'pending') {
            $sql .= " AND sp.id IS NOT NULL AND sp.cleared = 0";
        } elseif ($filter === 'not-submitted') {
            $sql .= " AND sp.id IS NULL";
        }

        // Add search conditions
        if (!empty($search)) {
            $sql .= " AND (s.id_number LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "ss";
        }

        $sql .= " ORDER BY s.id_number";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $students = [];
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }

        // Calculate counts
        $stmt = $conn->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN s.advising_cleared = 1 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN sp.id IS NOT NULL AND sp.cleared = 0 THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN sp.id IS NULL THEN 1 ELSE 0 END) as not_submitted
            FROM students s
            LEFT JOIN (
                SELECT student_id, MAX(id) as latest_plan
                FROM study_plans
                WHERE academic_year = (SELECT MAX(academic_year) FROM study_plans)
                GROUP BY student_id
            ) latest ON s.id = latest.student_id
            LEFT JOIN study_plans sp ON sp.id = latest.latest_plan
            WHERE s.advisor_id = ?
        ");
        $stmt->bind_param("i", $professor_id);
        $stmt->execute();
        $counts = $stmt->get_result()->fetch_assoc();

        echo json_encode([
            'success' => true,
            'students' => $students,
            'counts' => $counts
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function getSubmissionDetails() {
    global $conn, $professor_id;

    $student_id = $_GET['student_id'] ?? 0;

    try {
        // Get latest study plan
        $stmt = $conn->prepare("
            SELECT
                sp.*,
                s.id_number,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.program
            FROM study_plans sp
            JOIN students s ON s.id = sp.student_id
            WHERE sp.student_id = ? AND s.advisor_id = ?
            ORDER BY sp.id DESC
            LIMIT 1
        ");
        $stmt->bind_param("ii", $student_id, $professor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $plan = $result->fetch_assoc();

        if (!$plan) {
            echo json_encode(['success' => false, 'message' => 'No submission found']);
            return;
        }

        // Get current subjects
        $stmt = $conn->prepare("
            SELECT cs.*,
                GROUP_CONCAT(CONCAT(csp.prerequisite_code, ' (', csp.prerequisite_type, ')') SEPARATOR ', ') as prerequisites
            FROM current_subjects cs
            LEFT JOIN current_subject_prerequisites csp ON cs.id = csp.current_subject_id
            WHERE cs.study_plan_id = ?
            GROUP BY cs.id
        ");
        $stmt->bind_param("i", $plan['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        $current_subjects = [];
        while ($row = $result->fetch_assoc()) {
            $current_subjects[] = $row;
        }

        // Get planned subjects
        $stmt = $conn->prepare("
            SELECT ps.*,
                GROUP_CONCAT(CONCAT(psp.prerequisite_code, ' (', psp.prerequisite_type, ')') SEPARATOR ', ') as prerequisites
            FROM planned_subjects ps
            LEFT JOIN planned_subject_prerequisites psp ON ps.id = psp.planned_subject_id
            WHERE ps.study_plan_id = ?
            GROUP BY ps.id
        ");
        $stmt->bind_param("i", $plan['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        $planned_subjects = [];
        while ($row = $result->fetch_assoc()) {
            $planned_subjects[] = $row;
        }

        echo json_encode([
            'success' => true,
            'plan' => $plan,
            'current_subjects' => $current_subjects,
            'planned_subjects' => $planned_subjects
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

?>