<?php
session_start();

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    switch ($_SESSION['user_type']) {
        case 'admin':
            header('Location: admin_dashboard.php');
            exit();
        case 'professor':
            header('Location: prof_dashboard.php');
            exit();
        case 'student':
            header('Location: student_dashboard.php');
            exit();
        default:
            // Invalid user type, clear session and show login
            session_destroy();
            header('Location: login.php');
            exit();
    }
} else {
    // Not logged in, show login page
    header('Location: login.php');
    exit();
}
?>