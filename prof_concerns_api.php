<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';

// Check if user is logged in and is a professor
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'professor') {
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
    case 'getConcerns':
        getConcerns();
        break;
    case 'markAsRead':
        markAsRead();
        break;
    case 'deleteConcern':
        deleteConcern();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getConcerns() {
    global $conn, $professor_id;
    
    try {
        // Get all concerns for students assigned to this professor
        $stmt = $conn->prepare("
            SELECT 
                sc.*,
                s.idnumber as student_idnumber,
                CONCAT(s.firstname, ' ', s.lastname) as student_name
            FROM studentconcerns sc
            JOIN students s ON s.id = sc.studentid
            WHERE s.advisorid = ?
            ORDER BY sc.submissiondate DESC
        ");
        $stmt->bind_param("i", $professor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $concerns = [];
        while ($row = $result->fetch_assoc()) {
            $concerns[] = $row;
        }
        
        // Calculate stats
        $total = count($concerns);
        $new = 0;
        $read = 0;
        
        foreach ($concerns as $concern) {
            if ($concern['isread'] == '0' || $concern['isread'] === null) {
                $new++;
            } else {
                $read++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'concerns' => $concerns,
            'stats' => [
                'total' => $total,
                'new' => $new,
                'read' => $read
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

function markAsRead() {
    global $conn, $professor_id;
    
    $concern_id = $_POST['concernId'] ?? 0;
    
    try {
        // Verify that this concern belongs to one of professor's advisees
        $stmt = $conn->prepare("
            SELECT sc.id 
            FROM studentconcerns sc
            JOIN students s ON s.id = sc.studentid
            WHERE sc.id = ? AND s.advisorid = ?
        ");
        $stmt->bind_param("ii", $concern_id, $professor_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized - This concern does not belong to your advisees'
            ]);
            return;
        }
        
        // Mark as read
        $stmt = $conn->prepare("UPDATE studentconcerns SET isread = 1 WHERE id = ?");
        $stmt->bind_param("i", $concern_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Concern marked as read'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to mark concern as read'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

function deleteConcern() {
    global $conn, $professor_id;
    
    $concern_id = $_POST['concernId'] ?? 0;
    
    try {
        // Verify that this concern belongs to one of professor's advisees
        $stmt = $conn->prepare("
            SELECT sc.id 
            FROM studentconcerns sc
            JOIN students s ON s.id = sc.studentid
            WHERE sc.id = ? AND s.advisorid = ?
        ");
        $stmt->bind_param("ii", $concern_id, $professor_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized - This concern does not belong to your advisees'
            ]);
            return;
        }
        
        // Delete the concern
        $stmt = $conn->prepare("DELETE FROM studentconcerns WHERE id = ?");
        $stmt->bind_param("i", $concern_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Concern deleted successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete concern'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}
?>
