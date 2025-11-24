<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Upload - Admin Portal</title>
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
        .upload-section { margin-bottom: 30px; padding: 25px; background: #f8f9fa; border-radius: 8px; border: 2px dashed #ddd; }
        .upload-section h3 { font-size: 18px; color: #333; margin-bottom: 15px; }
        .upload-section p { color: #666; margin-bottom: 15px; font-size: 14px; }
        .file-input-wrapper { position: relative; overflow: hidden; display: inline-block; }
        .file-input-wrapper input[type=file] { position: absolute; left: -9999px; }
        .file-input-label { padding: 10px 20px; background: #6a1b9a; color: white; border-radius: 5px; cursor: pointer; display: inline-block; font-size: 14px; }
        .file-input-label:hover { background: #8e24aa; }
        .selected-file { margin: 10px 0; color: #555; font-size: 14px; }
        .btn-upload { padding: 10px 24px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; margin-top: 10px; }
        .btn-upload:hover { background: #45a049; }
        .btn-upload:disabled { background: #ccc; cursor: not-allowed; }
        .download-template { color: #2196F3; text-decoration: none; font-size: 14px; margin-left: 15px; }
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
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #6a1b9a; border-bottom-color: #6a1b9a; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
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
                <a href="admin_advisingassignment.php" class="menu-item">Advising Assignments</a>
                <a href="admin_reports.php" class="menu-item">System Reports</a>
                <a href="admin_bulk_operations.php" class="menu-item">Bulk Operations</a>
                <a href="admin_bulk_upload.php" class="menu-item">Bulk Operations</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Bulk Upload</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('students')">Upload Students</button>
                <button class="tab-btn" onclick="switchTab('professors')">Upload Professors</button>
                <button class="tab-btn" onclick="switchTab('courses')">Upload Courses</button>
            </div>
            
            <!-- Students Upload Tab -->
            <div id="students" class="tab-content active">
                <div class="content-card">
                    <h2>Bulk Upload Students</h2>
                    <div class="upload-section">
                        <h3>📊 Upload Student List (CSV)</h3>
                        <p>Upload a CSV file containing student information. Default password will be set to their ID number.</p>
                        
                        <a href="templates/students_template.csv" class="download-template" download>⬇ Download CSV Template</a>
                        
                        <div style="margin-top: 20px;">
                            <form id="studentUploadForm" enctype="multipart/form-data">
                                <div class="file-input-wrapper">
                                    <label class="file-input-label" for="studentFile">Choose CSV File</label>
                                    <input type="file" id="studentFile" name="csv_file" accept=".csv" onchange="updateFileName('studentFile')">
                                </div>
                                <div class="selected-file" id="studentFileName"></div>
                                <button type="submit" class="btn-upload">Upload Students</button>
                            </form>
                        </div>
                        
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
                        
                        <div style="margin-top: 20px;">
                            <form id="professorUploadForm" enctype="multipart/form-data">
                                <div class="file-input-wrapper">
                                    <label class="file-input-label" for="professorFile">Choose CSV File</label>
                                    <input type="file" id="professorFile" name="csv_file" accept=".csv" onchange="updateFileName('professorFile')">
                                </div>
                                <div class="selected-file" id="professorFileName"></div>
                                <button type="submit" class="btn-upload">Upload Professors</button>
                            </form>
                        </div>
                        
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
                        
                        <div style="margin-top: 20px;">
                            <form id="courseUploadForm" enctype="multipart/form-data">
                                <div class="file-input-wrapper">
                                    <label class="file-input-label" for="courseFile">Choose CSV File</label>
                                    <input type="file" id="courseFile" name="csv_file" accept=".csv" onchange="updateFileName('courseFile')">
                                </div>
                                <div class="selected-file" id="courseFileName"></div>
                                <button type="submit" class="btn-upload">Upload Courses</button>
                            </form>
                        </div>
                        
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
                                    <tr><td>prerequisites</td><td>Format: COURSECODE(TYPE) where TYPE is H/S/C. Multiple separated by comma</td><td>FNDMATH(H),PROLOGI(S)</td></tr>
                                </tbody>
                            </table>
                            <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 5px; font-size: 13px;">
                                <strong>Prerequisite Types:</strong><br>
                                • <strong>H</strong> = Hard-Prerequisite (must pass to take course)<br>
                                • <strong>S</strong> = Soft-Prerequisite (must take, pass or fail)<br>
                                • <strong>C</strong> = Co-requisite (take together in same term)<br>
                                <strong>Example:</strong> "FNDMATH(H),PROLOGI(S)" or "PROLOGI(C)" for co-requisites
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function updateFileName(inputId) {
            const input = document.getElementById(inputId);
            const fileNameDiv = document.getElementById(inputId + 'Name');
            
            if (input.files.length > 0) {
                fileNameDiv.textContent = '📄 ' + input.files[0].name;
            } else {
                fileNameDiv.textContent = '';
            }
        }

        // Student Upload Handler
        document.getElementById('studentUploadForm').addEventListener('submit', function(e) {
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
                    
                    // Clear form
                    document.getElementById('studentUploadForm').reset();
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

        // Professor Upload Handler
        document.getElementById('professorUploadForm').addEventListener('submit', function(e) {
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
                    
                    document.getElementById('professorUploadForm').reset();
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

        // Course Upload Handler
        document.getElementById('courseUploadForm').addEventListener('submit', function(e) {
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
                    
                    document.getElementById('courseUploadForm').reset();
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