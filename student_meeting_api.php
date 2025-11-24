<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'auth_check.php';
require_once 'config.php';

if (!isAuthenticated() || $_SESSION['user_type'] !== 'student') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

ob_clean();
header('Content-Type: application/json');

$student_id = $_SESSION['user_id'];
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
    
    // Get all slots for the advisor (future dates only)
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            CONCAT(p.first_name, ' ', p.last_name) as professor_name
        FROM advising_schedules s
        JOIN professors p ON p.id = s.professor_id
        WHERE s.professor_id = ? 
        AND s.available_date >= CURDATE()
        ORDER BY s.available_date ASC, s.available_time ASC
    ");
    $stmt->bind_param("i", $advisor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $slots = [];
    while ($row = $result->fetch_assoc()) {
        $slots[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'slots' => $slots
    ]);
}

function bookSlot() {
    global $conn, $student_id;
    
    $slot_id = $_POST['slot_id'] ?? 0;
    
    // Check if slot is available
    $stmt = $conn->prepare("SELECT * FROM advising_schedules WHERE id = ? AND is_booked = 0");
    $stmt->bind_param("i", $slot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Slot not available']);
        return;
    }
    
    $slot = $result->fetch_assoc();
    
    // Verify this is student's advisor
    $stmt = $conn->prepare("SELECT advisor_id FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student_result = $stmt->get_result()->fetch_assoc();
    
    if ($slot['professor_id'] != $student_result['advisor_id']) {
        echo json_encode(['success' => false, 'message' => 'This is not your advisor\'s slot']);
        return;
    }
    
    // Book the slot
    $stmt = $conn->prepare("UPDATE advising_schedules SET is_booked = 1, booked_by = ? WHERE id = ?");
    $stmt->bind_param("ii", $student_id, $slot_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Slot booked successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to book slot']);
    }
}

function cancelBooking() {
    global $conn, $student_id;
    
    $slot_id = $_POST['slot_id'] ?? 0;
    
    // Verify this is the student's booking
    $stmt = $conn->prepare("SELECT * FROM advising_schedules WHERE id = ? AND booked_by = ?");
    $stmt->bind_param("ii", $slot_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        return;
    }
    
    // Cancel the booking
    $stmt = $conn->prepare("UPDATE advising_schedules SET is_booked = 0, booked_by = NULL WHERE id = ?");
    $stmt->bind_param("i", $slot_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Booking cancelled successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel booking']);
    }
}

function getMyBookings() {
    global $conn, $student_id;
    
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            CONCAT(p.first_name, ' ', p.last_name) as adviser_name,
            p.email as adviser_email
        FROM advising_schedules s
        JOIN professors p ON p.id = s.professor_id
        WHERE s.booked_by = ?
        AND s.available_date >= CURDATE()
        ORDER BY s.available_date ASC, s.available_time ASC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'bookings' => $bookings
    ]);
}
?>
