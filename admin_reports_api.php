<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';

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
    case 'enrollment_stats':
        getEnrollmentStats();
        break;
    case 'failed_units':
        getFailedUnitsReport();
        break;
    case 'clearance_report':
        getClearanceReport();
        break;
    case 'workload_report':
        getWorkloadReport();
        break;
    case 'course_enrollment':
        getCourseEnrollment();
        break;
    case 'export_report':
        exportReport();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getEnrollmentStats() {
    global $conn;
    
    $year = $_GET['year'] ?? '';
    $program = $_GET['program'] ?? '';
    
    // Total students
    $query = "SELECT COUNT(*) as total FROM students WHERE 1=1";
    if ($program) $query .= " AND program = '" . $conn->real_escape_string($program) . "'";
    $total_students = $conn->query($query)->fetch_assoc()['total'];
    
    // Active students (those with advisers)
    $query = "SELECT COUNT(*) as active FROM students WHERE advisor_id IS NOT NULL";
    if ($program) $query .= " AND program = '" . $conn->real_escape_string($program) . "'";
    $active_students = $conn->query($query)->fetch_assoc()['active'];
    
    // By program
    $bscpe = $conn->query("SELECT COUNT(*) as count FROM students WHERE program = 'BS Computer Engineering'")->fetch_assoc()['count'];
    $bsece = $conn->query("SELECT COUNT(*) as count FROM students WHERE program = 'BS Electronics and Communications Engineering'")->fetch_assoc()['count'];
    $bsee = $conn->query("SELECT COUNT(*) as count FROM students WHERE program = 'BS Electrical Engineering'")->fetch_assoc()['count'];
    
    // Chart data
    $chart_data = [
        'labels' => ['BSCpE', 'BSECE', 'BSEE'],
        'values' => [$bscpe, $bsece, $bsee]
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_students' => $total_students,
            'active_students' => $active_students,
            'bscpe_students' => $bscpe,
            'bsece_students' => $bsece,
            'bsee_students' => $bsee
        ],
        'chart_data' => $chart_data
    ]);
}

function getFailedUnitsReport() {
    global $conn;
    
    $program = $_GET['program'] ?? '';
    $threshold = $_GET['threshold'] ?? 0;
    
    // Build query
    $query = "
        SELECT 
            s.*,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            CONCAT(p.first_name, ' ', p.last_name) as adviser_name
        FROM students s
        LEFT JOIN professors p ON p.id = s.advisor_id
        WHERE s.accumulated_failed_units >= " . (int)$threshold;
    
    if ($program) {
        $query .= " AND s.program = '" . $conn->real_escape_string($program) . "'";
    }
    
    $query .= " ORDER BY s.accumulated_failed_units DESC";
    
    $result = $conn->query($query);
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    // Stats
    $warning_count = $conn->query("SELECT COUNT(*) as count FROM students WHERE accumulated_failed_units >= 15 AND accumulated_failed_units < 25")->fetch_assoc()['count'];
    $critical_count = $conn->query("SELECT COUNT(*) as count FROM students WHERE accumulated_failed_units >= 25")->fetch_assoc()['count'];
    $avg_failed = $conn->query("SELECT AVG(accumulated_failed_units) as avg FROM students")->fetch_assoc()['avg'];
    
    // Chart data
    $normal = $conn->query("SELECT COUNT(*) as count FROM students WHERE accumulated_failed_units < 15")->fetch_assoc()['count'];
    $chart_data = [
        'values' => [$normal, $warning_count, $critical_count]
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'warning_count' => $warning_count,
            'critical_count' => $critical_count,
            'average_failed' => round($avg_failed, 1)
        ],
        'chart_data' => $chart_data,
        'students' => $students
    ]);
}

function getClearanceReport() {
    global $conn;
    
    $program = $_GET['program'] ?? '';
    $status = $_GET['status'] ?? '';
    
    // Build query
    $query = "
        SELECT 
            s.*,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            CONCAT(p.first_name, ' ', p.last_name) as adviser_name
        FROM students s
        LEFT JOIN professors p ON p.id = s.advisor_id
        WHERE 1=1";
    
    if ($program) {
        $query .= " AND s.program = '" . $conn->real_escape_string($program) . "'";
    }
    
    if ($status !== '') {
        $query .= " AND s.advising_cleared = " . (int)$status;
    }
    
    $query .= " ORDER BY s.advising_cleared DESC, s.last_name ASC";
    
    $result = $conn->query($query);
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    // Stats
    $cleared = $conn->query("SELECT COUNT(*) as count FROM students WHERE advising_cleared = 1")->fetch_assoc()['count'];
    $not_cleared = $conn->query("SELECT COUNT(*) as count FROM students WHERE advising_cleared = 0")->fetch_assoc()['count'];
    $total = $cleared + $not_cleared;
    $clearance_rate = $total > 0 ? round(($cleared / $total) * 100, 1) : 0;
    
    // Chart data
    $chart_data = [
        'values' => [$cleared, $not_cleared]
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'cleared_count' => $cleared,
            'not_cleared_count' => $not_cleared,
            'clearance_rate' => $clearance_rate
        ],
        'chart_data' => $chart_data,
        'students' => $students
    ]);
}

