<?php
require_once 'auth_check.php';
requireStudent();

require_once 'config.php';

$student_id = $_SESSION['user_id'];

// Handle API requests
if (isset($_GET['action']) || isset($_POST['action'])) {
    ob_start();
    error_reporting(0);
    ini_set('display_errors', 0);
    ob_clean();
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_available_slots':
            getAvailableSlots();
            break;
        case 'book_slot':
            bookSlot();
            break;
        case 'cancel_booking':
            cancelBooking();
            break;
        case 'get_my_bookings':
            getMyBookings();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit();
}

// API Functions
function getAvailableSlots() {
    global $conn, $student_id;
    
    // Get student's advisor
    $stmt = $conn->prepare("SELECT advisor_id FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $advisor_id = $result['advisor_id'];
    
    if (!$advisor_id) {
        echo json_encode(['success' => false, 'message' => 'No advisor assigned']);
        return;
    }
    
    // Get all available slots for the advisor (future dates and times only)
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            CONCAT(p.first_name, ' ', p.last_name) as professor_name,
            (SELECT COUNT(*) FROM student_appointments sa 
             WHERE sa.schedule_id = s.id AND sa.status != 'cancelled') as booked_count
        FROM advising_schedules s
        JOIN professors p ON p.id = s.professor_id
        WHERE s.professor_id = ? 
        AND s.is_available = 1
        AND (
            s.schedule_date > CURDATE() 
            OR (s.schedule_date = CURDATE() AND s.start_time > CURTIME())
        )
        ORDER BY s.schedule_date ASC, s.start_time ASC
    ");
    $stmt->bind_param("i", $advisor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $slots = [];
    while ($row = $result->fetch_assoc()) {
        $row['available_slots'] = $row['max_slots'] - $row['booked_count'];
        $row['is_fully_booked'] = $row['booked_count'] >= $row['max_slots'];
        
        // Check if student has already booked this slot
        $check_stmt = $conn->prepare("SELECT id FROM student_appointments WHERE schedule_id = ? AND student_id = ? AND status != 'cancelled'");
        $check_stmt->bind_param("ii", $row['id'], $student_id);
        $check_stmt->execute();
        $row['is_my_booking'] = $check_stmt->get_result()->num_rows > 0;
        
        $slots[] = $row;
    }
    
    echo json_encode(['success' => true, 'slots' => $slots]);
}

function bookSlot() {
    global $conn, $student_id;
    
    $slot_id = $_POST['slot_id'] ?? 0;
    
    // Check if slot is available
    $stmt = $conn->prepare("
        SELECT s.*, 
        (SELECT COUNT(*) FROM student_appointments sa 
         WHERE sa.schedule_id = s.id AND sa.status != 'cancelled') as booked_count
        FROM advising_schedules s 
        WHERE s.id = ? AND s.is_available = 1
    ");
    $stmt->bind_param("i", $slot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Slot not available']);
        return;
    }
    
    $slot = $result->fetch_assoc();
    
    // Check if slot is full
    if ($slot['booked_count'] >= $slot['max_slots']) {
        echo json_encode(['success' => false, 'message' => 'This slot is fully booked']);
        return;
    }
    
    // Check if slot has passed
    $slot_datetime = $slot['schedule_date'] . ' ' . $slot['start_time'];
    if (strtotime($slot_datetime) < time()) {
        echo json_encode(['success' => false, 'message' => 'This slot has already passed']);
        return;
    }
    
    // Verify this is student's advisor
    $stmt = $conn->prepare("SELECT advisor_id FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student_result = $stmt->get_result()->fetch_assoc();
    
    if ($slot['professor_id'] != $student_result['advisor_id']) {
        echo json_encode(['success' => false, 'message' => 'This is not your advisor\'s slot']);
        return;
    }
    
    // Check if student already has a booking for this slot
    $stmt = $conn->prepare("SELECT id FROM student_appointments WHERE schedule_id = ? AND student_id = ? AND status != 'cancelled'");
    $stmt->bind_param("ii", $slot_id, $student_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already booked this slot']);
        return;
    }
    
    // Book the slot
    $stmt = $conn->prepare("INSERT INTO student_appointments (schedule_id, student_id, status) VALUES (?, ?, 'pending')");
    $stmt->bind_param("ii", $slot_id, $student_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Slot booked successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to book slot']);
    }
}

function cancelBooking() {
    global $conn, $student_id;
    
    $appointment_id = $_POST['appointment_id'] ?? 0;
    
    // Verify this is the student's booking
    $stmt = $conn->prepare("SELECT * FROM student_appointments WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $appointment_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        return;
    }
    
    // Cancel the booking
    $stmt = $conn->prepare("UPDATE student_appointments SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $appointment_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel booking']);
    }
}

function getMyBookings() {
    global $conn, $student_id;
    
    $stmt = $conn->prepare("
        SELECT 
            sa.id as appointment_id,
            sa.status,
            sa.notes,
            sa.created_at as booked_at,
            s.schedule_date,
            s.start_time,
            s.end_time,
            s.location,
            s.max_slots,
            CONCAT(p.first_name, ' ', p.last_name) as adviser_name,
            p.email as adviser_email,
            (SELECT COUNT(*) FROM student_appointments sa2 
             WHERE sa2.schedule_id = s.id AND sa2.status != 'cancelled') as booked_count
        FROM student_appointments sa
        JOIN advising_schedules s ON s.id = sa.schedule_id
        JOIN professors p ON p.id = s.professor_id
        WHERE sa.student_id = ?
        AND sa.status != 'cancelled'
        AND (
            s.schedule_date > CURDATE() 
            OR (s.schedule_date = CURDATE() AND s.start_time > CURTIME())
        )
        ORDER BY s.schedule_date ASC, s.start_time ASC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    echo json_encode(['success' => true, 'bookings' => $bookings]);
}

// Get student info for HTML display
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, advisor_id FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$student_name = $result['name'];
$advisor_id = $result['advisor_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Schedule</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: #1976D2; color: white; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; background: #1565C0; }
        .sidebar-header h2 { font-size: 18px; margin-bottom: 5px; }
        .sidebar-header p { font-size: 13px; opacity: 0.9; }
        .sidebar-menu { padding: 20px 0; }
        .menu-item { padding: 15px 25px; color: white; text-decoration: none; display: block; transition: all 0.3s; border-left: 3px solid transparent; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); border-left-color: #90CAF9; }
        .main-content { margin-left: 260px; flex: 1; padding: 30px; width: calc(100% - 260px); }
        .top-bar { background: white; padding: 20px 30px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { font-size: 28px; color: #1976D2; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; text-decoration: none; font-size: 14px; }
        
        .content-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .content-card h3 { font-size: 20px; color: #1976D2; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .alert.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert.danger { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .alert.warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
        .alert.info { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
        
        .calendar-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        
        .date-card { background: #f8f9fa; border: 2px solid #e0e0e0; border-radius: 10px; padding: 20px; }
        .date-card.has-slots { border-color: #1976D2; }
        .date-header { font-size: 18px; font-weight: 600; color: #1976D2; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0; }
        .date-day { font-size: 14px; color: #666; margin-bottom: 10px; }
        
        .time-slots { display: flex; flex-direction: column; gap: 10px; }
        .time-slot { padding: 12px 15px; background: white; border: 2px solid #ddd; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; transition: all 0.3s; }
        .time-slot:hover { border-color: #1976D2; }
        .time-slot.booked { background: #f0f0f0; border-color: #ccc; }
        .time-slot.my-booking { background: #d4edda; border-color: #28a745; }
        
        .time-info { flex: 1; }
        .time-text { font-size: 15px; font-weight: 600; color: #333; }
        .booking-status { font-size: 13px; color: #666; margin-top: 3px; }
        
        .btn { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: #1976D2; color: white; }
        .btn-primary:hover { background: #1565C0; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-disabled { background: #ccc; color: #666; cursor: not-allowed; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .loading { text-align: center; padding: 40px; color: #666; }
        
        .my-bookings { margin-bottom: 30px; }
        .booking-card { background: #e3f2fd; border-left: 4px solid #1976D2; padding: 20px; border-radius: 8px; margin-bottom: 15px; }
        .booking-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px; }
        .booking-date { font-size: 18px; font-weight: 600; color: #1976D2; }
        .booking-details { font-size: 14px; color: #555; line-height: 1.6; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; flex-wrap: wrap; }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 15px; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab-btn.active { color: #1976D2; border-bottom-color: #1976D2; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Student Portal</h2>
                <p><?php echo htmlspecialchars($student_name); ?></p>
            </div>
            <nav class="sidebar-menu">
                <a href="student_dashboard.php" class="menu-item">Dashboard</a>
                <a href="student_booklet.php" class="menu-item">My Booklet</a>
                <a href="student_advising_form.php" class="menu-item">Academic Advising Form</a>
                <a href="student_meeting.php" class="menu-item active">Meeting Schedule</a>
                <a href="student_documents.php" class="menu-item">Documents</a>
                <a href="student_concerns.php" class="menu-item">Submit Concern</a>
                <a href="student_profile.php" class="menu-item">My Profile</a>
            </nav>
        </aside>
        
        <main class="main-content">
            <div class="top-bar">
                <div>
                    <h1>Meeting Schedule</h1>
                    <p style="color: #666; font-size: 14px; margin-top: 5px;">
                        <span id="currentTime"></span>
                    </p>
                </div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
            
            <?php if (!$advisor_id): ?>
                <div class="alert warning">
                    <strong>No Adviser Assigned</strong><br>
                    You don't have an adviser assigned yet. Please contact the admin office.
                </div>
            <?php else: ?>
                
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('available')">Available Slots</button>
                    <button class="tab-btn" onclick="switchTab('mybookings')">My Bookings</button>
                </div>
                
                <!-- Available Slots Tab -->
                <div id="available" class="tab-content active">
                    <div class="content-card">
                        <h3>Available Meeting Slots</h3>
                        <div id="alertContainer"></div>
                        <p style="margin-bottom: 20px; color: #666;">
                            Select an available time slot to book a meeting with your adviser.
                        </p>
                        <div id="slotsContainer">
                            <div class="loading">Loading available slots...</div>
                        </div>
                    </div>
                </div>
                
                <!-- My Bookings Tab -->
                <div id="mybookings" class="tab-content">
                    <div class="content-card">
                        <h3>My Bookings</h3>
                        <div id="bookingsContainer">
                            <div class="loading">Loading your bookings...</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        const advisorId = <?php echo json_encode($advisor_id); ?>;
        
        window.onload = function() {
            if (advisorId) {
                loadAvailableSlots();
                loadMyBookings();
            }
            updateTime();
        };

        function switchTab(tab) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById(tab).classList.add('active');
            event.target.classList.add('active');
            
            if (tab === 'mybookings') {
                loadMyBookings();
            }
        }

        function loadAvailableSlots() {
            const container = document.getElementById('slotsContainer');
            container.innerHTML = '<div class="loading">Loading available slots...</div>';
            
            fetch('student_meeting.php?action=get_available_slots')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderAvailableSlots(data.slots);
                    } else {
                        container.innerHTML = '<div class="empty-state">No available slots at the moment</div>';
                    }
                })
                .catch(error => {
                    container.innerHTML = '<div class="empty-state">Error loading slots</div>';
                });
        }

        function renderAvailableSlots(slots) {
            const container = document.getElementById('slotsContainer');
            
            if (slots.length === 0) {
                container.innerHTML = '<div class="empty-state">No available slots at the moment. Check back later!</div>';
                return;
            }
            
            // Group slots by date
            const groupedSlots = {};
            slots.forEach(slot => {
                if (!groupedSlots[slot.schedule_date]) {
                    groupedSlots[slot.schedule_date] = [];
                }
                groupedSlots[slot.schedule_date].push(slot);
            });
            
            let html = '<div class="calendar-container">';
            
            Object.keys(groupedSlots).sort().forEach(date => {
                const dateSlots = groupedSlots[date];
                const hasAvailable = dateSlots.some(s => !s.is_fully_booked);
                
                html += `
                    <div class="date-card ${hasAvailable ? 'has-slots' : ''}">
                        <div class="date-header">${formatDisplayDate(date)}</div>
                        <div class="date-day">${getDayOfWeek(date)}</div>
                        <div class="time-slots">
                `;
                
                dateSlots.forEach(slot => {
                    const isFullyBooked = slot.is_fully_booked;
                    const isMyBooking = slot.is_my_booking;
                    
                    html += `
                        <div class="time-slot ${isFullyBooked ? (isMyBooking ? 'my-booking' : 'booked') : ''}">
                            <div class="time-info">
                                <div class="time-text">${formatTime(slot.start_time)} - ${formatTime(slot.end_time)}</div>
                                <div class="booking-status">
                                    ${slot.location ? `📍 ${slot.location} • ` : ''}
                                    ${slot.available_slots} of ${slot.max_slots} slots available
                                </div>
                                ${isMyBooking ? '<div class="booking-status" style="color: #28a745; font-weight: 600;">✓ You have booked this slot</div>' : ''}
                            </div>
                            ${!isFullyBooked && !isMyBooking ? 
                                `<button class="btn btn-primary" onclick="bookSlot(${slot.id})">Book</button>` : 
                                isMyBooking ? 
                                `<span class="badge success">Booked</span>` : 
                                `<button class="btn btn-disabled" disabled>Full</button>`
                            }
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }

        function bookSlot(slotId) {
            if (!confirm('Are you sure you want to book this meeting slot?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'book_slot');
            formData.append('slot_id', slotId);
            
            fetch('student_meeting.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Meeting slot booked successfully!', 'success');
                    loadAvailableSlots();
                    loadMyBookings();
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Error booking slot', 'danger');
            });
        }

        function cancelBooking(appointmentId) {
            if (!confirm('Are you sure you want to cancel this booking?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'cancel_booking');
            formData.append('appointment_id', appointmentId);
            
            fetch('student_meeting.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Booking cancelled successfully', 'success');
                    loadAvailableSlots();
                    loadMyBookings();
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Error cancelling booking', 'danger');
            });
        }

        function loadMyBookings() {
            const container = document.getElementById('bookingsContainer');
            container.innerHTML = '<div class="loading">Loading your bookings...</div>';
            
            fetch('student_meeting.php?action=get_my_bookings')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderMyBookings(data.bookings);
                    } else {
                        container.innerHTML = '<div class="empty-state">No bookings yet</div>';
                    }
                })
                .catch(error => {
                    container.innerHTML = '<div class="empty-state">Error loading bookings</div>';
                });
        }

        function renderMyBookings(bookings) {
            const container = document.getElementById('bookingsContainer');
            
            if (bookings.length === 0) {
                container.innerHTML = '<div class="empty-state">You have no bookings yet</div>';
                return;
            }
            
            let html = '';
            bookings.forEach(booking => {
                const statusBadge = booking.status === 'confirmed' ? 
                    '<span class="badge success">Confirmed</span>' : 
                    '<span class="badge pending">Pending</span>';
                
                html += `
                    <div class="booking-card">
                        <div class="booking-header">
                            <div>
                                <div class="booking-date">${formatDisplayDate(booking.schedule_date)}</div>
                                ${statusBadge}
                            </div>
                            <button class="btn btn-danger" onclick="cancelBooking(${booking.appointment_id})">Cancel</button>
                        </div>
                        <div class="booking-details">
                            <strong>Time:</strong> ${formatTime(booking.start_time)} - ${formatTime(booking.end_time)}<br>
                            <strong>Location:</strong> ${booking.location || 'TBA'}<br>
                            <strong>Adviser:</strong> ${booking.adviser_name}<br>
                            <strong>Email:</strong> ${booking.adviser_email}<br>
                            <strong>Booked:</strong> ${booking.booked_count} of ${booking.max_slots} slots filled
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            container.innerHTML = `<div class="alert ${type}">${message}</div>`;
            
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        function formatDisplayDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        }

        function getDayOfWeek(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { weekday: 'long' });
        }

        function formatTime(timeString) {
            const [hours, minutes] = timeString.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 || 12;
            return `${displayHour}:${minutes} ${ampm}`;
        }

        // Update local time
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                timeElement.textContent = now.toLocaleString('en-US', options);
            }
        }
        setInterval(updateTime, 1000);
    </script>
</body>
</html>
