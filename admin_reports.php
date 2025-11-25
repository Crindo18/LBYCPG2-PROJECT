<?php
require_once 'auth_check.php';
requireAdmin();

require_once 'config.php';

if (isset($_GET['action'])) {
    ob_start();
    error_reporting(0);
    ini_set('display_errors', 0);

    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    ob_clean();
    header('Content-Type: application/json');

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'enrollment_stats': getEnrollmentStats(); break;
        case 'failed_units': getFailedUnitsReport(); break;
        case 'clearance_report': getClearanceReport(); break;
        case 'workload_report': getWorkloadReport(); break;
        case 'course_enrollment': getCourseEnrollment(); break;
        case 'export_report': exportReport(); break;
        default: echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit();
}

// ... (Keep getEnrollmentStats, getFailedUnitsReport, getClearanceReport unchanged) ...
function getEnrollmentStats() { global $conn; /* ... Keep existing logic ... */ echo json_encode(['success'=>true, 'stats'=>[], 'chart_data'=>[]]); } 
function getFailedUnitsReport() { global $conn; /* ... Keep existing logic ... */ echo json_encode(['success'=>true, 'stats'=>[], 'chart_data'=>[], 'students'=>[]]); }
function getClearanceReport() { global $conn; /* ... Keep existing logic ... */ echo json_encode(['success'=>true, 'stats'=>[], 'chart_data'=>[], 'students'=>[]]); }

// UPDATED FUNCTION
function getWorkloadReport() {
    global $conn;
    
    // Updated query to count from 'academic_advising_forms' (new) instead of 'study_plans' (old)
    $query = "
        SELECT 
            p.id,
            CONCAT(p.first_name, ' ', p.last_name) as professor_name,
            COUNT(DISTINCT s.id) as total_advisees,
            -- Count pending forms from the new table
            COUNT(DISTINCT CASE WHEN aaf.status = 'pending' THEN aaf.id END) as pending_plans,
            COUNT(DISTINCT CASE WHEN s.advising_cleared = 1 THEN s.id END) as cleared_students,
            COUNT(DISTINCT CASE WHEN s.accumulated_failed_units >= 25 THEN s.id END) as at_risk_students
        FROM professors p
        LEFT JOIN students s ON s.advisor_id = p.id
        LEFT JOIN academic_advising_forms aaf ON aaf.student_id = s.id
        GROUP BY p.id
        ORDER BY total_advisees DESC
    ";
    
    $result = $conn->query($query);
    $professors = [];
    $total_professors = 0;
    $total_advisees = 0;
    $total_pending = 0;
    
    while ($row = $result->fetch_assoc()) {
        $professors[] = $row;
        $total_professors++;
        $total_advisees += $row['total_advisees'];
        $total_pending += $row['pending_plans'];
    }
    
    $avg_advisees = $total_professors > 0 ? round($total_advisees / $total_professors, 1) : 0;
    
    // Chart data
    $labels = [];
    $values = [];
    foreach ($professors as $prof) {
        $labels[] = $prof['professor_name'];
        $values[] = $prof['total_advisees'];
    }
    
    $chart_data = [
        'labels' => $labels,
        'values' => $values
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_professors' => $total_professors,
            'avg_advisees' => $avg_advisees,
            'total_pending' => $total_pending
        ],
        'chart_data' => $chart_data,
        'professors' => $professors
    ]);
}

function getCourseEnrollment() {
    global $conn;
    
    $program = $_GET['program'] ?? '';
    $term = $_GET['term'] ?? '';
    
    // Updated to join with student_advising_booklet (where we auto-inserted data)
    $query = "
        SELECT 
            c.course_code,
            c.course_name,
            c.program,
            c.term,
            COUNT(DISTINCT b.student_id) as enrolled_count,
            COUNT(DISTINCT CASE WHEN b.is_failed = 0 THEN b.student_id END) as passed_count,
            COUNT(DISTINCT CASE WHEN b.is_failed = 1 THEN b.student_id END) as failed_count
        FROM course_catalog c
        LEFT JOIN student_advising_booklet b ON b.course_code = c.course_code
        WHERE 1=1";
    
    if ($program) {
        $query .= " AND c.program = '" . $conn->real_escape_string($program) . "'";
    }
    
    if ($term) {
        $query .= " AND c.term = '" . $conn->real_escape_string($term) . "'";
    }
    
    $query .= " GROUP BY c.id ORDER BY enrolled_count DESC LIMIT 20";
    
    $result = $conn->query($query);
    $courses = [];
    $labels = [];
    $values = [];
    
    while ($row = $result->fetch_assoc()) {
        $enrolled = $row['enrolled_count'];
        $passed = $row['passed_count'];
        $failed = $row['failed_count'];
        
        $pass_rate = $enrolled > 0 ? round(($passed / $enrolled) * 100, 1) : 0;
        $fail_rate = $enrolled > 0 ? round(($failed / $enrolled) * 100, 1) : 0;
        
        $row['pass_rate'] = $pass_rate;
        $row['fail_rate'] = $fail_rate;
        
        $courses[] = $row;
        $labels[] = $row['course_code'];
        $values[] = $enrolled;
    }
    
    $chart_data = [
        'labels' => $labels,
        'values' => $values
    ];
    
    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'chart_data' => $chart_data
    ]);
}

function exportReport() {
    // ... (Keep existing export logic, ensuring it uses the updated queries if they were hardcoded) ...
    // For CSV export, ensure you update the SQL queries inside this function similar to above
    // if they were referencing 'study_plans'.
    // For brevity, assuming basic export logic is fine or you can copy paste the queries above.
}
?>