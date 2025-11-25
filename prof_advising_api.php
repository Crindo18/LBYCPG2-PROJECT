<?php
require_once 'auth_check.php';
requireProfessor();

require_once 'config.php';

header('Content-Type: application/json');

$professor_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_advising_forms':
        getAdvisingForms();
        break;
    case 'get_form_details':
        getFormDetails();
        break;
    case 'update_form_status':
        updateFormStatus();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getAdvisingForms() {
    global $conn, $professor_id;
    
    try {
        // Get all advising forms for students advised by this professor
        $stmt = $conn->prepare("
            SELECT 
                aaf.id as form_id,
                aaf.student_id,
                s.id_number,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.program,
                aaf.form_data,
                aaf.status,
                aaf.submitted_at,
                aaf.reviewed_at,
                aaf.adviser_comments
            FROM academic_advising_forms aaf
            JOIN students s ON s.id = aaf.student_id
            WHERE s.advisor_id = ?
            ORDER BY aaf.submitted_at DESC
        ");
        
        $stmt->bind_param("i", $professor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $forms = [];
        $stats = [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'revision_requested' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            // Parse JSON form data
            $formData = json_decode($row['form_data'], true);
            
            $form = [
                'form_id' => $row['form_id'],
                'student_id' => $row['student_id'],
                'id_number' => $row['id_number'],
                'student_name' => $row['student_name'],
                'program' => $row['program'],
                'academic_year' => $formData['academic_year'] ?? 'N/A',
                'term' => $formData['term'] ?? 'N/A',
                'status' => $row['status'],
                'submitted_at' => $row['submitted_at'],
                'reviewed_at' => $row['reviewed_at'],
                'adviser_comments' => $row['adviser_comments']
            ];
            
            $forms[] = $form;
            
            // Update stats
            if (isset($stats[$row['status']])) {
                $stats[$row['status']]++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'forms' => $forms,
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error loading forms: ' . $e->getMessage()
        ]);
    }
}

function getFormDetails() {
    global $conn, $professor_id;
    
    $form_id = $_GET['form_id'] ?? 0;
    
    try {
        // Get form details
        $stmt = $conn->prepare("
            SELECT 
                aaf.*,
                s.id_number,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.email as student_email,
                s.program,
                s.year_level,
                s.accumulated_failed_units
            FROM academic_advising_forms aaf
            JOIN students s ON s.id = aaf.student_id
            WHERE aaf.id = ? AND s.advisor_id = ?
        ");
        
        $stmt->bind_param("ii", $form_id, $professor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Form not found or access denied'
            ]);
            return;
        }
        
        $form = $result->fetch_assoc();
        $form['form_data'] = json_decode($form['form_data'], true);
        
        // Get courses for this form
        $stmt = $conn->prepare("
            SELECT 
                afc.id as course_id,
                afc.course_type,
                afc.course_code,
                afc.prerequisites as prereq_text,
                afc.units
            FROM advising_form_courses afc
            WHERE afc.form_id = ?
            ORDER BY afc.course_type, afc.id
        ");
        
        $stmt->bind_param("i", $form_id);
        $stmt->execute();
        $courses_result = $stmt->get_result();
        
        $courses = [
            'current' => [],
            'next' => []
        ];
        
        while ($course = $courses_result->fetch_assoc()) {
            // Get prerequisites for each course
            $prereq_stmt = $conn->prepare("
                SELECT 
                    prerequisite_code,
                    prerequisite_type,
                    grade_received
                FROM advising_form_prerequisites
                WHERE course_id = ?
            ");
            
            $prereq_stmt->bind_param("i", $course['course_id']);
            $prereq_stmt->execute();
            $prereq_result = $prereq_stmt->get_result();
            
            $prerequisites = [];
            while ($prereq = $prereq_result->fetch_assoc()) {
                $prerequisites[] = $prereq;
            }
            
            $course['prerequisites'] = $prerequisites;
            $courses[$course['course_type']][] = $course;
        }
        
        $form['courses'] = $courses;
        
        echo json_encode([
            'success' => true,
            'form' => $form
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error loading form details: ' . $e->getMessage()
        ]);
    }
}

function updateFormStatus() {
    global $conn, $professor_id;
    
    $form_id = $_POST['form_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    $comments = $_POST['comments'] ?? '';
    
    // Validate status
    $validStatuses = ['approved', 'rejected', 'revision_requested'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid status'
        ]);
        return;
    }
    
    try {
        // Verify professor has access to this form
        $stmt = $conn->prepare("
            SELECT aaf.id 
            FROM academic_advising_forms aaf
            JOIN students s ON s.id = aaf.student_id
            WHERE aaf.id = ? AND s.advisor_id = ?
        ");
        
        $stmt->bind_param("ii", $form_id, $professor_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Form not found or access denied'
            ]);
            return;
        }
        
        // Update form status
        $stmt = $conn->prepare("
            UPDATE academic_advising_forms 
            SET status = ?,
                adviser_id = ?,
                adviser_comments = ?,
                reviewed_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param("sisi", $status, $professor_id, $comments, $form_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Form status updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update form status'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating form: ' . $e->getMessage()
        ]);
    }
}
?>
