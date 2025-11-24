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
    case 'get_advisees':
        getAdvisees();
        break;
    case 'send_email':
        sendEmail();
        break;
    case 'get_templates':
        getTemplates();
        break;
    case 'get_template':
        getTemplate();
        break;
    case 'save_template':
        saveTemplate();
        break;
    case 'delete_template':
        deleteTemplate();
        break;
    case 'get_sent_emails':
        getSentEmails();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getAdvisees() {
    global $conn, $professor_id;
    $stmt = $conn->prepare("SELECT id, id_number, CONCAT(first_name, ' ', last_name) as name FROM students WHERE advisor_id = ? ORDER BY last_name, first_name");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    echo json_encode(['success' => true, 'students' => $students]);
}

function sendEmail() {
    global $conn, $professor_id;
    $recipients = json_decode($_POST['recipients'], true);
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $send_immediately = $_POST['send_immediately'] === '1';
    
    $stmt = $conn->prepare("INSERT INTO email_queue (from_professor_id, to_student_id, subject, body, send_immediately) VALUES (?, ?, ?, ?, ?)");
    $success_count = 0;
    foreach ($recipients as $student_id) {
        $stmt->bind_param("iissi", $professor_id, $student_id, $subject, $message, $send_immediately);
        if ($stmt->execute()) $success_count++;
    }
    echo json_encode(['success' => true, 'message' => "Email queued for $success_count recipient(s)"]);
}

function getTemplates() {
    global $conn, $professor_id;
    $stmt = $conn->prepare("SELECT * FROM email_templates WHERE professor_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $professor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $templates = [];
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
    echo json_encode(['success' => true, 'templates' => $templates]);
}

function getTemplate() {
    global $conn, $professor_id;
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM email_templates WHERE id = ? AND professor_id = ?");
    $stmt->bind_param("ii", $id, $professor_id);
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    echo json_encode(['success' => true, 'template' => $template]);
}

function saveTemplate() {
    global $conn, $professor_id;
    $template_name = $_POST['template_name'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $stmt = $conn->prepare("INSERT INTO email_templates (professor_id, template_name, subject, body) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $professor_id, $template_name, $subject, $body);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Template saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save template']);
    }
}

function deleteTemplate() {
    global $conn, $professor_id;
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM email_templates WHERE id = ? AND professor_id = ?");
    $stmt->bind_param("ii", $id, $professor_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Template deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete template']);
    }
}

function getSentEmails() {
    global $conn, $professor_id;
    $stmt = $conn->prepare("
        SELECT eq.*, CONCAT(s.first_name, ' ', s.last_name) as recipient_name
        FROM email_queue eq
        JOIN students s ON s.id = eq.to_student_id
        WHERE eq.from_professor_id = ?
        ORDER BY eq.created_at DESC
        LIMIT 100
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
