<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$admin_id = $_SESSION['user_id'];

// Get admin info
$stmt = $conn->prepare("SELECT username FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_username = $stmt->get_result()->fetch_assoc()['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Advising Forms - Admin Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: #6a1b9a; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #4a148c; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.8; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.08); border-left-color: #BA68C8; }
        
        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #6a1b9a; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 14px; color: #666; margin-bottom: 10px; text-transform: uppercase; }
        .stat-card .number { font-size: 32px; font-weight: 700; color: #6a1b9a; }
        .stat-card.highlight-1 .number { color: #BA68C8; }
        .stat-card.highlight-2 .number { color: #ffb74d; }
        .stat-card.highlight-3 .number { color: #66bb6a; }
        
        /* Filters */
        .filter-bar { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .filter-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; color: #555; }
        .filter-group input, .filter-group select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .filter-btn { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; margin-top: 20px; }
        .export-btn { padding: 10px 20px; background: #27ae60; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; margin-left: 10px; }
        
        /* Content Card */
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .content-card h2 { font-size: 22px; color: #6a1b9a; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        /* Table */
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; white-space: nowrap; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        
        /* Badges */
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.cleared { background: #d4edda; color: #155724; }
        .badge.reviewed { background: #d1ecf1; color: #0c5460; }
        .badge.high-risk { background: #f8d7da; color: #721c24; }
        
        /* Action Buttons */
        .action-btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-block; margin-right: 5px; }
        .action-btn.view { background: #3498db; color: white; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .loading { text-align: center; padding: 40px; color: #666; }
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
                <a href="admin_advising_forms.php" class="menu-item active">Advising Forms</a>
                <a href="admin_advisingassignment.php" class="menu-item">Advising Assignments</a>
                <a href="admin_reports.php" class="menu-item">System Reports</a>
                <a href="admin_bulk_operations.php" class="menu-item">Bulk Ops & Uploads</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Academic Advising Forms</h1>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Submissions</h3>
                    <div class="number" id="totalForms">-</div>
                </div>
                <div class="stat-card highlight-2">
                    <h3>Pending Review</h3>
                    <div class="number" id="pendingForms">-</div>
                </div>
                <div class="stat-card highlight-3">
                    <h3>Cleared Forms</h3>
                    <div class="number" id="clearedForms">-</div>
                </div>
                <div class="stat-card highlight-1">
                    <h3>High Risk Students</h3>
                    <div class="number" id="highRiskCount">-</div>
                </div>
                <div class="stat-card">
                    <h3>Avg Failed Units</h3>
                    <div class="number" id="avgFailedUnits">-</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-bar">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" id="searchTerm" placeholder="Student name or ID...">
                    </div>
                    <div class="filter-group">
                        <label>Program</label>
                        <select id="filterProgram">
                            <option value="">All Programs</option>
                            <option value="BS Computer Engineering">BS Computer Engineering</option>
                            <option value="BS Electronics and Communications Engineering">BS ECE</option>
                            <option value="BS Electrical Engineering">BS Electrical Engineering</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select id="filterStatus">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="cleared">Cleared</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Risk Level</label>
                        <select id="filterRisk">
                            <option value="">All Risk Levels</option>
                            <option value="high">High Risk (>15 failed units)</option>
                            <option value="medium">Medium Risk (6-15 failed units)</option>
                            <option value="low">Low Risk (<6 failed units)</option>
                        </select>
                    </div>
                </div>
                <button class="filter-btn" onclick="applyFilters()">Apply Filters</button>
                <button class="export-btn" onclick="exportToCSV()">Export to CSV</button>
            </div>
            
            <!-- Forms List -->
            <div class="content-card">
                <h2>All Academic Advising Forms</h2>
                <div id="formsContent" class="loading">Loading advising forms...</div>
            </div>
        </main>
    </div>

    <script>
        let allForms = [];
        
        // Load forms on page load
        window.addEventListener('load', function() {
            loadAdvisingForms();
        });
        
        // Load advising forms
        async function loadAdvisingForms() {
            try {
                const response = await fetch('admin_advising_forms_api.php?action=get_all_advising_forms');
                const data = await response.json();
                
                if (data.success) {
                    allForms = data.forms;
                    updateStatistics(data.forms);
                    renderForms(data.forms);
                } else {
                    document.getElementById('formsContent').innerHTML = '<div class="empty-state">No advising forms found</div>';
                }
            } catch (error) {
                document.getElementById('formsContent').innerHTML = '<div class="empty-state">Error loading forms</div>';
            }
        }
        
        // Update statistics
        function updateStatistics(forms) {
            document.getElementById('totalForms').textContent = forms.length;
            document.getElementById('pendingForms').textContent = forms.filter(f => !f.cleared && !f.adviser_feedback).length;
            document.getElementById('clearedForms').textContent = forms.filter(f => f.cleared).length;
            
            const highRisk = forms.filter(f => f.overall_failed_units >= 15).length;
            document.getElementById('highRiskCount').textContent = highRisk;
            
            const avgFailed = forms.length > 0 
                ? Math.round(forms.reduce((sum, f) => sum + parseInt(f.overall_failed_units), 0) / forms.length)
                : 0;
            document.getElementById('avgFailedUnits').textContent = avgFailed;
        }
        
        // Render forms table
        function renderForms(forms) {
            const container = document.getElementById('formsContent');
            
            if (forms.length === 0) {
                container.innerHTML = '<div class="empty-state">No advising forms found</div>';
                return;
            }
            
            let html = '<div class="table-container"><table class="data-table">';
            html += '<thead><tr><th>Student</th><th>ID</th><th>Program</th><th>Adviser</th><th>Term</th><th>Failed Units</th><th>GPA</th><th>Status</th><th>Submitted</th></tr></thead><tbody>';
            
            forms.forEach(form => {
                let statusBadge = '<span class="badge pending">Pending</span>';
                if (form.cleared) {
                    statusBadge = '<span class="badge cleared">Cleared</span>';
                } else if (form.adviser_feedback) {
                    statusBadge = '<span class="badge reviewed">Reviewed</span>';
                }
                
                let riskBadge = '';
                if (form.overall_failed_units >= 15) {
                    riskBadge = '<br><span class="badge high-risk">High Risk</span>';
                }
                
                html += `
                    <tr>
                        <td><strong>${form.student_name}</strong></td>
                        <td>${form.student_id_number}</td>
                        <td>${form.program}</td>
                        <td>${form.adviser_name || 'Not Assigned'}</td>
                        <td>${form.academic_year}<br>${form.term}</td>
                        <td>
                            Current: ${form.current_year_failed_units}<br>
                            Total: ${form.overall_failed_units}
                            ${riskBadge}
                        </td>
                        <td>
                            Term: ${form.previous_term_gpa || 'N/A'}<br>
                            CGPA: ${form.cumulative_gpa || 'N/A'}
                        </td>
                        <td>${statusBadge}</td>
                        <td>${new Date(form.submission_date).toLocaleDateString()}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }
        
        // Apply filters
        function applyFilters() {
            const searchTerm = document.getElementById('searchTerm').value.toLowerCase();
            const programFilter = document.getElementById('filterProgram').value;
            const statusFilter = document.getElementById('filterStatus').value;
            const riskFilter = document.getElementById('filterRisk').value;
            
            let filtered = allForms.filter(form => {
                const matchesSearch = !searchTerm || 
                    form.student_name.toLowerCase().includes(searchTerm) ||
                    form.student_id_number.toString().includes(searchTerm);
                
                const matchesProgram = !programFilter || form.program === programFilter;
                
                let matchesStatus = true;
                if (statusFilter === 'pending') {
                    matchesStatus = !form.cleared && !form.adviser_feedback;
                } else if (statusFilter === 'reviewed') {
                    matchesStatus = form.adviser_feedback && !form.cleared;
                } else if (statusFilter === 'cleared') {
                    matchesStatus = form.cleared;
                }
                
                let matchesRisk = true;
                if (riskFilter === 'high') {
                    matchesRisk = form.overall_failed_units >= 15;
                } else if (riskFilter === 'medium') {
                    matchesRisk = form.overall_failed_units >= 6 && form.overall_failed_units < 15;
                } else if (riskFilter === 'low') {
                    matchesRisk = form.overall_failed_units < 6;
                }
                
                return matchesSearch && matchesProgram && matchesStatus && matchesRisk;
            });
            
            renderForms(filtered);
        }
        
        // Export to CSV
        function exportToCSV() {
            if (allForms.length === 0) {
                alert('No data to export');
                return;
            }
            
            let csv = 'Student Name,ID Number,Program,Adviser,Academic Year,Term,Current Year Failed Units,Overall Failed Units,Previous Term GPA,Cumulative GPA,Status,Submission Date\n';
            
            allForms.forEach(form => {
                let status = 'Pending';
                if (form.cleared) status = 'Cleared';
                else if (form.adviser_feedback) status = 'Reviewed';
                
                csv += `"${form.student_name}","${form.student_id_number}","${form.program}","${form.adviser_name || 'Not Assigned'}","${form.academic_year}","${form.term}",${form.current_year_failed_units},${form.overall_failed_units},"${form.previous_term_gpa || 'N/A'}","${form.cumulative_gpa || 'N/A'}","${status}","${new Date(form.submission_date).toLocaleDateString()}"\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `advising_forms_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
