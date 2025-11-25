<?php
// Test email functionality
require_once 'config.php';

echo "<h2>Email System Test</h2>\n";

// Test 1: Check email_queue table
echo "<h3>1. Checking email_queue table...</h3>\n";
$stmt = $conn->query("SELECT COUNT(*) as count FROM email_queue WHERE status = 'pending'");
$pending = $stmt->fetch_assoc()['count'];
echo "Pending emails in queue: $pending<br>\n";

// Test 2: Check if mail() function exists
echo "<h3>2. Checking PHP mail() function...</h3>\n";
if (function_exists('mail')) {
    echo "✓ mail() function is available<br>\n";
} else {
    echo "✗ mail() function is NOT available<br>\n";
}

// Test 3: Send a test email
echo "<h3>3. Sending test email...</h3>\n";
$to = "test@example.com"; // Change this to your email
$subject = "Test Email from Academic Advising System";
$message = "This is a test email to verify email functionality.";
$headers = "From: advising@dlsu.edu.ph\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$result = mail($to, $subject, $message, $headers);

if ($result) {
    echo "✓ Test email sent successfully<br>\n";
    echo "Check your inbox at: $to<br>\n";
} else {
    echo "✗ Failed to send test email<br>\n";
    echo "Note: mail() function may need SMTP configuration on your server<br>\n";
}

// Test 4: Check PHP configuration
echo "<h3>4. PHP Configuration:</h3>\n";
echo "sendmail_path: " . ini_get('sendmail_path') . "<br>\n";
echo "SMTP: " . ini_get('SMTP') . "<br>\n";
echo "smtp_port: " . ini_get('smtp_port') . "<br>\n";

echo "<h3>Summary:</h3>\n";
echo "If test email was sent successfully, the email system should work.<br>\n";
echo "If it failed, you may need to:<br>\n";
echo "1. Configure SMTP settings in php.ini<br>\n";
echo "2. Use PHPMailer library for better email support<br>\n";
echo "3. Check server email configuration<br>\n";
?>