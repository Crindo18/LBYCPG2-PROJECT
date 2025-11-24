<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('HTTP/1.0 403 Forbidden');
    exit('Unauthorized');
}

$student_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'check_status':
        checkDocumentStatus();
        break;
    case 'download':
        downloadDocument();
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function checkDocumentStatus() {
    global $conn, $student_id;
    
    header('Content-Type: application/json');
    
    // Check if student has study plans
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM study_plans WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $has_study_plan = $stmt->get_result()->fetch_assoc()['count'] > 0;
    
    // Check clearance status
    $stmt = $conn->prepare("SELECT advising_cleared FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $is_cleared = $stmt->get_result()->fetch_assoc()['advising_cleared'] == 1;
    
    echo json_encode([
        'success' => true,
        'has_study_plan' => $has_study_plan,
        'is_cleared' => $is_cleared
    ]);
}

function downloadDocument() {
    global $conn, $student_id;
    
    $type = $_GET['type'] ?? '';
    
    switch ($type) {
        case 'booklet':
            generateBookletPDF();
            break;
        case 'studyplan':
            generateStudyPlanPDF();
            break;
        case 'clearance':
            generateClearancePDF();
            break;
        case 'gpa':
            generateGPAPDF();
            break;
        default:
            header('HTTP/1.0 400 Bad Request');
            echo 'Invalid document type';
    }
}

function generateBookletPDF() {
    global $conn, $student_id;
    
    // Get student info
    $stmt = $conn->prepare("
        SELECT s.*, CONCAT(s.first_name, ' ', s.last_name) as full_name,
               CONCAT(p.first_name, ' ', p.last_name) as adviser_name
        FROM students s
        LEFT JOIN professors p ON p.id = s.advisor_id
        WHERE s.id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    
    // Get booklet records
    $stmt = $conn->prepare("
        SELECT * FROM student_advising_booklet 
        WHERE student_id = ? 
        ORDER BY academic_year, term
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Generate simple HTML for PDF
    $html = generateBookletHTML($student, $records);
    outputHTMLAsPDF($html, 'academic_booklet.pdf');
}

function generateStudyPlanPDF() {
    global $conn, $student_id;
    
    // Get latest study plan
    $stmt = $conn->prepare("
        SELECT sp.*, CONCAT(s.first_name, ' ', s.last_name) as student_name,
               s.id_number, s.program,
               CONCAT(p.first_name, ' ', p.last_name) as adviser_name
        FROM study_plans sp
        JOIN students s ON s.id = sp.student_id
        LEFT JOIN professors p ON p.id = s.advisor_id
        WHERE sp.student_id = ?
        ORDER BY sp.submission_date DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
    
    if (!$plan) {
        header('HTTP/1.0 404 Not Found');
        exit('No study plans found');
    }
    
    // Get planned subjects
    $stmt = $conn->prepare("SELECT * FROM planned_subjects WHERE study_plan_id = ?");
    $stmt->bind_param("i", $plan['id']);
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $html = generateStudyPlanHTML($plan, $subjects);
    outputHTMLAsPDF($html, 'study_plan.pdf');
}

function generateClearancePDF() {
    global $conn, $student_id;
    
    // Check if cleared
    $stmt = $conn->prepare("
        SELECT s.*, CONCAT(s.first_name, ' ', s.last_name) as full_name,
               CONCAT(p.first_name, ' ', p.last_name) as adviser_name
        FROM students s
        LEFT JOIN professors p ON p.id = s.advisor_id
        WHERE s.id = ? AND s.advising_cleared = 1
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('HTTP/1.0 403 Forbidden');
        exit('Student not cleared for enrollment');
    }
    
    $student = $result->fetch_assoc();
    
    $html = generateClearanceHTML($student);
    outputHTMLAsPDF($html, 'clearance_form.pdf');
}

function generateGPAPDF() {
    global $conn, $student_id;
    
    // Get student info
    $stmt = $conn->prepare("
        SELECT CONCAT(first_name, ' ', last_name) as full_name, id_number, program
        FROM students WHERE id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    
    // Get GPA records
    $stmt = $conn->prepare("
        SELECT * FROM term_gpa_summary 
        WHERE student_id = ? 
        ORDER BY academic_year, term
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $gpa_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $html = generateGPAHTML($student, $gpa_records);
    outputHTMLAsPDF($html, 'gpa_summary.pdf');
}

function generateBookletHTML($student, $records) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #333; padding-bottom: 20px; }
            .header h1 { color: #1976D2; margin: 0; }
            .header h2 { color: #666; margin: 10px 0; font-size: 18px; }
            .student-info { background: #f5f5f5; padding: 20px; margin-bottom: 30px; border-radius: 5px; }
            .student-info p { margin: 8px 0; }
            .student-info strong { color: #1976D2; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
            th { background: #1976D2; color: white; padding: 12px; text-align: left; font-size: 14px; }
            td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 13px; }
            tr:hover { background: #f9f9f9; }
            .term-header { background: #e3f2fd; font-weight: bold; font-size: 15px; }
            .passed { color: #28a745; }
            .failed { color: #dc3545; font-weight: bold; }
            .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>ACADEMIC ADVISING BOOKLET</h1>
            <h2>De La Salle University</h2>
            <h2>Department of Electronics, Computer, and Electrical Engineering</h2>
        </div>
        
        <div class="student-info">
            <p><strong>Name:</strong> ' . htmlspecialchars($student['full_name']) . '</p>
            <p><strong>ID Number:</strong> ' . htmlspecialchars($student['id_number']) . '</p>
            <p><strong>Program:</strong> ' . htmlspecialchars($student['program']) . '</p>
            <p><strong>Adviser:</strong> ' . htmlspecialchars($student['adviser_name'] ?? 'Not Assigned') . '</p>
            <p><strong>Failed Units:</strong> ' . htmlspecialchars($student['accumulated_failed_units']) . '</p>
        </div>
        
        <h3 style="color: #1976D2; margin-bottom: 15px;">Course Records</h3>
        <table>';
    
    $html .= '
        <thead>
            <tr>
                <th>Course Code</th>
                <th>Course Name</th>
                <th>Units</th>
                <th>Grade</th>
                <th>Status</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>';
    
    $current_year = '';
    $current_term = '';
    
    foreach ($records as $record) {
        if ($current_year != $record['academic_year'] || $current_term != $record['term']) {
            $current_year = $record['academic_year'];
            $current_term = $record['term'];
            $html .= '<tr class="term-header"><td colspan="6">
                      ' . htmlspecialchars($current_year) . ' - Term ' . htmlspecialchars($current_term) . '
                      </td></tr>';
        }
        
        $status_class = $record['is_failed'] ? 'failed' : 'passed';
        $status_text = $record['is_failed'] ? 'Failed' : 'Passed';
        
        $html .= '<tr>
            <td>' . htmlspecialchars($record['course_code']) . '</td>
            <td>' . htmlspecialchars($record['course_name']) . '</td>
            <td>' . htmlspecialchars($record['units']) . '</td>
            <td>' . htmlspecialchars($record['grade'] ?? 'N/A') . '</td>
            <td class="' . $status_class . '">' . $status_text . '</td>
            <td>' . htmlspecialchars($record['remarks'] ?? '-') . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>
        
        <div class="footer">
            <p>Generated on ' . date('F d, Y') . '</p>
            <p>This is an official document from the Academic Advising System</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

function generateStudyPlanHTML($plan, $subjects) {
    $total_units = array_sum(array_column($subjects, 'units'));
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #333; padding-bottom: 20px; }
            .header h1 { color: #1976D2; margin: 0; }
            .info-section { background: #f5f5f5; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
            .info-section p { margin: 8px 0; }
            .info-section strong { color: #1976D2; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #1976D2; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            .total-row { background: #e3f2fd; font-weight: bold; }
            .status-box { padding: 15px; margin: 20px 0; border-left: 4px solid; }
            .status-cleared { border-color: #28a745; background: #d4edda; }
            .status-pending { border-color: #ffc107; background: #fff3cd; }
            .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>STUDY PLAN</h1>
            <h2>' . htmlspecialchars($plan['academic_year']) . ' - ' . htmlspecialchars($plan['term']) . '</h2>
        </div>
        
        <div class="info-section">
            <p><strong>Student Name:</strong> ' . htmlspecialchars($plan['student_name']) . '</p>
            <p><strong>ID Number:</strong> ' . htmlspecialchars($plan['id_number']) . '</p>
            <p><strong>Program:</strong> ' . htmlspecialchars($plan['program']) . '</p>
            <p><strong>Adviser:</strong> ' . htmlspecialchars($plan['adviser_name'] ?? 'Not Assigned') . '</p>
            <p><strong>Submission Date:</strong> ' . date('F d, Y', strtotime($plan['submission_date'])) . '</p>
        </div>
        
        <h3 style="color: #1976D2;">Planned Subjects</h3>
        <table>
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Units</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($subjects as $subject) {
        $html .= '<tr>
            <td>' . htmlspecialchars($subject['subject_code']) . '</td>
            <td>' . htmlspecialchars($subject['subject_name']) . '</td>
            <td>' . htmlspecialchars($subject['units']) . '</td>
        </tr>';
    }
    
    $html .= '<tr class="total-row">
                <td colspan="2"><strong>Total Units</strong></td>
                <td><strong>' . $total_units . '</strong></td>
              </tr>
            </tbody>
        </table>';
    
    if ($plan['cleared']) {
        $html .= '<div class="status-box status-cleared">
            <strong>✓ CLEARED FOR ENROLLMENT</strong><br>
            This study plan has been approved by your adviser.
        </div>';
    } else {
        $html .= '<div class="status-box status-pending">
            <strong>⏳ PENDING REVIEW</strong><br>
            This study plan is awaiting adviser approval.
        </div>';
    }
    
    if ($plan['adviser_feedback']) {
        $html .= '<div style="margin: 20px 0; padding: 15px; background: #e3f2fd; border-radius: 5px;">
            <strong>Adviser Feedback:</strong><br>
            ' . nl2br(htmlspecialchars($plan['adviser_feedback'])) . '
        </div>';
    }
    
    $html .= '
        <div class="footer">
            <p>Generated on ' . date('F d, Y') . '</p>
            <p>This is an official document from the Academic Advising System</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

function generateClearanceHTML($student) {
    $clearance_date = date('F d, Y');
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 60px; }
            .certificate { border: 10px solid #1976D2; padding: 40px; }
            .header { text-align: center; margin-bottom: 40px; }
            .header h1 { color: #1976D2; font-size: 36px; margin: 0; }
            .header h2 { color: #666; font-size: 20px; margin: 10px 0; }
            .content { text-align: center; margin: 40px 0; font-size: 18px; line-height: 2; }
            .student-name { font-size: 28px; color: #1976D2; font-weight: bold; margin: 20px 0; }
            .info-box { background: #f5f5f5; padding: 20px; margin: 30px 0; text-align: left; }
            .info-box p { margin: 10px 0; }
            .signature-section { margin-top: 60px; display: flex; justify-content: space-around; }
            .signature-box { text-align: center; }
            .signature-line { border-top: 2px solid #333; width: 200px; margin: 40px auto 10px; }
            .seal { text-align: center; margin: 40px 0; }
            .seal-image { width: 150px; height: 150px; border: 3px solid #1976D2; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 48px; }
        </style>
    </head>
    <body>
        <div class="certificate">
            <div class="header">
                <h1>CERTIFICATE OF CLEARANCE</h1>
                <h2>Academic Advising</h2>
                <h2>De La Salle University</h2>
            </div>
            
            <div class="content">
                <p>This is to certify that</p>
                <div class="student-name">' . htmlspecialchars($student['full_name']) . '</div>
                <p>has been cleared for enrollment for the upcoming term.</p>
            </div>
            
            <div class="info-box">
                <p><strong>ID Number:</strong> ' . htmlspecialchars($student['id_number']) . '</p>
                <p><strong>Program:</strong> ' . htmlspecialchars($student['program']) . '</p>
                <p><strong>Department:</strong> ' . htmlspecialchars($student['department']) . '</p>
                <p><strong>Clearance Date:</strong> ' . $clearance_date . '</p>
            </div>
            
            <div class="seal">
                <div class="seal-image">✓</div>
                <p style="color: #28a745; font-weight: bold; margin-top: 10px;">APPROVED</p>
            </div>
            
            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <p><strong>' . htmlspecialchars($student['adviser_name'] ?? 'Academic Adviser') . '</strong></p>
                    <p style="font-size: 14px; color: #666;">Academic Adviser</p>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd; color: #666; font-size: 12px;">
                <p>This is an electronically generated certificate.</p>
                <p>Generated on ' . date('F d, Y \a\t g:i A') . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

function generateGPAHTML($student, $gpa_records) {
    $latest_cgpa = !empty($gpa_records) ? end($gpa_records)['cgpa'] : 'N/A';
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #333; padding-bottom: 20px; }
            .header h1 { color: #1976D2; margin: 0; }
            .info-section { background: #f5f5f5; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
            .info-section p { margin: 8px 0; }
            .summary-box { background: #e3f2fd; padding: 20px; margin: 20px 0; text-align: center; border-radius: 5px; }
            .summary-box h2 { color: #1976D2; margin: 0; font-size: 48px; }
            .summary-box p { margin: 5px 0; color: #666; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #1976D2; color: white; padding: 12px; text-align: left; }
            td { padding: 10px; border-bottom: 1px solid #ddd; }
            tr:hover { background: #f9f9f9; }
            .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>GPA SUMMARY REPORT</h1>
            <h2 style="color: #666; font-size: 18px; margin: 10px 0;">Academic Performance Overview</h2>
        </div>
        
        <div class="info-section">
            <p><strong>Name:</strong> ' . htmlspecialchars($student['full_name']) . '</p>
            <p><strong>ID Number:</strong> ' . htmlspecialchars($student['id_number']) . '</p>
            <p><strong>Program:</strong> ' . htmlspecialchars($student['program']) . '</p>
        </div>
        
        <div class="summary-box">
            <p style="font-size: 14px; color: #666;">CURRENT CUMULATIVE GPA</p>
            <h2>' . $latest_cgpa . '</h2>
            <p>out of 4.00</p>
        </div>
        
        <h3 style="color: #1976D2; margin: 30px 0 15px;">Term-by-Term Breakdown</h3>
        <table>
            <thead>
                <tr>
                    <th>Academic Year</th>
                    <th>Term</th>
                    <th>Term GPA</th>
                    <th>CGPA</th>
                    <th>Units Taken</th>
                    <th>Units Passed</th>
                    <th>Units Failed</th>
                    <th>Honors</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($gpa_records as $record) {
        $html .= '<tr>
            <td>' . htmlspecialchars($record['academic_year']) . '</td>
            <td>Term ' . htmlspecialchars($record['term']) . '</td>
            <td>' . htmlspecialchars($record['term_gpa'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($record['cgpa'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($record['total_units_taken']) . '</td>
            <td>' . htmlspecialchars($record['total_units_passed']) . '</td>
            <td>' . htmlspecialchars($record['total_units_failed']) . '</td>
            <td>' . htmlspecialchars($record['trimestral_honors'] ?? '-') . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
        </table>
        
        <div class="footer">
            <p>Generated on ' . date('F d, Y') . '</p>
            <p>This is an official document from the Academic Advising System</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

function outputHTMLAsPDF($html, $filename) {
    // Set headers for PDF download
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    
    // For now, output as HTML which can be printed to PDF by browser
    // In production, you would use a library like TCPDF, FPDF, or wkhtmltopdf
    echo $html;
    
    // Note: To properly generate PDF, install a library like:
    // - composer require tecnickcom/tcpdf
    // - Or use wkhtmltopdf command line tool
    // - Or use puppeteer/headless chrome
}
?>
