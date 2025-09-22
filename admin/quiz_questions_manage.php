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

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$message = '';

// if ($quiz_id == 0) {
//     header("Location: quizzes.php");
//     exit();
// }

// Get quiz details
$quiz_query = $conn->prepare("SELECT q.*, s.name as subject_name, l.title as lesson_title FROM quizzes q LEFT JOIN subjects s ON q.subject_id = s.id LEFT JOIN lessons l ON q.lesson_id = l.id WHERE q.id = ?");
$quiz_query->bind_param("i", $quiz_id);
$quiz_query->execute();
$quiz = $quiz_query->get_result()->fetch_assoc();

if (!$quiz) {
    header("Location: quizzes.php");
    exit();
}

// Handle question actions
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'add_question') {
        $question_text = trim($_POST['question_text']);
        $option_a = trim($_POST['option_a']);
        $option_b = trim($_POST['option_b']);
        $option_c = trim($_POST['option_c']);
        $option_d = trim($_POST['option_d']);
        $correct_answer = $_POST['correct_answer'];
        $explanation = trim($_POST['explanation']);
        $points = intval($_POST['points']);
        
        if (!empty($question_text) && !empty($option_a) && !empty($option_b)) {
            $next_order = $conn->query("SELECT COALESCE(MAX(question_order), 0) + 1 as next_order FROM quiz_questions WHERE quiz_id = $quiz_id")->fetch_assoc()['next_order'];
            
            $stmt = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation, points, question_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isssssssii", $quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $explanation, $points, $next_order);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Question added successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Error adding question!</div>';
            }
        } else {
            $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Question text and at least options A & B are required!</div>';
        }
    }
    
    if ($_POST['action'] == 'delete_question') {
        $question_id = intval($_POST['question_id']);
        $conn->query("DELETE FROM quiz_questions WHERE id = $question_id AND quiz_id = $quiz_id");
        $message = '<div class="alert alert-info"><i class="fas fa-trash me-2"></i>Question deleted successfully!</div>';
    }
    
    if ($_POST['action'] == 'update_question') {
        $question_id = intval($_POST['question_id']);
        $question_text = trim($_POST['question_text']);
        $option_a = trim($_POST['option_a']);
        $option_b = trim($_POST['option_b']);
        $option_c = trim($_POST['option_c']);
        $option_d = trim($_POST['option_d']);
        $correct_answer = $_POST['correct_answer'];
        $explanation = trim($_POST['explanation']);
        $points = intval($_POST['points']);
        
        $stmt = $conn->prepare("UPDATE quiz_questions SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?, explanation = ?, points = ? WHERE id = ? AND quiz_id = ?");
        $stmt->bind_param("sssssssiil", $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $explanation, $points, $question_id, $quiz_id);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Question updated successfully!</div>';
        }
    }
}

