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
    <title>Submit Concern</title>
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
        
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 20px; color: #1976D2; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; }
        .form-group textarea { min-height: 150px; resize: vertical; font-family: inherit; }
        .form-group .help-text { font-size: 13px; color: #666; margin-top: 5px; }
        
        .btn { padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #1976D2; color: white; }
        .btn-primary:hover { background: #1565C0; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert.danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .alert.info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        
        .concern-item { padding: 20px; background: #f8f9fa; border-left: 4px solid #1976D2; border-radius: 5px; margin-bottom: 15px; }
        .concern-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px; }
        .concern-term { font-weight: 600; color: #1976D2; }
        .concern-date { font-size: 13px; color: #666; }
        .concern-text { color: #333; line-height: 1.6; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .loading { text-align: center; padding: 40px; color: #666; }
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
                <h1>Submit Concern</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="content-card">
                <h3>New Concern</h3>
                
                <div id="concernAlert"></div>
                
                <form id="concernForm">
                    <div class="form-group">
                        <label>Term / Period *</label>
                        <select id="term" required>
                            <option value="">Select term...</option>
                            <option value="Term 1 - 2024/2025">Term 1 - 2024/2025</option>
                            <option value="Term 2 - 2024/2025">Term 2 - 2024/2025</option>
                            <option value="Term 3 - 2024/2025">Term 3 - 2024/2025</option>
                            <option value="General">General</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Your Concern *</label>
                        <textarea id="concern" placeholder="Please describe your concern or question in detail..." required></textarea>
                        <div class="help-text">Your adviser will review and respond to your concern</div>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">Submit Concern</button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">Clear</button>
                    </div>
                </form>
            </div>
            
            <div class="content-card">
                <h3>My Previous Concerns</h3>
                <div id="concernsContent" class="loading">Loading concerns...</div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadConcerns();
            
            document.getElementById('concernForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitConcern();
            });
        });

        function submitConcern() {
            const term = document.getElementById('term').value;
            const concern = document.getElementById('concern').value;
            
            if (!term || !concern) {
                showAlert('Please fill in all fields', 'danger');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'submit_concern');
            formData.append('term', term);
            formData.append('concern', concern);
            
            fetch('student_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Concern submitted successfully! Your adviser will review it.', 'success');
                    resetForm();
                    loadConcerns();
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Error submitting concern', 'danger');
            });
        }

        function resetForm() {
            document.getElementById('concernForm').reset();
        }

        function showAlert(message, type) {
            const alertDiv = document.getElementById('concernAlert');
            alertDiv.innerHTML = `<div class="alert ${type}">${message}</div>`;
            
            setTimeout(() => {
                alertDiv.innerHTML = '';
            }, 5000);
        }

        function loadConcerns() {
            fetch('student_api.php?action=get_my_concerns')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderConcerns(data.concerns);
                    } else {
                        document.getElementById('concernsContent').innerHTML = '<div class="empty-state">No concerns submitted yet</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('concernsContent').innerHTML = '<div class="empty-state">Error loading concerns</div>';
                });
        }

        function renderConcerns(concerns) {
            const container = document.getElementById('concernsContent');
            
            if (concerns.length === 0) {
                container.innerHTML = '<div class="empty-state">No concerns submitted yet</div>';
                return;
            }
            
            let html = '';
            concerns.forEach(concern => {
                html += `
                    <div class="concern-item">
                        <div class="concern-header">
                            <div class="concern-term">${concern.term}</div>
                            <div class="concern-date">${formatDate(concern.submission_date)}</div>
                        </div>
                        <div class="concern-text">${concern.concern}</div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }
    </script>
</body>
</html>