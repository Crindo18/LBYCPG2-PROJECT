<?php
require_once 'auth_check.php';
require_once 'config.php';

if (!isAuthenticated() || $_SESSION['user_type'] !== 'professor') {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    header('Location: login.php');
    exit();
}

$professor_id = $_SESSION['user_id'];

// Handle API requests
if (isset($_GET['action']) || isset($_POST['action'])) {
    ob_start();
    error_reporting(0);
    ini_set('display_errors', 0);
    ob_clean();
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
    case 'get_advisees':
        getAdvisees();
        break;
    case 'send_email':
        sendEmail();
        break;
    case 'get_templates':
        getTemplates();
        break;
    case 'get_template':
        getTemplate();
        break;
    case 'save_template':
        saveTemplate();
        break;
    case 'delete_template':
        deleteTemplate();
        break;
    case 'get_sent_emails':
        getSentEmails();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit();
}

// API Functions
function getAdvisees() {
    global $conn, $professor_id;
    $stmt = $conn->prepare("SELECT id, id_number, CONCAT(first_name, ' ', last_name) as name FROM students WHERE advisor_id = ? ORDER BY last_name, first_name");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    echo json_encode(['success' => true, 'students' => $students]);
}

function sendEmail() {
    global $conn, $professor_id;
    $recipients = json_decode($_POST['recipients'], true);
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $send_immediately = $_POST['send_immediately'] === '1';
    
    $stmt = $conn->prepare("INSERT INTO email_queue (from_professor_id, to_student_id, subject, body, send_immediately) VALUES (?, ?, ?, ?, ?)");
    $success_count = 0;
    foreach ($recipients as $student_id) {
        $stmt->bind_param("iissi", $professor_id, $student_id, $subject, $message, $send_immediately);
        if ($stmt->execute()) $success_count++;
    }
    echo json_encode(['success' => true, 'message' => "Email queued for $success_count recipient(s)"]);
}

