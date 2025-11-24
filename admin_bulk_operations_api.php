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

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_advisers':
        getAdvisers();
        break;
    case 'upload_grades':
        uploadGrades();
        break;
    case 'calculate_gpa':
        calculateGPA();
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

function uploadGrades() {
    global $conn;
    
    $academic_year = $_POST['academic_year'] ?? '';
    $term = $_POST['term'] ?? '';
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        return;
    }
    
    $file = $_FILES['file']['tmp_name'];
    $handle = fopen($file, 'r');
    
    if (!$handle) {
        echo json_encode(['success' => false, 'message' => 'Could not open file']);
        return;
    }
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    // Skip header row
    fgetcsv($handle);
    
    $conn->begin_transaction();
    
    try {
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 4) continue;
            
            $student_id = trim($data[0]);
            $course_code = trim($data[1]);
            $grade = floatval($data[2]);
            $is_failed = ($data[3] == '1' || strtolower($data[3]) == 'true') ? 1 : 0;
            
            // Get student by ID number
            $stmt = $conn->prepare("SELECT id FROM students WHERE id_number = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $student_result = $stmt->get_result();
            
            if ($student_result->num_rows === 0) {
                $errors[] = "Student $student_id not found";
                $error_count++;
                continue;
            }
            
            $student_db_id = $student_result->fetch_assoc()['id'];
            
            // Get course name
            $stmt = $conn->prepare("SELECT course_name, units FROM course_catalog WHERE course_code = ?");
            $stmt->bind_param("s", $course_code);
            $stmt->execute();
            $course_result = $stmt->get_result();
            
            if ($course_result->num_rows === 0) {
                $errors[] = "Course $course_code not found";
                $error_count++;
                continue;
            }
            
            $course = $course_result->fetch_assoc();
            
            // Insert or update grade
            $stmt = $conn->prepare("
                INSERT INTO student_advising_booklet 
                (student_id, academic_year, term, course_code, course_name, units, grade, is_failed)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE grade = ?, is_failed = ?
            ");
            $stmt->bind_param("isissiidid", 
                $student_db_id, $academic_year, $term, $course_code, 
                $course['course_name'], $course['units'], $grade, $is_failed,
                $grade, $is_failed
            );
            
            if ($stmt->execute()) {
                $success_count++;
                
                // Update failed units if failed
                if ($is_failed) {
                    $conn->query("
                        UPDATE students 
                        SET accumulated_failed_units = (
                            SELECT SUM(units) 
                            FROM student_advising_booklet 
                            WHERE student_id = $student_db_id AND is_failed = 1
                        )
                        WHERE id = $student_db_id
                    ");
                }
            } else {
                $error_count++;
                $errors[] = "Failed to insert grade for $student_id - $course_code";
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Grades uploaded successfully",
            'stats' => [
                'success' => $success_count,
                'errors' => $error_count,
                'total' => $success_count + $error_count
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    fclose($handle);
}

function calculateGPA() {
    global $conn;
    
    $academic_year = $_POST['academic_year'] ?? '';
    $term = $_POST['term'] ?? '';
    $program = $_POST['program'] ?? '';
    $recalculate = $_POST['recalculate'] ?? 0;
    
    // Build student query
    $student_query = "SELECT id FROM students WHERE 1=1";
    if ($program) {
        $student_query .= " AND program = '" . $conn->real_escape_string($program) . "'";
    }
    
    $students = $conn->query($student_query);
    
    $success_count = 0;
    $error_count = 0;
    
    while ($student = $students->fetch_assoc()) {
        $student_id = $student['id'];
        
        // Check if GPA already exists
        $check = $conn->query("
            SELECT id FROM term_gpa_summary 
            WHERE student_id = $student_id 
            AND academic_year = '$academic_year' 
            AND term = $term
        ");
        
        if ($check->num_rows > 0 && !$recalculate) {
            continue; // Skip if exists and not recalculating
        }
        
        // Calculate term GPA
        $grades_query = "
            SELECT grade, units 
            FROM student_advising_booklet 
            WHERE student_id = $student_id 
            AND academic_year = '$academic_year' 
            AND term = $term
            AND grade IS NOT NULL
        ";
        
        $grades = $conn->query($grades_query);
        $total_points = 0;
        $total_units = 0;
        $units_passed = 0;
        $units_failed = 0;
        
        while ($grade = $grades->fetch_assoc()) {
            $total_points += $grade['grade'] * $grade['units'];
            $total_units += $grade['units'];
            
            if ($grade['grade'] >= 1.0) {
                $units_passed += $grade['units'];
            } else {
                $units_failed += $grade['units'];
            }
        }
        
        $term_gpa = $total_units > 0 ? round($total_points / $total_units, 2) : 0;
        
        // Calculate cumulative GPA
        $all_grades = $conn->query("
            SELECT grade, units 
            FROM student_advising_booklet 
            WHERE student_id = $student_id 
            AND grade IS NOT NULL
        ");
        
        $cum_points = 0;
        $cum_units = 0;
        
        while ($grade = $all_grades->fetch_assoc()) {
            $cum_points += $grade['grade'] * $grade['units'];
            $cum_units += $grade['units'];
        }
        
        $cgpa = $cum_units > 0 ? round($cum_points / $cum_units, 2) : 0;
        
        // Determine honors
        $honors = '';
        if ($term_gpa >= 3.80) $honors = 'Summa Cum Laude';
        elseif ($term_gpa >= 3.60) $honors = 'Magna Cum Laude';
        elseif ($term_gpa >= 3.40) $honors = 'Cum Laude';
        elseif ($term_gpa >= 3.00) $honors = "Dean's List";
        
        // Insert or update GPA record
        $stmt = $conn->prepare("
            INSERT INTO term_gpa_summary 
            (student_id, academic_year, term, term_gpa, cgpa, total_units_taken, 
             total_units_passed, total_units_failed, accumulated_failed_units, trimestral_honors)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 
                (SELECT accumulated_failed_units FROM students WHERE id = ?), ?)
            ON DUPLICATE KEY UPDATE 
                term_gpa = ?, cgpa = ?, 
                total_units_taken = ?, total_units_passed = ?, total_units_failed = ?,
                trimestral_honors = ?
        ");
        
        $stmt->bind_param("isiddiiiisddiis", 
            $student_id, $academic_year, $term, $term_gpa, $cgpa,
            $total_units, $units_passed, $units_failed, $student_id, $honors,
            $term_gpa, $cgpa, $total_units, $units_passed, $units_failed, $honors
        );
        
        if ($stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "GPA calculated for $success_count students",
        'stats' => [
            'calculated' => $success_count,
            'skipped' => $error_count
        ]
    ]);
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
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $preview = $_POST['preview'] ?? 0;
    
    // Build recipient query
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
    $email_count = 0;
    
    // Note: This is a simulation. In production, integrate with mail server (PHPMailer, SendGrid, etc.)
    while ($user = $result->fetch_assoc()) {
        // Replace variables in message
        $personalized_message = str_replace(
            ['{name}', '{id_number}', '{program}'],
            [$user['name'], $user['id_number'] ?? 'N/A', $user['program'] ?? 'N/A'],
            $message
        );
        
        // Simulate sending email (in production, use mail() or PHPMailer)
        // mail($user['email'], $subject, $personalized_message);
        
        $email_count++;
        
        if ($preview) {
            break; // Send only one for preview
        }
    }
    
    $action_text = $preview ? 'Preview email would be sent' : 'Emails queued for sending';
    
    echo json_encode([
        'success' => true,
        'message' => "$action_text to $email_count recipients",
        'stats' => [
            'emails_sent' => $email_count
        ]
    ]);
}
?>
