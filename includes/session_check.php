<?php
include_once 'config.php';


function checkSessionAndRedirect() {
    $session_timeout = 30 * 60; 
    
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
    
    if (isset($_SESSION['login_time'])) {
        if ((time() - $_SESSION['login_time']) > $session_timeout) {
       
            updateOnlineStatus($_SESSION['user_id'], 'offline');
            
         
            session_unset();
            session_destroy();
            
            
            header("Location: ../login.php?timeout=1");
            exit();
        }

        $_SESSION['login_time'] = time();
    }
}

// Auto-call the function
checkSessionAndRedirect();
?>
