<?php
require_once 'auth_check.php';
requireAdmin();

require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    ob_start();
    error_reporting(0);
    ini_set('display_errors', 0);
    
    ob_clean();
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_advisers':
            getAdvisers();
            exit;
        case 'bulk_clearance':
            bulkClearance();
            exit;
        case 'send_mass_email':
            sendMassEmail();
            exit;
    }
}

function getAdvisers() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT id, CONCAT(first_name, ' ', last_name) as name 
        FROM professors 
        ORDER BY last_name
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $advisers = [];
    while ($row = $result->fetch_assoc()) {
        $advisers[] = $row;
    }
    
    echo json_encode(['success' => true, 'advisers' => $advisers]);
}

function bulkClearance() {
    global $conn;
    
    $clearance_action = $_POST['clearance_action'] ?? '';
    $target = $_POST['target'] ?? '';
    
    $clear_value = ($clearance_action === 'clear') ? 1 : 0;
    
    $where = "1=1";
    
    if ($target === 'program') {
        $program = $_POST['program'] ?? '';
        $where .= " AND program = '" . $conn->real_escape_string($program) . "'";
    } elseif ($target === 'adviser') {
        $adviser_id = $_POST['adviser_id'] ?? 0;
        $where .= " AND advisor_id = " . (int)$adviser_id;
    } elseif ($target === 'list') {
        $student_list = $_POST['student_list'] ?? '';
        $ids = array_filter(array_map('trim', explode("\n", $student_list)));
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No student IDs provided']);
            return;
        }
        $id_list = "'" . implode("','", array_map([$conn, 'real_escape_string'], $ids)) . "'";
        $where .= " AND id_number IN ($id_list)";
    }
    
    $query = "UPDATE students SET advising_cleared = $clear_value WHERE $where";
    $conn->query($query);
    $affected = $conn->affected_rows;
    
    echo json_encode([
        'success' => true,
        'message' => "$affected students " . ($clear_value ? 'cleared' : 'uncleared'),
        'stats' => [
            'affected' => $affected
        ]
    ]);
}

function sendMassEmail() {
    global $conn;
    
    $recipients = $_POST['recipients'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $preview = !empty($_POST['preview']);
    
    if (!$recipients || $subject === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Recipients, subject, and message are required']);
        return;
    }
    
    $query = "SELECT id, CONCAT(first_name, ' ', last_name) as name, email, id_number, program FROM ";
    
    switch ($recipients) {
        case 'all_students':
            $query .= "students";
            break;
        case 'all_professors':
            $query .= "professors";
            break;
        case 'program':
            $program = $_POST['program'] ?? '';
            $query .= "students WHERE program = '" . $conn->real_escape_string($program) . "'";
            break;
        case 'cleared':
            $query .= "students WHERE advising_cleared = 1";
            break;
        case 'not_cleared':
            $query .= "students WHERE advising_cleared = 0";
            break;
        case 'at_risk':
            $query .= "students WHERE accumulated_failed_units >= 25";
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid recipient selection']);
            return;
    }
    
    $result = $conn->query($query);
    $targets = [];
    while ($row = $result->fetch_assoc()) {
        $targets[] = $row;
        if ($preview) break;
    }
    
    if (empty($targets)) {
        echo json_encode(['success' => false, 'message' => 'No recipients match the selected filter']);
        return;
    }
    
    if ($preview) {
        $adminContact = getAdminContact();
        if (!$adminContact || empty($adminContact['email'])) {
            echo json_encode(['success' => false, 'message' => 'Admin account is missing an email address for previews']);
            return;
        }
        
        $sampleUser = $targets[0];
        $body = personalizeMessage($message, $sampleUser);
        
        try {
            $mail = createMailer();
            $mail->addAddress($adminContact['email'], $adminContact['name']);
            $mail->Subject = '[PREVIEW] ' . $subject;
            $mail->Body = nl2br($body);
            $mail->AltBody = strip_tags($body);
            $mail->send();
            
            echo json_encode([
                'success' => true,
                'message' => "Preview email sent to {$adminContact['email']}",
                'stats' => [
                    'emails_sent' => 1,
                    'failed' => 0
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Preview send failed: ' . $e->getMessage()]);
        }
        return;
    }
    
    $sent = 0;
    $failed = [];
    
    foreach ($targets as $user) {
        if (empty($user['email'])) {
            $failed[] = ($user['name'] ?? 'Unknown') . ' (missing email)';
            continue;
        }
        
        try {
            $mail = createMailer();
            $mail->addAddress($user['email'], $user['name'] ?? '');
            $mail->Subject = $subject;
            $body = personalizeMessage($message, $user);
            $mail->Body = nl2br($body);
            $mail->AltBody = strip_tags($body);
            $mail->send();
            $sent++;
        } catch (Exception $e) {
            $failed[] = $user['email'] . ': ' . $mail->ErrorInfo;
        }
    }
    
    $responseMessage = "$sent email(s) sent.";
    if ($failed) {
        $responseMessage .= ' ' . count($failed) . ' failed.';
    }
    
    echo json_encode([
        'success' => $sent > 0,
        'message' => $responseMessage,
        'stats' => [
            'emails_sent' => $sent,
            'failed' => count($failed)
        ],
        'errors' => $failed
    ]);
}

function createMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = MAILER_HOST;
    $mail->Port = MAILER_PORT;
    
    if (MAILER_USERNAME) {
        $mail->SMTPAuth = true;
        $mail->Username = MAILER_USERNAME;
        $mail->Password = MAILER_PASSWORD;
    } else {
        $mail->SMTPAuth = false;
    }
    
    $encryption = strtolower(MAILER_ENCRYPTION);
    if ($encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    
    $mail->setFrom(MAILER_FROM_EMAIL, MAILER_FROM_NAME);
    $mail->isHTML(true);
    
    return $mail;
}

function personalizeMessage(string $template, array $user): string {
    return str_replace(
        ['{name}', '{id_number}', '{program}'],
        [
            $user['name'] ?? 'Student',
            $user['id_number'] ?? 'N/A',
            $user['program'] ?? 'N/A'
        ],
        $template
    );
}

function getAdminContact(): ?array {
    global $conn;
    
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT username, email FROM admin WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return [
            'name' => $row['username'] ?: 'Administrator',
            'email' => $row['email'] ?? null
        ];
    }
    
    return null;
}

// HTML Interface below
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
        .sidebar { width: 260px; background: #00A36C; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #006B4A; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.9; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.08); border-left-color: #7FE5B8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #00A36C; }
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
        
        .bulk-upload-section { margin-top: 40px; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e0e0e0; flex-wrap: wrap; }
        .tab-btn { padding: 10px 22px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #3498db; border-bottom-color: #3498db; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .content-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .content-card h2 { font-size: 22px; color: #2c3e50; margin-bottom: 15px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
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
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadAdvisers();
        });

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
            fetch('?action=get_advisers')
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
            
            fetch('', {
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
            
            fetch('', {
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