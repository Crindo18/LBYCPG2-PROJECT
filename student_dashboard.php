<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_name = $stmt->get_result()->fetch_assoc()['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #1976D2; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #1565C0; }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.9; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #90CAF9; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #1976D2; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        .welcome-card { background: linear-gradient(135deg, #1976D2 0%, #2196F3 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3); }
        .welcome-card h2 { font-size: 32px; margin-bottom: 10px; }
        .welcome-card p { font-size: 16px; opacity: 0.9; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 13px; color: #666; margin-bottom: 10px; text-transform: uppercase; }
        .stat-card .value { font-size: 36px; font-weight: bold; color: #1976D2; margin-bottom: 5px; }
        .stat-card .label { font-size: 13px; color: #999; }
        .stat-card.warning .value { color: #ff9800; }
        .stat-card.danger .value { color: #dc3545; }
        .stat-card.success .value { color: #4CAF50; }
        
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 20px; color: #1976D2; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .info-item { padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .info-item label { font-size: 13px; color: #666; display: block; margin-bottom: 5px; }
        .info-item value { font-size: 16px; font-weight: 600; color: #333; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .alert.danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .alert.info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .alert.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.warning { background: #fff3cd; color: #856404; }
        .badge.danger { background: #f8d7da; color: #721c24; }
        .badge.info { background: #d1ecf1; color: #0c5460; }
        
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .action-btn { padding: 20px; background: white; border: 2px solid #e0e0e0; border-radius: 10px; text-align: center; text-decoration: none; color: #333; transition: all 0.3s; cursor: pointer; }
        .action-btn:hover { border-color: #1976D2; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(25, 118, 210, 0.2); }
        .action-btn .icon { font-size: 32px; margin-bottom: 10px; }
        .action-btn .title { font-weight: 600; font-size: 15px; }
        
        .loading { text-align: center; padding: 40px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Student Portal</h2>
                <p><?php echo htmlspecialchars($student_name); ?></p>
            </div>
            <nav class="sidebar-menu">
                <a href="student_dashboard.php" class="menu-item">Dashboard</a>
                <a href="student_booklet.php" class="menu-item">My Booklet</a>
                <a href="student_study_plan_enhanced.php" class="menu-item">Study Plan</a>
                <a href="student_meeting.php" class="menu-item">Meeting Schedule</a>
                <a href="student_documents.php" class="menu-item">Documents</a>
                <a href="student_concerns.php" class="menu-item">Submit Concern</a>
                <a href="student_profile.php" class="menu-item">My Profile</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Dashboard</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="welcome-card">
                <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>!</h2>
                <p>Here's your academic overview</p>
            </div>
            
            <div id="alerts"></div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Current CGPA</h3>
                    <div class="value" id="cgpa">-</div>
                    <div class="label">Cumulative GPA</div>
                </div>
                
                <div class="stat-card">
                    <h3>Term GPA</h3>
                    <div class="value" id="termGpa">-</div>
                    <div class="label">Last term</div>
                </div>
                
                <div class="stat-card warning">
                    <h3>Failed Units</h3>
                    <div class="value" id="failedUnits">0</div>
                    <div class="label">Out of 30 maximum</div>
                </div>
                
                <div class="stat-card">
                    <h3>Courses Taken</h3>
                    <div class="value" id="totalCourses">0</div>
                    <div class="label">Total courses</div>
                </div>
            </div>
            
            <div class="content-card">
                <h3>Quick Actions</h3>
                <div class="quick-actions">
                    <a href="student_study_plan.php" class="action-btn">
                        <div class="icon">📝</div>
                        <div class="title">Submit Study Plan</div>
                    </a>
                    <a href="student_booklet.php" class="action-btn">
                        <div class="icon">📚</div>
                        <div class="title">View My Booklet</div>
                    </a>
                    <a href="student_concerns.php" class="action-btn">
                        <div class="icon">💬</div>
                        <div class="title">Submit Concern</div>
                    </a>
                    <a href="student_profile.php" class="action-btn">
                        <div class="icon">👤</div>
                        <div class="title">My Profile</div>
                    </a>
                </div>
            </div>
            
            <div class="content-card">
                <h3>Academic Information</h3>
                <div class="info-grid" id="academicInfo">
                    <div class="loading">Loading information...</div>
                </div>
            </div>
            
            <div class="content-card">
                <h3>Adviser Information</h3>
                <div class="info-grid" id="adviserInfo">
                    <div class="loading">Loading adviser...</div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardData();
            loadAdviserInfo();
        });

        function loadDashboardData() {
            fetch('student_api.php?action=get_dashboard_data')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateDashboard(data.data);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function updateDashboard(data) {
            const student = data.student;
            const gpa = data.gpa;
            
            // Update GPA
            document.getElementById('cgpa').textContent = gpa.cgpa || '-';
            document.getElementById('termGpa').textContent = gpa.term_gpa || '-';
            
            // Update failed units
            const failedUnits = student.accumulated_failed_units;
            document.getElementById('failedUnits').textContent = failedUnits;
            
            // Update stat card color based on failed units
            const failedCard = document.getElementById('failedUnits').closest('.stat-card');
            if (failedUnits >= 25) {
                failedCard.classList.remove('warning');
                failedCard.classList.add('danger');
            } else if (failedUnits >= 15) {
                failedCard.classList.add('warning');
            }
            
            // Update total courses
            document.getElementById('totalCourses').textContent = data.total_courses;
            
            // Show alerts for failed units
            const alertsDiv = document.getElementById('alerts');
            if (failedUnits >= 25) {
                alertsDiv.innerHTML = `
                    <div class="alert danger">
                        <strong>⚠ CRITICAL WARNING:</strong> You have ${failedUnits} failed units. You are approaching the 30-unit limit. Please contact your adviser immediately.
                    </div>
                `;
            } else if (failedUnits >= 15) {
                alertsDiv.innerHTML = `
                    <div class="alert warning">
                        <strong>⚠ Warning:</strong> You have ${failedUnits} failed units. Please consult with your adviser about your academic progress.
                    </div>
                `;
            }
            
            // Show clearance status
            if (!student.advising_cleared) {
                alertsDiv.innerHTML += `
                    <div class="alert info">
                        <strong>ℹ Note:</strong> Your advising clearance is pending. Please submit your study plan and wait for adviser approval.
                    </div>
                `;
            } else {
                alertsDiv.innerHTML += `
                    <div class="alert success">
                        <strong>✓ Cleared:</strong> You are cleared for enrollment. Good luck this term!
                    </div>
                `;
            }
            
            // Update academic info
            const academicInfoHTML = `
                <div class="info-item">
                    <label>Student ID</label>
                    <value>${student.id_number}</value>
                </div>
                <div class="info-item">
                    <label>Program</label>
                    <value>${student.program.replace('BS ', '')}</value>
                </div>
                <div class="info-item">
                    <label>Department</label>
                    <value>DECE</value>
                </div>
                <div class="info-item">
                    <label>Advising Status</label>
                    <value>
                        ${student.advising_cleared ? 
                            '<span class="badge success">✓ Cleared</span>' : 
                            '<span class="badge warning">Pending</span>'}
                    </value>
                </div>
            `;
            document.getElementById('academicInfo').innerHTML = academicInfoHTML;
        }

        function loadAdviserInfo() {
            fetch('student_api.php?action=get_adviser_info')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const adviser = data.adviser;
                        const adviserInfoHTML = `
                            <div class="info-item">
                                <label>Adviser Name</label>
                                <value>${adviser.name}</value>
                            </div>
                            <div class="info-item">
                                <label>Email</label>
                                <value>${adviser.email}</value>
                            </div>
                            <div class="info-item">
                                <label>Department</label>
                                <value>DECE</value>
                            </div>
                        `;
                        document.getElementById('adviserInfo').innerHTML = adviserInfoHTML;
                    } else {
                        document.getElementById('adviserInfo').innerHTML = `
                            <div class="alert warning">No adviser assigned yet</div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('adviserInfo').innerHTML = `
                        <div class="alert danger">Error loading adviser information</div>
                    `;
                });
        }
    </script>
</body>
</html>