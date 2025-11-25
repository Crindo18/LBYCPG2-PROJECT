<?php
require_once 'auth_check.php';
requireProfessor();

require_once 'config.php';

$professor_id = $_SESSION['user_id'];
$plan_id = $_GET['id'] ?? 0;

// Get professor name
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM professors WHERE id = ?");
$stmt->bind_param("i", $professor_id);
$stmt->execute();
$professor_name = $stmt->get_result()->fetch_assoc()['name'];

// Get study plan details with ownership verification
$stmt = $conn->prepare("
    SELECT 
        sp.*,
        s.id as student_id,
        s.id_number,
        s.first_name,
        s.last_name,
        s.email,
        s.program,
        s.accumulated_failed_units,
        s.advising_cleared,
        CONCAT(s.first_name, ' ', s.last_name) as student_name
    FROM study_plans sp
    JOIN students s ON s.id = sp.student_id
    WHERE sp.id = ? AND s.advisor_id = ?
");
$stmt->bind_param("ii", $plan_id, $professor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Access denied or study plan not found");
}

$plan = $result->fetch_assoc();

// Get planned subjects
$stmt = $conn->prepare("SELECT * FROM planned_subjects WHERE study_plan_id = ? ORDER BY subject_code");
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$subjects_result = $stmt->get_result();

$subjects = [];
$total_units = 0;
while ($row = $subjects_result->fetch_assoc()) {
    $subjects[] = $row;
    $total_units += $row['units'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Study Plan</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #00A36C; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #00C97F; }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.8; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover { background: rgba(255,255,255,0.1); border-left-color: #7FE5B8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #00A36C; }
        .back-btn { padding: 8px 20px; background: #757575; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; margin-right: 10px; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        .plan-header { background: linear-gradient(135deg, #00A36C 0%, #8e24aa 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0, 163, 108, 0.3); }
        .plan-header h2 { font-size: 28px; margin-bottom: 10px; }
        .plan-header-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
        .plan-header-item { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; }
        .plan-header-item label { font-size: 12px; opacity: 0.9; display: block; margin-bottom: 5px; }
        .plan-header-item value { font-size: 18px; font-weight: 600; }
        
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 22px; color: #00A36C; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .info-item { padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .info-item label { font-size: 13px; color: #666; display: block; margin-bottom: 5px; }
        .info-item value { font-size: 16px; font-weight: 600; color: #333; }
        
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        .summary-row { background: #f0f7ff; font-weight: 600; }
        .summary-row td { border-top: 2px solid #00A36C; }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.warning { background: #fff3cd; color: #856404; }
        .badge.danger { background: #f8d7da; color: #721c24; }
        .badge.info { background: #d1ecf1; color: #0c5460; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .alert.danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .alert.info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        .alert.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; min-height: 120px; resize: vertical; font-family: inherit; }
        
        .action-buttons { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .btn { padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.3s; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        
        .screenshot-section { margin-top: 20px; }
        .screenshot-img { max-width: 100%; height: auto; border: 2px solid #ddd; border-radius: 8px; margin-top: 10px; }
        .no-screenshot { padding: 40px; text-align: center; color: #999; background: #f8f9fa; border: 2px dashed #ddd; border-radius: 8px; }
        
        .feedback-section { background: #e8f5e9; border-left: 4px solid #4CAF50; padding: 20px; border-radius: 5px; margin-top: 20px; }
        .feedback-section h4 { color: #2e7d32; margin-bottom: 10px; }
        .feedback-text { color: #333; line-height: 1.6; }
    </style>

</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Professor Portal</h2>
                <p><?php echo htmlspecialchars($professor_name); ?></p>
            </div>
            <nav class="sidebar-menu">
                <a href="prof_dashboard.php" class="menu-item">Dashboard</a>
                <a href="prof_advisees.php" class="menu-item">My Advisees</a>
                <a href="prof_study_plans.php" class="menu-item">Study Plans</a>
                <a href="prof_acadadvising.php" class="menu-item">Academic Advising</a>
                <a href="prof_concerns.php" class="menu-item">Student Concerns</a>
                <a href="prof_reports.php" class="menu-item">Reports</a>
                <a href="prof_email.php" class="menu-item active">Email System</a>
                <a href="prof_schedule.php" class="menu-item">Schedule</a>
                <a href="prof_grade_approvals.php" class="menu-item">Grade Approvals</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Review Study Plan</h1>
                <div>
                    <a href="prof_study_plans.php" class="back-btn">← Back to Study Plans</a>
                    <a href="login.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <!-- Plan Header -->
            <div class="plan-header">
                <h2><?php echo htmlspecialchars($plan['student_name']); ?></h2>
                <p>ID: <?php echo htmlspecialchars($plan['id_number']); ?> • <?php echo htmlspecialchars($plan['program']); ?></p>
                
                <div class="plan-header-grid">
                    <div class="plan-header-item">
                        <label>Academic Year</label>
                        <value><?php echo htmlspecialchars($plan['academic_year']); ?></value>
                    </div>
                    <div class="plan-header-item">
                        <label>Term</label>
                        <value><?php echo htmlspecialchars($plan['term']); ?></value>
                    </div>
                    <div class="plan-header-item">
                        <label>Submitted</label>
                        <value><?php echo date('M d, Y', strtotime($plan['submission_date'])); ?></value>
                    </div>
                    <div class="plan-header-item">
                        <label>Status</label>
                        <value>
                            <?php if ($plan['cleared']): ?>
                                ✓ Cleared
                            <?php else: ?>
                                Pending Review
                            <?php endif; ?>
                        </value>
                    </div>
                </div>
            </div>
            
            <!-- Failed Units Warning -->
            <?php if ($plan['accumulated_failed_units'] >= 25): ?>
                <div class="alert danger">
                    <strong>⚠ CRITICAL:</strong> This student has <?php echo $plan['accumulated_failed_units']; ?> failed units and is approaching the 30-unit limit.
                </div>
            <?php elseif ($plan['accumulated_failed_units'] >= 15): ?>
                <div class="alert warning">
                    <strong>⚠ Warning:</strong> This student has <?php echo $plan['accumulated_failed_units']; ?> failed units. Monitor closely.
                </div>
            <?php endif; ?>
            
            <!-- Existing Feedback -->
            <?php if ($plan['adviser_feedback']): ?>
                <div class="feedback-section">
                    <h4>Your Previous Feedback:</h4>
                    <div class="feedback-text"><?php echo nl2br(htmlspecialchars($plan['adviser_feedback'])); ?></div>
                </div>
            <?php endif; ?>
            
            <!-- Student Information -->
            <div class="content-card">
                <h3>Student Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Student Name</label>
                        <value><?php echo htmlspecialchars($plan['student_name']); ?></value>
                    </div>
                    <div class="info-item">
                        <label>Student ID</label>
                        <value><?php echo htmlspecialchars($plan['id_number']); ?></value>
                    </div>
                    <div class="info-item">
                        <label>Email</label>
                        <value><?php echo htmlspecialchars($plan['email']); ?></value>
                    </div>
                    <div class="info-item">
                        <label>Program</label>
                        <value><?php echo str_replace('BS ', '', htmlspecialchars($plan['program'])); ?></value>
                    </div>
                    <div class="info-item">
                        <label>Failed Units</label>
                        <value><?php echo $plan['accumulated_failed_units']; ?> / 30</value>
                    </div>
                    <div class="info-item">
                        <label>Advising Cleared</label>
                        <value>
                            <?php if ($plan['advising_cleared']): ?>
                                <span class="badge success">✓ Cleared</span>
                            <?php else: ?>
                                <span class="badge warning">Pending</span>
                            <?php endif; ?>
                        </value>
                    </div>
                </div>
            </div>
            
            <!-- Planned Subjects -->
            <div class="content-card">
                <h3>Planned Subjects (<?php echo count($subjects); ?> courses)</h3>
                
                <?php if (count($subjects) > 0): ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Name</th>
                                    <th>Units</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                        <td><?php echo $subject['units']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="summary-row">
                                    <td colspan="2"><strong>Total Units</strong></td>
                                    <td><strong><?php echo $total_units; ?> units</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert info">No subjects selected in this study plan.</div>
                <?php endif; ?>
            </div>
            
            <!-- Grade Screenshot -->
            <div class="content-card">
                <h3>Grade Screenshot</h3>
                <div class="screenshot-section">
                    <?php if ($plan['grade_screenshot']): ?>
                        <img src="uploads/grades/<?php echo htmlspecialchars($plan['grade_screenshot']); ?>" 
                             alt="Grade Screenshot" 
                             class="screenshot-img"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <div class="no-screenshot" style="display: none;">
                            Screenshot file not found or could not be loaded.
                            <br><small>Filename: <?php echo htmlspecialchars($plan['grade_screenshot']); ?></small>
                        </div>
                    <?php else: ?>
                        <div class="no-screenshot">
                            No grade screenshot uploaded yet.
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($plan['screenshot_reupload_requested']): ?>
                        <div class="alert warning" style="margin-top: 15px;">
                            <strong>Reupload Requested:</strong> <?php echo htmlspecialchars($plan['reupload_reason'] ?: 'Please reupload your grade screenshot.'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Certification Status -->
            <div class="content-card">
                <h3>Additional Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Student Certification</label>
                        <value>
                            <?php if ($plan['certified']): ?>
                                <span class="badge success">✓ Certified</span>
                            <?php else: ?>
                                <span class="badge warning">Not Certified</span>
                            <?php endif; ?>
                        </value>
                    </div>
                    <div class="info-item">
                        <label>Wants Meeting</label>
                        <value>
                            <?php if ($plan['wants_meeting']): ?>
                                <span class="badge info">Yes</span>
                            <?php else: ?>
                                <span class="badge">No</span>
                            <?php endif; ?>
                        </value>
                    </div>
                    <div class="info-item">
                        <label>Plan Status</label>
                        <value>
                            <?php if ($plan['cleared']): ?>
                                <span class="badge success">Cleared</span>
                            <?php else: ?>
                                <span class="badge warning">Pending</span>
                            <?php endif; ?>
                        </value>
                    </div>
                </div>
            </div>
            
            <!-- Review Actions -->
            <div class="content-card">
                <h3>Review & Feedback</h3>
                
                <div id="actionAlert"></div>
                
                <div class="form-group">
                    <label>Feedback / Comments</label>
                    <textarea id="feedback" placeholder="Enter your feedback or comments for the student..."><?php echo htmlspecialchars($plan['adviser_feedback'] ?? ''); ?></textarea>
                </div>
                
                <div class="action-buttons">
                    <?php if (!$plan['cleared']): ?>
                        <button class="btn btn-success" onclick="approvePlan()">
                            ✓ Approve & Clear Student
                        </button>
                        <button class="btn btn-danger" onclick="rejectPlan()">
                            ✗ Reject Plan
                        </button>
                    <?php else: ?>
                        <button class="btn btn-warning" onclick="revokeClearance()">
                            ⚠ Revoke Clearance
                        </button>
                    <?php endif; ?>
                    
                    <button class="btn btn-secondary" onclick="addFeedback()">
                        💬 Add Feedback Only
                    </button>
                    
                    <?php if ($plan['grade_screenshot']): ?>
                        <button class="btn btn-warning" onclick="requestReupload()">
                            📷 Request Screenshot Reupload
                        </button>
                    <?php endif; ?>
                    
                    <a href="prof_student_view.php?id=<?php echo $plan['student_id']; ?>" class="btn btn-secondary">
                        👤 View Student Profile
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        const planId = <?php echo $plan_id; ?>;
        const studentId = <?php echo $plan['student_id']; ?>;

        function approvePlan() {
            const feedback = document.getElementById('feedback').value;
            
            if (!confirm('Approve this study plan and clear the student for enrollment?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'approve_study_plan');
            formData.append('plan_id', planId);
            formData.append('feedback', feedback);
            
            fetch('prof_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Also clear the student
                    clearStudent();
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Error approving plan', 'danger');
            });
        }

        function clearStudent() {
            const formData = new FormData();
            formData.append('action', 'clear_student');
            formData.append('student_id', studentId);
            
            fetch('prof_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('✓ Study plan approved and student cleared for enrollment!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert('Plan approved but failed to clear student: ' + data.message, 'warning');
                }
            });
        }

        function rejectPlan() {
            const feedback = document.getElementById('feedback').value;
            
            if (!feedback.trim()) {
                showAlert('Please provide feedback explaining why the plan is rejected', 'warning');
                return;
            }
            
            if (!confirm('Reject this study plan? The student will need to resubmit.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'reject_study_plan');
            formData.append('plan_id', planId);
            formData.append('feedback', feedback);
            
            fetch('prof_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('✓ Study plan rejected. Student notified.', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Error rejecting plan', 'danger');
            });
        }

        function revokeClearance() {
            if (!confirm('Revoke clearance for this student? They will need to resubmit their plan.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'unclear_student');
            formData.append('student_id', studentId);
            
            fetch('prof_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('✓ Clearance revoked successfully', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Error revoking clearance', 'danger');
            });
        }

        function addFeedback() {
            const feedback = document.getElementById('feedback').value;
            
            if (!feedback.trim()) {
                showAlert('Please enter some feedback', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_feedback');
            formData.append('plan_id', planId);
            formData.append('feedback', feedback);
            
            fetch('prof_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('✓ Feedback added successfully', 'success');
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Error adding feedback', 'danger');
            });
        }

        function requestReupload() {
            const reason = prompt('Why do you need the student to reupload the screenshot?');
            
            if (!reason) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'request_screenshot_reupload');
            formData.append('plan_id', planId);
            formData.append('reason', reason);
            
            fetch('prof_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('✓ Reupload request sent to student', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Error requesting reupload', 'danger');
            });
        }

        function showAlert(message, type) {
            const alertDiv = document.getElementById('actionAlert');
            alertDiv.innerHTML = `<div class="alert ${type}">${message}</div>`;
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    </script>
</body>
</html>