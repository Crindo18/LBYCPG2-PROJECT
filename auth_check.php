<?php
/**
 * Authentication Check Module
 * Include this at the top of protected pages
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['user_type']) && 
           isset($_SESSION['logged_in']) && 
           $_SESSION['logged_in'] === true;
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Require specific user type - redirect if wrong type
 */
function requireUserType($requiredType) {
    requireAuth();
    
    if ($_SESSION['user_type'] !== $requiredType) {
        // User is logged in but wrong type - redirect to their dashboard
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
                // Invalid user type - logout and redirect to login
                session_destroy();
                header('Location: login.php');
                exit();
        }
    }
}

/**
 * Require admin access
 */
function requireAdmin() {
    requireUserType('admin');
}

/**
 * Require professor access
 */
function requireProfessor() {
    requireUserType('professor');
}

/**
 * Require student access
 */
function requireStudent() {
    requireUserType('student');
}
?>