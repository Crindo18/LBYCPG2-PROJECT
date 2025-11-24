<?php
require_once 'config.php';
requireUserType('student');

$student_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle study plan submissio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_study_plan'])) {
    try {
        $conn->beginTransaction();
        
        $term = $_POST['term'];
        $academic_year = $_POST['academic_year'];
        $wants_meeting = isset($_POST['meeting']) && $_POST['meeting'] === 'yes' ? 1 : 0;
        $schedule_id = $wants_meeting && !empty($_POST['schedule']) ? $_POST['schedule'] : null;
        
        // Insert study plan
        $stmt = $conn->prepare("
            INSERT INTO study_plans (student_id, term, academic_year, wants_meeting, selected_schedule_id, certified)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$student_id, $term, $academic_year, $wants_meeting, $schedule_id]);
        $plan_id = $conn->lastInsertId();
        
        // Insert current subjects
        if (!empty($_POST['current_subjects'])) {
            foreach ($_POST['current_subjects'] as $subject) {
                $stmt = $conn->prepare("
                    INSERT INTO current_subjects (study_plan_id, subject_code, subject_name, units)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$plan_id, $subject['code'], $subject['name'], $subject['units']]);
                $subject_id = $conn->lastInsertId();
                
                // Insert prerequisites
                if (!empty($subject['prerequisites'])) {
                    foreach ($subject['prerequisites'] as $prereq) {
                        $stmt = $conn->prepare("
                            INSERT INTO current_subject_prerequisites (current_subject_id, prerequisite_code, prerequisite_type)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$subject_id, $prereq['code'], $prereq['type']]);
                    }
                }
            }
        }
        
        // Insert planned subjects
        if (!empty($_POST['planned_subjects'])) {
            foreach ($_POST['planned_subjects'] as $subject) {
                $stmt = $conn->prepare("
                    INSERT INTO planned_subjects (study_plan_id, subject_code, subject_name, units)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$plan_id, $subject['code'], $subject['name'], $subject['units']]);
                $subject_id = $conn->lastInsertId();
                
                // Insert prerequisites
                if (!empty($subject['prerequisites'])) {
                    foreach ($subject['prerequisites'] as $prereq) {
                        $stmt = $conn->prepare("
                            INSERT INTO planned_subject_prerequisites (planned_subject_id, prerequisite_code, prerequisite_type)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$subject_id, $prereq['code'], $prereq['type']]);
                    }
                }
            }
        }
        
        $conn->commit();
        $message = 'Study plan submitted successfully!';
        $message_type = 'success';
    } catch (Exception $e) {
        $conn->rollBack();
        $message = 'Error submitting study plan: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle booklet record submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_booklet'])) {
    try {
        $records = $_POST['booklet_records'];
        
        foreach ($records as $record) {
            if (!empty($record['course_code'])) {
                $stmt = $conn->prepare("
                    INSERT INTO student_advising_booklet 
                    (student_id, academic_year, term, course_code, course_name, units, grade, term_gpa, cgpa, accumulated_failure)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    course_name = VALUES(course_name),
                    units = VALUES(units),
                    grade = VALUES(grade),
                    term_gpa = VALUES(term_gpa),
                    cgpa = VALUES(cgpa),
                    accumulated_failure = VALUES(accumulated_failure)
                ");
                
                $stmt->execute([
                    $student_id,
                    $record['academic_year'],
                    $record['term'],
                    $record['course_code'],
                    $record['course_name'],
                    $record['units'],
                    $record['grade'] ?: null,
                    $record['term_gpa'] ?: null,
                    $record['cgpa'] ?: null,
                    $record['accumulated_failure'] ?: 0
                ]);
            }
        }
        
        $message = 'Academic booklet updated successfully!';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Error saving booklet: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get existing booklet records
$stmt = $conn->prepare("
    SELECT * FROM student_advising_booklet
    WHERE student_id = ?
    ORDER BY academic_year DESC, term DESC
");
$stmt->execute([$student_id]);
$booklet_records = $stmt->fetchAll();

// Get available schedules
$stmt = $conn->prepare("
    SELECT s.*, CONCAT(p.first_name, ' ', p.last_name) as professor_name
    FROM advising_schedules s
    JOIN professors p ON s.professor_id = p.id
    WHERE s.is_booked = 0 AND s.available_date >= CURDATE()
    ORDER BY s.available_date, s.available_time
");
$stmt->execute();
$schedules = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Advising - Academic Advising System</title>
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
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .tab-btn {
            padding: 12px 30px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background: #1e3c72;
            color: white;
            border-color: #1e3c72;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .booklet-editor {
            margin-top: 20px;
        }
        
        .booklet-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .booklet-table th {
            background: #f8f9fa;
            padding: 10px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #e0e0e0;
        }
        
        .booklet-table td {
            padding: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .booklet-table input {
            width: 100%;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 13px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .form-section h3 {
            color: #1e3c72;
            margin-bottom: 20px;
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .subject-entry {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #e0e0e0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .prerequisite-entry {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
        }
        
        .prerequisite-entry input {
            flex: 2;
        }
        
        .prerequisite-entry select {
            flex: 1;
        }
        
        .btn-add,
        .btn-remove {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn-add {
            background: #2196F3;
            color: white;
        }
        
        .btn-add:hover {
            background: #1976D2;
        }
        
        .btn-remove {
            background: #dc3545;
            color: white;
        }
        
        .btn-remove:hover {
            background: #c82333;
        }
        
        .btn-submit {
            padding: 15px 40px;
            background: #1e3c72;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .btn-submit:hover:not(:disabled) {
            background: #2a5298;
        }
        
        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .certification-section {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
        }
        
        .checkbox-group {
            margin: 15px 0;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .meeting-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .radio-group label {
            display: block;
            margin: 10px 0;
            font-size: 14px;
        }
        
        .schedule-options {
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 5px;
            display: none;
        }
        
        .schedule-options.show {
            display: block;
        }
        
        .schedule-item {
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
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
                <h1>Academic Advising</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('booklet')">Academic Booklet</button>
                <button class="tab-btn" onclick="switchTab('studyplan')">Study Plan</button>
            </div>
            
            <!-- Academic Booklet Tab -->
            <div id="booklet" class="tab-content active">
                <div class="content-card">
                    <h2>Academic Advising Booklet</h2>
                    <p style="margin-bottom: 20px; color: #666;">Add and edit your academic records for each term. This information will be used to calculate your GPA and track your progress.</p>
                    
                    <form method="POST" class="booklet-editor">
                        <button type="button" class="btn-add" onclick="addBookletRow()" style="margin-bottom: 15px;">+ Add New Record</button>
                        
                        <div style="overflow-x: auto;">
                            <table class="booklet-table" id="bookletTable">
                                <thead>
                                    <tr>
                                        <th>Academic Year</th>
                                        <th>Term</th>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Units</th>
                                        <th>Grade</th>
                                        <th>Term GPA</th>
                                        <th>CGPA</th>
                                        <th>Accumulated Failure</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($booklet_records as $record): ?>
                                    <tr>
                                        <td><input type="text" name="booklet_records[][academic_year]" value="<?php echo htmlspecialchars($record['academic_year']); ?>" placeholder="2024-2025" required></td>
                                        <td><input type="number" name="booklet_records[][term]" value="<?php echo $record['term']; ?>" min="1" max="3" required></td>
                                        <td><input type="text" name="booklet_records[][course_code]" value="<?php echo htmlspecialchars($record['course_code']); ?>" required></td>
                                        <td><input type="text" name="booklet_records[][course_name]" value="<?php echo htmlspecialchars($record['course_name']); ?>"></td>
                                        <td><input type="number" name="booklet_records[][units]" value="<?php echo $record['units']; ?>" min="1" required></td>
                                        <td><input type="number" step="0.01" name="booklet_records[][grade]" value="<?php echo $record['grade']; ?>" min="0" max="4"></td>
                                        <td><input type="number" step="0.01" name="booklet_records[][term_gpa]" value="<?php echo $record['term_gpa']; ?>" min="0" max="4"></td>
                                        <td><input type="number" step="0.01" name="booklet_records[][cgpa]" value="<?php echo $record['cgpa']; ?>" min="0" max="4"></td>
                                        <td><input type="number" name="booklet_records[][accumulated_failure]" value="<?php echo $record['accumulated_failure']; ?>" min="0"></td>
                                        <td><button type="button" class="btn-remove" onclick="this.parentElement.parentElement.remove()">Remove</button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <button type="submit" name="save_booklet" class="btn-submit">Save Booklet</button>
                    </form>
                </div>
            </div>
            
            <!-- Study Plan Tab -->
            <div id="studyplan" class="tab-content">
                <div class="content-card">
                    <h2>Study Plan Submission</h2>
                    
                    <form method="POST" id="studyPlanForm">
                        <div class="form-section">
                            <div class="form-row" style="grid-template-columns: 1fr 1fr;">
                                <div class="form-group">
                                    <label>Academic Year *</label>
                                    <input type="text" name="academic_year" placeholder="e.g., 2024-2025" required>
                                </div>
                                <div class="form-group">
                                    <label>Term *</label>
                                    <input type="text" name="term" placeholder="e.g., Term 2" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>A. Current Term Subjects</h3>
                            <div id="currentSubjects"></div>
                            <button type="button" class="btn-add" onclick="addSubject('current')">+ Add Subject</button>
                        </div>
                        
                        <div class="form-section">
                            <h3>B. Planned Subjects Next Term</h3>
                            <div id="plannedSubjects"></div>
                            <button type="button" class="btn-add" onclick="addSubject('planned')">+ Add Subject</button>
                        </div>
                        
                        <div class="certification-section">
                            <h3 style="margin-bottom: 20px;">Certification</h3>
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" class="cert-checkbox" required>
                                    <span>I hereby certify that <strong>ALL PREREQUISITE REQUIREMENTS</strong> in relation to my CURRENTLY ENROLLED COURSES have been fully satisfied.</span>
                                </label>
                                <label>
                                    <input type="checkbox" class="cert-checkbox" required>
                                    <span>I hereby certify that I have checked <strong>ALL PREREQUISITE REQUIREMENTS</strong> in relation to my planned courses for NEXT TERM.</span>
                                </label>
                                <label>
                                    <input type="checkbox" class="cert-checkbox" required>
                                    <span>By submitting this form, I hereby declare that all information provided are <strong>true and correct</strong>.</span>
                                </label>
                                <label>
                                    <input type="checkbox" class="cert-checkbox" required>
                                    <span>I understand that this academic advising form is accomplished to ensure compliance with enrollment policies.</span>
                                </label>
                                <label>
                                    <input type="checkbox" class="cert-checkbox" required>
                                    <span>I shall take <strong>full responsibility and accountability</strong> for any violations in course enrollment.</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="meeting-section">
                            <h4>APPOINTMENT with ACADEMIC ADVISER</h4>
                            <p style="margin-bottom: 15px; color: #666;">Do you want to set a meeting with your academic adviser?</p>
                            
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="meeting" value="yes" onchange="toggleSchedule(true)">
                                    Yes, I am scheduling a meeting with my academic adviser.
                                </label>
                                <label>
                                    <input type="radio" name="meeting" value="no" onchange="toggleSchedule(false)" checked>
                                    No, I am waiving my privilege to meet my academic adviser.
                                </label>
                            </div>
                            
                            <div id="scheduleOptions" class="schedule-options">
                                <h5 style="margin-bottom: 10px;">Available Schedule:</h5>
                                <?php foreach ($schedules as $schedule): ?>
                                <div class="schedule-item">
                                    <label>
                                        <input type="radio" name="schedule" value="<?php echo $schedule['id']; ?>">
                                        <?php echo date('l, F j, Y', strtotime($schedule['available_date'])); ?> - 
                                        <?php echo date('g:i A', strtotime($schedule['available_time'])); ?>
                                        (<?php echo htmlspecialchars($schedule['professor_name']); ?>)
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <button type="submit" name="submit_study_plan" class="btn-submit" id="submitBtn" disabled>Submit Study Plan</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function addBookletRow() {
            const tbody = document.querySelector('#bookletTable tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" name="booklet_records[][academic_year]" placeholder="2024-2025" required></td>
                <td><input type="number" name="booklet_records[][term]" min="1" max="3" required></td>
                <td><input type="text" name="booklet_records[][course_code]" required></td>
                <td><input type="text" name="booklet_records[][course_name]"></td>
                <td><input type="number" name="booklet_records[][units]" min="1" required></td>
                <td><input type="number" step="0.01" name="booklet_records[][grade]" min="0" max="4"></td>
                <td><input type="number" step="0.01" name="booklet_records[][term_gpa]" min="0" max="4"></td>
                <td><input type="number" step="0.01" name="booklet_records[][cgpa]" min="0" max="4"></td>
                <td><input type="number" name="booklet_records[][accumulated_failure]" min="0"></td>
                <td><button type="button" class="btn-remove" onclick="this.parentElement.parentElement.remove()">Remove</button></td>
            `;
            tbody.appendChild(row);
        }
        
        let currentSubjectCount = 0;
        let plannedSubjectCount = 0;
        
        function addSubject(type) {
            const container = type === 'current' ? document.getElementById('currentSubjects') : document.getElementById('plannedSubjects');
            const count = type === 'current' ? currentSubjectCount++ : plannedSubjectCount++;
            
            const entry = document.createElement('div');
            entry.className = 'subject-entry';
            entry.innerHTML = `
                <div class="form-row">
                    <div class="form-group">
                        <label>Subject Code</label>
                        <input type="text" name="${type}_subjects[${count}][code]" placeholder="e.g., CSSWENG" required>
                    </div>
                    <div class="form-group">
                        <label>Units</label>
                        <input type="number" name="${type}_subjects[${count}][units]" placeholder="3" min="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Subject Name</label>
                    <input type="text" name="${type}_subjects[${count}][name]" placeholder="Software Engineering">
                </div>
                <div class="form-group">
                    <label>Prerequisites</label>
                    <div id="${type}_prereq_${count}">
                        <div class="prerequisite-entry">
                            <input type="text" name="${type}_subjects[${count}][prerequisites][0][code]" placeholder="Prerequisite Code">
                            <select name="${type}_subjects[${count}][prerequisites][0][type]">
                                <option value="hard">Hard Prerequisite</option>
                                <option value="soft">Soft Prerequisite</option>
                                <option value="co-requisite">Co-requisite</option>
                            </select>
                            <button type="button" class="btn-remove" style="padding: 6px 12px;" onclick="this.parentElement.remove()">Remove</button>
                        </div>
                    </div>
                    <button type="button" class="btn-add" style="margin-top: 5px; font-size: 12px; padding: 6px 12px;" onclick="addPrerequisite('${type}', ${count})">+ Add Prerequisite</button>
                </div>
                <button type="button" class="btn-remove" onclick="this.parentElement.remove()">Remove Subject</button>
            `;
            container.appendChild(entry);
        }
        
        function addPrerequisite(type, subjectIndex) {
            const container = document.getElementById(`${type}_prereq_${subjectIndex}`);
            const prereqCount = container.children.length;
            
            const prereqEntry = document.createElement('div');
            prereqEntry.className = 'prerequisite-entry';
            prereqEntry.innerHTML = `
                <input type="text" name="${type}_subjects[${subjectIndex}][prerequisites][${prereqCount}][code]" placeholder="Prerequisite Code">
                <select name="${type}_subjects[${subjectIndex}][prerequisites][${prereqCount}][type]">
                    <option value="hard">Hard Prerequisite</option>
                    <option value="soft">Soft Prerequisite</option>
                    <option value="co-requisite">Co-requisite</option>
                </select>
                <button type="button" class="btn-remove" style="padding: 6px 12px;" onclick="this.parentElement.remove()">Remove</button>
            `;
            container.appendChild(prereqEntry);
        }
        
        function toggleSchedule(show) {
            const scheduleDiv = document.getElementById('scheduleOptions');
            if (show) {
                scheduleDiv.classList.add('show');
            } else {
                scheduleDiv.classList.remove('show');
            }
        }
        
        // Check certification checkboxes
        function checkCertifications() {
            const checkboxes = document.querySelectorAll('.cert-checkbox');
            const submitBtn = document.getElementById('submitBtn');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            submitBtn.disabled = !allChecked;
        }
        
        document.querySelectorAll('.cert-checkbox').forEach(cb => {
            cb.addEventListener('change', checkCertifications);
        });
        
        // Initialize with one subject each
        addSubject('current');
        addSubject('planned')