function getTemplates() {
    global $conn, $professor_id;
    $stmt = $conn->prepare("SELECT * FROM email_templates WHERE professor_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $templates = [];
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
    echo json_encode(['success' => true, 'templates' => $templates]);
}

function getTemplate() {
    global $conn, $professor_id;
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM email_templates WHERE id = ? AND professor_id = ?");
    $stmt->bind_param("ii", $id, $professor_id);
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    echo json_encode(['success' => true, 'template' => $template]);
}

function saveTemplate() {
    global $conn, $professor_id;
    $template_name = $_POST['template_name'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $stmt = $conn->prepare("INSERT INTO email_templates (professor_id, template_name, subject, body) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $professor_id, $template_name, $subject, $body);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Template saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save template']);
    }
}

function deleteTemplate() {
    global $conn, $professor_id;
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM email_templates WHERE id = ? AND professor_id = ?");
    $stmt->bind_param("ii", $id, $professor_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Template deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete template']);
    }
}

function getSentEmails() {
    global $conn, $professor_id;
    $stmt = $conn->prepare("
        SELECT eq.*, CONCAT(s.first_name, ' ', s.last_name) as recipient_name
        FROM email_queue eq
        JOIN students s ON s.id = eq.to_student_id
        WHERE eq.from_professor_id = ?
        ORDER BY eq.created_at DESC
        LIMIT 100
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $emails = [];
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row;
    }
    echo json_encode(['success' => true, 'emails' => $emails]);
}

// Get professor info for HTML display
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name, last_name FROM professors WHERE id = ?");
$stmt->bind_param("i", $professor_id);
$stmt->execute();
$professor = $stmt->get_result()->fetch_assoc();
$professor_name = $professor['full_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email System - Professor Portal</title>
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
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #7FE5B8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #00A36C; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h2 { font-size: 22px; color: #00A36C; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .tabs { display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 2px solid #f0f0f0; }
        .tab { padding: 12px 20px; background: none; border: none; color: #666; cursor: pointer; font-size: 14px; font-weight: 500; border-bottom: 3px solid transparent; margin-bottom: -2px; transition: all 0.3s; }
        .tab.active { color: #00A36C; border-bottom-color: #00A36C; }
        .tab:hover { color: #00A36C; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; font-family: inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #00A36C; }
        .form-group textarea { min-height: 150px; resize: vertical; }
        .form-group small { color: #666; font-size: 13px; display: block; margin-top: 5px; }
        
        /* Improved Recipient Selection */
        .recipient-selector { border: 1px solid #ddd; border-radius: 5px; padding: 15px; background: #f8f9fa; }
        .recipient-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd; }
        .recipient-count { font-size: 14px; color: #666; }
        .recipient-count strong { color: #00A36C; }
        .search-box-recipients { margin-bottom: 15px; }
        .search-box-recipients input { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .search-box-recipients input:focus { outline: none; border-color: #00A36C; }
        .recipient-list { max-height: 250px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; background: white; }
        .recipient-item { padding: 10px 15px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: background 0.2s; }
        .recipient-item:last-child { border-bottom: none; }
        .recipient-item:hover { background: #f8f9fa; }
        .recipient-item input[type="checkbox"] { cursor: pointer; width: 18px; height: 18px; }
        .recipient-item label { cursor: pointer; flex: 1; font-size: 14px; margin: 0; }
        .recipient-item .student-id { color: #999; font-size: 13px; margin-left: 8px; }
        .select-actions { display: flex; gap: 10px; }
        .btn-small { padding: 5px 12px; background: #00A36C; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .btn-small:hover { background: #00C97F; }
        .btn-small.secondary { background: #6c757d; }
        .btn-small.secondary:hover { background: #5a6268; }
        
        .checkbox-group { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
        .checkbox-group input[type="checkbox"] { width: auto; margin: 0; cursor: pointer; }
        .checkbox-group label { margin: 0; font-weight: normal; cursor: pointer; }
        .btn-group { display: flex; gap: 10px; }
        .btn-primary { padding: 10px 25px; background: #00A36C; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 500; }
        .btn-primary:hover { background: #00C97F; }
        .btn-secondary { padding: 10px 25px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .btn-secondary:hover { background: #5a6268; }
        .table-container { overflow-x: auto; margin-top: 20px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.danger { background: #f8d7da; color: #721c24; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .template-item { padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 15px; background: #f8f9fa; }
        .template-item h4 { margin-bottom: 8px; color: #333; font-size: 16px; }
        .template-item p { font-size: 13px; color: #666; margin-bottom: 10px; }
        .template-item .actions { display: flex; gap: 8px; }
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
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <div>
                    <h1>Email System</h1>
                    <p style="color: #666; font-size: 14px; margin-top: 5px;">Send emails to your advisees</p>
                </div>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="content-card">
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('compose')">Compose Email</button>
                    <button class="tab" onclick="switchTab('templates')">Templates</button>
                    <button class="tab" onclick="switchTab('sent')">Sent Emails</button>
                </div>
                
                <!-- Compose Email Tab -->
                <div id="compose-tab" class="tab-content active">
                    <form id="composeForm">
                        <div class="form-group">
                            <label>Recipients</label>
                            <div class="recipient-selector">
                                <div class="recipient-header">
                                    <div class="recipient-count">
                                        <span id="selectedCount">0</span> student(s) selected
                                    </div>
                                    <div class="select-actions">
                                        <button type="button" class="btn-small" onclick="selectAll()">Select All</button>
                                        <button type="button" class="btn-small secondary" onclick="deselectAll()">Deselect All</button>
                                    </div>
                                </div>
                                
                                <div class="search-box-recipients">
                                    <input type="text" id="recipientSearch" placeholder="🔍 Search students by name or ID...">
                                </div>
                                
                                <div class="recipient-list" id="recipientList">
                                    <div class="loading" style="padding: 20px;">Loading students...</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" id="subject" placeholder="Enter email subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Message</label>
                            <textarea id="message" placeholder="Enter your message" required></textarea>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="sendImmediately" checked>
                            <label for="sendImmediately">Send immediately</label>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn-primary">Send Email</button>
                            <button type="button" class="btn-secondary" onclick="showTemplates()">Load Template</button>
                        </div>
                    </form>
                </div>
                
                <!-- Templates Tab -->
                <div id="templates-tab" class="tab-content">
                    <button type="button" class="btn-primary" onclick="showNewTemplateForm()" style="margin-bottom: 20px;">Create New Template</button>
                    
                    <div id="newTemplateForm" style="display: none; padding: 20px; background: #f8f9fa; border-radius: 5px; margin-bottom: 20px;">
                        <h3 style="margin-bottom: 15px; color: #6a1b9a;">New Template</h3>
                        <form id="templateForm">
                            <div class="form-group">
                                <label>Template Name</label>
                                <input type="text" id="templateName" placeholder="e.g., Study Plan Reminder" required>
                            </div>
                            <div class="form-group">
                                <label>Subject</label>
                                <input type="text" id="templateSubject" placeholder="Email subject" required>
                            </div>
                            <div class="form-group">
                                <label>Message Body</label>
                                <textarea id="templateBody" placeholder="Template message" required style="min-height: 120px;"></textarea>
                            </div>
                            <div class="btn-group">
                                <button type="submit" class="btn-primary">Save Template</button>
                                <button type="button" class="btn-secondary" onclick="hideNewTemplateForm()">Cancel</button>
                            </div>
                        </form>
                    </div>
                    
                    <div id="templatesList">
                        <div class="loading">Loading templates...</div>
                    </div>
                </div>
                
                <!-- Sent Emails Tab -->
                <div id="sent-tab" class="tab-content">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Recipient</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="sentEmailsTable">
                                <tr><td colspan="4" class="loading">Loading sent emails...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    let allStudents = [];
    let selectedStudents = new Set();

    document.addEventListener('DOMContentLoaded', function() {
        loadStudents();
        loadTemplates();
        loadSentEmails();
        
        // Search functionality
        document.getElementById('recipientSearch').addEventListener('input', function() {
            filterStudents(this.value);
        });
    });

    function switchTab(tab) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        event.target.classList.add('active');
        document.getElementById(tab + '-tab').classList.add('active');
        if (tab === 'templates') loadTemplates();
        if (tab === 'sent') loadSentEmails();
    }

    function showTemplates() {
        document.querySelectorAll('.tab')[1].click();
    }

    function loadStudents() {
        fetch('prof_email.php?action=get_advisees')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allStudents = data.students;
                renderStudentList(allStudents);
            }
        });
    }

    function renderStudentList(students) {
        const list = document.getElementById('recipientList');
        if (students.length === 0) {
            list.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No students found</div>';
            return;
        }
        
        list.innerHTML = students.map(s => `
            <div class="recipient-item" onclick="toggleStudent(${s.id})">
                <input type="checkbox" id="student_${s.id}" value="${s.id}" ${selectedStudents.has(s.id) ? 'checked' : ''} onclick="event.stopPropagation(); toggleStudent(${s.id})">
                <label for="student_${s.id}">${s.name}<span class="student-id">${s.id_number}</span></label>
            </div>
        `).join('');
        
        updateSelectedCount();
    }

    function toggleStudent(id) {
        if (selectedStudents.has(id)) {
            selectedStudents.delete(id);
        } else {
            selectedStudents.add(id);
        }
        const checkbox = document.getElementById('student_' + id);
        if (checkbox) checkbox.checked = selectedStudents.has(id);
        updateSelectedCount();
    }

    function selectAll() {
        const visibleStudents = document.querySelectorAll('.recipient-item:not([style*="display: none"]) input[type="checkbox"]');
        visibleStudents.forEach(cb => {
            selectedStudents.add(parseInt(cb.value));
            cb.checked = true;
        });
        updateSelectedCount();
    }

    function deselectAll() {
        selectedStudents.clear();
        document.querySelectorAll('.recipient-item input[type="checkbox"]').forEach(cb => cb.checked = false);
        updateSelectedCount();
    }

    function updateSelectedCount() {
        document.getElementById('selectedCount').textContent = selectedStudents.size;
    }

    function filterStudents(search) {
        const filtered = allStudents.filter(s => 
            s.name.toLowerCase().includes(search.toLowerCase()) || 
            s.id_number.includes(search)
        );
        renderStudentList(filtered);
    }

    document.getElementById('composeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (selectedStudents.size === 0) { 
            alert('Please select at least one recipient'); 
            return; 
        }
        
        const formData = new FormData();
        formData.append('action', 'send_email');
        formData.append('recipients', JSON.stringify(Array.from(selectedStudents)));
        formData.append('subject', document.getElementById('subject').value);
        formData.append('message', document.getElementById('message').value);
        formData.append('send_immediately', document.getElementById('sendImmediately').checked ? '1' : '0');
        
        fetch('prof_email.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                document.getElementById('subject').value = '';
                document.getElementById('message').value = '';
                deselectAll();
                loadSentEmails();
            } else { 
                alert('Error: ' + data.message); 
            }
        });
    });

    function loadTemplates() {
        fetch('prof_email.php?action=get_templates')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const list = document.getElementById('templatesList');
                if (data.templates.length === 0) {
                    list.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No templates yet</p>';
                    return;
                }
                list.innerHTML = data.templates.map(t => `
                    <div class="template-item">
                        <h4>${t.template_name}</h4>
                        <p><strong>Subject:</strong> ${t.subject}</p>
                        <p style="white-space: pre-wrap;">${t.body.substring(0, 150)}${t.body.length > 150 ? '...' : ''}</p>
                        <div class="actions">
                            <button class="btn-secondary" onclick="useTemplate(${t.id})">Use Template</button>
                            <button class="btn-secondary" onclick="deleteTemplate(${t.id})" style="background: #dc3545;">Delete</button>
                        </div>
                    </div>
                `).join('');
            }
        });
    }

    function showNewTemplateForm() {
        document.getElementById('newTemplateForm').style.display = 'block';
    }

    function hideNewTemplateForm() {
        document.getElementById('newTemplateForm').style.display = 'none';
        document.getElementById('templateForm').reset();
    }

    document.getElementById('templateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('action', 'save_template');
        formData.append('template_name', document.getElementById('templateName').value);
        formData.append('subject', document.getElementById('templateSubject').value);
        formData.append('body', document.getElementById('templateBody').value);
        fetch('prof_email.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                hideNewTemplateForm();
                loadTemplates();
            } else { alert('Error: ' + data.message); }
        });
    });

    function useTemplate(id) {
        fetch(`prof_email.php?action=get_template&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('subject').value = data.template.subject;
                document.getElementById('message').value = data.template.body;
                document.querySelectorAll('.tab')[0].click();
                alert('Template loaded into composer');
            }
        });
    }

    function deleteTemplate(id) {
        if (!confirm('Delete this template?')) return;
        const formData = new FormData();
        formData.append('action', 'delete_template');
        formData.append('id', id);
        fetch('prof_email.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                loadTemplates();
            } else { alert('Error: ' + data.message); }
        });
    }

    function loadSentEmails() {
        fetch('prof_email.php?action=get_sent_emails')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tbody = document.getElementById('sentEmailsTable');
                if (data.emails.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 40px; color: #999;">No sent emails yet</td></tr>';
                    return;
                }
                tbody.innerHTML = data.emails.map(e => `
                    <tr>
                        <td>${new Date(e.created_at).toLocaleString('en-US', {year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'})}</td>
                        <td>${e.recipient_name}</td>
                        <td>${e.subject}</td>
                        <td><span class="badge ${e.status === 'sent' ? 'success' : e.status === 'pending' ? 'pending' : 'danger'}">${e.status}</span></td>
                    </tr>
                `).join('');
            }
        });
    }
    </script>
</body>
</html>
