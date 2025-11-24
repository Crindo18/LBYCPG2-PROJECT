<?php
require_once 'config.php';
requireUserType('student');

$student_id = $_SESSION['user_id'];

// Get student information with advisor details
$stmt = $conn->prepare("
    SELECT s.*, 
           CONCAT(p.first_name, ' ', p.last_name) as advisor_name,
           p.id as advisor_id_num
    FROM students s
    LEFT JOIN professors p ON s.advisor_id = p.id
    WHERE s.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - Academic Advising System</title>
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
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .info-item {
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-badge.cleared {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.not-cleared {
            background: #f8d7da;
            color: #721c24;
        }
        
        .change-password-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .change-password-section h3 {
            color: #1e3c72;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .change-password-section p {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .btn-change-password {
            padding: 12px 24px;
            background: #1e3c72;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-change-password:hover {
            background: #2a5298;
        }
        
        .note-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #ffc107;
        }
        
        .note-box p {
            color: #856404;
            font-size: 13px;
            margin: 0;
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
                <h1>Account Information</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="content-card">
                <h2>Personal Information</h2>
                
                <div class="info-grid">
                    <div>
                        <div class="info-item">
                            <div class="info-label">ID Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['id_number']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">First Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['first_name']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Middle Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['middle_name'] ?: 'N/A'); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Last Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['last_name']); ?></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="info-item">
                            <div class="info-label">College</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['college']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Department</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['department']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Program</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['program']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Specialization</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['specialization'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="content-card">
                <h2>Contact Information</h2>
                
                <div class="info-grid">
                    <div>
                        <div class="info-item">
                            <div class="info-label">Student Phone Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['phone_number']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Student Email Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['email']); ?></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="info-item">
                            <div class="info-label">Parent/Guardian Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['parent_guardian_name']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Parent/Guardian Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['parent_guardian_number']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="content-card">
                <h2>Academic Advisor Information</h2>
                
                <div class="info-item">
                    <div class="info-label">Assigned Academic Advisor</div>
                    <div class="info-value">
                        <?php if ($student['advisor_name']): ?>
                            <?php echo htmlspecialchars($student['advisor_name']); ?> 
                            (ID: <?php echo htmlspecialchars($student['advisor_id_num']); ?>)
                        <?php else: ?>
                            Not assigned yet
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Academic Advising Status - Current Term</div>
                    <div class="info-value">
                        <span class="status-badge <?php echo $student['advising_cleared'] ? 'cleared' : 'not-cleared'; ?>">
                            <?php echo $student['advising_cleared'] ? '✓ Cleared' : '✗ Not Cleared'; ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!$student['advising_cleared']): ?>
                <div class="note-box">
                    <p><strong>Note:</strong> To receive academic advising clearance, please complete your Study Plan submission in the Academic Advising section. Your clearance status will be updated once your advisor reviews and approves your submission.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="content-card">
                <h2>Account Security</h2>
                
                <div class="change-password-section">
                    <h3>Change Password</h3>
                    <p>To change your password, you need to provide your current password and set a new secure password.</p>
                    <a href="../change_password.php" class="btn-change-password">Change Password</a>
                </div>
                
                <div class="note-box" style="margin-top: 20px;">
                    <p><strong>Important:</strong> To change other account information (name, contact details, program, etc.), please contact the admin department or visit the Registrar's office. These changes require official documentation and cannot be modified through this portal.</p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>