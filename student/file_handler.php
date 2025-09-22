<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit('Access denied');
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

// Get parameters
$file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;
$lesson_id = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'preview'; // 'preview' or 'download'

$file_path = '';
$file_name = '';

// Get file info
if ($file_id > 0) {
    // From lesson_files table
    $query = "SELECT * FROM lesson_files WHERE id = $file_id";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $file = $result->fetch_assoc();
        $file_path = $file['file_path'];
        $file_name = $file['original_name'];
    }
} elseif ($lesson_id > 0) {
    // From lessons table (presentation column)
    $query = "SELECT presentation FROM lessons WHERE id = $lesson_id AND presentation IS NOT NULL";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $lesson = $result->fetch_assoc();
        $presentation_path = $lesson['presentation'];
        
        // Handle different path formats
        if (strpos($presentation_path, '../') === 0) {
            $file_path = $presentation_path;
        } elseif (strpos($presentation_path, 'uploads/') === 0) {
            $file_path = '../' . $presentation_path;
        } else {
            $file_path = $presentation_path;
        }
        
        $file_name = basename($presentation_path);
    }
}

// Check if file exists
if (empty($file_path) || !file_exists($file_path)) {
    http_response_code(404);
    exit('File not found: ' . $file_path);
}

// Get file info
$file_size = filesize($file_path);
$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Set appropriate MIME type
$mime_types = [
    'pdf' => 'application/pdf',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'mp4' => 'video/mp4',
    'avi' => 'video/x-msvideo',
    'mov' => 'video/quicktime',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'txt' => 'text/plain',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

$content_type = isset($mime_types[$file_extension]) ? $mime_types[$file_extension] : 'application/octet-stream';

// Set headers
header('Content-Type: ' . $content_type);
header('Content-Length: ' . $file_size);
header('Accept-Ranges: bytes');

if ($action === 'download') {
    // Force download
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Cache-Control: no-cache, must-revalidate');
} else {
    // Preview mode
    header('Content-Disposition: inline; filename="' . $file_name . '"');
    header('Cache-Control: public, max-age=3600');
}

// Handle range requests for video/audio
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    $ranges = explode('=', $range);
    $offsets = explode('-', $ranges[1]);
    $offset = intval($offsets[0]);
    $length = intval($offsets[1]) - $offset;
    
    if (!$length) {
        $length = $file_size - $offset;
    }
    
    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $offset . '-' . ($offset + $length - 1) . '/' . $file_size);
    header('Content-Length: ' . $length);
    
    $file = fopen($file_path, 'r');
    fseek($file, $offset);
    echo fread($file, $length);
    fclose($file);
} else {
    // Output entire file
    readfile($file_path);
}

exit();
?>
