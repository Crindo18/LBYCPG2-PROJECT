<?php
require_once 'auth_check.php';
require_once 'config.php';

if (!isAuthenticated() || $_SESSION['user_type'] !== 'professor') {
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
        
        /* Recipient Selector */
        .recipient-selector { border: 1px solid #ddd; border-radius: 5px; padding: 15px; background: #f8f9fa; }
        .recipient-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd; }
        .recipient-list { max-height: 250px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; background: white; }
        .recipient-item { padding: 10px 15px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .recipient-item:hover { background: #f8f9fa; }
        .recipient-item input[type="checkbox"] { cursor: pointer; width: 18px; height: 18px; }
        .btn-small { padding: 5px 12px; background: #00A36C; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        
        .checkbox-group { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
        .checkbox-group input[type="checkbox"] { width: auto; margin: 0; cursor: pointer; }
        .btn-group { display: flex; gap: 10px; }
        .btn-primary { padding: 10px 25px; background: #00A36C; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .btn-secondary { padding: 10px 25px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.failed { background: #f8d7da; color: #721c24; }
        .template-item { padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 15px; background: #f8f9fa; }
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
                <a href="prof_advising_forms.php" class="menu-item">Advising Forms</a>
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
                
                <!-- Compose Tab -->
                <div id="compose-tab" class="tab-content active">
                    <form id="composeForm">
                        <div class="form-group">
                            <label>Recipients</label>
                            <div class="recipient-selector">
                                <div class="recipient-header">
                                    <div class="recipient-count"><span id="selectedCount">0</span> selected</div>
                                    <div>
                                        <button type="button" class="btn-small" onclick="selectAll()">Select All</button>
                                        <button type="button" class="btn-small" onclick="deselectAll()" style="background:#6c757d">Clear</button>
                                    </div>
                                </div>
                                <div class="recipient-list" id="recipientList"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" id="subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Message</label>
                            <textarea id="message" required></textarea>
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
                    <!-- Simple form to add template -->
                    <div style="margin-bottom:20px; padding:15px; background:#eee; border-radius:5px;">
                        <h4>Create New Template</h4>
                        <form id="templateForm">
                            <input type="text" id="tplName" placeholder="Template Name" style="width:100%; margin-bottom:5px; padding:8px;">
                            <input type="text" id="tplSubject" placeholder="Subject" style="width:100%; margin-bottom:5px; padding:8px;">
                            <textarea id="tplBody" placeholder="Body" style="width:100%; height:80px; margin-bottom:5px; padding:8px;"></textarea>
                            <button type="submit" class="btn-primary">Save Template</button>
                        </form>
                    </div>
                    <div id="templatesList">Loading...</div>
                </div>
                
                <!-- Sent Emails Tab -->
                <div id="sent-tab" class="tab-content">
                    <div style="margin-bottom: 15px;">
                        <button class="btn-primary" onclick="processEmails()">Process Pending Emails</button>
                        <button class="btn-secondary" onclick="loadSentEmails()">Refresh</button>
                    </div>
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Recipient</th><th>Subject</th><th>Status</th></tr></thead>
                        <tbody id="sentEmailsTable"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    let allStudents = [];
    let selectedStudents = new Set();

    document.addEventListener('DOMContentLoaded', () => {
        loadStudents();
        loadTemplates();
        loadSentEmails();
    });

    function switchTab(tab) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        event.target.classList.add('active');
        document.getElementById(tab + '-tab').classList.add('active');
    }

    function loadStudents() {
        // Pointing to prof_email_api.php
        fetch('prof_email_api.php?action=get_advisees')
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                allStudents = data.students;
                const list = document.getElementById('recipientList');
                list.innerHTML = allStudents.map(s => `
                    <div class="recipient-item" onclick="toggleStudent(${s.id})">
                        <input type="checkbox" id="s_${s.id}" ${selectedStudents.has(s.id) ? 'checked' : ''}>
                        <label>${s.full_name} (${s.id_number})</label>
                    </div>
                `).join('');
            }
        });
    }

    function toggleStudent(id) {
        if(selectedStudents.has(id)) selectedStudents.delete(id);
        else selectedStudents.add(id);
        document.getElementById('s_'+id).checked = selectedStudents.has(id);
        document.getElementById('selectedCount').textContent = selectedStudents.size;
    }

    function selectAll() {
        allStudents.forEach(s => selectedStudents.add(s.id));
        loadStudents(); // re-render
        document.getElementById('selectedCount').textContent = allStudents.length;
    }

    function deselectAll() {
        selectedStudents.clear();
        loadStudents();
        document.getElementById('selectedCount').textContent = 0;
    }

    document.getElementById('composeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if(selectedStudents.size === 0) return alert('Select recipients');
        
        const formData = new FormData();
        formData.append('action', 'send_email');
        formData.append('recipients', JSON.stringify(Array.from(selectedStudents)));
        formData.append('subject', document.getElementById('subject').value);
        formData.append('message', document.getElementById('message').value);
        formData.append('send_immediately', document.getElementById('sendImmediately').checked ? '1' : '0');

        fetch('prof_email_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if(data.success) {
                document.getElementById('subject').value = '';
                document.getElementById('message').value = '';
                deselectAll();
                loadSentEmails();
            }
        });
    });

    function loadSentEmails() {
        fetch('prof_email_api.php?action=get_sent_emails')
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                const tbody = document.getElementById('sentEmailsTable');
                tbody.innerHTML = data.emails.map(e => `
                    <tr>
                        <td>${e.created_at}</td>
                        <td>${e.recipient_name || 'Unknown'}</td>
                        <td>${e.subject}</td>
                        <td><span class="badge ${e.status === 'sent' ? 'success' : (e.status === 'failed' ? 'failed' : 'pending')}">${e.status}</span></td>
                    </tr>
                `).join('');
            }
        });
    }

    function processEmails() {
        fetch('prof_email_api.php?action=process_emails')
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            loadSentEmails();
        });
    }

    // Template functions
    function loadTemplates() {
        fetch('prof_email_api.php?action=get_templates').then(r=>r.json()).then(d=>{
            if(d.success) {
                document.getElementById('templatesList').innerHTML = d.templates.map(t => `
                    <div class="template-item">
                        <strong>${t.template_name}</strong>
                        <p>${t.subject}</p>
                        <button class="btn-small" onclick="useTemplate('${t.subject}', '${t.body.replace(/'/g, "\\'")}')">Use</button>
                        <button class="btn-small" style="background:red" onclick="deleteTemplate(${t.id})">Delete</button>
                    </div>
                `).join('');
            }
        });
    }

    function useTemplate(sub, body) {
        document.getElementById('subject').value = sub;
        document.getElementById('message').value = body;
        switchTab('compose');
    }

    function deleteTemplate(id) {
        if(!confirm('Delete?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_template');
        fd.append('id', id);
        fetch('prof_email_api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
            loadTemplates();
        });
    }

    document.getElementById('templateForm').addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData();
        fd.append('action', 'save_template');
        fd.append('template_name', document.getElementById('tplName').value);
        fd.append('subject', document.getElementById('tplSubject').value);
        fd.append('body', document.getElementById('tplBody').value);
        fetch('prof_email_api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
            alert(d.message);
            loadTemplates();
            e.target.reset();
        });
    });
    
    function showTemplates() { switchTab('templates'); }
    </script>
</body>
</html>