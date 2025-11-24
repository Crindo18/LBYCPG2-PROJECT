<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'professor') {
    header('Location: login.php');
    exit();
}

ob_start();
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'professor') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

ob_clean();
header('Content-Type: application/json');

$professor_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create_availability':
        createAvailability();
        break;
    case 'get_availability':
        getAvailability();
        break;
    case 'get_appointments':
        getAppointments();
        break;
    case 'confirm_appointment':
        confirmAppointment();
        break;
    case 'cancel_appointment':
        cancelAppointment();
        break;
    case 'delete_slot':
        deleteSlot();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function createAvailability() {
    global $conn, $professor_id;
    
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $location = $_POST['location'];
    $max_slots = $_POST['max_slots'];
    
    // Validate that end time is after start time
    if (strtotime($end_time) <= strtotime($start_time)) {
        echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
        return;
    }
    
    // Check for overlapping slots
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM advising_schedules 
        WHERE professor_id = ? AND schedule_date = ? 
        AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?))
    ");
    $stmt->bind_param("isssss", $professor_id, $date, $end_time, $start_time, $end_time, $start_time);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'This time slot overlaps with an existing slot']);
        return;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO advising_schedules (professor_id, schedule_date, start_time, end_time, location, max_slots, is_available) 
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->bind_param("issssi", $professor_id, $date, $start_time, $end_time, $location, $max_slots);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Availability created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create availability']);
    }
}

function getAvailability() {
    global $conn, $professor_id;
    
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            COUNT(sa.id) as booked_count
        FROM advising_schedules a
        LEFT JOIN student_appointments sa ON sa.schedule_id = a.id AND sa.status != 'cancelled'
        WHERE a.professor_id = ? AND a.schedule_date >= CURDATE()
        GROUP BY a.id
        ORDER BY a.schedule_date, a.start_time
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $slots = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_available'] = $row['booked_count'] < $row['max_slots'];
        $slots[] = $row;
    }
    
    echo json_encode(['success' => true, 'slots' => $slots]);
}

function getAppointments() {
    global $conn, $professor_id;
    
    $stmt = $conn->prepare("
        SELECT 
            sa.*,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            s.id_number,
            a.schedule_date,
            a.start_time,
            a.end_time,
            a.location
        FROM student_appointments sa
        JOIN students s ON s.id = sa.student_id
        JOIN advising_schedules a ON a.id = sa.schedule_id
        WHERE a.professor_id = ? AND a.schedule_date >= CURDATE()
        ORDER BY a.schedule_date, a.start_time
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    echo json_encode(['success' => true, 'appointments' => $appointments]);
}

function confirmAppointment() {
    global $conn, $professor_id;
    
    $id = $_POST['id'];
    
    // Verify this appointment belongs to the professor
    $stmt = $conn->prepare("
        SELECT sa.* FROM student_appointments sa
        JOIN advising_schedules a ON a.id = sa.schedule_id
        WHERE sa.id = ? AND a.professor_id = ?
    ");
    $stmt->bind_param("ii", $id, $professor_id);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE student_appointments SET status = 'confirmed' WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Appointment confirmed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to confirm appointment']);
    }
}

function cancelAppointment() {
    global $conn, $professor_id;
    
    $id = $_POST['id'];
    
    // Verify this appointment belongs to the professor
    $stmt = $conn->prepare("
        SELECT sa.* FROM student_appointments sa
        JOIN advising_schedules a ON a.id = sa.schedule_id
        WHERE sa.id = ? AND a.professor_id = ?
    ");
    $stmt->bind_param("ii", $id, $professor_id);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE student_appointments SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Appointment cancelled']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment']);
    }
}

function deleteSlot() {
    global $conn, $professor_id;
    
    $id = $_POST['id'];
    
    // Check if there are any confirmed appointments
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM student_appointments 
        WHERE schedule_id = ? AND status != 'cancelled'
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete slot with existing appointments']);
        return;
    }
    
    // Delete the slot
    $stmt = $conn->prepare("DELETE FROM advising_schedules WHERE id = ? AND professor_id = ?");
    $stmt->bind_param("ii", $id, $professor_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Availability slot deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete slot']);
    }
}

