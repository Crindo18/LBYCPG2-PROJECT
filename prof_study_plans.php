<?php
require_once 'auth_check.php';
requireProfessor();

require_once 'config.php';

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
    <title>Study Plans Review</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #00A36C; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #00C97F; }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.8; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #7FE5B8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #00A36C; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #00A36C; border-bottom-color: #00A36C; }
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .content-card h2 { font-size: 22px; color: #00A36C; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.approved { background: #d4edda; color: #155724; }
        .badge.rejected { background: #f8d7da; color: #721c24; }
        .btn-review { padding: 6px 12px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-review:hover { background: #1976D2; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .no-data { text-align: center; padding: 40px; color: #999; font-style: italic; }
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
                <a href="prof_study_plans.php" class="menu-item active">Study Plans</a>
                <a href="prof_acadadvising.php" class="menu-item">Academic Advising</a>
                <a href="prof_concerns.php" class="menu-item">Student Concerns</a>
                <a href="prof_reports.php" class="menu-item">Reports</a>
                <a href="prof_email.php" class="menu-item">Email System</a>
                <a href="prof_schedule.php" class="menu-item">Schedule</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Study Plans Review</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('pending')">Pending Review</button>
                <button class="tab-btn" onclick="switchTab('approved')">Approved</button>
                <button class="tab-btn" onclick="switchTab('rejected')">Rejected</button>
                <button class="tab-btn" onclick="switchTab('all')">All Plans</button>
            </div>
            
            <div class="content-card">
                <h2 id="tabTitle">Pending Study Plans</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Program</th>
                                <th>Academic Year</th>
                                <th>Term</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="plansTable">
                            <tr><td colspan="8" class="loading">Loading study plans...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        let currentStatus = 'pending';

        document.addEventListener('DOMContentLoaded', function() {
            loadPlans('pending');
        });

        function switchTab(status) {
            currentStatus = status;
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            const titles = {
                'pending': 'Pending Study Plans',
                'approved': 'Approved Study Plans',
                'rejected': 'Rejected Study Plans',
                'all': 'All Study Plans'
            };
            document.getElementById('tabTitle').textContent = titles[status];
            
            loadPlans(status);
        }

        function loadPlans(status) {
            fetch(`prof_api.php?action=get_study_plans&status=${status}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderPlans(data.plans);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function renderPlans(plans) {
            const tbody = document.getElementById('plansTable');
            
            if (plans.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="no-data">No study plans found</td></tr>';
                return;
            }

            let html = '';
            plans.forEach(plan => {
                let statusBadge = '';
                if (plan.status === 'approved') {
                    statusBadge = '<span class="badge approved">Approved</span>';
                } else if (plan.status === 'rejected') {
                    statusBadge = '<span class="badge rejected">Rejected</span>';
                } else {
                    statusBadge = '<span class="badge pending">Pending</span>';
                }
                
                html += `
                    <tr>
                        <td><strong>${plan.id_number}</strong></td>
                        <td>${plan.student_name}</td>
                        <td>${plan.program.replace('BS ', '')}</td>
                        <td>${plan.academic_year || 'N/A'}</td>
                        <td>${plan.term || 'N/A'}</td>
                        <td>${formatDate(plan.created_at)}</td>
                        <td>${statusBadge}</td>
                        <td><a href="prof_study_plan_view.php?id=${plan.id}" class="btn-review">Review</a></td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    </script>
</body>
</html>