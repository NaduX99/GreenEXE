<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'learning_platform');
$student_id = $_SESSION['user_id'];

if (isset($_POST['lesson_id']) && isset($_POST['progress'])) {
    $lesson_id = intval($_POST['lesson_id']);
    $progress = intval($_POST['progress']);
    
    // Update progress
    $conn->query("UPDATE lesson_progress SET progress_percentage = $progress WHERE student_id = $student_id AND lesson_id = $lesson_id");
    
    echo json_encode(['status' => 'success']);
}
?>
