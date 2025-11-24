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
    <title>My Academic Booklet</title>
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
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 20px; color: #1976D2; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; flex-wrap: wrap; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #1976D2; border-bottom-color: #1976D2; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .gpa-summary { display: flex; gap: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px; margin-bottom: 30px; flex-wrap: wrap; }
        .gpa-item { flex: 1; min-width: 150px; text-align: center; }
        .gpa-item label { font-size: 14px; color: #666; display: block; margin-bottom: 8px; }
        .gpa-item .value { font-size: 38px; font-weight: bold; color: #1976D2; }
        .filter-section { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .filter-section label { font-weight: 600; color: #555; }
        .filter-section select { padding: 8px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; white-space: nowrap; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        .data-table tr.editable { cursor: pointer; }
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.danger { background: #f8d7da; color: #721c24; }
        .badge.warning { background: #fff3cd; color: #856404; }
        .badge.info { background: #d1ecf1; color: #0c5460; }
        .btn { padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.3s; white-space: nowrap; }
        .btn-primary { background: #1976D2; color: white; }
        .btn-primary:hover { background: #1565C0; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .edit-icon { color: #1976D2; cursor: pointer; font-size: 16px; }
        .edit-icon:hover { color: #1565C0; }
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .term-section { margin-bottom: 40px; }
        .term-section h4 { color: #1976D2; font-size: 18px; margin-bottom: 15px; padding: 10px; background: #e3f2fd; border-radius: 5px; }
        .summary-row { background: #f0f7ff; font-weight: 600; }
        .summary-row td { border-top: 2px solid #1976D2; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert.warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .alert.info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .modal-header h3 { color: #1976D2; font-size: 22px; }
        .close-modal { font-size: 28px; cursor: pointer; color: #999; }
        .close-modal:hover { color: #333; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-group textarea { min-height: 100px; resize: vertical; font-family: inherit; }
        .form-group .help-text { font-size: 12px; color: #666; margin-top: 5px; }
        .request-item { padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 5px; margin-bottom: 10px; }
        .request-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
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
                <a href="student_advising_form.php" class="menu-item active">Academic Advising Form</a>
                <a href="student_meeting.php" class="menu-item">Meeting Schedule</a>
                <a href="student_documents.php" class="menu-item">Documents</a>
                <a href="student_concerns.php" class="menu-item">Submit Concern</a>
                <a href="student_profile.php" class="menu-item">My Profile</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>My Academic Booklet</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('overview')">Overview</button>
                <button class="tab-btn" onclick="switchTab('records')">Course Records</button>
                <button class="tab-btn" onclick="switchTab('edit-requests')">Edit Requests</button>
            </div>
            
            <div id="overview" class="tab-content active">
                <div class="content-card">
                    <h3>GPA Summary</h3>
                    <div id="gpaSummary" class="loading">Loading...</div>
                </div>
                <div class="content-card">
                    <h3>Instructions</h3>
                    <div class="alert info">
                        <strong>ℹ How to use:</strong>
                        <ol style="margin: 10px 0 0 20px; line-height: 1.8;">
                            <li>Courses from approved study plans appear here automatically</li>
                            <li>Update grades by clicking the edit icon (✏️)</li>
                            <li>All changes require adviser approval</li>
                            <li>Check "Edit Requests" tab for status</li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <div id="records" class="tab-content">
                <div class="content-card">
                    <h3>Course Records</h3>
                    <div class="filter-section">
                        <label>Filter:</label>
                        <select id="yearFilter" onchange="filterRecords()"><option value="">All Years</option></select>
                        <select id="termFilter" onchange="filterRecords()"><option value="">All Terms</option><option value="1">Term 1</option><option value="2">Term 2</option><option value="3">Term 3</option></select>
                        <button onclick="showAllRecords()" class="btn btn-secondary">Show All</button>
                    </div>
                    <div id="bookletContent" class="loading">Loading...</div>
                </div>
            </div>
            
            <div id="edit-requests" class="tab-content">
                <div class="content-card">
                    <h3>My Edit Requests</h3>
                    <div id="editRequestsContent" class="loading">Loading...</div>
                </div>
            </div>
        </main>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Course Grade</h3>
                <span class="close-modal" onclick="closeEditModal()">&times;</span>
            </div>
            <div id="editAlert"></div>
            <form id="editForm">
                <input type="hidden" id="editRecordId">
                <div class="form-group">
                    <label>Course</label>
                    <input type="text" id="editCourseCode" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label>Current Grade</label>
                    <input type="text" id="editCurrentGrade" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label>New Grade *</label>
                    <input type="number" id="editNewGrade" step="0.01" min="0" max="4" placeholder="e.g., 2.50" required>
                    <div class="help-text">Enter 0.00-4.00 (1.00 = highest)</div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="editStatus"><option value="0">Passed</option><option value="1">Failed</option></select>
                </div>
                <div class="form-group">
                    <label>Reason *</label>
                    <textarea id="editReason" placeholder="Explain why..." required></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Submit</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let allRecords = [];
        
        document.addEventListener('DOMContentLoaded', function() {
            loadGPAData();
            loadBookletRecords();
            document.getElementById('editForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitGradeEdit();
            });
        });

        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tab).classList.add('active');
            event.target.classList.add('active');
            if (tab === 'edit-requests') loadEditRequests();
        }

        function loadGPAData() {
            fetch('student_api.php?action=get_my_gpa')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.terms.length > 0) {
                        const latest = data.terms[0];
                        const totalUnits = data.terms.reduce((sum, t) => sum + (t.total_units_taken || 0), 0);
                        const totalPassed = data.terms.reduce((sum, t) => sum + (t.total_units_passed || 0), 0);
                        const totalFailed = data.terms.reduce((sum, t) => sum + (t.total_units_failed || 0), 0);
                        
                        document.getElementById('gpaSummary').innerHTML = `
                            <div class="gpa-summary">
                                <div class="gpa-item"><label>CGPA</label><div class="value">${latest.cgpa || 'N/A'}</div></div>
                                <div class="gpa-item"><label>Term GPA</label><div class="value">${latest.term_gpa || 'N/A'}</div></div>
                                <div class="gpa-item"><label>Total</label><div class="value" style="font-size:32px">${totalUnits}</div></div>
                                <div class="gpa-item"><label>Passed</label><div class="value" style="font-size:32px;color:#4CAF50">${totalPassed}</div></div>
                                <div class="gpa-item"><label>Failed</label><div class="value" style="font-size:32px;color:#dc3545">${totalFailed}</div></div>
                            </div>
                        `;
                    } else {
                        document.getElementById('gpaSummary').innerHTML = '<div class="empty-state">No GPA data</div>';
                    }
                });
        }

        function loadBookletRecords() {
            fetch('student_api.php?action=get_my_booklet_editable')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allRecords = data.records;
                        const years = [...new Set(allRecords.map(r => r.academic_year))];
                        const yearFilter = document.getElementById('yearFilter');
                        years.forEach(year => {
                            const opt = document.createElement('option');
                            opt.value = year;
                            opt.textContent = year;
                            yearFilter.appendChild(opt);
                        });
                        renderBookletRecords(data.records);
                    } else {
                        document.getElementById('bookletContent').innerHTML = '<div class="empty-state">No records</div>';
                    }
                });
        }

        function renderBookletRecords(records) {
            const container = document.getElementById('bookletContent');
            if (records.length === 0) {
                container.innerHTML = '<div class="empty-state">No courses yet</div>';
                return;
            }
            
            const grouped = {};
            records.forEach(record => {
                const key = `${record.academic_year}_${record.term}`;
                if (!grouped[key]) grouped[key] = { year: record.academic_year, term: record.term, courses: [] };
                grouped[key].courses.push(record);
            });
            
            const sorted = Object.values(grouped).sort((a, b) => {
                if (a.year !== b.year) return b.year.localeCompare(a.year);
                return b.term - a.term;
            });
            
            let html = '';
            sorted.forEach(group => {
                html += `<div class="term-section"><h4>${group.year} - Term ${group.term}</h4>`;
                html += '<div class="table-container"><table class="data-table">';
                html += '<thead><tr><th>Code</th><th>Course Name</th><th>Units</th><th>Grade</th><th>Status</th><th>Approval</th><th>Actions</th></tr></thead><tbody>';
                
                group.courses.forEach(c => {
                    const statusBadge = c.is_failed ? '<span class="badge danger">Failed</span>' : '<span class="badge success">Passed</span>';
                    const approvalBadge = c.approval_status === 'pending' ? '<span class="badge warning">Pending</span>' : '<span class="badge success">Approved</span>';
                    const canEdit = c.grade !== null && parseFloat(c.grade) > 0;
                    
                    html += `<tr>
                        <td><strong>${c.course_code}</strong></td>
                        <td>${c.course_name || 'N/A'}</td>
                        <td>${c.units}</td>
                        <td><strong>${c.grade || '-'}</strong></td>
                        <td>${statusBadge}</td>
                        <td>${approvalBadge}</td>
                        <td>
                            ${canEdit ? `<span class="edit-icon" onclick="openEditModal(${c.id}, '${c.course_code}', '${c.course_name}', '${c.grade}', ${c.is_failed})" title="Edit Grade">✏️</span>` : ''}
                        </td>
                    </tr>`;
                });
                
                html += '</tbody></table></div></div>';
            });
            container.innerHTML = html;
        }

        function filterRecords() {
            const year = document.getElementById('yearFilter').value;
            const term = document.getElementById('termFilter').value;
            let filtered = allRecords;
            if (year) filtered = filtered.filter(r => r.academic_year === year);
            if (term) filtered = filtered.filter(r => r.term == term);
            renderBookletRecords(filtered);
        }

        function showAllRecords() {
            document.getElementById('yearFilter').value = '';
            document.getElementById('termFilter').value = '';
            renderBookletRecords(allRecords);
        }

        function openEditModal(id, code, name, grade, failed) {
            document.getElementById('editRecordId').value = id;
            document.getElementById('editCourseCode').value = code + ' - ' + name;
            document.getElementById('editCurrentGrade').value = grade || 'Not set';
            document.getElementById('editNewGrade').value = '';
            document.getElementById('editStatus').value = failed ? '1' : '0';
            document.getElementById('editReason').value = '';
            document.getElementById('editAlert').innerHTML = '';
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function submitGradeEdit() {
            const formData = new FormData();
            formData.append('action', 'submit_grade_edit');
            formData.append('record_id', document.getElementById('editRecordId').value);
            formData.append('new_grade', document.getElementById('editNewGrade').value);
            formData.append('is_failed', document.getElementById('editStatus').value);
            formData.append('reason', document.getElementById('editReason').value);
            
            fetch('student_api.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editAlert').innerHTML = '<div class="alert success">Edit request submitted! Waiting for adviser approval.</div>';
                        setTimeout(() => {
                            closeEditModal();
                            loadBookletRecords();
                            switchTab('edit-requests');
                        }, 2000);
                    } else {
                        document.getElementById('editAlert').innerHTML = '<div class="alert warning">Error: ' + data.message + '</div>';
                    }
                });
        }

        function loadEditRequests() {
            fetch('student_api.php?action=get_my_edit_requests')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('editRequestsContent');
                    if (data.success && data.requests.length > 0) {
                        let html = '';
                        data.requests.forEach(req => {
                            const statusBadge = req.status === 'approved' ? '<span class="badge success">Approved</span>' : 
                                              req.status === 'rejected' ? '<span class="badge danger">Rejected</span>' : 
                                              '<span class="badge warning">Pending</span>';
                            
                            html += `<div class="request-item">
                                <div class="request-header">
                                    <strong>${req.course_code} - ${req.field_name}</strong>
                                    ${statusBadge}
                                </div>
                                <div><strong>Old:</strong> ${req.old_value} → <strong>New:</strong> ${req.new_value}</div>
                                <div><strong>Reason:</strong> ${req.reason}</div>
                                ${req.review_notes ? '<div><strong>Adviser Notes:</strong> ' + req.review_notes + '</div>' : ''}
                                <div style="font-size:12px;color:#666;margin-top:5px">${new Date(req.requested_at).toLocaleString()}</div>
                            </div>`;
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<div class="empty-state">No edit requests yet</div>';
                    }
                });
        }
    </script>
</body>
</html>