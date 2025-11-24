<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

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
        .sidebar { width: 260px; background: #2c3e50; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #1a252f; }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.9; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #3498db; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #2c3e50; }
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
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Admin Portal</h2>
                <p><?php echo htmlspecialchars($admin_name); ?></p>
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
                <h1>Bulk Operations</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="operations-grid">
                <!-- Bulk Grade Upload -->
                <div class="operation-card">
                    <h3><span class="operation-icon">📊</span> Bulk Grade Upload</h3>
                    <p class="operation-description">
                        Upload multiple student grades at once using a CSV file. Perfect for end-of-term grade submissions.
                    </p>
                    
                    <a href="templates/grades_template.csv" class="template-download" download>📥 Download CSV Template</a>
                    
                    <div id="gradeAlert"></div>
                    
                    <form id="gradeUploadForm">
                        <div class="form-group">
                            <label>Academic Year *</label>
                            <input type="text" id="gradeYear" placeholder="e.g., 2024-2025" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Term *</label>
                            <select id="gradeTerm" required>
                                <option value="">Select term...</option>
                                <option value="1">Term 1</option>
                                <option value="2">Term 2</option>
                                <option value="3">Term 3</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>CSV File *</label>
                            <input type="file" id="gradeFile" accept=".csv" required>
                            <div class="help-text">Format: student_id, course_code, grade, is_failed</div>
                        </div>
                        
                        <button type="button" class="btn btn-primary" onclick="uploadGrades()">Upload Grades</button>
                    </form>
                    
                    <div class="progress-container" id="gradeProgress">
                        <div class="progress-bar">
                            <div class="progress-fill" id="gradeProgressFill">0%</div>
                        </div>
                    </div>
                    
                    <div class="result-box" id="gradeResult"></div>
                </div>
                
                <!-- Bulk GPA Calculation -->
                <div class="operation-card">
                    <h3><span class="operation-icon">🧮</span> Bulk GPA Calculation</h3>
                    <p class="operation-description">
                        Automatically calculate GPA for all students or specific programs. Updates term GPA and cumulative GPA.
                    </p>
                    
                    <div id="gpaAlert"></div>
                    
                    <form id="gpaCalculationForm">
                        <div class="form-group">
                            <label>Academic Year *</label>
                            <input type="text" id="gpaYear" placeholder="e.g., 2024-2025" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Term *</label>
                            <select id="gpaTerm" required>
                                <option value="">Select term...</option>
                                <option value="1">Term 1</option>
                                <option value="2">Term 2</option>
                                <option value="3">Term 3</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Program</label>
                            <select id="gpaProgram">
                                <option value="">All Programs</option>
                                <option value="BS Computer Engineering">BSCpE</option>
                                <option value="BS Electronics and Communications Engineering">BSECE</option>
                                <option value="BS Electrical Engineering">BSEE</option>
                            </select>
                            <div class="help-text">Leave empty to calculate for all programs</div>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="gpaRecalculate">
                            <label for="gpaRecalculate">Recalculate existing GPAs</label>
                        </div>
                        
                        <button type="button" class="btn btn-success" onclick="calculateGPA()">Calculate GPA</button>
                    </form>
                    
                    <div class="progress-container" id="gpaProgress">
                        <div class="progress-bar">
                            <div class="progress-fill" id="gpaProgressFill">0%</div>
                        </div>
                    </div>
                    
                    <div class="result-box" id="gpaResult"></div>
                </div>
                
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
        </main>
    </div>

    <script>
        // Load advisers on page load
        window.onload = function() {
            loadAdvisers();
        };

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

        function uploadGrades() {
            const year = document.getElementById('gradeYear').value;
            const term = document.getElementById('gradeTerm').value;
            const file = document.getElementById('gradeFile').files[0];
            
            if (!year || !term || !file) {
                showAlert('gradeAlert', 'Please fill all required fields', 'danger');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'upload_grades');
            formData.append('academic_year', year);
            formData.append('term', term);
            formData.append('file', file);
            
            showProgress('gradeProgress', 'gradeProgressFill');
            
            fetch('admin_bulk_operations_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideProgress('gradeProgress');
                if (data.success) {
                    showAlert('gradeAlert', data.message, 'success');
                    showResult('gradeResult', data.stats);
                } else {
                    showAlert('gradeAlert', 'Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                hideProgress('gradeProgress');
                showAlert('gradeAlert', 'Error uploading grades', 'danger');
            });
        }

        function calculateGPA() {
            const year = document.getElementById('gpaYear').value;
            const term = document.getElementById('gpaTerm').value;
            const program = document.getElementById('gpaProgram').value;
            const recalculate = document.getElementById('gpaRecalculate').checked ? 1 : 0;
            
            if (!year || !term) {
                showAlert('gpaAlert', 'Please fill all required fields', 'danger');
                return;
            }
            
            if (!confirm('This will calculate GPA for all matching students. Continue?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'calculate_gpa');
            formData.append('academic_year', year);
            formData.append('term', term);
            formData.append('program', program);
            formData.append('recalculate', recalculate);
            
            showProgress('gpaProgress', 'gpaProgressFill');
            
            fetch('admin_bulk_operations_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideProgress('gpaProgress');
                if (data.success) {
                    showAlert('gpaAlert', data.message, 'success');
                    showResult('gpaResult', data.stats);
                } else {
                    showAlert('gpaAlert', 'Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                hideProgress('gpaProgress');
                showAlert('gpaAlert', 'Error calculating GPA', 'danger');
            });
        }

        function performClearance() {
            const action = document.getElementById('clearanceAction').value;
            const target = document.getElementById('clearanceTarget').value;
            const confirm = document.getElementById('clearanceConfirm').checked;
            
            if (!action || !target || !confirm) {
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
            
            if (!confirm(`This will ${action} students. Continue?`)) {
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
                if (data.success) {
                    showAlert('emailAlert', data.message, 'success');
                    showResult('emailResult', data.stats);
                } else {
                    showAlert('emailAlert', 'Error: ' + data.message, 'danger');
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
    </script>
</body>
</html>
