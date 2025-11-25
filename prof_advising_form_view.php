<?php
require_once 'auth_check.php';
requireProfessor();

require_once 'config.php';

$professor_id = $_SESSION['user_id'];
$form_id = $_GET['id'] ?? 0;

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
    <title>Advising Form Review</title>
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
        .back-btn { padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; margin-right: 10px; transition: background 0.3s; }
        .back-btn:hover { background: #5a6268; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; transition: background 0.3s; }
        .logout-btn:hover { background: #c82333; }
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .content-card h2 { font-size: 22px; color: #00A36C; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .content-card h3 { font-size: 18px; color: #333; margin-top: 25px; margin-bottom: 15px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .info-item { padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #00A36C; }
        .info-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; font-weight: 600; }
        .info-value { font-size: 16px; color: #333; font-weight: 600; }
        .badge { padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.approved { background: #d4edda; color: #155724; }
        .badge.rejected { background: #f8d7da; color: #721c24; }
        .badge.revision { background: #e3f2fd; color: #1565c0; }
        .badge.warning { background: #ffebee; color: #c62828; }
        .badge.success { background: #e8f5e9; color: #2e7d32; }
        
        .courses-section { margin-top: 25px; }
        .courses-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .courses-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; }
        .courses-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .courses-table tr:hover { background: #f8f9fa; }
        
        .prereq-list { font-size: 13px; color: #666; margin-top: 5px; }
        .prereq-item { display: inline-block; padding: 3px 8px; background: #e3f2fd; border-radius: 3px; margin: 2px; font-size: 12px; }
        
        .file-link { display: inline-block; padding: 8px 16px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px; margin: 5px; font-size: 14px; transition: background 0.3s; }
        .file-link:hover { background: #1976D2; }
        
        .action-section { background: #f8f9fa; padding: 25px; border-radius: 10px; margin-top: 20px; }
        .action-section h3 { margin-top: 0; color: #333; }
        .action-buttons { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .btn { padding: 12px 24px; border: none; border-radius: 5px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-approve { background: #4CAF50; color: white; }
        .btn-approve:hover { background: #45a049; }
        .btn-reject { background: #dc3545; color: white; }
        .btn-reject:hover { background: #c82333; }
        .btn-revision { background: #2196F3; color: white; }
        .btn-revision:hover { background: #1976D2; }
        
        textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; font-size: 14px; min-height: 100px; resize: vertical; }
        textarea:focus { border-color: #00A36C; outline: none; }
        
        .loading { text-align: center; padding: 40px; color: #666; }
        .error { text-align: center; padding: 40px; color: #dc3545; }
        
        .notes-box { background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107; margin-top: 15px; }
        .notes-box strong { color: #856404; }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; height: auto; }
            .main-content { margin-left: 0; width: 100%; padding: 15px; }
            .info-grid { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
        }
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
                <a href="prof_advising_forms.php" class="menu-item active">Advising Forms</a>
                <a href="prof_acadadvising.php" class="menu-item">Academic Advising</a>
                <a href="prof_concerns.php" class="menu-item">Student Concerns</a>
                <a href="prof_reports.php" class="menu-item">Reports</a>
                <a href="prof_email.php" class="menu-item">Email System</a>
                <a href="prof_schedule.php" class="menu-item">Schedule</a>
                <a href="prof_grade_approvals.php" class="menu-item">Grade Approvals</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Advising Form Review</h1>
                <div>
                    <a href="prof_advising_forms.php" class="back-btn">← Back to Forms</a>
                    <a href="login.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <div id="formContent">
                <div class="loading">Loading form details...</div>
            </div>
        </main>
    </div>

    <script>
        const formId = <?php echo $form_id; ?>;
        let formData = null;

        document.addEventListener('DOMContentLoaded', function() {
            loadFormDetails();
        });

        function loadFormDetails() {
            fetch(`prof_advising_api.php?action=get_form_details&form_id=${formId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        formData = data.form;
                        renderFormDetails(formData);
                    } else {
                        document.getElementById('formContent').innerHTML = 
                            `<div class="content-card"><div class="error">${data.message}</div></div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('formContent').innerHTML = 
                        '<div class="content-card"><div class="error">Error loading form details</div></div>';
                });
        }

        function renderFormDetails(form) {
            const formDataObj = form.form_data;
            
            let statusBadge = '';
            switch(form.status) {
                case 'approved':
                    statusBadge = '<span class="badge approved">APPROVED</span>';
                    break;
                case 'rejected':
                    statusBadge = '<span class="badge rejected">REJECTED</span>';
                    break;
                case 'revision_requested':
                    statusBadge = '<span class="badge revision">REVISION NEEDED</span>';
                    break;
                default:
                    statusBadge = '<span class="badge pending">PENDING REVIEW</span>';
            }
            
            let failedUnitsBadge = '';
            if (form.accumulated_failed_units >= 25) {
                failedUnitsBadge = ' <span class="badge warning">CRITICAL RISK</span>';
            } else if (form.accumulated_failed_units >= 15) {
                failedUnitsBadge = ' <span class="badge warning">AT RISK</span>';
            } else if (form.accumulated_failed_units === 0) {
                failedUnitsBadge = ' <span class="badge success">EXCELLENT</span>';
            }
            
            let filesHTML = '';
            if (form.grades_screenshot) {
                filesHTML += `<a href="${form.grades_screenshot}" target="_blank" class="file-link">📄 View Grades Screenshot</a>`;
            }
            if (form.booklet_file) {
                filesHTML += `<a href="${form.booklet_file}" target="_blank" class="file-link">📕 View Academic Booklet</a>`;
            }
            
            let notesHTML = '';
            if (formDataObj.additional_notes) {
                notesHTML = `
                    <div class="notes-box">
                        <strong>Student Notes:</strong><br>
                        ${escapeHtml(formDataObj.additional_notes)}
                    </div>
                `;
            }
            
            let currentCoursesHTML = '';
            if (form.courses.current && form.courses.current.length > 0) {
                currentCoursesHTML = `
                    <table class="courses-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Units</th>
                                <th>Prerequisites</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${form.courses.current.map(course => `
                                <tr>
                                    <td><strong>${course.course_code}</strong></td>
                                    <td>${course.units}</td>
                                    <td>
                                        ${course.prerequisites && course.prerequisites.length > 0 ? 
                                            course.prerequisites.map(p => 
                                                `<span class="prereq-item">${p.prerequisite_code} (${p.prerequisite_type}) - ${p.grade_received || 'N/A'}</span>`
                                            ).join('') 
                                            : '<span style="color: #999;">None</span>'
                                        }
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
            } else {
                currentCoursesHTML = '<p style="color: #999; font-style: italic;">No courses listed</p>';
            }
            
            let commentsHTML = '';
            if (form.adviser_comments) {
                commentsHTML = `
                    <div class="content-card">
                        <h3>Previous Review Comments</h3>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #00A36C;">
                            ${escapeHtml(form.adviser_comments)}
                        </div>
                        <div style="margin-top: 10px; font-size: 13px; color: #666;">
                            Reviewed on: ${formatDateTime(form.reviewed_at)}
                        </div>
                    </div>
                `;
            }
            
            let actionHTML = '';
            if (form.status === 'pending' || form.status === 'revision_requested') {
                actionHTML = `
                    <div class="action-section">
                        <h3>Review Actions</h3>
                        <div style="margin-top: 15px;">
                            <label for="reviewComments" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                                Review Comments:
                            </label>
                            <textarea id="reviewComments" placeholder="Enter your comments or feedback for the student..."></textarea>
                        </div>
                        <div class="action-buttons">
                            <button class="btn btn-approve" onclick="updateStatus('approved')">✓ Approve Form</button>
                            <button class="btn btn-revision" onclick="updateStatus('revision_requested')">↻ Request Revision</button>
                            <button class="btn btn-reject" onclick="updateStatus('rejected')">✗ Reject Form</button>
                        </div>
                    </div>
                `;
            }
            
            const html = `
                <div class="content-card">
                    <h2>Student Information ${statusBadge}</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Student ID</div>
                            <div class="info-value">${form.id_number}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Student Name</div>
                            <div class="info-value">${form.student_name}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value">${form.student_email}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Program</div>
                            <div class="info-value">${form.program}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Year Level</div>
                            <div class="info-value">${form.year_level}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Failed Units (Accumulated)</div>
                            <div class="info-value">${form.accumulated_failed_units} units${failedUnitsBadge}</div>
                        </div>
                    </div>
                    
                    <h3>Academic Period</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Academic Year</div>
                            <div class="info-value">${formDataObj.academic_year || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Term</div>
                            <div class="info-value">${formDataObj.term || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Submitted</div>
                            <div class="info-value">${formatDateTime(form.submitted_at)}</div>
                        </div>
                    </div>
                    
                    <h3>Academic Performance</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Current Year Failed Units</div>
                            <div class="info-value">${formDataObj.current_year_failed_units || '0'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Overall Failed Units</div>
                            <div class="info-value">${formDataObj.overall_failed_units || '0'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Previous Term GPA</div>
                            <div class="info-value">${formDataObj.previous_term_gpa || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Cumulative GPA</div>
                            <div class="info-value">${formDataObj.cumulative_gpa || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Max Course Load (Units)</div>
                            <div class="info-value">${formDataObj.max_course_load_units || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Total Enrolled Units</div>
                            <div class="info-value">${formDataObj.total_enrolled_units || 'N/A'}</div>
                        </div>
                    </div>
                    
                    ${formDataObj.trimestral_honors ? `
                        <div class="info-grid" style="margin-top: 15px;">
                            <div class="info-item">
                                <div class="info-label">Trimestral Honors</div>
                                <div class="info-value">${formDataObj.trimestral_honors}</div>
                            </div>
                        </div>
                    ` : ''}
                    
                    ${notesHTML}
                    
                    <h3>Uploaded Documents</h3>
                    <div style="margin-top: 10px;">
                        ${filesHTML || '<p style="color: #999; font-style: italic;">No documents uploaded</p>'}
                    </div>
                </div>
                
                <div class="content-card">
                    <h2>Enrolled Courses</h2>
                    ${currentCoursesHTML}
                </div>
                
                ${commentsHTML}
                ${actionHTML}
            `;
            
            document.getElementById('formContent').innerHTML = html;
        }

        function updateStatus(status) {
            const comments = document.getElementById('reviewComments').value;
            
            let confirmMsg = '';
            switch(status) {
                case 'approved':
                    confirmMsg = 'Are you sure you want to approve this advising form?';
                    break;
                case 'rejected':
                    confirmMsg = 'Are you sure you want to reject this advising form?';
                    break;
                case 'revision_requested':
                    confirmMsg = 'Request revision for this advising form?';
                    break;
            }
            
            if (!confirm(confirmMsg)) return;
            
            const formData = new FormData();
            formData.append('action', 'update_form_status');
            formData.append('form_id', formId);
            formData.append('status', status);
            formData.append('comments', comments);
            
            fetch('prof_advising_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Form status updated successfully!');
                    loadFormDetails(); // Reload to show updated status
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating form status');
            });
        }

        function formatDateTime(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
