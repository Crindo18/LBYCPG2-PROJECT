<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT s.*, CONCAT(p.first_name, ' ', p.last_name) as adviser_name
    FROM students s
    LEFT JOIN professors p ON p.id = s.advisor_id
    WHERE s.id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
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
        
        .profile-header { background: linear-gradient(135deg, #1976D2 0%, #2196F3 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3); }
        .profile-header h2 { font-size: 32px; margin-bottom: 5px; }
        .profile-header p { font-size: 16px; opacity: 0.9; }
        
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 20px; color: #1976D2; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .info-item { padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .info-item label { font-size: 13px; color: #666; display: block; margin-bottom: 5px; }
        .info-item value { font-size: 16px; font-weight: 600; color: #333; }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.warning { background: #fff3cd; color: #856404; }
        .badge.danger { background: #f8d7da; color: #721c24; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .alert.danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .alert.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Student Portal</h2>
                <p><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
            </div>
            <nav class="sidebar-menu">
                <a href="student_dashboard.php" class="menu-item">Dashboard</a>
                <a href="student_booklet.php" class="menu-item">My Booklet</a>
                <a href="student_advising_form.php" class="menu-item">Academic Advising Form</a>
                <a href="student_meeting.php" class="menu-item">Meeting Schedule</a>
                <a href="student_documents.php" class="menu-item">Documents</a>
                <a href="student_concerns.php" class="menu-item">Submit Concern</a>
                <a href="student_profile.php" class="menu-item active">My Profile</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>My Profile</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="profile-header">
                <h2><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?></h2>
                <p>ID: <?php echo htmlspecialchars($student['id_number']); ?> • <?php echo htmlspecialchars($student['program']); ?></p>
            </div>
            
            <?php if ($student['accumulated_failed_units'] >= 25): ?>
                <div class="alert danger">
                    <strong>⚠ CRITICAL WARNING:</strong> You have <?php echo $student['accumulated_failed_units']; ?> failed units. Please contact your adviser immediately.
                </div>
            <?php elseif ($student['accumulated_failed_units'] >= 15): ?>
                <div class="alert warning">
                    <strong>⚠ Warning:</strong> You have <?php echo $student['accumulated_failed_units']; ?> failed units. Please consult with your adviser.
                </div>
            <?php endif; ?>
            
            <?php if ($student['advising_cleared']): ?>
                <div class="alert success">
                    <strong>✓ Cleared:</strong> You are cleared for enrollment!
                </div>
            <?php endif; ?>
            
            <div class="content-card">
                <h3>Personal Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Full Name</label>
                        <value><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?></value>
                    </div>
                    <div class="info-item">
                        <label>Student ID</label>
                        <value><?php echo htmlspecialchars($student['id_number']); ?></value>
                    </div>
                    <div class="info-item">
                        <label>Email</label>
                        <value><?php echo htmlspecialchars($student['email']); ?></value>
                    </div>
                    <div class="info-item">
                        <label>Phone</label>
                        <value><?php echo htmlspecialchars($student['phone_number']); ?></value>
                    </div>
                </div>
            </div>
            
            <div class="content-card">
                <h3>Academic Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>College</label>
                        <value><?php echo htmlspecialchars($student['college']); ?></value>
                    </div>
                    <div class="info-item">
                        <label>Department</label>
                        <value><?php echo htmlspecialchars($student['department']); ?></value>
                    </div>
                    <div class="info-item">
                        <label>Program</label>
                        <value><?php echo htmlspecialchars($student['program']); ?></value>
                    </div>
                    <div class="info-item">
                        <label>Specialization</label>
                        <value><?php echo htmlspecialchars($student['specialization'] ?: 'N/A'); ?></value>
                    </div>
                    <div class="info-item">
                        <label>Accumulated Failed Units</label>
                        <value><?php echo $student['accumulated_failed_units']; ?> / 30</value>
                    </div>
                    <div class="info-item">
                        <label>Advising Status</label>
                        <value>
                            <?php if ($student['advising_cleared']): ?>
                                <span class="badge success">✓ Cleared</span>
                            <?php else: ?>
                                <span class="badge warning">Pending</span>
                            <?php endif; ?>
                        </value>
                    </div>
                </div>
            </div>
            
            <div class="content-card">
                <h3>Guardian Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Guardian Name</label>
                        <value><?php echo htmlspecialchars($student['parent_guardian_name']); ?></value>
                    </div>
                    <div class="info-item">
                        <label>Guardian Contact</label>
                        <value><?php echo htmlspecialchars($student['parent_guardian_number']); ?></value>
                    </div>
                </div>
            </div>
            
            <div class="content-card">
                <h3>Adviser Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Adviser Name</label>
                        <value><?php echo htmlspecialchars($student['adviser_name'] ?: 'Not assigned yet'); ?></value>
                    </div>
                    <?php if ($student['adviser_name']): ?>
                        <div class="info-item">
                            <label>Department</label>
                            <value>DECE</value>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>