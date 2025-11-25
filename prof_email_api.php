<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'professor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$professor_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// --- Helper Function: Create Mailer ---
function createMailer() {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = MAILER_HOST;
    $mail->Port = MAILER_PORT;
    if (MAILER_USERNAME) {
        $mail->SMTPAuth = true;
        $mail->Username = MAILER_USERNAME;
        $mail->Password = MAILER_PASSWORD;
    }
    $mail->SMTPSecure = (MAILER_ENCRYPTION === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->setFrom(MAILER_FROM_EMAIL, MAILER_FROM_NAME);
    $mail->isHTML(true);
    return $mail;
}

switch ($action) {
    case 'get_advisees':
        $stmt = $conn->prepare("SELECT id, id_number, CONCAT(first_name, ' ', last_name) as full_name, email FROM students WHERE advisor_id = ? ORDER BY last_name");
        $stmt->bind_param("i", $professor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $students = [];
        while($row = $result->fetch_assoc()) $students[] = $row;
        echo json_encode(['success' => true, 'students' => $students]);
        break;

    case 'send_email':
        $recipients = json_decode($_POST['recipients'], true);
        $subject = $_POST['subject'];
        $message = $_POST['message'];
        $send_immediately = $_POST['send_immediately'] == '1';
        
        $conn->begin_transaction();
        $sent = 0; $queued = 0;
        
        $mail = createMailer();
        
        foreach ($recipients as $sid) {
            // Get student email
            $s = $conn->query("SELECT email, CONCAT(first_name, ' ', last_name) as name FROM students WHERE id = $sid")->fetch_assoc();
            if(!$s) continue;
            
            $status = 'pending';
            
            if ($send_immediately) {
                try {
                    $mail->clearAddresses();
                    $mail->addAddress($s['email'], $s['name']);
                    $mail->Subject = $subject;
                    $mail->Body = nl2br($message); // basic formatting
                    $mail->send();
                    $status = 'sent';
                    $sent++;
                } catch (Exception $e) {
                    $status = 'failed';
                }
            } else {
                $queued++;
            }
            
            $stmt = $conn->prepare("INSERT INTO email_queue (from_professor_id, to_student_id, subject, body, status, created_at, sent_at) VALUES (?, ?, ?, ?, ?, NOW(), " . ($status=='sent' ? "NOW()" : "NULL") . ")");
            $stmt->bind_param("iisss", $professor_id, $sid, $subject, $message, $status);
            $stmt->execute();
        }
        $conn->commit();
        
        $msg = $send_immediately ? "Sent $sent emails." : "Queued $queued emails.";
        if($send_immediately && $sent < count($recipients)) $msg .= " (Some failed)";
        echo json_encode(['success' => true, 'message' => $msg]);
        break;

    case 'get_sent_emails':
        // Aligned with frontend action name
        $stmt = $conn->prepare("
            SELECT eq.*, CONCAT(s.first_name, ' ', s.last_name) as recipient_name 
            FROM email_queue eq 
            JOIN students s ON s.id = eq.to_student_id 
            WHERE eq.from_professor_id = ? 
            ORDER BY eq.created_at DESC LIMIT 50
        ");
        $stmt->bind_param("i", $professor_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $emails = [];
        while($row = $res->fetch_assoc()) $emails[] = $row;
        echo json_encode(['success' => true, 'emails' => $emails]);
        break;

    case 'process_emails':
        // Process all pending emails for this prof
        $stmt = $conn->prepare("
            SELECT eq.*, s.email as student_email, CONCAT(s.first_name, ' ', s.last_name) as student_name 
            FROM email_queue eq
            JOIN students s ON s.id = eq.to_student_id
            WHERE eq.from_professor_id = ? AND eq.status = 'pending'
        ");
        $stmt->bind_param("i", $professor_id);
        $stmt->execute();
        $pending = $stmt->get_result();
        
        $processed = 0;
        $mail = createMailer();
        
        while($email = $pending->fetch_assoc()) {
            try {
                $mail->clearAddresses();
                $mail->addAddress($email['student_email'], $email['student_name']);
                $mail->Subject = $email['subject'];
                $mail->Body = nl2br($email['body']);
                $mail->send();
                
                $conn->query("UPDATE email_queue SET status='sent', sent_at=NOW() WHERE id=" . $email['id']);
                $processed++;
            } catch (Exception $e) {
                $conn->query("UPDATE email_queue SET status='failed' WHERE id=" . $email['id']);
            }
        }
        echo json_encode(['success' => true, 'message' => "Processed $processed emails."]);
        break;

    case 'get_templates':
        $res = $conn->query("SELECT * FROM email_templates WHERE professor_id = $professor_id ORDER BY created_at DESC");
        $tpl = [];
        while($r = $res->fetch_assoc()) $tpl[] = $r;
        echo json_encode(['success' => true, 'templates' => $tpl]);
        break;

    case 'save_template':
        $name = $_POST['template_name'];
        $sub = $_POST['subject'];
        $bod = $_POST['body'];
        $stmt = $conn->prepare("INSERT INTO email_templates (professor_id, template_name, subject, body) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $professor_id, $name, $sub, $bod);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Template saved']);
        break;

    case 'delete_template':
        $id = $_POST['id'];
        $conn->query("DELETE FROM email_templates WHERE id=$id AND professor_id=$professor_id");
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>