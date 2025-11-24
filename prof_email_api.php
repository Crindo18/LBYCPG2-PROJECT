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
    case 'get_advisees':
        getAdvisees($conn, $professor_id);
        break;
    
    case 'send_email':
        sendEmail($conn, $professor_id);
        break;
    
    case 'send_bulk_email':
        sendBulkEmail($conn, $professor_id);
        break;
    
    case 'get_templates':
        getTemplates($conn, $professor_id);
        break;
    
    case 'save_template':
        saveTemplate($conn, $professor_id);
        break;
    
    case 'delete_template':
        deleteTemplate($conn, $professor_id);
        break;
    
    case 'get_email_history':
        getEmailHistory($conn, $professor_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getAdvisees($conn, $professor_id) {
    $stmt = $conn->prepare("
        SELECT id, id_number, CONCAT(first_name, ' ', last_name) as full_name, 
               email, advising_cleared, accumulated_failed_units
        FROM students
        WHERE advisor_id = ?
        ORDER BY last_name, first_name
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
}

function sendEmail($conn, $professor_id) {
    $recipients = json_decode($_POST['recipients'], true);
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);
    
    if (empty($recipients) || empty($subject) || empty($body)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        foreach ($recipients as $student_id) {
            // Verify student belongs to this professor
            $stmt = $conn->prepare("
                SELECT id, email, CONCAT(first_name, ' ', last_name) as full_name
                FROM students
                WHERE id = ? AND advisor_id = ?
            ");
            $stmt->bind_param("ii", $student_id, $professor_id);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
            
            if (!$student) {
                continue;
            }
            
            // Personalize message
            $personalized_body = str_replace('{student_name}', $student['full_name'], $body);
            
            // Insert into email queue
            $stmt = $conn->prepare("
                INSERT INTO email_queue (from_professor_id, to_student_id, subject, body, status)
                VALUES (?, ?, ?, ?, 'sent')
            ");
            $stmt->bind_param("iiss", $professor_id, $student_id, $subject, $personalized_body);
            $stmt->execute();
            
            // In production, integrate with actual email service (SMTP, SendGrid, etc.)
            // For now, we're just logging to database
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Email sent']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function sendBulkEmail($conn, $professor_id) {
    $filter = $_POST['filter'];
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);
    
    if (empty($subject) || empty($body)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Build query based on filter
    $query = "
        SELECT id, email, CONCAT(first_name, ' ', last_name) as full_name
        FROM students
        WHERE advisor_id = ?
    ";
    
    if ($filter === 'cleared') {
        $query .= " AND advising_cleared = 1";
    } elseif ($filter === 'not_cleared') {
        $query .= " AND advising_cleared = 0";
    } elseif ($filter === 'at_risk') {
        $query .= " AND accumulated_failed_units >= 15";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $count = 0;
    $conn->begin_transaction();
    
    try {
        while ($student = $result->fetch_assoc()) {
            $personalized_body = str_replace('{student_name}', $student['full_name'], $body);
            
            $stmt = $conn->prepare("
                INSERT INTO email_queue (from_professor_id, to_student_id, subject, body, status)
                VALUES (?, ?, ?, ?, 'sent')
            ");
            $stmt->bind_param("iiss", $professor_id, $student['id'], $subject, $personalized_body);
            $stmt->execute();
            $count++;
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'count' => $count]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getTemplates($conn, $professor_id) {
    $stmt = $conn->prepare("
        SELECT * FROM email_templates
        WHERE professor_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $templates = [];
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
    
    echo json_encode(['success' => true, 'templates' => $templates]);
}

function saveTemplate($conn, $professor_id) {
    $name = trim($_POST['name']);
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);
    
    if (empty($name) || empty($subject) || empty($body)) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        return;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO email_templates (professor_id, template_name, subject, body)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $professor_id, $name, $subject, $body);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function deleteTemplate($conn, $professor_id) {
    $template_id = $_POST['template_id'];
    
    $stmt = $conn->prepare("
        DELETE FROM email_templates
        WHERE id = ? AND professor_id = ?
    ");
    $stmt->bind_param("ii", $template_id, $professor_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getEmailHistory($conn, $professor_id) {
    $stmt = $conn->prepare("
        SELECT 
            eq.*,
            GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') as recipient_names
        FROM email_queue eq
        JOIN students s ON s.id = eq.to_student_id
        WHERE eq.from_professor_id = ?
        GROUP BY eq.id, eq.subject, eq.body, eq.sent_at
        ORDER BY eq.sent_at DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $emails = [];
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row;
    }
    
    echo json_encode(['success' => true, 'emails' => $emails]);
}
?>