// Get all questions for this quiz
$questions = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id = $quiz_id ORDER BY question_order ASC, created_at ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quiz Questions - <?php echo htmlspecialchars($quiz['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); }
        .question-card { margin-bottom: 20px; border-left: 4px solid #007bff; }
        .correct-option { background-color: #d4edda; font-weight: bold; }
        .option-label { font-weight: bold; color: #007bff; }
        .step-indicator { display: flex; justify-content: center; margin-bottom: 30px; }
        .step { flex: 1; text-align: center; padding: 15px; border-bottom: 3px solid #e9ecef; }
        .step.completed { border-bottom-color: #28a745; color: #28a745; }
        .step.active { border-bottom-color: #007bff; color: #007bff; }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2"><i class="fas fa-question-circle me-2 text-primary"></i>Manage Quiz Questions</h1>
                <p class="text-muted">
                    <strong>Quiz:</strong> <?php echo htmlspecialchars($quiz['title']); ?> | 
                    <strong>Subject:</strong> <?php echo htmlspecialchars($quiz['subject_name']); ?>
                    <?php if ($quiz['lesson_title']): ?>
                    | <strong>Lesson:</strong> <?php echo htmlspecialchars($quiz['lesson_title']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <a href="quiz_preview.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-success me-2">
                    <i class="fas fa-eye me-2"></i>Preview Quiz
                </a>
                <a href="quizzes.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Quizzes
                </a>
            </div>
        </div>
        
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step completed">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <div><strong>Step 1</strong></div>
                <div>Quiz Created</div>
            </div>
            <div class="step active">
                <i class="fas fa-question fa-2x mb-2"></i>
                <div><strong>Step 2</strong></div>
                <div>Add Questions</div>
            </div>
            <div class="step">
                <i class="fas fa-eye fa-2x mb-2"></i>
                <div><strong>Step 3</strong></div>
                <div>Preview & Publish</div>
            </div>
        </div>
        
        <?php echo $message; ?>
        
        <!-- Quiz Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center">
                        <i class="fas fa-list fa-2x mb-2"></i>
                        <h4><?php echo $questions->num_rows; ?></h4>
                        <small>Total Questions</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-2x mb-2"></i>
                        <h4><?php echo $quiz['time_limit']; ?> min</h4>
                        <small>Time Limit</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body text-center">
                        <i class="fas fa-percentage fa-2x mb-2"></i>
                        <h4><?php echo $quiz['passing_score']; ?>%</h4>
                        <small>Passing Score</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body text-center">
                        <i class="fas fa-star fa-2x mb-2"></i>
                        <h4><?php echo $conn->query("SELECT SUM(points) as total FROM quiz_questions WHERE quiz_id = $quiz_id")->fetch_assoc()['total'] ?? 0; ?></h4>
                        <small>Total Points</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Add Question Form -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add New Question</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="addQuestionForm">
                            <input type="hidden" name="action" value="add_question">
                            
                            <div class="mb-3">
                                <label for="question_text" class="form-label">Question *</label>
                                <textarea class="form-control" id="question_text" name="question_text" rows="3" required placeholder="Enter your question here..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Answer Options *</label>
                                
                                <div class="mb-2">
                                    <div class="input-group">
                                        <span class="input-group-text option-label">A)</span>
                                        <input type="text" class="form-control" name="option_a" required placeholder="Option A">
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <div class="input-group">
                                        <span class="input-group-text option-label">B)</span>
                                        <input type="text" class="form-control" name="option_b" required placeholder="Option B">
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <div class="input-group">
                                        <span class="input-group-text option-label">C)</span>
                                        <input type="text" class="form-control" name="option_c" placeholder="Option C (optional)">
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <div class="input-group">
                                        <span class="input-group-text option-label">D)</span>
                                        <input type="text" class="form-control" name="option_d" placeholder="Option D (optional)">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="correct_answer" class="form-label">Correct Answer *</label>
                                <select class="form-select" id="correct_answer" name="correct_answer" required>
                                    <option value="">Select correct answer</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="explanation" class="form-label">Explanation (Optional)</label>
                                <textarea class="form-control" id="explanation" name="explanation" rows="2" placeholder="Explain why this is the correct answer..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="points" class="form-label">Points</label>
                                <input type="number" class="form-control" id="points" name="points" value="1" min="1" max="10">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Add Question
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Questions List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Quiz Questions (<?php echo $questions->num_rows; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($questions->num_rows > 0): ?>
                            <?php $question_number = 1; ?>
                            <?php while ($question = $questions->fetch_assoc()): ?>
                            <div class="card question-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h6 class="text-primary">Question <?php echo $question_number; ?> (<?php echo $question['points']; ?> point<?php echo $question['points'] != 1 ? 's' : ''; ?>)</h6>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $question['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_question">
                                                <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Delete this question?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <strong><?php echo htmlspecialchars($question['question_text']); ?></strong>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-1 p-2 rounded <?php echo $question['correct_answer'] == 'A' ? 'correct-option' : ''; ?>">
                                                <strong>A)</strong> <?php echo htmlspecialchars($question['option_a']); ?>
                                                <?php if ($question['correct_answer'] == 'A'): ?><i class="fas fa-check-circle text-success ms-2"></i><?php endif; ?>
                                            </div>
                                            <div class="mb-1 p-2 rounded <?php echo $question['correct_answer'] == 'B' ? 'correct-option' : ''; ?>">
                                                <strong>B)</strong> <?php echo htmlspecialchars($question['option_b']); ?>
                                                <?php if ($question['correct_answer'] == 'B'): ?><i class="fas fa-check-circle text-success ms-2"></i><?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <?php if (!empty($question['option_c'])): ?>
                                            <div class="mb-1 p-2 rounded <?php echo $question['correct_answer'] == 'C' ? 'correct-option' : ''; ?>">
                                                <strong>C)</strong> <?php echo htmlspecialchars($question['option_c']); ?>
                                                <?php if ($question['correct_answer'] == 'C'): ?><i class="fas fa-check-circle text-success ms-2"></i><?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($question['option_d'])): ?>
                                            <div class="mb-1 p-2 rounded <?php echo $question['correct_answer'] == 'D' ? 'correct-option' : ''; ?>">
                                                <strong>D)</strong> <?php echo htmlspecialchars($question['option_d']); ?>
                                                <?php if ($question['correct_answer'] == 'D'): ?><i class="fas fa-check-circle text-success ms-2"></i><?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($question['explanation'])): ?>
                                    <div class="mt-3 p-2 bg-light rounded">
                                        <small><strong>Explanation:</strong> <?php echo htmlspecialchars($question['explanation']); ?></small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Edit Question Modal -->
                            <div class="modal fade" id="editModal<?php echo $question['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Question <?php echo $question_number; ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="update_question">
                                                <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Question *</label>
                                                    <textarea class="form-control" name="question_text" rows="3" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Option A *</label>
                                                            <input type="text" class="form-control" name="option_a" value="<?php echo htmlspecialchars($question['option_a']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Option C</label>
                                                            <input type="text" class="form-control" name="option_c" value="<?php echo htmlspecialchars($question['option_c']); ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Option B *</label>
                                                            <input type="text" class="form-control" name="option_b" value="<?php echo htmlspecialchars($question['option_b']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Option D</label>
                                                            <input type="text" class="form-control" name="option_d" value="<?php echo htmlspecialchars($question['option_d']); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Correct Answer *</label>
                                                            <select class="form-select" name="correct_answer" required>
                                                                <option value="A" <?php echo $question['correct_answer'] == 'A' ? 'selected' : ''; ?>>A</option>
                                                                <option value="B" <?php echo $question['correct_answer'] == 'B' ? 'selected' : ''; ?>>B</option>
                                                                <option value="C" <?php echo $question['correct_answer'] == 'C' ? 'selected' : ''; ?>>C</option>
                                                                <option value="D" <?php echo $question['correct_answer'] == 'D' ? 'selected' : ''; ?>>D</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Points</label>
                                                            <input type="number" class="form-control" name="points" value="<?php echo $question['points']; ?>" min="1" max="10">
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Explanation</label>
                                                    <textarea class="form-control" name="explanation" rows="2"><?php echo htmlspecialchars($question['explanation']); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Update Question</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <?php $question_number++; ?>
                            <?php endwhile; ?>
                            
                            <!-- Action Buttons -->
                            <div class="text-center mt-4">
                                <?php if ($questions->num_rows >= 5): ?>
                                <a href="quiz_preview.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-success btn-lg me-2">
                                    <i class="fas fa-eye me-2"></i>Preview Quiz
                                </a>
                                <button class="btn btn-primary btn-lg" onclick="publishQuiz()">
                                    <i class="fas fa-paper-plane me-2"></i>Publish Quiz
                                </button>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Add at least 5 questions before you can preview or publish the quiz.
                                    <strong>Current: <?php echo $questions->num_rows; ?>/5</strong>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                                <h5>No Questions Added Yet</h5>
                                <p class="text-muted">Start by adding your first MCQ question using the form on the left.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function publishQuiz() {
            if (confirm('Publish this quiz? Students will be able to take it once published.')) {
                // Update quiz status to published
                fetch('update_quiz_status.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'quiz_id=<?php echo $quiz_id; ?>&status=active'
                })
                .then(() => {
                    alert('Quiz published successfully!');
                    window.location.href = 'quizzes.php';
                });
            }
        }
        
        // Auto-clear form after successful submission
        document.getElementById('addQuestionForm').addEventListener('submit', function(e) {
            setTimeout(() => {
                this.reset();
            }, 100);
        });
    </script>
</body>
</html>
