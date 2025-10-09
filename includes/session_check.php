<?php
include_once 'config.php';


function checkSessionAndRedirect() {
    $session_timeout = 30 * 60; // 30 minutes
    
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
    
    if (isset($_SESSION['login_time'])) {
        if ((time() - $_SESSION['login_time']) > $session_timeout) {
            // Update user status to offline
            updateOnlineStatus($_SESSION['user_id'], 'offline');
            
            // Clear session
            session_unset();
            session_destroy();
            
            // Redirect with timeout message
            header("Location: ../login.php?timeout=1");
            exit();
        }
        // Update last activity time
        $_SESSION['login_time'] = time();
    }
}

// Auto-call the function
checkSessionAndRedirect();
?>
