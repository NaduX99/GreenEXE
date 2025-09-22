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
$selected_subject = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$selected_lesson = isset($_GET['lesson']) ? intval($_GET['lesson']) : 0;

// Handle quiz creation
if (isset($_POST['create_quiz'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $subject_id = intval($_POST['subject_id']);
    $lesson_id = !empty($_POST['lesson_id']) ? intval($_POST['lesson_id']) : NULL;
    $time_limit = intval($_POST['time_limit']);
    $passing_score = intval($_POST['passing_score']);
    
    if (!empty($title) && $subject_id > 0) {
        $stmt = $conn->prepare("INSERT INTO quizzes (title, description, subject_id, lesson_id, time_limit, passing_score, created_by, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
        $stmt->bind_param("ssiiiis", $title, $description, $subject_id, $lesson_id, $time_limit, $passing_score, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $quiz_id = $conn->insert_id;
            header("Location: quiz_questions_manage.php?quiz_id=$quiz_id");
            exit();
        } else {
            $message = '<div class="alert alert-danger">Error creating quiz: ' . $conn->error . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning">Title and subject are required!</div>';
    }
}

// Get subjects and lessons
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name ASC");
$lessons = $conn->query("SELECT l.*, s.name as subject_name FROM lessons l LEFT JOIN subjects s ON l.subject_id = s.id ORDER BY s.name ASC, l.title ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create MCQ Quiz - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { background: rgba(255, 255, 255, 0.95); min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); }
        .step-indicator { display: flex; justify-content: center; margin-bottom: 30px; }
        .step { flex: 1; text-align: center; padding: 15px; border-bottom: 3px solid #e9ecef; position: relative; }
        .step.active { border-bottom-color: #007bff; color: #007bff; }
        .step.completed { border-bottom-color: #28a745; color: #28a745; }
    </style>
</head>
<body>
    <div class="container p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2"><i class="fas fa-plus-circle me-2 text-primary"></i>Create Custom MCQ Quiz</h1>
                <p class="text-muted">Build your own multiple choice quiz with 4 options per question</p>
            </div>
            <a href="quizzes.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Quizzes
            </a>
        </div>
        
        <?php echo $message; ?>
        
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active">
                <i class="fas fa-info-circle fa-2x mb-2"></i>
                <div><strong>Step 1</strong></div>
                <div>Quiz Setup</div>
            </div>
            <div class="step">
                <i class="fas fa-question fa-2x mb-2"></i>
                <div><strong>Step 2</strong></div>
                <div>Add Questions</div>
            </div>
            <div class="step">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <div><strong>Step 3</strong></div>
                <div>Review & Publish</div>
            </div>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Quiz Configuration</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <!-- Subject & Lesson Selection -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="subject_id" class="form-label"><i class="fas fa-graduation-cap me-2"></i>Subject *</label>
                                    <select class="form-select" id="subject_id" name="subject_id" required onchange="filterLessons()">
                                        <option value="">Choose Subject</option>
                                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                                        <option value="<?php echo $subject['id']; ?>" <?php echo $selected_subject == $subject['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['name']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="lesson_id" class="form-label"><i class="fas fa-book me-2"></i>Lesson (Optional)</label>
                                    <select class="form-select" id="lesson_id" name="lesson_id">
                                        <option value="">No specific lesson</option>
                                        <?php while ($lesson = $lessons->fetch_assoc()): ?>
                                        <option value="<?php echo $lesson['id']; ?>" data-subject="<?php echo $lesson['subject_id']; ?>" <?php echo $selected_lesson == $lesson['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lesson['subject_name'] . ' - ' . $lesson['title']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Quiz Details -->
                            <div class="mb-4">
                                <label for="title" class="form-label"><i class="fas fa-heading me-2"></i>Quiz Title *</label>
                                <input type="text" class="form-control form-control-lg" id="title" name="title" 
                                       placeholder="e.g., Mathematics - Algebra MCQ Test" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="description" class="form-label"><i class="fas fa-align-left me-2"></i>Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" 
                                          placeholder="Describe what this quiz covers..."></textarea>
                            </div>
                            
                            <!-- Quiz Settings -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="time_limit" class="form-label"><i class="fas fa-clock me-2"></i>Time Limit (minutes)</label>
                                    <input type="number" class="form-control" id="time_limit" name="time_limit" 
                                           value="30" min="5" max="180">
                                    <small class="text-muted">Students will have this much time to complete the quiz</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="passing_score" class="form-label"><i class="fas fa-percentage me-2"></i>Passing Score (%)</label>
                                    <input type="number" class="form-control" id="passing_score" name="passing_score" 
                                           value="70" min="1" max="100">
                                    <small class="text-muted">Minimum score required to pass</small>
                                </div>
                            </div>
                            
                            <!-- Feature Highlights -->
                            <div class="alert alert-info">
                                <h6><i class="fas fa-star me-2"></i>Quiz Features:</h6>
                                <ul class="mb-0">
                                    <li>✅ Multiple Choice Questions (A, B, C, D options)</li>
                                    <li>✅ Timer functionality with auto-submit</li>
                                    <li>✅ Instant scoring and feedback</li>
                                    <li>✅ Question explanations support</li>
                                    <li>✅ Subject and lesson organization</li>
                                </ul>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" name="create_quiz" class="btn btn-primary btn-lg">
                                    <i class="fas fa-arrow-right me-2"></i>Create Quiz & Add Questions
                                </button>
                                <a href="quizzes.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterLessons() {
            const subjectId = document.getElementById('subject_id').value;
            const lessonSelect = document.getElementById('lesson_id');
            const allOptions = lessonSelect.querySelectorAll('option[data-subject]');
            
            // Reset lesson selection
            lessonSelect.value = '';
            
            // Show/hide lessons based on selected subject
            allOptions.forEach(option => {
                if (option.dataset.subject == subjectId || subjectId === '') {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            filterLessons();
        });
    </script>
</body>
</html>
