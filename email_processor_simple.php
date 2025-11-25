<?php
require_once 'config.php';

// Email configuration
define('FROM_EMAIL', 'advising@dlsu.edu.ph');
define('FROM_NAME', 'DLSU Academic Advising');

function sendQueuedEmails() {
    global $conn;
    
    // Get pending emails from queue
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
        try {
            // Prepare email headers
            $headers = "From: " . $email['professor_name'] . " <" . FROM_EMAIL . ">\r\n";
            $headers .= "Reply-To: " . $email['professor_email'] . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            // Send email
            $success = mail(
                $email['student_email'],
                $email['subject'],
                $email['body'],
                $headers
            );
            
            if ($success) {
                // Update status to sent
                $update = $conn->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
                $update->bind_param("i", $email['id']);
                $update->execute();
                $sent_count++;
            } else {
                throw new Exception("mail() function returned false");
            }
            
        } catch (Exception $e) {
            // Update status to failed
            $update = $conn->prepare("UPDATE email_queue SET status = 'failed', error_message = ? WHERE id = ?");
            $error_msg = $e->getMessage();
            $update->bind_param("si", $error_msg, $email['id']);
            $update->execute();
            
            $failed_count++;
            error_log("Failed to send email ID {$email['id']}: " . $e->getMessage());
        }
    }
    
    return [
        'sent' => $sent_count,
        'failed' => $failed_count
    ];
}

// Run the processor
if (php_sapi_name() === 'cli') {
    // Running from command line
    $result = sendQueuedEmails();
    echo "Emails sent: {$result['sent']}, Failed: {$result['failed']}\n";
} else {
    // Running from web
    header('Content-Type: application/json');
    $result = sendQueuedEmails();
    echo json_encode([
        'success' => true,
        'sent' => $result['sent'],
        'failed' => $result['failed']
    ]);
}
?>