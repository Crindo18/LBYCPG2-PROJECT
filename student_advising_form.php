<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student information
$stmt = $conn->prepare("
    SELECT s.*, CONCAT(s.first_name, ' ', s.last_name) as full_name,
           CONCAT(p.first_name, ' ', p.last_name) as adviser_name
    FROM students s
    LEFT JOIN professors p ON p.id = s.advisor_id
    WHERE s.id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get Course Catalog for Dropdowns
$course_catalog = [];
$course_query = $conn->query("SELECT course_code, course_name, units FROM course_catalog WHERE is_active = 1 ORDER BY course_code ASC");
while($row = $course_query->fetch_assoc()) {
    $course_catalog[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Advising Form</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: 260px; background: #1976D2; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #1565C0; }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.9; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #90CAF9; }
        
        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #1976D2; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        /* Tabs */
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; flex-wrap: wrap; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #1976D2; border-bottom-color: #1976D2; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* Content Cards */
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 20px; color: #1976D2; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .section-header { font-size: 18px; color: #1976D2; margin: 25px 0 15px 0; padding-bottom: 10px; border-bottom: 1px solid #e0e0e0; }
        
        /* Form Elements */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; font-family: inherit; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .form-group .help-text { font-size: 13px; color: #666; margin-top: 5px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        
        /* Course Entry */
        .course-entry-container { margin-bottom: 30px; border: 1px solid #eee; padding: 15px; border-radius: 8px; }
        .course-entry { background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 15px; position: relative; }
        .course-entry h4 { color: #1976D2; margin-bottom: 15px; font-size: 16px; }
        .remove-course-btn { position: absolute; top: 15px; right: 15px; background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 13px; }
        .add-course-btn { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; margin-top: 10px; width: 100%; }
        
        /* Booklet Summary Box - Matches your Image Format */
        .booklet-summary-box {
            background-color: #fff;
            border: 2px solid #333;
            padding: 20px;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        .booklet-summary-header {
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 15px;
            text-transform: uppercase;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        /* Prerequisite Entry */
        .prerequisite-container { margin-top: 15px; padding: 15px; background: white; border-radius: 5px; }
        .prerequisite-entry { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; margin-bottom: 10px; align-items: end; }
        .add-prereq-btn { background: #17a2b8; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; margin-top: 5px; }
        .remove-btn { background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        
        /* File Upload */
        .file-upload { border: 2px dashed #ddd; border-radius: 8px; padding: 30px; text-align: center; background: #fafafa; cursor: pointer; transition: all 0.3s; }
        .file-upload:hover { border-color: #1976D2; background: #f0f7ff; }
        .file-upload input[type="file"] { display: none; }
        .file-upload-label { cursor: pointer; color: #666; }
        .file-preview { margin-top: 15px; padding: 10px; background: #e3f2fd; border-radius: 5px; font-size: 14px; color: #1565C0; }
        
        /* Checkbox */
        .checkbox-group { display: flex; align-items: center; gap: 10px; margin: 20px 0; }
        .checkbox-group input[type="checkbox"] { width: auto; width: 18px; height: 18px; }
        .checkbox-group label { margin-bottom: 0; font-weight: normal; }
        
        /* Buttons */
        .btn { padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #1976D2; color: white; }
        .btn-primary:hover { background: #1565C0; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        .btn-container { display: flex; gap: 15px; margin-top: 30px; }
        
        /* Alerts */
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert.danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .alert.warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .alert.info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        
        /* Table */
        .table-container { overflow-x: auto; margin-top: 20px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.warning { background: #fff3cd; color: #856404; }
        .badge.info { background: #d1ecf1; color: #0c5460; }
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Student Portal</h2>
                <p><?php echo htmlspecialchars($student['full_name']); ?></p>
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
                <h1>Academic Advising Form</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('new')">Submit New Form</button>
                <button class="tab-btn" onclick="switchTab('history')">Submission History</button>
            </div>
            
            <!-- New Form Tab -->
            <div id="new" class="tab-content active">
                <div class="content-card">
                    <h3>Academic Advising Form</h3>
                    <p style="margin-bottom: 20px; color: #666;">Complete this form for academic advising. All fields marked with * are required.</p>
                    
                    <div id="formAlert"></div>
                    
                    <form id="advisingForm">
                        <!-- Basic Information -->
                        <h4 class="section-header">Student Details</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Student ID Number</label>
                                <input type="text" value="<?php echo htmlspecialchars($student['id_number']); ?>" readonly style="background: #f0f0f0;">
                            </div>
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($student['full_name']); ?>" readonly style="background: #f0f0f0;">
                            </div>
                            <div class="form-group">
                                <label>Program</label>
                                <input type="text" value="<?php echo htmlspecialchars($student['program']); ?>" readonly style="background: #f0f0f0;">
                            </div>
                        </div>
                        
                        <!-- Booklet-style Form Section -->
                        <div class="booklet-summary-box">
                            <div class="booklet-summary-header">
                                Academic Term Record
                            </div>
                            
                            <!-- Top: Academic Year & Term -->
                            <div class="form-row" style="margin-bottom: 20px; border-bottom: 1px dashed #ccc; padding-bottom: 15px;">
                                <div class="form-group">
                                    <label>Academic Year *</label>
                                    <input type="text" id="academicYear" placeholder="e.g., 2024-2025" required>
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
                            </div>

                            <!-- Middle: Enrolled Courses (The Table) -->
                            <h4 style="font-size: 16px; color: #555; margin-bottom: 10px;">Enrolled Courses</h4>
                            <div id="currentCoursesContainer" class="course-entry-container" style="border: none; padding: 0;">
                                <!-- Courses will be added here -->
                            </div>
                            <button type="button" class="add-course-btn" onclick="addCurrentCourse()">+ Add Course Record</button>
                            
                            <div class="form-row" style="margin-top: 15px;">
                                <div class="form-group">
                                    <label>Max Course Load Units *</label>
                                    <input type="number" id="maxUnits" min="1" step="1" placeholder="21" required>
                                </div>
                                <div class="form-group">
                                    <label>Total Enrolled Units *</label>
                                    <input type="number" id="totalEnrolledUnits" min="0" step="1" placeholder="0" required>
                                </div>
                            </div>

                            <!-- Bottom: Summary Statistics (Matches Image) -->
                            <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #333;">
                                <h4 style="font-size: 16px; color: #333; margin-bottom: 15px; text-decoration: underline;">Term Summary</h4>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Term GPA</label>
                                        <input type="number" id="previousTermGPA" min="0" max="4" step="0.001" placeholder="0.000">
                                    </div>
                                    <div class="form-group">
                                        <label>CGPA (Cumulative)</label>
                                        <input type="number" id="cumulativeGPA" min="0" max="4" step="0.001" placeholder="0.000">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Accumulated Failure (Units) *</label>
                                        <input type="number" id="overallFailedUnits" min="0" step="1" placeholder="0" required>
                                    </div>
                                    <!-- Added this field to match your request -->
                                    <div class="form-group">
                                        <label>Trimestral Honors</label>
                                        <select id="trimestralHonors">
                                            <option value="">None</option>
                                            <option value="First Dean's Lister">First Dean's Lister</option>
                                            <option value="Second Dean's Lister">Second Dean's Lister</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Hidden field for current year failures, might be calculated or manual -->
                                <input type="hidden" id="currentYearFailedUnits" value="0">
                            </div>
                        </div>
                        
                        <!-- Grade Screenshot Upload -->
                        <div class="form-group">
                            <label>Previous Term Grade Screenshot *</label>
                            <div class="file-upload" onclick="document.getElementById('gradeScreenshot').click()">
                                <input type="file" id="gradeScreenshot" accept="image/*,.pdf" required>
                                <label class="file-upload-label">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2 2v-4M17 8l-5-5-5 5M12 3v12"/>
                                    </svg>
                                    <p style="margin-top: 10px; font-weight: 600;">Click to upload grade screenshot</p>
                                    <p style="font-size: 12px; color: #999; margin-top: 5px;">Supported: JPG, PNG, PDF (Max 5MB)</p>
                                </label>
                            </div>
                            <div id="filePreview" class="file-preview" style="display: none;"></div>
                        </div>
                        
                        <!-- Additional Notes -->
                        <div class="form-group" style="margin-top: 30px;">
                            <label>Additional Notes/Concerns (Optional)</label>
                            <textarea id="additionalNotes" placeholder="Any concerns, questions, or additional information you'd like to share with your adviser..."></textarea>
                        </div>
                        
                        <!-- Certifications -->
                        <h4 class="section-header">Certifications</h4>
                        <div class="checkbox-group">
                            <input type="checkbox" id="certifyPrerequisites" required>
                            <label for="certifyPrerequisites">I certify that ALL prerequisite requirements for my currently enrolled courses have been fully satisfied. *</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="certifyAccuracy" required>
                            <label for="certifyAccuracy">I certify that all information provided in this form is true and correct. *</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="requestMeeting">
                            <label for="requestMeeting">I would like to request an advising meeting with my academic adviser.</label>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="btn-container">
                            <button type="submit" class="btn btn-primary">Submit Form</button>
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">Clear Form</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- History Tab -->
            <div id="history" class="tab-content">
                <div class="content-card">
                    <h3>Form Submission History</h3>
                    <div id="historyContent">
                        <div class="empty-state">Loading...</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let currentCourseCount = 0;
        // Course Catalog Data
        const courseCatalog = <?php echo json_encode($course_catalog); ?>;
        
        // Helper to generate Course Options
        function getCourseOptionsHtml() {
            let options = '<option value="">Select Course...</option>';
            courseCatalog.forEach(course => {
                options += `<option value="${course.course_code}">${course.course_code} - ${course.course_name}</option>`;
            });
            return options;
        }

        // Handle Course Selection Change
        function onCourseSelect(selectElement, id) {
            const selectedCode = selectElement.value;
            const nameInput = document.querySelector(`input[name="courseName_${id}"]`);
            const unitsInput = document.querySelector(`input[name="courseUnits_${id}"]`);
            
            if (selectedCode) {
                const course = courseCatalog.find(c => c.course_code === selectedCode);
                if (course) {
                    nameInput.value = course.course_name;
                    unitsInput.value = course.units;
                }
            } else {
                nameInput.value = '';
                unitsInput.value = '';
            }
        }

        // File upload preview handlers
        document.getElementById('gradeScreenshot').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const preview = document.getElementById('filePreview');
                preview.style.display = 'block';
                preview.innerHTML = `📄 ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
            }
        });
        
        // Tab switching
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tab).classList.add('active');
            
            if (tab === 'history') {
                loadSubmissionHistory();
            }
        }
        
        // Add current course entry
        function addCurrentCourse() {
            currentCourseCount++;
            const container = document.getElementById('currentCoursesContainer');
            const courseDiv = document.createElement('div');
            courseDiv.className = 'course-entry';
            courseDiv.id = `course-${currentCourseCount}`;
            
            const courseOptions = getCourseOptionsHtml();

            courseDiv.innerHTML = `
                <button type="button" class="remove-course-btn" onclick="removeCourse(${currentCourseCount})">Remove</button>
                <h4>Course ${currentCourseCount}</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Course Code *</label>
                        <select name="courseCode[]" required onchange="onCourseSelect(this, ${currentCourseCount})">
                            ${courseOptions}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Course Name *</label>
                        <input type="text" name="courseName_${currentCourseCount}" readonly style="background-color: #e9ecef;" placeholder="Auto-filled">
                        <input type="hidden" name="courseName[]">
                    </div>
                    <div class="form-group">
                        <label>Units *</label>
                        <input type="number" name="courseUnits_${currentCourseCount}" min="0" step="1" placeholder="0" required>
                        <input type="hidden" name="courseUnits[]">
                    </div>
                </div>
                <div class="prerequisite-container">
                    <label style="font-weight: 600; margin-bottom: 10px; display: block;">Prerequisites</label>
                    <div id="prereq-container-${currentCourseCount}">
                        <div class="prerequisite-entry">
                            <div>
                                <select name="prereqCode_${currentCourseCount}[]" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    ${courseOptions.replace('Select Course...', 'Select Prerequisite (or NONE)')}
                                </select>
                            </div>
                            <div>
                                <select name="prereqType_${currentCourseCount}[]" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="">Type</option>
                                    <option value="H">Hard (H)</option>
                                    <option value="S">Soft (S)</option>
                                    <option value="C">Co-req (C)</option>
                                </select>
                            </div>
                            <div>
                                <input type="text" name="prereqGrade_${currentCourseCount}[]" placeholder="Grade/N/A" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="add-prereq-btn" onclick="addPrerequisite(${currentCourseCount})">+ Add Another Prerequisite</button>
                </div>
            `;
            container.appendChild(courseDiv);
        }
        
        // Remove course
        function removeCourse(id) {
            const element = document.getElementById(`course-${id}`);
            if (element) {
                element.remove();
            }
        }
        
        // Add prerequisite
        function addPrerequisite(courseId) {
            const container = document.getElementById(`prereq-container-${courseId}`);
            const prereqDiv = document.createElement('div');
            const courseOptions = getCourseOptionsHtml();
            
            prereqDiv.className = 'prerequisite-entry';
            prereqDiv.innerHTML = `
                <div>
                    <select name="prereqCode_${courseId}[]" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        ${courseOptions.replace('Select Course...', 'Select Prerequisite')}
                    </select>
                </div>
                <div>
                    <select name="prereqType_${courseId}[]" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Type</option>
                        <option value="H">Hard (H)</option>
                        <option value="S">Soft (S)</option>
                        <option value="C">Co-req (C)</option>
                    </select>
                </div>
                <div>
                    <input type="text" name="prereqGrade_${courseId}[]" placeholder="Grade/N/A" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div>
                    <button type="button" class="remove-btn" onclick="this.parentElement.parentElement.remove()">Remove</button>
                </div>
            `;
            container.appendChild(prereqDiv);
        }
        
        // Form submission
        document.getElementById('advisingForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const gradeScreenshot = document.getElementById('gradeScreenshot').files[0];
            if (!gradeScreenshot) {
                showAlert('Please upload grade screenshot', 'warning');
                return;
            }
            if (gradeScreenshot.size > 5 * 1024 * 1024) {
                showAlert('Grade screenshot must be less than 5MB', 'danger');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'submit_advising_form');
            formData.append('academic_year', document.getElementById('academicYear').value);
            formData.append('term', document.getElementById('term').value);
            // Added new fields to submission
            formData.append('current_year_failed_units', document.getElementById('currentYearFailedUnits').value);
            formData.append('overall_failed_units', document.getElementById('overallFailedUnits').value);
            formData.append('previous_term_gpa', document.getElementById('previousTermGPA').value || '0');
            formData.append('cumulative_gpa', document.getElementById('cumulativeGPA').value || '0');
            formData.append('trimestral_honors', document.getElementById('trimestralHonors').value);
            
            formData.append('max_units', document.getElementById('maxUnits').value);
            formData.append('total_enrolled_units', document.getElementById('totalEnrolledUnits').value);
            formData.append('additional_notes', document.getElementById('additionalNotes').value);
            formData.append('certify_prerequisites', document.getElementById('certifyPrerequisites').checked ? '1' : '0');
            formData.append('certify_accuracy', document.getElementById('certifyAccuracy').checked ? '1' : '0');
            formData.append('request_meeting', document.getElementById('requestMeeting').checked ? '1' : '0');
            formData.append('grade_screenshot', gradeScreenshot);
            
            const courses = [];
            document.querySelectorAll('.course-entry').forEach((entry, index) => {
                const courseId = entry.id.split('-')[1];
                const courseCode = entry.querySelector('select[name="courseCode[]"]').value;
                const courseName = entry.querySelector(`input[name="courseName_${courseId}"]`).value;
                const units = entry.querySelector(`input[name="courseUnits_${courseId}"]`).value;
                
                const prereqs = [];
                const prereqContainer = entry.querySelector(`#prereq-container-${courseId}`);
                prereqContainer.querySelectorAll('.prerequisite-entry').forEach(prereqEntry => {
                    const codeSelect = prereqEntry.querySelector(`select[name="prereqCode_${courseId}[]"]`);
                    const code = codeSelect ? codeSelect.value : '';
                    const type = prereqEntry.querySelector(`select[name="prereqType_${courseId}[]"]`).value;
                    const grade = prereqEntry.querySelector(`input[name="prereqGrade_${courseId}[]"]`).value;
                    
                    if (code) {
                        prereqs.push({ code, type, grade });
                    }
                });
                
                if(courseCode) {
                    courses.push({
                        code: courseCode,
                        name: courseName,
                        units: units,
                        prerequisites: prereqs
                    });
                }
            });
            
            formData.append('current_courses', JSON.stringify(courses));
            
            try {
                const response = await fetch('student_advising_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Academic advising form submitted successfully! Your adviser will review it soon.', 'success');
                    setTimeout(() => {
                        resetForm();
                        switchTab('history');
                    }, 2000);
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            } catch (error) {
                showAlert('Error submitting form. Please try again.', 'danger');
            }
        });
        
        function resetForm() {
            document.getElementById('advisingForm').reset();
            document.getElementById('currentCoursesContainer').innerHTML = '';
            document.getElementById('filePreview').style.display = 'none';
            currentCourseCount = 0;
            addCurrentCourse();
        }
        
        function showAlert(message, type) {
            const alertDiv = document.getElementById('formAlert');
            alertDiv.innerHTML = `<div class="alert ${type}">${message}</div>`;
            alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(() => {
                alertDiv.innerHTML = '';
            }, 5000);
        }
        
        async function loadSubmissionHistory() {
            const container = document.getElementById('historyContent');
            container.innerHTML = '<div class="empty-state">Loading...</div>';
            
            try {
                const response = await fetch('student_advising_api.php?action=get_advising_forms');
                const data = await response.json();
                
                if (data.success && data.forms.length > 0) {
                    let html = '<div class="table-container"><table class="data-table">';
                    html += '<thead><tr><th>Academic Year</th><th>Term</th><th>Submitted</th><th>Status</th><th>Adviser Feedback</th></tr></thead><tbody>';
                    
                    data.forms.forEach(form => {
                        let statusBadge = '<span class="badge warning">Pending</span>';
                        if (form.cleared) {
                            statusBadge = '<span class="badge success">Cleared</span>';
                        } else if (form.adviser_feedback) {
                            statusBadge = '<span class="badge info">Reviewed</span>';
                        }
                        
                        html += `
                            <tr>
                                <td><strong>${form.academic_year}</strong></td>
                                <td>${form.term}</td>
                                <td>${new Date(form.submission_date).toLocaleDateString()}</td>
                                <td>${statusBadge}</td>
                                <td>${form.adviser_feedback || '-'}</td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table></div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="empty-state">No forms submitted yet</div>';
                }
            } catch (error) {
                container.innerHTML = '<div class="empty-state">Error loading submission history</div>';
            }
        }
        
        window.addEventListener('load', function() {
            addCurrentCourse();
        });
    </script>
</body>
</html>