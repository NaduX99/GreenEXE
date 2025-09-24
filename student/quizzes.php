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
$message = '';
$debug_info = '';

// Check existing tables and their structures
$tables_info = [];
$tables_result = $conn->query("SHOW TABLES");
if ($tables_result) {
    while ($row = $tables_result->fetch_row()) {
        $table_name = $row[0];
        $tables_info[$table_name] = [];
        
        // Get column info for each table
        $columns_result = $conn->query("DESCRIBE $table_name");
        if ($columns_result) {
            while ($col = $columns_result->fetch_assoc()) {
                $tables_info[$table_name][] = $col['Field'];
            }
        }
    }
}

$debug_info .= "Tables found: " . implode(', ', array_keys($tables_info)) . ". ";

// Create quiz_attempts table with correct structure if it doesn't exist
if (!isset($tables_info['quiz_attempts'])) {
    $create_attempts = "CREATE TABLE quiz_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        quiz_id INT NOT NULL,
        score DECIMAL(5,2) DEFAULT 0,
        total_questions INT DEFAULT 0,
        correct_answers INT DEFAULT 0,
        time_taken INT DEFAULT 0,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student_quiz (student_id, quiz_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    if ($conn->query($create_attempts)) {
        $debug_info .= "Quiz attempts table created. ";
        $tables_info['quiz_attempts'] = ['id', 'student_id', 'quiz_id', 'score', 'total_questions', 'correct_answers', 'time_taken', 'completed_at'];
    } else {
        $debug_info .= "Failed to create quiz_attempts: " . $conn->error . ". ";
    }
}

// Create other tables if needed
if (!isset($tables_info['quizzes'])) {
    $create_quizzes = "CREATE TABLE quizzes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        subject_id INT NOT NULL,
        time_limit INT DEFAULT 30,
        total_questions INT DEFAULT 0,
        difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    if ($conn->query($create_quizzes)) {
        $debug_info .= "Quizzes table created. ";
    } else {
        $debug_info .= "Failed to create quizzes: " . $conn->error . ". ";
    }
}

if (!isset($tables_info['quiz_questions'])) {
    $create_questions = "CREATE TABLE quiz_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT NOT NULL,
        question_text TEXT NOT NULL,
        option_a VARCHAR(255) NOT NULL,
        option_b VARCHAR(255) NOT NULL,
        option_c VARCHAR(255) NOT NULL,
        option_d VARCHAR(255) NOT NULL,
        correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
        explanation TEXT,
        points INT DEFAULT 1,
        difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    if ($conn->query($create_questions)) {
        $debug_info .= "Quiz questions table created. ";
    } else {
        $debug_info .= "Failed to create quiz_questions: " . $conn->error . ". ";
    }
}

// Check if quiz_attempts table has the required columns
$attempts_table_ready = false;
if (isset($tables_info['quiz_attempts'])) {
    $required_columns = ['student_id', 'quiz_id', 'score'];
    $has_all_columns = true;
    foreach ($required_columns as $col) {
        if (!in_array($col, $tables_info['quiz_attempts'])) {
            $has_all_columns = false;
            break;
        }
    }
    $attempts_table_ready = $has_all_columns;
    $debug_info .= "Quiz attempts table ready: " . ($attempts_table_ready ? 'Yes' : 'No') . ". ";
}

// Get quiz statistics - ULTRA SAFE VERSION
if ($attempts_table_ready) {
    // Test with a simple query first
    $test_query = $conn->query("SELECT COUNT(*) as count FROM quiz_attempts WHERE student_id = $student_id LIMIT 1");
    if ($test_query) {
        $quiz_stats_query = "SELECT 
            (SELECT COUNT(*) FROM quizzes WHERE status = 'active') as total_quizzes,
            COUNT(DISTINCT quiz_id) as completed_quizzes,
            IFNULL(AVG(score), 0) as avg_score,
            IFNULL(MAX(score), 0) as best_score
            FROM quiz_attempts 
            WHERE student_id = $student_id";
        
        $stats_result = $conn->query($quiz_stats_query);
        
        if ($stats_result) {
            $quiz_stats = $stats_result->fetch_assoc();
            $debug_info .= "Stats loaded successfully. ";
        } else {
            $debug_info .= "Stats query failed: " . $conn->error . ". ";
            $quiz_stats = [
                'total_quizzes' => 0,
                'completed_quizzes' => 0,
                'avg_score' => 0.0,
                'best_score' => 0.0
            ];
        }
    } else {
        $debug_info .= "Test query failed: " . $conn->error . ". ";
        $quiz_stats = [
            'total_quizzes' => 0,
            'completed_quizzes' => 0,
            'avg_score' => 0.0,
            'best_score' => 0.0
        ];
    }
} else {
    // Safe fallback when attempts table is not ready
    $total_result = $conn->query("SELECT COUNT(*) as total FROM quizzes WHERE status = 'active'");
    $total_quizzes = $total_result ? $total_result->fetch_assoc()['total'] : 0;
    
    $quiz_stats = [
        'total_quizzes' => $total_quizzes,
        'completed_quizzes' => 0,
        'avg_score' => 0.0,
        'best_score' => 0.0
    ];
    $debug_info .= "Using fallback stats. ";
}

// Get quizzes - SAFE VERSION
$quizzes_query = "SELECT q.id, q.title, q.description, q.time_limit, q.created_at,
    COALESCE(s.name, 'General') as subject_name,
    COALESCE((SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id), 0) as question_count
    FROM quizzes q
    LEFT JOIN subjects s ON q.subject_id = s.id
    WHERE q.status = 'active'
    ORDER BY q.created_at DESC";

$quizzes = $conn->query($quizzes_query);

if (!$quizzes) {
    $debug_info .= "Quiz query failed: " . $conn->error . ". ";
    
    // Ultimate fallback - just get basic quiz info
    $simple_query = "SELECT id, title, description, time_limit, created_at FROM quizzes WHERE status = 'active' ORDER BY created_at DESC";
    $quizzes = $conn->query($simple_query);
    
    if ($quizzes) {
        $debug_info .= "Using basic quiz query. ";
    } else {
        $debug_info .= "All quiz queries failed. ";
        $quizzes = false;
    }
} else {
    $debug_info .= "Found " . $quizzes->num_rows . " quizzes. ";
}

// Get recent attempts - ONLY if table is ready
$recent_attempts_data = [];
if ($attempts_table_ready) {
    $recent_query = "SELECT qa.score, qa.completed_at, q.title as quiz_title, 
        COALESCE(s.name, 'General') as subject_name
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        LEFT JOIN subjects s ON q.subject_id = s.id
        WHERE qa.student_id = $student_id
        ORDER BY qa.completed_at DESC
        LIMIT 5";
    
    $recent_result = $conn->query($recent_query);
    
    if ($recent_result) {
        while ($attempt = $recent_result->fetch_assoc()) {
            $recent_attempts_data[] = $attempt;
        }
        $debug_info .= "Recent attempts: " . count($recent_attempts_data) . ". ";
    } else {
        $debug_info .= "Recent attempts failed: " . $conn->error . ". ";
    }
}

// Add attempt data for each quiz if table is ready
$quizzes_with_attempts = [];
if ($quizzes && $attempts_table_ready) {
    while ($quiz = $quizzes->fetch_assoc()) {
        // Get attempt info for this quiz
        $attempt_query = "SELECT COUNT(*) as attempts, MAX(score) as best_score 
            FROM quiz_attempts 
            WHERE quiz_id = {$quiz['id']} AND student_id = $student_id";
        
        $attempt_result = $conn->query($attempt_query);
        
        if ($attempt_result) {
            $attempt_data = $attempt_result->fetch_assoc();
            $quiz['attempts_count'] = $attempt_data['attempts'];
            $quiz['best_score'] = $attempt_data['best_score'];
        } else {
            $quiz['attempts_count'] = 0;
            $quiz['best_score'] = null;
        }
        
        $quizzes_with_attempts[] = $quiz;
    }
} elseif ($quizzes) {
    while ($quiz = $quizzes->fetch_assoc()) {
        $quiz['attempts_count'] = 0;
        $quiz['best_score'] = null;
        $quiz['subject_name'] = $quiz['subject_name'] ?? 'General';
        $quiz['question_count'] = $quiz['question_count'] ?? 0;
        $quizzes_with_attempts[] = $quiz;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzes - Student Portal</title>
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

        .sidebar {
            width: 250px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border-right: 1px solid rgba(255, 255, 255, 0.25);
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        }

        .sidebar-header {
            padding: 2rem 1.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            text-align: center;
            background: rgba(255, 255, 255, 0.08);
        }

        .sidebar-header h4 {
            color: white;
            margin: 0;
            font-weight: 700;
            font-size: 1.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .sidebar-header small {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.8rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .nav-section {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 1.5rem 1.5rem 0.5rem;
            margin: 0;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav .nav-item {
            margin: 0.2rem 1rem;
        }

        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            padding: 0.8rem 1rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        .sidebar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .sidebar-nav .nav-link.active {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .sidebar-nav .nav-link i {
            width: 20px;
            margin-right: 0.8rem;
            text-align: center;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
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

        /* Quiz specific styles */
        .quiz-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            color: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        .quiz-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
        }

        .quiz-completed {
            border-left: 4px solid rgba(16, 185, 129, 0.7);
        }

        .quiz-not-attempted {
            border-left: 4px solid rgba(245, 158, 11, 0.7);
        }

        .score-circle {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: #f1f5f9;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
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
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h4><i class="fas fa-graduation-cap me-2"></i>LearnArena</h4>
                <small>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></small>
            </div>
            
            <nav>
                <ul class="sidebar-nav">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <i class="fas fa-home"></i>Dashboard
                        </a>
                    </li>
                    
                    <div class="nav-section">Learning</div>
                    <li class="nav-item">
                        <a href="lessons.php" class="nav-link">
                            <i class="fas fa-book-open"></i>Lessons
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="quizzes.php" class="nav-link active">
                            <i class="fas fa-question-circle"></i>Quizzes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="assignments.php" class="nav-link">
                            <i class="fas fa-tasks"></i>Assignments
                        </a>
                    </li>
                    
                    <div class="nav-section">Communication</div>
                    <li class="nav-item">
                        <a href="meetings.php" class="nav-link">
                            <i class="fas fa-video"></i>Meetings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="chat.php" class="nav-link">
                            <i class="fas fa-comments"></i>Chat Room
                        </a>
                    </li>
                    
                    <div class="nav-section">Progress</div>
                    <li class="nav-item">
                        <a href="leaderboard.php" class="nav-link">
                            <i class="fas fa-trophy"></i>Leaderboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="certificates.php" class="nav-link">
                            <i class="fas fa-certificate"></i>Certificates
                        </a>
                    </li>
                    
                    <div class="nav-section">Account</div>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link">
                            <i class="fas fa-user"></i>Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Welcome Header -->
            <div class="welcome-header">
                <h1><i class="fas fa-question-circle me-3 text-info"></i>Quizzes</h1>
                <p>Test your knowledge and track your progress with our interactive quizzes</p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-question-circle text-primary"></i></div>
                    <div class="number"><?php echo $quiz_stats['total_quizzes']; ?></div>
                    <div class="label">Total Quizzes</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-check-circle text-success"></i></div>
                    <div class="number"><?php echo $quiz_stats['completed_quizzes']; ?></div>
                    <div class="label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-percentage text-warning"></i></div>
                    <div class="number"><?php echo round($quiz_stats['avg_score'], 1); ?>%</div>
                    <div class="label">Average Score</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-star text-info"></i></div>
                    <div class="number"><?php echo round($quiz_stats['best_score'], 1); ?>%</div>
                    <div class="label">Best Score</div>
                </div>
            </div>

            <div class="content-grid">
                <!-- Available Quizzes -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h5><i class="fas fa-list me-2"></i>Available Quizzes (<?php echo count($quizzes_with_attempts); ?>)</h5>
                    </div>
                    <div class="content-card-body">
                        <?php if (!empty($quizzes_with_attempts)): ?>
                            <?php foreach ($quizzes_with_attempts as $quiz): ?>
                            <?php
                            $question_count = $quiz['question_count'] ?? 0;
                            $attempts_count = $quiz['attempts_count'] ?? 0;
                            $best_score = $quiz['best_score'] ?? null;
                            ?>
                            <div class="quiz-card <?php echo $attempts_count > 0 ? 'quiz-completed' : 'quiz-not-attempted'; ?>">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($quiz['subject_name']); ?></span>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($quiz['created_at'])); ?>
                                            </small>
                                        </div>
                                        
                                        <h6 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h6>
                                        
                                        <?php if (!empty($quiz['description'])): ?>
                                        <p class="text-muted small">
                                            <?php echo htmlspecialchars($quiz['description']); ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <div class="quiz-meta">
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo $quiz['time_limit']; ?> minutes
                                                
                                                <i class="fas fa-question ms-3 me-1"></i>
                                                <?php echo $question_count; ?> questions
                                                
                                                <?php if ($attempts_count > 0): ?>
                                                <i class="fas fa-redo ms-3 me-1"></i>
                                                Attempted <?php echo $attempts_count; ?> time(s)
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 text-end">
                                        <?php if ($best_score !== null): ?>
                                        <div class="score-circle mb-2" style="background: <?php echo $best_score >= 80 ? 'rgba(16, 185, 129, 0.3)' : ($best_score >= 60 ? 'rgba(245, 158, 11, 0.3)' : 'rgba(239, 68, 68, 0.3)'); ?>">
                                            <?php echo round($best_score); ?>%
                                        </div>
                                        <small class="text-muted d-block mb-2">Best Score</small>
                                        <?php endif; ?>
                                        
                                        <div class="quiz-actions">
                                            <?php if ($question_count > 0): ?>
                                            <a href="quiz_take.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm" style="background: <?php echo $attempts_count > 0 ? 'rgba(245, 158, 11, 0.3)' : 'rgba(59, 130, 246, 0.3)'; ?>; color: white; border: 1px solid rgba(255, 255, 255, 0.2);">
                                                <i class="fas fa-<?php echo $attempts_count > 0 ? 'redo' : 'play'; ?> me-1"></i>
                                                <?php echo $attempts_count > 0 ? 'Retake Quiz' : 'Start Quiz'; ?>
                                            </a>
                                            <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled style="background: rgba(107, 114, 128, 0.3); border: 1px solid rgba(255, 255, 255, 0.2);">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                No Questions Yet
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-question-circle fa-4x text-muted mb-3"></i>
                            <h4>No Quizzes Available</h4>
                            <p class="text-muted">Quizzes will appear here once admins create them!</p>
                            <?php if (!$attempts_table_ready): ?>
                            <div class="alert alert-info mt-3" style="background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(255, 255, 255, 0.2); color: white;">
                                <i class="fas fa-cog me-2"></i>
                                Quiz system is being set up. Please refresh the page in a moment.
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Results -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h5><i class="fas fa-history me-2"></i>Recent Results</h5>
                    </div>
                    <div class="content-card-body">
                        <?php if (!empty($recent_attempts_data)): ?>
                            <?php foreach ($recent_attempts_data as $attempt): ?>
                            <div class="d-flex align-items-center mb-3 p-3 rounded" style="background: rgba(255, 255, 255, 0.08);">
                                <div class="score-circle me-3" style="width: 40px; height: 40px; font-size: 0.8rem; background: <?php echo $attempt['score'] >= 80 ? 'rgba(16, 185, 129, 0.3)' : ($attempt['score'] >= 60 ? 'rgba(245, 158, 11, 0.3)' : 'rgba(239, 68, 68, 0.3)'); ?>">
                                    <?php echo round($attempt['score']); ?>%
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($attempt['quiz_title']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($attempt['subject_name']); ?><br>
                                        <?php echo date('M j, g:i A', strtotime($attempt['completed_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="text-center mt-3">
                                <a href="quiz_results.php" class="btn btn-sm" style="background: rgba(59, 130, 246, 0.3); color: white; border: 1px solid rgba(255, 255, 255, 0.2);">
                                    <i class="fas fa-chart-line me-2"></i>View All Results
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No Quiz Results Yet</h6>
                                <p class="text-muted">Take a quiz to see your results here!</p>
                                <?php if (!$attempts_table_ready): ?>
                                <small class="text-warning">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Results tracking will be available once you take your first quiz.
                                </small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="content-card">
                <div class="content-card-header">
                    <h5><i class="fas fa-lightbulb me-2"></i>Quiz Tips</h5>
                </div>
                <div class="content-card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <small>Read questions carefully</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <small>Manage your time wisely</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <small>Review lessons before quizzes</small>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            <small>You can retake quizzes to improve</small>
                        </li>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }

        // Add mobile menu button
        if (window.innerWidth <= 768) {
            const mobileMenuBtn = document.createElement('button');
            mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            mobileMenuBtn.className = 'btn btn-primary position-fixed';
            mobileMenuBtn.style.cssText = 'top: 1rem; left: 1rem; z-index: 1001; backdrop-filter: blur(10px); background: rgba(59, 130, 246, 0.3); border: 1px solid rgba(255, 255, 255, 0.2);';
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
        });

        // Enhanced hover effects
        document.querySelectorAll('.stat-card, .content-card, .quiz-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = this.classList.contains('stat-card') ? 'translateY(-8px) scale(1.02)' : 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>