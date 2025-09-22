<?php
session_start();

// Update user status to offline if logged in
if (isset($_SESSION['user_id'])) {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'learning_platform';
    
    $conn = new mysqli($host, $username, $password, $database);
    
    if (!$conn->connect_error) {
        $user_id = $_SESSION['user_id'];
        $conn->query("UPDATE users SET status = 'offline' WHERE id = $user_id");
    }
}

// Destroy session
session_destroy();

// Redirect to login
header("Location: login.php?logout=success");
exit();
?>
