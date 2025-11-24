<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$admin_username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Academic Advising System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #6a1b9a; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #4a148c; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.8; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #BA68C8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #6a1b9a; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-left: 4px solid #6a1b9a; }
        .stat-card h3 { font-size: 14px; color: #666; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-value { font-size: 32px; font-weight: 700; color: #6a1b9a; margin-bottom: 5px; }
        .stat-card .stat-label { font-size: 13px; color: #999; }
        .stat-card.blue { border-left-color: #2196F3; }
        .stat-card.blue .stat-value { color: #2196F3; }
        .stat-card.green { border-left-color: #4CAF50; }
        .stat-card.green .stat-value { color: #4CAF50; }
        .stat-card.orange { border-left-color: #ff9800; }
        .stat-card.orange .stat-value { color: #ff9800; }
        .stat-card.red { border-left-color: #f44336; }
        .stat-card.red .stat-value { color: #f44336; }
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h2 { font-size: 22px; color: #6a1b9a; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        .progress-bar { width: 100%; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; display: inline-block; }
        .progress-fill { height: 100%; background: #4CAF50; border-radius: 4px; transition: width 0.3s; }
        .progress-fill.blue { background: #2196F3; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .no-data { text-align: center; padding: 40px; color: #999; font-style: italic; }
        .alert-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .alert-badge.warning { background: #fff3cd; color: #856404; }
        .alert-badge.danger { background: #f8d7da; color: #721c24; }
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
                <a href="admin_advisingassignment.php" class="menu-item">Advising Assignments</a>
                <a href="admin_reports.php" class="menu-item">System Reports</a>
                <a href="admin_bulk_operations.php" class="menu-item">Bulk Ops & Uploads</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Dashboard Overview</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid" id="statsGrid">
                <div class="loading">Loading statistics...</div>
            </div>
            
            <!-- Program Distribution -->
            <div class="content-card">
                <h2>Program Distribution</h2>
                <div id="programDistribution">
                    <div class="loading">Loading program data...</div>
                </div>
            </div>
            
            <!-- Professor Advising Progress -->
            <div class="content-card">
                <h2>Professor Advising Summary</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Professor ID</th>
                                <th>Professor Name</th>
                                <th>Department</th>
                                <th>Total Advisees</th>
                                <th>Completed</th>
                                <th>Pending</th>
                                <th>Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody id="professorProgressTable">
                            <tr><td colspan="7" class="loading">Loading professor data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Current and Planned Enrollment -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div class="content-card">
                    <h2>Current Term Enrollment</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Students Enrolled</th>
                                </tr>
                            </thead>
                            <tbody id="currentEnrollmentTable">
                                <tr><td colspan="2" class="loading">Loading enrollment data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="content-card">
                    <h2>Planned Enrollment - Next Term</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Planning to Enroll</th>
                                </tr>
                            </thead>
                            <tbody id="plannedEnrollmentTable">
                                <tr><td colspan="2" class="loading">Loading planned data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Load dashboard data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardStats();
        });

        function loadDashboardStats() {
            fetch('admin_api.php?action=get_dashboard_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderStats(data.stats);
                        renderProgramDistribution(data.stats.program_distribution);
                        renderProfessorProgress(data.stats.professor_progress);
                        renderCurrentEnrollment(data.stats.current_enrollment);
                        renderPlannedEnrollment(data.stats.planned_enrollment);
                    } else {
                        console.error('Error loading stats:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        function renderStats(stats) {
            const statsGrid = document.getElementById('statsGrid');
            statsGrid.innerHTML = `
                <div class="stat-card">
                    <h3>Total Students</h3>
                    <div class="stat-value">${stats.total_students}</div>
                    <div class="stat-label">Registered in system</div>
                </div>
                <div class="stat-card blue">
                    <h3>Total Professors</h3>
                    <div class="stat-value">${stats.total_professors}</div>
                    <div class="stat-label">Active advisers</div>
                </div>
                <div class="stat-card green">
                    <h3>Cleared Students</h3>
                    <div class="stat-value">${stats.cleared_students}</div>
                    <div class="stat-label">Advising completed</div>
                </div>
                <div class="stat-card orange">
                    <h3>Students Assigned</h3>
                    <div class="stat-value">${stats.assigned_students}</div>
                    <div class="stat-label">Have an adviser</div>
                </div>
                <div class="stat-card orange">
                    <h3>At Risk Students</h3>
                    <div class="stat-value">${stats.at_risk_students}</div>
                    <div class="stat-label">≥15 failed units</div>
                </div>
                <div class="stat-card red">
                    <h3>Critical Students</h3>
                    <div class="stat-value">${stats.critical_students}</div>
                    <div class="stat-label">≥25 failed units</div>
                </div>
            `;
        }

        function renderProgramDistribution(programs) {
            const container = document.getElementById('programDistribution');
            if (programs.length === 0) {
                container.innerHTML = '<div class="no-data">No program data available</div>';
                return;
            }

            let html = '<div class="table-container"><table class="data-table"><thead><tr><th>Program</th><th>Student Count</th><th>Distribution</th></tr></thead><tbody>';
            
            const total = programs.reduce((sum, p) => sum + parseInt(p.count), 0);
            
            programs.forEach(program => {
                const percentage = total > 0 ? Math.round((program.count / total) * 100) : 0;
                html += `
                    <tr>
                        <td><strong>${program.program}</strong></td>
                        <td>${program.count} students</td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="progress-bar" style="flex: 1;">
                                    <div class="progress-fill" style="width: ${percentage}%;"></div>
                                </div>
                                <span style="font-size: 12px;">${percentage}%</span>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        function renderProfessorProgress(professors) {
            const tbody = document.getElementById('professorProgressTable');
            if (professors.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">No professor data available</td></tr>';
                return;
            }

            let html = '';
            professors.forEach(prof => {
                html += `
                    <tr>
                        <td>${prof.id_number}</td>
                        <td>${prof.name}</td>
                        <td>${prof.department}</td>
                        <td>${prof.total_advisees}</td>
                        <td>${prof.completed}</td>
                        <td>${prof.pending}</td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="progress-bar" style="flex: 1;">
                                    <div class="progress-fill" style="width: ${prof.completion_rate}%;"></div>
                                </div>
                                <span style="font-size: 12px; font-weight: 600;">${prof.completion_rate}%</span>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function renderCurrentEnrollment(enrollment) {
            const tbody = document.getElementById('currentEnrollmentTable');
            if (enrollment.length === 0) {
                tbody.innerHTML = '<tr><td colspan="2" class="no-data">No enrollment data available yet</td></tr>';
                return;
            }

            let html = '';
            enrollment.forEach(item => {
                html += `
                    <tr>
                        <td><strong>${item.subject_code}</strong></td>
                        <td>${item.student_count} students</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function renderPlannedEnrollment(enrollment) {
            const tbody = document.getElementById('plannedEnrollmentTable');
            if (enrollment.length === 0) {
                tbody.innerHTML = '<tr><td colspan="2" class="no-data">No planned enrollment data available yet</td></tr>';
                return;
            }

            let html = '';
            enrollment.forEach(item => {
                html += `
                    <tr>
                        <td><strong>${item.subject_code}</strong></td>
                        <td>${item.student_count} students</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }
    </script>
</body>
</html>