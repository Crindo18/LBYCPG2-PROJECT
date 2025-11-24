<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'professor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$professor_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'add_availability':
        addAvailability($conn, $professor_id);
        break;
    
    case 'get_schedules':
        getSchedules($conn, $professor_id);
        break;
    
    case 'get_appointments':
        getAppointments($conn, $professor_id);
        break;
    
    case 'get_requests':
        getRequests($conn, $professor_id);
        break;
    
    case 'assign_slot':
        assignSlot($conn, $professor_id);
        break;
    
    case 'reject_request':
        rejectRequest($conn, $professor_id);
        break;
    
    case 'delete_slot':
        deleteSlot($conn, $professor_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function addAvailability($conn, $professor_id) {
    $date = $_POST['date'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];
    
    // Create 30-minute slots
    $start = strtotime($startTime);
    $end = strtotime($endTime);
    $count = 0;
    
    $conn->begin_transaction();
    
    try {
        while ($start < $end) {
            $time = date('H:i:s', $start);
            
            // Check if slot already exists
            $stmt = $conn->prepare("
                SELECT id FROM advising_schedules
                WHERE professor_id = ? AND available_date = ? AND available_time = ?
            ");
            $stmt->bind_param("iss", $professor_id, $date, $time);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 0) {
                $stmt = $conn->prepare("
                    INSERT INTO advising_schedules (professor_id, available_date, available_time, is_booked)
                    VALUES (?, ?, ?, 0)
                ");
                $stmt->bind_param("iss", $professor_id, $date, $time);
                $stmt->execute();
                $count++;
            }
            
            $start += 1800; // Add 30 minutes
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'count' => $count]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getSchedules($conn, $professor_id) {
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            CONCAT(st.first_name, ' ', st.last_name) as student_name,
            st.id_number
        FROM advising_schedules s
        LEFT JOIN students st ON st.id = s.booked_by
        WHERE s.professor_id = ?
        ORDER BY s.available_date, s.available_time
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    
    echo json_encode(['success' => true, 'schedules' => $schedules]);
}

function getAppointments($conn, $professor_id) {
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            CONCAT(st.first_name, ' ', st.last_name) as student_name,
            st.id_number
        FROM advising_schedules s
        JOIN students st ON st.id = s.booked_by
        WHERE s.professor_id = ? AND s.is_booked = 1
        ORDER BY s.available_date, s.available_time
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

function getRequests($conn, $professor_id) {
    $stmt = $conn->prepare("
        SELECT 
            sp.id as plan_id,
            sp.student_id,
            sp.term,
            sp.submission_date,
            s.id_number,
            CONCAT(s.first_name, ' ', s.last_name) as student_name
        FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        WHERE s.advisor_id = ? 
        AND sp.wants_meeting = 1 
        AND sp.selected_schedule_id IS NULL
        AND sp.status = 'pending'
        ORDER BY sp.submission_date
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    
    echo json_encode(['success' => true, 'requests' => $requests]);
}

function assignSlot($conn, $professor_id) {
    $studentId = $_POST['student_id'];
    $planId = $_POST['plan_id'];
    $slotId = $_POST['slot_id'];
    
    $conn->begin_transaction();
    
    try {
        // Verify slot belongs to this professor and is available
        $stmt = $conn->prepare("
            SELECT id FROM advising_schedules
            WHERE id = ? AND professor_id = ? AND is_booked = 0
        ");
        $stmt->bind_param("ii", $slotId, $professor_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception('Slot not available');
        }
        
        // Book the slot
        $stmt = $conn->prepare("
            UPDATE advising_schedules
            SET is_booked = 1, booked_by = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $studentId, $slotId);
        $stmt->execute();
        
        // Update study plan
        $stmt = $conn->prepare("
            UPDATE study_plans
            SET selected_schedule_id = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $slotId, $planId);
        $stmt->execute();
        
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function rejectRequest($conn, $professor_id) {
    $planId = $_POST['plan_id'];
    
    // Verify plan belongs to this professor's advisee
    $stmt = $conn->prepare("
        UPDATE study_plans sp
        JOIN students s ON s.id = sp.student_id
        SET sp.wants_meeting = 0
        WHERE sp.id = ? AND s.advisor_id = ?
    ");
    $stmt->bind_param("ii", $planId, $professor_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
    }
}

function deleteSlot($conn, $professor_id) {
    $slotId = $_POST['slot_id'];
    
    $stmt = $conn->prepare("
        DELETE FROM advising_schedules
        WHERE id = ? AND professor_id = ? AND is_booked = 0
    ");
    $stmt->bind_param("ii", $slotId, $professor_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cannot delete booked slot']);
    }
}
?>
