<?php
require_once 'auth_check.php';
requireProfessor();

require_once 'config.php';

$professor_id = $_SESSION['user_id'];

// Get professor name
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name, last_name FROM professors WHERE id = ?");
$stmt->bind_param("i", $professor_id);
$stmt->execute();
$professor = $stmt->get_result()->fetch_assoc();
$professor_name = $professor['full_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Advising - Professor Portal</title>
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
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h2 { font-size: 22px; color: #6a1b9a; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .content-card h3 { font-size: 18px; color: #6a1b9a; margin-bottom: 15px; }
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .form-group { flex: 1; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-group input:focus { outline: none; border-color: #6a1b9a; }
        .btn-primary { padding: 10px 25px; background: #6a1b9a; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 500; }
        .btn-primary:hover { background: #4a148c; }
        .deadline-info { margin-top: 20px; padding-top: 20px; border-top: 1px solid #f0f0f0; color: #666; font-size: 14px; }
        .controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 20px; }
        .filters { display: flex; gap: 10px; }
        .filter-btn { padding: 8px 16px; border: 1px solid #ddd; background: white; color: #555; border-radius: 5px; cursor: pointer; font-size: 13px; transition: all 0.3s; }
        .filter-btn:hover { background: #f8f9fa; }
        .filter-btn.active { background: #6a1b9a; color: white; border-color: #6a1b9a; }
        .search-box { flex: 0 0 300px; }
        .search-box input { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .search-box input:focus { outline: none; border-color: #6a1b9a; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.danger { background: #f8d7da; color: #721c24; }
        .action-buttons { display: flex; gap: 8px; }
        .btn-view { padding: 6px 12px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-view:hover { background: #1976D2; }
        .btn-clear { padding: 6px 12px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .btn-clear:hover { background: #388E3C; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .no-data { text-align: center; padding: 40px; color: #999; font-style: italic; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 3% auto; padding: 30px; border-radius: 10px; width: 80%; max-width: 900px; max-height: 85vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .modal-header h2 { color: #6a1b9a; font-size: 22px; }
        .close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 1; }
        .close:hover { color: #000; }
        .submission-detail { margin-bottom: 25px; }
        .submission-detail h3 { color: #333; margin-bottom: 15px; font-size: 16px; }
        .subject-list { background: #f8f9fa; padding: 15px; border-radius: 5px; }
        .subject-item { margin-bottom: 10px; padding: 10px; background: white; border-radius: 5px; border-left: 3px solid #6a1b9a; }
        .student-info-box { margin-bottom: 20px; padding: 15px; background: #e3f2fd; border-radius: 5px; }
        .student-info-box p { margin-bottom: 8px; font-size: 14px; }
        .student-info-box p:last-child { margin-bottom: 0; }
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
                <a href="prof_acadadvising.php" class="menu-item active">Academic Advising</a>
                <a href="prof_concerns.php" class="menu-item">Student Concerns</a>
                <a href="prof_reports.php" class="menu-item">Reports</a>
                <a href="prof_email.php" class="menu-item">Email System</a>
                <a href="prof_schedule.php" class="menu-item">Schedule</a>
                <a href="prof_grade_approvals.php" class="menu-item">Grade Approvals</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <div>
                    <h1>Academic Advising Management</h1>
                    <p style="color: #666; font-size: 14px; margin-top: 5px;">Welcome back, Prof. <?php echo htmlspecialchars($professor['last_name']); ?>!</p>
                </div>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="content-card">
                <h3>Set Advising Deadline</h3>
                <p style="color: #666; margin-bottom: 20px; font-size: 14px;">Set the deadline for students to submit their academic advising forms</p>
                
                <form id="deadlineForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Term</label>
                            <input type="text" name="term" placeholder="e.g., AY 2024-2025 Term 2" required>
                        </div>
                        <div class="form-group">
                            <label>Deadline Date</label>
                            <input type="date" name="deadline_date" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Set Deadline</button>
                </form>
                
                <div class="deadline-info">
                    <strong>Current Deadline:</strong> <span id="deadline-display">Loading...</span>
                </div>
            </div>
            
            <div class="content-card">
                <h2>Assigned Students</h2>
                
                <div class="controls">
                    <div class="filters">
                        <button type="button" class="filter-btn active">All (0)</button>
                        <button type="button" class="filter-btn">Completed (0)</button>
                        <button type="button" class="filter-btn">Pending (0)</button>
                        <button type="button" class="filter-btn">Not Submitted (0)</button>
                    </div>
                    <div class="search-box">
                        <input type="text" placeholder="Search by ID or name...">
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Course</th>
                                <th>Submission Date</th>
                                <th>Meeting</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" class="loading">Loading students...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <div id="submissionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Study Plan Submission</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <div class="student-info-section"></div>
            
            <div class="submission-detail">
                <h3>Current Subjects</h3>
                <div class="subject-list">
                    <p style="color: #999;">Loading...</p>
                </div>
            </div>
            
            <div class="submission-detail">
                <h3>Planned Subjects</h3>
                <div class="subject-list">
                    <p style="color: #999;">Loading...</p>
                </div>
            </div>
            
            <div style="margin-top: 25px; display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn-clear" id="modalClearBtn">Clear Student for Advising</button>
                <button style="padding: 10px 20px; background: #999; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        loadStudents();
        loadCurrentDeadline();
    });

    let currentFilter = 'all';
    let currentSearch = '';

    document.getElementById('deadlineForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const term = this.querySelector('input[name="term"]').value;
        const deadline_date = this.querySelector('input[name="deadline_date"]').value;
        if (!term || !deadline_date) { alert('Please fill in all fields'); return; }
        const formData = new FormData();
        formData.append('action', 'set_deadline');
        formData.append('term', term);
        formData.append('deadline_date', deadline_date);
        fetch('prof_api.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                document.getElementById('deadline-display').textContent = data.deadline + ' (' + data.term + ')';
                document.getElementById('deadlineForm').reset();
            } else { alert('Error: ' + data.message); }
        })
        .catch(error => { console.error('Error:', error); alert('Error setting deadline'); });
    });

    function loadCurrentDeadline() {
        fetch('prof_api.php?action=get_current_deadline')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.deadline) {
                document.getElementById('deadline-display').textContent = data.deadline.deadline_date + ' (' + data.deadline.term + ')';
            } else {
                document.getElementById('deadline-display').textContent = 'No deadline set';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('deadline-display').textContent = 'Error loading';
        });
    }

    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const btnText = this.textContent.toLowerCase();
            if (btnText.includes('all')) currentFilter = 'all';
            else if (btnText.includes('completed')) currentFilter = 'completed';
            else if (btnText.includes('pending')) currentFilter = 'pending';
            else if (btnText.includes('not submitted')) currentFilter = 'not-submitted';
            loadStudents();
        });
    });

    document.querySelector('.search-box input').addEventListener('input', function() {
        currentSearch = this.value;
        loadStudents();
    });

    function loadStudents() {
        fetch(`prof_api.php?action=get_students&filter=${currentFilter}&search=${encodeURIComponent(currentSearch)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) { renderStudentsTable(data.students); updateFilterCounts(data.counts); }
            else { console.error('Error:', data.message); alert('Error loading students: ' + data.message); }
        })
        .catch(error => { console.error('Error:', error); alert('Error loading students'); });
    }

    function renderStudentsTable(students) {
        const tbody = document.querySelector('.data-table tbody');
        if (students.length === 0) { tbody.innerHTML = '<tr><td colspan="7" class="no-data">No students found</td></tr>'; return; }
        tbody.innerHTML = students.map(student => `
            <tr>
                <td>${student.id_number}</td>
                <td>${student.name}</td>
                <td>${student.program}</td>
                <td>${student.submission_date ? new Date(student.submission_date).toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric'}) : '-'}</td>
                <td>${student.wants_meeting ? 'Requested' : (student.submission_date ? 'Waived' : '-')}</td>
                <td><span class="badge ${getStatusClass(student)}">${getStatusText(student)}</span></td>
                <td>
                    <div class="action-buttons">
                        ${student.plan_id ? `
                            <button class="btn-view" onclick="viewSubmission(${student.id}, ${student.plan_id})">View</button>
                            ${!student.advising_cleared ? `<button class="btn-clear" onclick="clearStudent(${student.id})">Clear</button>` : ''}
                        ` : `<span style="font-size: 13px; color: #999;">No submission yet</span>`}
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function updateFilterCounts(counts) {
        const buttons = document.querySelectorAll('.filter-btn');
        if (buttons.length >= 4) {
            buttons[0].textContent = `All (${counts.total})`;
            buttons[1].textContent = `Completed (${counts.completed})`;
            buttons[2].textContent = `Pending (${counts.pending})`;
            buttons[3].textContent = `Not Submitted (${counts.not_submitted})`;
        }
    }

    function getStatusClass(student) {
        if (student.advising_cleared) return 'success';
        if (student.plan_id && !student.plan_cleared) return 'pending';
        return 'danger';
    }

    function getStatusText(student) {
        if (student.advising_cleared) return 'Cleared';
        if (student.plan_id && !student.plan_cleared) return 'Pending Review';
        return 'Not Submitted';
    }

    function viewSubmission(studentId, planId) {
        fetch(`prof_api.php?action=get_submission_details&student_id=${studentId}`)
        .then(response => response.json())
        .then(data => { if (data.success) { displaySubmissionModal(data); } else { alert('Error: ' + data.message); } })
        .catch(error => { console.error('Error:', error); alert('Error loading submission'); });
    }

    function displaySubmissionModal(data) {
        const modal = document.getElementById('submissionModal');
        const plan = data.plan;
        const infoSection = modal.querySelector('.student-info-section');
        infoSection.innerHTML = `
            <div class="student-info-box">
                <p><strong>Student:</strong> ${plan.student_name} (${plan.id_number})</p>
                <p><strong>Program:</strong> ${plan.program}</p>
                <p><strong>Academic Year:</strong> ${plan.academic_year}</p>
                <p><strong>Term:</strong> ${plan.term}</p>
                <p><strong>Submission Date:</strong> ${new Date(plan.submission_date).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}</p>
                <p><strong>Meeting Requested:</strong> ${plan.wants_meeting ? 'Yes' : 'No'}</p>
            </div>
        `;
        const currentSubjectsList = modal.querySelector('.submission-detail:nth-of-type(1) .subject-list');
        currentSubjectsList.innerHTML = data.current_subjects.length > 0 ? 
            data.current_subjects.map(s => `
                <div class="subject-item">
                    <strong>${s.subject_code}</strong> - ${s.units} units<br>
                    <span style="font-size: 13px; color: #666;">${s.subject_name}</span>
                    ${s.prerequisites ? `<br><span style="font-size: 12px; color: #999;">Prerequisites: ${s.prerequisites}</span>` : ''}
                </div>
            `).join('') : '<p style="color: #999;">No current subjects listed</p>';
        const plannedSubjectsList = modal.querySelector('.submission-detail:nth-of-type(2) .subject-list');
        plannedSubjectsList.innerHTML = data.planned_subjects.length > 0 ?
            data.planned_subjects.map(s => `
                <div class="subject-item">
                    <strong>${s.subject_code}</strong> - ${s.units} units<br>
                    <span style="font-size: 13px; color: #666;">${s.subject_name}</span>
                    ${s.prerequisites ? `<br><span style="font-size: 12px; color: #999;">Prerequisites: ${s.prerequisites}</span>` : ''}
                </div>
            `).join('') : '<p style="color: #999;">No planned subjects listed</p>';
        document.getElementById('modalClearBtn').onclick = function() { clearStudentFromModal(plan.student_id); };
        modal.style.display = 'block';
    }

    function clearStudentFromModal(studentId) {
        if (confirm('Clear this student for academic advising?')) clearStudent(studentId);
    }

    function clearStudent(studentId) {
        const formData = new FormData();
        formData.append('action', 'clear_student');
        formData.append('student_id', studentId);
        fetch('prof_api.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => { if (data.success) { alert(data.message); closeModal(); loadStudents(); } else { alert('Error: ' + data.message); } })
        .catch(error => { console.error('Error:', error); alert('Error clearing student'); });
    }

    function closeModal() {
        document.getElementById('submissionModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('submissionModal');
        if (event.target === modal) modal.style.display = 'none';
    }
    </script>
</body>
</html>
