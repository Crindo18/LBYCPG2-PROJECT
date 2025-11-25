<?php
require_once 'auth_check.php';
requireStudent();

require_once 'config.php';

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
    <title>Document Downloads</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #00A36C; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #008558; }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.9; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #7FE5B8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #00A36C; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 20px; color: #00A36C; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .document-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        
        .document-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .document-card:hover { transform: translateY(-5px); }
        .document-card.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .document-card.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .document-card.blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        
        .document-icon { font-size: 48px; margin-bottom: 15px; }
        .document-title { font-size: 22px; font-weight: 600; margin-bottom: 10px; }
        .document-description { font-size: 14px; opacity: 0.9; margin-bottom: 20px; line-height: 1.5; }
        
        .download-btn { padding: 12px 24px; background: white; color: #333; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; transition: all 0.3s; width: 100%; }
        .download-btn:hover { transform: scale(1.05); }
        .download-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .alert.warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        
        .status-badge { display: inline-block; padding: 5px 12px; background: rgba(255,255,255,0.2); border-radius: 15px; font-size: 13px; margin-top: 10px; }
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
                <a href="student_advising_form.php" class="menu-item">Academic Advising Form</a>
                <a href="student_meeting.php" class="menu-item">Meeting Schedule</a>
                <a href="student_documents.php" class="menu-item active">Documents</a>
                <a href="student_concerns.php" class="menu-item">Submit Concern</a>
                <a href="student_profile.php" class="menu-item">My Profile</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Document Downloads</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="content-card">
                <h3>Available Documents</h3>
                
                <div class="alert info">
                    <strong>Note:</strong> All documents are generated in real-time based on your current academic records.
                </div>
                
                <div class="document-grid">
                    <!-- Academic Booklet -->
                    <div class="document-card">
                        <div class="document-icon">📚</div>
                        <div class="document-title">Academic Booklet</div>
                        <div class="document-description">
                            Complete record of all your courses, grades, and academic performance by term.
                        </div>
                        <button class="download-btn" onclick="downloadDocument('booklet')">
                            Download PDF
                        </button>
                        <div class="status-badge" id="bookletStatus">Ready</div>
                    </div>
                    
                    <!-- GPA Summary -->
                    <div class="document-card blue">
                        <div class="document-icon">📊</div>
                        <div class="document-title">GPA Summary</div>
                        <div class="document-description">
                            Complete breakdown of your GPA by term, including cumulative GPA and academic standing.
                        </div>
                        <button class="download-btn" onclick="downloadDocument('gpa')">
                            Download PDF
                        </button>
                        <div class="status-badge" id="gpaStatus">Ready</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        window.onload = function() {
            checkDocumentStatus();
        };

        function checkDocumentStatus() {
            fetch('student_documents_api.php?action=check_status')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update study plan status
                        const planBtn = document.getElementById('studyPlanBtn');
                        const planStatus = document.getElementById('planStatus');
                        if (data.has_study_plan) {
                            planStatus.textContent = 'Available';
                            planBtn.disabled = false;
                        } else {
                            planStatus.textContent = 'No plans submitted';
                            planBtn.disabled = true;
                        }
                        
                        // Update clearance status
                        const clearanceBtn = document.getElementById('clearanceBtn');
                        const clearanceStatus = document.getElementById('clearanceStatus');
                        if (data.is_cleared) {
                            clearanceStatus.textContent = 'Cleared ✓';
                            clearanceBtn.disabled = false;
                        } else {
                            clearanceStatus.textContent = 'Not yet cleared';
                            clearanceBtn.disabled = true;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking status:', error);
                });
        }

        function downloadDocument(type) {
            // Update button to show loading state
            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = 'Generating...';
            btn.disabled = true;
            
            // Create download link
            const link = document.createElement('a');
            link.href = `student_documents_api.php?action=download&type=${type}`;
            link.download = `${type}_document.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Reset button after 2 seconds
            setTimeout(() => {
                btn.textContent = originalText;
                if (type !== 'clearance' && type !== 'studyplan') {
                    btn.disabled = false;
                } else {
                    checkDocumentStatus();
                }
            }, 2000);
        }
    </script>
</body>
</html>