$professor_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name, last_name FROM professors WHERE id = ?");
$stmt->bind_param("i", $professor_id);
$stmt->execute();
$professor = $stmt->get_result()->fetch_assoc();
$professor_name = $professor['full_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule - Professor Portal</title>
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
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .form-group { flex: 1; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #6a1b9a; }
        .btn-primary { padding: 10px 25px; background: #6a1b9a; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 500; }
        .btn-primary:hover { background: #4a148c; }
        .btn-danger { padding: 8px 16px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; }
        .btn-danger:hover { background: #c82333; }
        .schedule-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .schedule-item { padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #6a1b9a; }
        .schedule-item h3 { font-size: 16px; color: #333; margin-bottom: 12px; }
        .schedule-item p { font-size: 14px; color: #666; margin-bottom: 8px; }
        .schedule-item .actions { margin-top: 15px; display: flex; gap: 8px; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .no-data { text-align: center; padding: 40px; color: #999; font-style: italic; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge.success { background: #d4edda; color: #155724; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.danger { background: #f8d7da; color: #721c24; }
        .badge.info { background: #d1ecf1; color: #0c5460; }
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
                <a href="prof_concerns.php" class="menu-item">Student Concerns</a>
                <a href="prof_reports.php" class="menu-item">Reports</a>
                <a href="prof_email.php" class="menu-item active">Email System</a>
                <a href="prof_schedule.php" class="menu-item">Schedule</a>
                <a href="prof_grade_approvals.php" class="menu-item">Grade Approvals</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <div>
                    <h1>Advising Schedule</h1>
                    <p style="color: #666; font-size: 14px; margin-top: 5px;">Manage your advising appointments</p>
                </div>
                <a href="login.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="content-card">
                <h2>Create Availability Slot</h2>
                <form id="scheduleForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="date" required>
                        </div>
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="start_time" required>
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="end_time" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Location/Room</label>
                            <input type="text" name="location" placeholder="e.g., Office 304" required>
                        </div>
                        <div class="form-group">
                            <label>Max Students</label>
                            <input type="number" name="max_slots" min="1" value="5" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Create Availability</button>
                </form>
            </div>
            
            <div class="content-card">
                <h2>Upcoming Appointments</h2>
                <div id="appointmentsList" class="schedule-grid">
                    <div class="loading">Loading appointments...</div>
                </div>
            </div>
            
            <div class="content-card">
                <h2>Available Slots</h2>
                <div id="availabilityList" class="schedule-grid">
                    <div class="loading">Loading availability...</div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        loadAppointments();
        loadAvailability();
        
        // Set minimum date to today
        const dateInput = document.querySelector('input[type="date"]');
        dateInput.min = new Date().toISOString().split('T')[0];
    });

    document.getElementById('scheduleForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'create_availability');
        
        fetch('prof_schedule.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                this.reset();
                loadAvailability();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error creating availability');
        });
    });

    function loadAppointments() {
        fetch('prof_schedule.php?action=get_appointments')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('appointmentsList');
                if (data.appointments.length === 0) {
                    container.innerHTML = '<div class="no-data">No upcoming appointments</div>';
                    return;
                }
                container.innerHTML = data.appointments.map(apt => `
                    <div class="schedule-item">
                        <h3>${apt.student_name}</h3>
                        <p><strong>Date:</strong> ${new Date(apt.schedule_date).toLocaleDateString('en-US', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'})}</p>
                        <p><strong>Time:</strong> ${formatTime(apt.start_time)} - ${formatTime(apt.end_time)}</p>
                        <p><strong>Location:</strong> ${apt.location || 'TBA'}</p>
                        <p><strong>Status:</strong> <span class="badge ${getStatusBadge(apt.status)}">${apt.status}</span></p>
                        ${apt.notes ? `<p><strong>Notes:</strong> ${apt.notes}</p>` : ''}
                        <div class="actions">
                            ${apt.status === 'pending' ? `
                                <button class="btn-primary" onclick="confirmAppointment(${apt.id})">Confirm</button>
                                <button class="btn-danger" onclick="cancelAppointment(${apt.id})">Cancel</button>
                            ` : apt.status === 'confirmed' ? `
                                <button class="btn-danger" onclick="cancelAppointment(${apt.id})">Cancel</button>
                            ` : ''}
                        </div>
                    </div>
                `).join('');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('appointmentsList').innerHTML = '<div class="no-data">Error loading appointments</div>';
        });
    }

    function loadAvailability() {
        fetch('prof_schedule.php?action=get_availability')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('availabilityList');
                if (data.slots.length === 0) {
                    container.innerHTML = '<div class="no-data">No available slots</div>';
                    return;
                }
                container.innerHTML = data.slots.map(slot => `
                    <div class="schedule-item">
                        <h3>${new Date(slot.schedule_date).toLocaleDateString('en-US', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'})}</h3>
                        <p><strong>Time:</strong> ${formatTime(slot.start_time)} - ${formatTime(slot.end_time)}</p>
                        <p><strong>Location:</strong> ${slot.location || 'TBA'}</p>
                        <p><strong>Booked:</strong> ${slot.booked_count || 0} / ${slot.max_slots}</p>
                        <p><strong>Status:</strong> <span class="badge ${slot.is_available ? 'success' : 'danger'}">${slot.is_available ? 'Available' : 'Full'}</span></p>
                        <div class="actions">
                            <button class="btn-danger" onclick="deleteSlot(${slot.id})">Delete</button>
                        </div>
                    </div>
                `).join('');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('availabilityList').innerHTML = '<div class="no-data">Error loading availability</div>';
        });
    }

    function confirmAppointment(id) {
        if (!confirm('Confirm this appointment?')) return;
        const formData = new FormData();
        formData.append('action', 'confirm_appointment');
        formData.append('id', id);
        fetch('prof_schedule.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                loadAppointments();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function cancelAppointment(id) {
        if (!confirm('Cancel this appointment?')) return;
        const formData = new FormData();
        formData.append('action', 'cancel_appointment');
        formData.append('id', id);
        fetch('prof_schedule.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                loadAppointments();
                loadAvailability();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function deleteSlot(id) {
        if (!confirm('Delete this availability slot?')) return;
        const formData = new FormData();
        formData.append('action', 'delete_slot');
        formData.append('id', id);
        fetch('prof_schedule.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                loadAvailability();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function formatTime(time) {
        const [hours, minutes] = time.split(':');
        const h = parseInt(hours);
        const ampm = h >= 12 ? 'PM' : 'AM';
        const displayHour = h % 12 || 12;
        return `${displayHour}:${minutes} ${ampm}`;
    }

    function getStatusBadge(status) {
        switch(status) {
            case 'confirmed': return 'success';
            case 'pending': return 'pending';
            case 'cancelled': return 'danger';
            default: return 'info';
        }
    }
    </script>
</body>
</html>
