<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Advising - Professor Portal</title>
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
            background: #6a1b9a;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            background: #4a148c;
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
            border-left-color: #BA68C8;
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
            color: #6a1b9a;
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
            color: #6a1b9a;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .deadline-section {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #ffc107;
        }
        
        .deadline-section h3 {
            color: #856404;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .btn-set-deadline {
            padding: 10px 24px;
            background: #6a1b9a;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn-set-deadline:hover {
            background: #8e24aa;
        }
        
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .filter-btn.active {
            background: #6a1b9a;
            color: white;
            border-color: #6a1b9a;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge.completed {
            background: #d4edda;
            color: #155724;
        }
        
        .badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge.not-submitted {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-view {
            padding: 6px 12px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-view:hover {
            background: #1976D2;
        }
        
        .btn-clear {
            padding: 6px 12px;
            background: #8e24aa;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            margin-left: 5px;
        }
        
        .btn-clear:hover {
            background: #45a049;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .modal-header h3 {
            color: #6a1b9a;
            font-size: 22px;
        }
        
        .close {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .submission-detail {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .submission-detail h4 {
            color: #555;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .subject-list {
            margin: 10px 0;
        }
        
        .subject-item {
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-radius: 4px;
            border-left: 3px solid #6a1b9a;
        }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Professor Portal</h2>
                <p>Academic Advising System</p>
            </div>
            <nav class="sidebar-menu">
                <a href="prof_dashboard.php" class="menu-item">Dashboard</a>
                <a href="prof_advisees.php" class="menu-item">My Advisees</a>
                <a href="prof_study_plans.php" class="menu-item">Study Plans</a>
                <a href="prof_acadadvising.php" class="menu-item active">Academic Advising</a>
                <a href="prof_reports.php" class="menu-item">Reports</a>
                <a href="prof_email.php" class="menu-item">Email System</a>
                <a href="prof_schedule.php" class="menu-item">Schedule</a>
                <a href="prof_grade_approvals.php" class="menu-item">Grade Approvals</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Academic Advising Management</h1>
                <a href="../login.html" class="logout-btn">Logout</a>
            </div>
            
            <!-- Deadline Section -->
            <div class="content-card">
                <div class="deadline-section">
                    <h3>Set Advising Deadline</h3>
                    <p style="color: #856404; margin-bottom: 15px; font-size: 14px;">Set the deadline for students to submit their academic advising forms</p>
                    
                    <form>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Term</label>
                                <input type="text" placeholder="e.g., AY 2024-2025 Term 2" required>
                            </div>
                            <div class="form-group">
                                <label>Deadline Date</label>
                                <input type="date" required>
                            </div>
                            <button type="submit" class="btn-set-deadline">Set Deadline</button>
                        </div>
                    </form>
                    
                    <p style="margin-top: 15px; font-size: 13px; color: #856404;">
                        <strong>Current Deadline:</strong> December 15, 2025 (AY 2024-2025 Term 1)
                    </p>
                </div>
            </div>
            
            <!-- Students List -->
            <div class="content-card">
                <h2>Assigned Students</h2>
                
                <div class="filter-section">
                    <button class="filter-btn active">All (32)</button>
                    <button class="filter-btn">Completed (24)</button>
                    <button class="filter-btn">Pending (5)</button>
                    <button class="filter-btn">Not Submitted (3)</button>
                    <div class="search-box">
                        <input type="text" placeholder="🔍 Search by ID or name...">
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Course</th>
                                <th>Submission Date</th>
                                <th>Meeting</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>12012345</td>
                                <td>Juan Dela Cruz</td>
                                <td>BS Computer Science</td>
                                <td>Nov 22, 2025</td>
                                <td>Requested</td>
                                <td><span class="badge pending">Pending Review</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewSubmission('12012345')">View</button>
                                        <button class="btn-clear">Clear</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>12012346</td>
                                <td>Maria Garcia</td>
                                <td>BS Computer Science</td>
                                <td>Nov 21, 2025</td>
                                <td>Waived</td>
                                <td><span class="badge completed">Cleared</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewSubmission('12012346')">View</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>12012347</td>
                                <td>Pedro Santos</td>
                                <td>BS Computer Science</td>
                                <td>Nov 20, 2025</td>
                                <td>Requested</td>
                                <td><span class="badge completed">Cleared</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewSubmission('12012347')">View</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>12012348</td>
                                <td>Ana Reyes</td>
                                <td>BS Computer Science</td>
                                <td>Nov 19, 2025</td>
                                <td>Waived</td>
                                <td><span class="badge completed">Cleared</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" onclick="viewSubmission('12012348')">View</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>12012349</td>
                                <td>Carlos Mendoza</td>
                                <td>BS Computer Science</td>
                                <td>-</td>
                                <td>-</td>
                                <td><span class="badge not-submitted">Not Submitted</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <span style="font-size: 13px; color: #999;">No submission yet</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal for viewing submission -->
    <div id="submissionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Study Plan Submission</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <div style="margin-bottom: 20px; padding: 15px; background: #e3f2fd; border-radius: 5px;">
                <p><strong>Student:</strong> Juan Santos Dela Cruz (12012345)</p>
                <p><strong>Submission Date:</strong> November 22, 2025</p>
                <p><strong>Meeting Requested:</strong> Yes</p>
            </div>
            
            <div class="submission-detail">
                <h4>Current Term Subjects</h4>
                <div class="subject-list">
                    <div class="subject-item">
                        <strong>CSSWENG</strong> - 3 units<br>
                        <span style="font-size: 13px; color: #666;">Prerequisites: CSADPRG</span>
                    </div>
                    <div class="subject-item">
                        <strong>CSMCPRO</strong> - 3 units<br>
                        <span style="font-size: 13px; color: #666;">Prerequisites: CSADPRG</span>
                    </div>
                    <div class="subject-item">
                        <strong>CSINTSY</strong> - 3 units<br>
                        <span style="font-size: 13px; color: #666;">Prerequisites: CSALGCM</span>
                    </div>
                </div>
            </div>
            
            <div class="submission-detail">
                <h4>Planned Subjects Next Term</h4>
                <div class="subject-list">
                    <div class="subject-item">
                        <strong>CSNETWK</strong> - 3 units<br>
                        <span style="font-size: 13px; color: #666;">Prerequisites: CCPROG3</span>
                    </div>
                    <div class="subject-item">
                        <strong>CSARCH2</strong> - 3 units<br>
                        <span style="font-size: 13px; color: #666;">Prerequisites: CSARCH1</span>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                <button class="btn-clear" style="padding: 12px 30px; font-size: 15px;">✓ Clear Student for Advising</button>
            </div>
        </div>
    </div>
    
    <script>
        function viewSubmission(studentId) {
            document.getElementById('submissionModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('submissionModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('submissionModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>