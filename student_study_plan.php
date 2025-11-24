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
    <title>Study Plan</title>
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
        
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; flex-wrap: wrap; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #1976D2; border-bottom-color: #1976D2; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 20px; color: #1976D2; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .form-group .help-text { font-size: 13px; color: #666; margin-top: 5px; }
        
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input[type="checkbox"] { width: auto; }
        
        .course-selector { border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; background: #f8f9fa; }
        .course-list { max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; background: white; }
        .course-item { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.2s; }
        .course-item:hover { background: #e3f2fd; }
        .course-item.selected { background: #90CAF9; color: white; }
        .course-item .course-code { font-weight: 600; }
        .course-item .course-info { font-size: 13px; color: #666; margin-top: 5px; }
        .course-item.selected .course-info { color: rgba(255,255,255,0.9); }
        
        .selected-courses { margin-top: 20px; }
        .selected-course-item { padding: 10px 15px; background: white; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .remove-btn { padding: 5px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #1976D2; color: white; }
        .btn-primary:hover { background: #1565C0; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert.danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .alert.warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .alert.info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.warning { background: #fff3cd; color: #856404; }
        .badge.info { background: #d1ecf1; color: #0c5460; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .loading { text-align: center; padding: 40px; color: #666; }
        
        .search-box { margin-bottom: 15px; }
        .search-box input { padding: 10px 15px; width: 100%; border: 1px solid #ddd; border-radius: 5px; }
        
        .filter-buttons { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
        .filter-btn { padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 5px; cursor: pointer; transition: all 0.3s; }
        .filter-btn.active { background: #1976D2; color: white; border-color: #1976D2; }
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
                <a href="student_study_plan_enhanced.php" class="menu-item">Study Plan</a>
                <a href="student_meeting.php" class="menu-item">Meeting Schedule</a>
                <a href="student_documents.php" class="menu-item">Documents</a>
                <a href="student_concerns.php" class="menu-item">Submit Concern</a>
                <a href="student_profile.php" class="menu-item">My Profile</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Study Plan</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('new')">Submit New Plan</button>
                <button class="tab-btn" onclick="switchTab('history')">My Study Plans</button>
            </div>
            
            <!-- New Study Plan Tab -->
            <div id="new" class="tab-content active">
                <div class="content-card">
                    <h3>Submit Study Plan</h3>
                    
                    <div id="submitAlert"></div>
                    
                    <form id="studyPlanForm">
                        <div class="form-group">
                            <label>Academic Year *</label>
                            <input type="text" id="academicYear" placeholder="e.g., 2024-2025" required>
                            <div class="help-text">Enter the academic year for this study plan</div>
                        </div>
                        
                        <div class="form-group">
                            <label>Term *</label>
                            <select id="term" required>
                                <option value="">Select term...</option>
                                <option value="Term 1">Term 1</option>
                                <option value="Term 2">Term 2</option>
                                <option value="Term 3">Term 3</option>
                            </select>
                        </div>
                        
                        <div class="course-selector">
                            <h4 style="margin-bottom: 15px;">Select Courses to Enroll</h4>
                            
                            <div class="search-box">
                                <input type="text" id="courseSearch" placeholder="Search courses by code or name..." onkeyup="filterCourses()">
                            </div>
                            
                            <div class="filter-buttons">
                                <button type="button" class="filter-btn active" onclick="filterByTerm('all')">All Terms</button>
                                <button type="button" class="filter-btn" onclick="filterByTerm('Term 1')">Term 1</button>
                                <button type="button" class="filter-btn" onclick="filterByTerm('Term 2')">Term 2</button>
                                <button type="button" class="filter-btn" onclick="filterByTerm('Term 3')">Term 3</button>
                            </div>
                            
                            <div id="courseList" class="course-list">
                                <div class="loading">Loading available courses...</div>
                            </div>
                            
                            <div class="selected-courses">
                                <h4 style="margin-bottom: 10px;">Selected Courses (<span id="selectedCount">0</span>)</h4>
                                <div id="selectedCourses"></div>
                                <div style="margin-top: 10px; font-weight: 600;">
                                    Total Units: <span id="totalUnits">0</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="certified" value="1">
                                <label for="certified" style="margin: 0;">I certify that all information is correct</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="wantsMeeting" value="1">
                                <label for="wantsMeeting" style="margin: 0;">I would like to schedule a meeting with my adviser</label>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">Submit Study Plan</button>
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Study Plan History Tab -->
            <div id="history" class="tab-content">
                <div class="content-card">
                    <h3>My Study Plans</h3>
                    <div id="plansContent" class="loading">Loading study plans...</div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let allCourses = [];
        let selectedCourses = [];
        let currentTermFilter = 'all';

        document.addEventListener('DOMContentLoaded', function() {
            loadAvailableCourses();
            
            document.getElementById('studyPlanForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitStudyPlan();
            });
        });

        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            document.getElementById(tab).classList.add('active');
            event.target.classList.add('active');
            
            if (tab === 'history') {
                loadStudyPlans();
            }
        }

        function loadAvailableCourses() {
            fetch('student_api.php?action=get_available_courses')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allCourses = data.courses;
                        renderCourseList();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function renderCourseList() {
            const container = document.getElementById('courseList');
            
            let filtered = allCourses;
            
            // Apply term filter
            if (currentTermFilter !== 'all') {
                filtered = filtered.filter(c => c.term === currentTermFilter);
            }
            
            // Apply search filter
            const searchTerm = document.getElementById('courseSearch').value.toLowerCase();
            if (searchTerm) {
                filtered = filtered.filter(c => 
                    c.course_code.toLowerCase().includes(searchTerm) || 
                    c.course_name.toLowerCase().includes(searchTerm)
                );
            }
            
            if (filtered.length === 0) {
                container.innerHTML = '<div class="empty-state">No courses found</div>';
                return;
            }
            
            let html = '';
            filtered.forEach(course => {
                const isSelected = selectedCourses.some(c => c.code === course.course_code);
                html += `
                    <div class="course-item ${isSelected ? 'selected' : ''}" onclick="toggleCourse('${course.course_code}', '${course.course_name}', ${course.units})">
                        <div class="course-code">${course.course_code} - ${course.course_name}</div>
                        <div class="course-info">${course.units} units | ${course.term} | ${course.course_type}</div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function toggleCourse(code, name, units) {
            const index = selectedCourses.findIndex(c => c.code === code);
            
            if (index > -1) {
                selectedCourses.splice(index, 1);
            } else {
                selectedCourses.push({ code, name, units });
            }
            
            renderCourseList();
            renderSelectedCourses();
        }

        function renderSelectedCourses() {
            const container = document.getElementById('selectedCourses');
            
            if (selectedCourses.length === 0) {
                container.innerHTML = '<div style="color: #999; font-style: italic;">No courses selected yet</div>';
                document.getElementById('selectedCount').textContent = '0';
                document.getElementById('totalUnits').textContent = '0';
                return;
            }
            
            let html = '';
            selectedCourses.forEach(course => {
                html += `
                    <div class="selected-course-item">
                        <div>
                            <strong>${course.code}</strong> - ${course.name} (${course.units} units)
                        </div>
                        <button type="button" class="remove-btn" onclick="removeCourse('${course.code}')">Remove</button>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            
            const totalUnits = selectedCourses.reduce((sum, c) => sum + c.units, 0);
            document.getElementById('selectedCount').textContent = selectedCourses.length;
            document.getElementById('totalUnits').textContent = totalUnits;
        }

        function removeCourse(code) {
            selectedCourses = selectedCourses.filter(c => c.code !== code);
            renderCourseList();
            renderSelectedCourses();
        }

        function filterCourses() {
            renderCourseList();
        }

        function filterByTerm(term) {
            currentTermFilter = term;
            
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            renderCourseList();
        }

        function submitStudyPlan() {
            const academicYear = document.getElementById('academicYear').value;
            const term = document.getElementById('term').value;
            const certified = document.getElementById('certified').checked ? 1 : 0;
            const wantsMeeting = document.getElementById('wantsMeeting').checked ? 1 : 0;
            
            if (!academicYear || !term) {
                showAlert('Please fill in all required fields', 'danger');
                return;
            }
            
            if (selectedCourses.length === 0) {
                showAlert('Please select at least one course', 'warning');
                return;
            }
            
            if (!certified) {
                showAlert('Please certify that the information is correct', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'submit_study_plan');
            formData.append('academic_year', academicYear);
            formData.append('term', term);
            formData.append('certified', certified);
            formData.append('wants_meeting', wantsMeeting);
            formData.append('planned_subjects', JSON.stringify(selectedCourses));
            
            fetch('student_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Study plan submitted successfully! Your adviser will review it soon.', 'success');
                    resetForm();
                    setTimeout(() => {
                        switchTab('history');
                    }, 2000);
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Error submitting study plan', 'danger');
            });
        }

        function resetForm() {
            document.getElementById('studyPlanForm').reset();
            selectedCourses = [];
            renderSelectedCourses();
            renderCourseList();
        }

        function showAlert(message, type) {
            const alertDiv = document.getElementById('submitAlert');
            alertDiv.innerHTML = `<div class="alert ${type}">${message}</div>`;
            
            setTimeout(() => {
                alertDiv.innerHTML = '';
            }, 5000);
        }

        function loadStudyPlans() {
            const container = document.getElementById('plansContent');
            container.innerHTML = '<div class="loading">Loading study plans...</div>';
            
            fetch('student_api.php?action=get_my_study_plans')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderStudyPlans(data.plans);
                    } else {
                        container.innerHTML = '<div class="empty-state">No study plans submitted yet</div>';
                    }
                })
                .catch(error => {
                    container.innerHTML = '<div class="empty-state">Error loading study plans</div>';
                });
        }

        function renderStudyPlans(plans) {
            const container = document.getElementById('plansContent');
            
            if (plans.length === 0) {
                container.innerHTML = '<div class="empty-state">No study plans submitted yet</div>';
                return;
            }
            
            let html = '<div class="table-container"><table class="data-table">';
            html += '<thead><tr><th>Period</th><th>Submitted</th><th>Status</th><th>Courses</th><th>Feedback</th></tr></thead>';
            html += '<tbody>';
            
            plans.forEach(plan => {
                let statusBadge = '<span class="badge warning">Pending</span>';
                if (plan.cleared) {
                    statusBadge = '<span class="badge success">Cleared</span>';
                } else if (plan.adviser_feedback) {
                    statusBadge = '<span class="badge info">Reviewed</span>';
                }
                
                const courseCount = plan.subjects ? plan.subjects.length : 0;
                const feedback = plan.adviser_feedback || '-';
                
                html += `
                    <tr>
                        <td><strong>${plan.academic_year} - ${plan.term}</strong></td>
                        <td>${formatDate(plan.submission_date)}</td>
                        <td>${statusBadge}</td>
                        <td>${courseCount} courses</td>
                        <td>${feedback}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    </script>
</body>
</html>