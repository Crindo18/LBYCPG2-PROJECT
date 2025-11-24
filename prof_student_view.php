<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'professor') {
    header('Location: login.php');
    exit();
}

$professor_id = $_SESSION['user_id'];
$student_id = $_GET['id'] ?? 0;

// Verify this student is assigned to this professor
$stmt = $conn->prepare("
    SELECT 
        s.*,
        CONCAT(s.first_name, ' ', s.middle_name, ' ', s.last_name) as full_name,
        CONCAT(p.first_name, ' ', p.last_name) as adviser_name
    FROM students s
    LEFT JOIN professors p ON p.id = s.advisor_id
    WHERE s.id = ? AND s.advisor_id = ?
");
$stmt->bind_param("ii", $student_id, $professor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Access denied or student not found");
}

$student = $result->fetch_assoc();

// Get professor name
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM professors WHERE id = ?");
$stmt->bind_param("i", $professor_id);
$stmt->execute();
$professor_name = $stmt->get_result()->fetch_assoc()['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - <?php echo htmlspecialchars($student['full_name']); ?></title>
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
        .menu-item:hover { background: rgba(255,255,255,0.1); border-left-color: #BA68C8; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #6a1b9a; }
        .back-btn { padding: 8px 20px; background: #757575; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; margin-right: 10px; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        .student-header { background: linear-gradient(135deg, #6a1b9a 0%, #8e24aa 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .student-header h2 { font-size: 32px; margin-bottom: 10px; }
        .student-header-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
        .student-header-item { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; }
        .student-header-item label { font-size: 12px; opacity: 0.9; display: block; margin-bottom: 5px; }
        .student-header-item value { font-size: 18px; font-weight: 600; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; flex-wrap: wrap; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #6a1b9a; border-bottom-color: #6a1b9a; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 22px; color: #6a1b9a; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .info-item { padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .info-item label { font-size: 13px; color: #666; display: block; margin-bottom: 5px; }
        .info-item value { font-size: 16px; font-weight: 600; color: #333; }
        
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: #555; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tr:hover { background: #f8f9fa; }
        
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.warning { background: #fff3cd; color: #856404; }
        .badge.danger { background: #f8d7da; color: #721c24; }
        .badge.info { background: #d1ecf1; color: #0c5460; }
        .badge.pending { background: #e3f2fd; color: #1565c0; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .alert.danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .alert.info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.3s; }
        .btn-primary { background: #6a1b9a; color: white; }
        .btn-primary:hover { background: #4a148c; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .empty-state img { width: 120px; opacity: 0.3; margin-bottom: 20px; }
        
        .gpa-display { display: flex; align-items: center; gap: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px; margin-bottom: 20px; }
        .gpa-item { text-align: center; }
        .gpa-item label { font-size: 14px; color: #666; display: block; margin-bottom: 8px; }
        .gpa-item .value { font-size: 42px; font-weight: bold; color: #6a1b9a; }
        
        .action-buttons { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
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
                <a href="prof_email.php" class="menu-item">Email System</a>
                <a href="prof_schedule.php" class="menu-item">Schedule</a>
                <a href="prof_grade_approvals.php" class="menu-item">Grade Approvals</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Student Profile</h1>
                <div>
                    <a href="prof_advisees.php" class="back-btn">← Back to Advisees</a>
                    <a href="login.php" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <!-- Student Header -->
            <div class="student-header">
                <h2><?php echo htmlspecialchars($student['full_name']); ?></h2>
                <p>ID: <?php echo htmlspecialchars($student['id_number']); ?> • <?php echo htmlspecialchars($student['program']); ?></p>
                
                <div class="student-header-grid">
                    <div class="student-header-item">
                        <label>Email</label>
                        <value><?php echo htmlspecialchars($student['email']); ?></value>
                    </div>
                    <div class="student-header-item">
                        <label>Phone</label>
                        <value><?php echo htmlspecialchars($student['phone_number']); ?></value>
                    </div>
                    <div class="student-header-item">
                        <label>Failed Units</label>
                        <value><?php echo $student['accumulated_failed_units']; ?> / 30</value>
                    </div>
                    <div class="student-header-item">
                        <label>Advising Status</label>
                        <value><?php echo $student['advising_cleared'] ? '✓ Cleared' : '⏳ Pending'; ?></value>
                    </div>
                </div>
            </div>
            
            <!-- Failed Units Alert -->
            <?php if ($student['accumulated_failed_units'] >= 25): ?>
                <div class="alert danger">
                    <strong>⚠ CRITICAL:</strong> This student has <?php echo $student['accumulated_failed_units']; ?> failed units and is approaching the 30-unit limit. Immediate intervention required.
                </div>
            <?php elseif ($student['accumulated_failed_units'] >= 15): ?>
                <div class="alert warning">
                    <strong>⚠ WARNING:</strong> This student has <?php echo $student['accumulated_failed_units']; ?> failed units. Close monitoring recommended.
                </div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('overview')">Overview</button>
                <button class="tab-btn" onclick="switchTab('booklet')">Academic Booklet</button>
                <button class="tab-btn" onclick="switchTab('grades')">Term Grades</button>
                <button class="tab-btn" onclick="switchTab('studyplans')">Study Plans</button>
                <button class="tab-btn" onclick="switchTab('concerns')">Concerns</button>
            </div>
            
            <!-- Overview Tab -->
            <div id="overview" class="tab-content active">
                <div class="content-card">
                    <h3>Personal Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Full Name</label>
                            <value><?php echo htmlspecialchars($student['full_name']); ?></value>
                        </div>
                        <div class="info-item">
                            <label>ID Number</label>
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
                        <div class="info-item">
                            <label>College</label>
                            <value><?php echo htmlspecialchars($student['college']); ?></value>
                        </div>
                        <div class="info-item">
                            <label>Department</label>
                            <value>DECE</value>
                        </div>
                        <div class="info-item">
                            <label>Program</label>
                            <value><?php echo htmlspecialchars($student['program']); ?></value>
                        </div>
                        <div class="info-item">
                            <label>Specialization</label>
                            <value><?php echo htmlspecialchars($student['specialization'] ?? 'N/A'); ?></value>
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
                    <h3>Academic Status</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Adviser</label>
                            <value><?php echo htmlspecialchars($student['adviser_name']); ?></value>
                        </div>
                        <div class="info-item">
                            <label>Accumulated Failed Units</label>
                            <value><?php echo $student['accumulated_failed_units']; ?> / 30</value>
                        </div>
                        <div class="info-item">
                            <label>Advising Cleared</label>
                            <value>
                                <?php if ($student['advising_cleared']): ?>
                                    <span class="badge success">✓ Cleared</span>
                                <?php else: ?>
                                    <span class="badge pending">⏳ Pending</span>
                                <?php endif; ?>
                            </value>
                        </div>
                        <div class="info-item">
                            <label>Account Created</label>
                            <value><?php echo date('M d, Y', strtotime($student['created_at'])); ?></value>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <?php if (!$student['advising_cleared']): ?>
                            <button class="btn btn-success" onclick="clearStudent()">✓ Clear for Enrollment</button>
                        <?php else: ?>
                            <button class="btn btn-secondary" onclick="unclearStudent()">Revoke Clearance</button>
                        <?php endif; ?>
                        <button class="btn btn-primary" onclick="sendEmail()">✉ Send Email</button>
                    </div>
                </div>
            </div>
            
            <!-- Academic Booklet Tab -->
            <div id="booklet" class="tab-content">
                <div class="content-card">
                    <h3>Academic Booklet</h3>
                    <div id="bookletContent">
                        <div style="text-align: center; padding: 40px; color: #999;">
                            Loading academic records...
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Term Grades Tab -->
            <div id="grades" class="tab-content">
                <div class="content-card">
                    <h3>Term GPA Summary</h3>
                    <div id="gradesContent">
                        <div style="text-align: center; padding: 40px; color: #999;">
                            Loading grades...
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Study Plans Tab -->
            <div id="studyplans" class="tab-content">
                <div class="content-card">
                    <h3>Study Plan Submissions</h3>
                    <div id="studyPlansContent">
                        <div style="text-align: center; padding: 40px; color: #999;">
                            Loading study plans...
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Concerns Tab -->
            <div id="concerns" class="tab-content">
                <div class="content-card">
                    <h3>Student Concerns</h3>
                    <div id="concernsContent">
                        <div style="text-align: center; padding: 40px; color: #999;">
                            Loading concerns...
                        </div>
                    </div>
                </div>
            </div>
            
        </main>
    </div>

    <script>
        const studentId = <?php echo $student_id; ?>;
        
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
            
            // Load data if not loaded
            if (tabName === 'booklet') loadBooklet();
            if (tabName === 'grades') loadGrades();
            if (tabName === 'studyplans') loadStudyPlans();
            if (tabName === 'concerns') loadConcerns();
        }
        
        function loadBooklet() {
            fetch(`prof_api.php?action=get_student_booklet&student_id=${studentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderBooklet(data.records);
                    } else {
                        document.getElementById('bookletContent').innerHTML = '<div class="empty-state">No academic records found</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('bookletContent').innerHTML = '<div class="alert danger">Error loading booklet</div>';
                });
        }
        
        function renderBooklet(records) {
            const container = document.getElementById('bookletContent');
            
            if (records.length === 0) {
                container.innerHTML = '<div class="empty-state">No courses recorded yet</div>';
                return;
            }
            
            // Group by academic year and term
            const grouped = {};
            records.forEach(record => {
                const key = `${record.academic_year} - Term ${record.term}`;
                if (!grouped[key]) grouped[key] = [];
                grouped[key].push(record);
            });
            
            let html = '';
            for (const [period, courses] of Object.entries(grouped)) {
                html += `<h4 style="margin: 20px 0 10px 0; color: #6a1b9a;">${period}</h4>`;
                html += '<div class="table-container"><table class="data-table">';
                html += '<thead><tr><th>Course Code</th><th>Course Name</th><th>Units</th><th>Grade</th><th>Status</th><th>Remarks</th></tr></thead>';
                html += '<tbody>';
                
                courses.forEach(course => {
                    const statusBadge = course.is_failed ? 
                        '<span class="badge danger">Failed</span>' : 
                        '<span class="badge success">Passed</span>';
                    
                    html += `
                        <tr>
                            <td><strong>${course.course_code}</strong></td>
                            <td>${course.course_name || 'N/A'}</td>
                            <td>${course.units}</td>
                            <td><strong>${course.grade || 'N/A'}</strong></td>
                            <td>${statusBadge}</td>
                            <td>${course.remarks || '-'}</td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div>';
            }
            
            container.innerHTML = html;
        }
        
        function loadGrades() {
            fetch(`prof_api.php?action=get_student_gpa&student_id=${studentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderGrades(data.terms);
                    } else {
                        document.getElementById('gradesContent').innerHTML = '<div class="empty-state">No GPA records found</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('gradesContent').innerHTML = '<div class="alert danger">Error loading grades</div>';
                });
        }
        
        function renderGrades(terms) {
            const container = document.getElementById('gradesContent');
            
            if (terms.length === 0) {
                container.innerHTML = '<div class="empty-state">No GPA data available</div>';
                return;
            }
            
            let html = '<div class="table-container"><table class="data-table">';
            html += '<thead><tr><th>Period</th><th>Term GPA</th><th>CGPA</th><th>Units Taken</th><th>Units Passed</th><th>Units Failed</th><th>Honors</th></tr></thead>';
            html += '<tbody>';
            
            terms.forEach(term => {
                const honors = term.trimestral_honors ? 
                    `<span class="badge success">${term.trimestral_honors}</span>` : '-';
                
                html += `
                    <tr>
                        <td><strong>${term.academic_year} - Term ${term.term}</strong></td>
                        <td><strong>${term.term_gpa || 'N/A'}</strong></td>
                        <td><strong>${term.cgpa || 'N/A'}</strong></td>
                        <td>${term.total_units_taken}</td>
                        <td>${term.total_units_passed}</td>
                        <td>${term.total_units_failed}</td>
                        <td>${honors}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }
        
        function loadStudyPlans() {
            fetch(`prof_api.php?action=get_student_study_plans&student_id=${studentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderStudyPlans(data.plans);
                    } else {
                        document.getElementById('studyPlansContent').innerHTML = '<div class="empty-state">No study plans submitted</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('studyPlansContent').innerHTML = '<div class="alert danger">Error loading study plans</div>';
                });
        }
        
        function renderStudyPlans(plans) {
            const container = document.getElementById('studyPlansContent');
            
            if (plans.length === 0) {
                container.innerHTML = '<div class="empty-state">No study plans submitted yet</div>';
                return;
            }
            
            let html = '<div class="table-container"><table class="data-table">';
            html += '<thead><tr><th>Period</th><th>Submitted</th><th>Certified</th><th>Cleared</th><th>Actions</th></tr></thead>';
            html += '<tbody>';
            
            plans.forEach(plan => {
                const certBadge = plan.certified ? '<span class="badge success">✓ Yes</span>' : '<span class="badge pending">No</span>';
                const clearBadge = plan.cleared ? '<span class="badge success">✓ Cleared</span>' : '<span class="badge pending">Pending</span>';
                
                html += `
                    <tr>
                        <td><strong>${plan.academic_year} - ${plan.term}</strong></td>
                        <td>${formatDate(plan.submission_date)}</td>
                        <td>${certBadge}</td>
                        <td>${clearBadge}</td>
                        <td><a href="prof_study_plan_view.php?id=${plan.id}" class="btn btn-primary" style="padding: 6px 12px; font-size: 13px;">Review</a></td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }
        
        function loadConcerns() {
            fetch(`prof_api.php?action=get_student_concerns&student_id=${studentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderConcerns(data.concerns);
                    } else {
                        document.getElementById('concernsContent').innerHTML = '<div class="empty-state">No concerns submitted</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('concernsContent').innerHTML = '<div class="alert danger">Error loading concerns</div>';
                });
        }
        
        function renderConcerns(concerns) {
            const container = document.getElementById('concernsContent');
            
            if (concerns.length === 0) {
                container.innerHTML = '<div class="empty-state">No concerns submitted</div>';
                return;
            }
            
            let html = '';
            concerns.forEach(concern => {
                html += `
                    <div style="padding: 20px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #6a1b9a;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <strong style="color: #6a1b9a;">Term: ${concern.term}</strong>
                            <span style="font-size: 13px; color: #666;">${formatDate(concern.submission_date)}</span>
                        </div>
                        <p style="color: #333; line-height: 1.6;">${concern.concern}</p>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function clearStudent() {
            if (!confirm('Clear this student for enrollment?')) return;
            
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
                    alert('Student cleared successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        function unclearStudent() {
            if (!confirm('Revoke clearance for this student?')) return;
            
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
                    alert('Clearance revoked successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        function sendEmail() {
            alert('Email feature coming soon!');
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    </script>
</body>
</html>