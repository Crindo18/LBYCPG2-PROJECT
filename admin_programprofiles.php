<?php
require_once 'auth_check.php';
requireAdmin();

require_once 'config.php';

$message = '';
$message_type = '';

// Handle program profile submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_program'])) {
    try {
        $program_name = trim($_POST['program_name']);
        $specialization = trim($_POST['specialization']);
        $total_units = intval($_POST['total_units']);
        $department = trim($_POST['department']);
        $description = trim($_POST['description']);
        $program_id = !empty($_POST['program_id']) ? intval($_POST['program_id']) : null;
        
        $checklist_file = null;
        
        // Handle file upload
        if (isset($_FILES['checklist_file']) && $_FILES['checklist_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['checklist_file'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['pdf', 'xlsx', 'xls'];
            
            if (!in_array($file_ext, $allowed_exts)) {
                throw new Exception('Only PDF and Excel files are allowed');
            }
            
            if ($file['size'] > MAX_FILE_SIZE) {
                throw new Exception('File size exceeds 10MB limit');
            }
            
            $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $filepath = UPLOAD_DIR . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to upload file');
            }
            
            $checklist_file = $filename;
            
            // Delete old file if updating
            if ($program_id) {
                $stmt = $conn->prepare("SELECT checklist_file FROM program_profiles WHERE id = ?");
                $stmt->execute([$program_id]);
                $old_file = $stmt->fetchColumn();
                if ($old_file && file_exists(UPLOAD_DIR . $old_file)) {
                    unlink(UPLOAD_DIR . $old_file);
                }
            }
        }
        
        if ($program_id) {
            // Update existing program
            if ($checklist_file) {
                $stmt = $conn->prepare("
                    UPDATE program_profiles 
                    SET program_name = ?, specialization = ?, total_units = ?, 
                        department = ?, description = ?, checklist_file = ?
                    WHERE id = ?
                ");
                $stmt->execute([$program_name, $specialization, $total_units, $department, $description, $checklist_file, $program_id]);
            } else {
                $stmt = $conn->prepare("
                    UPDATE program_profiles 
                    SET program_name = ?, specialization = ?, total_units = ?, 
                        department = ?, description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$program_name, $specialization, $total_units, $department, $description, $program_id]);
            }
            $message = 'Program profile updated successfully!';
        } else {
            // Insert new program
            $stmt = $conn->prepare("
                INSERT INTO program_profiles (program_name, specialization, total_units, department, description, checklist_file)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$program_name, $specialization, $total_units, $department, $description, $checklist_file]);
            $message = 'Program profile created successfully!';
        }
        
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle program deletion
if (isset($_GET['delete'])) {
    try {
        $program_id = intval($_GET['delete']);
        
        // Get and delete file
        $stmt = $conn->prepare("SELECT checklist_file FROM program_profiles WHERE id = ?");
        $stmt->execute([$program_id]);
        $file = $stmt->fetchColumn();
        if ($file && file_exists(UPLOAD_DIR . $file)) {
            unlink(UPLOAD_DIR . $file);
        }
        
        $stmt = $conn->prepare("DELETE FROM program_profiles WHERE id = ?");
        $stmt->execute([$program_id]);
        
        $message = 'Program profile deleted successfully!';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Error deleting program: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all program profiles
$stmt = $conn->query("SELECT * FROM program_profiles ORDER BY program_name, specialization");
$programs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Profiles - Admin Portal</title>
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
            background: #00A36C;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            background: #006B4A;
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
            border-left-color: #7FE5B8;
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
            color: #00A36C;
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
        
        .content-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .content-card h2 {
            font-size: 22px;
            color: #00A36C;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .btn-add-new {
            padding: 12px 24px;
            background: #00A36C;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
        }
        
        .btn-add-new:hover {
            background: #8e24aa;
        }
        
        .program-list {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        
        .program-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .program-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .program-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .program-title h3 {
            color: #00A36C;
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .program-title p {
            color: #666;
            font-size: 14px;
        }
        
        .program-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit,
        .btn-delete,
        .btn-view {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-edit {
            background: #2196F3;
            color: white;
        }
        
        .btn-view {
            background: #4CAF50;
            color: white;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .program-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .info-item {
            font-size: 13px;
        }
        
        .info-label {
            color: #999;
            margin-bottom: 3px;
        }
        
        .info-value {
            color: #333;
            font-weight: 600;
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
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 30px auto;
            padding: 0;
            border-radius: 10px;
            width: 95%;
            max-width: 800px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .modal-header h3 {
            color: #00A36C;
            font-size: 24px;
        }
        
        .close {
            font-size: 32px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover {
            color: #000;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .file-upload {
            border: 2px dashed #ddd;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
        }
        
        .file-upload input[type="file"] {
            display: none;
        }
        
        .file-upload-label {
            display: inline-block;
            padding: 10px 20px;
            background: #00A36C;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .file-upload-label:hover {
            background: #8e24aa;
        }
        
        .btn-save {
            padding: 12px 30px;
            background: #00A36C;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            margin-top: 20px;
        }
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
                <a href="admin_accounts.php" class="menu-item active">User Accounts</a>
                <a href="admin_courses.php" class="menu-item">Course Catalog</a>
                <a href="admin_advisingassignment.php" class="menu-item">Advising Assignments</a>
                <a href="admin_reports.php" class="menu-item">System Reports</a>
                <a href="admin_bulk_operations.php" class="menu-item">Bulk Ops & Uploads</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <h1>Program Profiles</h1>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="content-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0; padding: 0; border: none;">All Program Profiles</h2>
                    <button class="btn-add-new" onclick="openModal('add')">+ Add New Program</button>
                </div>
                
                <div class="program-list">
                    <?php foreach ($programs as $program): ?>
                    <div class="program-card">
                        <div class="program-header">
                            <div class="program-title">
                                <h3><?php echo htmlspecialchars($program['program_name']); ?></h3>
                                <p><?php echo htmlspecialchars($program['specialization'] ?: 'No specialization'); ?></p>
                            </div>
                            <div class="program-actions">
                                <?php if ($program['checklist_file']): ?>
                                    <a href="../uploads/<?php echo htmlspecialchars($program['checklist_file']); ?>" class="btn-view" target="_blank">View File</a>
                                <?php endif; ?>
                                <button class="btn-edit" onclick='editProgram(<?php echo json_encode($program); ?>)'>Edit</button>
                                <a href="?delete=<?php echo $program['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this program?')">Delete</a>
                            </div>
                        </div>
                        <div class="program-info">
                            <div class="info-item">
                                <div class="info-label">Total Units</div>
                                <div class="info-value"><?php echo $program['total_units']; ?> units</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Department</div>
                                <div class="info-value"><?php echo htmlspecialchars($program['department']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Checklist File</div>
                                <div class="info-value"><?php echo $program['checklist_file'] ? 'Uploaded' : 'Not uploaded'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Last Updated</div>
                                <div class="info-value"><?php echo date('M j, Y', strtotime($program['updated_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
    
    <div id="programModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Program Profile</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="program_id" id="program_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Course Name *</label>
                            <input type="text" name="program_name" id="program_name" required>
                        </div>
                        <div class="form-group">
                            <label>Specialization</label>
                            <input type="text" name="specialization" id="specialization" placeholder="Optional">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Total Units Required *</label>
                            <input type="number" name="total_units" id="total_units" required min="1">
                        </div>
                        <div class="form-group">
                            <label>Department *</label>
                            <input type="text" name="department" id="department" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="description" rows="3" placeholder="Optional description of the program"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Program Checklist (PDF or Excel)</label>
                        <div class="file-upload">
                            <input type="file" name="checklist_file" id="checklist_file" accept=".pdf,.xlsx,.xls">
                            <label for="checklist_file" class="file-upload-label">Choose File</label>
                            <p id="fileName" style="margin-top: 10px; color: #666; font-size: 13px;">No file chosen</p>
                            <p style="margin-top: 5px; color: #999; font-size: 12px;">Max file size: 10MB. Allowed: PDF, XLSX, XLS</p>
                        </div>
                    </div>
                    
                    <button type="submit" name="save_program" class="btn-save">Save Program Profile</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openModal(mode) {
            const modal = document.getElementById('programModal');
            const title = document.getElementById('modalTitle');
            
            if (mode === 'add') {
                title.textContent = 'Add New Program Profile';
                document.querySelector('form').reset();
                document.getElementById('program_id').value = '';
                document.getElementById('fileName').textContent = 'No file chosen';
            }
            
            modal.style.display = 'block';
        }
        
        function editProgram(program) {
            document.getElementById('modalTitle').textContent = 'Edit Program Profile';
            document.getElementById('program_id').value = program.id;
            document.getElementById('program_name').value = program.program_name;
            document.getElementById('specialization').value = program.specialization || '';
            document.getElementById('total_units').value = program.total_units;
            document.getElementById('department').value = program.department;
            document.getElementById('description').value = program.description || '';
            document.getElementById('fileName').textContent = program.checklist_file || 'No file uploaded';
            
            document.getElementById('programModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('programModal').style.display = 'none';
        }
        
        document.getElementById('checklist_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'No file chosen';
            document.getElementById('fileName').textContent = fileName;
        });
        
        window.onclick = function(event) {
            const modal = document.getElementById('programModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>