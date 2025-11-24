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
        
        /* File Upload Styling */
        .file-upload-area { border: 2px dashed #1976D2; border-radius: 8px; padding: 30px; text-align: center; background: #f8f9fa; cursor: pointer; transition: all 0.3s; }
        .file-upload-area:hover { background: #e3f2fd; border-color: #1565C0; }
        .file-upload-area.drag-over { background: #bbdefb; border-color: #0d47a1; }
        .file-upload-icon { font-size: 48px; color: #1976D2; margin-bottom: 10px; }
        .file-upload-text { font-size: 16px; color: #555; margin-bottom: 5px; }
        .file-upload-hint { font-size: 13px; color: #999; }
        .file-preview { margin-top: 20px; padding: 15px; background: white; border: 1px solid #ddd; border-radius: 8px; }
        .file-preview img { max-width: 100%; max-height: 300px; border-radius: 5px; }
        .file-preview-info { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
        .remove-file-btn { padding: 8px 15px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; }
        
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
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        
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
        .badge.danger { background: #f8d7da; color: #721c24; }
        .badge.info { background: #d1ecf1; color: #0c5460; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .loading { text-align: center; padding: 40px; color: #666; }
        
        .search-box { margin-bottom: 15px; }
        .search-box input { padding: 10px 15px; width: 100%; border: 1px solid #ddd; border-radius: 5px; }
        
        .filter-buttons { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
        .filter-btn { padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 5px; cursor: pointer; transition: all 0.3s; }
        .filter-btn.active { background: #1976D2; color: white; border-color: #1976D2; }
        
        /* Screenshot viewer modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; max-width: 800px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-close { font-size: 30px; cursor: pointer; color: #999; }
        .modal-close:hover { color: #333; }
        
        .action-buttons { display: flex; gap: 10px; }
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
                    
                    <form id="studyPlanForm" enctype="multipart/form-data">
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
                        
                        <!-- NEW: Grade Screenshot Upload -->
                        <div class="form-group">
                            <label>Grade Screenshot (Optional)</label>
                            <input type="file" id="screenshotInput" accept="image/jpeg,image/png,image/jpg" style="display: none;">
                            <div class="file-upload-area" id="uploadArea" onclick="document.getElementById('screenshotInput').click()">
                                <div class="file-upload-icon">📸</div>
                                <div class="file-upload-text">Click to upload grade screenshot</div>
                                <div class="file-upload-hint">PNG or JPG (Max 5MB)</div>
                            </div>
                            <div id="filePreview" class="file-preview" style="display: none;">
                                <img id="previewImage" src="" alt="Preview">
                                <div class="file-preview-info">
                                    <span id="fileName"></span>
                                    <button type="button" class="remove-file-btn" onclick="removeFile()">Remove</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="course-selector">
                            <h4 style="margin-bottom: 15px;">Select Courses to Enroll</h4>
                            
                            <div class="search-box">
                                <input type="text" id="courseSearch" placeholder="Search courses..." oninput="filterCourses()">
                            </div>
                            
                            <div class="filter-buttons">
                                <button type="button" class="filter-btn active" onclick="filterByTerm('all')">All Terms</button>
                                <button type="button" class="filter-btn" onclick="filterByTerm('Term 1')">Term 1</button>
                                <button type="button" class="filter-btn" onclick="filterByTerm('Term 2')">Term 2</button>
                                <button type="button" class="filter-btn" onclick="filterByTerm('Term 3')">Term 3</button>
                            </div>
                            
                            <div class="course-list" id="courseList">
                                <div class="loading">Loading courses...</div>
                            </div>
                            
                            <div class="selected-courses">
                                <h4 style="margin-bottom: 10px;">Selected Courses (<span id="selectedCount">0</span> courses, <span id="totalUnits">0</span> units)</h4>
                                <div id="selectedCoursesContainer"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="certified" required>
                                <label for="certified">I certify that the information provided is correct</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="wantsMeeting">
                                <label for="wantsMeeting">I would like to request a meeting with my adviser</label>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-primary" onclick="submitStudyPlan()">Submit Study Plan</button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset</button>
                    </form>
                </div>
            </div>
            
            <!-- My Study Plans Tab -->
            <div id="history" class="tab-content">
                <div class="content-card">
                    <h3>My Study Plans History</h3>
                    <div id="plansContent">
                        <div class="loading">Loading study plans...</div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Screenshot View Modal -->
    <div id="screenshotModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Grade Screenshot</h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <img id="modalImage" src="" style="max-width: 100%; border-radius: 5px;">
        </div>
    </div>
    
    <!-- Reupload Modal -->
    <div id="reuploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reupload Grade Screenshot</h3>
                <span class="modal-close" onclick="closeReuploadModal()">&times;</span>
            </div>
            <div id="reuploadAlert"></div>
            <p style="margin-bottom: 15px; color: #dc3545;">Your adviser has requested a new screenshot. Please upload an updated grade screenshot.</p>
            <div class="form-group">
                <input type="file" id="reuploadInput" accept="image/jpeg,image/png,image/jpg">
            </div>
            <div id="reuploadPreview" class="file-preview" style="display: none;">
                <img id="reuploadPreviewImage" src="" alt="Preview">
            </div>
            <div class="action-buttons" style="margin-top: 20px;">
                <button class="btn btn-primary" onclick="submitReupload()">Upload Screenshot</button>
                <button class="btn btn-secondary" onclick="closeReuploadModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        let allCourses = [];
        let selectedCourses = [];
        let currentTermFilter = 'all';
        let currentPlanId = null;
        let uploadedFile = null;

        window.onload = function() {
            loadAvailableCourses();
        };

        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById(tab).classList.add('active');
            event.target.classList.add('active');
            
            if (tab === 'history') {
                loadStudyPlans();
            }
        }

        // File upload handling
        document.getElementById('screenshotInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    showAlert('File size exceeds 5MB limit', 'danger');
                    return;
                }
                
                uploadedFile = file;
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImage').src = e.target.result;
                    document.getElementById('fileName').textContent = file.name;
                    document.getElementById('filePreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        // Drag and drop
        const uploadArea = document.getElementById('uploadArea');
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('drag-over');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (file && (file.type === 'image/jpeg' || file.type === 'image/png')) {
                const input = document.getElementById('screenshotInput');
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                input.files = dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            } else {
                showAlert('Please drop a valid image file (PNG or JPG)', 'danger');
            }
        });

        function removeFile() {
            uploadedFile = null;
            document.getElementById('screenshotInput').value = '';
            document.getElementById('filePreview').style.display = 'none';
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
                .catch(error => {
                    console.error('Error loading courses:', error);
                });
        }

        function renderCourseList() {
            const container = document.getElementById('courseList');
            const searchTerm = document.getElementById('courseSearch').value.toLowerCase();
            
            let filtered = allCourses.filter(course => {
                const matchesSearch = course.course_code.toLowerCase().includes(searchTerm) || 
                                    course.course_name.toLowerCase().includes(searchTerm);
                const matchesTerm = currentTermFilter === 'all' || course.term === currentTermFilter;
                return matchesSearch && matchesTerm;
            });
            
            if (filtered.length === 0) {
                container.innerHTML = '<div class="empty-state">No courses found</div>';
                return;
            }
            
            let html = '';
            filtered.forEach(course => {
                const isSelected = selectedCourses.find(c => c.code === course.course_code);
                html += `
                    <div class="course-item ${isSelected ? 'selected' : ''}" onclick="toggleCourse('${course.course_code}')">
                        <div class="course-code">${course.course_code}</div>
                        <div class="course-info">${course.course_name} • ${course.units} units • ${course.term}</div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function toggleCourse(code) {
            const course = allCourses.find(c => c.course_code === code);
            const index = selectedCourses.findIndex(c => c.code === code);
            
            if (index > -1) {
                selectedCourses.splice(index, 1);
            } else {
                selectedCourses.push({
                    code: course.course_code,
                    name: course.course_name,
                    units: parseInt(course.units)
                });
            }
            
            renderCourseList();
            renderSelectedCourses();
        }

        function renderSelectedCourses() {
            const container = document.getElementById('selectedCoursesContainer');
            
            if (selectedCourses.length === 0) {
                container.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No courses selected</div>';
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
                        <button class="remove-btn" onclick="removeCourse('${course.code}')">Remove</button>
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
            
            // Add screenshot if uploaded
            if (uploadedFile) {
                formData.append('screenshot', uploadedFile);
            }
            
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
            uploadedFile = null;
            document.getElementById('filePreview').style.display = 'none';
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
            html += '<thead><tr><th>Period</th><th>Submitted</th><th>Status</th><th>Courses</th><th>Screenshot</th><th>Feedback</th><th>Actions</th></tr></thead>';
            html += '<tbody>';
            
            plans.forEach(plan => {
                let statusBadge = '<span class="badge warning">Pending</span>';
                if (plan.cleared) {
                    statusBadge = '<span class="badge success">Cleared</span>';
                } else if (plan.adviser_feedback) {
                    statusBadge = '<span class="badge info">Reviewed</span>';
                }
                
                // Screenshot status
                let screenshotInfo = '-';
                if (plan.screenshot_reupload_requested) {
                    screenshotInfo = '<span class="badge danger">Reupload Required</span>';
                } else if (plan.grade_screenshot) {
                    screenshotInfo = `<button class="btn btn-sm btn-primary" style="padding: 5px 10px; font-size: 12px;" onclick="viewScreenshot('${plan.grade_screenshot}')">View</button>`;
                }
                
                const courseCount = plan.subjects ? plan.subjects.length : 0;
                const feedback = plan.adviser_feedback || '-';
                
                // Actions
                let actions = '';
                if (plan.screenshot_reupload_requested) {
                    actions = `<button class="btn btn-success" style="padding: 5px 10px; font-size: 12px;" onclick="openReuploadModal(${plan.id})">Upload Screenshot</button>`;
                }
                
                html += `
                    <tr>
                        <td><strong>${plan.academic_year} - ${plan.term}</strong></td>
                        <td>${formatDate(plan.submission_date)}</td>
                        <td>${statusBadge}</td>
                        <td>${courseCount} courses</td>
                        <td>${screenshotInfo}</td>
                        <td>${feedback}</td>
                        <td>${actions}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        function viewScreenshot(filename) {
            document.getElementById('modalImage').src = 'uploads/grades/' + filename;
            document.getElementById('screenshotModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('screenshotModal').classList.remove('active');
        }

        function openReuploadModal(planId) {
            currentPlanId = planId;
            document.getElementById('reuploadModal').classList.add('active');
            document.getElementById('reuploadInput').value = '';
            document.getElementById('reuploadPreview').style.display = 'none';
        }

        function closeReuploadModal() {
            document.getElementById('reuploadModal').classList.remove('active');
            currentPlanId = null;
        }

        document.getElementById('reuploadInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('reuploadPreviewImage').src = e.target.result;
                    document.getElementById('reuploadPreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        function submitReupload() {
            const fileInput = document.getElementById('reuploadInput');
            const file = fileInput.files[0];
            
            if (!file) {
                showReuploadAlert('Please select a file', 'danger');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'upload_grade_screenshot');
            formData.append('plan_id', currentPlanId);
            formData.append('screenshot', file);
            
            fetch('student_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showReuploadAlert('Screenshot uploaded successfully!', 'success');
                    setTimeout(() => {
                        closeReuploadModal();
                        loadStudyPlans();
                    }, 1500);
                } else {
                    showReuploadAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showReuploadAlert('Error uploading screenshot', 'danger');
            });
        }

        function showReuploadAlert(message, type) {
            const alertDiv = document.getElementById('reuploadAlert');
            alertDiv.innerHTML = `<div class="alert ${type}">${message}</div>`;
            
            setTimeout(() => {
                alertDiv.innerHTML = '';
            }, 5000);
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    </script>
</body>
</html>
