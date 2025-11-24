<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

ob_clean();
header('Content-Type: application/json');

$student_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'submit_advising_form':
        submitAdvisingForm();
        break;
    case 'get_advising_forms':
        getAdvisingForms();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function submitAdvisingForm() {
    global $conn, $student_id;
    
    try {
        // Validate required fields
        $required_fields = ['academic_year', 'term', 'current_year_failed_units', 'overall_failed_units',
                           'max_units', 'total_enrolled_units', 'certify_prerequisites', 'certify_accuracy'];
        
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Validate certifications
        if ($_POST['certify_prerequisites'] != '1' || $_POST['certify_accuracy'] != '1') {
            throw new Exception('You must certify all required statements');
        }
        
        // Handle file uploads
        $uploadDir = 'uploads/advising_forms/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Upload grade screenshot
        if (!isset($_FILES['grade_screenshot']) || $_FILES['grade_screenshot']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Grade screenshot upload failed');
        }
        
        $gradeScreenshot = $_FILES['grade_screenshot'];
        $gradeExt = strtolower(pathinfo($gradeScreenshot['name'], PATHINFO_EXTENSION));
        $gradeFilename = $student_id . '_' . time() . '_grade.' . $gradeExt;
        $gradeTargetPath = $uploadDir . $gradeFilename;
        
        if (!move_uploaded_file($gradeScreenshot['tmp_name'], $gradeTargetPath)) {
            throw new Exception('Failed to save grade screenshot');
        }
        
        // Parse current courses JSON
        $currentCourses = json_decode($_POST['current_courses'], true);
        if (!$currentCourses || !is_array($currentCourses)) {
            throw new Exception('Invalid current courses data');
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Pack data into JSON for 'form_data' column
        $formDataArray = [
            'academic_year' => $_POST['academic_year'],
            'term' => $_POST['term'],
            'current_year_failed_units' => $_POST['current_year_failed_units'],
            'overall_failed_units' => $_POST['overall_failed_units'],
            'previous_term_gpa' => $_POST['previous_term_gpa'],
            'cumulative_gpa' => $_POST['cumulative_gpa'],
            'max_course_load_units' => $_POST['max_units'],
            'total_enrolled_units' => $_POST['total_enrolled_units'],
            'additional_notes' => $_POST['additional_notes'],
            'certify_prerequisites' => $_POST['certify_prerequisites'],
            'certify_accuracy' => $_POST['certify_accuracy'],
            'request_meeting' => $_POST['request_meeting']
        ];
        $formDataJson = json_encode($formDataArray);

        // Insert using the correct columns from your database schema
        // Note: booklet_file column is set to NULL as it is no longer required
        $stmt = $conn->prepare("
            INSERT INTO academic_advising_forms (
                student_id, 
                form_data, 
                grades_screenshot, 
                booklet_file, 
                status, 
                submitted_at
            ) VALUES (?, ?, ?, NULL, 'pending', NOW())
        ");
        
        $stmt->bind_param(
            "iss",
            $student_id,
            $formDataJson,
            $gradeTargetPath
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert advising form: ' . $stmt->error);
        }
        
        $formId = $conn->insert_id;
        
        // Insert current enrolled courses
        $stmt = $conn->prepare("
            INSERT INTO advising_form_courses (
                form_id, course_type, course_code, units
            ) VALUES (?, 'current', ?, ?)
        ");
        
        foreach ($currentCourses as $course) {
            $stmt->bind_param("isi", $formId, $course['code'], $course['units']);
            if (!$stmt->execute()) {
                throw new Exception('Failed to insert course: ' . $stmt->error);
            }
            
            $courseId = $conn->insert_id;
            
            // Insert prerequisites for this course
            if (!empty($course['prerequisites'])) {
                $prereqStmt = $conn->prepare("
                    INSERT INTO advising_form_prerequisites (
                        course_id, prerequisite_code, prerequisite_type, grade_received
                    ) VALUES (?, ?, ?, ?)
                ");
                
                foreach ($course['prerequisites'] as $prereq) {
                    $prereqStmt->bind_param(
                        "isss",
                        $courseId,
                        $prereq['code'],
                        $prereq['type'],
                        $prereq['grade']
                    );
                    $prereqStmt->execute();
                }
            }
        }
        
        // Update student's accumulated failed units
        $stmt = $conn->prepare("UPDATE students SET accumulated_failed_units = ? WHERE id = ?");
        $stmt->bind_param("ii", $_POST['overall_failed_units'], $student_id);
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Academic advising form submitted successfully',
            'form_id' => $formId
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        
        // Clean up uploaded files on error
        if (isset($gradeTargetPath) && file_exists($gradeTargetPath)) {
            unlink($gradeTargetPath);
        }
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function getAdvisingForms() {
    global $conn, $student_id;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                aaf.*,
                CONCAT(p.first_name, ' ', p.last_name) as adviser_name,
                COUNT(afc.id) as course_count
            FROM academic_advising_forms aaf
            LEFT JOIN students s ON s.id = aaf.student_id
            LEFT JOIN professors p ON p.id = s.advisor_id
            LEFT JOIN advising_form_courses afc ON afc.form_id = aaf.id
            WHERE aaf.student_id = ?
            GROUP BY aaf.id
            ORDER BY aaf.submitted_at DESC
        ");
        
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $forms = [];
        while ($row = $result->fetch_assoc()) {
            // Unpack JSON data so the frontend can read it
            if (!empty($row['form_data'])) {
                $jsonData = json_decode($row['form_data'], true);
                if (is_array($jsonData)) {
                    // Merge JSON data into the row array
                    $row = array_merge($row, $jsonData);
                }
            }
            // Map DB column names to what frontend expects for status badges
            if (isset($row['adviser_comments'])) {
                $row['adviser_feedback'] = $row['adviser_comments'];
            }
            if (isset($row['submitted_at'])) {
                $row['submission_date'] = $row['submitted_at'];
            }
            if ($row['status'] === 'approved') {
                $row['cleared'] = 1;
            } else {
                $row['cleared'] = 0;
            }

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