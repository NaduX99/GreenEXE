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

// Get current date and period filters
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$period = isset($_GET['period']) ? $_GET['period'] : 'all_time';

// Build date condition based on period
switch($period) {
    case 'today':
        $date_condition = "AND DATE(qa.completed_at) = '$selected_date'";
        $lesson_date_condition = "AND DATE(lc.completed_at) = '$selected_date'";
        break;
    case 'week':
        $date_condition = "AND qa.completed_at >= DATE_SUB('$selected_date', INTERVAL 7 DAY)";
        $lesson_date_condition = "AND lc.completed_at >= DATE_SUB('$selected_date', INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "AND qa.completed_at >= DATE_SUB('$selected_date', INTERVAL 30 DAY)";
        $lesson_date_condition = "AND lc.completed_at >= DATE_SUB('$selected_date', INTERVAL 30 DAY)";
        break;
    case 'all_time':
    default:
        $date_condition = "";
        $lesson_date_condition = "";
        break;
}

// Get leaderboard data - calculate rankings in real-time
$leaderboard = $conn->query("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.created_at as joined_date,
        COALESCE(quiz_stats.avg_score, 0) as avg_score,
        COALESCE(quiz_stats.quiz_count, 0) as quiz_completed,
        COALESCE(quiz_stats.total_attempts, 0) as total_quiz_attempts,
        COALESCE(lesson_stats.lesson_count, 0) as lesson_completed,
        (
            (COALESCE(quiz_stats.avg_score, 0) * 0.6) + 
            (COALESCE(quiz_stats.quiz_count, 0) * 5) + 
            (COALESCE(lesson_stats.lesson_count, 0) * 2)
        ) as total_points
    FROM users u
    LEFT JOIN (
        SELECT 
            user_id,
            AVG(score) as avg_score,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as quiz_count,
            COUNT(*) as total_attempts
        FROM quiz_attempts qa
        WHERE 1=1 $date_condition
        GROUP BY user_id
    ) quiz_stats ON u.id = quiz_stats.user_id
    LEFT JOIN (
        SELECT 
            user_id,
            COUNT(*) as lesson_count
        FROM lesson_completions lc
        WHERE 1=1 $lesson_date_condition
        GROUP BY user_id
    ) lesson_stats ON u.id = lesson_stats.user_id
    WHERE u.role = 'student'
    ORDER BY total_points DESC, avg_score DESC, quiz_completed DESC
");

// Get leaderboard statistics
$leaderboard_stats = $conn->query("SELECT 
    COUNT(DISTINCT u.id) as active_students,
    COALESCE(SUM(
        (COALESCE(quiz_stats.avg_score, 0) * 0.6) + 
        (COALESCE(quiz_stats.quiz_count, 0) * 5) + 
        (COALESCE(lesson_stats.lesson_count, 0) * 2)
    ), 0) as total_points_distributed,
    COALESCE(SUM(quiz_stats.quiz_count), 0) as total_quizzes_completed,
    COALESCE(SUM(lesson_stats.lesson_count), 0) as total_lessons_completed
    FROM users u
    LEFT JOIN (
        SELECT 
            user_id,
            AVG(score) as avg_score,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as quiz_count
        FROM quiz_attempts qa
        WHERE 1=1 $date_condition
        GROUP BY user_id
    ) quiz_stats ON u.id = quiz_stats.user_id
    LEFT JOIN (
        SELECT 
            user_id,
            COUNT(*) as lesson_count
        FROM lesson_completions lc
        WHERE 1=1 $lesson_date_condition
        GROUP BY user_id
    ) lesson_stats ON u.id = lesson_stats.user_id
    WHERE u.role = 'student'
")->fetch_assoc();

// Get top performers by category
$top_quiz_performer = $conn->query("
    SELECT u.name, AVG(qa.score) as avg_score, COUNT(qa.id) as quiz_count
    FROM users u
    JOIN quiz_attempts qa ON u.id = qa.user_id
    WHERE qa.status = 'completed' AND u.role = 'student'
    GROUP BY u.id, u.name
    ORDER BY avg_score DESC, quiz_count DESC
    LIMIT 1
")->fetch_assoc();

$most_active_student = $conn->query("
    SELECT u.name, COUNT(DISTINCT DATE(qa.started_at)) as active_days
    FROM users u
    JOIN quiz_attempts qa ON u.id = qa.user_id
    WHERE qa.status = 'completed' AND u.role = 'student'
    GROUP BY u.id, u.name
    ORDER BY active_days DESC
    LIMIT 1
")->fetch_assoc();

// Recent achievements
$recent_achievements = $conn->query("
    SELECT 
        u.name,
        qa.score,
        q.title as quiz_title,
        s.name as subject_name,
        qa.completed_at
    FROM quiz_attempts qa
    JOIN users u ON qa.user_id = u.id
    JOIN quizzes q ON qa.quiz_id = q.id
    LEFT JOIN subjects s ON q.subject_id = s.id
    WHERE qa.status = 'completed' AND qa.score >= 80
    ORDER BY qa.completed_at DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Admin Panel</title>
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
        
        .leaderboard-item { padding: 15px; margin-bottom: 10px; border-radius: 10px; transition: all 0.3s ease; }
        .leaderboard-item:hover { transform: translateX(5px); }
        .leaderboard-item.rank-1 { background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%); color: #333; }
        .leaderboard-item.rank-2 { background: linear-gradient(135deg, #c0c0c0 0%, #e2e2e2 100%); color: #333; }
        .leaderboard-item.rank-3 { background: linear-gradient(135deg, #cd7f32 0%, #daa561 100%); color: white; }
        .leaderboard-item.rank-other { background: #f8f9fa; border: 1px solid #dee2e6; }
        
        .rank-badge { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem; }
        .rank-1 .rank-badge { background: #fff; color: #ffd700; border: 3px solid #ffd700; }
        .rank-2 .rank-badge { background: #fff; color: #c0c0c0; border: 3px solid #c0c0c0; }
        .rank-3 .rank-badge { background: #fff; color: #cd7f32; border: 3px solid #cd7f32; }
        .rank-other .rank-badge { background: #007bff; color: white; }
        
        .student-avatar { width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        .stat-card { text-align: center; padding: 20px; }
        .stat-number { font-size: 1.8rem; font-weight: bold; }
        .achievement-item { padding: 12px; border-left: 4px solid #28a745; margin-bottom: 8px; background: #f8f9fa; border-radius: 0 8px 8px 0; }
        .crown-icon { color: #ffd700; }
        .medal-icon { color: #c0c0c0; }
        .bronze-icon { color: #cd7f32; }
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
                    <h1 class="h2"><i class="fas fa-trophy me-2 text-warning"></i>Student Leaderboard</h1>
                    <div class="btn-toolbar">
                        <button class="btn btn-outline-success" onclick="exportLeaderboard()">
                            <i class="fas fa-download me-2"></i>Export Rankings
                        </button>
                        <button class="btn btn-outline-secondary ms-2" onclick="location.reload()">
                            <i class="fas fa-sync me-2"></i>Refresh
                        </button>
                    </div>
                </div>

                <?php echo $message; ?>

                <!-- Period Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row align-items-center">
                            <div class="col-md-4">
                                <label class="form-label">Time Period</label>
                                <select name="period" class="form-select" onchange="this.form.submit()">
                                    <option value="all_time" <?php echo $period == 'all_time' ? 'selected' : ''; ?>>All Time</option>
                                    <option value="today" <?php echo $period == 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="week" <?php echo $period == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Reference Date</label>
                                <input type="date" name="date" class="form-control" value="<?php echo $selected_date; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="text-muted">
                                    <small>
                                        <i class="fas fa-info-circle me-1"></i>
                                        Showing <?php echo ucfirst(str_replace('_', ' ', $period)); ?> rankings
                                    </small>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Leaderboard Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body stat-card">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $leaderboard_stats['active_students'] ?? 0; ?></div>
                                <small>Active Students</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body stat-card">
                                <i class="fas fa-star fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo number_format($leaderboard_stats['total_points_distributed'] ?? 0); ?></div>
                                <small>Total Points</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body stat-card">
                                <i class="fas fa-question-circle fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $leaderboard_stats['total_quizzes_completed'] ?? 0; ?></div>
                                <small>Quizzes Completed</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body stat-card">
                                <i class="fas fa-book fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $leaderboard_stats['total_lessons_completed'] ?? 0; ?></div>
                                <small>Lessons Completed</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Main Leaderboard -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-trophy me-2"></i>Student Rankings (<?php echo $leaderboard->num_rows; ?> Students)</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($leaderboard->num_rows > 0): ?>
                                    <?php $current_rank = 1; ?>
                                    <?php while ($student = $leaderboard->fetch_assoc()): ?>
                                        <?php 
                                        $rank_class = $current_rank <= 3 ? 'rank-' . $current_rank : 'rank-other';
                                        ?>
                                        <div class="leaderboard-item <?php echo $rank_class; ?>">
                                            <div class="d-flex align-items-center">
                                                <!-- Rank Badge -->
                                                <div class="rank-badge me-3">
                                                    <?php if ($current_rank == 1): ?>
                                                    <i class="fas fa-crown crown-icon"></i>
                                                    <?php elseif ($current_rank == 2): ?>
                                                    <i class="fas fa-medal medal-icon"></i>
                                                    <?php elseif ($current_rank == 3): ?>
                                                    <i class="fas fa-medal bronze-icon"></i>
                                                    <?php else: ?>
                                                    <?php echo $current_rank; ?>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Student Info -->
                                                <div class="student-avatar me-3">
                                                    <?php echo strtoupper(substr($student['name'], 0, 2)); ?>
                                                </div>

                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($student['name']); ?></h6>
                                                    <div class="small text-muted">
                                                        <?php echo htmlspecialchars($student['email']); ?>
                                                    </div>
                                                    <div class="small">
                                                        <i class="fas fa-question-circle me-1"></i><?php echo $student['quiz_completed']; ?> quizzes |
                                                        <i class="fas fa-book me-1"></i><?php echo $student['lesson_completed']; ?> lessons |
                                                        <i class="fas fa-calendar me-1"></i>Joined <?php echo date('M Y', strtotime($student['joined_date'])); ?>
                                                    </div>
                                                </div>

                                                <!-- Points & Stats -->
                                                <div class="text-end">
                                                    <div class="h5 mb-1 text-primary">
                                                        <i class="fas fa-star text-warning me-1"></i>
                                                        <?php echo number_format($student['total_points']); ?>
                                                    </div>
                                                    <div class="small">
                                                        <div>Avg Score: <strong><?php echo number_format($student['avg_score'], 1); ?>%</strong></div>
                                                        <div>Total Attempts: <strong><?php echo $student['total_quiz_attempts']; ?></strong></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php $current_rank++; ?>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-trophy fa-3x mb-3"></i>
                                        <h5>No Student Activity</h5>
                                        <p>No student performance data found for the selected period.</p>
                                        <a href="users.php" class="btn btn-primary">
                                            <i class="fas fa-users me-2"></i>Manage Students
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Info -->
                    <div class="col-md-4">
                        <!-- Top Performers -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-star me-2"></i>Hall of Fame</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($top_quiz_performer): ?>
                                <div class="mb-3">
                                    <h6 class="text-primary"><i class="fas fa-medal me-2"></i>Quiz Champion</h6>
                                    <div><strong><?php echo htmlspecialchars($top_quiz_performer['name']); ?></strong></div>
                                    <div class="small text-muted">
                                        <?php echo number_format($top_quiz_performer['avg_score'], 1); ?>% average score | 
                                        <?php echo $top_quiz_performer['quiz_count']; ?> quizzes completed
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($most_active_student): ?>
                                <div class="mb-3">
                                    <h6 class="text-success"><i class="fas fa-fire me-2"></i>Most Active Student</h6>
                                    <div><strong><?php echo htmlspecialchars($most_active_student['name']); ?></strong></div>
                                    <div class="small text-muted">
                                        Active for <?php echo $most_active_student['active_days']; ?> different days
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="mt-4">
                                    <h6 class="text-info"><i class="fas fa-calculator me-2"></i>Scoring System</h6>
                                    <div class="small">
                                        <div class="mb-1">• <strong>Quiz Score:</strong> Average × 0.6</div>
                                        <div class="mb-1">• <strong>Quiz Completion:</strong> +5 points each</div>
                                        <div class="mb-1">• <strong>Lesson Completion:</strong> +2 points each</div>
                                        <div class="text-muted mt-2">
                                            <small>Rankings update in real-time based on student activity.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Achievements -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-trophy me-2"></i>Recent High Scores</h5>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php if ($recent_achievements->num_rows > 0): ?>
                                    <?php while ($achievement = $recent_achievements->fetch_assoc()): ?>
                                    <div class="achievement-item">
                                        <div class="d-flex align-items-center">
                                            <div class="me-2">
                                                <i class="fas fa-star text-warning"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold small"><?php echo htmlspecialchars($achievement['name']); ?></div>
                                                <div class="small text-muted">
                                                    <?php echo number_format($achievement['score'], 1); ?>% in <?php echo htmlspecialchars($achievement['quiz_title']); ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars($achievement['subject_name'] ?? 'General'); ?> | 
                                                    <?php echo date('M j, g:i A', strtotime($achievement['completed_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-trophy fa-2x mb-2"></i>
                                        <p>No high scores (80%+) yet</p>
                                        <small>Encourage students to take quizzes!</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportLeaderboard() {
            const leaderboardData = {
                generated_at: new Date().toISOString(),
                period: '<?php echo $period; ?>',
                date: '<?php echo $selected_date; ?>',
                stats: <?php echo json_encode($leaderboard_stats); ?>
            };
            
            const dataStr = JSON.stringify(leaderboardData, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'leaderboard_' + '<?php echo $period; ?>' + '_' + new Date().toISOString().split('T')[0] + '.json';
            link.click();
            URL.revokeObjectURL(url);
        }

        // Add hover effects
        document.querySelectorAll('.leaderboard-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.boxShadow = '';
            });
        });
    </script>
</body>
</html>
