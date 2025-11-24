<?php
require_once 'auth_check.php';
require_once 'config.php';

if (!isAuthenticated() || $_SESSION['user_type'] !== 'professor') {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header('Location: login.php');
    exit();
}

$professor_id = $_SESSION['user_id'];

// Handle API requests
if (isset($_GET['action']) || isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
    case 'get_stats':
        getStats($conn, $professor_id);
        break;
    
    case 'get_pending':
        getPendingRequests($conn, $professor_id);
        break;
    
    case 'get_history':
        getHistory($conn, $professor_id);
        break;
    
    case 'approve_request':
        approveRequest($conn, $professor_id);
        break;
    
    case 'reject_request':
        rejectRequest($conn, $professor_id);
        break;
    
    case 'bulk_approve':
        bulkApprove($conn, $professor_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit();
}

// API Functions
function getStats($conn, $professor_id) {
    // Pending count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as pending
        FROM grade_edit_requests ger
        JOIN students s ON s.id = ger.student_id
        WHERE s.advisor_id = ? AND ger.status = 'pending'
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $pending = $stmt->get_result()->fetch_assoc()['pending'];
    
    // Approved today
    $stmt = $conn->prepare("
        SELECT COUNT(*) as approved_today
        FROM grade_edit_requests ger
        JOIN students s ON s.id = ger.student_id
        WHERE s.advisor_id = ? 
        AND ger.status = 'approved'
        AND DATE(ger.processed_date) = CURDATE()
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $approved_today = $stmt->get_result()->fetch_assoc()['approved_today'];
    
    // Total processed
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_processed
        FROM grade_edit_requests ger
        JOIN students s ON s.id = ger.student_id
        WHERE s.advisor_id = ? AND ger.status IN ('approved', 'rejected')
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $total_processed = $stmt->get_result()->fetch_assoc()['total_processed'];
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'pending' => $pending,
            'approved_today' => $approved_today,
            'total_processed' => $total_processed
        ]
    ]);
}

