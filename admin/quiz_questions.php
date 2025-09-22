<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'learning_platform';

$conn = new mysqli($host, $username, $password, $database);

$message = '';
$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($quiz_id == 0) {
    header("Location: quizzes.php");
    exit();
}

// Get quiz data
$quiz_query = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
$quiz_query->bind_param("i", $quiz_id);
$quiz_query->execute();
$quiz_result = $quiz_query->get_result();

if ($quiz_result->num_rows == 0) {
    header("Location: quizzes.php");
    exit();
}

$quiz = $quiz_result->fetch_assoc();

// Handle question actions
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $question = trim($_POST['question']);
        $option_a = trim($_POST['option_a']);
        $option_b = trim($_POST['option_b']);
        $option_c = trim($_POST['option_c']);
        $option_d = trim($_POST['option_d']);
        $correct_answer = $_POST['correct_answer'];
        $points = intval($_POST['points']);
        
        if (!empty($question) && !empty($option_a) && !empty($option_b)) {
            $stmt = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_answer, points, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issssssi", $quiz_id, $question, $option_a, $option_b, $option_c, $option_d, $correct_answer, $points);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Question added successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Failed to add question!</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Question and at least 2 options are required!</div>';
        }
    }
    
    if ($_POST['action'] == 'delete') {
        $question_id = intval($_POST['question_id']);
        $conn->query("DELETE FROM quiz_questions WHERE id = $question_id AND quiz_id = $quiz_id");
        $message = '<div class="alert alert-success">Question deleted successfully!</div>';
    }
}

// Get questions
$questions = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id = $quiz_id ORDER BY created_at ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Questions - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 15px 20px;
            margin: 5px 10px;
            border-radius: 8px;
        }
        .sidebar .nav-link:hover { background: rgba(255, 255, 255, 0.1); color: white; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); }
        .question-card { margin-bottom: 15px; }
        .correct-answer { background-color: #d4edda; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center py-4">
                        <h4 class="text-white">Admin Panel</h4>
                        <p class="text-white-50">Welcome, <?php echo $_SESSION['name']; ?></p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="users.php"><i class="fas fa-users me-2"></i> Users</a></li>
                        <li class="nav-item"><a class="nav-link" href="quizzes.php"><i class="fas fa-question-circle me-2"></i> Quizzes</a></li>
                        <li class="nav-item"><a class="nav-link" href="lessons.php"><i class="fas fa-book me-2"></i> Lessons</a></li>
                        <li class="nav-item"><a class="nav-link" href="meetings.php"><i class="fas fa-video me-2"></i> Meetings</a></li>
                        <li class="nav-item"><a class="nav-link" href="chatroom.php"><i class="fas fa-comments me-2"></i> Chat Room</a></li>
                        <li class="nav-item"><a class="nav-link" href="leaderboard.php"><i class="fas fa-trophy me-2"></i> Leaderboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <h1 class="h2"><i class="fas fa-list me-2"></i>Quiz Questions</h1>
                    <a href="quizzes.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Quizzes
                    </a>
                </div>
                
                <div class="alert alert-info">
                    <strong>Quiz:</strong> <?php echo htmlspecialchars($quiz['title']); ?> | 
                    <strong>Questions:</strong> <?php echo $questions->num_rows; ?>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Add Question Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-plus me-2"></i>Add New Question</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="question" class="form-label">Question</label>
                                <textarea class="form-control" id="question" name="question" rows="3" required></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="option_a" class="form-label">Option A</label>
                                        <input type="text" class="form-control" id="option_a" name="option_a" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="option_b" class="form-label">Option B</label>
                                        <input type="text" class="form-control" id="option_b" name="option_b" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="option_c" class="form-label">Option C</label>
                                        <input type="text" class="form-control" id="option_c" name="option_c">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="option_d" class="form-label">Option D</label>
                                        <input type="text" class="form-control" id="option_d" name="option_d">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="correct_answer" class="form-label">Correct Answer</label>
                                        <select class="form-control" id="correct_answer" name="correct_answer" required>
                                            <option value="A">Option A</option>
                                            <option value="B">Option B</option>
                                            <option value="C">Option C</option>
                                            <option value="D">Option D</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="points" class="form-label">Points</label>
                                        <input type="number" class="form-control" id="points" name="points" value="1" min="1" max="10">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Add Question
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Questions List -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Quiz Questions (<?php echo $questions->num_rows; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($questions->num_rows > 0): ?>
                            <?php $question_number = 1; ?>
                            <?php while ($question = $questions->fetch_assoc()): ?>
                            <div class="card question-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <h6>Question <?php echo $question_number; ?> (<?php echo $question['points']; ?> points)</h6>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this question?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <p class="mt-2"><strong><?php echo htmlspecialchars($question['question']); ?></strong></p>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1 <?php echo $question['correct_answer'] == 'A' ? 'correct-answer' : ''; ?>">
                                                A) <?php echo htmlspecialchars($question['option_a']); ?>
                                            </p>
                                            <p class="mb-1 <?php echo $question['correct_answer'] == 'B' ? 'correct-answer' : ''; ?>">
                                                B) <?php echo htmlspecialchars($question['option_b']); ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <?php if (!empty($question['option_c'])): ?>
                                            <p class="mb-1 <?php echo $question['correct_answer'] == 'C' ? 'correct-answer' : ''; ?>">
                                                C) <?php echo htmlspecialchars($question['option_c']); ?>
                                            </p>
                                            <?php endif; ?>
                                            <?php if (!empty($question['option_d'])): ?>
                                            <p class="mb-1 <?php echo $question['correct_answer'] == 'D' ? 'correct-answer' : ''; ?>">
                                                D) <?php echo htmlspecialchars($question['option_d']); ?>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php $question_number++; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No questions added yet. Add your first question above.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
