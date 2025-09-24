<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

$student_id = $_SESSION['user_id'];
$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($quiz_id <= 0) {
    header("Location: quizzes.php");
    exit();
}

// Get quiz details
$quiz_query = "SELECT * FROM quizzes WHERE id = $quiz_id AND status = 'active'";
$quiz_result = $conn->query($quiz_query);

if (!$quiz_result || $quiz_result->num_rows == 0) {
    header("Location: quizzes.php");
    exit();
}

$quiz = $quiz_result->fetch_assoc();

// Get questions
$questions = [];
$questions_result = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id = $quiz_id ORDER BY id ASC");
if ($questions_result) {
    while ($question = $questions_result->fetch_assoc()) {
        $questions[] = $question;
    }
}

if (empty($questions)) {
    echo '<div class="alert alert-warning">No questions found for this quiz.</div>';
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quiz['title']); ?> - Quiz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Dashboard CSS Styles with Background Image */
        body { 
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background: url('../1.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            position: relative;
            color: rgba(255, 255, 255, 0.9);
        }

        /* Background overlay for better readability */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        /* Text styles with better readability */
        h1, h2, h3, h4, h5, h6 {
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        p, span, div, small {
            color: rgba(255, 255, 255, 0.9);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        .text-muted {
            color: rgba(255, 255, 255, 0.7) !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        /* Enhanced glassmorphism effects */
        .glass-effect {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        .progress-bar-custom { 
            height: 8px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }

        .badge {
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-shadow: none;
        }

        /* Button styles */
        .btn {
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        /* Card styles */
        .card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            color: rgba(255, 255, 255, 0.9);
        }

        .card-header {
            background: rgba(255, 255, 255, 0.15) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Custom Scrollbar with enhanced styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        /* Quiz specific styles */
        .quiz-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .status-panel {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
        }

        .quiz-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .question-card {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .option {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .option:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(59, 130, 246, 0.5);
        }

        .option input[type="radio"]:checked + span {
            color: #60a5fa;
            font-weight: 600;
        }

        .submit-section {
            background: rgba(34, 197, 94, 0.1);
            border: 2px solid rgba(34, 197, 94, 0.3);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            margin-top: 30px;
            backdrop-filter: blur(10px);
        }

        .submit-btn {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .submit-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            transform: translateY(-2px);
        }

        .submit-btn:disabled {
            background: #6b7280;
            cursor: not-allowed;
        }

        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(59, 130, 246, 0.9);
            color: white;
            padding: 15px 20px;
            border-radius: 25px;
            font-weight: bold;
            z-index: 1000;
            backdrop-filter: blur(10px);
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .quiz-container {
                padding: 15px;
            }
            
            .quiz-header {
                padding: 20px;
            }
            
            .question-card {
                padding: 20px;
            }
            
            .timer {
                top: 10px;
                right: 10px;
                padding: 10px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="quiz-container">
        <a href="quizzes.php" class="btn btn-outline-light mb-3">
            <i class="fas fa-arrow-left me-2"></i>Back to Quizzes
        </a>

        <!-- Timer -->
        <div class="timer" id="timer">
            <i class="fas fa-clock me-2"></i>
            <span id="time-display"><?php echo $quiz['time_limit'] ?? 30; ?>:00</span>
        </div>

        <!-- Status Panel -->
        <div class="status-panel" id="statusPanel">
            <strong>Status:</strong> <span id="statusText">Quiz loaded, ready to start</span>
        </div>

        <!-- Quiz Header -->
        <div class="quiz-header">
            <h1><?php echo htmlspecialchars($quiz['title']); ?></h1>
            <?php if (!empty($quiz['description'])): ?>
            <p class="lead"><?php echo htmlspecialchars($quiz['description']); ?></p>
            <?php endif; ?>
            
            <div class="row mt-3">
                <div class="col-md-4">
                    <h5><?php echo count($questions); ?></h5>
                    <small>Questions</small>
                </div>
                <div class="col-md-4">
                    <h5><?php echo $quiz['time_limit'] ?? 30; ?> min</h5>
                    <small>Time Limit</small>
                </div>
                <div class="col-md-4">
                    <h5 id="progress-count">0</h5>
                    <small>Answered</small>
                </div>
            </div>
        </div>

        <!-- Quiz Questions -->
        <div id="quizQuestions">
            <?php foreach ($questions as $index => $question): ?>
            <div class="question-card">
                <h5 class="mb-3">
                    <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                    <?php echo htmlspecialchars($question['question_text']); ?>
                </h5>

                <div class="options">
                    <label class="option d-block">
                        <input type="radio" name="question_<?php echo $question['id']; ?>" value="A" data-question="<?php echo $question['id']; ?>" class="me-2">
                        <span>A) <?php echo htmlspecialchars($question['option_a']); ?></span>
                    </label>

                    <label class="option d-block">
                        <input type="radio" name="question_<?php echo $question['id']; ?>" value="B" data-question="<?php echo $question['id']; ?>" class="me-2">
                        <span>B) <?php echo htmlspecialchars($question['option_b']); ?></span>
                    </label>

                    <label class="option d-block">
                        <input type="radio" name="question_<?php echo $question['id']; ?>" value="C" data-question="<?php echo $question['id']; ?>" class="me-2">
                        <span>C) <?php echo htmlspecialchars($question['option_c']); ?></span>
                    </label>

                    <label class="option d-block">
                        <input type="radio" name="question_<?php echo $question['id']; ?>" value="D" data-question="<?php echo $question['id']; ?>" class="me-2">
                        <span>D) <?php echo htmlspecialchars($question['option_d']); ?></span>
                    </label>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Submit Section -->
        <div class="submit-section">
            <h4><i class="fas fa-check-circle me-2"></i>Ready to Submit?</h4>
            <p class="text-muted mb-4">Make sure you've answered all questions before submitting.</p>
            
            <button type="button" class="submit-btn" id="submitBtn" onclick="submitQuiz()">
                <i class="fas fa-paper-plane me-2"></i>Submit Quiz
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let timeElapsed = 0;
        let timerInterval;
        const totalQuestions = <?php echo count($questions); ?>;

        // Update status
        function updateStatus(message) {
            document.getElementById('statusText').textContent = message;
            console.log('Status:', message);
        }

        // Start timer
        function startTimer() {
            const timeLimit = <?php echo $quiz['time_limit'] ?? 30; ?> * 60;
            
            timerInterval = setInterval(function() {
                timeElapsed++;
                let timeRemaining = timeLimit - timeElapsed;
                
                if (timeRemaining <= 0) {
                    updateStatus('Time up! Auto-submitting...');
                    submitQuiz();
                    return;
                }
                
                let minutes = Math.floor(timeRemaining / 60);
                let seconds = timeRemaining % 60;
                
                document.getElementById('time-display').textContent = 
                    minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                
            }, 1000);
            
            updateStatus('Timer started');
        }

        // Progress counter
        function updateProgress() {
            let answered = document.querySelectorAll('input[type="radio"]:checked').length;
            document.getElementById('progress-count').textContent = answered;
            updateStatus(`${answered} of ${totalQuestions} questions answered`);
        }

        // Collect answers
        function collectAnswers() {
            const answers = {};
            const checkedRadios = document.querySelectorAll('input[type="radio"]:checked');
            
            checkedRadios.forEach(radio => {
                const questionId = radio.getAttribute('data-question');
                answers[questionId] = radio.value;
            });
            
            updateStatus(`Collected ${Object.keys(answers).length} answers`);
            return answers;
        }

        // Submit quiz using AJAX
        function submitQuiz() {
            updateStatus('Submitting quiz...');
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            submitBtn.disabled = true;
            
            clearInterval(timerInterval);
            
            const answers = collectAnswers();
            
            // Prepare data
            const quizData = {
                quiz_id: <?php echo $quiz_id; ?>,
                student_id: <?php echo $student_id; ?>,
                answers: answers,
                time_taken: timeElapsed,
                total_questions: totalQuestions
            };
            
            console.log('Submitting data:', quizData);
            updateStatus('Sending data to server...');
            
            // Send AJAX request
            fetch('quiz_submit_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(quizData)
            })
            .then(response => {
                updateStatus('Server response received');
                return response.json();
            })
            .then(data => {
                updateStatus('Processing server response...');
                console.log('Server response:', data);
                
                if (data.success) {
                    updateStatus('Quiz submitted successfully! Redirecting...');
                    
                    // Store result in sessionStorage for the results page
                    sessionStorage.setItem('quizResult', JSON.stringify(data.result));
                    
                    // Redirect to results
                    setTimeout(() => {
                        window.location.href = 'quiz_results.php';
                    }, 1000);
                } else {
                    updateStatus('Error: ' + data.message);
                    alert('Error submitting quiz: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Quiz';
                }
            })
            .catch(error => {
                updateStatus('Network error: ' + error.message);
                console.error('Error:', error);
                alert('Network error. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Quiz';
            });
        }

        // Track progress
        document.addEventListener('change', function(e) {
            if (e.target.type === 'radio') {
                updateProgress();
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateStatus('Quiz loaded successfully');
            startTimer();
            updateProgress();
        });
    </script>
</body>
</html>