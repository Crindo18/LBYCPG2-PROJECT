<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

ob_clean();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_all_advising_forms':
        getAllAdvisingForms();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getAllAdvisingForms() {
    global $conn;
    
    try {
        // Get all advising forms with student and adviser information
        $stmt = $conn->prepare("
            SELECT 
                aaf.*,
                s.id_number as student_id_number,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.program,
                s.email as student_email,
                CONCAT(p.first_name, ' ', p.last_name) as adviser_name,
                p.email as adviser_email,
                COUNT(afc.id) as course_count
            FROM academic_advising_forms aaf
            INNER JOIN students s ON s.id = aaf.student_id
            LEFT JOIN professors p ON p.id = s.advisor_id
            LEFT JOIN advising_form_courses afc ON afc.form_id = aaf.id
            GROUP BY aaf.id
            ORDER BY aaf.submission_date DESC
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $forms = [];
        while ($row = $result->fetch_assoc()) {
            $forms[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'forms' => $forms
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>
