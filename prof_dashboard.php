<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a professor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'professor') {
    header("Location: login.php");
    exit();
}

// Get professor inf
$professor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM professors WHERE id = ?");
$stmt->bind_param("i", $professor_id);
$stmt->execute();
$result = $stmt->get_result();
$professor = $result->fetch_assoc();
$professor_name = $professor['firstname'] . ' ' . $professor['lastname'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professor Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: #6a1b9a;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 25px 20px;
            background: #4a148c;
        }

        .sidebar-header h2 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 13px;
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            padding: 15px 25px;
            color: white;
            text-decoration: none;
            display: block;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: #BA68C8;
        }

        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 30px;
            width: calc(100% - 260px);
        }

        .top-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-bar h1 {
            font-size: 28px;
            color: #6a1b9a;
        }

        .logout-btn {
            padding: 8px 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: bold;
            color: #6a1b9a;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 13px;
            color: #999;
        }

        .stat-card.warning .value {
            color: #ff9800;
        }

        .stat-card.danger .value {
            color: #dc3545;
        }

        .stat-card.success .value {
            color: #4CAF50;
        }

        .content-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .content-card h2 {
            font-size: 22px;
            color: #6a1b9a;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
        }

        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge.pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge.approved {
            background: #d4edda;
            color: #155724;
        }

        .badge.warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge.danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge.success {
            background: #d4edda;
            color: #155724;
        }

        .btn-view {
            padding: 6px 12px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view:hover {
            background: #1976D2;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }
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
                <div>
                    <h1>Dashboard</h1>
                    <p style="color: #666; font-size: 14px; margin-top: 5px;">Welcome back, Prof. <?php echo htmlspecialchars($professor['lastname']); ?>!</p>
                </div>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Advisees</h3>
                    <div class="value" id="totalAdvisees">0</div>
                    <div class="label">Students assigned</div>
                </div>
                <div class="stat-card warning">
                    <h3>Pending Review</h3>
                    <div class="value" id="pendingPlans">0</div>
                    <div class="label">Study plans awaiting</div>
                </div>
                <div class="stat-card success">
                    <h3>Cleared</h3>
                    <div class="value" id="clearedStudents">0</div>
                    <div class="label">Ready for enrollment</div>
                </div>
                <div class="stat-card danger">
                    <h3>At-Risk</h3>
                    <div class="value" id="atRiskStudents">0</div>
                    <div class="label">≥15 failed units</div>
                </div>
            </div>

            <div class="content-card">
                <h2>Recent Study Plan Submissions</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recentPlansTable">
                            <tr><td colspan="6" class="loading">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-card">
                <h2>Students Requiring Attention</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Failed Units</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="attentionTable">
                            <tr><td colspan="6" class="loading">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardStats();
            loadRecentPlans();
            loadAttentionStudents();
        });

        function loadDashboardStats() {
            fetch('prof_api.php?action=getDashboardStats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('totalAdvisees').textContent = data.stats.totaladvisees;
                        document.getElementById('pendingPlans').textContent = data.stats.pendingplans;
                        document.getElementById('clearedStudents').textContent = data.stats.clearedstudents;
                        document.getElementById('atRiskStudents').textContent = data.stats.atriskstudents;
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function loadRecentPlans() {
            fetch('prof_api.php?action=getRecentPlans')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderRecentPlans(data.plans);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function renderRecentPlans(plans) {
            const tbody = document.getElementById('recentPlansTable');
            
            if (plans.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="no-data">No recent submissions</td></tr>';
                return;
            }

            let html = '';
            plans.forEach(plan => {
                const statusBadge = plan.status === 'approved' 
                    ? '<span class="badge approved">Approved</span>' 
                    : plan.status === 'rejected' 
                        ? '<span class="badge danger">Rejected</span>' 
                        : '<span class="badge pending">Pending</span>';
                
                html += `
                    <tr>
                        <td>${plan.idnumber}</td>
                        <td>${plan.studentname}</td>
                        <td>${plan.program.replace('BS', '')}</td>
                        <td>${formatDate(plan.createdat)}</td>
                        <td>${statusBadge}</td>
                        <td><a href="prof_study_plan_view.php?id=${plan.planid}" class="btn-view">Review</a></td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function loadAttentionStudents() {
            fetch('prof_api.php?action=getAttentionStudents')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderAttentionStudents(data.students);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function renderAttentionStudents(students) {
            const tbody = document.getElementById('attentionTable');
            
            if (students.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="no-data">All students in good standing!</td></tr>';
                return;
            }

            let html = '';
            students.forEach(student => {
                let failedBadge = '';
                if (student.accumulatedfailedunits >= 25) {
                    failedBadge = '<span class="badge danger">CRITICAL</span>';
                } else if (student.accumulatedfailedunits >= 15) {
                    failedBadge = '<span class="badge warning">AT RISK</span>';
                }

                const statusBadge = student.advisingcleared 
                    ? '<span class="badge success">Cleared</span>' 
                    : '<span class="badge pending">Pending</span>';
                
                html += `
                    <tr>
                        <td>${student.idnumber}</td>
                        <td>${student.fullname}</td>
                        <td>${student.program.replace('BS', '')}</td>
                        <td>${student.accumulatedfailedunits} ${failedBadge}</td>
                        <td>${statusBadge}</td>
                        <td><a href="prof_student_view.php?id=${student.id}" class="btn-view">View</a></td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            });
        }
    </script>
</body>
</html>