function getWorkloadReport() {
    global $conn;
    
    $query = "
        SELECT 
            p.id,
            CONCAT(p.first_name, ' ', p.last_name) as professor_name,
            COUNT(DISTINCT s.id) as total_advisees,
            COUNT(DISTINCT CASE WHEN sp.cleared = 0 AND sp.adviser_feedback IS NULL THEN sp.id END) as pending_plans,
            COUNT(DISTINCT CASE WHEN s.advising_cleared = 1 THEN s.id END) as cleared_students,
            COUNT(DISTINCT CASE WHEN s.accumulated_failed_units >= 25 THEN s.id END) as at_risk_students
        FROM professors p
        LEFT JOIN students s ON s.advisor_id = p.id
        LEFT JOIN study_plans sp ON sp.student_id = s.id
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
    
    // Get course enrollment stats
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
    global $conn;
    
    $type = $_GET['type'] ?? '';
    $format = $_GET['format'] ?? 'pdf';
    
    // For now, generate CSV export (simplest implementation)
    // In production, you'd use libraries like TCPDF for PDF or PHPSpreadsheet for Excel
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $type . '_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    switch ($type) {
        case 'enrollment':
            fputcsv($output, ['ID Number', 'Student Name', 'Program', 'Adviser', 'Status']);
            $result = $conn->query("
                SELECT s.id_number, CONCAT(s.first_name, ' ', s.last_name) as name, 
                       s.program, CONCAT(p.first_name, ' ', p.last_name) as adviser,
                       CASE WHEN s.advisor_id IS NOT NULL THEN 'Active' ELSE 'Inactive' END as status
                FROM students s
                LEFT JOIN professors p ON p.id = s.advisor_id
            ");
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            break;
            
        case 'failed':
            fputcsv($output, ['ID Number', 'Student Name', 'Program', 'Failed Units', 'Status', 'Adviser']);
            $result = $conn->query("
                SELECT s.id_number, CONCAT(s.first_name, ' ', s.last_name) as name,
                       s.program, s.accumulated_failed_units,
                       CASE WHEN s.accumulated_failed_units >= 25 THEN 'Critical' 
                            WHEN s.accumulated_failed_units >= 15 THEN 'Warning' 
                            ELSE 'Normal' END as status,
                       CONCAT(p.first_name, ' ', p.last_name) as adviser
                FROM students s
                LEFT JOIN professors p ON p.id = s.advisor_id
                ORDER BY s.accumulated_failed_units DESC
            ");
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            break;
            
        case 'clearance':
            fputcsv($output, ['ID Number', 'Student Name', 'Program', 'Clearance Status', 'Adviser']);
            $result = $conn->query("
                SELECT s.id_number, CONCAT(s.first_name, ' ', s.last_name) as name,
                       s.program, 
                       CASE WHEN s.advising_cleared = 1 THEN 'Cleared' ELSE 'Not Cleared' END as status,
                       CONCAT(p.first_name, ' ', p.last_name) as adviser
                FROM students s
                LEFT JOIN professors p ON p.id = s.advisor_id
                ORDER BY s.advising_cleared DESC
            ");
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            break;
            
        case 'workload':
            fputcsv($output, ['Professor Name', 'Total Advisees', 'Pending Plans', 'Cleared Students', 'At-Risk Students']);
            $result = $conn->query("
                SELECT 
                    CONCAT(p.first_name, ' ', p.last_name) as name,
                    COUNT(DISTINCT s.id) as advisees,
                    COUNT(DISTINCT CASE WHEN sp.cleared = 0 THEN sp.id END) as pending,
                    COUNT(DISTINCT CASE WHEN s.advising_cleared = 1 THEN s.id END) as cleared,
                    COUNT(DISTINCT CASE WHEN s.accumulated_failed_units >= 25 THEN s.id END) as at_risk
                FROM professors p
                LEFT JOIN students s ON s.advisor_id = p.id
                LEFT JOIN study_plans sp ON sp.student_id = s.id
                GROUP BY p.id
            ");
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            break;
            
        case 'courses':
            fputcsv($output, ['Course Code', 'Course Name', 'Program', 'Term', 'Enrolled', 'Pass Rate', 'Fail Rate']);
            $result = $conn->query("
                SELECT 
                    c.course_code, c.course_name, c.program, c.term,
                    COUNT(DISTINCT b.student_id) as enrolled,
                    ROUND(COUNT(DISTINCT CASE WHEN b.is_failed = 0 THEN b.student_id END) * 100.0 / 
                          NULLIF(COUNT(DISTINCT b.student_id), 0), 1) as pass_rate,
                    ROUND(COUNT(DISTINCT CASE WHEN b.is_failed = 1 THEN b.student_id END) * 100.0 / 
                          NULLIF(COUNT(DISTINCT b.student_id), 0), 1) as fail_rate
                FROM course_catalog c
                LEFT JOIN student_advising_booklet b ON b.course_code = c.course_code
                GROUP BY c.id
            ");
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            break;
    }
    
    fclose($output);
    exit();
}
?>
