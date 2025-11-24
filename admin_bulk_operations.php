<?php
require_once 'auth_check.php';
requireAdmin();

require_once 'config.php';

$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_name = $stmt->get_result()->fetch_assoc()['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Operations</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #6a1b9a; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #4a148c; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.9; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.08); border-left-color: #BA68C8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #6a1b9a; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        .operations-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 30px; }
        
        .operation-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .operation-card h3 { font-size: 20px; color: #2c3e50; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; display: flex; align-items: center; gap: 10px; }
        .operation-icon { font-size: 24px; }
        .operation-description { color: #666; margin-bottom: 20px; line-height: 1.6; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px; }
        .form-group input[type="file"], .form-group input[type="text"], .form-group select, .form-group textarea {
            width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;
        }
        .form-group textarea { min-height: 120px; resize: vertical; font-family: inherit; }
        .form-group .help-text { font-size: 12px; color: #999; margin-top: 5px; }
        
        .file-upload-area { border: 2px dashed #3498db; border-radius: 8px; padding: 30px; text-align: center; background: #f8f9fa; cursor: pointer; transition: all 0.3s; }
        .file-upload-area:hover { background: #e9ecef; border-color: #2980b9; }
        .file-upload-icon { font-size: 48px; color: #3498db; margin-bottom: 10px; }
        .file-info { margin-top: 15px; padding: 10px; background: white; border-radius: 5px; font-size: 14px; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; transition: all 0.3s; width: 100%; }
        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #229954; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-warning:hover { background: #e67e22; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert.danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .alert.warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .alert.info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        
        .progress-container { display: none; margin-top: 20px; }
        .progress-bar { width: 100%; height: 30px; background: #f0f0f0; border-radius: 15px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #3498db, #2ecc71); transition: width 0.3s; text-align: center; line-height: 30px; color: white; font-weight: bold; }
        
        .result-box { margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; display: none; }
        .result-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px; }
        .result-stat { text-align: center; padding: 15px; background: white; border-radius: 5px; }
        .result-stat-value { font-size: 24px; font-weight: bold; color: #3498db; }
        .result-stat-label { font-size: 12px; color: #666; margin-top: 5px; }
        
        .checkbox-group { display: flex; align-items: center; gap: 10px; margin: 10px 0; }
        .checkbox-group input[type="checkbox"] { width: auto; }
        
        .template-download { display: inline-block; padding: 8px 16px; background: #95a5a6; color: white; border-radius: 5px; text-decoration: none; font-size: 13px; margin-bottom: 15px; }
        .template-download:hover { background: #7f8c8d; }
        .bulk-upload-section { margin-top: 40px; }
        .bulk-upload-heading { color: #2c3e50; margin-bottom: 10px; }
        .bulk-upload-subtitle { color: #666; margin-bottom: 20px; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e0e0e0; flex-wrap: wrap; }
        .tab-btn { padding: 10px 22px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #3498db; border-bottom-color: #3498db; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .content-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .content-card h2 { font-size: 22px; color: #2c3e50; margin-bottom: 15px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .upload-section { margin-top: 10px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 2px dashed #ddd; }
        .upload-section h3 { font-size: 18px; color: #2c3e50; margin-bottom: 15px; }
        .upload-section p { color: #666; margin-bottom: 15px; font-size: 14px; }
        .file-input-wrapper { position: relative; overflow: hidden; display: inline-block; margin-top: 10px; }
        .file-input-wrapper input[type="file"] { position: absolute; left: -9999px; }
        .file-input-label { padding: 10px 20px; background: #3498db; color: white; border-radius: 5px; cursor: pointer; display: inline-block; font-size: 14px; }
        .file-input-label:hover { background: #2980b9; }
        .selected-file { margin: 10px 0; color: #555; font-size: 14px; }
        .btn-upload { padding: 10px 24px; background: #27ae60; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; margin-top: 10px; }
        .btn-upload:hover { background: #1f8c4c; }
        .btn-upload:disabled { background: #ccc; cursor: not-allowed; }
        .download-template { color: #3498db; text-decoration: none; font-size: 14px; margin-left: 15px; }
        .download-template:hover { text-decoration: underline; }
        .result-box { margin-top: 20px; padding: 15px; border-radius: 5px; display: none; }
        .result-box.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .result-box.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .result-details { margin-top: 10px; font-size: 13px; }
        .error-list { max-height: 200px; overflow-y: auto; margin-top: 10px; }
        .error-item { padding: 5px; background: white; margin: 5px 0; border-radius: 3px; font-size: 12px; }
        .format-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        .format-table th, .format-table td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        .format-table th { background: #f0f0f0; font-weight: 600; }
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
                <a href="admin_advisingassignment.php" class="menu-item">Advising Assignments</a>
                <a href="admin_reports.php" class="menu-item">System Reports</a>
                <a href="admin_bulk_operations.php" class="menu-item active">Bulk Ops & Uploads</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Bulk Operations</h1>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="operations-grid">
                <!-- Bulk Clearance Management -->
                <div class="operation-card">
                    <h3><span class="operation-icon">✅</span> Bulk Clearance Management</h3>
                    <p class="operation-description">
                        Clear or unclear multiple students at once. Useful for mass clearance operations.
                    </p>
                    
                    <div id="clearanceAlert"></div>
                    
                    <form id="clearanceForm">
                        <div class="form-group">
                            <label>Action *</label>
                            <select id="clearanceAction" required>
                                <option value="">Select action...</option>
                                <option value="clear">Clear Students</option>
                                <option value="unclear">Unclear Students</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Target Students *</label>
                            <select id="clearanceTarget" required>
                                <option value="">Select target...</option>
                                <option value="all">All Students</option>
                                <option value="program">By Program</option>
                                <option value="adviser">By Adviser</option>
                                <option value="list">From List</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="clearanceProgramGroup" style="display: none;">
                            <label>Program</label>
                            <select id="clearanceProgram">
                                <option value="BS Computer Engineering">BSCpE</option>
                                <option value="BS Electronics and Communications Engineering">BSECE</option>
                                <option value="BS Electrical Engineering">BSEE</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="clearanceAdviserGroup" style="display: none;">
                            <label>Adviser</label>
                            <select id="clearanceAdviser">
                                <option value="">Loading advisers...</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="clearanceListGroup" style="display: none;">
                            <label>Student ID Numbers (one per line)</label>
                            <textarea id="clearanceList" placeholder="12012345
12012346
12012347"></textarea>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="clearanceConfirm" required>
                            <label for="clearanceConfirm">I confirm this bulk operation</label>
                        </div>
                        
                        <button type="button" class="btn btn-warning" onclick="performClearance()">Execute Clearance</button>
                    </form>
                    
                    <div class="result-box" id="clearanceResult"></div>
                </div>
                
                <!-- Mass Email -->
                <div class="operation-card">
                    <h3><span class="operation-icon">📧</span> Mass Email</h3>
                    <p class="operation-description">
                        Send emails to multiple users at once. Choose recipients and compose your message.
                    </p>
                    
                    <div id="emailAlert"></div>
                    
                    <form id="emailForm">
                        <div class="form-group">
                            <label>Recipients *</label>
                            <select id="emailRecipients" required>
                                <option value="">Select recipients...</option>
                                <option value="all_students">All Students</option>
                                <option value="all_professors">All Professors</option>
                                <option value="program">Students by Program</option>
                                <option value="cleared">Cleared Students Only</option>
                                <option value="not_cleared">Not Cleared Students Only</option>
                                <option value="at_risk">At-Risk Students (25+ failed units)</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="emailProgramGroup" style="display: none;">
                            <label>Program</label>
                            <select id="emailProgram">
                                <option value="BS Computer Engineering">BSCpE</option>
                                <option value="BS Electronics and Communications Engineering">BSECE</option>
                                <option value="BS Electrical Engineering">BSEE</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Subject *</label>
                            <input type="text" id="emailSubject" placeholder="Email subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Message *</label>
                            <textarea id="emailMessage" placeholder="Email content..." required></textarea>
                            <div class="help-text">Available variables: {name}, {id_number}, {program}</div>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="emailPreview">
                            <label for="emailPreview">Send test email to myself first</label>
                        </div>
                        
                        <button type="button" class="btn btn-danger" onclick="sendMassEmail()">Send Emails</button>
                    </form>
                    
                    <div class="progress-container" id="emailProgress">
                        <div class="progress-bar">
                            <div class="progress-fill" id="emailProgressFill">0%</div>
                        </div>
                    </div>
                    
                    <div class="result-box" id="emailResult"></div>
                </div>
            </div>

            <section class="bulk-upload-section">
                <h2 class="bulk-upload-heading">Bulk Upload Center</h2>
                <p class="bulk-upload-subtitle">Import students, professors, and courses via CSV templates for faster onboarding.</p>
                
                <div class="tabs">
                    <button class="tab-btn active" data-upload-tab="students" onclick="switchUploadTab('students')">Upload Students</button>
                    <button class="tab-btn" data-upload-tab="professors" onclick="switchUploadTab('professors')">Upload Professors</button>
                    <button class="tab-btn" data-upload-tab="courses" onclick="switchUploadTab('courses')">Upload Courses</button>
                </div>
                
                <!-- Students Upload Tab -->
                <div id="students" class="tab-content active">
                    <div class="content-card">
                        <h2>Bulk Upload Students</h2>
                        <div class="upload-section">
                            <h3>📊 Upload Student List (CSV)</h3>
                            <p>Upload a CSV file containing student information. Default password will be set to their ID number.</p>
                            
                            <a href="templates/students_template.csv" class="download-template" download>⬇ Download CSV Template</a>
                            
                            <form id="studentUploadForm" enctype="multipart/form-data">
                                <div class="file-input-wrapper">
                                    <label class="file-input-label" for="studentFile">Choose CSV File</label>
                                    <input type="file" id="studentFile" name="csv_file" accept=".csv" onchange="updateFileName('studentFile')">
                                </div>
                                <div class="selected-file" id="studentFileName"></div>
                                <button type="submit" class="btn-upload">Upload Students</button>
                            </form>
                            
                            <div id="studentResult" class="result-box"></div>
                            
                            <details style="margin-top: 20px;">
                                <summary style="cursor: pointer; font-weight: 600; color: #555;">CSV Format Requirements</summary>
                                <table class="format-table">
                                    <thead>
                                        <tr>
                                            <th>Column</th>
                                            <th>Description</th>
                                            <th>Example</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>id_number</td><td>Student ID (numbers only)</td><td>12012345</td></tr>
                                        <tr><td>first_name</td><td>First name</td><td>Juan</td></tr>
                                        <tr><td>middle_name</td><td>Middle name (optional)</td><td>Santos</td></tr>
                                        <tr><td>last_name</td><td>Last name</td><td>Dela Cruz</td></tr>
                                        <tr><td>college</td><td>College name</td><td>Gokongwei College of Engineering</td></tr>
                                        <tr><td>department</td><td>Department name</td><td>GCOE-ECEE</td></tr>
                                        <tr><td>program</td><td>Program name</td><td>BS Computer Engineering</td></tr>
                                        <tr><td>specialization</td><td>Specialization (optional)</td><td>N/A</td></tr>
                                        <tr><td>phone_number</td><td>Contact number</td><td>+63 917 123 4567</td></tr>
                                        <tr><td>email</td><td>Email address</td><td>juan_delacruz@dlsu.edu.ph</td></tr>
                                        <tr><td>parent_guardian_name</td><td>Parent/Guardian name</td><td>Maria Dela Cruz</td></tr>
                                        <tr><td>parent_guardian_number</td><td>Parent/Guardian contact</td><td>+63 918 765 4321</td></tr>
                                    </tbody>
                                </table>
                            </details>
                        </div>
                    </div>
                </div>
                
                <!-- Professors Upload Tab -->
                <div id="professors" class="tab-content">
                    <div class="content-card">
                        <h2>Bulk Upload Professors</h2>
                        <div class="upload-section">
                            <h3>📊 Upload Professor List (CSV)</h3>
                            <p>Upload a CSV file containing professor information. Default password will be set to their ID number.</p>
                            
                            <a href="templates/professors_template.csv" class="download-template" download>⬇ Download CSV Template</a>
                            
                            <form id="professorUploadForm" enctype="multipart/form-data">
                                <div class="file-input-wrapper">
                                    <label class="file-input-label" for="professorFile">Choose CSV File</label>
                                    <input type="file" id="professorFile" name="csv_file" accept=".csv" onchange="updateFileName('professorFile')">
                                </div>
                                <div class="selected-file" id="professorFileName"></div>
                                <button type="submit" class="btn-upload">Upload Professors</button>
                            </form>
                            
                            <div id="professorResult" class="result-box"></div>
                            
                            <details style="margin-top: 20px;">
                                <summary style="cursor: pointer; font-weight: 600; color: #555;">CSV Format Requirements</summary>
                                <table class="format-table">
                                    <thead>
                                        <tr>
                                            <th>Column</th>
                                            <th>Description</th>
                                            <th>Example</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>id_number</td><td>Professor ID (numbers only)</td><td>10012345</td></tr>
                                        <tr><td>first_name</td><td>First name</td><td>Maria</td></tr>
                                        <tr><td>middle_name</td><td>Middle name (optional)</td><td>Santos</td></tr>
                                        <tr><td>last_name</td><td>Last name</td><td>Garcia</td></tr>
                                        <tr><td>department</td><td>Department name</td><td>GCOE-ECEE</td></tr>
                                        <tr><td>email</td><td>Email address</td><td>maria.garcia@dlsu.edu.ph</td></tr>
                                    </tbody>
                                </table>
                            </details>
                        </div>
                    </div>
                </div>
                
                <!-- Courses Upload Tab -->
                <div id="courses" class="tab-content">
                    <div class="content-card">
                        <h2>Bulk Upload Courses</h2>
                        <div class="upload-section">
                            <h3>📊 Upload Course Catalog (CSV)</h3>
                            <p>Upload a CSV file containing course information for the catalog.</p>
                            
                            <a href="templates/courses_template.csv" class="download-template" download>⬇ Download CSV Template</a>
                            
                            <form id="courseUploadForm" enctype="multipart/form-data">
                                <div class="file-input-wrapper">
                                    <label class="file-input-label" for="courseFile">Choose CSV File</label>
                                    <input type="file" id="courseFile" name="csv_file" accept=".csv" onchange="updateFileName('courseFile')">
                                </div>
                                <div class="selected-file" id="courseFileName"></div>
                                <button type="submit" class="btn-upload">Upload Courses</button>
                            </form>
                            
                            <div id="courseResult" class="result-box"></div>
                            
                            <details style="margin-top: 20px;">
                                <summary style="cursor: pointer; font-weight: 600; color: #555;">CSV Format Requirements</summary>
                                <table class="format-table">
                                    <thead>
                                        <tr>
                                            <th>Column</th>
                                            <th>Description</th>
                                            <th>Example</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>course_code</td><td>Unique course code</td><td>CSSWENG</td></tr>
                                        <tr><td>course_name</td><td>Full course name</td><td>Software Engineering</td></tr>
                                        <tr><td>units</td><td>Number of units</td><td>3</td></tr>
                                        <tr><td>program</td><td>Program name</td><td>BS Computer Engineering</td></tr>
                                        <tr><td>term</td><td>Term (Term 1 to Term 12)</td><td>Term 2</td></tr>
                                        <tr><td>course_type</td><td>Type: major, minor, elective, general_education</td><td>major</td></tr>
                                        <tr><td>prerequisites</td><td>Format: COURSECODE(TYPE) with TYPE as H/S/C, separated by commas</td><td>FNDMATH(H),PROLOGI(S)</td></tr>
                                    </tbody>
                                </table>
                                <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 5px; font-size: 13px;">
                                    <strong>Prerequisite Types:</strong><br>
                                    • <strong>H</strong> = Hard-Prerequisite<br>
                                    • <strong>S</strong> = Soft-Prerequisite<br>
                                    • <strong>C</strong> = Co-requisite<br>
                                    <strong>Example:</strong> "FNDMATH(H),PROLOGI(S)" or "PROLOGI(C)"
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Load advisers on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadAdvisers();
        });

        // Show/hide conditional fields
        document.getElementById('clearanceTarget')?.addEventListener('change', function() {
            document.getElementById('clearanceProgramGroup').style.display = 
                this.value === 'program' ? 'block' : 'none';
            document.getElementById('clearanceAdviserGroup').style.display = 
                this.value === 'adviser' ? 'block' : 'none';
            document.getElementById('clearanceListGroup').style.display = 
                this.value === 'list' ? 'block' : 'none';
        });

        document.getElementById('emailRecipients')?.addEventListener('change', function() {
            document.getElementById('emailProgramGroup').style.display = 
                this.value === 'program' ? 'block' : 'none';
        });

        function loadAdvisers() {
            fetch('admin_bulk_operations_api.php?action=get_advisers')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('clearanceAdviser');
                        select.innerHTML = '<option value="">Select adviser...</option>';
                        data.advisers.forEach(adviser => {
                            select.innerHTML += `<option value="${adviser.id}">${adviser.name}</option>`;
                        });
                    }
                });
        }

        function performClearance() {
            const action = document.getElementById('clearanceAction').value;
            const target = document.getElementById('clearanceTarget').value;
            const confirmationChecked = document.getElementById('clearanceConfirm').checked;
            
            if (!action || !target || !confirmationChecked) {
                showAlert('clearanceAlert', 'Please complete all required fields', 'danger');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'bulk_clearance');
            formData.append('clearance_action', action);
            formData.append('target', target);
            
            if (target === 'program') {
                formData.append('program', document.getElementById('clearanceProgram').value);
            } else if (target === 'adviser') {
                formData.append('adviser_id', document.getElementById('clearanceAdviser').value);
            } else if (target === 'list') {
                formData.append('student_list', document.getElementById('clearanceList').value);
            }
            
            if (!window.confirm(`This will ${action} students. Continue?`)) {
                return;
            }
            
            fetch('admin_bulk_operations_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('clearanceAlert', data.message, 'success');
                    showResult('clearanceResult', data.stats);
                } else {
                    showAlert('clearanceAlert', 'Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('clearanceAlert', 'Error performing clearance', 'danger');
            });
        }

        function sendMassEmail() {
            const recipients = document.getElementById('emailRecipients').value;
            const subject = document.getElementById('emailSubject').value;
            const message = document.getElementById('emailMessage').value;
            const preview = document.getElementById('emailPreview').checked ? 1 : 0;
            
            if (!recipients || !subject || !message) {
                showAlert('emailAlert', 'Please fill all required fields', 'danger');
                return;
            }
            
            if (!preview && !confirm('This will send emails to multiple users. Continue?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'send_mass_email');
            formData.append('recipients', recipients);
            formData.append('subject', subject);
            formData.append('message', message);
            formData.append('preview', preview);
            
            if (recipients === 'program') {
                formData.append('program', document.getElementById('emailProgram').value);
            }
            
            showProgress('emailProgress', 'emailProgressFill');
            
            fetch('admin_bulk_operations_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideProgress('emailProgress');
                const alertType = data.success ? 'success' : 'danger';
                showAlert('emailAlert', data.message || 'Unknown response', alertType);
                
                if (data.stats) {
                    showResult('emailResult', data.stats);
                    renderErrorList('emailResult', data.errors);
                }
            })
            .catch(error => {
                hideProgress('emailProgress');
                showAlert('emailAlert', 'Error sending emails', 'danger');
            });
        }

        function showAlert(containerId, message, type) {
            const container = document.getElementById(containerId);
            container.innerHTML = `<div class="alert ${type}">${message}</div>`;
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        function showProgress(containerId, fillId) {
            document.getElementById(containerId).style.display = 'block';
            let progress = 0;
            const interval = setInterval(() => {
                progress += 10;
                document.getElementById(fillId).style.width = progress + '%';
                document.getElementById(fillId).textContent = progress + '%';
                if (progress >= 90) clearInterval(interval);
            }, 200);
        }

        function hideProgress(containerId) {
            document.getElementById(containerId).style.display = 'none';
        }

        function showResult(containerId, stats) {
            const container = document.getElementById(containerId);
            let html = '<div class="result-stats">';
            
            Object.keys(stats).forEach(key => {
                const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                html += `
                    <div class="result-stat">
                        <div class="result-stat-value">${stats[key]}</div>
                        <div class="result-stat-label">${label}</div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
            container.style.display = 'block';
        }

        function renderErrorList(containerId, errors) {
            if (!errors || errors.length === 0) {
                return;
            }
            const container = document.getElementById(containerId);
            if (!container) return;
            
            const list = document.createElement('div');
            list.className = 'error-list';
            list.innerHTML = '<strong>Details:</strong>';
            errors.forEach(err => {
                const item = document.createElement('div');
                item.className = 'error-item';
                item.textContent = err;
                list.appendChild(item);
            });
            container.appendChild(list);
        }

        function switchUploadTab(tabName) {
            document.querySelectorAll('.bulk-upload-section .tab-content').forEach(tab => tab.classList.remove('active'));
            const target = document.getElementById(tabName);
            if (target) {
                target.classList.add('active');
            }
            document.querySelectorAll('.bulk-upload-section .tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.uploadTab === tabName);
            });
        }

        function updateFileName(inputId) {
            const input = document.getElementById(inputId);
            const fileNameDiv = document.getElementById(inputId + 'Name');
            if (!input || !fileNameDiv) return;
            fileNameDiv.textContent = input.files.length > 0 ? '📄 ' + input.files[0].name : '';
        }

        document.getElementById('studentUploadForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'bulk_upload_students');
            const resultDiv = document.getElementById('studentResult');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result-box';
            resultDiv.innerHTML = 'Uploading... Please wait.';
            
            fetch('admin_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.className = 'result-box success';
                    let html = `<strong>✓ Upload Successful!</strong><div class="result-details">`;
                    html += `Total: ${data.total} | Successful: ${data.successful} | Failed: ${data.failed}</div>`;
                    if (data.errors.length > 0) {
                        html += `<div class="error-list"><strong>Errors:</strong>`;
                        data.errors.forEach(err => {
                            html += `<div class="error-item">${err}</div>`;
                        });
                        html += `</div>`;
                    }
                    resultDiv.innerHTML = html;
                    this.reset();
                    document.getElementById('studentFileName').textContent = '';
                } else {
                    resultDiv.className = 'result-box error';
                    resultDiv.innerHTML = `<strong>✗ Upload Failed</strong><div class="result-details">${data.message}</div>`;
                }
            })
            .catch(error => {
                resultDiv.className = 'result-box error';
                resultDiv.innerHTML = `<strong>✗ Error</strong><div class="result-details">${error.message}</div>`;
            });
        });

        document.getElementById('professorUploadForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'bulk_upload_professors');
            const resultDiv = document.getElementById('professorResult');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result-box';
            resultDiv.innerHTML = 'Uploading... Please wait.';
            
            fetch('admin_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.className = 'result-box success';
                    let html = `<strong>✓ Upload Successful!</strong><div class="result-details">`;
                    html += `Total: ${data.total} | Successful: ${data.successful} | Failed: ${data.failed}</div>`;
                    if (data.errors.length > 0) {
                        html += `<div class="error-list"><strong>Errors:</strong>`;
                        data.errors.forEach(err => {
                            html += `<div class="error-item">${err}</div>`;
                        });
                        html += `</div>`;
                    }
                    resultDiv.innerHTML = html;
                    this.reset();
                    document.getElementById('professorFileName').textContent = '';
                } else {
                    resultDiv.className = 'result-box error';
                    resultDiv.innerHTML = `<strong>✗ Upload Failed</strong><div class="result-details">${data.message}</div>`;
                }
            })
            .catch(error => {
                resultDiv.className = 'result-box error';
                resultDiv.innerHTML = `<strong>✗ Error</strong><div class="result-details">${error.message}</div>`;
            });
        });

        document.getElementById('courseUploadForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'bulk_upload_courses');
            const resultDiv = document.getElementById('courseResult');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result-box';
            resultDiv.innerHTML = 'Uploading... Please wait.';
            
            fetch('admin_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.className = 'result-box success';
                    let html = `<strong>✓ Upload Successful!</strong><div class="result-details">`;
                    html += `Total: ${data.total} | Successful: ${data.successful} | Failed: ${data.failed}</div>`;
                    if (data.errors.length > 0) {
                        html += `<div class="error-list"><strong>Errors:</strong>`;
                        data.errors.forEach(err => {
                            html += `<div class="error-item">${err}</div>`;
                        });
                        html += `</div>`;
                    }
                    resultDiv.innerHTML = html;
                    this.reset();
                    document.getElementById('courseFileName').textContent = '';
                } else {
                    resultDiv.className = 'result-box error';
                    resultDiv.innerHTML = `<strong>✗ Upload Failed</strong><div class="result-details">${data.message}</div>`;
                }
            })
            .catch(error => {
                resultDiv.className = 'result-box error';
                resultDiv.innerHTML = `<strong>✗ Error</strong><div class="result-details">${error.message}</div>`;
            });
        });
    </script>
</body>
</html>