function getPendingRequests($conn, $professor_id) {
    $stmt = $conn->prepare("
        SELECT 
            ger.*,
            s.id_number,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            c.course_code,
            c.course_name
        FROM grade_edit_requests ger
        JOIN students s ON s.id = ger.student_id
        JOIN courses c ON c.id = ger.course_id
        WHERE s.advisor_id = ? AND ger.status = 'pending'
        ORDER BY ger.request_date ASC
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    
    echo json_encode(['success' => true, 'requests' => $requests]);
}

function getHistory($conn, $professor_id) {
    $stmt = $conn->prepare("
        SELECT 
            ger.*,
            s.id_number,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            c.course_code,
            c.course_name
        FROM grade_edit_requests ger
        JOIN students s ON s.id = ger.student_id
        JOIN courses c ON c.id = ger.course_id
        WHERE s.advisor_id = ? AND ger.status IN ('approved', 'rejected')
        ORDER BY ger.processed_date DESC
        LIMIT 100
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    
    echo json_encode(['success' => true, 'requests' => $requests]);
}

function approveRequest($conn, $professor_id) {
    $request_id = $_POST['request_id'];
    
    $conn->begin_transaction();
    
    try {
        // Verify request belongs to professor's advisee
        $stmt = $conn->prepare("
            SELECT ger.*, s.advisor_id
            FROM grade_edit_requests ger
            JOIN students s ON s.id = ger.student_id
            WHERE ger.id = ? AND ger.status = 'pending'
        ");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        
        if (!$request || $request['advisor_id'] != $professor_id) {
            throw new Exception('Request not found or unauthorized');
        }
        
        // Update grade in student_grades table
        $stmt = $conn->prepare("
            UPDATE student_grades
            SET grade = ?, updated_at = NOW()
            WHERE student_id = ? AND course_id = ? AND academic_year = ? AND term = ?
        ");
        $stmt->bind_param("siiss", 
            $request['new_grade'],
            $request['student_id'],
            $request['course_id'],
            $request['academic_year'],
            $request['term']
        );
        $stmt->execute();
        
        // If no record exists, insert it
        if ($stmt->affected_rows === 0) {
            $stmt = $conn->prepare("
                INSERT INTO student_grades (student_id, course_id, grade, academic_year, term, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("iisss",
                $request['student_id'],
                $request['course_id'],
                $request['new_grade'],
                $request['academic_year'],
                $request['term']
            );
            $stmt->execute();
        }
        
        // Update request status
        $stmt = $conn->prepare("
            UPDATE grade_edit_requests
            SET status = 'approved', processed_by = ?, processed_date = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $professor_id, $request_id);
        $stmt->execute();
        
        // Recalculate failed units if grade is failing
        $failing_grades = ['5.0', 'F', 'INC', 'DRP'];
        if (in_array($request['new_grade'], $failing_grades)) {
            recalculateFailedUnits($conn, $request['student_id']);
        }
        
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function rejectRequest($conn, $professor_id) {
    $request_id = $_POST['request_id'];
    $notes = trim($_POST['notes']);
    
    // Verify request belongs to professor's advisee
    $stmt = $conn->prepare("
        SELECT s.advisor_id
        FROM grade_edit_requests ger
        JOIN students s ON s.id = ger.student_id
        WHERE ger.id = ? AND ger.status = 'pending'
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result || $result['advisor_id'] != $professor_id) {
        echo json_encode(['success' => false, 'message' => 'Request not found or unauthorized']);
        return;
    }
    
    $stmt = $conn->prepare("
        UPDATE grade_edit_requests
        SET status = 'rejected', processed_by = ?, processed_date = NOW(), rejection_notes = ?
        WHERE id = ?
    ");
    $stmt->bind_param("isi", $professor_id, $notes, $request_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function bulkApprove($conn, $professor_id) {
    $request_ids = json_decode($_POST['request_ids'], true);
    
    if (empty($request_ids)) {
        echo json_encode(['success' => false, 'message' => 'No requests selected']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        $count = 0;
        
        foreach ($request_ids as $request_id) {
            // Verify and get request details
            $stmt = $conn->prepare("
                SELECT ger.*, s.advisor_id
                FROM grade_edit_requests ger
                JOIN students s ON s.id = ger.student_id
                WHERE ger.id = ? AND ger.status = 'pending'
            ");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            
            if (!$request || $request['advisor_id'] != $professor_id) {
                continue;
            }
            
            // Update grade
            $stmt = $conn->prepare("
                UPDATE student_grades
                SET grade = ?, updated_at = NOW()
                WHERE student_id = ? AND course_id = ? AND academic_year = ? AND term = ?
            ");
            $stmt->bind_param("siiss",
                $request['new_grade'],
                $request['student_id'],
                $request['course_id'],
                $request['academic_year'],
                $request['term']
            );
            $stmt->execute();
            
            // Insert if not exists
            if ($stmt->affected_rows === 0) {
                $stmt = $conn->prepare("
                    INSERT INTO student_grades (student_id, course_id, grade, academic_year, term, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("iisss",
                    $request['student_id'],
                    $request['course_id'],
                    $request['new_grade'],
                    $request['academic_year'],
                    $request['term']
                );
                $stmt->execute();
            }
            
            // Update request status
            $stmt = $conn->prepare("
                UPDATE grade_edit_requests
                SET status = 'approved', processed_by = ?, processed_date = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $professor_id, $request_id);
            $stmt->execute();
            
            // Recalculate failed units
            $failing_grades = ['5.0', 'F', 'INC', 'DRP'];
            if (in_array($request['new_grade'], $failing_grades)) {
                recalculateFailedUnits($conn, $request['student_id']);
            }
            
            $count++;
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'count' => $count]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function recalculateFailedUnits($conn, $student_id) {
    $stmt = $conn->prepare("
        SELECT SUM(c.units) as total_failed
        FROM student_grades sg
        JOIN courses c ON c.id = sg.course_id
        WHERE sg.student_id = ? 
        AND sg.grade IN ('5.0', 'F', 'INC', 'DRP')
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $total_failed = $result['total_failed'] ?? 0;
    
    $stmt = $conn->prepare("
        UPDATE students
        SET accumulated_failed_units = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $total_failed, $student_id);
    $stmt->execute();
}

$professor_id = $_SESSION['user_id'];
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
    <title>Grade Edit Approvals - Professor Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #6a1b9a; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #4a148c; }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.8; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #BA68C8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #6a1b9a; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        .stats-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-card .label { font-size: 13px; color: #666; text-transform: uppercase; margin-bottom: 5px; }
        .stat-card .value { font-size: 32px; font-weight: bold; color: #6a1b9a; }
        .stat-card.warning .value { color: #ff9800; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #6a1b9a; border-bottom-color: #6a1b9a; }
        
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 20px; color: #6a1b9a; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .toolbar { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; }
        .search-box { flex: 1; }
        .search-box input { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #6a1b9a; color: white; }
        .btn-primary:hover { background: #8e24aa; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-small { padding: 6px 12px; font-size: 13px; }
        
        .request-list { display: grid; gap: 15px; }
        .request-card { padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; transition: all 0.3s; }
        .request-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .request-card.selected { border-color: #6a1b9a; background: #f3e5f5; }
        
        .request-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .student-info { font-weight: 600; color: #333; }
        .request-date { font-size: 13px; color: #999; }
        
        .grade-change { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .grade-item label { display: block; font-size: 12px; color: #666; margin-bottom: 5px; }
        .grade-item .value { font-weight: 600; color: #333; }
        .grade-old { color: #dc3545; }
        .grade-new { color: #28a745; }
        
        .reason-box { padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; margin: 15px 0; border-radius: 5px; }
        .reason-box label { display: block; font-weight: 600; color: #856404; margin-bottom: 5px; }
        .reason-box p { color: #856404; font-size: 14px; }
        
        .request-actions { display: flex; gap: 10px; margin-top: 15px; }
        
        .checkbox { margin-right: 10px; }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.approved { background: #d4edda; color: #155724; }
        .badge.rejected { background: #f8d7da; color: #721c24; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%; }
        .modal-content h3 { color: #6a1b9a; margin-bottom: 20px; }
        .modal-content textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; min-height: 120px; font-family: inherit; margin-bottom: 20px; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
        
        .loading { text-align: center; padding: 40px; color: #666; }
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
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
                <a href="prof_reports.php" class="menu-item">Reports</a>
                <a href="prof_email.php" class="menu-item active">Email System</a>
                <a href="prof_schedule.php" class="menu-item">Schedule</a>
                <a href="prof_grade_approvals.php" class="menu-item">Grade Approvals</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Grade Edit Approvals</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="stats-bar">
                <div class="stat-card warning">
                    <div class="label">Pending Requests</div>
                    <div class="value" id="pendingCount">0</div>
                </div>
                <div class="stat-card">
                    <div class="label">Approved Today</div>
                    <div class="value" id="approvedToday">0</div>
                </div>
                <div class="stat-card">
                    <div class="label">Total Processed</div>
                    <div class="value" id="totalProcessed">0</div>
                </div>
            </div>
            
            <div id="alertContainer"></div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('pending')">Pending Requests</button>
                <button class="tab-btn" onclick="switchTab('history')">History</button>
            </div>
            
            <!-- Pending Tab -->
            <div id="pending-tab" class="tab-content active">
                <div class="content-card">
                    <div class="toolbar">
                        <div class="search-box">
                            <input type="text" id="searchPending" placeholder="🔍 Search by student ID, name, or course..." onkeyup="filterRequests()">
                        </div>
                        <button class="btn btn-success" onclick="bulkApprove()" id="bulkApproveBtn" disabled>
                            Approve Selected (<span id="selectedCount">0</span>)
                        </button>
                        <button class="btn btn-primary" onclick="selectAll()">Select All</button>
                    </div>
                    
                    <div class="request-list" id="pendingRequests">
                        <div class="loading">Loading requests...</div>
                    </div>
                </div>
            </div>
            
            <!-- History Tab -->
            <div id="history-tab" class="tab-content">
                <div class="content-card">
                    <div class="toolbar">
                        <div class="search-box">
                            <input type="text" id="searchHistory" placeholder="🔍 Search history..." onkeyup="filterHistory()">
                        </div>
                        <select id="statusFilter" onchange="filterHistory()" style="padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="all">All Status</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="request-list" id="historyRequests">
                        <div class="loading">Loading history...</div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3>Reject Grade Edit Request</h3>
            <p style="margin-bottom: 15px; color: #666;">Please provide a reason for rejection:</p>
            <textarea id="rejectionNotes" placeholder="Enter rejection reason..."></textarea>
            <div class="modal-actions">
                <button class="btn btn-danger" onclick="confirmReject()">Reject</button>
                <button class="btn btn-primary" onclick="closeRejectModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        let allRequests = [];
        let historyRequests = [];
        let selectedRequests = new Set();
        let currentRejectId = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            loadPendingRequests();
            loadHistory();
        });
        
        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        function loadStats() {
            fetch('prof_grade_approvals.php?action=get_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('pendingCount').textContent = data.stats.pending;
                        document.getElementById('approvedToday').textContent = data.stats.approved_today;
                        document.getElementById('totalProcessed').textContent = data.stats.total_processed;
                    }
                });
        }
        
        function loadPendingRequests() {
            fetch('prof_grade_approvals.php?action=get_pending')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allRequests = data.requests;
                        renderPendingRequests(allRequests);
                    }
                });
        }
        
        function renderPendingRequests(requests) {
            const container = document.getElementById('pendingRequests');
            
            if (requests.length === 0) {
                container.innerHTML = '<div class="empty-state">No pending requests</div>';
                return;
            }
            
            let html = '';
            requests.forEach(req => {
                const isSelected = selectedRequests.has(req.id);
                html += `
                    <div class="request-card ${isSelected ? 'selected' : ''}" data-id="${req.id}">
                        <div class="request-header">
                            <div>
                                <input type="checkbox" class="checkbox" ${isSelected ? 'checked' : ''} 
                                       onchange="toggleSelect(${req.id})" onclick="event.stopPropagation()">
                                <span class="student-info">${req.student_name} (${req.id_number})</span>
                            </div>
                            <div class="request-date">${formatDate(req.request_date)}</div>
                        </div>
                        
                        <div class="grade-change">
                            <div class="grade-item">
                                <label>Course</label>
                                <div class="value">${req.course_code} - ${req.course_name}</div>
                            </div>
                            <div class="grade-item">
                                <label>Current Grade</label>
                                <div class="value grade-old">${req.old_grade || 'N/A'}</div>
                            </div>
                            <div class="grade-item">
                                <label>Requested Grade</label>
                                <div class="value grade-new">${req.new_grade}</div>
                            </div>
                        </div>
                        
                        <div class="grade-change" style="grid-template-columns: 1fr 1fr;">
                            <div class="grade-item">
                                <label>Academic Year</label>
                                <div class="value">${req.academic_year}</div>
                            </div>
                            <div class="grade-item">
                                <label>Term</label>
                                <div class="value">${req.term}</div>
                            </div>
                        </div>
                        
                        <div class="reason-box">
                            <label>Student's Reason:</label>
                            <p>${req.reason}</p>
                        </div>
                        
                        <div class="request-actions">
                            <button class="btn btn-success btn-small" onclick="approveRequest(${req.id})">✓ Approve</button>
                            <button class="btn btn-danger btn-small" onclick="rejectRequest(${req.id})">✗ Reject</button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function toggleSelect(requestId) {
            if (selectedRequests.has(requestId)) {
                selectedRequests.delete(requestId);
            } else {
                selectedRequests.add(requestId);
            }
            updateSelectedCount();
            renderPendingRequests(allRequests);
        }
        
        function selectAll() {
            if (selectedRequests.size === allRequests.length) {
                selectedRequests.clear();
            } else {
                allRequests.forEach(req => selectedRequests.add(req.id));
            }
            updateSelectedCount();
            renderPendingRequests(allRequests);
        }
        
        function updateSelectedCount() {
            document.getElementById('selectedCount').textContent = selectedRequests.size;
            document.getElementById('bulkApproveBtn').disabled = selectedRequests.size === 0;
        }
        
        function approveRequest(requestId) {
            if (!confirm('Approve this grade edit request?')) return;
            
            const formData = new FormData();
            formData.append('action', 'approve_request');
            formData.append('request_id', requestId);
            
            fetch('prof_grade_approvals.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Grade edit approved!', 'success');
                    loadStats();
                    loadPendingRequests();
                    loadHistory();
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            });
        }
        
        function rejectRequest(requestId) {
            currentRejectId = requestId;
            document.getElementById('rejectModal').classList.add('active');
            document.getElementById('rejectionNotes').value = '';
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
            currentRejectId = null;
        }
        
        function confirmReject() {
            const notes = document.getElementById('rejectionNotes').value.trim();
            
            if (!notes) {
                alert('Please provide a rejection reason');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'reject_request');
            formData.append('request_id', currentRejectId);
            formData.append('notes', notes);
            
            fetch('prof_grade_approvals.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Grade edit rejected', 'success');
                    closeRejectModal();
                    loadStats();
                    loadPendingRequests();
                    loadHistory();
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            });
        }
        
        function bulkApprove() {
            if (selectedRequests.size === 0) return;
            
            if (!confirm(`Approve ${selectedRequests.size} grade edit request(s)?`)) return;
            
            const formData = new FormData();
            formData.append('action', 'bulk_approve');
            formData.append('request_ids', JSON.stringify([...selectedRequests]));
            
            fetch('prof_grade_approvals.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(`${data.count} grade edit(s) approved!`, 'success');
                    selectedRequests.clear();
                    updateSelectedCount();
                    loadStats();
                    loadPendingRequests();
                    loadHistory();
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            });
        }
        
        function loadHistory() {
            fetch('prof_grade_approvals.php?action=get_history')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        historyRequests = data.requests;
                        filterHistory();
                    }
                });
        }
        
        function filterHistory() {
            const search = document.getElementById('searchHistory').value.toLowerCase();
            const status = document.getElementById('statusFilter').value;
            
            let filtered = historyRequests.filter(req => {
                const matchesSearch = req.student_name.toLowerCase().includes(search) ||
                                     req.id_number.toString().includes(search) ||
                                     req.course_code.toLowerCase().includes(search);
                const matchesStatus = status === 'all' || req.status === status;
                
                return matchesSearch && matchesStatus;
            });
            
            renderHistory(filtered);
        }
        
        function renderHistory(requests) {
            const container = document.getElementById('historyRequests');
            
            if (requests.length === 0) {
                container.innerHTML = '<div class="empty-state">No history records</div>';
                return;
            }
            
            let html = '';
            requests.forEach(req => {
                const statusBadge = req.status === 'approved' ? 
                    '<span class="badge approved">Approved</span>' : 
                    '<span class="badge rejected">Rejected</span>';
                
                html += `
                    <div class="request-card">
                        <div class="request-header">
                            <div>
                                <span class="student-info">${req.student_name} (${req.id_number})</span>
                                ${statusBadge}
                            </div>
                            <div class="request-date">${formatDate(req.processed_date)}</div>
                        </div>
                        
                        <div class="grade-change">
                            <div class="grade-item">
                                <label>Course</label>
                                <div class="value">${req.course_code}</div>
                            </div>
                            <div class="grade-item">
                                <label>Old Grade</label>
                                <div class="value">${req.old_grade || 'N/A'}</div>
                            </div>
                            <div class="grade-item">
                                <label>New Grade</label>
                                <div class="value">${req.new_grade}</div>
                            </div>
                        </div>
                        
                        ${req.status === 'rejected' && req.rejection_notes ? `
                            <div class="reason-box" style="background: #f8d7da; border-left-color: #dc3545;">
                                <label style="color: #721c24;">Rejection Notes:</label>
                                <p style="color: #721c24;">${req.rejection_notes}</p>
                            </div>
                        ` : ''}
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function filterRequests() {
            const search = document.getElementById('searchPending').value.toLowerCase();
            
            const filtered = allRequests.filter(req => 
                req.student_name.toLowerCase().includes(search) ||
                req.id_number.toString().includes(search) ||
                req.course_code.toLowerCase().includes(search)
            );
            
            renderPendingRequests(filtered);
        }
        
        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            container.innerHTML = `<div class="alert ${type}">${message}</div>`;
            setTimeout(() => container.innerHTML = '', 5000);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    </script>
</body>
</html>
