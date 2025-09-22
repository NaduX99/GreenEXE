<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'learning_platform');

$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

if ($subject_id > 0) {
    $lessons = $conn->query("SELECT id, title FROM lessons WHERE subject_id = $subject_id AND status = 'published' ORDER BY lesson_order ASC, title ASC");
    
    $result = [];
    if ($lessons) {
        while ($lesson = $lessons->fetch_assoc()) {
            $result[] = $lesson;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
} else {
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>
