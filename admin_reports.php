<?php
require_once 'auth_check.php';
requireAdmin();

require_once 'config.php';

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

$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_name = $stmt->get_result()->fetch_assoc()['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #6a1b9a; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #4a148c; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.9; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.08); border-left-color: #BA68C8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #6a1b9a; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; flex-wrap: wrap; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #3498db; border-bottom-color: #3498db; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 20px; color: #2c3e50; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .stat-card.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.red { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-value { font-size: 36px; font-weight: bold; margin: 10px 0; }
        .stat-label { font-size: 14px; opacity: 0.9; }
        
        .chart-container { position: relative; height: 400px; margin: 20px 0; }
        
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        
        .filter-section { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-section select, .filter-section input { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #229954; }
        .btn-export { background: #95a5a6; color: white; }
        .btn-export:hover { background: #7f8c8d; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        
        .loading { text-align: center; padding: 40px; color: #666; }
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        
        .export-buttons { display: flex; gap: 10px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Admin Portal</h2>
                <p>Academic Advising System</p>
            </div>
            <nav class="sidebar-menu">
                <a href="admin_dashboard.php" class="menu-item">Dashboard</a>
                <a href="admin_accounts.php" class="menu-item">User Accounts</a>
                <a href="admin_courses.php" class="menu-item">Course Catalog</a>
                <a href="admin_advising_forms.php" class="menu-item">Advising Forms</a>
                <a href="admin_advisingassignment.php" class="menu-item">Advising Assignments</a>
                <a href="admin_reports.php" class="menu-item active">System Reports</a>
                <a href="admin_bulk_operations.php" class="menu-item">Bulk Ops & Uploads</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>System Reports</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('enrollment')">Enrollment Statistics</button>
                <button class="tab-btn" onclick="switchTab('failed')">Failed Units Report</button>
                <button class="tab-btn" onclick="switchTab('clearance')">Clearance Report</button>
                <button class="tab-btn" onclick="switchTab('workload')">Professor Workload</button>
                <button class="tab-btn" onclick="switchTab('courses')">Course Enrollment</button>
            </div>
            
            <!-- Enrollment Statistics -->
            <div id="enrollment" class="tab-content active">
                <div class="content-card">
                    <h3>Enrollment Statistics</h3>
                    
                    <div class="filter-section">
                        <select id="enrollmentYear">
                            <option value="">All Years</option>
                            <option value="2024-2025">2024-2025</option>
                            <option value="2023-2024">2023-2024</option>
                        </select>
                        <select id="enrollmentProgram">
                            <option value="">All Programs</option>
                            <option value="BS Computer Engineering">BSCpE</option>
                            <option value="BS Electronics and Communications Engineering">BSECE</option>
                            <option value="BS Electrical Engineering">BSEE</option>
                        </select>
                        <button class="btn btn-primary" onclick="loadEnrollmentStats()">Apply Filters</button>
                    </div>
                    
                    <div class="stats-grid" id="enrollmentStats">
                        <div class="loading">Loading statistics...</div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="enrollmentChart"></canvas>
                    </div>
                    
                    <div class="export-buttons">
                        <button class="btn btn-export" onclick="exportReport('enrollment', 'pdf')">Export PDF</button>
                        <button class="btn btn-export" onclick="exportReport('enrollment', 'excel')">Export Excel</button>
                    </div>
                </div>
            </div>
            
            <!-- Failed Units Report -->
            <div id="failed" class="tab-content">
                <div class="content-card">
                    <h3>Failed Units Report</h3>
                    
                    <div class="alert info">
                        <strong>Critical Students:</strong> Students with 25+ failed units are at risk and need immediate attention.
                    </div>
                    
                    <div class="filter-section">
                        <select id="failedProgram">
                            <option value="">All Programs</option>
                            <option value="BS Computer Engineering">BSCpE</option>
                            <option value="BS Electronics and Communications Engineering">BSECE</option>
                            <option value="BS Electrical Engineering">BSEE</option>
                        </select>
                        <select id="failedThreshold">
                            <option value="0">All Students</option>
                            <option value="15">15+ Units (Warning)</option>
                            <option value="25">25+ Units (Critical)</option>
                        </select>
                        <button class="btn btn-primary" onclick="loadFailedUnitsReport()">Apply Filters</button>
                    </div>
                    
                    <div class="stats-grid" id="failedStats">
                        <div class="loading">Loading statistics...</div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="failedChart"></canvas>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table" id="failedTable">
                            <thead>
                                <tr>
                                    <th>ID Number</th>
                                    <th>Student Name</th>
                                    <th>Program</th>
                                    <th>Failed Units</th>
                                    <th>Status</th>
                                    <th>Adviser</th>
                                </tr>
                            </thead>
                            <tbody id="failedTableBody">
                                <tr><td colspan="6" class="loading">Loading data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="export-buttons">
                        <button class="btn btn-export" onclick="exportReport('failed', 'pdf')">Export PDF</button>
                        <button class="btn btn-export" onclick="exportReport('failed', 'excel')">Export Excel</button>
                    </div>
                </div>
            </div>
            
            <!-- Clearance Report -->
            <div id="clearance" class="tab-content">
                <div class="content-card">
                    <h3>Clearance Report</h3>
                    
                    <div class="filter-section">
                        <select id="clearanceProgram">
                            <option value="">All Programs</option>
                            <option value="BS Computer Engineering">BSCpE</option>
                            <option value="BS Electronics and Communications Engineering">BSECE</option>
                            <option value="BS Electrical Engineering">BSEE</option>
                        </select>
                        <select id="clearanceStatus">
                            <option value="">All Status</option>
                            <option value="1">Cleared</option>
                            <option value="0">Not Cleared</option>
                        </select>
                        <button class="btn btn-primary" onclick="loadClearanceReport()">Apply Filters</button>
                    </div>
                    
                    <div class="stats-grid" id="clearanceStats">
                        <div class="loading">Loading statistics...</div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="clearanceChart"></canvas>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table" id="clearanceTable">
                            <thead>
                                <tr>
                                    <th>ID Number</th>
                                    <th>Student Name</th>
                                    <th>Program</th>
                                    <th>Clearance Status</th>
                                    <th>Adviser</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody id="clearanceTableBody">
                                <tr><td colspan="6" class="loading">Loading data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="export-buttons">
                        <button class="btn btn-export" onclick="exportReport('clearance', 'pdf')">Export PDF</button>
                        <button class="btn btn-export" onclick="exportReport('clearance', 'excel')">Export Excel</button>
                    </div>
                </div>
            </div>
            
            <!-- Professor Workload -->
            <div id="workload" class="tab-content">
                <div class="content-card">
                    <h3>Professor Workload Report</h3>
                    
                    <div class="stats-grid" id="workloadStats">
                        <div class="loading">Loading statistics...</div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="workloadChart"></canvas>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table" id="workloadTable">
                            <thead>
                                <tr>
                                    <th>Professor Name</th>
                                    <th>Total Advisees</th>
                                    <th>Pending Plans</th>
                                    <th>Cleared Students</th>
                                    <th>At-Risk Students</th>
                                    <th>Workload Status</th>
                                </tr>
                            </thead>
                            <tbody id="workloadTableBody">
                                <tr><td colspan="6" class="loading">Loading data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="export-buttons">
                        <button class="btn btn-export" onclick="exportReport('workload', 'pdf')">Export PDF</button>
                        <button class="btn btn-export" onclick="exportReport('workload', 'excel')">Export Excel</button>
                    </div>
                </div>
            </div>
            
            <!-- Course Enrollment -->
            <div id="courses" class="tab-content">
                <div class="content-card">
                    <h3>Course Enrollment Statistics</h3>
                    
                    <div class="filter-section">
                        <select id="courseProgram">
                            <option value="">All Programs</option>
                            <option value="BS Computer Engineering">BSCpE</option>
                            <option value="BS Electronics and Communications Engineering">BSECE</option>
                            <option value="BS Electrical Engineering">BSEE</option>
                        </select>
                        <select id="courseTerm">
                            <option value="">All Terms</option>
                            <option value="Term 1">Term 1</option>
                            <option value="Term 2">Term 2</option>
                            <option value="Term 3">Term 3</option>
                            <option value="Term 4">Term 4</option>
                            <option value="Term 5">Term 5</option>
                            <option value="Term 6">Term 6</option>
                            <option value="Term 7">Term 7</option>
                            <option value="Term 8">Term 8</option>
                            <option value="Term 9">Term 9</option>
                            <option value="Term 10">Term 10</option>
                            <option value="Term 11">Term 11</option>
                            <option value="Term 12">Term 12</option>
                        </select>
                        <button class="btn btn-primary" onclick="loadCourseEnrollment()">Apply Filters</button>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="courseChart"></canvas>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table" id="courseTable">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Name</th>
                                    <th>Program</th>
                                    <th>Term</th>
                                    <th>Enrolled Students</th>
                                    <th>Pass Rate</th>
                                    <th>Fail Rate</th>
                                </tr>
                            </thead>
                            <tbody id="courseTableBody">
                                <tr><td colspan="7" class="loading">Loading data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="export-buttons">
                        <button class="btn btn-export" onclick="exportReport('courses', 'pdf')">Export PDF</button>
                        <button class="btn btn-export" onclick="exportReport('courses', 'excel')">Export Excel</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let enrollmentChart, failedChart, clearanceChart, workloadChart, courseChart;
        
        window.onload = function() {
            loadEnrollmentStats();
        };

        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById(tab).classList.add('active');
            event.target.classList.add('active');
            
            // Load data for the active tab
            switch(tab) {
                case 'enrollment':
                    loadEnrollmentStats();
                    break;
                case 'failed':
                    loadFailedUnitsReport();
                    break;
                case 'clearance':
                    loadClearanceReport();
                    break;
                case 'workload':
                    loadWorkloadReport();
                    break;
                case 'courses':
                    loadCourseEnrollment();
                    break;
            }
        }

        function loadEnrollmentStats() {
            const year = document.getElementById('enrollmentYear')?.value || '';
            const program = document.getElementById('enrollmentProgram')?.value || '';
            
            fetch(`admin_reports.php?action=enrollment_stats&year=${year}&program=${program}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderEnrollmentStats(data.stats);
                        renderEnrollmentChart(data.chart_data);
                    }
                });
        }

        function renderEnrollmentStats(stats) {
            const container = document.getElementById('enrollmentStats');
            container.innerHTML = `
                <div class="stat-card">
                    <div class="stat-label">Total Students</div>
                    <div class="stat-value">${stats.total_students}</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-label">Active Students</div>
                    <div class="stat-value">${stats.active_students}</div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-label">BSCpE Students</div>
                    <div class="stat-value">${stats.bscpe_students}</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-label">BSECE Students</div>
                    <div class="stat-value">${stats.bsece_students}</div>
                </div>
                <div class="stat-card red">
                    <div class="stat-label">BSEE Students</div>
                    <div class="stat-value">${stats.bsee_students}</div>
                </div>
            `;
        }

        function renderEnrollmentChart(data) {
            const ctx = document.getElementById('enrollmentChart');
            if (enrollmentChart) enrollmentChart.destroy();
            
            enrollmentChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Students by Program',
                        data: data.values,
                        backgroundColor: ['#667eea', '#11998e', '#f093fb', '#4facfe']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        function loadFailedUnitsReport() {
            const program = document.getElementById('failedProgram')?.value || '';
            const threshold = document.getElementById('failedThreshold')?.value || '0';
            
            fetch(`admin_reports.php?action=failed_units&program=${program}&threshold=${threshold}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderFailedStats(data.stats);
                        renderFailedChart(data.chart_data);
                        renderFailedTable(data.students);
                    }
                });
        }

        function renderFailedStats(stats) {
            const container = document.getElementById('failedStats');
            container.innerHTML = `
                <div class="stat-card orange">
                    <div class="stat-label">Warning (15+ units)</div>
                    <div class="stat-value">${stats.warning_count}</div>
                </div>
                <div class="stat-card red">
                    <div class="stat-label">Critical (25+ units)</div>
                    <div class="stat-value">${stats.critical_count}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Average Failed Units</div>
                    <div class="stat-value">${stats.average_failed}</div>
                </div>
            `;
        }

        function renderFailedChart(data) {
            const ctx = document.getElementById('failedChart');
            if (failedChart) failedChart.destroy();
            
            failedChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['0-14 units', '15-24 units', '25+ units'],
                    datasets: [{
                        data: data.values,
                        backgroundColor: ['#27ae60', '#f39c12', '#e74c3c']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        function renderFailedTable(students) {
            const tbody = document.getElementById('failedTableBody');
            if (students.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No students found</td></tr>';
                return;
            }
            
            let html = '';
            students.forEach(student => {
                const statusClass = student.accumulated_failed_units >= 25 ? 'red' : 'orange';
                const statusText = student.accumulated_failed_units >= 25 ? 'Critical' : 'Warning';
                html += `
                    <tr>
                        <td>${student.id_number}</td>
                        <td>${student.student_name}</td>
                        <td>${student.program}</td>
                        <td><strong>${student.accumulated_failed_units}</strong></td>
                        <td><span style="color: ${statusClass === 'red' ? '#e74c3c' : '#f39c12'}; font-weight: bold;">${statusText}</span></td>
                        <td>${student.adviser_name || 'Not Assigned'}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function loadClearanceReport() {
            const program = document.getElementById('clearanceProgram')?.value || '';
            const status = document.getElementById('clearanceStatus')?.value || '';
            
            fetch(`admin_reports.php?action=clearance_report&program=${program}&status=${status}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderClearanceStats(data.stats);
                        renderClearanceChart(data.chart_data);
                        renderClearanceTable(data.students);
                    }
                });
        }

        function renderClearanceStats(stats) {
            const container = document.getElementById('clearanceStats');
            container.innerHTML = `
                <div class="stat-card green">
                    <div class="stat-label">Cleared Students</div>
                    <div class="stat-value">${stats.cleared_count}</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-label">Not Cleared</div>
                    <div class="stat-value">${stats.not_cleared_count}</div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-label">Clearance Rate</div>
                    <div class="stat-value">${stats.clearance_rate}%</div>
                </div>
            `;
        }

        function renderClearanceChart(data) {
            const ctx = document.getElementById('clearanceChart');
            if (clearanceChart) clearanceChart.destroy();
            
            clearanceChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Cleared', 'Not Cleared'],
                    datasets: [{
                        data: data.values,
                        backgroundColor: ['#27ae60', '#e74c3c']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        function renderClearanceTable(students) {
            const tbody = document.getElementById('clearanceTableBody');
            if (students.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No students found</td></tr>';
                return;
            }
            
            let html = '';
            students.forEach(student => {
                const statusColor = student.advising_cleared == 1 ? '#27ae60' : '#e74c3c';
                const statusText = student.advising_cleared == 1 ? 'Cleared' : 'Not Cleared';
                html += `
                    <tr>
                        <td>${student.id_number}</td>
                        <td>${student.student_name}</td>
                        <td>${student.program}</td>
                        <td><span style="color: ${statusColor}; font-weight: bold;">${statusText}</span></td>
                        <td>${student.adviser_name || 'Not Assigned'}</td>
                        <td>${student.created_at}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function loadWorkloadReport() {
            fetch('admin_reports.php?action=workload_report')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderWorkloadStats(data.stats);
                        renderWorkloadChart(data.chart_data);
                        renderWorkloadTable(data.professors);
                    }
                });
        }

        function renderWorkloadStats(stats) {
            const container = document.getElementById('workloadStats');
            container.innerHTML = `
                <div class="stat-card">
                    <div class="stat-label">Total Professors</div>
                    <div class="stat-value">${stats.total_professors}</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-label">Avg Advisees per Prof</div>
                    <div class="stat-value">${stats.avg_advisees}</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-label">Pending Study Plans</div>
                    <div class="stat-value">${stats.total_pending}</div>
                </div>
            `;
        }

        function renderWorkloadChart(data) {
            const ctx = document.getElementById('workloadChart');
            if (workloadChart) workloadChart.destroy();
            
            workloadChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Number of Advisees',
                        data: data.values,
                        backgroundColor: '#3498db'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function renderWorkloadTable(professors) {
            const tbody = document.getElementById('workloadTableBody');
            if (professors.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No professors found</td></tr>';
                return;
            }
            
            let html = '';
            professors.forEach(prof => {
                const workloadStatus = prof.total_advisees > 50 ? 'Overloaded' : prof.total_advisees > 30 ? 'High' : 'Normal';
                const statusColor = prof.total_advisees > 50 ? '#e74c3c' : prof.total_advisees > 30 ? '#f39c12' : '#27ae60';
                html += `
                    <tr>
                        <td>${prof.professor_name}</td>
                        <td>${prof.total_advisees}</td>
                        <td>${prof.pending_plans}</td>
                        <td>${prof.cleared_students}</td>
                        <td>${prof.at_risk_students}</td>
                        <td><span style="color: ${statusColor}; font-weight: bold;">${workloadStatus}</span></td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function loadCourseEnrollment() {
            const program = document.getElementById('courseProgram')?.value || '';
            const term = document.getElementById('courseTerm')?.value || '';
            
            fetch(`admin_reports.php?action=course_enrollment&program=${program}&term=${term}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderCourseChart(data.chart_data);
                        renderCourseTable(data.courses);
                    }
                });
        }

        function renderCourseChart(data) {
            const ctx = document.getElementById('courseChart');
            if (courseChart) courseChart.destroy();
            
            courseChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Enrolled Students',
                        data: data.values,
                        backgroundColor: '#3498db'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function renderCourseTable(courses) {
            const tbody = document.getElementById('courseTableBody');
            if (courses.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No courses found</td></tr>';
                return;
            }
            
            let html = '';
            courses.forEach(course => {
                html += `
                    <tr>
                        <td>${course.course_code}</td>
                        <td>${course.course_name}</td>
                        <td>${course.program}</td>
                        <td>${course.term}</td>
                        <td>${course.enrolled_count}</td>
                        <td>${course.pass_rate}%</td>
                        <td>${course.fail_rate}%</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function exportReport(reportType, format) {
            const filters = getFilters(reportType);
            window.open(`admin_reports.php?action=export_report&type=${reportType}&format=${format}${filters}`, '_blank');
        }

        function getFilters(reportType) {
            let filters = '';
            switch(reportType) {
                case 'enrollment':
                    filters = `&year=${document.getElementById('enrollmentYear')?.value || ''}&program=${document.getElementById('enrollmentProgram')?.value || ''}`;
                    break;
                case 'failed':
                    filters = `&program=${document.getElementById('failedProgram')?.value || ''}&threshold=${document.getElementById('failedThreshold')?.value || ''}`;
                    break;
                case 'clearance':
                    filters = `&program=${document.getElementById('clearanceProgram')?.value || ''}&status=${document.getElementById('clearanceStatus')?.value || ''}`;
                    break;
                case 'courses':
                    filters = `&program=${document.getElementById('courseProgram')?.value || ''}&term=${document.getElementById('courseTerm')?.value || ''}`;
                    break;
            }
            return filters;
        }
    </script>
</body>
</html>