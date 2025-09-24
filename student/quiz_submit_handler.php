<?php
header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$student_id = $_SESSION['user_id'];
$quiz_id = intval($input['quiz_id']);
$answers = $input['answers'] ?? [];
$time_taken = intval($input['time_taken'] ?? 0);

// Get correct answers
$correct_answers_query = "SELECT id, correct_answer FROM quiz_questions WHERE quiz_id = $quiz_id";
$correct_result = $conn->query($correct_answers_query);

$correct_answers_map = [];
while ($row = $correct_result->fetch_assoc()) {
    $correct_answers_map[$row['id']] = $row['correct_answer'];
}

// Calculate score
$correct_count = 0;
$total_questions = count($correct_answers_map);

foreach ($correct_answers_map as $question_id => $correct_answer) {
    $user_answer = $answers[$question_id] ?? '';
    if ($user_answer === $correct_answer) {
        $correct_count++;
    }
}

$score = $total_questions > 0 ? ($correct_count / $total_questions) * 100 : 0;

// Get quiz title
$quiz_title = 'Quiz';
$quiz_result = $conn->query("SELECT title FROM quizzes WHERE id = $quiz_id");
if ($quiz_result && $quiz_result->num_rows > 0) {
    $quiz = $quiz_result->fetch_assoc();
    $quiz_title = $quiz['title'];
}

// Prepare result
$result = [
    'quiz_title' => $quiz_title,
    'quiz_id' => $quiz_id,
    'score' => $score,
    'correct_answers' => $correct_count,
    'total_questions' => $total_questions,
    'time_taken' => $time_taken
];

// Try to save to database (optional)
$save_query = "INSERT INTO quiz_attempts (student_id, quiz_id, score, total_questions, correct_answers, time_taken) VALUES ($student_id, $quiz_id, $score, $total_questions, $correct_count, $time_taken)";
$conn->query($save_query); // Don't check for errors

// Store in session as backup
$_SESSION['quiz_result'] = $result;

// Return success
echo json_encode([
    'success' => true,
    'message' => 'Quiz submitted successfully',
    'result' => $result
]);
?>
