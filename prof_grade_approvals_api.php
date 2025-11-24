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
    case 'get_stats':
        getStats($conn, $professor_id);
        break;
    
    case 'get_pending':
        getPendingRequests($conn, $professor_id);
        break;
    
    case 'get_history':
        getHistory($conn, $professor_id);
        break;
    
    case 'approve_request':
        approveRequest($conn, $professor_id);
        break;
    
    case 'reject_request':
        rejectRequest($conn, $professor_id);
        break;
    
    case 'bulk_approve':
        bulkApprove($conn, $professor_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getStats($conn, $professor_id) {
    // Pending count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as pending
        FROM grade_edit_requests ger
        JOIN students s ON s.id = ger.student_id
        WHERE s.advisor_id = ? AND ger.status = 'pending'
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $pending = $stmt->get_result()->fetch_assoc()['pending'];
    
    // Approved today
    $stmt = $conn->prepare("
        SELECT COUNT(*) as approved_today
        FROM grade_edit_requests ger
        JOIN students s ON s.id = ger.student_id
        WHERE s.advisor_id = ? 
        AND ger.status = 'approved'
        AND DATE(ger.processed_date) = CURDATE()
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $approved_today = $stmt->get_result()->fetch_assoc()['approved_today'];
    
    // Total processed
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_processed
        FROM grade_edit_requests ger
        JOIN students s ON s.id = ger.student_id
        WHERE s.advisor_id = ? AND ger.status IN ('approved', 'rejected')
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $total_processed = $stmt->get_result()->fetch_assoc()['total_processed'];
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'pending' => $pending,
            'approved_today' => $approved_today,
            'total_processed' => $total_processed
        ]
    ]);
}

