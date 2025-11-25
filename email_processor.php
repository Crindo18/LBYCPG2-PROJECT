<?php
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email configuration - UPDATE WITH YOUR SETTINGS
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'DLSU Academic Advising');

function sendQueuedEmails() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            eq.*,
            s.email as student_email,
            s.first_name as student_first_name,
            CONCAT(s.first_name, ' ', s.last_name) as student_name,
            CONCAT(p.first_name, ' ', p.last_name) as professor_name,
            p.email as professor_email
        FROM email_queue eq
        JOIN students s ON s.id = eq.to_student_id
        JOIN professors p ON p.id = eq.from_professor_id
        WHERE eq.status = 'pending'
        ORDER BY eq.created_at ASC
        LIMIT 50
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sent_count = 0;
    $failed_count = 0;
    
    while ($email = $result->fetch_assoc()) {
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            
            $mail->setFrom(SMTP_FROM_EMAIL, $email['professor_name'] . ' via ' . SMTP_FROM_NAME);
            $mail->addAddress($email['student_email'], $email['student_name']);
            $mail->addReplyTo($email['professor_email'], $email['professor_name']);
            
            $mail->isHTML(true);
            $mail->Subject = $email['subject'];
            $mail->Body = nl2br(htmlspecialchars($email['body']));
            $mail->AltBody = $email['body'];
            
            $mail->send();
            
            $update = $conn->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $update->bind_param("i", $email['id']);
            $update->execute();
            
            $sent_count++;
            
        } catch (Exception $e) {
            $update = $conn->prepare("UPDATE email_queue SET status = 'failed', error_message = ? WHERE id = ?");
            $error_msg = "Mailer Error: {$mail->ErrorInfo}";
            $update->bind_param("si", $error_msg, $email['id']);
            $update->execute();
            
            $failed_count++;
            error_log("Failed to send email ID {$email['id']}: {$mail->ErrorInfo}");
        }
    }
    
    return ['sent' => $sent_count, 'failed' => $failed_count];
}

if (php_sapi_name() === 'cli') {
    $result = sendQueuedEmails();
    echo "Emails sent: {$result['sent']}, Failed: {$result['failed']}\n";
} else {
    header('Content-Type: application/json');
    $result = sendQueuedEmails();
    echo json_encode(['success' => true, 'sent' => $result['sent'], 'failed' => $result['failed']]);
}
?>