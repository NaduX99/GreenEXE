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

// Date range filter
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Overall Statistics
$overall_stats = $conn->query("SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
    (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
    (SELECT COUNT(*) FROM subjects) as total_subjects,
    (SELECT COUNT(*) FROM lessons) as total_lessons,
    (SELECT COUNT(*) FROM quizzes) as total_quizzes,
    (SELECT COUNT(*) FROM quiz_questions) as total_questions,
    (SELECT COUNT(*) FROM quiz_attempts) as total_attempts,
    (SELECT COUNT(*) FROM quiz_attempts WHERE status = 'completed') as completed_attempts,
    (SELECT AVG(score) FROM quiz_attempts WHERE status = 'completed') as avg_score
")->fetch_assoc();

// Date-filtered statistics
$period_stats = $conn->query("SELECT 
    (SELECT COUNT(*) FROM users WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to') as new_users,
    (SELECT COUNT(*) FROM quiz_attempts WHERE DATE(started_at) BETWEEN '$date_from' AND '$date_to') as period_attempts,
    (SELECT COUNT(*) FROM quiz_attempts WHERE DATE(started_at) BETWEEN '$date_from' AND '$date_to' AND status = 'completed') as period_completed,
    (SELECT AVG(score) FROM quiz_attempts WHERE DATE(started_at) BETWEEN '$date_from' AND '$date_to' AND status = 'completed') as period_avg_score,
    (SELECT COUNT(*) FROM lesson_completions WHERE DATE(completed_at) BETWEEN '$date_from' AND '$date_to') as lesson_completions
")->fetch_assoc();

// Top performing students
$top_students = $conn->query("SELECT 
    u.name, u.email,
    COUNT(qa.id) as quiz_count,
    AVG(qa.score) as avg_score,
    MAX(qa.score) as best_score,
    COUNT(lc.id) as lessons_completed
    FROM users u
    LEFT JOIN quiz_attempts qa ON u.id = qa.user_id AND qa.status = 'completed'
    LEFT JOIN lesson_completions lc ON u.id = lc.user_id
    WHERE u.role = 'student'
    GROUP BY u.id, u.name, u.email
    HAVING quiz_count > 0
    ORDER BY avg_score DESC, quiz_count DESC
    LIMIT 10
");

// Quiz performance by subject
$quiz_by_subject = $conn->query("SELECT 
    s.name as subject_name,
    COUNT(DISTINCT q.id) as quiz_count,
    COUNT(qa.id) as total_attempts,
    COUNT(CASE WHEN qa.status = 'completed' THEN 1 END) as completed_attempts,
    AVG(CASE WHEN qa.status = 'completed' THEN qa.score END) as avg_score,
    MIN(CASE WHEN qa.status = 'completed' THEN qa.score END) as min_score,
    MAX(CASE WHEN qa.status = 'completed' THEN qa.score END) as max_score
    FROM subjects s
    LEFT JOIN quizzes q ON s.id = q.subject_id
    LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id
    GROUP BY s.id, s.name
    ORDER BY avg_score DESC
");

// Daily activity (last 30 days)
$daily_activity = $conn->query("SELECT 
    DATE(qa.started_at) as activity_date,
    COUNT(qa.id) as quiz_attempts,
    COUNT(CASE WHEN qa.status = 'completed' THEN 1 END) as completed_quizzes,
    AVG(CASE WHEN qa.status = 'completed' THEN qa.score END) as avg_score
    FROM quiz_attempts qa
    WHERE qa.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(qa.started_at)
    ORDER BY activity_date DESC
    LIMIT 30
");

// Most difficult questions (lowest success rate)
$difficult_questions = $conn->query("SELECT 
    qq.question_text,
    s.name as subject_name,
    q.title as quiz_title,
    COUNT(qa.id) as attempt_count,
    qq.correct_answer,
    (COUNT(qa.id) * 0.25) as estimated_correct
    FROM quiz_questions qq
    LEFT JOIN quizzes q ON qq.quiz_id = q.id
    LEFT JOIN subjects s ON q.subject_id = s.id
    LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.status = 'completed'
    GROUP BY qq.id, qq.question_text, s.name, q.title, qq.correct_answer
    HAVING attempt_count > 5
    ORDER BY estimated_correct ASC
    LIMIT 10
");

// Recent activity log
$recent_activity = $conn->query("SELECT 
    u.name as user_name,
    'Quiz Attempt' as activity_type,
    CONCAT(q.title, ' - Score: ', qa.score, '%') as activity_description,
    qa.started_at as activity_time
    FROM quiz_attempts qa
    JOIN users u ON qa.user_id = u.id
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.status = 'completed'
    ORDER BY qa.started_at DESC
    LIMIT 20
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.9); padding: 12px 20px; margin: 2px 8px; border-radius: 8px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255, 255, 255, 0.2); color: white; transform: translateX(5px); }
        .sidebar .nav-link i { width: 20px; text-align: center; margin-right: 10px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); transition: all 0.3s ease; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12); }
        .nav-section { color: rgba(255, 255, 255, 0.6); font-size: 11px; font-weight: 600; text-transform: uppercase; padding: 15px 20px 5px; margin-bottom: 0; }
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-brand h4 { color: white; margin: 0; }
        .stat-card { text-align: center; padding: 25px 15px; }
        .stat-number { font-size: 2.2rem; font-weight: bold; margin-bottom: 5px; }
        .progress-thin { height: 8px; }
        .metric-item { padding: 15px; border-left: 4px solid #007bff; margin-bottom: 10px; background: #f8f9fa; border-radius: 0 8px 8px 0; }
        .activity-item { padding: 12px 0; border-bottom: 1px solid #eee; }
        .activity-item:last-child { border-bottom: none; }
        .chart-container { height: 300px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
           <!-- Sidebar Navigation -->
            <nav class="col-md-2 d-md-block sidebar p-0">
                <div class="position-sticky pt-0">
                    <!-- Brand -->
                    <div class="sidebar-brand">
                        <h4><i class="fas fa-graduation-cap me-2"></i>LMS Admin</h4>
                        <small class="text-white-50">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></small>
                    </div>

                    <!-- Navigation Menu -->
                    <ul class="nav flex-column py-3">
                        <!-- Dashboard -->
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>Dashboard
                            </a>
                        </li>

                        <!-- Content Management -->
                        <div class="nav-section">Content Management</div>
                        <li class="nav-item">
                            <a class="nav-link" href="subjects.php">
                                <i class="fas fa-graduation-cap"></i>Subjects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="lessons.php">
                                <i class="fas fa-book"></i>Lessons
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="quizzes.php">
                                <i class="fas fa-question-circle"></i>Quizzes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="assignments.php">
                                <i class="fas fa-question-circle"></i>Assignment
                            </a>
                        </li>

                        <!-- User Management -->
                        <div class="nav-section">User Management</div>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i>Users
                            </a>
                        </li>

                        <!-- Meetings & Communication -->
                        <div class="nav-section">Communication</div>
                        <li class="nav-item">
                            <a class="nav-link" href="meetings.php">
                                <i class="fas fa-video"></i>Meetings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="chatroom.php">
                                <i class="fas fa-comments"></i>Chat Room
                            </a>
                        </li>

                        <!-- Analytics -->
                        <div class="nav-section">Analytics</div>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="leaderboard.php">
                                <i class="fas fa-trophy"></i>Leaderboard
                            </a>
                        </li>

                        <!-- System -->
                        <div class="nav-section">System</div>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i>Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user"></i>Profile
                            </a>
                        </li>

                        <!-- Logout -->
                        <div class="nav-section">Account</div>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php" onclick="return confirm('Are you sure you want to logout?')">
                                <i class="fas fa-sign-out-alt"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>


            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-chart-bar me-2 text-primary"></i>Reports & Analytics</h1>
                    <div class="btn-toolbar">
                        <button class="btn btn-outline-primary me-2" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Report
                        </button>
                        <button class="btn btn-outline-success" onclick="exportData()">
                            <i class="fas fa-download me-2"></i>Export Data
                        </button>
                    </div>
                </div>

                <!-- Date Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row align-items-center">
                            <div class="col-md-3">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Apply Filter
                                </button>
                            </div>
                            <div class="col-md-3 d-flex align-items-end justify-content-end">
                                <div class="text-muted">
                                    <small>Period: <?php echo date('M j', strtotime($date_from)) . ' - ' . date('M j, Y', strtotime($date_to)); ?></small>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Overall Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body stat-card">
                                <i class="fas fa-users fa-2x mb-2 opacity-75"></i>
                                <div class="stat-number"><?php echo number_format($overall_stats['total_students']); ?></div>
                                <div>Total Students</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body stat-card">
                                <i class="fas fa-question-circle fa-2x mb-2 opacity-75"></i>
                                <div class="stat-number"><?php echo number_format($overall_stats['total_quizzes']); ?></div>
                                <div>Total Quizzes</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body stat-card">
                                <i class="fas fa-chart-line fa-2x mb-2 opacity-75"></i>
                                <div class="stat-number"><?php echo number_format($overall_stats['total_attempts']); ?></div>
                                <div>Quiz Attempts</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body stat-card">
                                <i class="fas fa-percentage fa-2x mb-2 opacity-75"></i>
                                <div class="stat-number"><?php echo number_format($overall_stats['avg_score'] ?? 0, 1); ?>%</div>
                                <div>Average Score</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Period Statistics -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-calendar me-2"></i>Period Performance (<?php echo date('M j', strtotime($date_from)) . ' - ' . date('M j, Y', strtotime($date_to)); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="metric-item">
                                            <h4 class="text-primary"><?php echo $period_stats['new_users']; ?></h4>
                                            <small>New Users Registered</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="metric-item">
                                            <h4 class="text-success"><?php echo $period_stats['period_attempts']; ?></h4>
                                            <small>Quiz Attempts</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="metric-item">
                                            <h4 class="text-info"><?php echo $period_stats['period_completed']; ?></h4>
                                            <small>Completed Quizzes</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="metric-item">
                                            <h4 class="text-warning"><?php echo $period_stats['lesson_completions']; ?></h4>
                                            <small>Lessons Completed</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($period_stats['period_completed'] > 0): ?>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Completion Rate:</span>
                                        <strong><?php echo number_format(($period_stats['period_completed'] / $period_stats['period_attempts']) * 100, 1); ?>%</strong>
                                    </div>
                                    <div class="progress progress-thin">
                                        <div class="progress-bar" style="width: <?php echo ($period_stats['period_completed'] / $period_stats['period_attempts']) * 100; ?>%"></div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <div class="d-flex justify-content-between">
                                        <span>Average Score:</span>
                                        <strong><?php echo number_format($period_stats['period_avg_score'] ?? 0, 1); ?>%</strong>
                                    </div>
                                    <div class="progress progress-thin">
                                        <div class="progress-bar bg-success" style="width: <?php echo $period_stats['period_avg_score'] ?? 0; ?>%"></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <!-- Top Students -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5><i class="fas fa-star me-2"></i>Top Performing Students</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($top_students->num_rows > 0): ?>
                                    <?php $rank = 1; ?>
                                    <?php while ($student = $top_students->fetch_assoc()): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="me-3">
                                            <span class="badge bg-<?php echo $rank <= 3 ? 'warning' : 'secondary'; ?> rounded-pill"><?php echo $rank; ?></span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($student['name']); ?></h6>
                                            <div class="small text-muted">
                                                <?php echo $student['quiz_count']; ?> quizzes | 
                                                <?php echo $student['lessons_completed']; ?> lessons | 
                                                Best: <?php echo number_format($student['best_score'], 1); ?>%
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="h5 mb-0 text-success"><?php echo number_format($student['avg_score'], 1); ?>%</div>
                                            <small class="text-muted">Avg Score</small>
                                        </div>
                                    </div>
                                    <?php $rank++; ?>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-user-graduate fa-3x mb-3"></i>
                                        <p>No student performance data available yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Subject Performance -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5><i class="fas fa-graduation-cap me-2"></i>Performance by Subject</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($quiz_by_subject->num_rows > 0): ?>
                                    <?php while ($subject = $quiz_by_subject->fetch_assoc()): ?>
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($subject['subject_name']); ?></h6>
                                            <span class="badge bg-primary"><?php echo $subject['quiz_count']; ?> quizzes</span>
                                        </div>
                                        <div class="small text-muted mb-1">
                                            <?php echo $subject['total_attempts']; ?> attempts | 
                                            <?php echo $subject['completed_attempts']; ?> completed
                                        </div>
                                        <?php if ($subject['avg_score']): ?>
                                        <div class="progress progress-thin mb-1">
                                            <div class="progress-bar" style="width: <?php echo $subject['avg_score']; ?>%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between small">
                                            <span>Avg: <?php echo number_format($subject['avg_score'], 1); ?>%</span>
                                            <span>Range: <?php echo number_format($subject['min_score'], 1); ?>% - <?php echo number_format($subject['max_score'], 1); ?>%</span>
                                        </div>
                                        <?php else: ?>
                                        <div class="text-muted small">No completed attempts yet</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-chart-bar fa-3x mb-3"></i>
                                        <p>No subject performance data available.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Activity Chart -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-line me-2"></i>Daily Activity (Last 30 Days)</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <div class="text-center">
                                        <i class="fas fa-chart-area fa-3x text-muted mb-3"></i>
                                        <h5>Activity Chart</h5>
                                        <p class="text-muted">Daily quiz attempts and completion trends</p>
                                        <div class="row mt-4">
                                            <?php if ($daily_activity->num_rows > 0): ?>
                                                <?php 
                                                $activities = [];
                                                while ($day = $daily_activity->fetch_assoc()) {
                                                    $activities[] = $day;
                                                }
                                                $latest_day = $activities[0] ?? null;
                                                ?>
                                                <?php if ($latest_day): ?>
                                                <div class="col-md-6">
                                                    <div class="metric-item">
                                                        <h4><?php echo $latest_day['quiz_attempts']; ?></h4>
                                                        <small>Latest Day Attempts</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="metric-item">
                                                        <h4><?php echo number_format($latest_day['avg_score'] ?? 0, 1); ?>%</h4>
                                                        <small>Latest Day Avg Score</small>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5><i class="fas fa-clock me-2"></i>Recent Activity</h5>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if ($recent_activity->num_rows > 0): ?>
                                    <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                                    <div class="activity-item">
                                        <div class="d-flex align-items-start">
                                            <div class="me-2 mt-1">
                                                <i class="fas fa-user-check text-success"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold small"><?php echo htmlspecialchars($activity['user_name']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($activity['activity_description']); ?></div>
                                                <div class="small text-muted"><?php echo date('M j, g:i A', strtotime($activity['activity_time'])); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-clock fa-2x mb-3"></i>
                                        <p>No recent activity</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Summary -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-info-circle me-2"></i>System Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-primary"><?php echo $overall_stats['total_subjects']; ?></h4>
                                            <small>Subjects Created</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-success"><?php echo $overall_stats['total_lessons']; ?></h4>
                                            <small>Lessons Available</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-info"><?php echo $overall_stats['total_questions']; ?></h4>
                                            <small>Questions Created</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-warning"><?php echo number_format(($overall_stats['completed_attempts'] / max($overall_stats['total_attempts'], 1)) * 100, 1); ?>%</h4>
                                            <small>Completion Rate</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportData() {
            // Simple data export functionality
            const reportData = {
                generated_at: new Date().toISOString(),
                period: '<?php echo $date_from . " to " . $date_to; ?>',
                overall_stats: <?php echo json_encode($overall_stats); ?>,
                period_stats: <?php echo json_encode($period_stats); ?>
            };
            
            const dataStr = JSON.stringify(reportData, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'lms_report_' + new Date().toISOString().split('T')[0] + '.json';
            link.click();
            URL.revokeObjectURL(url);
        }

        // Auto-refresh every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
