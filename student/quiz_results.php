<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['user_id'];

// Try to get quiz results if available
$quiz_result = null;
if (isset($_GET['quiz_id']) || isset($_SESSION['quiz_result'])) {
    $quiz_id = isset($_GET['quiz_id']) ? $_GET['quiz_id'] : $_SESSION['quiz_result']['quiz_id'];
    
    try {
        $stmt = $conn->prepare("SELECT qr.*, q.title as quiz_title 
                               FROM quiz_results qr 
                               JOIN quizzes q ON qr.quiz_id = q.id 
                               WHERE qr.student_id = ? AND qr.quiz_id = ? 
                               ORDER BY qr.completed_at DESC LIMIT 1");
        $stmt->bind_param("ii", $student_id, $quiz_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $quiz_result = $result->fetch_assoc();
        }
    } catch (Exception $e) {
        // Handle error or use session data
    }
}

// If we have session data but no DB data, use session data
if (!$quiz_result && isset($_SESSION['quiz_result'])) {
    $quiz_result = $_SESSION['quiz_result'];
    unset($_SESSION['quiz_result']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - Learning Management System</title>
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

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }


        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
        }

        .welcome-header {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }

        .welcome-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, transparent 50%, rgba(255, 255, 255, 0.05) 100%);
            pointer-events: none;
        }

        .welcome-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        .welcome-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            color: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, transparent 50%, rgba(255, 255, 255, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.18);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.9;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        .stat-card .label {
            font-size: 0.9rem;
            opacity: 0.8;
            font-weight: 500;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .content-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            padding: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.25);
        }

        .content-card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.08);
        }

        .content-card-header h5 {
            margin: 0;
            color: white;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        .content-card-body {
            padding: 2rem;
            color: white;
        }

        .lesson-item, .meeting-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .lesson-item:hover, .meeting-item:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(5px);
        }

        .lesson-item:last-child, .meeting-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .quick-action-btn {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .quick-action-btn:hover {
            background: rgba(255, 255, 255, 0.18);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        /* Quiz Results Specific Styles */
        .result-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .result-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            margin-bottom: 2rem;
        }

        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .score-excellent { 
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.8), rgba(22, 163, 74, 0.8)); 
            border: 2px solid rgba(34, 197, 94, 0.5);
        }
        .score-good { 
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.8), rgba(29, 78, 216, 0.8)); 
            border: 2px solid rgba(59, 130, 246, 0.5);
        }
        .score-average { 
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.8), rgba(217, 119, 6, 0.8)); 
            border: 2px solid rgba(245, 158, 11, 0.5);
        }
        .score-poor { 
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.8), rgba(220, 38, 38, 0.8)); 
            border: 2px solid rgba(239, 68, 68, 0.5);
        }

        .loading {
            text-align: center;
            padding: 50px;
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

        .badge {
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-shadow: none;
        }

        /* Enhanced responsive design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1000;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            body {
                background-attachment: scroll;
            }
            
            .result-card {
                padding: 20px;
            }
            
            .score-circle {
                width: 120px;
                height: 120px;
                font-size: 2rem;
            }
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

        /* Loading animation for background */
        .bg-loading {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { opacity: 0.8; }
            50% { opacity: 1; }
            100% { opacity: 0.8; }
        }

        /* Enhanced glassmorphism effects */
        .glass-effect {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">


        <!-- Main Content -->
        <main class="main-content">
            <!-- Welcome Header -->
            <div class="welcome-header">
                <h1><i class="fas fa-trophy me-3 text-warning"></i>Quiz Results</h1>
                <p>See how you performed on your latest quiz attempt.</p>
            </div>

            <div class="result-container">
                <div id="loadingDiv" class="loading" style="<?php echo $quiz_result ? 'display: none;' : ''; ?>">
                    <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
                    <h3>Loading Results...</h3>
                </div>

                <div id="resultDiv" style="<?php echo $quiz_result ? '' : 'display: none;'; ?>">
                    <div class="result-card">
                        <h1><i class="fas fa-trophy me-3"></i>Quiz Complete!</h1>
                        <h3 class="mb-4" id="quizTitle"><?php echo $quiz_result ? htmlspecialchars($quiz_result['quiz_title']) : 'Quiz Results'; ?></h3>

                        <div id="scoreCircle" class="score-circle">
                            <span id="scoreText"><?php echo $quiz_result ? round(($quiz_result['correct_answers'] / $quiz_result['total_questions']) * 100) : '0'; ?>%</span>
                        </div>

                        <h3 class="mb-4" id="scoreMessage">Good Job!</h3>

                        <div class="row">
                            <div class="col-md-3">
                                <h4 id="correctCount"><?php echo $quiz_result ? $quiz_result['correct_answers'] : '0'; ?></h4>
                                <p class="text-muted">Correct</p>
                            </div>
                            <div class="col-md-3">
                                <h4 id="totalCount"><?php echo $quiz_result ? $quiz_result['total_questions'] : '0'; ?></h4>
                                <p class="text-muted">Total</p>
                            </div>
                            <div class="col-md-3">
                                <h4 id="timeText"><?php echo $quiz_result ? gmdate("i:s", $quiz_result['time_taken']) : '0:00'; ?></h4>
                                <p class="text-muted">Time</p>
                            </div>
                            <div class="col-md-3">
                                <h4 id="finalScore"><?php echo $quiz_result ? round(($quiz_result['correct_answers'] / $quiz_result['total_questions']) * 100) : '0'; ?>%</h4>
                                <p class="text-muted">Score</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <a href="quizzes.php" class="btn btn-primary me-2">
                                <i class="fas fa-list me-2"></i>All Quizzes
                            </a>
                            <?php if ($quiz_result && isset($quiz_result['quiz_id'])): ?>
                            <button id="retakeBtn" class="btn btn-outline-warning" >
                                <i class="fas fa-redo me-2"></i>Retake
                            </button>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                </div>

                <div id="errorDiv" style="display: none;">
                    <div class="alert alert-danger">
                        <h4>Error Loading Results</h4>
                        <p>Could not load quiz results. <a href="quizzes.php">Return to quizzes</a></p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return minutes + ':' + (secs < 10 ? '0' : '') + secs;
        }

        function displayResults(result) {
            console.log('Displaying results:', result);

            // Update content
            document.getElementById('quizTitle').textContent = result.quiz_title || 'Quiz Results';
            document.getElementById('correctCount').textContent = result.correct_answers;
            document.getElementById('totalCount').textContent = result.total_questions;
            document.getElementById('timeText').textContent = formatTime(result.time_taken);
            document.getElementById('finalScore').textContent = Math.round(result.score) + '%';
            document.getElementById('scoreText').textContent = Math.round(result.score) + '%';

            // Score styling
            const score = result.score;
            const scoreCircle = document.getElementById('scoreCircle');
            const scoreMessage = document.getElementById('scoreMessage');

            if (score >= 90) {
                scoreCircle.className = 'score-circle score-excellent';
                scoreMessage.textContent = 'Excellent! 🎉';
            } else if (score >= 80) {
                scoreCircle.className = 'score-circle score-good';
                scoreMessage.textContent = 'Great Job! 👏';
            } else if (score >= 60) {
                scoreCircle.className = 'score-circle score-average';
                scoreMessage.textContent = 'Good Work! 👍';
            } else {
                scoreCircle.className = 'score-circle score-poor';
                scoreMessage.textContent = 'Keep Practicing! 💪';
            }

            // Show retake button if quiz_id available
            if (result.quiz_id) {
                const retakeBtn = document.getElementById('retakeBtn');
                retakeBtn.style.display = 'inline-block';
                retakeBtn.onclick = () => {
                    window.location.href = 'quiz_take_ajax.php?id=' + result.quiz_id;
                };
            }

            // Hide loading, show results
            document.getElementById('loadingDiv').style.display = 'none';
            document.getElementById('resultDiv').style.display = 'block';

            // Confetti for excellent scores
            if (score >= 90) {
                setTimeout(createConfetti, 500);
            }

            document.getElementById('retakeBtn')?.addEventListener('click', function() {
    // Redirect to your quiz question page with the quiz ID
    const quizId = "<?php echo $quiz_result['quiz_id']; ?>";
    window.location.href = `quiz_take.php?id=${quizId}`;
        }

        function createConfetti() {
            for (let i = 0; i < 50; i++) {
                setTimeout(() => {
                    let confetti = document.createElement('div');
                    confetti.style.position = 'fixed';
                    confetti.style.left = Math.random() * 100 + 'vw';
                    confetti.style.top = '-10px';
                    confetti.style.width = '10px';
                    confetti.style.height = '10px';
                    confetti.style.backgroundColor = ['#f59e0b', '#ef4444', '#22c55e', '#3b82f6'][Math.floor(Math.random() * 4)];
                    confetti.style.pointerEvents = 'none';
                    confetti.style.zIndex = '1000';
                    document.body.appendChild(confetti);
                    
                    let animation = confetti.animate([
                        { transform: 'translateY(-10px) rotate(0deg)', opacity: 1 },
                        { transform: 'translateY(100vh) rotate(360deg)', opacity: 0 }
                    ], {
                        duration: 3000,
                        easing: 'linear'
                    });
                    
                    animation.addEventListener('finish', () => {
                        confetti.remove();
                    });
                }, i * 100);
            }
        }

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }

        // Add mobile menu button
        if (window.innerWidth <= 768) {
            const mobileMenuBtn = document.createElement('button');
            mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            mobileMenuBtn.className = 'btn btn-primary position-fixed';
            mobileMenuBtn.style.cssText = 'top: 1rem; left: 1rem; z-index: 1001; backdrop-filter: blur(10px);';
            mobileMenuBtn.onclick = toggleSidebar;
            document.body.appendChild(mobileMenuBtn);
        }

        // Background image loading optimization
        document.addEventListener('DOMContentLoaded', function() {
            // Preload background image
            const img = new Image();
            img.onload = function() {
                document.body.classList.remove('bg-loading');
            };
            img.src = '../1.png';
            
            // Set up retake button if it exists
            const retakeBtn = document.getElementById('retakeBtn');
            if (retakeBtn) {
                retakeBtn.onclick = function() {
                    window.location.href = 'quiz_take_ajax.php?id=<?php echo $quiz_result ? $quiz_result["quiz_id"] : ""; ?>';
                };
            }
            
            // If we have PHP results, style them appropriately
            <?php if ($quiz_result): ?>
            {
                const score = Math.round((<?php echo $quiz_result['correct_answers']; ?> / <?php echo $quiz_result['total_questions']; ?>) * 100);
                const scoreCircle = document.getElementById('scoreCircle');
                const scoreMessage = document.getElementById('scoreMessage');

                if (score >= 90) {
                    scoreCircle.className = 'score-circle score-excellent';
                    scoreMessage.textContent = 'Excellent! 🎉';
                    setTimeout(createConfetti, 500);
                } else if (score >= 80) {
                    scoreCircle.className = 'score-circle score-good';
                    scoreMessage.textContent = 'Great Job! 👏';
                } else if (score >= 60) {
                    scoreCircle.className = 'score-circle score-average';
                    scoreMessage.textContent = 'Good Work! 👍';
                } else {
                    scoreCircle.className = 'score-circle score-poor';
                    scoreMessage.textContent = 'Keep Practicing! 💪';
                }
            }
            <?php else: ?>
            // If no PHP results, try to get from sessionStorage
            console.log('Results page loaded');
            const storedResult = sessionStorage.getItem('quizResult');
            if (storedResult) {
                try {
                    const result = JSON.parse(storedResult);
                    console.log('Found result in sessionStorage:', result);
                    sessionStorage.removeItem('quizResult'); // Clean up
                    displayResults(result);
                } catch (e) {
                    console.error('Error parsing stored result:', e);
                    setTimeout(() => {
                        document.getElementById('loadingDiv').style.display = 'none';
                        document.getElementById('errorDiv').style.display = 'block';
                    }, 2000);
                }
            } else {
                // If no sessionStorage result, show error
                setTimeout(() => {
                    document.getElementById('loadingDiv').style.display = 'none';
                    document.getElementById('errorDiv').style.display = 'block';
                }, 2000);
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>