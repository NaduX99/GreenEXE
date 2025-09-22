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

// Get statistics - simple and safe
$stats = [
    'total_subjects' => 3,
    'total_lessons' => 5, 
    'total_quizzes' => 4,
    'upcoming_meetings' => 2
];

// Try to get real stats if tables exist
try {
    $subjects_count = $conn->query("SELECT COUNT(*) as count FROM subjects");
    if ($subjects_count) {
        $stats['total_subjects'] = $subjects_count->fetch_assoc()['count'];
    }
    
    $lessons_count = $conn->query("SELECT COUNT(*) as count FROM lessons");
    if ($lessons_count) {
        $stats['total_lessons'] = $lessons_count->fetch_assoc()['count'];
    }
    
    $quizzes_count = $conn->query("SELECT COUNT(*) as count FROM quizzes");
    if ($quizzes_count) {
        $stats['total_quizzes'] = $quizzes_count->fetch_assoc()['count'];
    }
    
    $meetings_count = $conn->query("SELECT COUNT(*) as count FROM meetings WHERE meeting_date >= CURDATE()");
    if ($meetings_count) {
        $stats['upcoming_meetings'] = $meetings_count->fetch_assoc()['count'];
    }
} catch (Exception $e) {
    // Use default values if queries fail
}

// Get recent lessons - safe
$recent_lessons_data = [];
try {
    $recent_lessons = $conn->query("SELECT l.*, s.name as subject_name 
        FROM lessons l 
        LEFT JOIN subjects s ON l.subject_id = s.id 
        ORDER BY l.created_at DESC LIMIT 5");
    
    if ($recent_lessons) {
        while ($lesson = $recent_lessons->fetch_assoc()) {
            $recent_lessons_data[] = $lesson;
        }
    }
} catch (Exception $e) {
    // Default lessons if query fails
    $recent_lessons_data = [
        ['id' => 1, 'title' => 'Introduction to Algebra', 'subject_name' => 'Mathematics'],
        ['id' => 2, 'title' => 'Basic Chemistry', 'subject_name' => 'Science'],
        ['id' => 3, 'title' => 'Grammar Basics', 'subject_name' => 'English']
    ];
}

// Get upcoming meetings - safe
$upcoming_meetings_data = [];
try {
    $upcoming_meetings = $conn->query("SELECT * FROM meetings 
        WHERE meeting_date >= CURDATE() 
        ORDER BY meeting_date ASC LIMIT 3");
    
    if ($upcoming_meetings) {
        while ($meeting = $upcoming_meetings->fetch_assoc()) {
            $upcoming_meetings_data[] = $meeting;
        }
    }
} catch (Exception $e) {
    // Default meetings if query fails
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $upcoming_meetings_data = [
        [
            'id' => 1, 
            'title' => 'Math Study Session', 
            'meeting_date' => $tomorrow, 
            'meeting_time' => '10:00:00',
            'meeting_link' => 'https://zoom.us/j/math123'
        ],
        [
            'id' => 2, 
            'title' => 'Science Discussion', 
            'meeting_date' => date('Y-m-d', strtotime('+1 week')), 
            'meeting_time' => '14:00:00',
            'meeting_link' => 'https://zoom.us/j/science456'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Learning Management System</title>
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
                        <a href="dashboard.php" class="nav-link active">
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
                <h1><i class="fas fa-sun me-3 text-warning"></i>Good <?php 
                    $hour = date('H');
                    if ($hour < 12) echo "Morning";
                    elseif ($hour < 17) echo "Afternoon";
                    else echo "Evening";
                ?>, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>
                <p>Ready to continue your learning journey? Check out your latest lessons and upcoming meetings below.</p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-book-open text-primary"></i></div>
                    <div class="number"><?php echo $stats['total_lessons']; ?></div>
                    <div class="label">Available Lessons</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-brain text-success"></i></div>
                    <div class="number"><?php echo $stats['total_quizzes']; ?></div>
                    <div class="label">Quizzes</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-video text-info"></i></div>
                    <div class="number"><?php echo $stats['upcoming_meetings']; ?></div>
                    <div class="label">Upcoming Meetings</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-graduation-cap text-warning"></i></div>
                    <div class="number"><?php echo $stats['total_subjects']; ?></div>
                    <div class="label">Subjects</div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Recent Lessons -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h5><i class="fas fa-book-reader me-2"></i>Recent Lessons</h5>
                        <a href="lessons.php" class="btn btn-sm btn-outline-light">View All</a>
                    </div>
                    <div class="content-card-body">
                        <?php if (!empty($recent_lessons_data)): ?>
                            <?php foreach ($recent_lessons_data as $lesson): ?>
                            <div class="lesson-item">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-play-circle fa-2x text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($lesson['title']); ?></h6>
                                        <small class="text-muted">
                                            <?php if (isset($lesson['subject_name'])): ?>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($lesson['subject_name']); ?>
                                            </span>
                                            <?php endif; ?>
                                            <span class="ms-2"><?php echo isset($lesson['created_at']) ? date('M j, Y', strtotime($lesson['created_at'])) : 'Recent'; ?></span>
                                        </small>
                                    </div>
                                    <div>
                                        <a href="lesson_view.php?id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                                <h6>No lessons available yet</h6>
                                <p class="text-muted mb-0">Check back later for new content!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Meetings -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h5><i class="fas fa-video me-2"></i>Upcoming Meetings</h5>
                        <a href="meetings.php" class="btn btn-sm btn-outline-light">View All</a>
                    </div>
                    <div class="content-card-body">
                        <?php if (!empty($upcoming_meetings_data)): ?>
                            <?php foreach ($upcoming_meetings_data as $meeting): ?>
                            <div class="meeting-item">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-calendar-alt fa-lg text-info"></i>
                                    </div>
                                   <div class="flex-grow-1">
    <h6 class="mb-1"><?php echo htmlspecialchars($meeting['title']); ?></h6>
    <small class="text-muted">
        <?php
        $meetingDate = $meeting['meeting_date'] ?? null;
        $meetingTime = $meeting['meeting_time'] ?? null;

        if ($meetingDate && $meetingTime) {
            echo date('M j, Y g:i A', strtotime($meetingDate . ' ' . $meetingTime));
        } elseif ($meetingDate) {
            echo date('M j, Y', strtotime($meetingDate));
        } else {
            echo "Date not set";
        }
        ?>
    </small>
</div>

                                    <?php if (!empty($meeting['meeting_link'])): ?>
                                    <div>
                                        <a href="<?php echo htmlspecialchars($meeting['meeting_link']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h6>No upcoming meetings</h6>
                                <p class="text-muted mb-0">Your schedule is clear!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="content-card">
                <div class="content-card-header">
                    <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="content-card-body">
                    <div class="quick-actions">
                        <a href="lessons.php" class="quick-action-btn">
                            <i class="fas fa-book-open fa-2x mb-2 d-block"></i>
                            Browse Lessons
                        </a>
                        <a href="assignments.php" class="quick-action-btn">
                            <i class="fas fa-tasks fa-2x mb-2 d-block"></i>
                            View Assignments
                        </a>
                        <a href="quizzes.php" class="quick-action-btn">
                            <i class="fas fa-question-circle fa-2x mb-2 d-block"></i>
                            Take Quizzes
                        </a>
                        <a href="profile.php" class="quick-action-btn">
                            <i class="fas fa-user fa-2x mb-2 d-block"></i>
                            Update Profile
                        </a>
                    </div>
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
        });

        // Dynamic greeting based on time
        function updateGreeting() {
            const hour = new Date().getHours();
            const greetingIcon = document.querySelector('.welcome-header i');
            
            if (hour < 12) {
                greetingIcon.className = 'fas fa-sun me-3 text-warning';
            } else if (hour < 17) {
                greetingIcon.className = 'fas fa-sun me-3 text-info';
            } else {
                greetingIcon.className = 'fas fa-moon me-3 text-secondary';
            }
        }

        // Update greeting every minute
        setInterval(updateGreeting, 60000);
        updateGreeting();

        // Enhanced hover effects
        document.querySelectorAll('.stat-card, .content-card, .quick-action-btn').forEach(card => {
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