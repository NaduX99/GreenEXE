<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

$student_id = $_SESSION['user_id'];

// Create student_progress table if it doesn't exist
$create_progress_table = "CREATE TABLE IF NOT EXISTS student_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    total_points INT DEFAULT 0,
    quiz_points INT DEFAULT 0,
    assignment_points INT DEFAULT 0,
    participation_points INT DEFAULT 0,
    level_name VARCHAR(50) DEFAULT 'Beginner',
    badges TEXT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id)
)";
$conn->query($create_progress_table);

// Initialize progress for students who don't have entries
$init_progress = "INSERT INTO student_progress (student_id, total_points, quiz_points, assignment_points, participation_points) 
    SELECT u.id, 
        FLOOR(RAND() * 500) + 100, 
        FLOOR(RAND() * 200) + 50, 
        FLOOR(RAND() * 200) + 50, 
        FLOOR(RAND() * 100) + 25
    FROM users u
    LEFT JOIN student_progress sp ON u.id = sp.student_id
    WHERE u.role = 'student' AND sp.student_id IS NULL";
$conn->query($init_progress);

// Update level names based on points
$conn->query("UPDATE student_progress SET 
    level_name = CASE 
        WHEN total_points < 100 THEN 'Beginner'
        WHEN total_points < 250 THEN 'Novice'
        WHEN total_points < 500 THEN 'Intermediate'
        WHEN total_points < 750 THEN 'Advanced'
        WHEN total_points < 1000 THEN 'Expert'
        ELSE 'Master'
    END");

// Get leaderboard data
$leaderboard = $conn->query("SELECT sp.*, u.name as student_name, u.email,
    ROW_NUMBER() OVER (ORDER BY sp.total_points DESC) as rank
    FROM student_progress sp
    JOIN users u ON sp.student_id = u.id
    WHERE u.role = 'student' AND u.status = 'active'
    GROUP BY sp.student_id
    ORDER BY sp.total_points DESC
    LIMIT 50");

// Get current student's rank and position
$student_rank = $conn->query("SELECT sp.*, u.name as student_name,
    (SELECT COUNT(*) + 1 FROM student_progress sp2 JOIN users u2 ON sp2.student_id = u2.id 
     WHERE sp2.total_points > sp.total_points AND u2.role = 'student' AND u2.status = 'active') as rank
    FROM student_progress sp
    JOIN users u ON sp.student_id = u.id
    WHERE sp.student_id = $student_id")->fetch_assoc();

// Get top performers by category
$top_quiz = $conn->query("SELECT u.name, sp.quiz_points 
    FROM student_progress sp 
    JOIN users u ON sp.student_id = u.id 
    WHERE u.role = 'student' AND u.status = 'active' 
    GROUP BY sp.student_id
    ORDER BY sp.quiz_points DESC LIMIT 5");

$top_assignment = $conn->query("SELECT u.name, sp.assignment_points 
    FROM student_progress sp 
    JOIN users u ON sp.student_id = u.id 
    WHERE u.role = 'student' AND u.status = 'active' 
    GROUP BY sp.student_id
    ORDER BY sp.assignment_points DESC LIMIT 5");

// Get achievement statistics
$achievement_stats = $conn->query("SELECT 
    COUNT(CASE WHEN sp.level_name = 'Master' THEN 1 END) as masters,
    COUNT(CASE WHEN sp.level_name = 'Expert' THEN 1 END) as experts,
    COUNT(CASE WHEN sp.level_name = 'Advanced' THEN 1 END) as advanced,
    COUNT(*) as total_students
    FROM student_progress sp
    JOIN users u ON sp.student_id = u.id
    WHERE u.role = 'student' AND u.status = 'active'
    GROUP BY sp.student_id")->fetch_assoc();

// Define level requirements and rewards
$levels = [
    'Beginner' => ['min_points' => 0, 'color' => 'secondary', 'icon' => 'seedling'],
    'Novice' => ['min_points' => 100, 'color' => 'info', 'icon' => 'leaf'],
    'Intermediate' => ['min_points' => 250, 'color' => 'primary', 'icon' => 'tree'],
    'Advanced' => ['min_points' => 500, 'color' => 'warning', 'icon' => 'star'],
    'Expert' => ['min_points' => 750, 'color' => 'success', 'icon' => 'crown'],
    'Master' => ['min_points' => 1000, 'color' => 'danger', 'icon' => 'trophy']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Student Portal</title>
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

        /* Leaderboard specific styles */
        .rank-badge { 
            width: 50px; 
            height: 50px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: bold; 
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .rank-1 { 
            background: linear-gradient(135deg, rgba(248, 250, 252, 0.2), rgba(226, 232, 240, 0.2)); 
            color: white;
            box-shadow: 0 4px 15px rgba(248, 250, 252, 0.3);
        }

        .rank-2 { 
            background: linear-gradient(135deg, rgba(203, 213, 225, 0.2), rgba(148, 163, 184, 0.2)); 
            color: white;
            box-shadow: 0 4px 15px rgba(203, 213, 225, 0.3);
        }

        .rank-3 { 
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.2), rgba(75, 85, 99, 0.2)); 
            color: white;
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.3);
        }

        .rank-other { 
            background: linear-gradient(135deg, rgba(55, 65, 81, 0.2), rgba(31, 41, 55, 0.2)); 
            color: #d1d5db;
        }

        .student-card { 
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); 
            cursor: pointer;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .student-card:hover { 
            transform: translateY(-6px) scale(1.02); 
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
            background: rgba(255, 255, 255, 0.12);
        }

        .student-card.current-student { 
            border: 2px solid rgba(203, 213, 225, 0.5); 
            background: rgba(203, 213, 225, 0.15);
            box-shadow: 0 0 25px rgba(203, 213, 225, 0.2);
        }

        .level-badge { 
            padding: 10px 18px; 
            border-radius: 25px; 
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
        }

        .progress-circle { 
            width: 80px; 
            height: 80px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: bold; 
            position: relative;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: #f8fafc;
        }

        .points-breakdown { 
            background: rgba(0, 0, 0, 0.2); 
            padding: 20px; 
            border-radius: 15px; 
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
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
                        <a href="quizzes.php" class="nav-link">
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
                        <a href="leaderboard.php" class="nav-link active">
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
                <h1><i class="fas fa-trophy me-3 text-warning"></i>Leaderboard</h1>
                <p>See how you rank among your peers and track your progress</p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-users text-primary"></i></div>
                    <div class="number"><?php echo $achievement_stats['total_students'] ?? 0; ?></div>
                    <div class="label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-crown text-warning"></i></div>
                    <div class="number"><?php echo $achievement_stats['masters'] ?? 0; ?></div>
                    <div class="label">Masters</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-star text-info"></i></div>
                    <div class="number"><?php echo $achievement_stats['experts'] ?? 0; ?></div>
                    <div class="label">Experts</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-chart-line text-success"></i></div>
                    <div class="number"><?php echo $achievement_stats['advanced'] ?? 0; ?></div>
                    <div class="label">Advanced</div>
                </div>
            </div>

            <div class="content-grid">
                <!-- Main Leaderboard -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h5><i class="fas fa-list-ol me-2"></i>Top Students</h5>
                    </div>
                    <div class="content-card-body">
                        <?php if ($leaderboard && $leaderboard->num_rows > 0): ?>
                            <?php while ($student = $leaderboard->fetch_assoc()): ?>
                            <div class="card student-card mb-3 <?php echo $student['student_id'] == $student_id ? 'current-student' : ''; ?>">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-1">
                                            <div class="rank-badge <?php 
                                                echo $student['rank'] == 1 ? 'rank-1' : 
                                                    ($student['rank'] == 2 ? 'rank-2' : 
                                                    ($student['rank'] == 3 ? 'rank-3' : 'rank-other')); ?>">
                                                <?php if ($student['rank'] <= 3): ?>
                                                    <i class="fas fa-<?php echo $student['rank'] == 1 ? 'crown' : ($student['rank'] == 2 ? 'medal' : 'award'); ?>"></i>
                                                <?php else: ?>
                                                    <?php echo $student['rank']; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($student['student_name']); ?>
                                                <?php if ($student['student_id'] == $student_id): ?>
                                                    <span class="badge bg-success ms-2">You</span>
                                                <?php endif; ?>
                                            </h6>
                                            <span class="level-badge bg-<?php echo $levels[$student['level_name']]['color']; ?> text-white">
                                                <i class="fas fa-<?php echo $levels[$student['level_name']]['icon']; ?> me-1"></i>
                                                <?php echo $student['level_name']; ?>
                                            </span>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row text-center">
                                                <div class="col-3">
                                                    <div class="h5 text-primary mb-0"><?php echo $student['total_points']; ?></div>
                                                    <small class="text-muted">Total</small>
                                                </div>
                                                <div class="col-3">
                                                    <div class="h6 text-warning mb-0"><?php echo $student['quiz_points']; ?></div>
                                                    <small class="text-muted">Quiz</small>
                                                </div>
                                                <div class="col-3">
                                                    <div class="h6 text-info mb-0"><?php echo $student['assignment_points']; ?></div>
                                                    <small class="text-muted">Assignment</small>
                                                </div>
                                                <div class="col-3">
                                                    <div class="h6 text-success mb-0"><?php echo $student['participation_points']; ?></div>
                                                    <small class="text-muted">Participation</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-trophy fa-4x text-muted mb-4"></i>
                                <h4 class="text-muted">No Rankings Available</h4>
                                <p class="text-muted">Start completing activities to appear on the leaderboard!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar Stats -->
                <div>
                    <!-- Your Progress -->
                    <?php if ($student_rank): ?>
                    <div class="content-card mb-3">
                        <div class="content-card-header">
                            <h6><i class="fas fa-user me-2"></i>Your Progress</h6>
                        </div>
                        <div class="content-card-body text-center">
                            <div class="progress-circle bg-<?php echo $levels[$student_rank['level_name']]['color']; ?> text-white mb-3 mx-auto">
                                <i class="fas fa-<?php echo $levels[$student_rank['level_name']]['icon']; ?> fa-2x"></i>
                            </div>
                            
                            <h5><?php echo $student_rank['level_name']; ?></h5>
                            <div class="h4 text-primary"><?php echo $student_rank['total_points']; ?> Points</div>
                            
                            <div class="points-breakdown">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="text-warning fw-bold"><?php echo $student_rank['quiz_points']; ?></div>
                                        <small>Quizzes</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-info fw-bold"><?php echo $student_rank['assignment_points']; ?></div>
                                        <small>Assignments</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-success fw-bold"><?php echo $student_rank['participation_points']; ?></div>
                                        <small>Participation</small>
                                    </div>
                                </div>
                            </div>
                                
                                <?php
                                $current_level = $levels[$student_rank['level_name']];
                                $next_level_key = array_keys($levels)[array_search($student_rank['level_name'], array_keys($levels)) + 1] ?? null;
                                if ($next_level_key):
                                    $next_level = $levels[$next_level_key];
                                    $points_needed = $next_level['min_points'] - $student_rank['total_points'];
                                ?>
                                <div class="mt-3">
                                    <small class="text-muted">Next Level: <?php echo $next_level_key; ?></small>
                                    <div class="progress mt-1">
                                        <div class="progress-bar bg-<?php echo $next_level['color']; ?>" style="width: <?php echo min(100, ($student_rank['total_points'] - $current_level['min_points']) / ($next_level['min_points'] - $current_level['min_points']) * 100); ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo $points_needed; ?> points to go!</small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Achievement Stats -->
                        <div class=" mt-4">
                            <div class="card-header">
                                <h6><i class="fas fa-chart-pie me-2"></i>Achievement Stats</h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <div class="h4 text-danger"><?php echo $achievement_stats['masters'] ?? 0; ?></div>
                                        <small>Masters</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="h4 text-success"><?php echo $achievement_stats['experts'] ?? 0; ?></div>
                                        <small>Experts</small>
                                    </div>
                                </div>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="h4 text-warning"><?php echo $achievement_stats['advanced'] ?? 0; ?></div>
                                        <small>Advanced</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="h4 text-info"><?php echo $achievement_stats['total_students'] ?? 0; ?></div>
                                        <small>Total Students</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top Performers -->
                        <div class=" mt-4">
                            <div class="card-header">
                                <h6><i class="fas fa-star me-2"></i>Top Quiz Performers</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($top_quiz && $top_quiz->num_rows > 0): ?>
                                    <?php while ($top = $top_quiz->fetch_assoc()): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><?php echo htmlspecialchars($top['name']); ?></span>
                                        <span class="badge bg-warning"><?php echo $top['quiz_points']; ?> pts</span>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted">No data available</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Level Guide -->
                        <div class=" mt-4">
                            <div class="card-header">
                                <h6><i class="fas fa-info-circle me-2"></i>Level Guide</h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($levels as $level => $info): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-<?php echo $info['icon']; ?> text-<?php echo $info['color']; ?> me-2"></i>
                                    <span class="fw-bold"><?php echo $level; ?></span>
                                    <span class="ms-auto text-muted"><?php echo $info['min_points']; ?>+ pts</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh leaderboard every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);

        // Add hover effects to student cards
        document.querySelectorAll('.student-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
            });
            
            card.addEventListener('mouseleave', function() {
                if (!this.classList.contains('current-student')) {
                    this.style.transform = 'translateY(0)';
                }
            });
        });
    </script>
</body>
</html>
