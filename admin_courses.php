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
    <title>Course Catalog - Admin Portal</title>
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
        .filter-section { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .search-box { flex: 1; min-width: 250px; }
        .search-box input { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .filter-select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; min-width: 150px; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; white-space: nowrap; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        .btn-edit, .btn-delete { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; margin-right: 5px; }
        .btn-edit { background: #2196F3; color: white; }
        .btn-edit:hover { background: #1976D2; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-delete:hover { background: #c82333; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; }
        .modal-content { background-color: white; margin: 50px auto; padding: 0; border-radius: 10px; width: 90%; max-width: 700px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 25px 30px; border-bottom: 2px solid #f0f0f0; }
        .modal-header h3 { color: #00A36C; font-size: 22px; }
        .close { font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; }
        .close:hover { color: #000; }
        .modal-body { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; font-family: inherit; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .btn-save { padding: 12px 30px; background: #00A36C; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; }
        .btn-save:hover { background: #8e24aa; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .no-data { text-align: center; padding: 40px; color: #999; font-style: italic; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .badge.major { background: #e3f2fd; color: #1976D2; }
        .badge.minor { background: #f3e5f5; color: #7b1fa2; }
        .badge.elective { background: #fff3e0; color: #e65100; }
        .badge.general_education { background: #e8f5e9; color: #2e7d32; }
        .prereq-display { font-size: 12px; color: #666; }
        .prereq-badge { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; margin-right: 4px; font-family: monospace; }
        .help-text { font-size: 12px; color: #666; margin-top: 5px; }
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
                <a href="admin_courses.php" class="menu-item active">Course Catalog</a>
                <a href="admin_advisingassignment.php" class="menu-item">Advising Assignments</a>
                <a href="admin_reports.php" class="menu-item">System Reports</a>
                <a href="admin_bulk_operations.php" class="menu-item">Bulk Ops & Uploads</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Course Catalog Management</h1>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchProgram('BS Computer Engineering')">BSCpE</button>
                <button class="tab-btn" onclick="switchProgram('BS Electronics and Communications Engineering')">BSECE</button>
                <button class="tab-btn" onclick="switchProgram('BS Electrical Engineering')">BSEE</button>
            </div>
            
            <div class="content-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0; padding: 0; border: none;" id="programTitle">BS Computer Engineering Courses</h2>
                    <button class="btn-add-new" onclick="openAddModal()">+ Add New Course</button>
                </div>
                
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="🔍 Search by course code or name..." onkeyup="filterCourses()">
                    </div>
                    <select id="termFilter" class="filter-select" onchange="filterCourses()">
                        <option value="">All Terms</option>
                        <option value="Term 1">Term 1</option>
                        <option value="Term 2">Term 2</option>
                        <option value="Term 3">Term 3</option>
                        <option value="Term 4">Term 4</option>
                        <option value="Term 5">Term 5</option>
                        <option value="Term 6">Term 6</option>
                        <option value="Term 7">Term 7</option>
                        <option value="Term 8">Term 8</option>
                        <option value="Term 9">Term 9</option>
                        <option value="Term 10">Term 10</option>
                        <option value="Term 11">Term 11</option>
                        <option value="Term 12">Term 12</option>
                    </select>
                    <select id="typeFilter" class="filter-select" onchange="filterCourses()">
                        <option value="">All Types</option>
                        <option value="major">Major</option>
                        <option value="minor">Minor</option>
                        <option value="elective">Elective</option>
                        <option value="general_education">General Education</option>
                    </select>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Course Name</th>
                                <th>Units</th>
                                <th>Term</th>
                                <th>Type</th>
                                <th>Prerequisites</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="coursesTable">
                            <tr><td colspan="7" class="loading">Loading courses...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add/Edit Course Modal -->
    <div id="courseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Course</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="courseForm">
                    <input type="hidden" id="courseId">
                    <input type="hidden" id="courseProgram">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Course Code *</label>
                            <input type="text" id="courseCode" required placeholder="e.g., CSSWENG">
                        </div>
                        <div class="form-group">
                            <label>Units *</label>
                            <input type="number" id="units" required min="1" max="6" placeholder="e.g., 3">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Course Name *</label>
                        <input type="text" id="courseName" required placeholder="e.g., Software Engineering">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Term *</label>
                            <select id="term" required>
                                <option value="">Select Term</option>
                                <option value="Term 1">Term 1</option>
                                <option value="Term 2">Term 2</option>
                                <option value="Term 3">Term 3</option>
                                <option value="Term 4">Term 4</option>
                                <option value="Term 5">Term 5</option>
                                <option value="Term 6">Term 6</option>
                                <option value="Term 7">Term 7</option>
                                <option value="Term 8">Term 8</option>
                                <option value="Term 9">Term 9</option>
                                <option value="Term 10">Term 10</option>
                                <option value="Term 11">Term 11</option>
                                <option value="Term 12">Term 12</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Course Type *</label>
                            <select id="courseType" required>
                                <option value="major">Major</option>
                                <option value="minor">Minor</option>
                                <option value="elective">Elective</option>
                                <option value="general_education">General Education</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Prerequisites</label>
                        <textarea id="prerequisites" placeholder="e.g., FNDMATH(H),PROLOGI(S) or PROLOGI(C)"></textarea>
                        <div class="help-text">
                            Format: COURSECODE(TYPE) separated by commas<br>
                            Types: H = Hard-Prerequisite, S = Soft-Prerequisite, C = Co-requisite<br>
                            Example: FNDMATH(H),PROLOGI(S) or PROLOGI(C)
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-save">Save Course</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentProgram = 'BS Computer Engineering';
        let allCourses = [];

        // Load courses on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCourses();
        });

        function switchProgram(program) {
            currentProgram = program;
            
            // Update active tab
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update title
            const programShort = program.includes('Computer') ? 'BSCpE' : 
                                program.includes('Electronics') ? 'BSECE' : 'BSEE';
            document.getElementById('programTitle').textContent = `${programShort} Courses`;
            
            // Reset filters
            document.getElementById('searchInput').value = '';
            document.getElementById('termFilter').value = '';
            document.getElementById('typeFilter').value = '';
            
            loadCourses();
        }

        function loadCourses() {
            const params = new URLSearchParams({
                action: 'get_course_catalog',
                program: currentProgram
            });
            
            fetch(`admin_api.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allCourses = data.courses;
                        renderCourses(allCourses);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function filterCourses() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const termFilter = document.getElementById('termFilter').value;
            const typeFilter = document.getElementById('typeFilter').value;
            
            let filtered = allCourses.filter(course => {
                const matchesSearch = course.course_code.toLowerCase().includes(searchTerm) || 
                                     course.course_name.toLowerCase().includes(searchTerm);
                const matchesTerm = !termFilter || course.term === termFilter;
                const matchesType = !typeFilter || course.course_type === typeFilter;
                
                return matchesSearch && matchesTerm && matchesType;
            });
            
            renderCourses(filtered);
        }

        function renderCourses(courses) {
            const tbody = document.getElementById('coursesTable');
            
            if (courses.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">No courses found</td></tr>';
                return;
            }
            
            let html = '';
            courses.forEach(course => {
                const prereqDisplay = course.prerequisites ? 
                    formatPrerequisites(course.prerequisites) : 
                    '<span style="color: #999;">None</span>';
                
                html += `
                    <tr>
                        <td><strong>${course.course_code}</strong></td>
                        <td>${course.course_name}</td>
                        <td>${course.units}</td>
                        <td>${course.term}</td>
                        <td><span class="badge ${course.course_type}">${course.course_type.replace('_', ' ')}</span></td>
                        <td class="prereq-display">${prereqDisplay}</td>
                        <td>
                            <button class="btn-edit" onclick="editCourse(${course.id})">Edit</button>
                            <button class="btn-delete" onclick="deleteCourse(${course.id}, '${course.course_code}')">Delete</button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        function formatPrerequisites(prereqs) {
            if (!prereqs) return '<span style="color: #999;">None</span>';
            
            const parts = prereqs.split(',');
            return parts.map(p => `<span class="prereq-badge">${p.trim()}</span>`).join('');
        }

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Course';
            document.getElementById('courseForm').reset();
            document.getElementById('courseId').value = '';
            document.getElementById('courseProgram').value = currentProgram;
            document.getElementById('courseCode').disabled = false;
            document.getElementById('courseModal').style.display = 'block';
        }

        function editCourse(courseId) {
            const course = allCourses.find(c => Number(c.id) === Number(courseId));
            if (!course) return;
            
            document.getElementById('modalTitle').textContent = 'Edit Course';
            document.getElementById('courseId').value = course.id;
            document.getElementById('courseProgram').value = course.program;
            document.getElementById('courseCode').value = course.course_code;
            document.getElementById('courseName').value = course.course_name;
            document.getElementById('units').value = course.units;
            document.getElementById('term').value = course.term;
            document.getElementById('courseType').value = course.course_type;
            document.getElementById('prerequisites').value = course.prerequisites || '';
            
            document.getElementById('courseModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('courseModal').style.display = 'none';
        }

        // Course form submission
        document.getElementById('courseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const courseId = document.getElementById('courseId').value;
            const formData = new FormData();
            formData.append('course_code', document.getElementById('courseCode').value);
            formData.append('program', document.getElementById('courseProgram').value || currentProgram);
            
            if (courseId) {
                formData.append('action', 'update_course');
                formData.append('course_id', courseId);
            } else {
                formData.append('action', 'add_course');
            }
            
            formData.append('course_name', document.getElementById('courseName').value);
            formData.append('units', document.getElementById('units').value);
            formData.append('term', document.getElementById('term').value);
            formData.append('course_type', document.getElementById('courseType').value);
            formData.append('prerequisites', document.getElementById('prerequisites').value);
            
            fetch('admin_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeModal();
                    loadCourses();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        });

        function deleteCourse(courseId, courseCode) {
            if (!confirm(`Are you sure you want to delete ${courseCode}?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_course');
            formData.append('course_id', courseId);
            
            fetch('admin_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadCourses();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('courseModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>