<?php
require_once 'config.php';
requireUserType('student');

$student_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle concern submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_concern'])) {
    $term = trim($_POST['term']);
    $concern = trim($_POST['concern']);
    
    if (empty($term) || empty($concern)) {
        $message = 'Please fill in all required fields';
        $message_type = 'error';
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO student_concerns (student_id, term, concern)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$student_id, $term, $concern]);
            
            $message = 'Your concern has been submitted successfully. The admin department will review it.';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error submitting concern. Please try again.';
            $message_type = 'error';
        }
    }
}

// Get previous submissions
$stmt = $conn->prepare("
    SELECT * FROM student_concerns
    WHERE student_id = ?
    ORDER BY submission_date DESC
");
$stmt->execute([$student_id]);
$previous_concerns = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Concern - Academic Advising System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: #1e3c72;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            background: #152a52;
        }
        
        .sidebar-header h2 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            font-size: 13px;
            opacity: 0.8;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .menu-item {
            padding: 15px 25px;
            color: white;
            text-decoration: none;
            display: block;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover,
        .menu-item.active {
            background: rgba(255,255,255,0.1);
            border-left-color: #4CAF50;
        }
        
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 30px;
            width: calc(100% - 260px);
        }
        
        .top-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-bar h1 {
            font-size: 28px;
            color: #1e3c72;
        }
        
        .logout-btn {
            padding: 8px 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }
        
        .content-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .content-card h2 {
            font-size: 22px;
            color: #1e3c72;
            margin-bottom: 20px;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-box {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #2196F3;
        }
        
        .info-box p {
            color: #1565c0;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .info-box p:last-child {
            margin-bottom: 0;
        }
        
        .confidential-notice {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        
        .confidential-notice p {
            color: #856404;
            font-size: 13px;
            margin: 0;
        }
        
        .confidential-notice strong {
            display: block;
            margin-bottom: 5px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 15px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1e3c72;
            box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1);
        }
        
        .form-group textarea {
            min-height: 200px;
            resize: vertical;
        }
        
        .form-hint {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        .btn-submit {
            padding: 15px 40px;
            background: #1e3c72;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .btn-submit:hover:not(:disabled) {
            background: #2a5298;
        }
        
        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .concern-card {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .concern-header {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .concern-header strong {
            color: #333;
        }
        
        .concern-header span {
            color: #666;
            font-size: 13px;
        }
        
        .concern-body {
            padding: 15px;
        }
        
        .concern-body p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Student Portal</h2>
                <p>Academic Advising System</p>
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
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="content-card">
                <h2>Student Concern Submission</h2>
                
                <div class="info-box">
                    <p><strong>About This Form:</strong></p>
                    <p>Are there concerns that you want to bring up in relation to the current term?</p>
                    <p>Rest assured that all declared information will be kept <strong>confidential</strong>.</p>
                    <p>You may opt to omit specific name/s or course name/s as needed.</p>
                </div>
                
                <div class="confidential-notice">
                    <p><strong>🔒 Confidentiality Notice</strong></p>
                    <p>This concern will only be visible to the admin department. Your academic adviser will not have access to this information unless you explicitly mention wanting to discuss it with them.</p>
                </div>
                
                <form method="POST" id="concernForm">
                    <div class="form-group">
                        <label for="term">Academic Term *</label>
                        <input type="text" name="term" id="term" placeholder="e.g., AY 2024-2025 Term 1" required>
                        <div class="form-hint">Please specify the academic year and term this concern relates to</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="concern">Your Concern *</label>
                        <textarea name="concern" id="concern" placeholder="Please describe your concern in detail. You may omit specific names or course codes if you prefer to keep certain information anonymous." required></textarea>
                        <div class="form-hint">Be as detailed as possible to help us understand and address your concern effectively. Remember, this is confidential.</div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <button type="submit" name="submit_concern" class="btn-submit" id="submitBtn" disabled>Submit Concern</button>
                        <span style="color: #666; font-size: 14px;">* Required fields</span>
                    </div>
                </form>
            </div>
            
            <div class="content-card">
                <h2>Your Previous Submissions</h2>
                <p style="color: #666; margin-bottom: 20px;">View concerns you have previously submitted.</p>
                
                <?php if (!empty($previous_concerns)): ?>
                    <?php foreach ($previous_concerns as $concern): ?>
                    <div class="concern-card">
                        <div class="concern-header">
                            <strong><?php echo htmlspecialchars($concern['term']); ?></strong>
                            <span>Submitted: <?php echo date('F j, Y', strtotime($concern['submission_date'])); ?></span>
                        </div>
                        <div class="concern-body">
                            <p><?php echo nl2br(htmlspecialchars($concern['concern'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #999; font-style: italic;">No previous submissions found.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        const form = document.getElementById('concernForm');
        const termInput = document.getElementById('term');
        const concernInput = document.getElementById('concern');
        const submitBtn = document.getElementById('submitBtn');
        
        function checkFormValidity() {
            const isValid = termInput.value.trim() !== '' && concernInput.value.trim() !== '';
            submitBtn.disabled = !isValid;
        }
        
        termInput.addEventListener('input', checkFormValidity);
        concernInput.addEventListener('input', checkFormValidity);
    </script>
</body>
</html>