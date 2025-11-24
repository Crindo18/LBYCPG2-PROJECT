<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'professor') {
    header('Location: login.php');
    exit();
}

$professor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM professors WHERE id = ?");
$stmt->bind_param("i", $professor_id);
$stmt->execute();
$professor_name = $stmt->get_result()->fetch_assoc()['full_name'];
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
        .sidebar { width: 260px; background: #6a1b9a; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #4a148c; }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.8; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #BA68C8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #6a1b9a; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #6a1b9a; border-bottom-color: #6a1b9a; }
        
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 20px; color: #6a1b9a; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; font-family: inherit; }
        .form-group textarea { min-height: 200px; resize: vertical; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #6a1b9a; box-shadow: 0 0 0 3px rgba(106, 27, 154, 0.1); }
        
        .btn { padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #6a1b9a; color: white; }
        .btn-primary:hover { background: #8e24aa; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        
        .action-buttons { display: flex; gap: 10px; margin-top: 20px; }
        
        .template-list { display: grid; gap: 15px; }
        .template-card { padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
        .template-card:hover { border-color: #6a1b9a; box-shadow: 0 2px 8px rgba(106, 27, 154, 0.1); }
        .template-card h4 { color: #6a1b9a; margin-bottom: 10px; }
        .template-card p { color: #666; font-size: 14px; }
        .template-actions { display: flex; gap: 10px; margin-top: 10px; }
        .btn-small { padding: 6px 12px; font-size: 13px; }
        
        .email-history { display: grid; gap: 15px; }
        .email-item { padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; }
        .email-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px; }
        .email-to { font-weight: 600; color: #333; }
        .email-date { font-size: 13px; color: #999; }
        .email-subject { color: #6a1b9a; font-weight: 600; margin-bottom: 5px; }
        .email-preview { color: #666; font-size: 14px; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .recipient-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
        .recipient-tag { background: #6a1b9a; color: white; padding: 6px 12px; border-radius: 20px; font-size: 13px; display: flex; align-items: center; gap: 8px; }
        .recipient-tag .remove { cursor: pointer; font-weight: bold; }
        
        .loading { text-align: center; padding: 40px; color: #666; }
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
                <a href="prof_reports.php" class="menu-item">Reports</a>
                <a href="prof_email.php" class="menu-item active">Email System</a>
                <a href="prof_schedule.php" class="menu-item">Schedule</a>
                <a href="prof_grade_approvals.php" class="menu-item">Grade Approvals</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Email System</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div id="alertContainer"></div>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('compose')">Compose Email</button>
                <button class="tab-btn" onclick="switchTab('templates')">Templates</button>
                <button class="tab-btn" onclick="switchTab('history')">Email History</button>
                <button class="tab-btn" onclick="switchTab('bulk')">Bulk Email</button>
            </div>
            
            <!-- Compose Email Tab -->
            <div id="compose-tab" class="tab-content active">
                <div class="content-card">
                    <h3>Compose New Email</h3>
                    <form id="composeForm">
                        <div class="form-group">
                            <label>Recipient Type</label>
                            <select id="recipientType" onchange="handleRecipientTypeChange()">
                                <option value="individual">Individual Student</option>
                                <option value="multiple">Multiple Students</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="individualRecipient">
                            <label>Select Student</label>
                            <select id="studentSelect">
                                <option value="">Loading students...</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="multipleRecipients" style="display:none;">
                            <label>Select Students</label>
                            <select id="multiStudentSelect" multiple style="height: 150px;">
                                <option value="">Loading students...</option>
                            </select>
                            <div class="recipient-tags" id="recipientTags"></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Use Template (Optional)</label>
                            <select id="templateSelect" onchange="loadTemplate()">
                                <option value="">-- None --</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Subject *</label>
                            <input type="text" id="emailSubject" placeholder="Email subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Message *</label>
                            <textarea id="emailBody" placeholder="Type your message here..." required></textarea>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-primary">Send Email</button>
                            <button type="button" class="btn btn-secondary" onclick="clearForm()">Clear</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Templates Tab -->
            <div id="templates-tab" class="tab-content">
                <div class="content-card">
                    <h3>Email Templates</h3>
                    <button class="btn btn-success" onclick="showNewTemplateForm()" style="margin-bottom: 20px;">+ New Template</button>
                    
                    <div id="newTemplateForm" style="display:none; margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px;">Create New Template</h4>
                        <div class="form-group">
                            <label>Template Name *</label>
                            <input type="text" id="templateName" placeholder="e.g., Meeting Reminder">
                        </div>
                        <div class="form-group">
                            <label>Subject *</label>
                            <input type="text" id="templateSubject" placeholder="Email subject">
                        </div>
                        <div class="form-group">
                            <label>Body *</label>
                            <textarea id="templateBody" placeholder="Email body (use {student_name} for personalization)"></textarea>
                        </div>
                        <div class="action-buttons">
                            <button class="btn btn-success" onclick="saveTemplate()">Save Template</button>
                            <button class="btn btn-secondary" onclick="hideNewTemplateForm()">Cancel</button>
                        </div>
                    </div>
                    
                    <div class="template-list" id="templateList">
                        <div class="loading">Loading templates...</div>
                    </div>
                </div>
            </div>
            
            <!-- History Tab -->
            <div id="history-tab" class="tab-content">
                <div class="content-card">
                    <h3>Email History</h3>
                    <div class="email-history" id="emailHistory">
                        <div class="loading">Loading email history...</div>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Email Tab -->
            <div id="bulk-tab" class="tab-content">
                <div class="content-card">
                    <h3>Send Bulk Email to All Advisees</h3>
                    <form id="bulkEmailForm">
                        <div class="form-group">
                            <label>Filter Recipients (Optional)</label>
                            <select id="bulkFilter">
                                <option value="all">All Advisees</option>
                                <option value="cleared">Only Cleared Students</option>
                                <option value="not_cleared">Only Non-Cleared Students</option>
                                <option value="at_risk">Only At-Risk Students (≥15 failed units)</option>
                            </select>
                        </div>
                        
                        <div id="recipientCount" style="margin-bottom: 15px; padding: 10px; background: #e3f2fd; border-radius: 5px; color: #1565c0;">
                            Recipients: <strong id="countDisplay">0</strong> students
                        </div>
                        
                        <div class="form-group">
                            <label>Subject *</label>
                            <input type="text" id="bulkSubject" placeholder="Email subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Message *</label>
                            <textarea id="bulkBody" placeholder="Type your message here..." required></textarea>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-primary">Send to All</button>
                            <button type="button" class="btn btn-secondary" onclick="clearBulkForm()">Clear</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        let allStudents = [];
        let templates = [];
        
        document.addEventListener('DOMContentLoaded', function() {
            loadStudents();
            loadTemplates();
            loadEmailHistory();
            
            document.getElementById('composeForm').addEventListener('submit', sendEmail);
            document.getElementById('bulkEmailForm').addEventListener('submit', sendBulkEmail);
            document.getElementById('bulkFilter').addEventListener('change', updateRecipientCount);
        });
        
        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
            
            if (tabName === 'history') {
                loadEmailHistory();
            }
        }
        
        function loadStudents() {
            fetch('prof_email_api.php?action=get_advisees')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allStudents = data.students;
                        populateStudentSelects();
                        updateRecipientCount();
                    }
                });
        }
        
        function populateStudentSelects() {
            const singleSelect = document.getElementById('studentSelect');
            const multiSelect = document.getElementById('multiStudentSelect');
            
            let options = '<option value="">-- Select Student --</option>';
            allStudents.forEach(student => {
                options += `<option value="${student.id}">${student.id_number} - ${student.full_name}</option>`;
            });
            
            singleSelect.innerHTML = options;
            multiSelect.innerHTML = options.replace('-- Select Student --', '');
        }
        
        function handleRecipientTypeChange() {
            const type = document.getElementById('recipientType').value;
            document.getElementById('individualRecipient').style.display = type === 'individual' ? 'block' : 'none';
            document.getElementById('multipleRecipients').style.display = type === 'multiple' ? 'block' : 'none';
        }
        
        function sendEmail(e) {
            e.preventDefault();
            
            const type = document.getElementById('recipientType').value;
            let recipients = [];
            
            if (type === 'individual') {
                const studentId = document.getElementById('studentSelect').value;
                if (!studentId) {
                    showAlert('Please select a student', 'error');
                    return;
                }
                recipients = [studentId];
            } else {
                const selected = Array.from(document.getElementById('multiStudentSelect').selectedOptions);
                recipients = selected.map(opt => opt.value);
                if (recipients.length === 0) {
                    showAlert('Please select at least one student', 'error');
                    return;
                }
            }
            
            const formData = new FormData();
            formData.append('action', 'send_email');
            formData.append('recipients', JSON.stringify(recipients));
            formData.append('subject', document.getElementById('emailSubject').value);
            formData.append('body', document.getElementById('emailBody').value);
            
            fetch('prof_email_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Email sent successfully!', 'success');
                    clearForm();
                    loadEmailHistory();
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            });
        }
        
        function sendBulkEmail(e) {
            e.preventDefault();
            
            if (!confirm('Send email to all selected advisees?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'send_bulk_email');
            formData.append('filter', document.getElementById('bulkFilter').value);
            formData.append('subject', document.getElementById('bulkSubject').value);
            formData.append('body', document.getElementById('bulkBody').value);
            
            fetch('prof_email_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(`Email sent to ${data.count} students successfully!`, 'success');
                    clearBulkForm();
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            });
        }
        
        function loadTemplates() {
            fetch('prof_email_api.php?action=get_templates')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        templates = data.templates;
                        renderTemplates();
                        populateTemplateSelect();
                    }
                });
        }
        
        function renderTemplates() {
            const container = document.getElementById('templateList');
            
            if (templates.length === 0) {
                container.innerHTML = '<p style="text-align:center; color:#999;">No templates yet. Create your first template!</p>';
                return;
            }
            
            let html = '';
            templates.forEach(template => {
                html += `
                    <div class="template-card">
                        <h4>${template.template_name}</h4>
                        <div style="margin: 10px 0;">
                            <strong>Subject:</strong> ${template.subject}
                        </div>
                        <p>${template.body.substring(0, 150)}...</p>
                        <div class="template-actions">
                            <button class="btn btn-primary btn-small" onclick="useTemplate(${template.id})">Use</button>
                            <button class="btn btn-secondary btn-small" onclick="deleteTemplate(${template.id})">Delete</button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function populateTemplateSelect() {
            const select = document.getElementById('templateSelect');
            let options = '<option value="">-- None --</option>';
            
            templates.forEach(template => {
                options += `<option value="${template.id}">${template.template_name}</option>`;
            });
            
            select.innerHTML = options;
        }
        
        function showNewTemplateForm() {
            document.getElementById('newTemplateForm').style.display = 'block';
        }
        
        function hideNewTemplateForm() {
            document.getElementById('newTemplateForm').style.display = 'none';
            document.getElementById('templateName').value = '';
            document.getElementById('templateSubject').value = '';
            document.getElementById('templateBody').value = '';
        }
        
        function saveTemplate() {
            const name = document.getElementById('templateName').value;
            const subject = document.getElementById('templateSubject').value;
            const body = document.getElementById('templateBody').value;
            
            if (!name || !subject || !body) {
                showAlert('Please fill in all template fields', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'save_template');
            formData.append('name', name);
            formData.append('subject', subject);
            formData.append('body', body);
            
            fetch('prof_email_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Template saved successfully!', 'success');
                    hideNewTemplateForm();
                    loadTemplates();
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            });
        }
        
        function useTemplate(templateId) {
            const template = templates.find(t => t.id == templateId);
            if (template) {
                document.getElementById('emailSubject').value = template.subject;
                document.getElementById('emailBody').value = template.body;
                switchTab('compose');
            }
        }
        
        function loadTemplate() {
            const templateId = document.getElementById('templateSelect').value;
            if (templateId) {
                useTemplate(templateId);
            }
        }
        
        function deleteTemplate(templateId) {
            if (!confirm('Delete this template?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_template');
            formData.append('template_id', templateId);
            
            fetch('prof_email_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Template deleted', 'success');
                    loadTemplates();
                } else {
                    showAlert('Error: ' + data.message, 'error');
                }
            });
        }
        
        function loadEmailHistory() {
            fetch('prof_email_api.php?action=get_email_history')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderEmailHistory(data.emails);
                    }
                });
        }
        
        function renderEmailHistory(emails) {
            const container = document.getElementById('emailHistory');
            
            if (emails.length === 0) {
                container.innerHTML = '<p style="text-align:center; color:#999;">No emails sent yet</p>';
                return;
            }
            
            let html = '';
            emails.forEach(email => {
                html += `
                    <div class="email-item">
                        <div class="email-header">
                            <div>
                                <div class="email-to">To: ${email.recipient_names}</div>
                                <div class="email-subject">${email.subject}</div>
                            </div>
                            <div class="email-date">${formatDate(email.sent_at)}</div>
                        </div>
                        <div class="email-preview">${email.body.substring(0, 200)}...</div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function updateRecipientCount() {
            const filter = document.getElementById('bulkFilter').value;
            
            let count = 0;
            if (filter === 'all') {
                count = allStudents.length;
            } else if (filter === 'cleared') {
                count = allStudents.filter(s => s.advising_cleared).length;
            } else if (filter === 'not_cleared') {
                count = allStudents.filter(s => !s.advising_cleared).length;
            } else if (filter === 'at_risk') {
                count = allStudents.filter(s => s.accumulated_failed_units >= 15).length;
            }
            
            document.getElementById('countDisplay').textContent = count;
        }
        
        function clearForm() {
            document.getElementById('composeForm').reset();
            document.getElementById('recipientType').value = 'individual';
            handleRecipientTypeChange();
        }
        
        function clearBulkForm() {
            document.getElementById('bulkEmailForm').reset();
            updateRecipientCount();
        }
        
        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            container.innerHTML = `<div class="alert ${type}">${message}</div>`;
            setTimeout(() => container.innerHTML = '', 5000);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    </script>
</body>
</html>