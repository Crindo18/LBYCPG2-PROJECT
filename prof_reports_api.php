<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'professor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

ob_clean();
header('Content-Type: application/json');

$professor_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_programs':
        getPrograms();
        break;
    case 'get_overview_stats':
        getOverviewStats();
        break;
    case 'get_performance_data':
        getPerformanceData();
        break;
    case 'get_failed_units_data':
        getFailedUnitsData();
        break;
    case 'get_studyplan_data':
        getStudyPlanData();
        break;
    case 'generate_custom_report':
        generateCustomReport();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getPrograms() {
    global $conn, $professor_id;
    
    $stmt = $conn->prepare("SELECT DISTINCT program FROM students WHERE advisor_id = ? ORDER BY program");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $programs = [];
    while ($row = $result->fetch_assoc()) {
        $programs[] = $row['program'];
    }
    
    echo json_encode(['success' => true, 'programs' => $programs]);
}

function getOverviewStats() {
    global $conn, $professor_id;
    
    $year = $_GET['year'] ?? 'all';
    $term = $_GET['term'] ?? 'all';
    
    // Get basic stats
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM students WHERE advisor_id = ?");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $total_advisees = $stmt->get_result()->fetch_assoc()['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as cleared FROM students WHERE advisor_id = ? AND advising_cleared = 1");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $cleared_students = $stmt->get_result()->fetch_assoc()['cleared'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as at_risk FROM students WHERE advisor_id = ? AND accumulated_failed_units >= 15");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $at_risk_students = $stmt->get_result()->fetch_assoc()['at_risk'];
    
    // Calculate average GPA
    $stmt = $conn->prepare("
        SELECT AVG(gpa) as avg_gpa 
        FROM term_gpa_summary tgs
        JOIN students s ON s.id = tgs.student_id
        WHERE s.advisor_id = ?
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $avg_gpa_result = $stmt->get_result()->fetch_assoc();
    $avg_gpa = $avg_gpa_result['avg_gpa'] ? number_format($avg_gpa_result['avg_gpa'], 2) : '0.00';
    
    $clearance_rate = $total_advisees > 0 ? round(($cleared_students / $total_advisees) * 100, 1) : 0;
    
    $stats = [
        'total_advisees' => $total_advisees,
        'cleared_students' => $cleared_students,
        'at_risk_students' => $at_risk_students,
        'avg_gpa' => $avg_gpa,
        'clearance_rate' => $clearance_rate
    ];
    
    // Clearance data for chart
    $clearance = [
        'cleared' => $cleared_students,
        'not_cleared' => $total_advisees - $cleared_students
    ];
    
    // GPA Distribution
    $stmt = $conn->prepare("
        SELECT 
            CASE
                WHEN gpa >= 3.5 THEN '3.5-4.0'
                WHEN gpa >= 3.0 THEN '3.0-3.49'
                WHEN gpa >= 2.5 THEN '2.5-2.99'
                WHEN gpa >= 2.0 THEN '2.0-2.49'
                WHEN gpa >= 1.5 THEN '1.5-1.99'
                ELSE 'Below 1.5'
            END as gpa_range,
            COUNT(DISTINCT tgs.student_id) as count
        FROM term_gpa_summary tgs
        JOIN students s ON s.id = tgs.student_id
        WHERE s.advisor_id = ?
        GROUP BY gpa_range
        ORDER BY gpa_range DESC
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $gpa_labels = [];
    $gpa_counts = [];
    while ($row = $result->fetch_assoc()) {
        $gpa_labels[] = $row['gpa_range'];
        $gpa_counts[] = (int)$row['count'];
    }
    
    $gpa_distribution = [
        'labels' => $gpa_labels,
        'counts' => $gpa_counts
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'clearance' => $clearance,
        'gpa_distribution' => $gpa_distribution
    ]);
}

function getPerformanceData() {
    global $conn, $professor_id;
    
    $program = $_GET['program'] ?? 'all';
    $sort = $_GET['sort'] ?? 'gpa-desc';
    
    // Build query
    $query = "
        SELECT 
            s.id,
            s.id_number,
            s.program,
            s.accumulated_failed_units,
            s.advising_cleared,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            COALESCE(
                (SELECT AVG(gpa) 
                 FROM term_gpa_summary 
                 WHERE student_id = s.id), 
                0
            ) as gpa
        FROM students s
        WHERE s.advisor_id = ?
    ";
    
    $params = [$professor_id];
    $types = "i";
    
    if ($program !== 'all') {
        $query .= " AND s.program = ?";
        $params[] = $program;
        $types .= "s";
    }
    
    // Add sorting
    switch ($sort) {
        case 'gpa-desc':
            $query .= " ORDER BY gpa DESC, s.last_name";
            break;
        case 'gpa-asc':
            $query .= " ORDER BY gpa ASC, s.last_name";
            break;
        case 'failed-desc':
            $query .= " ORDER BY s.accumulated_failed_units DESC, s.last_name";
            break;
        case 'name':
            $query .= " ORDER BY s.last_name, s.first_name";
            break;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $row['gpa'] = number_format($row['gpa'], 2);
        $row['advising_cleared'] = (bool)$row['advising_cleared'];
        $students[] = $row;
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
}

function getFailedUnitsData() {
    global $conn, $professor_id;
    
    $period = $_GET['period'] ?? 'last-3';
    $groupBy = $_GET['groupby'] ?? 'term';
    
    // Determine year filter
    $currentYear = date('Y');
    $yearFilter = "";
    switch ($period) {
        case 'current':
            $yearFilter = "AND sab.academic_year = '$currentYear-" . ($currentYear + 1) . "'";
            break;
        case 'last-2':
            $yearFilter = "AND sab.academic_year >= '" . ($currentYear - 2) . "-" . ($currentYear - 1) . "'";
            break;
        case 'last-3':
            $yearFilter = "AND sab.academic_year >= '" . ($currentYear - 3) . "-" . ($currentYear - 2) . "'";
            break;
    }
    
    // Trend data
    if ($groupBy === 'term') {
        $trendQuery = "
            SELECT 
                CONCAT(sab.academic_year, ' T', sab.term) as period,
                COUNT(DISTINCT s.id) as student_count
            FROM student_advising_booklet sab
            JOIN students s ON s.id = sab.student_id
            WHERE s.advisor_id = ? 
                AND sab.grade_status = 'Failed'
                $yearFilter
            GROUP BY sab.academic_year, sab.term
            ORDER BY sab.academic_year, sab.term
        ";
    } else if ($groupBy === 'year') {
        $trendQuery = "
            SELECT 
                sab.academic_year as period,
                COUNT(DISTINCT s.id) as student_count
            FROM student_advising_booklet sab
            JOIN students s ON s.id = sab.student_id
            WHERE s.advisor_id = ? 
                AND sab.grade_status = 'Failed'
                $yearFilter
            GROUP BY sab.academic_year
            ORDER BY sab.academic_year
        ";
    } else {
        $trendQuery = "
            SELECT 
                sab.course_code as period,
                COUNT(*) as student_count
            FROM student_advising_booklet sab
            JOIN students s ON s.id = sab.student_id
            WHERE s.advisor_id = ? 
                AND sab.grade_status = 'Failed'
                $yearFilter
            GROUP BY sab.course_code
            ORDER BY student_count DESC
            LIMIT 10
        ";
    }
    
    $stmt = $conn->prepare($trendQuery);
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $trend_labels = [];
    $trend_counts = [];
    while ($row = $result->fetch_assoc()) {
        $trend_labels[] = $row['period'];
        $trend_counts[] = (int)$row['student_count'];
    }
    
    // Most failed courses
    $coursesQuery = "
        SELECT 
            sab.course_code,
            COUNT(*) as failure_count
        FROM student_advising_booklet sab
        JOIN students s ON s.id = sab.student_id
        WHERE s.advisor_id = ? 
            AND sab.grade_status = 'Failed'
            $yearFilter
        GROUP BY sab.course_code
        ORDER BY failure_count DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($coursesQuery);
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $course_codes = [];
    $course_counts = [];
    while ($row = $result->fetch_assoc()) {
        $course_codes[] = $row['course_code'];
        $course_counts[] = (int)$row['failure_count'];
    }
    
    // Detailed table data
    $tableQuery = "
        SELECT 
            sab.course_code,
            sab.course_title as course_name,
            COUNT(*) as failure_count,
            COUNT(DISTINCT sab.student_id) as student_count,
            AVG(
                CASE 
                    WHEN sab.final_grade REGEXP '^[0-9.]+$' 
                    THEN CAST(sab.final_grade AS DECIMAL(3,2))
                    ELSE NULL 
                END
            ) as avg_grade
        FROM student_advising_booklet sab
        JOIN students s ON s.id = sab.student_id
        WHERE s.advisor_id = ? 
            AND sab.grade_status = 'Failed'
            $yearFilter
        GROUP BY sab.course_code, sab.course_title
        ORDER BY failure_count DESC
    ";
    
    $stmt = $conn->prepare($tableQuery);
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $table_data = [];
    while ($row = $result->fetch_assoc()) {
        $row['avg_grade'] = $row['avg_grade'] ? number_format($row['avg_grade'], 2) : 'N/A';
        $table_data[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'trend_data' => [
            'labels' => $trend_labels,
            'counts' => $trend_counts
        ],
        'course_data' => [
            'courses' => $course_codes,
            'counts' => $course_counts
        ],
        'table_data' => $table_data
    ]);
}

function getStudyPlanData() {
    global $conn, $professor_id;
    
    $year = $_GET['year'] ?? 'all';
    $status = $_GET['status'] ?? 'all';
    
    // Build query filters
    $yearFilter = "";
    if ($year !== 'all') {
        $yearFilter = "AND sp.academic_year = '$year'";
    }
    
    $statusFilter = "";
    if ($status === 'approved') {
        $statusFilter = "AND sp.cleared = 1";
    } elseif ($status === 'pending') {
        $statusFilter = "AND sp.cleared = 0 AND (sp.adviser_feedback IS NULL OR sp.adviser_feedback = '')";
    } elseif ($status === 'rejected') {
        $statusFilter = "AND sp.cleared = 0 AND sp.adviser_feedback IS NOT NULL AND sp.adviser_feedback != ''";
    }
    
    // Get stats
    $statsQuery = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN sp.cleared = 1 THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN sp.cleared = 0 AND (sp.adviser_feedback IS NULL OR sp.adviser_feedback = '') THEN 1 ELSE 0 END) as pending,
            AVG(DATEDIFF(sp.cleared_date, sp.submission_date)) as avg_response
        FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        WHERE s.advisor_id = ?
            $yearFilter
            $statusFilter
    ";
    
    $stmt = $conn->prepare($statsQuery);
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $statsResult = $stmt->get_result()->fetch_assoc();
    
    $total = (int)$statsResult['total'];
    $approved = (int)$statsResult['approved'];
    $pending = (int)$statsResult['pending'];
    $approval_rate = $total > 0 ? round(($approved / $total) * 100, 1) : 0;
    $avg_response_time = $statsResult['avg_response'] ? round($statsResult['avg_response'], 1) : 0;
    
    $stats = [
        'total_plans' => $total,
        'approved_plans' => $approved,
        'pending_plans' => $pending,
        'approval_rate' => $approval_rate,
        'avg_response_time' => $avg_response_time
    ];
    
    // Approval trend (by term)
    $trendQuery = "
        SELECT 
            CONCAT(sp.academic_year, ' T', sp.term) as period,
            COUNT(*) as total,
            SUM(CASE WHEN sp.cleared = 1 THEN 1 ELSE 0 END) as approved
        FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        WHERE s.advisor_id = ?
            $yearFilter
        GROUP BY sp.academic_year, sp.term
        ORDER BY sp.academic_year, sp.term
    ";
    
    $stmt = $conn->prepare($trendQuery);
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $approval_labels = [];
    $approval_rates = [];
    while ($row = $result->fetch_assoc()) {
        $approval_labels[] = $row['period'];
        $rate = $row['total'] > 0 ? round(($row['approved'] / $row['total']) * 100, 1) : 0;
        $approval_rates[] = $rate;
    }
    
    // Response time data
    $responseQuery = "
        SELECT 
            CONCAT(sp.academic_year, ' T', sp.term) as period,
            AVG(DATEDIFF(sp.cleared_date, sp.submission_date)) as avg_days
        FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        WHERE s.advisor_id = ?
            AND sp.cleared_date IS NOT NULL
            $yearFilter
        GROUP BY sp.academic_year, sp.term
        ORDER BY sp.academic_year, sp.term
    ";
    
    $stmt = $conn->prepare($responseQuery);
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $response_labels = [];
    $response_times = [];
    while ($row = $result->fetch_assoc()) {
        $response_labels[] = $row['period'];
        $response_times[] = $row['avg_days'] ? round($row['avg_days'], 1) : 0;
    }
    
    // Get plan list
    $plansQuery = "
        SELECT 
            sp.id,
            sp.academic_year,
            sp.term,
            sp.submission_date,
            sp.cleared_date,
            sp.cleared,
            sp.adviser_feedback,
            s.id_number,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            DATEDIFF(sp.cleared_date, sp.submission_date) as response_time
        FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        WHERE s.advisor_id = ?
            $yearFilter
            $statusFilter
        ORDER BY sp.submission_date DESC
        LIMIT 50
    ";
    
    $stmt = $conn->prepare($plansQuery);
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $plans = [];
    while ($row = $result->fetch_assoc()) {
        $row['cleared'] = (bool)$row['cleared'];
        $row['response_time'] = $row['response_time'] ? $row['response_time'] . ' days' : null;
        $plans[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'approval_trend' => [
            'labels' => $approval_labels,
            'rates' => $approval_rates
        ],
        'response_times' => [
            'labels' => $response_labels,
            'times' => $response_times
        ],
        'plans' => $plans
    ]);
}

function generateCustomReport() {
    global $conn, $professor_id;
    
    $type = $_GET['type'] ?? 'advisee-summary';
    $dateRange = $_GET['dateRange'] ?? 'current-term';
    $program = $_GET['program'] ?? 'all';
    $includeGPA = $_GET['includeGPA'] === 'true';
    $includeFailed = $_GET['includeFailed'] === 'true';
    $includeContact = $_GET['includeContact'] === 'true';
    
    $reportHtml = '';
    $title = '';
    $subtitle = '';
    
    switch ($type) {
        case 'advisee-summary':
            $result = generateAdviseeSummary($program, $includeGPA, $includeFailed, $includeContact);
            $reportHtml = $result['html'];
            $title = 'Advisee Summary Report';
            $subtitle = 'Complete overview of all assigned advisees';
            break;
            
        case 'academic-performance':
            $result = generateAcademicPerformance($program, $includeGPA, $includeFailed);
            $reportHtml = $result['html'];
            $title = 'Academic Performance Analysis';
            $subtitle = 'Detailed analysis of student academic standing';
            break;
            
        case 'at-risk-students':
            $result = generateAtRiskReport($program);
            $reportHtml = $result['html'];
            $title = 'At-Risk Students Report';
            $subtitle = 'Students requiring immediate attention (≥15 failed units)';
            break;
            
        case 'cleared-students':
            $result = generateClearedStudents($program, $includeContact);
            $reportHtml = $result['html'];
            $title = 'Cleared Students List';
            $subtitle = 'Students approved for enrollment';
            break;
            
        case 'pending-review':
            $result = generatePendingReview($program);
            $reportHtml = $result['html'];
            $title = 'Pending Review Items';
            $subtitle = 'Outstanding advising tasks and submissions';
            break;
    }
    
    echo json_encode([
        'success' => true,
        'report' => $reportHtml,
        'title' => $title,
        'subtitle' => $subtitle
    ]);
}

function generateAdviseeSummary($program, $includeGPA, $includeFailed, $includeContact) {
    global $conn, $professor_id;
    
    $query = "
        SELECT 
            s.id,
            s.id_number,
            s.program,
            s.email,
            s.phone_number,
            s.accumulated_failed_units,
            s.advising_cleared,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            COALESCE(
                (SELECT AVG(gpa) FROM term_gpa_summary WHERE student_id = s.id), 
                0
            ) as gpa
        FROM students s
        WHERE s.advisor_id = ?
    ";
    
    $params = [$professor_id];
    $types = "i";
    
    if ($program !== 'all') {
        $query .= " AND s.program = ?";
        $params[] = $program;
        $types .= "s";
    }
    
    $query .= " ORDER BY s.last_name, s.first_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $html = '<div class="data-table"><table><thead><tr>';
    $html .= '<th>Student ID</th><th>Name</th><th>Program</th>';
    
    if ($includeGPA) $html .= '<th>GPA</th>';
    if ($includeFailed) $html .= '<th>Failed Units</th>';
    if ($includeContact) $html .= '<th>Email</th><th>Phone</th>';
    
    $html .= '<th>Status</th></tr></thead><tbody>';
    
    while ($row = $result->fetch_assoc()) {
        $statusClass = $row['advising_cleared'] ? 'badge-success' : 'badge-warning';
        $statusText = $row['advising_cleared'] ? 'Cleared' : 'Not Cleared';
        
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['id_number']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['full_name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['program']) . '</td>';
        
        if ($includeGPA) $html .= '<td><strong>' . number_format($row['gpa'], 2) . '</strong></td>';
        if ($includeFailed) $html .= '<td>' . $row['accumulated_failed_units'] . '</td>';
        if ($includeContact) {
            $html .= '<td>' . htmlspecialchars($row['email']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['phone_number']) . '</td>';
        }
        
        $html .= '<td><span class="badge ' . $statusClass . '">' . $statusText . '</span></td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table></div>';
    
    return ['html' => $html];
}

function generateAcademicPerformance($program, $includeGPA, $includeFailed) {
    global $conn, $professor_id;
    
    $query = "
        SELECT 
            s.id,
            s.id_number,
            s.program,
            s.accumulated_failed_units,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            COALESCE(
                (SELECT AVG(gpa) FROM term_gpa_summary WHERE student_id = s.id), 
                0
            ) as gpa
        FROM students s
        WHERE s.advisor_id = ?
    ";
    
    $params = [$professor_id];
    $types = "i";
    
    if ($program !== 'all') {
        $query .= " AND s.program = ?";
        $params[] = $program;
        $types .= "s";
    }
    
    $query .= " ORDER BY gpa DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $html = '<div class="data-table"><table><thead><tr>';
    $html .= '<th>Rank</th><th>Student ID</th><th>Name</th><th>Program</th>';
    $html .= '<th>GPA</th><th>Failed Units</th><th>Academic Standing</th>';
    $html .= '</tr></thead><tbody>';
    
    $rank = 1;
    while ($row = $result->fetch_assoc()) {
        $gpa = $row['gpa'];
        $standing = $gpa >= 2.5 ? 'Good Standing' : ($gpa >= 2.0 ? 'Needs Improvement' : 'At Risk');
        $standingClass = $gpa >= 2.5 ? 'badge-success' : ($gpa >= 2.0 ? 'badge-warning' : 'badge-danger');
        
        $html .= '<tr>';
        $html .= '<td><strong>#' . $rank . '</strong></td>';
        $html .= '<td>' . htmlspecialchars($row['id_number']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['full_name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['program']) . '</td>';
        $html .= '<td><strong>' . number_format($gpa, 2) . '</strong></td>';
        $html .= '<td>' . $row['accumulated_failed_units'] . '</td>';
        $html .= '<td><span class="badge ' . $standingClass . '">' . $standing . '</span></td>';
        $html .= '</tr>';
        
        $rank++;
    }
    
    $html .= '</tbody></table></div>';
    
    return ['html' => $html];
}

function generateAtRiskReport($program) {
    global $conn, $professor_id;
    
    $query = "
        SELECT 
            s.id,
            s.id_number,
            s.program,
            s.accumulated_failed_units,
            s.email,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            COALESCE(
                (SELECT AVG(gpa) FROM term_gpa_summary WHERE student_id = s.id), 
                0
            ) as gpa
        FROM students s
        WHERE s.advisor_id = ?
            AND s.accumulated_failed_units >= 15
    ";
    
    $params = [$professor_id];
    $types = "i";
    
    if ($program !== 'all') {
        $query .= " AND s.program = ?";
        $params[] = $program;
        $types .= "s";
    }
    
    $query .= " ORDER BY s.accumulated_failed_units DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $html = '<div class="data-table"><table><thead><tr>';
    $html .= '<th>Student ID</th><th>Name</th><th>Program</th>';
    $html .= '<th>Failed Units</th><th>GPA</th><th>Risk Level</th><th>Email</th>';
    $html .= '</tr></thead><tbody>';
    
    while ($row = $result->fetch_assoc()) {
        $failed = $row['accumulated_failed_units'];
        $riskLevel = $failed >= 30 ? 'Critical' : ($failed >= 20 ? 'High' : 'Moderate');
        $riskClass = $failed >= 30 ? 'badge-danger' : ($failed >= 20 ? 'badge-warning' : 'badge-info');
        
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['id_number']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['full_name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['program']) . '</td>';
        $html .= '<td><strong>' . $failed . '</strong></td>';
        $html .= '<td>' . number_format($row['gpa'], 2) . '</td>';
        $html .= '<td><span class="badge ' . $riskClass . '">' . $riskLevel . '</span></td>';
        $html .= '<td>' . htmlspecialchars($row['email']) . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table></div>';
    
    return ['html' => $html];
}

function generateClearedStudents($program, $includeContact) {
    global $conn, $professor_id;
    
    $query = "
        SELECT 
            s.id,
            s.id_number,
            s.program,
            s.email,
            s.phone_number,
            CONCAT(s.first_name, ' ', s.last_name) as full_name
        FROM students s
        WHERE s.advisor_id = ?
            AND s.advising_cleared = 1
    ";
    
    $params = [$professor_id];
    $types = "i";
    
    if ($program !== 'all') {
        $query .= " AND s.program = ?";
        $params[] = $program;
        $types .= "s";
    }
    
    $query .= " ORDER BY s.last_name, s.first_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $html = '<div class="data-table"><table><thead><tr>';
    $html .= '<th>Student ID</th><th>Name</th><th>Program</th>';
    
    if ($includeContact) $html .= '<th>Email</th><th>Phone</th>';
    
    $html .= '</tr></thead><tbody>';
    
    while ($row = $result->fetch_assoc()) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['id_number']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['full_name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['program']) . '</td>';
        
        if ($includeContact) {
            $html .= '<td>' . htmlspecialchars($row['email']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['phone_number']) . '</td>';
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table></div>';
    
    return ['html' => $html];
}

function generatePendingReview($program) {
    global $conn, $professor_id;
    
    $query = "
        SELECT 
            s.id,
            s.id_number,
            s.program,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            sp.id as plan_id,
            sp.academic_year,
            sp.term,
            sp.submission_date,
            DATEDIFF(NOW(), sp.submission_date) as days_pending
        FROM students s
        LEFT JOIN study_plans sp ON sp.student_id = s.id 
            AND sp.cleared = 0 
            AND (sp.adviser_feedback IS NULL OR sp.adviser_feedback = '')
        WHERE s.advisor_id = ?
            AND (s.advising_cleared = 0 OR sp.id IS NOT NULL)
    ";
    
    $params = [$professor_id];
    $types = "i";
    
    if ($program !== 'all') {
        $query .= " AND s.program = ?";
        $params[] = $program;
        $types .= "s";
    }
    
    $query .= " ORDER BY days_pending DESC, s.last_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $html = '<div class="data-table"><table><thead><tr>';
    $html .= '<th>Student ID</th><th>Name</th><th>Program</th>';
    $html .= '<th>Item</th><th>Pending Since</th><th>Days Pending</th><th>Priority</th>';
    $html .= '</tr></thead><tbody>';
    
    while ($row = $result->fetch_assoc()) {
        $item = $row['plan_id'] ? 'Study Plan (' . $row['academic_year'] . ' T' . $row['term'] . ')' : 'Advising Clearance';
        $days = $row['days_pending'] ?? 0;
        $priority = $days >= 7 ? 'High' : ($days >= 3 ? 'Medium' : 'Normal');
        $priorityClass = $days >= 7 ? 'badge-danger' : ($days >= 3 ? 'badge-warning' : 'badge-info');
        
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($row['id_number']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['full_name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['program']) . '</td>';
        $html .= '<td>' . $item . '</td>';
        $html .= '<td>' . ($row['submission_date'] ?? 'N/A') . '</td>';
        $html .= '<td><strong>' . $days . ' days</strong></td>';
        $html .= '<td><span class="badge ' . $priorityClass . '">' . $priority . '</span></td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table></div>';
    
    return ['html' => $html];
}
?>
