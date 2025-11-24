<?php
require_once 'config.php';

echo "<style>
body { font-family: Arial; max-width: 900px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
.card { background: white; padding: 25px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h2 { color: #6a1b9a; border-bottom: 2px solid #6a1b9a; padding-bottom: 10px; }
.success { color: #28a745; font-weight: bold; }
.error { color: #dc3545; font-weight: bold; }
.warning { color: #ff9800; font-weight: bold; }
pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
.btn { padding: 12px 24px; background: #6a1b9a; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px 5px; }
</style>";

echo "<h1>🔧 Professor Dashboard Fix Tool</h1>";

// Step 1: Check and fix study_plans table
echo "<div class='card'>";
echo "<h2>Step 1: Checking study_plans Table</h2>";

// Check if status column exists
$result = $conn->query("SHOW COLUMNS FROM study_plans LIKE 'status'");

if ($result->num_rows === 0) {
    echo "<p class='warning'>⚠ 'status' column missing from study_plans table. Adding...</p>";
    
    $conn->query("ALTER TABLE study_plans ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER reupload_reason");
    
    echo "<p class='success'>✓ 'status' column added</p>";
} else {
    echo "<p class='success'>✓ 'status' column exists</p>";
}

// Check other required columns
$required_columns = ['created_at', 'updated_at'];
foreach ($required_columns as $col) {
    $result = $conn->query("SHOW COLUMNS FROM study_plans LIKE '$col'");
    if ($result->num_rows === 0) {
        echo "<p class='warning'>⚠ '$col' column missing. Adding...</p>";
        
        if ($col === 'created_at') {
            $conn->query("ALTER TABLE study_plans ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER screenshot_reupload_requested");
        } else {
            $conn->query("ALTER TABLE study_plans ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }
        
        echo "<p class='success'>✓ '$col' column added</p>";
    } else {
        echo "<p class='success'>✓ '$col' column exists</p>";
    }
}

echo "</div>";

// Step 2: Check professor record
echo "<div class='card'>";
echo "<h2>Step 2: Checking Professor Record</h2>";

$stmt = $conn->prepare("SELECT * FROM professors WHERE id_number = '10012345'");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $prof = $result->fetch_assoc();
    echo "<p class='success'>✓ Professor exists (ID: {$prof['id']})</p>";
    echo "<p>Name: {$prof['first_name']} {$prof['last_name']}</p>";
    $prof_id = $prof['id'];
} else {
    echo "<p class='error'>✗ Professor not found!</p>";
    echo "<p>Run complete_login_fix.php first</p>";
    exit();
}

echo "</div>";

// Step 3: Check assigned students
echo "<div class='card'>";
echo "<h2>Step 3: Checking Assigned Students</h2>";

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE advisor_id = ?");
$stmt->bind_param("i", $prof_id);
$stmt->execute();
$student_count = $stmt->get_result()->fetch_assoc()['count'];

echo "<p>Assigned students: <strong>$student_count</strong></p>";

if ($student_count === 0) {
    echo "<p class='warning'>⚠ No students assigned to this professor</p>";
    echo "<p><strong>Action Required:</strong></p>";
    echo "<ol>";
    echo "<li>Login as admin</li>";
    echo "<li>Go to Admin → Advising Assignments</li>";
    echo "<li>Find professor: Maria Santos Garcia (ID: 10012345)</li>";
    echo "<li>Click 'Manage' and assign some students</li>";
    echo "</ol>";
} else {
    echo "<p class='success'>✓ Professor has $student_count assigned student(s)</p>";
    
    // Show list
    $stmt = $conn->prepare("
        SELECT id, id_number, CONCAT(first_name, ' ', last_name) as name, 
               program, accumulated_failed_units, advising_cleared 
        FROM students 
        WHERE advisor_id = ? 
        LIMIT 10
    ");
    $stmt->bind_param("i", $prof_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; margin-top: 15px;'>";
    echo "<tr><th>ID Number</th><th>Name</th><th>Program</th><th>Failed Units</th><th>Cleared</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $cleared = $row['advising_cleared'] ? '✓ Yes' : '✗ No';
        echo "<tr>";
        echo "<td>{$row['id_number']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "<td>" . str_replace('BS ', '', $row['program']) . "</td>";
        echo "<td>{$row['accumulated_failed_units']}</td>";
        echo "<td>$cleared</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

echo "</div>";

// Step 4: Test API endpoints
echo "<div class='card'>";
echo "<h2>Step 4: Testing API Endpoints</h2>";

// Simulate session
$_SESSION['user_id'] = $prof_id;
$_SESSION['user_type'] = 'professor';

echo "<p class='success'>✓ Session simulated for testing</p>";
echo "<p>User ID: {$_SESSION['user_id']}, Type: {$_SESSION['user_type']}</p>";

// Test get_dashboard_stats
echo "<h3>Testing: get_dashboard_stats</h3>";
try {
    // Total advisees
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM students WHERE advisor_id = ?");
    $stmt->bind_param("i", $prof_id);
    $stmt->execute();
    $total_advisees = $stmt->get_result()->fetch_assoc()['total'];
    
    // Pending study plans
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT sp.id) as pending
        FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        WHERE s.advisor_id = ? AND sp.status = 'pending'
    ");
    $stmt->bind_param("i", $prof_id);
    $stmt->execute();
    $pending_plans = $stmt->get_result()->fetch_assoc()['pending'];
    
    // Cleared students
    $stmt = $conn->prepare("SELECT COUNT(*) as cleared FROM students WHERE advisor_id = ? AND advising_cleared = 1");
    $stmt->bind_param("i", $prof_id);
    $stmt->execute();
    $cleared_students = $stmt->get_result()->fetch_assoc()['cleared'];
    
    // At-risk students
    $stmt = $conn->prepare("SELECT COUNT(*) as at_risk FROM students WHERE advisor_id = ? AND accumulated_failed_units >= 15");
    $stmt->bind_param("i", $prof_id);
    $stmt->execute();
    $at_risk_students = $stmt->get_result()->fetch_assoc()['at_risk'];
    
    $stats = [
        'total_advisees' => $total_advisees,
        'pending_plans' => $pending_plans,
        'cleared_students' => $cleared_students,
        'at_risk_students' => $at_risk_students
    ];
    
    echo "<p class='success'>✓ Dashboard stats query successful</p>";
    echo "<pre>" . json_encode($stats, JSON_PRETTY_PRINT) . "</pre>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
}

// Test get_my_advisees
echo "<h3>Testing: get_my_advisees</h3>";
try {
    $stmt = $conn->prepare("
        SELECT 
            s.id,
            s.id_number,
            s.program,
            s.email,
            s.phone_number,
            s.accumulated_failed_units,
            s.advising_cleared,
            CONCAT(s.first_name, ' ', s.last_name) as full_name
        FROM students s
        WHERE s.advisor_id = ?
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->bind_param("i", $prof_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo "<p class='success'>✓ Advisees query successful</p>";
    echo "<p>Found " . count($students) . " advisee(s)</p>";
    
    if (count($students) > 0) {
        echo "<pre>" . json_encode($students[0], JSON_PRETTY_PRINT) . "</pre>";
        echo "<p><em>Showing first student only...</em></p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "</div>";

// Step 5: Check file permissions
echo "<div class='card'>";
echo "<h2>Step 5: Checking Files</h2>";

$files_to_check = [
    'prof_api.php',
    'prof_dashboard.php',
    'prof_advisees.php',
    'prof_student_view.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        echo "<p class='success'>✓ $file exists (permissions: $perms)</p>";
    } else {
        echo "<p class='error'>✗ $file NOT FOUND</p>";
    }
}

echo "</div>";

// Final summary
echo "<div class='card' style='background: #d4edda; border: 2px solid #28a745;'>";
echo "<h2 style='color: #155724;'>✅ Diagnostic Complete!</h2>";

if ($student_count > 0) {
    echo "<h3>Everything looks good! Ready to test:</h3>";
    echo "<ol>";
    echo "<li>Clear browser cache (Ctrl + Shift + Delete)</li>";
    echo "<li>Open browser console (F12)</li>";
    echo "<li>Go to: <a href='prof_dashboard.php'>prof_dashboard.php</a></li>";
    echo "<li>Check console for any errors</li>";
    echo "<li>Dashboard should load with stats</li>";
    echo "</ol>";
    
    echo "<div style='background: white; padding: 20px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h3>Expected Dashboard Stats:</h3>";
    echo "<ul>";
    echo "<li><strong>Total Advisees:</strong> $total_advisees</li>";
    echo "<li><strong>Pending Plans:</strong> $pending_plans</li>";
    echo "<li><strong>Cleared Students:</strong> $cleared_students</li>";
    echo "<li><strong>At-Risk Students:</strong> $at_risk_students</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<a href='prof_dashboard.php' class='btn' style='font-size: 18px; margin-top: 20px;'>🚀 Open Professor Dashboard</a>";
} else {
    echo "<h3>⚠ Action Required:</h3>";
    echo "<p><strong>No students are assigned to this professor yet.</strong></p>";
    echo "<p>The dashboard will work, but all stats will show 0.</p>";
    echo "<p><strong>To assign students:</strong></p>";
    echo "<ol>";
    echo "<li>Login as admin (username: admin, password: password)</li>";
    echo "<li>Go to Admin Portal → Advising Assignments</li>";
    echo "<li>Find Maria Santos Garcia (10012345)</li>";
    echo "<li>Click 'Manage'</li>";
    echo "<li>Select and assign some students</li>";
    echo "</ol>";
    
    echo "<a href='admin_dashboard.php' class='btn' style='font-size: 18px; margin-top: 20px; background: #dc3545;'>Go to Admin Dashboard</a>";
}

echo "</div>";

?>