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
    <title>Advising Forms Review</title>
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
        
        .stats-bar { display: flex; gap: 20px; margin-bottom: 30px; }
        .stat-item { 
            flex: 1; 
            padding: 25px; 
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px; 
            text-align: center; 
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        .stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            transition: all 0.3s ease;
        }
        .stat-item:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }
        .stat-item:nth-child(1) { border-color: rgba(255, 193, 7, 0.1); }
        .stat-item:nth-child(1)::before { background: linear-gradient(90deg, #FFC107, #FFD54F); }
        .stat-item:nth-child(1):hover { border-color: rgba(255, 193, 7, 0.3); }
        .stat-item:nth-child(2) { border-color: rgba(76, 175, 80, 0.1); }
        .stat-item:nth-child(2)::before { background: linear-gradient(90deg, #4CAF50, #81C784); }
        .stat-item:nth-child(2):hover { border-color: rgba(76, 175, 80, 0.3); }
        .stat-item:nth-child(3) { border-color: rgba(244, 67, 54, 0.1); }
        .stat-item:nth-child(3)::before { background: linear-gradient(90deg, #F44336, #EF5350); }
        .stat-item:nth-child(3):hover { border-color: rgba(244, 67, 54, 0.3); }
        .stat-item:nth-child(4) { border-color: rgba(33, 150, 243, 0.1); }
        .stat-item:nth-child(4)::before { background: linear-gradient(90deg, #2196F3, #64B5F6); }
        .stat-item:nth-child(4):hover { border-color: rgba(33, 150, 243, 0.3); }
        
        .stat-item .label { 
            font-size: 12px; 
            color: #666; 
            margin-bottom: 10px; 
            text-transform: uppercase; 
            letter-spacing: 1px;
            font-weight: 600;
        }
        .stat-item .value { 
            font-size: 36px; 
            font-weight: 700; 
            line-height: 1;
        }
        .stat-item:nth-child(1) .value {
            background: linear-gradient(135deg, #FFC107 0%, #FFD54F 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-item:nth-child(2) .value {
            background: linear-gradient(135deg, #4CAF50 0%, #81C784 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-item:nth-child(3) .value {
            background: linear-gradient(135deg, #F44336 0%, #EF5350 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-item:nth-child(4) .value {
            background: linear-gradient(135deg, #2196F3 0%, #64B5F6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #00A36C; border-bottom-color: #00A36C; }
        .tab-btn:hover { color: #00A36C; background: rgba(0, 163, 108, 0.05); }
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .content-card h2 { font-size: 22px; color: #00A36C; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .filter-section { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .search-box { flex: 1; min-width: 250px; }
        .search-box input { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; transition: border 0.3s; }
        .search-box input:focus { border-color: #00A36C; outline: none; }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; min-width: 150px; transition: border 0.3s; }
        .filter-select:focus { border-color: #00A36C; outline: none; }
        
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; white-space: nowrap; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.approved { background: #d4edda; color: #155724; }
        .badge.rejected { background: #f8d7da; color: #721c24; }
        .badge.revision { background: #e3f2fd; color: #1565c0; }
        .btn-review { padding: 6px 12px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; transition: background 0.3s; }
        .btn-review:hover { background: #1976D2; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .no-data { text-align: center; padding: 40px; color: #999; font-style: italic; }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; height: auto; }
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
            .stats-bar { flex-direction: column; }
            .filter-section { flex-direction: column; }
            .top-bar { flex-direction: column; gap: 15px; align-items: flex-start; }
            .tabs { overflow-x: auto; }
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
                <a href="prof_advising_forms.php" class="menu-item active">Advising Forms</a>
                <a href="prof_acadadvising.php" class="menu-item">Academic Advising</a>
                <a href="prof_concerns.php" class="menu-item">Student Concerns</a>
                <a href="prof_reports.php" class="menu-item">Reports</a>
                <a href="prof_email.php" class="menu-item">Email System</a>
                <a href="prof_schedule.php" class="menu-item">Schedule</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Advising Forms Review</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="stats-bar">
                <div class="stat-item">
                    <div class="label">Pending</div>
                    <div class="value" id="statPending">0</div>
                </div>
                <div class="stat-item">
                    <div class="label">Approved</div>
                    <div class="value" id="statApproved">0</div>
                </div>
                <div class="stat-item">
                    <div class="label">Rejected</div>
                    <div class="value" id="statRejected">0</div>
                </div>
                <div class="stat-item">
                    <div class="label">Revision Needed</div>
                    <div class="value" id="statRevision">0</div>
                </div>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('all')">All Forms</button>
                <button class="tab-btn" onclick="switchTab('pending')">Pending Review</button>
                <button class="tab-btn" onclick="switchTab('approved')">Approved</button>
                <button class="tab-btn" onclick="switchTab('rejected')">Rejected</button>
                <button class="tab-btn" onclick="switchTab('revision_requested')">Needs Revision</button>
            </div>
            
            <div class="content-card">
                <h2 id="tabTitle">All Advising Forms</h2>
                
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="🔍 Search by ID, name, or academic year..." onkeyup="filterForms()">
                    </div>
                    <select id="termFilter" class="filter-select" onchange="filterForms()">
                        <option value="">All Terms</option>
                        <option value="Term 1">Term 1</option>
                        <option value="Term 2">Term 2</option>
                        <option value="Term 3">Term 3</option>
                    </select>
                    <select id="programFilter" class="filter-select" onchange="filterForms()">
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
                                <th>Form ID</th>
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
                        <tbody id="formsTable">
                            <tr><td colspan="9" class="loading">Loading advising forms...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        let currentStatus = 'all';
        let allForms = [];

        document.addEventListener('DOMContentLoaded', function() {
            loadForms();
        });

        function switchTab(status) {
            currentStatus = status;
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            const titles = {
                'all': 'All Advising Forms',
                'pending': 'Pending Advising Forms',
                'approved': 'Approved Advising Forms',
                'rejected': 'Rejected Advising Forms',
                'revision_requested': 'Forms Needing Revision'
            };
            document.getElementById('tabTitle').textContent = titles[status];
            
            filterForms();
        }

        function loadForms() {
            fetch('prof_advising_api.php?action=get_advising_forms')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allForms = data.forms;
                        updateStats(data.stats);
                        filterForms();
                    } else {
                        document.getElementById('formsTable').innerHTML = 
                            '<tr><td colspan="9" class="no-data">Failed to load advising forms</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('formsTable').innerHTML = 
                        '<tr><td colspan="9" class="no-data">Error loading advising forms</td></tr>';
                });
        }

        function updateStats(stats) {
            document.getElementById('statPending').textContent = stats.pending || 0;
            document.getElementById('statApproved').textContent = stats.approved || 0;
            document.getElementById('statRejected').textContent = stats.rejected || 0;
            document.getElementById('statRevision').textContent = stats.revision_requested || 0;
        }

        function filterForms() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const term = document.getElementById('termFilter').value;
            const program = document.getElementById('programFilter').value;
            
            let filtered = allForms.filter(form => {
                // Status filter
                if (currentStatus !== 'all' && form.status !== currentStatus) return false;
                
                // Search filter
                const searchableText = `${form.form_id} ${form.id_number} ${form.student_name} ${form.academic_year}`.toLowerCase();
                if (search && !searchableText.includes(search)) return false;
                
                // Term filter
                if (term && form.term !== term) return false;
                
                // Program filter
                if (program && form.program !== program) return false;
                
                return true;
            });
            
            renderForms(filtered);
        }

        function renderForms(forms) {
            const tbody = document.getElementById('formsTable');
            
            if (forms.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="no-data">No advising forms found</td></tr>';
                return;
            }

            let html = '';
            forms.forEach(form => {
                const programShort = form.program ? form.program.replace('BS ', '') : 'N/A';
                
                let statusBadge = '';
                switch(form.status) {
                    case 'approved':
                        statusBadge = '<span class="badge approved">APPROVED</span>';
                        break;
                    case 'rejected':
                        statusBadge = '<span class="badge rejected">REJECTED</span>';
                        break;
                    case 'revision_requested':
                        statusBadge = '<span class="badge revision">REVISION NEEDED</span>';
                        break;
                    default:
                        statusBadge = '<span class="badge pending">PENDING</span>';
                }
                
                html += `
                    <tr>
                        <td><strong>#${form.form_id}</strong></td>
                        <td>${form.id_number}</td>
                        <td>${form.student_name}</td>
                        <td>${programShort}</td>
                        <td>${form.academic_year || 'N/A'}</td>
                        <td>${form.term || 'N/A'}</td>
                        <td>${formatDate(form.submitted_at)}</td>
                        <td>${statusBadge}</td>
                        <td><a href="prof_advising_form_view.php?id=${form.form_id}" class="btn-review">Review</a></td>
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
