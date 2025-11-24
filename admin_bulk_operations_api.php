<?php
require_once 'auth_check.php';
requireAdmin();

require_once 'config.php';

ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

ob_clean();
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_advisers':
        getAdvisers();
        break;
    case 'bulk_clearance':
        bulkClearance();
        break;
    case 'send_mass_email':
        sendMassEmail();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getAdvisers() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT id, CONCAT(first_name, ' ', last_name) as name 
        FROM professors 
        ORDER BY last_name
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $advisers = [];
    while ($row = $result->fetch_assoc()) {
        $advisers[] = $row;
    }
    
    echo json_encode(['success' => true, 'advisers' => $advisers]);
}

function bulkClearance() {
    global $conn;
    
    $clearance_action = $_POST['clearance_action'] ?? '';
    $target = $_POST['target'] ?? '';
    
    $clear_value = ($clearance_action === 'clear') ? 1 : 0;
    
    // Build WHERE clause
    $where = "1=1";
    
    if ($target === 'program') {
        $program = $_POST['program'] ?? '';
        $where .= " AND program = '" . $conn->real_escape_string($program) . "'";
    } elseif ($target === 'adviser') {
        $adviser_id = $_POST['adviser_id'] ?? 0;
        $where .= " AND advisor_id = " . (int)$adviser_id;
    } elseif ($target === 'list') {
        $student_list = $_POST['student_list'] ?? '';
        $ids = array_filter(array_map('trim', explode("\n", $student_list)));
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No student IDs provided']);
            return;
        }
        $id_list = "'" . implode("','", array_map([$conn, 'real_escape_string'], $ids)) . "'";
        $where .= " AND id_number IN ($id_list)";
    }
    
    // Execute update
    $query = "UPDATE students SET advising_cleared = $clear_value WHERE $where";
    $conn->query($query);
    $affected = $conn->affected_rows;
    
    echo json_encode([
        'success' => true,
        'message' => "$affected students " . ($clear_value ? 'cleared' : 'uncleared'),
        'stats' => [
            'affected' => $affected
        ]
    ]);
}

function sendMassEmail() {
    global $conn;
    
    $recipients = $_POST['recipients'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $preview = !empty($_POST['preview']);
    
    if (!$recipients || $subject === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Recipients, subject, and message are required']);
        return;
    }
    
    $query = "SELECT id, CONCAT(first_name, ' ', last_name) as name, email, id_number, program FROM ";
    
    switch ($recipients) {
        case 'all_students':
            $query .= "students";
            break;
        case 'all_professors':
            $query .= "professors";
            break;
        case 'program':
            $program = $_POST['program'] ?? '';
            $query .= "students WHERE program = '" . $conn->real_escape_string($program) . "'";
            break;
        case 'cleared':
            $query .= "students WHERE advising_cleared = 1";
            break;
        case 'not_cleared':
            $query .= "students WHERE advising_cleared = 0";
            break;
        case 'at_risk':
            $query .= "students WHERE accumulated_failed_units >= 25";
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid recipient selection']);
            return;
    }
    
    $result = $conn->query($query);
    $targets = [];
    while ($row = $result->fetch_assoc()) {
        $targets[] = $row;
        if ($preview) {
            break;
        }
    }
    
    if (empty($targets)) {
        echo json_encode(['success' => false, 'message' => 'No recipients match the selected filter']);
        return;
    }
    
    if ($preview) {
        $adminContact = getAdminContact();
        if (!$adminContact || empty($adminContact['email'])) {
            echo json_encode(['success' => false, 'message' => 'Admin account is missing an email address for previews']);
            return;
        }
        
        $sampleUser = $targets[0];
        $body = personalizeMessage($message, $sampleUser);
        
        try {
            $mail = createMailer();
            $mail->addAddress($adminContact['email'], $adminContact['name']);
            $mail->Subject = '[PREVIEW] ' . $subject;
            $mail->Body = nl2br($body);
            $mail->AltBody = strip_tags($body);
            $mail->send();
            
            echo json_encode([
                'success' => true,
                'message' => "Preview email sent to {$adminContact['email']}",
                'stats' => [
                    'emails_sent' => 1,
                    'failed' => 0
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Preview send failed: ' . $e->getMessage()]);
        }
        return;
    }
    
    $sent = 0;
    $failed = [];
    
    foreach ($targets as $user) {
        if (empty($user['email'])) {
            $failed[] = ($user['name'] ?? 'Unknown') . ' (missing email)';
            continue;
        }
        
        try {
            $mail = createMailer();
            $mail->addAddress($user['email'], $user['name'] ?? '');
            $mail->Subject = $subject;
            $body = personalizeMessage($message, $user);
            $mail->Body = nl2br($body);
            $mail->AltBody = strip_tags($body);
            $mail->send();
            $sent++;
        } catch (Exception $e) {
            $failed[] = $user['email'] . ': ' . $mail->ErrorInfo;
        }
    }
    
    $responseMessage = "$sent email(s) sent.";
    if ($failed) {
        $responseMessage .= ' ' . count($failed) . ' failed.';
    }
    
    echo json_encode([
        'success' => $sent > 0,
        'message' => $responseMessage,
        'stats' => [
            'emails_sent' => $sent,
            'failed' => count($failed)
        ],
        'errors' => $failed
    ]);
}

function createMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = MAILER_HOST;
    $mail->Port = MAILER_PORT;
    
    if (MAILER_USERNAME) {
        $mail->SMTPAuth = true;
        $mail->Username = MAILER_USERNAME;
        $mail->Password = MAILER_PASSWORD;
    } else {
        $mail->SMTPAuth = false;
    }
    
    $encryption = strtolower(MAILER_ENCRYPTION);
    if ($encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    
    $mail->setFrom(MAILER_FROM_EMAIL, MAILER_FROM_NAME);
    $mail->isHTML(true);
    
    return $mail;
}

function personalizeMessage(string $template, array $user): string {
    return str_replace(
        ['{name}', '{id_number}', '{program}'],
        [
            $user['name'] ?? 'Student',
            $user['id_number'] ?? 'N/A',
            $user['program'] ?? 'N/A'
        ],
        $template
    );
}

function getAdminContact(): ?array {
    global $conn;
    
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT username, email FROM admin WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return [
            'name' => $row['username'] ?: 'Administrator',
            'email' => $row['email'] ?? null
        ];
    }
    
    return null;
}
?>
