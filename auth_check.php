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
 * Require specific user type - redirect to current page if wrong type
 */
function requireUserType($requiredType) {
    requireAuth();
    
    if ($_SESSION['user_type'] !== $requiredType) {
        // User is logged in but wrong type - redirect back to current page
        // This prevents cross-role access (e.g., student trying to access admin pages)
        $current_page = $_SERVER['PHP_SELF'];
        $redirect_page = '';
        
        switch ($_SESSION['user_type']) {
            case 'admin':
                $redirect_page = 'admin_dashboard.php';
                break;
            case 'professor':
                $redirect_page = 'prof_dashboard.php';
                break;
            case 'student':
                $redirect_page = 'student_dashboard.php';
                break;
            default:
                // Invalid user type - logout and redirect to login
                session_destroy();
                header('Location: login.php');
                exit();
        }
        
        // Redirect to their dashboard (not allowing cross-role access)
        header('Location: ' . $redirect_page);
        exit();
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