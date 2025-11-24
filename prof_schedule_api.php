<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';

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
?>