function getPendingRequests($conn, $professor_id) {
    $stmt = $conn->prepare("
        SELECT 
            ger.*,
            s.id_number,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            c.course_code,
            c.course_name
        FROM grade_edit_requests ger
        JOIN students s ON s.id = ger.student_id
        JOIN courses c ON c.id = ger.course_id
        WHERE s.advisor_id = ? AND ger.status = 'pending'
        ORDER BY ger.request_date ASC
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

function getHistory($conn, $professor_id) {
    $stmt = $conn->prepare("
        SELECT 
            ger.*,
            s.id_number,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            c.course_code,
            c.course_name
        FROM grade_edit_requests ger
        JOIN students s ON s.id = ger.student_id
        JOIN courses c ON c.id = ger.course_id
        WHERE s.advisor_id = ? AND ger.status IN ('approved', 'rejected')
        ORDER BY ger.processed_date DESC
        LIMIT 100
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

function approveRequest($conn, $professor_id) {
    $request_id = $_POST['request_id'];
    
    $conn->begin_transaction();
    
    try {
        // Verify request belongs to professor's advisee
        $stmt = $conn->prepare("
            SELECT ger.*, s.advisor_id
            FROM grade_edit_requests ger
            JOIN students s ON s.id = ger.student_id
            WHERE ger.id = ? AND ger.status = 'pending'
        ");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        
        if (!$request || $request['advisor_id'] != $professor_id) {
            throw new Exception('Request not found or unauthorized');
        }
        
        // Update grade in student_grades table
        $stmt = $conn->prepare("
            UPDATE student_grades
            SET grade = ?, updated_at = NOW()
            WHERE student_id = ? AND course_id = ? AND academic_year = ? AND term = ?
        ");
        $stmt->bind_param("siiss", 
            $request['new_grade'],
            $request['student_id'],
            $request['course_id'],
            $request['academic_year'],
            $request['term']
        );
        $stmt->execute();
        
        // If no record exists, insert it
        if ($stmt->affected_rows === 0) {
            $stmt = $conn->prepare("
                INSERT INTO student_grades (student_id, course_id, grade, academic_year, term, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("iisss",
                $request['student_id'],
                $request['course_id'],
                $request['new_grade'],
                $request['academic_year'],
                $request['term']
            );
            $stmt->execute();
        }
        
        // Update request status
        $stmt = $conn->prepare("
            UPDATE grade_edit_requests
            SET status = 'approved', processed_by = ?, processed_date = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $professor_id, $request_id);
        $stmt->execute();
        
        // Recalculate failed units if grade is failing
        $failing_grades = ['5.0', 'F', 'INC', 'DRP'];
        if (in_array($request['new_grade'], $failing_grades)) {
            recalculateFailedUnits($conn, $request['student_id']);
        }
        
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function rejectRequest($conn, $professor_id) {
    $request_id = $_POST['request_id'];
    $notes = trim($_POST['notes']);
    
    // Verify request belongs to professor's advisee
    $stmt = $conn->prepare("
        SELECT s.advisor_id
        FROM grade_edit_requests ger
        JOIN students s ON s.id = ger.student_id
        WHERE ger.id = ? AND ger.status = 'pending'
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result || $result['advisor_id'] != $professor_id) {
        echo json_encode(['success' => false, 'message' => 'Request not found or unauthorized']);
        return;
    }
    
    $stmt = $conn->prepare("
        UPDATE grade_edit_requests
        SET status = 'rejected', processed_by = ?, processed_date = NOW(), rejection_notes = ?
        WHERE id = ?
    ");
    $stmt->bind_param("isi", $professor_id, $notes, $request_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function bulkApprove($conn, $professor_id) {
    $request_ids = json_decode($_POST['request_ids'], true);
    
    if (empty($request_ids)) {
        echo json_encode(['success' => false, 'message' => 'No requests selected']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        $count = 0;
        
        foreach ($request_ids as $request_id) {
            // Verify and get request details
            $stmt = $conn->prepare("
                SELECT ger.*, s.advisor_id
                FROM grade_edit_requests ger
                JOIN students s ON s.id = ger.student_id
                WHERE ger.id = ? AND ger.status = 'pending'
            ");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            
            if (!$request || $request['advisor_id'] != $professor_id) {
                continue;
            }
            
            // Update grade
            $stmt = $conn->prepare("
                UPDATE student_grades
                SET grade = ?, updated_at = NOW()
                WHERE student_id = ? AND course_id = ? AND academic_year = ? AND term = ?
            ");
            $stmt->bind_param("siiss",
                $request['new_grade'],
                $request['student_id'],
                $request['course_id'],
                $request['academic_year'],
                $request['term']
            );
            $stmt->execute();
            
            // Insert if not exists
            if ($stmt->affected_rows === 0) {
                $stmt = $conn->prepare("
                    INSERT INTO student_grades (student_id, course_id, grade, academic_year, term, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("iisss",
                    $request['student_id'],
                    $request['course_id'],
                    $request['new_grade'],
                    $request['academic_year'],
                    $request['term']
                );
                $stmt->execute();
            }
            
            // Update request status
            $stmt = $conn->prepare("
                UPDATE grade_edit_requests
                SET status = 'approved', processed_by = ?, processed_date = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $professor_id, $request_id);
            $stmt->execute();
            
            // Recalculate failed units
            $failing_grades = ['5.0', 'F', 'INC', 'DRP'];
            if (in_array($request['new_grade'], $failing_grades)) {
                recalculateFailedUnits($conn, $request['student_id']);
            }
            
            $count++;
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'count' => $count]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function recalculateFailedUnits($conn, $student_id) {
    $stmt = $conn->prepare("
        SELECT SUM(c.units) as total_failed
        FROM student_grades sg
        JOIN courses c ON c.id = sg.course_id
        WHERE sg.student_id = ? 
        AND sg.grade IN ('5.0', 'F', 'INC', 'DRP')
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $total_failed = $result['total_failed'] ?? 0;
    
    $stmt = $conn->prepare("
        UPDATE students
        SET accumulated_failed_units = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $total_failed, $student_id);
    $stmt->execute();
}
?>
