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
    <title>User Accounts - Admin Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #00A36C; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #006B4A; }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.8; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #7FE5B8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #00A36C; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h2 { font-size: 22px; color: #00A36C; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #00A36C; border-bottom-color: #00A36C; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .btn-add-new { padding: 12px 24px; background: #00A36C; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; }
        .btn-add-new:hover { background: #8e24aa; }
        .filter-section { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .search-box { flex: 1; min-width: 250px; }
        .search-box input { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; min-width: 150px; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table th { background: #f8f9fa; padding: 10px; text-align: left; font-weight: 600; font-size: 12px; color: #555; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 10px; border-bottom: 1px solid #f0f0f0; }
        .data-table tr:hover { background: #f8f9fa; }
        .btn-view-pass, .btn-edit, .btn-delete { padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-right: 5px; }
        .btn-view-pass { background: #4CAF50; color: white; }
        .btn-view-pass:hover { background: #45a049; }
        .btn-edit { background: #2196F3; color: white; }
        .btn-edit:hover { background: #1976D2; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-delete:hover { background: #c82333; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; }
        .modal-content { background-color: white; margin: 30px auto; padding: 0; border-radius: 10px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 25px 30px; border-bottom: 2px solid #f0f0f0; position: sticky; top: 0; background: white; z-index: 10; }
        .modal-header h3 { color: #00A36C; font-size: 22px; }
        .close { font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; }
        .close:hover { color: #000; }
        .modal-body { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .btn-save { padding: 12px 30px; background: #00A36C; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; }
        .btn-save:hover { background: #8e24aa; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .no-data { text-align: center; padding: 40px; color: #999; font-style: italic; }
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge.warning { background: #fff3cd; color: #856404; }
        .badge.danger { background: #f8d7da; color: #721c24; }
        .badge.success { background: #d4edda; color: #155724; }
        .password-display { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; font-family: monospace; font-size: 18px; text-align: center; border: 2px solid #00A36C; color: #00A36C; font-weight: bold; }
        .section-title { font-size: 16px; font-weight: 600; color: #00A36C; margin-top: 20px; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #e0e0e0; }
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
                <a href="admin_accounts.php" class="menu-item active">User Accounts</a>
                <a href="admin_courses.php" class="menu-item">Course Catalog</a>
                <a href="admin_advising_forms.php" class="menu-item">Advising Forms</a>
                <a href="admin_advisingassignment.php" class="menu-item">Advising Assignments</a>
                <a href="admin_reports.php" class="menu-item">System Reports</a>
                <a href="admin_bulk_operations.php" class="menu-item">Bulk Ops & Uploads</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>User Account Management</h1>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('students')">Students</button>
                <button class="tab-btn" onclick="switchTab('professors')">Professors</button>
            </div>
            
            <!-- Students Tab -->
            <div id="students" class="tab-content active">
                <div class="content-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; padding: 0; border: none;">Student Accounts</h2>
                        <button class="btn-add-new" onclick="openAddStudentModal()">+ Add Student</button>
                    </div>
                    
                    <div class="filter-section">
                        <div class="search-box">
                            <input type="text" id="studentSearch" placeholder="🔍 Search by ID, name, or email..." onkeyup="filterStudents()">
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
                                    <th>Adviser</th>
                                    <th>Failed Units</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="studentsTable">
                                <tr><td colspan="8" class="loading">Loading students...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Professors Tab -->
            <div id="professors" class="tab-content">
                <div class="content-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; padding: 0; border: none;">Professor Accounts</h2>
                        <button class="btn-add-new" onclick="openAddProfessorModal()">+ Add Professor</button>
                    </div>
                    
                    <div class="filter-section">
                        <div class="search-box">
                            <input type="text" id="professorSearch" placeholder="🔍 Search by ID, name, or email..." onkeyup="loadProfessors()">
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID Number</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Email</th>
                                    <th>Advisees</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="professorsTable">
                                <tr><td colspan="6" class="loading">Loading professors...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Student Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="studentModalTitle">Add New Student</h3>
                <span class="close" onclick="closeStudentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="studentForm">
                    <div class="section-title">Personal Information</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>ID Number *</label>
                            <input type="text" id="studentIdNumber" required placeholder="e.g., 12012345" pattern="[0-9]{8}">
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" id="studentEmail" required placeholder="student@dlsu.edu.ph">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" id="studentFirstName" required>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" id="studentMiddleName">
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" id="studentLastName" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="text" id="studentPhone" required placeholder="+63 917 123 4567">
                    </div>
                    
                    <div class="section-title">Academic Information</div>
                    <div class="form-group">
                        <label>College *</label>
                        <input type="text" id="studentCollege" value="Gokongwei College of Engineering" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Department *</label>
                        <input type="text" id="studentDepartment" value="The Department of Electronics, Computer, and Electrical Engineering (DECE)" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Program *</label>
                            <select id="studentProgram" required>
                                <option value="">Select Program</option>
                                <option value="BS Computer Engineering">BS Computer Engineering</option>
                                <option value="BS Electronics and Communications Engineering">BS Electronics and Communications Engineering</option>
                                <option value="BS Electrical Engineering">BS Electrical Engineering</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Specialization</label>
                            <input type="text" id="studentSpecialization" placeholder="N/A">
                        </div>
                    </div>
                    
                    <div class="section-title">Guardian Information</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Parent/Guardian Name *</label>
                            <input type="text" id="guardianName" required>
                        </div>
                        <div class="form-group">
                            <label>Parent/Guardian Phone *</label>
                            <input type="text" id="guardianPhone" required placeholder="+63 918 765 4321">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-save">Save Student</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Professor Modal -->
    <div id="professorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="professorModalTitle">Add New Professor</h3>
                <span class="close" onclick="closeProfessorModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="professorForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>ID Number *</label>
                            <input type="text" id="professorIdNumber" required placeholder="e.g., 10012345" pattern="[0-9]{8}">
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" id="professorEmail" required placeholder="professor@dlsu.edu.ph">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" id="professorFirstName" required>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" id="professorMiddleName">
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" id="professorLastName" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Department *</label>
                        <input type="text" id="professorDepartment" value="The Department of Electronics, Computer, and Electrical Engineering (DECE)" required>
                    </div>
                    
                    <button type="submit" class="btn-save">Save Professor</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>User Password</h3>
                <span class="close" onclick="closePasswordModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 10px; color: #666;">Default password for this account:</p>
                <div class="password-display" id="passwordDisplay">Loading...</div>
                <p style="font-size: 13px; color: #999; text-align: center;">Note: Default password is the user's ID number</p>
            </div>
        </div>
    </div>

    <script>
        let allStudents = [];
        let allProfessors = [];

        document.addEventListener('DOMContentLoaded', function() {
            loadStudents();
            loadProfessors();
        });

        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById(tab).classList.add('active');
            event.target.classList.add('active');
        }

        // Students Functions
        function loadStudents() {
            fetch('admin_api.php?action=get_students_list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allStudents = data.students;
                        renderStudents(allStudents);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function filterStudents() {
            const search = document.getElementById('studentSearch').value.toLowerCase();
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
            const tbody = document.getElementById('studentsTable');
            
            if (students.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="no-data">No students found</td></tr>';
                return;
            }
            
            let html = '';
            students.forEach(student => {
                const failedUnits = Number(student.accumulated_failed_units) || 0;
                let failedBadge = '';
                
                if (failedUnits >= 25) {
                    failedBadge = '<span class="badge danger">CRITICAL</span>';
                } else if (failedUnits >= 15) {
                    failedBadge = '<span class="badge warning">AT RISK</span>';
                }
                
                const isCleared = Number(student.advising_cleared) === 1;
                const statusBadge = isCleared ? 
                    '<span class="badge success">Cleared</span>' : 
                    '<span class="badge" style="background: #fff3cd; color: #856404;">Pending</span>';
                
                html += `
                    <tr>
                        <td><strong>${student.id_number}</strong></td>
                        <td>${student.full_name}</td>
                        <td>${student.program.replace('BS ', '')}</td>
                        <td>${student.email}</td>
                        <td>${student.adviser_name || '<em style="color: #999;">Not assigned</em>'}</td>
                        <td>${failedUnits} ${failedBadge}</td>
                        <td>${statusBadge}</td>
                        <td style="white-space: nowrap;">
                            <button class="btn-edit" onclick="editStudent(${student.id})">Edit</button>
                            <button class="btn-delete" onclick="deleteStudent(${student.id}, '${student.id_number}')">Delete</button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        // Professors Functions
        function loadProfessors() {
            const search = document.getElementById('professorSearch').value;
            
            fetch(`admin_api.php?action=get_professors_list&search=${search}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allProfessors = data.professors;
                        renderProfessors(allProfessors);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function renderProfessors(professors) {
            const tbody = document.getElementById('professorsTable');
            
            if (professors.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="no-data">No professors found</td></tr>';
                return;
            }
            
            let html = '';
            professors.forEach(prof => {
                html += `
                    <tr>
                        <td><strong>${prof.id_number}</strong></td>
                        <td>${prof.full_name}</td>
                        <td>DECE</td>
                        <td>${prof.email}</td>
                        <td>${prof.advisee_count} students</td>
                        <td style="white-space: nowrap;">
                            <button class="btn-edit" onclick="editProfessor(${prof.id})">Edit</button>
                            <button class="btn-delete" onclick="deleteProfessor(${prof.id}, '${prof.id_number}')">Delete</button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        // Modal Functions
        function openAddStudentModal() {
            document.getElementById('studentModalTitle').textContent = 'Add New Student';
            document.getElementById('studentForm').reset();
            document.getElementById('studentForm').dataset.mode = 'add';
            document.getElementById('studentForm').dataset.studentId = '';
            document.getElementById('studentIdNumber').disabled = false;
            document.getElementById('studentCollege').value = 'Gokongwei College of Engineering';
            document.getElementById('studentDepartment').value = 'The Department of Electronics, Computer, and Electrical Engineering (DECE)';
            document.getElementById('studentModal').style.display = 'block';
        }

        function editStudent(studentId) {
            // Fetch complete student details from API
            fetch(`admin_api.php?action=get_student_details&student_id=${studentId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('Error loading student details');
                        return;
                    }
                    
                    const student = data.student;
                    
                    document.getElementById('studentModalTitle').textContent = 'Edit Student';
                    document.getElementById('studentForm').dataset.mode = 'edit';
                    document.getElementById('studentForm').dataset.studentId = student.id;
                    document.getElementById('studentIdNumber').value = student.id_number;
                    document.getElementById('studentIdNumber').disabled = true;
                    document.getElementById('studentFirstName').value = student.first_name;
                    document.getElementById('studentMiddleName').value = student.middle_name || '';
                    document.getElementById('studentLastName').value = student.last_name;
                    document.getElementById('studentCollege').value = student.college;
                    document.getElementById('studentDepartment').value = student.department;
                    document.getElementById('studentProgram').value = student.program;
                    document.getElementById('studentSpecialization').value = student.specialization || 'N/A';
                    document.getElementById('studentPhone').value = student.phone_number;
                    document.getElementById('studentEmail').value = student.email;
                    document.getElementById('guardianName').value = student.parent_guardian_name || '';
                    document.getElementById('guardianPhone').value = student.parent_guardian_number || '';
                    
                    document.getElementById('studentModal').style.display = 'block';
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function closeStudentModal() {
            document.getElementById('studentModal').style.display = 'none';
        }

        function openAddProfessorModal() {
            document.getElementById('professorModalTitle').textContent = 'Add New Professor';
            document.getElementById('professorForm').reset();
            document.getElementById('professorForm').dataset.mode = 'add';
            document.getElementById('professorForm').dataset.professorId = '';
            document.getElementById('professorIdNumber').disabled = false;
            document.getElementById('professorDepartment').value = 'The Department of Electronics, Computer, and Electrical Engineering (DECE)';
            document.getElementById('professorModal').style.display = 'block';
        }

        function editProfessor(professorId) {
            // Fetch complete professor details from API
            fetch(`admin_api.php?action=get_professor_details&professor_id=${professorId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('Error loading professor details');
                        return;
                    }
                    
                    const professor = data.professor;
                    
                    document.getElementById('professorModalTitle').textContent = 'Edit Professor';
                    document.getElementById('professorForm').dataset.mode = 'edit';
                    document.getElementById('professorForm').dataset.professorId = professor.id;
                    document.getElementById('professorIdNumber').value = professor.id_number;
                    document.getElementById('professorIdNumber').disabled = true;
                    document.getElementById('professorFirstName').value = professor.first_name;
                    document.getElementById('professorMiddleName').value = professor.middle_name || '';
                    document.getElementById('professorLastName').value = professor.last_name;
                    document.getElementById('professorDepartment').value = professor.department;
                    document.getElementById('professorEmail').value = professor.email;
                    
                    document.getElementById('professorModal').style.display = 'block';
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function closeProfessorModal() {
            document.getElementById('professorModal').style.display = 'none';
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
        }

        // Student Form Submission
        document.getElementById('studentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const mode = this.dataset.mode || 'add';
            const studentId = this.dataset.studentId;
            
            const formData = new FormData();
            formData.append('action', mode === 'edit' ? 'edit_student' : 'add_single_student');
            
            if (mode === 'edit') {
                formData.append('student_id', studentId);
            } else {
                formData.append('id_number', document.getElementById('studentIdNumber').value);
            }
            
            formData.append('first_name', document.getElementById('studentFirstName').value);
            formData.append('middle_name', document.getElementById('studentMiddleName').value);
            formData.append('last_name', document.getElementById('studentLastName').value);
            formData.append('college', document.getElementById('studentCollege').value);
            formData.append('department', document.getElementById('studentDepartment').value);
            formData.append('program', document.getElementById('studentProgram').value);
            formData.append('specialization', document.getElementById('studentSpecialization').value || 'N/A');
            formData.append('phone_number', document.getElementById('studentPhone').value);
            formData.append('email', document.getElementById('studentEmail').value);
            formData.append('guardian_name', document.getElementById('guardianName').value);
            formData.append('guardian_phone', document.getElementById('guardianPhone').value);
            
            fetch('admin_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeStudentModal();
                    loadStudents();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => alert('Error: ' + error.message));
        });

        // Professor Form Submission
        document.getElementById('professorForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const mode = this.dataset.mode || 'add';
            const professorId = this.dataset.professorId;
            
            const formData = new FormData();
            formData.append('action', mode === 'edit' ? 'edit_professor' : 'add_single_professor');
            
            if (mode === 'edit') {
                formData.append('professor_id', professorId);
            } else {
                formData.append('id_number', document.getElementById('professorIdNumber').value);
            }
            
            formData.append('first_name', document.getElementById('professorFirstName').value);
            formData.append('middle_name', document.getElementById('professorMiddleName').value);
            formData.append('last_name', document.getElementById('professorLastName').value);
            formData.append('department', document.getElementById('professorDepartment').value);
            formData.append('email', document.getElementById('professorEmail').value);
            
            fetch('admin_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeProfessorModal();
                    loadProfessors();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => alert('Error: ' + error.message));
        });

        // View Password
        function viewPassword(userId, userType) {
            document.getElementById('passwordDisplay').textContent = 'Loading...';
            document.getElementById('passwordModal').style.display = 'block';
            
            fetch(`admin_api.php?action=get_user_password&user_id=${userId}&user_type=${userType}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('passwordDisplay').textContent = data.password;
                    } else {
                        document.getElementById('passwordDisplay').textContent = 'Error loading password';
                    }
                })
                .catch(error => {
                    document.getElementById('passwordDisplay').textContent = 'Error: ' + error.message;
                });
        }

        // Delete Functions
        function deleteStudent(studentId, idNumber) {
            if (!confirm(`Are you sure you want to delete student ${idNumber}?`)) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_student');
            formData.append('student_id', studentId);
            
            fetch('admin_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadStudents();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => alert('Error: ' + error.message));
        }

        function deleteProfessor(professorId, idNumber) {
            if (!confirm(`Are you sure you want to delete professor ${idNumber}?`)) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_professor');
            formData.append('professor_id', professorId);
            
            fetch('admin_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadProfessors();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => alert('Error: ' + error.message));
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>