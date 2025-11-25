<?php
require_once 'auth_check.php';
requireStudent();

require_once 'config.php';

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
        .sidebar { width: 260px; background: #00A36C; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #008558; }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.9; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #7FE5B8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #00A36C; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        /* Filter Section */
        .filter-section { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .filter-section label { font-weight: 600; color: #555; }
        .filter-section select { padding: 8px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        
        /* Booklet Specific Styles (Matching Image) */
        .booklet-page {
            background: white;
            border: 2px solid #000; /* Black border like the image */
            margin-bottom: 30px;
            padding: 0;
            page-break-inside: avoid;
        }
        
        .booklet-header {
            text-align: center;
            padding: 15px;
            border-bottom: 2px solid #000;
            font-family: "Times New Roman", serif;
        }
        
        .booklet-header h4 {
            font-size: 20px;
            font-style: italic;
            margin: 0;
            font-weight: bold;
            color: #000;
        }
        
        .academic-term-label {
            text-align: left;
            padding: 10px 15px;
            font-weight: bold;
            font-family: "Times New Roman", serif;
            border-bottom: 1px solid #000;
        }

        .booklet-table {
            width: 100%;
            border-collapse: collapse;
            font-family: "Times New Roman", serif;
        }
        
        .booklet-table th {
            border-bottom: 1px solid #000;
            border-right: 1px solid #000;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            background: white; /* No grey background in image */
            color: #000;
        }
        
        .booklet-table th:last-child {
            border-right: none;
        }
        
        .booklet-table td {
            border-bottom: 1px solid #ccc; /* Lighter inner lines */
            border-right: 1px solid #000; /* Solid column dividers */
            padding: 8px;
            color: #000;
            vertical-align: middle;
        }
        
        .booklet-table td:last-child {
            border-right: none;
            text-align: center;
        }
        
        .booklet-table tr:last-child td {
            border-bottom: 2px solid #000;
        }

        /* Booklet Footer (Summary) */
        .booklet-footer {
            padding: 15px 20px;
            font-family: "Times New Roman", serif;
            font-size: 15px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .summary-item {
            display: flex;
            align-items: center;
            width: 48%;
        }
        
        .summary-item label {
            font-weight: bold;
            margin-right: 10px;
            white-space: nowrap;
        }
        
        .summary-line {
            border-bottom: 1px solid #000;
            flex-grow: 1;
            text-align: center;
            font-weight: bold;
            min-height: 20px;
        }

        /* Buttons & Modals */
        .btn { padding: 6px 12px; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #00A36C; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .edit-icon { color: #00A36C; cursor: pointer; font-size: 16px; float: right; margin-left: 5px; }
        .edit-icon:hover { color: #008558; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
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
                <a href="student_booklet.php" class="menu-item active">My Booklet</a>
                <a href="student_advising_form.php" class="menu-item">Academic Advising Form</a>
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
            
            <div class="filter-section">
                <label>Filter View:</label>
                <select id="yearFilter" onchange="filterRecords()"><option value="">All Academic Years</option></select>
                <select id="termFilter" onchange="filterRecords()"><option value="">All Terms</option><option value="1">Term 1</option><option value="2">Term 2</option><option value="3">Term 3</option></select>
                <button onclick="showAllRecords()" class="btn btn-secondary">Reset Filters</button>
            </div>

            <div id="bookletContent" class="loading">Loading academic records...</div>
        </main>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Edit Grade</h3>
            <div id="editAlert"></div>
            <form id="editForm">
                <input type="hidden" id="editRecordId">
                <div class="form-group">
                    <label>Course</label>
                    <input type="text" id="editCourseCode" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label>New Grade</label>
                    <input type="number" id="editNewGrade" step="0.001" min="0" max="4" placeholder="e.g., 3.500" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="editStatus"><option value="0">Passed</option><option value="1">Failed</option></select>
                </div>
                <div class="form-group">
                    <label>Reason for Change</label>
                    <textarea id="editReason" placeholder="Why are you editing this record?" required></textarea>
                </div>
                <div style="text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let allRecords = [];
        
        document.addEventListener('DOMContentLoaded', function() {
            loadBookletRecords();
            document.getElementById('editForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitGradeEdit();
            });
        });

        function loadBookletRecords() {
            fetch('student_api.php?action=get_my_booklet_editable')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allRecords = data.records;
                        populateFilters(data.records);
                        renderBookletRecords(data.records);
                    } else {
                        document.getElementById('bookletContent').innerHTML = '<div class="empty-state">No records found.</div>';
                    }
                })
                .catch(err => {
                    document.getElementById('bookletContent').innerHTML = '<div class="empty-state">Error loading records.</div>';
                });
        }

        function populateFilters(records) {
            const years = [...new Set(records.map(r => r.academic_year))].sort();
            const yearFilter = document.getElementById('yearFilter');
            // Keep first option
            yearFilter.innerHTML = '<option value="">All Academic Years</option>';
            years.forEach(year => {
                const opt = document.createElement('option');
                opt.value = year;
                opt.textContent = year;
                yearFilter.appendChild(opt);
            });
        }

        function renderBookletRecords(records) {
            const container = document.getElementById('bookletContent');
            if (records.length === 0) {
                container.innerHTML = '<div class="empty-state">No records match your filter.</div>';
                return;
            }
            
            // Group by Year and Term
            const grouped = {};
            records.forEach(record => {
                const key = `${record.academic_year}_${record.term}`;
                if (!grouped[key]) grouped[key] = { 
                    year: record.academic_year, 
                    term: record.term, 
                    courses: [],
                    gpaData: {} // Placeholder if API provided summaries
                };
                grouped[key].courses.push(record);
            });
            
            // Sort groups (Newest first usually, but booklets often read oldest to newest. Let's do Academic Year Ascending)
            const sortedKeys = Object.keys(grouped).sort();
            
            let html = '';
            sortedKeys.forEach(key => {
                const group = grouped[key];
                
                // Calculate Term Stats Frontend (Simulation since we don't have backend summary data in this specific API call)
                let termUnits = 0;
                let termPoints = 0;
                let termFailures = 0;
                
                group.courses.forEach(c => {
                    let units = parseFloat(c.units || 0);
                    if (c.grade !== null && c.grade !== '') {
                        let grade = parseFloat(c.grade);
                        if (!isNaN(grade)) {
                            termPoints += grade * units;
                            termUnits += units;
                        }
                    }
                    if (c.is_failed == 1) {
                        termFailures += units;
                    }
                });
                
                let termGPA = termUnits > 0 ? (termPoints / termUnits).toFixed(3) : "0.000";
                
                // Simple Honors Logic (Example)
                let honors = "None";
                if (termUnits >= 12 && termGPA >= 3.4 && termFailures === 0) honors = "First Dean's Lister";
                else if (termUnits >= 12 && termGPA >= 3.0 && termFailures === 0) honors = "Second Dean's Lister";

                html += `
                <div class="booklet-page">
                    <div class="booklet-header">
                        <h4>${getTermName(group.term)}</h4>
                    </div>
                    <div class="academic-term-label">
                        Academic Year/Term: <span style="font-weight:normal; margin-left:10px;">${group.year} / Term ${group.term}</span>
                    </div>
                    
                    <table class="booklet-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Course Code</th>
                                <th style="width: 40%;">Course Name</th>
                                <th style="width: 10%;">Units</th>
                                <th style="width: 10%;">Grade</th>
                                <th style="width: 20%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                group.courses.forEach(c => {
                    const canEdit = true; // Assuming student can request edits
                    html += `
                        <tr>
                            <td style="text-align:center;">${c.course_code}</td>
                            <td style="padding-left:15px;">${c.course_name || ''}</td>
                            <td style="text-align:center;">${c.units}</td>
                            <td style="text-align:center; font-weight:bold;">${c.grade !== null ? c.grade : ''}</td>
                            <td style="text-align:center;">
                                <span class="edit-icon" onclick="openEditModal(${c.id}, '${c.course_code}', '${c.grade}', ${c.is_failed})" title="Edit">✏️</span>
                            </td>
                        </tr>
                    `;
                });
                
                // Pad with empty rows if less than 8 courses to maintain "Booklet" look
                for(let i = group.courses.length; i < 8; i++) {
                    html += `<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>`;
                }

                html += `
                        </tbody>
                    </table>
                    
                    <div class="booklet-footer">
                        <div class="summary-row">
                            <div class="summary-item">
                                <label>Term GPA:</label>
                                <div class="summary-line">${termGPA}</div>
                            </div>
                            <div class="summary-item">
                                <label>CGPA:</label>
                                <div class="summary-line">--</div> <!-- Needs backend calc -->
                            </div>
                        </div>
                        
                        <div class="summary-row">
                            <div class="summary-item">
                                <label>Accumulated Failure (Units):</label>
                                <div class="summary-line">${termFailures > 0 ? termFailures : '--'}</div>
                            </div>
                        </div>
                        
                        <div class="summary-row">
                            <div class="summary-item" style="width: 100%;">
                                <label>Trimestral Honors:</label>
                                <div class="summary-line">${honors}</div>
                            </div>
                        </div>
                    </div>
                </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function getTermName(term) {
            if (term == 1) return "First Term";
            if (term == 2) return "Second Term";
            if (term == 3) return "Third Term";
            return "Term " + term;
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

        function openEditModal(id, code, grade, failed) {
            document.getElementById('editRecordId').value = id;
            document.getElementById('editCourseCode').value = code;
            document.getElementById('editNewGrade').value = grade === 'null' ? '' : grade;
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
                        document.getElementById('editAlert').innerHTML = '<div class="alert success" style="color: green; background: #e8f5e9; padding: 10px; border-radius: 5px;">Request submitted successfully!</div>';
                        setTimeout(() => {
                            closeEditModal();
                            // Ideally reload data here
                        }, 1500);
                    } else {
                        document.getElementById('editAlert').innerHTML = '<div class="alert warning" style="color: red; background: #ffebee; padding: 10px; border-radius: 5px;">Error: ' + data.message + '</div>';
                    }
                });
        }
    </script>
</body>
</html>