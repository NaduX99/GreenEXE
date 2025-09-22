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

// Get statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'total_subjects' => $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'],
    'total_lessons' => $conn->query("SELECT COUNT(*) as count FROM lessons")->fetch_assoc()['count'],
    'total_quizzes' => $conn->query("SELECT COUNT(*) as count FROM quizzes")->fetch_assoc()['count'],
    'total_questions' => $conn->query("SELECT COUNT(*) as count FROM quiz_questions")->fetch_assoc()['count'],
    'online_users' => $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'online'")->fetch_assoc()['count'],
    'pending_quizzes' => $conn->query("SELECT COUNT(*) as count FROM quizzes WHERE status = 'pending'")->fetch_assoc()['count'],
    'today_attempts' => $conn->query("SELECT COUNT(*) as count FROM quiz_attempts WHERE DATE(started_at) = CURDATE()")->fetch_assoc()['count']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Learning Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.9); padding: 12px 20px; margin: 2px 8px; border-radius: 8px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255, 255, 255, 0.2); color: white; transform: translateX(5px); }
        .sidebar .nav-link i { width: 20px; text-align: center; margin-right: 10px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); transition: all 0.3s ease; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15); }
        .stat-card { text-align: center; padding: 30px 20px; }
        .stat-number { font-size: 2.5rem; font-weight: bold; margin-bottom: 10px; }
        .nav-section { color: rgba(255, 255, 255, 0.6); font-size: 11px; font-weight: 600; text-transform: uppercase; padding: 15px 20px 5px; margin-bottom: 0; }
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-brand h4 { color: white; margin: 0; }
        .quick-action { border-radius: 12px; transition: all 0.3s ease; }
        .quick-action:hover { transform: translateY(-2px); }
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
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard Overview</h1>
                    <div class="btn-toolbar">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="fas fa-sync me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body stat-card">
                                <i class="fas fa-users fa-2x mb-3 opacity-75"></i>
                                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                                <div>Total Users</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-success">
                            <div class="card-body stat-card">
                                <i class="fas fa-graduation-cap fa-2x mb-3 opacity-75"></i>
                                <div class="stat-number"><?php echo $stats['total_subjects']; ?></div>
                                <div>Subjects</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-info">
                            <div class="card-body stat-card">
                                <i class="fas fa-book fa-2x mb-3 opacity-75"></i>
                                <div class="stat-number"><?php echo $stats['total_lessons']; ?></div>
                                <div>Lessons</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body stat-card">
                                <i class="fas fa-question-circle fa-2x mb-3 opacity-75"></i>
                                <div class="stat-number"><?php echo $stats['total_quizzes']; ?></div>
                                <div>Quizzes</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Secondary Stats -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-secondary">
                            <div class="card-body stat-card">
                                <i class="fas fa-list fa-2x mb-3 opacity-75"></i>
                                <div class="stat-number"><?php echo $stats['total_questions']; ?></div>
                                <div>Quiz Questions</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-dark">
                            <div class="card-body stat-card">
                                <i class="fas fa-circle fa-2x mb-3 opacity-75"></i>
                                <div class="stat-number"><?php echo $stats['online_users']; ?></div>
                                <div>Online Users</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body stat-card">
                                <i class="fas fa-clock fa-2x mb-3 opacity-75"></i>
                                <div class="stat-number"><?php echo $stats['pending_quizzes']; ?></div>
                                <div>Pending Quizzes</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-white bg-success">
                            <div class="card-body stat-card">
                                <i class="fas fa-chart-line fa-2x mb-3 opacity-75"></i>
                                <div class="stat-number"><?php echo $stats['today_attempts']; ?></div>
                                <div>Today's Attempts</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="subjects.php" class="btn btn-outline-primary quick-action">
                                        <i class="fas fa-graduation-cap me-2"></i>Manage Subjects
                                    </a>
                                    <a href="quiz_create.php" class="btn btn-outline-success quick-action">
                                        <i class="fas fa-plus-circle me-2"></i>Create New Quiz
                                    </a>
                                    <a href="lesson_add.php" class="btn btn-outline-info quick-action">
                                        <i class="fas fa-book-open me-2"></i>Add New Lesson
                                    </a>
                                    <a href="users_add.php" class="btn btn-outline-warning quick-action">
                                        <i class="fas fa-user-plus me-2"></i>Add New User
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-info-circle me-2"></i>System Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Database Connection</span>
                                        <span class="badge bg-success">Connected</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>System Status</span>
                                        <span class="badge bg-success">Running</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Last Backup</span>
                                        <span class="text-muted">Today</span>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <a href="reports.php" class="btn btn-primary">
                                        <i class="fas fa-chart-bar me-2"></i>View Full Report
                                    </a>
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
        // Auto-refresh stats every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
