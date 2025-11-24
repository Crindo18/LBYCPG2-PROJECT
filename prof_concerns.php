<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a professor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'professor') {
    header("Location: login.php");
    exit();
}

// Get professor info
$professor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM professors WHERE id = ?");
$stmt->bind_param("i", $professor_id);
$stmt->execute();
$result = $stmt->get_result();
$professor = $result->fetch_assoc();
$professor_name = $professor['firstname'] . ' ' . $professor['lastname'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Concerns - Professor Portal</title>
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

        /* Uniform Sidebar Styles */
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            font-size: 18px;
            margin-bottom: 5px;
            font-weight: 600;
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
            font-size: 14px;
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255, 255, 255, 0.1);
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
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
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

        .logout-btn:hover {
            background: #c82333;
        }

        .content-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .content-card h2 {
            font-size: 22px;
            color: #6a1b9a;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
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

        .concern-list {
            display: grid;
            gap: 20px;
        }

        .concern-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
        }

        .concern-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .concern-card.read {
            opacity: 0.7;
        }

        .concern-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .concern-meta {
            font-size: 13px;
            color: #666;
        }

        .concern-meta strong {
            color: #6a1b9a;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }

        .badge.new {
            background: #ffebee;
            color: #c62828;
        }

        .badge.read {
            background: #e3f2fd;
            color: #1565c0;
        }

        .concern-content {
            color: #333;
            font-size: 14px;
            line-height: 1.6;
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .concern-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-mark-read {
            padding: 8px 16px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-mark-read:hover {
            background: #45a049;
        }

        .btn-delete {
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }

        .stats-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            padding: 10px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #6a1b9a;
        }

        .stat-item .label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .stat-item .value {
            font-size: 24px;
            font-weight: bold;
            color: #6a1b9a;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- UNIFORM SIDEBAR -->
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
                <a href="prof_concerns.php" class="menu-item active">Student Concerns</a>
                <a href="prof_reports.php" class="menu-item">Reports</a>
                <a href="prof_email.php" class="menu-item">Email System</a>
                <a href="prof_schedule.php" class="menu-item">Schedule</a>
                <a href="prof_grade_approvals.php" class="menu-item">Grade Approvals</a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <h1>Student Concerns</h1>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>

            <div class="content-card">
                <div class="stats-bar">
                    <div class="stat-item">
                        <div class="label">Total Concerns</div>
                        <div class="value" id="totalConcerns">0</div>
                    </div>
                    <div class="stat-item">
                        <div class="label">New</div>
                        <div class="value" id="newConcerns">0</div>
                    </div>
                    <div class="stat-item">
                        <div class="label">Read</div>
                        <div class="value" id="readConcerns">0</div>
                    </div>
                </div>

                <h2>All Student Concerns</h2>
                <div class="filter-section">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="new">New</button>
                    <button class="filter-btn" data-filter="read">Read</button>
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="🔍 Search by student ID, name, or term...">
                    </div>
                </div>

                <div class="concern-list" id="concernsList">
                    <div class="loading">Loading concerns...</div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let currentFilter = 'all';
        let allConcerns = [];

        document.addEventListener('DOMContentLoaded', function() {
            loadConcerns();
            setupEventListeners();
        });

        function setupEventListeners() {
            // Filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentFilter = this.getAttribute('data-filter');
                    filterConcerns();
                });
            });

            // Search input
            document.getElementById('searchInput').addEventListener('input', function() {
                filterConcerns();
            });
        }

        function loadConcerns() {
            fetch('prof_concerns_api.php?action=getConcerns')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allConcerns = data.concerns;
                        updateStats(data.stats);
                        filterConcerns();
                    } else {
                        showError('Failed to load concerns: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Error loading concerns');
                });
        }

        function updateStats(stats) {
            document.getElementById('totalConcerns').textContent = stats.total;
            document.getElementById('newConcerns').textContent = stats.new;
            document.getElementById('readConcerns').textContent = stats.read;
        }

        function filterConcerns() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            let filtered = allConcerns.filter(concern => {
                // Filter by status
                if (currentFilter === 'new' && concern.isread == '1') return false;
                if (currentFilter === 'read' && concern.isread == '0') return false;

                // Filter by search term
                if (searchTerm) {
                    const searchableText = `${concern.student_idnumber} ${concern.student_name} ${concern.term} ${concern.concern}`.toLowerCase();
                    if (!searchableText.includes(searchTerm)) return false;
                }

                return true;
            });

            renderConcerns(filtered);
        }

        function renderConcerns(concerns) {
            const container = document.getElementById('concernsList');
            
            if (concerns.length === 0) {
                container.innerHTML = '<div class="no-data">No concerns found</div>';
                return;
            }

            let html = '';
            concerns.forEach(concern => {
                const statusBadge = concern.isread == '1' 
                    ? '<span class="badge read">READ</span>' 
                    : '<span class="badge new">NEW</span>';
                
                const cardClass = concern.isread == '1' ? 'concern-card read' : 'concern-card';
                const markReadBtn = concern.isread == '0' 
                    ? `<button class="btn-mark-read" onclick="markAsRead(${concern.id})">Mark as Read</button>` 
                    : '';

                html += `
                    <div class="${cardClass}">
                        <div class="concern-header">
                            <div>
                                <div class="concern-meta">
                                    <strong>Student ID:</strong> ${concern.student_idnumber} | 
                                    <strong>Name:</strong> ${concern.student_name} | 
                                    <strong>Term:</strong> ${concern.term}
                                    ${statusBadge}
                                </div>
                                <div class="concern-meta" style="margin-top: 5px;">
                                    <strong>Submitted:</strong> ${formatDate(concern.submissiondate)}
                                </div>
                            </div>
                        </div>
                        <div class="concern-content">
                            ${concern.concern}
                        </div>
                        <div class="concern-actions">
                            ${markReadBtn}
                            <button class="btn-delete" onclick="deleteConcern(${concern.id})">Delete</button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function markAsRead(concernId) {
            if (!confirm('Mark this concern as read?')) return;

            fetch('prof_concerns_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=markAsRead&concernId=${concernId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadConcerns(); // Reload to update display
                } else {
                    alert('Failed to mark as read: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error marking concern as read');
            });
        }

        function deleteConcern(concernId) {
            if (!confirm('Are you sure you want to delete this concern? This action cannot be undone.')) return;

            fetch('prof_concerns_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=deleteConcern&concernId=${concernId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadConcerns(); // Reload to update display
                } else {
                    alert('Failed to delete concern: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting concern');
            });
        }

        function formatDate(dateString) {
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

        function showError(message) {
            document.getElementById('concernsList').innerHTML = 
                `<div class="no-data">${message}</div>`;
        }
    </script>
</body>
</html>
