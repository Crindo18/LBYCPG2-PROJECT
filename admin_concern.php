<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Concerns - Admin Portal</title>
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
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h2 { font-size: 22px; color: #6a1b9a; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .filter-section { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-btn { padding: 8px 16px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; cursor: pointer; font-size: 14px; transition: all 0.3s; }
        .filter-btn.active { background: #6a1b9a; color: white; border-color: #6a1b9a; }
        .search-box { flex: 1; min-width: 250px; }
        .search-box input { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .concern-list { display: grid; gap: 20px; }
        .concern-card { border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; transition: all 0.3s; }
        .concern-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .concern-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .concern-meta { font-size: 13px; color: #666; }
        .concern-meta strong { color: #6a1b9a; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 10px; }
        .badge.new { background: #ffebee; color: #c62828; }
        .badge.read { background: #e3f2fd; color: #1565c0; }
        .concern-content { color: #333; font-size: 14px; line-height: 1.6; margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .concern-actions { display: flex; gap: 10px; margin-top: 15px; }
        .btn-mark-read { padding: 8px 16px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; }
        .btn-delete { padding: 8px 16px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; }
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
                <a href="admin_advisingassignment.php" class="menu-item">Advising Assignments</a>
                <a href="admin_reports.php" class="menu-item">System Reports</a>
                <a href="admin_bulk_operations.php" class="menu-item">Bulk Operations</a>
                <a href="admin_bulk_upload.php" class="menu-item">Bulk Operations</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Student Concerns</h1>
                <a href="../login.html" class="logout-btn">Logout</a>
            </div>
            
            <div class="content-card">
                <h2>All Student Concerns</h2>
                
                <div class="filter-section">
                    <button class="filter-btn active">All (24)</button>
                    <button class="filter-btn">New (12)</button>
                    <button class="filter-btn">Read (12)</button>
                    <div class="search-box">
                        <input type="text" placeholder="🔍 Search by student ID, name, or term...">
                    </div>
                </div>
                
                <div class="concern-list">
                    <div class="concern-card">
                        <div class="concern-header">
                            <div>
                                <div class="concern-meta">
                                    <strong>Student ID:</strong> 12012345 | <strong>Name:</strong> Juan Dela Cruz | <strong>Term:</strong> AY 2024-2025 Term 1
                                    <span class="badge new">NEW</span>
                                </div>
                                <div class="concern-meta" style="margin-top: 5px;">
                                    <strong>Submitted:</strong> November 22, 2025 at 3:45 PM
                                </div>
                            </div>
                        </div>
                        <div class="concern-content">
                            I am having difficulty balancing the workload for three major courses this term. The deadlines for major projects in CSSWENG, CSMCPRO, and CSINTSY often overlap, making it challenging to give adequate attention to each course. I have been working on time management strategies, but the simultaneous demands are still overwhelming. I would like to discuss potential solutions or accommodations that might help me manage these courses more effectively.
                        </div>
                        <div class="concern-actions">
                            <button class="btn-mark-read">Mark as Read</button>
                            <button class="btn-delete">Delete</button>
                        </div>
                    </div>
                    
                    <div class="concern-card">
                        <div class="concern-header">
                            <div>
                                <div class="concern-meta">
                                    <strong>Student ID:</strong> 12012346 | <strong>Name:</strong> Maria Garcia | <strong>Term:</strong> AY 2024-2025 Term 1
                                    <span class="badge new">NEW</span>
                                </div>
                                <div class="concern-meta" style="margin-top: 5px;">
                                    <strong>Submitted:</strong> November 21, 2025 at 10:30 AM
                                </div>
                            </div>
                        </div>
                        <div class="concern-content">
                            I would like to request clarification on the prerequisites for CSSWENG. According to the checklist, CSADPRG is required, but I've heard from other students that there might be additional co-requisites or recommended subjects. Could you please provide the official requirements so I can plan my enrollment for next term accordingly?
                        </div>
                        <div class="concern-actions">
                            <button class="btn-mark-read">Mark as Read</button>
                            <button class="btn-delete">Delete</button>
                        </div>
                    </div>
                    
                    <div class="concern-card" style="opacity: 0.8;">
                        <div class="concern-header">
                            <div>
                                <div class="concern-meta">
                                    <strong>Student ID:</strong> 12012347 | <strong>Name:</strong> Pedro Santos | <strong>Term:</strong> AY 2024-2025 Term 1
                                    <span class="badge read">READ</span>
                                </div>
                                <div class="concern-meta" style="margin-top: 5px;">
                                    <strong>Submitted:</strong> November 18, 2025 at 2:15 PM
                                </div>
                            </div>
                        </div>
                        <div class="concern-content">
                            I am concerned about my current GPA standing. After receiving my midterm grades, I realized that I might be at risk of accumulating too many failure units. I would appreciate guidance on what steps I can take to improve my situation and avoid any issues with my enrollment eligibility.
                        </div>
                        <div class="concern-actions">
                            <button class="btn-delete">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>