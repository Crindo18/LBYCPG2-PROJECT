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
    <title>My Advisees</title>
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
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; transition: background 0.3s; }
        .logout-btn:hover { background: #c82333; }
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .content-card h2 { font-size: 22px; color: #00A36C; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .filter-section { display: flex; gap: 15px; margin-bottom: 20px; }
        .search-box { flex: 1; }
        .search-box input { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; transition: border 0.3s; }
        .search-box input:focus { border-color: #00A36C; outline: none; }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; min-width: 150px; transition: border 0.3s; }
        .filter-select:focus { border-color: #00A36C; outline: none; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge.warning { background: #fff3cd; color: #856404; }
        .badge.danger { background: #f8d7da; color: #721c24; }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.pending { background: #e3f2fd; color: #1565c0; }
        .btn-view { padding: 6px 12px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; transition: background 0.3s; }
        .btn-view:hover { background: #1976D2; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .no-data { text-align: center; padding: 40px; color: #999; font-style: italic; }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; height: auto; }
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
            .filter-section { flex-direction: column; }
            .top-bar { flex-direction: column; gap: 15px; align-items: flex-start; }
            .top-bar h1 { font-size: 24px; }
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
                <a href="prof_advisees.php" class="menu-item active">My Advisees</a>
                <a href="prof_study_plans.php" class="menu-item">Study Plans</a>
                <a href="prof_acadadvising.php" class="menu-item">Academic Advising</a>
                <a href="prof_concerns.php" class="menu-item">Student Concerns</a>
                <a href="prof_reports.php" class="menu-item">Reports</a>
                <a href="prof_email.php" class="menu-item">Email System</a>
                <a href="prof_schedule.php" class="menu-item">Schedule</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>My Advisees</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="content-card">
                <h2>Student List</h2>
                
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="🔍 Search by ID, name, or email..." onkeyup="filterStudents()">
                    </div>
                    <select id="programFilter" class="filter-select" onchange="filterStudents()">
                        <option value="">All Programs</option>
                        <option value="BS Computer Engineering">BSCpE</option>
                        <option value="BS Electronics and Communications Engineering">BSECE</option>
                        <option value="BS Electrical Engineering">BSEE</option>
                    </select>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID Number</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Email</th>
                                <th>Failed Units</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="adviseesTable">
                            <tr><td colspan="7" class="loading">Loading advisees...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        let allStudents = [];

        document.addEventListener('DOMContentLoaded', function() {
            loadAdvisees();
        });

        function loadAdvisees() {
            fetch('prof_api.php?action=get_my_advisees')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allStudents = data.students;
                        renderStudents(allStudents);
                    } else {
                        document.getElementById('adviseesTable').innerHTML = 
                            '<tr><td colspan="7" class="no-data">Failed to load advisees</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('adviseesTable').innerHTML = 
                        '<tr><td colspan="7" class="no-data">Error loading advisees</td></tr>';
                });
        }

        function filterStudents() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const program = document.getElementById('programFilter').value;
            
            let filtered = allStudents.filter(student => {
                const matchesSearch = student.id_number.toString().includes(search) || 
                                     student.full_name.toLowerCase().includes(search) ||
                                     student.email.toLowerCase().includes(search);
                const matchesProgram = !program || student.program === program;
                
                return matchesSearch && matchesProgram;
            });
            
            renderStudents(filtered);
        }

        function renderStudents(students) {
            const tbody = document.getElementById('adviseesTable');
            
            if (students.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">No advisees found</td></tr>';
                return;
            }

            let html = '';
            students.forEach(student => {
                const programShort = student.program.replace('BS ', '');
                
                let failedBadge = '';
                if (student.accumulated_failed_units >= 25) {
                    failedBadge = ' <span class="badge danger">CRITICAL</span>';
                } else if (student.accumulated_failed_units >= 15) {
                    failedBadge = ' <span class="badge warning">AT RISK</span>';
                }
                
                const statusBadge = student.advising_cleared ? 
                    '<span class="badge success">Cleared</span>' : 
                    '<span class="badge pending">Pending</span>';
                
                html += `
                    <tr>
                        <td><strong>${student.id_number}</strong></td>
                        <td>${student.full_name}</td>
                        <td>${programShort}</td>
                        <td>${student.email}</td>
                        <td>${student.accumulated_failed_units} ${failedBadge}</td>
                        <td>${statusBadge}</td>
                        <td><a href="prof_student_view.php?id=${student.id}" class="btn-view">View Details</a></td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
    </script>
</body>
</html>