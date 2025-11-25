<?php
require_once 'auth_check.php';
require_once 'config.php';

if (!isAuthenticated() || $_SESSION['user_type'] !== 'professor') {
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header('Location: login.php');
    exit();
}

$professor_id = $_SESSION['user_id'];

// Handle API requests
if (isset($_GET['action'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    
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
    exit();
}

// API Functions
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

$professor_id = $_SESSION['user_id'];

// Get professor info
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM professors WHERE id = ?");
$stmt->bind_param("i", $professor_id);
$stmt->execute();
$professor_name = $stmt->get_result()->fetch_assoc()['full_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Professor Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
        /* Sidebar Styles */
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #00A36C; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #00C97F; }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.8; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #7FE5B8; }
        
        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #00A36C; }
        .top-bar p { color: #666; font-size: 14px; margin-top: 5px; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        .report-tabs {
            background: white;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .tabs-header {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
        }

        .tab-btn {
            flex: 1;
            padding: 1rem;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: #999;
            transition: all 0.2s;
            border-bottom: 3px solid transparent;
        }

        .tab-btn:hover {
            background: #f7fafc;
            color: #00A36C;
        }

        .tab-btn.active {
            color: #00A36C;
            border-bottom-color: #00A36C;
        }

        .tab-content {
            display: none;
            padding: 2rem;
        }

        .tab-content.active {
            display: block;
        }

        .filters-section {
            background: #f7fafc;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #666;
            font-size: 0.875rem;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .btn {
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #00A36C;
            color: white;
        }

        .btn-primary:hover {
            background: #2a4365;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #2d3748;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .btn-success {
            background: #38a169;
            color: white;
        }

        .btn-success:hover {
            background: #2f855a;
        }

        .export-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .stat-label {
            font-size: 0.875rem;
            color: #999;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #00A36C;
        }

        .stat-trend {
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .trend-up {
            color: #38a169;
        }

        .trend-down {
            color: #e53e3e;
        }

        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .chart-header h3 {
            font-size: 1.25rem;
            color: #333;
        }

        .data-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #f7fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #666;
            font-size: 0.875rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table tbody tr:hover {
            background: #f7fafc;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-warning {
            background: #feebc8;
            color: #7c2d12;
        }

        .badge-danger {
            background: #fed7d7;
            color: #742a2a;
        }

        .badge-info {
            background: #bee3f8;
            color: #00A36C;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #999;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #00A36C;
            transition: width 0.3s;
        }

        canvas {
            max-width: 100%;
            height: auto !important;
        }

        @media print {
            .navbar, .filters-section, .export-buttons, .btn {
                display: none;
            }
            
            .container {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            
            .chart-container, .data-table, .stat-card {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Professor Portal</h2>
                <p><?php echo htmlspecialchars($professor_name); ?></p>
            </div>
            <nav class="sidebar-menu">
                <a href="prof_dashboard.php" class="menu-item">Dashboard</a>
                <a href="prof_advisees.php" class="menu-item">My Advisees</a>
                <a href="prof_study_plans.php" class="menu-item">Study Plans</a>
                <a href="prof_acadadvising.php" class="menu-item">Academic Advising</a>
                <a href="prof_concerns.php" class="menu-item">Student Concerns</a>
                <a href="prof_reports.php" class="menu-item active">Reports</a>
                <a href="prof_email.php" class="menu-item">Email System</a>
                <a href="prof_schedule.php" class="menu-item">Schedule</a>
                <a href="prof_grade_approvals.php" class="menu-item">Grade Approvals</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <div>
                    <h1>Reports & Analytics</h1>
                    <p>Comprehensive analysis of advisee performance and academic advising metrics</p>
                </div>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
        <div class="report-tabs">
            <div class="tabs-header">
                <button class="tab-btn active" data-tab="overview">Overview</button>
                <button class="tab-btn" data-tab="performance">Advisee Performance</button>
                <button class="tab-btn" data-tab="failed-units">Failed Units Trends</button>
                <button class="tab-btn" data-tab="study-plans">Study Plan Analytics</button>
                <button class="tab-btn" data-tab="custom">Custom Reports</button>
            </div>

            <!-- Overview Tab -->
            <div id="overview-tab" class="tab-content active">
                <div class="filters-section">
                    <div class="filter-group">
                        <label>Academic Year</label>
                        <select id="overview-year">
                            <option value="all">All Years</option>
                            <option value="2024-2025" selected>2024-2025</option>
                            <option value="2023-2024">2023-2024</option>
                            <option value="2022-2023">2022-2023</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Term</label>
                        <select id="overview-term">
                            <option value="all">All Terms</option>
                            <option value="1" selected>Term 1</option>
                            <option value="2">Term 2</option>
                            <option value="3">Term 3</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary" onclick="loadOverviewData()">Apply Filters</button>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div class="export-buttons">
                            <button class="btn btn-success" onclick="exportToPDF('overview')">📄 PDF</button>
                            <button class="btn btn-success" onclick="exportToExcel('overview')">📊 Excel</button>
                        </div>
                    </div>
                </div>

                <div class="stats-grid" id="overview-stats">
                    <div class="loading">Loading statistics...</div>
                </div>

                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Advising Clearance Status</h3>
                    </div>
                    <canvas id="clearance-chart"></canvas>
                </div>

                <div class="chart-container">
                    <div class="chart-header">
                        <h3>GPA Distribution</h3>
                    </div>
                    <canvas id="gpa-distribution-chart"></canvas>
                </div>
            </div>

            <!-- Advisee Performance Tab -->
            <div id="performance-tab" class="tab-content">
                <div class="filters-section">
                    <div class="filter-group">
                        <label>Program</label>
                        <select id="performance-program">
                            <option value="all">All Programs</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Sort By</label>
                        <select id="performance-sort">
                            <option value="gpa-desc">GPA (High to Low)</option>
                            <option value="gpa-asc">GPA (Low to High)</option>
                            <option value="failed-desc">Failed Units (High to Low)</option>
                            <option value="name">Name (A-Z)</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary" onclick="loadPerformanceData()">Apply Filters</button>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div class="export-buttons">
                            <button class="btn btn-success" onclick="exportToPDF('performance')">📄 PDF</button>
                            <button class="btn btn-success" onclick="exportToExcel('performance')">📊 Excel</button>
                        </div>
                    </div>
                </div>

                <div class="data-table" id="performance-table">
                    <div class="loading">Loading performance data...</div>
                </div>
            </div>

            <!-- Failed Units Trends Tab -->
            <div id="failed-units-tab" class="tab-content">
                <div class="filters-section">
                    <div class="filter-group">
                        <label>Time Period</label>
                        <select id="failed-period">
                            <option value="current">Current Year</option>
                            <option value="last-2">Last 2 Years</option>
                            <option value="last-3" selected>Last 3 Years</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Group By</label>
                        <select id="failed-groupby">
                            <option value="term">By Term</option>
                            <option value="year">By Year</option>
                            <option value="course">By Course</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary" onclick="loadFailedUnitsData()">Apply Filters</button>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div class="export-buttons">
                            <button class="btn btn-success" onclick="exportToPDF('failed-units')">📄 PDF</button>
                            <button class="btn btn-success" onclick="exportToExcel('failed-units')">📊 Excel</button>
                        </div>
                    </div>
                </div>

                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Failed Units Trend Over Time</h3>
                    </div>
                    <canvas id="failed-units-trend-chart"></canvas>
                </div>

                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Most Failed Courses</h3>
                    </div>
                    <canvas id="failed-courses-chart"></canvas>
                </div>

                <div class="data-table" id="failed-units-table">
                    <div class="loading">Loading data...</div>
                </div>
            </div>

            <!-- Study Plan Analytics Tab -->
            <div id="study-plans-tab" class="tab-content">
                <div class="filters-section">
                    <div class="filter-group">
                        <label>Academic Year</label>
                        <select id="studyplan-year">
                            <option value="all">All Years</option>
                            <option value="2024-2025" selected>2024-2025</option>
                            <option value="2023-2024">2023-2024</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select id="studyplan-status">
                            <option value="all">All Status</option>
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary" onclick="loadStudyPlanData()">Apply Filters</button>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div class="export-buttons">
                            <button class="btn btn-success" onclick="exportToPDF('study-plans')">📄 PDF</button>
                            <button class="btn btn-success" onclick="exportToExcel('study-plans')">📊 Excel</button>
                        </div>
                    </div>
                </div>

                <div class="stats-grid" id="studyplan-stats">
                    <div class="loading">Loading statistics...</div>
                </div>

                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Study Plan Approval Rate</h3>
                    </div>
                    <canvas id="approval-rate-chart"></canvas>
                </div>

                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Response Time Analysis</h3>
                    </div>
                    <canvas id="response-time-chart"></canvas>
                </div>

                <div class="data-table" id="studyplan-table">
                    <div class="loading">Loading data...</div>
                </div>
            </div>

            <!-- Custom Reports Tab -->
            <div id="custom-tab" class="tab-content">
                <div class="filters-section" style="display: block;">
                    <h3 style="margin-bottom: 1rem;">Generate Custom Report</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="filter-group">
                            <label>Report Type</label>
                            <select id="custom-type">
                                <option value="advisee-summary">Advisee Summary</option>
                                <option value="academic-performance">Academic Performance Analysis</option>
                                <option value="at-risk-students">At-Risk Students</option>
                                <option value="cleared-students">Cleared Students List</option>
                                <option value="pending-review">Pending Review Items</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Date Range</label>
                            <select id="custom-daterange">
                                <option value="current-term">Current Term</option>
                                <option value="current-year">Current Academic Year</option>
                                <option value="last-year">Last Academic Year</option>
                                <option value="all-time">All Time</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>

                        <div class="filter-group" id="custom-date-from-group" style="display: none;">
                            <label>From Date</label>
                            <input type="date" id="custom-date-from">
                        </div>

                        <div class="filter-group" id="custom-date-to-group" style="display: none;">
                            <label>To Date</label>
                            <input type="date" id="custom-date-to">
                        </div>

                        <div class="filter-group">
                            <label>Filter by Program</label>
                            <select id="custom-program">
                                <option value="all">All Programs</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Include</label>
                            <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                                <label style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="checkbox" id="include-gpa" checked> GPA
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="checkbox" id="include-failed" checked> Failed Units
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="checkbox" id="include-contact" checked> Contact Info
                                </label>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button class="btn btn-primary" onclick="generateCustomReport()">Generate Report</button>
                        <button class="btn btn-success" onclick="exportCustomReportPDF()">📄 Export PDF</button>
                        <button class="btn btn-success" onclick="exportCustomReportExcel()">📊 Export Excel</button>
                    </div>
                </div>

                <div id="custom-report-result" style="display: none; margin-top: 2rem;">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3 id="custom-report-title">Custom Report</h3>
                            <p id="custom-report-subtitle" style="color: #718096; font-size: 0.875rem;"></p>
                        </div>
                        <div id="custom-report-content"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    
    <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;
                
                // Update active button
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Update active content
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(tabName + '-tab').classList.add('active');
                
                // Load data for the tab
                loadTabData(tabName);
            });
        });

        // Custom date range toggle
        document.getElementById('custom-daterange').addEventListener('change', function() {
            const isCustom = this.value === 'custom';
            document.getElementById('custom-date-from-group').style.display = isCustom ? 'block' : 'none';
            document.getElementById('custom-date-to-group').style.display = isCustom ? 'block' : 'none';
        });

        // Chart instances
        let charts = {};

        // Load initial data
        loadTabData('overview');
        loadProgramOptions();

        function loadTabData(tab) {
            switch(tab) {
                case 'overview':
                    loadOverviewData();
                    break;
                case 'performance':
                    loadPerformanceData();
                    break;
                case 'failed-units':
                    loadFailedUnitsData();
                    break;
                case 'study-plans':
                    loadStudyPlanData();
                    break;
                case 'custom':
                    // Custom tab doesn't auto-load
                    break;
            }
        }

        function loadProgramOptions() {
            fetch('prof_reports.php?action=get_programs')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const programs = data.programs;
                        
                        // Update all program selects
                        ['performance-program', 'custom-program'].forEach(id => {
                            const select = document.getElementById(id);
                            programs.forEach(prog => {
                                const option = document.createElement('option');
                                option.value = prog;
                                option.textContent = prog;
                                select.appendChild(option);
                            });
                        });
                    }
                });
        }

        // Overview Tab Functions
        function loadOverviewData() {
            const year = document.getElementById('overview-year').value;
            const term = document.getElementById('overview-term').value;

            fetch(`prof_reports.php?action=get_overview_stats&year=${year}&term=${term}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        displayOverviewStats(data.stats);
                        renderClearanceChart(data.clearance);
                        renderGPADistribution(data.gpa_distribution);
                    }
                });
        }

        function displayOverviewStats(stats) {
            const statsHtml = `
                <div class="stat-card">
                    <div class="stat-label">Total Advisees</div>
                    <div class="stat-value">${stats.total_advisees}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Cleared Students</div>
                    <div class="stat-value">${stats.cleared_students}</div>
                    <div class="stat-trend trend-up">
                        ${stats.clearance_rate}% clearance rate
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">At-Risk Students</div>
                    <div class="stat-value">${stats.at_risk_students}</div>
                    <div class="stat-trend ${stats.at_risk_students > 0 ? 'trend-down' : 'trend-up'}">
                        ≥15 failed units
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Average GPA</div>
                    <div class="stat-value">${stats.avg_gpa}</div>
                    <div class="stat-trend">
                        Across all advisees
                    </div>
                </div>
            `;
            document.getElementById('overview-stats').innerHTML = statsHtml;
        }

        function renderClearanceChart(data) {
            const ctx = document.getElementById('clearance-chart');
            
            if (charts['clearance']) {
                charts['clearance'].destroy();
            }
            
            charts['clearance'] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Cleared', 'Not Cleared'],
                    datasets: [{
                        data: [data.cleared, data.not_cleared],
                        backgroundColor: ['#38a169', '#e53e3e'],
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function renderGPADistribution(data) {
            const ctx = document.getElementById('gpa-distribution-chart');
            
            if (charts['gpa-dist']) {
                charts['gpa-dist'].destroy();
            }
            
            charts['gpa-dist'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Number of Students',
                        data: data.counts,
                        backgroundColor: '#2c5282',
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Performance Tab Functions
        function loadPerformanceData() {
            const program = document.getElementById('performance-program').value;
            const sort = document.getElementById('performance-sort').value;

            fetch(`prof_reports.php?action=get_performance_data&program=${program}&sort=${sort}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        displayPerformanceTable(data.students);
                    }
                });
        }

        function displayPerformanceTable(students) {
            if (students.length === 0) {
                document.getElementById('performance-table').innerHTML = `
                    <div class="empty-state">
                        <p>No performance data available</p>
                    </div>
                `;
                return;
            }

            let tableHtml = `
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Program</th>
                            <th>GPA</th>
                            <th>Failed Units</th>
                            <th>Status</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            students.forEach(student => {
                const performanceClass = student.gpa >= 2.5 ? 'badge-success' : (student.gpa >= 2.0 ? 'badge-warning' : 'badge-danger');
                const performanceText = student.gpa >= 2.5 ? 'Good Standing' : (student.gpa >= 2.0 ? 'Needs Improvement' : 'At Risk');
                const statusClass = student.advising_cleared ? 'badge-success' : 'badge-warning';
                const statusText = student.advising_cleared ? 'Cleared' : 'Not Cleared';

                tableHtml += `
                    <tr>
                        <td>${student.id_number}</td>
                        <td>${student.full_name}</td>
                        <td>${student.program}</td>
                        <td><strong>${student.gpa || 'N/A'}</strong></td>
                        <td>${student.accumulated_failed_units}</td>
                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                        <td><span class="badge ${performanceClass}">${performanceText}</span></td>
                    </tr>
                `;
            });

            tableHtml += '</tbody></table>';
            document.getElementById('performance-table').innerHTML = tableHtml;
        }

        // Failed Units Tab Functions
        function loadFailedUnitsData() {
            const period = document.getElementById('failed-period').value;
            const groupBy = document.getElementById('failed-groupby').value;

            fetch(`prof_reports.php?action=get_failed_units_data&period=${period}&groupby=${groupBy}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderFailedUnitsTrend(data.trend_data);
                        renderFailedCourses(data.course_data);
                        displayFailedUnitsTable(data.table_data);
                    }
                });
        }

        function renderFailedUnitsTrend(data) {
            const ctx = document.getElementById('failed-units-trend-chart');
            
            if (charts['failed-trend']) {
                charts['failed-trend'].destroy();
            }
            
            charts['failed-trend'] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Students with Failed Units',
                        data: data.counts,
                        borderColor: '#e53e3e',
                        backgroundColor: 'rgba(229, 62, 62, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        function renderFailedCourses(data) {
            const ctx = document.getElementById('failed-courses-chart');
            
            if (charts['failed-courses']) {
                charts['failed-courses'].destroy();
            }
            
            charts['failed-courses'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.courses,
                    datasets: [{
                        label: 'Number of Failures',
                        data: data.counts,
                        backgroundColor: '#e53e3e',
                    }]
                },
                options: {
                    responsive: true,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        function displayFailedUnitsTable(data) {
            if (data.length === 0) {
                document.getElementById('failed-units-table').innerHTML = `
                    <div class="empty-state">
                        <p>No failed units data available</p>
                    </div>
                `;
                return;
            }

            let tableHtml = `
                <table>
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Total Failures</th>
                            <th>Students Affected</th>
                            <th>Average Grade</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.forEach(row => {
                tableHtml += `
                    <tr>
                        <td><strong>${row.course_code}</strong></td>
                        <td>${row.course_name}</td>
                        <td>${row.failure_count}</td>
                        <td>${row.student_count}</td>
                        <td>${row.avg_grade}</td>
                    </tr>
                `;
            });

            tableHtml += '</tbody></table>';
            document.getElementById('failed-units-table').innerHTML = tableHtml;
        }

        // Study Plan Tab Functions
        function loadStudyPlanData() {
            const year = document.getElementById('studyplan-year').value;
            const status = document.getElementById('studyplan-status').value;

            fetch(`prof_reports.php?action=get_studyplan_data&year=${year}&status=${status}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        displayStudyPlanStats(data.stats);
                        renderApprovalRateChart(data.approval_trend);
                        renderResponseTimeChart(data.response_times);
                        displayStudyPlanTable(data.plans);
                    }
                });
        }

        function displayStudyPlanStats(stats) {
            const statsHtml = `
                <div class="stat-card">
                    <div class="stat-label">Total Submissions</div>
                    <div class="stat-value">${stats.total_plans}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Approved</div>
                    <div class="stat-value">${stats.approved_plans}</div>
                    <div class="stat-trend trend-up">
                        ${stats.approval_rate}% approval rate
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending Review</div>
                    <div class="stat-value">${stats.pending_plans}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Avg Response Time</div>
                    <div class="stat-value">${stats.avg_response_time}</div>
                    <div class="stat-trend">
                        days
                    </div>
                </div>
            `;
            document.getElementById('studyplan-stats').innerHTML = statsHtml;
        }

        function renderApprovalRateChart(data) {
            const ctx = document.getElementById('approval-rate-chart');
            
            if (charts['approval-rate']) {
                charts['approval-rate'].destroy();
            }
            
            charts['approval-rate'] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Approval Rate (%)',
                        data: data.rates,
                        borderColor: '#38a169',
                        backgroundColor: 'rgba(56, 161, 105, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        }

        function renderResponseTimeChart(data) {
            const ctx = document.getElementById('response-time-chart');
            
            if (charts['response-time']) {
                charts['response-time'].destroy();
            }
            
            charts['response-time'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Average Days to Respond',
                        data: data.times,
                        backgroundColor: '#2c5282',
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Days'
                            }
                        }
                    }
                }
            });
        }

        function displayStudyPlanTable(plans) {
            if (plans.length === 0) {
                document.getElementById('studyplan-table').innerHTML = `
                    <div class="empty-state">
                        <p>No study plan data available</p>
                    </div>
                `;
                return;
            }

            let tableHtml = `
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Academic Year</th>
                            <th>Term</th>
                            <th>Submission Date</th>
                            <th>Status</th>
                            <th>Response Time</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            plans.forEach(plan => {
                const statusClass = plan.cleared ? 'badge-success' : (plan.adviser_feedback ? 'badge-danger' : 'badge-warning');
                const statusText = plan.cleared ? 'Approved' : (plan.adviser_feedback ? 'Needs Revision' : 'Pending');

                tableHtml += `
                    <tr>
                        <td>${plan.id_number}</td>
                        <td>${plan.student_name}</td>
                        <td>${plan.academic_year}</td>
                        <td>Term ${plan.term}</td>
                        <td>${plan.submission_date}</td>
                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                        <td>${plan.response_time || 'N/A'}</td>
                    </tr>
                `;
            });

            tableHtml += '</tbody></table>';
            document.getElementById('studyplan-table').innerHTML = tableHtml;
        }

        // Custom Report Functions
        function generateCustomReport() {
            const type = document.getElementById('custom-type').value;
            const dateRange = document.getElementById('custom-daterange').value;
            const program = document.getElementById('custom-program').value;
            const includeGPA = document.getElementById('include-gpa').checked;
            const includeFailed = document.getElementById('include-failed').checked;
            const includeContact = document.getElementById('include-contact').checked;

            const params = new URLSearchParams({
                action: 'generate_custom_report',
                type: type,
                dateRange: dateRange,
                program: program,
                includeGPA: includeGPA,
                includeFailed: includeFailed,
                includeContact: includeContact
            });

            if (dateRange === 'custom') {
                params.append('dateFrom', document.getElementById('custom-date-from').value);
                params.append('dateTo', document.getElementById('custom-date-to').value);
            }

            fetch(`prof_reports.php?${params}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        displayCustomReport(data.report, data.title, data.subtitle);
                    }
                });
        }

        function displayCustomReport(report, title, subtitle) {
            document.getElementById('custom-report-title').textContent = title;
            document.getElementById('custom-report-subtitle').textContent = subtitle;
            document.getElementById('custom-report-content').innerHTML = report;
            document.getElementById('custom-report-result').style.display = 'block';
        }

        // Export Functions
        function exportToPDF(reportType) {
            window.print();
        }

        function exportToExcel(reportType) {
            const data = getReportData(reportType);
            const ws = XLSX.utils.json_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Report');
            XLSX.writeFile(wb, `${reportType}_report_${new Date().toISOString().split('T')[0]}.xlsx`);
        }

        function exportCustomReportPDF() {
            window.print();
        }

        function exportCustomReportExcel() {
            const type = document.getElementById('custom-type').value;
            exportToExcel('custom_' + type);
        }

        function getReportData(reportType) {
            // This would fetch the current view data for export
            // Simplified version here
            return [];
        }
    </script>
        </main>
    </div>
</body>
</html>