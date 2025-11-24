<?php
require_once 'config.php';
requireUserType('student');

$student_id = $_SESSION['user_id'];

// Get student information
$stmt = $conn->prepare("SELECT program, specialization FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Get program profile with checklist file
$stmt = $conn->prepare("
    SELECT * FROM program_profiles 
    WHERE program_name = ? AND (specialization = ? OR specialization IS NULL)
    LIMIT 1
");
$stmt->execute([$student['program'], $student['specialization']]);
$program = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Checklist - Academic Advising System</title>
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
        }
        
        .content-card h2 {
            font-size: 22px;
            color: #1e3c72;
            margin-bottom: 20px;
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
        
        .btn-download {
            padding: 12px 24px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        
        .btn-download:hover {
            background: #45a049;
        }
        
        .btn-view {
            padding: 12px 24px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-view:hover {
            background: #1976D2;
        }
        
        .no-file-message {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin-top: 20px;
        }
        
        .no-file-message p {
            color: #856404;
            font-size: 14px;
            line-height: 1.6;
        }
        
        iframe {
            width: 100%;
            height: 800px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-top: 20px;
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
                <h1>Program Checklist</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="content-card">
                <h2><?php echo htmlspecialchars($student['program']); ?><?php echo $student['specialization'] ? ' - ' . htmlspecialchars($student['specialization']) : ''; ?></h2>
                
                <div class="info-box">
                    <p><strong>About This Checklist:</strong></p>
                    <p>This is your official program checklist containing all required courses, prerequisites, and program requirements. Use this as your guide for planning your academic journey.</p>
                </div>
                
                <?php if ($program && $program['checklist_file']): ?>
                    <div style="margin: 20px 0;">
                        <a href="../uploads/<?php echo htmlspecialchars($program['checklist_file']); ?>" class="btn-view" target="_blank">📄 View Checklist</a>
                        <a href="../uploads/<?php echo htmlspecialchars($program['checklist_file']); ?>" class="btn-download" download>⬇ Download Checklist</a>
                    </div>
                    
                    <?php
                    $file_ext = strtolower(pathinfo($program['checklist_file'], PATHINFO_EXTENSION));
                    if ($file_ext === 'pdf'):
                    ?>
                        <iframe src="../uploads/<?php echo htmlspecialchars($program['checklist_file']); ?>"></iframe>
                    <?php elseif (in_array($file_ext, ['xlsx', 'xls'])): ?>
                        <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 5px; text-align: center;">
                            <p style="color: #666; margin-bottom: 15px;">Excel files cannot be previewed directly. Please download the file to view it.</p>
                            <a href="../uploads/<?php echo htmlspecialchars($program['checklist_file']); ?>" class="btn-download" download>⬇ Download Excel File</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-file-message">
                        <p><strong>⚠ Checklist Not Available</strong></p>
                        <p>The program checklist for your course has not been uploaded yet. Please contact your academic adviser or the admin office for assistance.</p>
                    </div>
                <?php endif; ?>
                
                <?php if ($program && $program['description']): ?>
                    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <h3 style="color: #1e3c72; margin-bottom: 10px;">Program Description</h3>
                        <p style="color: #666; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($program['description'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>