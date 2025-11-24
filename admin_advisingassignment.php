<?php
require_once 'auth_check.php';
requireAdmin();

require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advising Assignments - Admin Portal</title>
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
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        .progress-bar { width: 100%; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: #4CAF50; border-radius: 4px; }
        .btn-manage { padding: 6px 12px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-manage:hover { background: #1976D2; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; }
        .modal-content { background-color: white; margin: 50px auto; padding: 0; border-radius: 10px; width: 90%; max-width: 1000px; max-height: 85vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 25px 30px; border-bottom: 2px solid #f0f0f0; position: sticky; top: 0; background: white; z-index: 10; }
        .modal-header h3 { color: #6a1b9a; font-size: 22px; }
        .close { font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; }
        .close:hover { color: #000; }
        .modal-body { padding: 30px; }
        .assignment-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .assignment-column { border: 2px solid #e0e0e0; border-radius: 8px; padding: 20px; }
        .assignment-column h4 { color: #6a1b9a; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .student-list { max-height: 400px; overflow-y: auto; }
        .student-item { padding: 12px; margin: 5px 0; background: #f8f9fa; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: all 0.3s; border: 2px solid transparent; }
        .student-item:hover { background: #e3f2fd; border-color: #90caf9; }
        .student-item.selected { background: #1976D2; border-color: #1565C0; color: white; box-shadow: 0 2px 8px rgba(25, 118, 210, 0.4); }
        .student-item.selected .student-name { color: white; }
        .student-item.selected .student-details { color: rgba(255, 255, 255, 0.9); }
        .student-info { flex: 1; }
        .student-name { font-weight: 600; color: #333; }
        .student-details { font-size: 12px; color: #666; margin-top: 3px; }
        .btn-assign { padding: 8px 16px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; margin-top: 15px; }
        .btn-assign:hover { background: #45a049; }
        .btn-unassign { padding: 6px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-unassign:hover { background: #c82333; }
        .filter-section { display: flex; gap: 15px; margin-bottom: 20px; }
        .search-box { flex: 1; }
        .search-box input { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; min-width: 150px; }
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge.warning { background: #fff3cd; color: #856404; }
        .badge.danger { background: #f8d7da; color: #721c24; }
        .info-box { background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2196F3; }
        .info-box p { margin: 5px 0; font-size: 14px; color: #555; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .no-data { text-align: center; padding: 40px; color: #999; font-style: italic; }
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
                <a href="admin_advising_forms.php" class="menu-item">Advising Forms</a>
                <a href="admin_advisingassignment.php" class="menu-item active">Advising Assignments</a>
                <a href="admin_reports.php" class="menu-item">System Reports</a>
                <a href="admin_bulk_operations.php" class="menu-item">Bulk Ops & Uploads</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Academic Advising Assignments</h1>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="content-card">
                <h2>Professor-Student Assignments</h2>
                <p style="color: #666; margin-bottom: 20px; font-size: 14px;">Manage which students are assigned to which professors for academic advising.</p>
                
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
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="professorsTable">
                            <tr><td colspan="8" class="loading">Loading professors...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Assignment Modal -->
    <div id="assignmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Manage Advisees</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="info-box">
                    <p><strong>Professor:</strong> <span id="professorName"></span></p>
                    <p><strong>Current Advisees:</strong> <span id="adviseeCount">0</span> students</p>
                </div>

                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="unassignedSearch" placeholder="🔍 Search unassigned students..." onkeyup="filterUnassigned()">
                    </div>
                    <select id="programFilter" class="filter-select" onchange="filterUnassigned()">
                        <option value="">All Programs</option>
                        <option value="BS Computer Engineering">BSCpE</option>
                        <option value="BS Electronics and Communications Engineering">BSECE</option>
                        <option value="BS Electrical Engineering">BSEE</option>
                    </select>
                </div>
                
                <div class="assignment-grid">
                    <div class="assignment-column">
                        <h4>Unassigned Students <span id="selectedCount" style="color: #2196F3; font-weight: normal; font-size: 14px;">(0 selected)</span></h4>
                        <div class="student-list" id="unassignedList">
                            <div class="loading">Loading...</div>
                        </div>
                        <button class="btn-assign" onclick="assignSelected()" id="assignBtn" disabled>
                            ➜ Assign <span id="assignCount">0</span> Selected Student(s)
                        </button>
                    </div>
                    
                    <div class="assignment-column">
                        <h4>Currently Assigned Students</h4>
                        <div class="student-list" id="assignedList">
                            <div class="loading">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentProfessorId = null;
        let allUnassigned = [];
        let selectedStudents = [];

        document.addEventListener('DOMContentLoaded', function() {
            loadProfessors();
        });

        function loadProfessors() {
            fetch('admin_api.php?action=get_professors_list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderProfessors(data.professors);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function renderProfessors(professors) {
            const tbody = document.getElementById('professorsTable');
            
            if (professors.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="no-data">No professors found</td></tr>';
                return;
            }

            let html = '';
            professors.forEach(prof => {
                const totalAdvisees = Number(prof.advisee_count) || 0;
                const completed = Number(prof.completed_advisees) || 0;
                const pending = Number(prof.pending_advisees) || 0;
                const completion = totalAdvisees > 0 ? Math.round((completed / totalAdvisees) * 100) : 0;
                
                html += `
                    <tr>
                        <td>${prof.id_number}</td>
                        <td>${prof.full_name}</td>
                        <td>DECE</td>
                        <td>${totalAdvisees}</td>
                        <td>${completed}</td>
                        <td>${pending}</td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="progress-bar" style="flex: 1;">
                                    <div class="progress-fill" style="width: ${completion}%;"></div>
                                </div>
                                <span style="font-size: 12px; font-weight: 600;">${completion}%</span>
                            </div>
                        </td>
                        <td>
                            <button class="btn-manage" onclick="manageAdvisees(${prof.id}, '${prof.full_name}', ${prof.advisee_count})">Manage</button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function manageAdvisees(professorId, professorName, adviseeCount) {
            currentProfessorId = professorId;
            document.getElementById('professorName').textContent = professorName;
            document.getElementById('adviseeCount').textContent = adviseeCount;
            document.getElementById('modalTitle').textContent = `Manage Advisees - ${professorName}`;
            
            selectedStudents = [];
            document.getElementById('assignBtn').disabled = true;
            document.getElementById('selectedCount').textContent = '(0 selected)';
            document.getElementById('assignCount').textContent = '0';
            document.getElementById('unassignedSearch').value = '';
            document.getElementById('programFilter').value = '';
            
            loadUnassignedStudents();
            loadAssignedStudents(professorId);
            
            document.getElementById('assignmentModal').style.display = 'block';
        }

        function loadUnassignedStudents() {
            fetch('admin_api.php?action=get_unassigned_students')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allUnassigned = data.students;
                        renderUnassigned(allUnassigned);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function filterUnassigned() {
            const search = document.getElementById('unassignedSearch').value.toLowerCase();
            const program = document.getElementById('programFilter').value;
            
            let filtered = allUnassigned.filter(student => {
                const matchesSearch = student.id_number.toString().includes(search) || 
                                     student.full_name.toLowerCase().includes(search) ||
                                     student.email.toLowerCase().includes(search);
                const matchesProgram = !program || student.program === program;
                
                return matchesSearch && matchesProgram;
            });
            
            renderUnassigned(filtered);
        }

        function renderUnassigned(students) {
            const container = document.getElementById('unassignedList');
            
            if (students.length === 0) {
                container.innerHTML = '<div class="no-data">No unassigned students</div>';
                return;
            }

            let html = '';
            students.forEach(student => {
                const isSelected = selectedStudents.includes(student.id);
                const programShort = student.program.replace('BS ', '').replace('Electronics and Communications Engineering', 'ECE');
                
                html += `
                    <div class="student-item ${isSelected ? 'selected' : ''}" onclick="toggleStudent(${student.id})">
                        <div class="student-info">
                            <div class="student-name">${student.full_name}</div>
                            <div class="student-details">${student.id_number} • ${programShort}</div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function toggleStudent(studentId) {
            const index = selectedStudents.indexOf(studentId);
            if (index > -1) {
                selectedStudents.splice(index, 1);
            } else {
                selectedStudents.push(studentId);
            }
            
            // Update UI
            const count = selectedStudents.length;
            document.getElementById('assignBtn').disabled = count === 0;
            document.getElementById('selectedCount').textContent = `(${count} selected)`;
            document.getElementById('assignCount').textContent = count;
            
            // Re-render to update visual selection
            renderUnassigned(allUnassigned.filter(s => {
                const search = document.getElementById('unassignedSearch').value.toLowerCase();
                const program = document.getElementById('programFilter').value;
                const matchesSearch = s.id_number.toString().includes(search) || 
                                     s.full_name.toLowerCase().includes(search) ||
                                     s.email.toLowerCase().includes(search);
                const matchesProgram = !program || s.program === program;
                return matchesSearch && matchesProgram;
            }));
        }

        function assignSelected() {
            if (selectedStudents.length === 0) return;
            
            if (!confirm(`Assign ${selectedStudents.length} student(s) to this adviser?`)) return;
            
            const promises = selectedStudents.map(studentId => {
                const formData = new FormData();
                formData.append('action', 'assign_student_to_adviser');
                formData.append('student_id', studentId);
                formData.append('professor_id', currentProfessorId);
                
                return fetch('admin_api.php', {
                    method: 'POST',
                    body: formData
                }).then(r => r.json());
            });
            
            Promise.all(promises).then(results => {
                const allSuccess = results.every(r => r.success);
                if (allSuccess) {
                    alert(`${selectedStudents.length} student(s) assigned successfully!`);
                    selectedStudents = [];
                    document.getElementById('assignBtn').disabled = true;
                    document.getElementById('selectedCount').textContent = '(0 selected)';
                    document.getElementById('assignCount').textContent = '0';
                    loadUnassignedStudents();
                    loadAssignedStudents(currentProfessorId);
                    loadProfessors(); // Refresh main table
                } else {
                    alert('Some assignments failed. Please try again.');
                }
            });
        }

        function loadAssignedStudents(professorId) {
            fetch(`admin_api.php?action=get_adviser_students&professor_id=${professorId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderAssigned(data.students);
                        document.getElementById('adviseeCount').textContent = data.students.length;
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function renderAssigned(students) {
            const container = document.getElementById('assignedList');
            
            if (students.length === 0) {
                container.innerHTML = '<div class="no-data">No assigned students</div>';
                return;
            }

            let html = '';
            students.forEach(student => {
                const programShort = student.program.replace('BS ', '').replace('Electronics and Communications Engineering', 'ECE');
                let failedBadge = '';
                
                if (student.accumulated_failed_units >= 25) {
                    failedBadge = ' <span class="badge danger">CRITICAL</span>';
                } else if (student.accumulated_failed_units >= 15) {
                    failedBadge = ' <span class="badge warning">AT RISK</span>';
                }
                
                html += `
                    <div class="student-item">
                        <div class="student-info">
                            <div class="student-name">${student.full_name}${failedBadge}</div>
                            <div class="student-details">${student.id_number} • ${programShort} • ${student.accumulated_failed_units} failed units</div>
                        </div>
                        <button class="btn-unassign" onclick="unassignStudent(${student.id})">Remove</button>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function unassignStudent(studentId) {
            if (!confirm('Remove this student from the adviser?')) return;
            
            const formData = new FormData();
            formData.append('action', 'remove_student_from_adviser');
            formData.append('student_id', studentId);
            
            fetch('admin_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadUnassignedStudents();
                    loadAssignedStudents(currentProfessorId);
                    loadProfessors();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function closeModal() {
            document.getElementById('assignmentModal').style.display = 'none';
            selectedStudents = [];
        }

        window.onclick = function(event) {
            const modal = document.getElementById('assignmentModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>