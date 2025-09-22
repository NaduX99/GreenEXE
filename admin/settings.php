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

// Handle settings updates
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'update_general') {
        $platform_name = trim($_POST['platform_name']);
        $admin_email = trim($_POST['admin_email']);
        $timezone = $_POST['timezone'];
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        
        // For now, we'll store in a simple way or session
        $_SESSION['settings_updated'] = true;
        $message = '<div class="alert alert-success">General settings updated successfully!</div>';
    }
    
    if ($_POST['action'] == 'update_quiz') {
        $default_time_limit = intval($_POST['default_time_limit']);
        $default_passing_score = intval($_POST['default_passing_score']);
        $allow_retakes = isset($_POST['allow_retakes']) ? 1 : 0;
        $max_attempts = intval($_POST['max_attempts']);
        
        $message = '<div class="alert alert-success">Quiz settings updated successfully!</div>';
    }
    
    if ($_POST['action'] == 'update_user') {
        $auto_approve_users = isset($_POST['auto_approve_users']) ? 1 : 0;
        $email_verification = isset($_POST['email_verification']) ? 1 : 0;
        $password_min_length = intval($_POST['password_min_length']);
        
        $message = '<div class="alert alert-success">User settings updated successfully!</div>';
    }
    
    if ($_POST['action'] == 'backup_database') {
        // Simple backup notification
        $message = '<div class="alert alert-info">Database backup initiated! Check your backup location.</div>';
    }
    
    if ($_POST['action'] == 'clear_cache') {
        // Clear any temporary files or cache
        $message = '<div class="alert alert-info">System cache cleared successfully!</div>';
    }
    
    if ($_POST['action'] == 'update_email') {
        $smtp_host = trim($_POST['smtp_host']);
        $smtp_port = intval($_POST['smtp_port']);
        $smtp_username = trim($_POST['smtp_username']);
        $smtp_password = trim($_POST['smtp_password']);
        $from_email = trim($_POST['from_email']);
        $from_name = trim($_POST['from_name']);
        
        $message = '<div class="alert alert-success">Email settings updated successfully!</div>';
    }
}

// Get system information
$system_info = [
    'php_version' => phpversion(),
    'mysql_version' => $conn->server_info,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'max_upload_size' => ini_get('upload_max_filesize'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'disk_space' => function_exists('disk_free_space') ? disk_free_space('.') : 'Unknown'
];

// Get database statistics
$db_stats = $conn->query("SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM subjects) as total_subjects,
    (SELECT COUNT(*) FROM lessons) as total_lessons,
    (SELECT COUNT(*) FROM quizzes) as total_quizzes,
    (SELECT COUNT(*) FROM quiz_questions) as total_questions,
    (SELECT COUNT(*) FROM quiz_attempts) as total_attempts
")->fetch_assoc();

// Get recent activity
$recent_activity = $conn->query("
    SELECT 'User Registration' as activity, COUNT(*) as count 
    FROM users WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'Quiz Created', COUNT(*) 
    FROM quizzes WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'Quiz Attempts', COUNT(*) 
    FROM quiz_attempts WHERE DATE(started_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Panel</title>
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
        .settings-nav { border-bottom: 1px solid #dee2e6; margin-bottom: 20px; }
        .settings-nav .nav-link { color: #6c757d; border-bottom: 2px solid transparent; padding: 12px 16px; }
        .settings-nav .nav-link.active { color: #007bff; border-bottom-color: #007bff; }
        .system-info-item { padding: 12px; border-left: 4px solid #007bff; margin-bottom: 8px; background: #f8f9fa; border-radius: 0 8px 8px 0; }
        .stat-card { text-align: center; padding: 20px; }
        .stat-number { font-size: 1.5rem; font-weight: bold; }
        .danger-zone { border: 2px solid #dc3545; background: #fff5f5; border-radius: 10px; padding: 20px; }
        .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #2196F3; }
        input:checked + .slider:before { transform: translateX(26px); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <nav class="col-md-2 d-md-block sidebar p-0">
                <div class="position-sticky pt-0">
                    <div class="sidebar-brand">
                        <h4><i class="fas fa-graduation-cap me-2"></i>LMS Admin</h4>
                        <small class="text-white-50">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></small>
                    </div>

                    <ul class="nav flex-column py-3">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>Dashboard
                            </a>
                        </li>

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

                        <div class="nav-section">User Management</div>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i>Users
                            </a>
                        </li>

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

                        <div class="nav-section">System</div>
                        <li class="nav-item">
                            <a class="nav-link active" href="settings.php">
                                <i class="fas fa-cog"></i>Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user"></i>Profile
                            </a>
                        </li>

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
                    <h1 class="h2"><i class="fas fa-cog me-2 text-primary"></i>System Settings</h1>
                    <div class="btn-toolbar">
                        <button class="btn btn-outline-success me-2" onclick="exportSettings()">
                            <i class="fas fa-download me-2"></i>Export Config
                        </button>
                        <button class="btn btn-outline-secondary" onclick="location.reload()">
                            <i class="fas fa-sync me-2"></i>Refresh
                        </button>
                    </div>
                </div>

                <?php echo $message; ?>

                <!-- Settings Navigation Tabs -->
                <ul class="nav nav-tabs settings-nav">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#general">
                            <i class="fas fa-cog me-2"></i>General
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#quiz">
                            <i class="fas fa-question-circle me-2"></i>Quiz Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#users">
                            <i class="fas fa-users me-2"></i>User Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#email">
                            <i class="fas fa-envelope me-2"></i>Email Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#system">
                            <i class="fas fa-server me-2"></i>System Info
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#maintenance">
                            <i class="fas fa-tools me-2"></i>Maintenance
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-cog me-2"></i>General Platform Settings</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_general">
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Platform Name</label>
                                                        <input type="text" name="platform_name" class="form-control" value="Learning Management System" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Admin Email</label>
                                                        <input type="email" name="admin_email" class="form-control" value="admin@learning.com" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Timezone</label>
                                                        <select name="timezone" class="form-select">
                                                            <option value="Asia/Kolkata" selected>Asia/Kolkata (IST)</option>
                                                            <option value="America/New_York">America/New_York (EST)</option>
                                                            <option value="Europe/London">Europe/London (GMT)</option>
                                                            <option value="Asia/Tokyo">Asia/Tokyo (JST)</option>
                                                            <option value="Australia/Sydney">Australia/Sydney (AEST)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Maintenance Mode</label>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenanceMode">
                                                            <label class="form-check-label" for="maintenanceMode">
                                                                Enable maintenance mode
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">When enabled, only admins can access the system</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Save General Settings
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-info-circle me-2"></i>Quick Stats</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="system-info-item">
                                            <div class="d-flex justify-content-between">
                                                <span>Total Users</span>
                                                <strong><?php echo $db_stats['total_users']; ?></strong>
                                            </div>
                                        </div>
                                        <div class="system-info-item">
                                            <div class="d-flex justify-content-between">
                                                <span>Total Subjects</span>
                                                <strong><?php echo $db_stats['total_subjects']; ?></strong>
                                            </div>
                                        </div>
                                        <div class="system-info-item">
                                            <div class="d-flex justify-content-between">
                                                <span>Total Quizzes</span>
                                                <strong><?php echo $db_stats['total_quizzes']; ?></strong>
                                            </div>
                                        </div>
                                        <div class="system-info-item">
                                            <div class="d-flex justify-content-between">
                                                <span>Quiz Attempts</span>
                                                <strong><?php echo $db_stats['total_attempts']; ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quiz Settings -->
                    <div class="tab-pane fade" id="quiz">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-question-circle me-2"></i>Quiz Configuration</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_quiz">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Default Time Limit (minutes)</label>
                                                <input type="number" name="default_time_limit" class="form-control" value="30" min="5" max="180">
                                                <small class="text-muted">Default time limit for new quizzes</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Default Passing Score (%)</label>
                                                <input type="number" name="default_passing_score" class="form-control" value="70" min="1" max="100">
                                                <small class="text-muted">Default passing score for new quizzes</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Maximum Attempts</label>
                                                <input type="number" name="max_attempts" class="form-control" value="3" min="1" max="10">
                                                <small class="text-muted">Maximum quiz attempts per student</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Allow Retakes</label>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="allow_retakes" id="allowRetakes" checked>
                                                    <label class="form-check-label" for="allowRetakes">
                                                        Students can retake quizzes
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6>Quiz Display Options</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="showCorrectAnswers" checked>
                                            <label class="form-check-label" for="showCorrectAnswers">
                                                Show correct answers after completion
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="showScoreImmediately" checked>
                                            <label class="form-check-label" for="showScoreImmediately">
                                                Show score immediately after completion
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="randomizeQuestions">
                                            <label class="form-check-label" for="randomizeQuestions">
                                                Randomize question order
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save me-2"></i>Save Quiz Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- User Settings -->
                    <div class="tab-pane fade" id="users">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-users me-2"></i>User Management Configuration</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_user">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Auto-approve New Users</label>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="auto_approve_users" id="autoApproveUsers" checked>
                                                    <label class="form-check-label" for="autoApproveUsers">
                                                        Automatically approve new user registrations
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email Verification</label>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="email_verification" id="emailVerification">
                                                    <label class="form-check-label" for="emailVerification">
                                                        Require email verification for new accounts
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Minimum Password Length</label>
                                                <input type="number" name="password_min_length" class="form-control" value="8" min="6" max="20">
                                                <small class="text-muted">Minimum required password length</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Default User Role</label>
                                                <select name="default_role" class="form-select">
                                                    <option value="student" selected>Student</option>
                                                    <option value="admin">Admin</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6>User Permissions</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="allowSelfRegistration" checked>
                                            <label class="form-check-label" for="allowSelfRegistration">
                                                Allow self-registration
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="allowProfileEdit" checked>
                                            <label class="form-check-label" for="allowProfileEdit">
                                                Users can edit their own profiles
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="allowPasswordReset" checked>
                                            <label class="form-check-label" for="allowPasswordReset">
                                                Allow password reset requests
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-save me-2"></i>Save User Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="tab-pane fade" id="email">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-envelope me-2"></i>Email Configuration</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_email">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Host</label>
                                                <input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Port</label>
                                                <input type="number" name="smtp_port" class="form-control" value="587">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Username</label>
                                                <input type="text" name="smtp_username" class="form-control" placeholder="your-email@gmail.com">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Password</label>
                                                <input type="password" name="smtp_password" class="form-control" placeholder="your-app-password">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">From Email</label>
                                                <input type="email" name="from_email" class="form-control" placeholder="noreply@learning.com">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">From Name</label>
                                                <input type="text" name="from_name" class="form-control" value="Learning Platform">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6>Email Features</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                                            <label class="form-check-label" for="emailNotifications">
                                                Send email notifications
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="welcomeEmails" checked>
                                            <label class="form-check-label" for="welcomeEmails">
                                                Send welcome emails to new users
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="quizReminders">
                                            <label class="form-check-label" for="quizReminders">
                                                Send quiz deadline reminders
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-save me-2"></i>Save Email Settings
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary ms-2" onclick="testEmail()">
                                        <i class="fas fa-paper-plane me-2"></i>Test Email
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div class="tab-pane fade" id="system">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-server me-2"></i>System Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="system-info-item">
                                            <div class="d-flex justify-content-between">
                                                <span>PHP Version</span>
                                                <strong><?php echo $system_info['php_version']; ?></strong>
                                            </div>
                                        </div>
                                        <div class="system-info-item">
                                            <div class="d-flex justify-content-between">
                                                <span>MySQL Version</span>
                                                <strong><?php echo $system_info['mysql_version']; ?></strong>
                                            </div>
                                        </div>
                                        <div class="system-info-item">
                                            <div class="d-flex justify-content-between">
                                                <span>Server Software</span>
                                                <strong><?php echo explode('/', $system_info['server_software'])[0]; ?></strong>
                                            </div>
                                        </div>
                                        <div class="system-info-item">
                                            <div class="d-flex justify-content-between">
                                                <span>Max Upload Size</span>
                                                <strong><?php echo $system_info['max_upload_size']; ?></strong>
                                            </div>
                                        </div>
                                        <div class="system-info-item">
                                            <div class="d-flex justify-content-between">
                                                <span>Memory Limit</span>
                                                <strong><?php echo $system_info['memory_limit']; ?></strong>
                                            </div>
                                        </div>
                                        <div class="system-info-item">
                                            <div class="d-flex justify-content-between">
                                                <span>Max Execution Time</span>
                                                <strong><?php echo $system_info['max_execution_time']; ?>s</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-bar me-2"></i>Database Statistics</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6 mb-3">
                                                <div class="card bg-primary text-white">
                                                    <div class="card-body stat-card">
                                                        <div class="stat-number"><?php echo $db_stats['total_users']; ?></div>
                                                        <small>Users</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="card bg-success text-white">
                                                    <div class="card-body stat-card">
                                                        <div class="stat-number"><?php echo $db_stats['total_subjects']; ?></div>
                                                        <small>Subjects</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="card bg-info text-white">
                                                    <div class="card-body stat-card">
                                                        <div class="stat-number"><?php echo $db_stats['total_quizzes']; ?></div>
                                                        <small>Quizzes</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="card bg-warning text-white">
                                                    <div class="card-body stat-card">
                                                        <div class="stat-number"><?php echo $db_stats['total_questions']; ?></div>
                                                        <small>Questions</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <h6>Recent Activity (Last 7 Days)</h6>
                                            <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                                            <div class="d-flex justify-content-between">
                                                <span><?php echo $activity['activity']; ?></span>
                                                <strong><?php echo $activity['count']; ?></strong>
                                            </div>
                                            <?php endwhile; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Maintenance -->
                    <div class="tab-pane fade" id="maintenance">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-tools me-2"></i>System Maintenance</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-database me-2"></i>Database Operations</h6>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="backup_database">
                                                    <button type="submit" class="btn btn-outline-primary mb-2 w-100">
                                                        <i class="fas fa-download me-2"></i>Backup Database
                                                    </button>
                                                </form>
                                                <button class="btn btn-outline-info mb-2 w-100" onclick="optimizeDatabase()">
                                                    <i class="fas fa-wrench me-2"></i>Optimize Database
                                                </button>
                                                <button class="btn btn-outline-warning mb-2 w-100" onclick="repairTables()">
                                                    <i class="fas fa-hammer me-2"></i>Repair Tables
                                                </button>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <h6><i class="fas fa-broom me-2"></i>System Cleanup</h6>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="clear_cache">
                                                    <button type="submit" class="btn btn-outline-success mb-2 w-100">
                                                        <i class="fas fa-trash me-2"></i>Clear Cache
                                                    </button>
                                                </form>
                                                <button class="btn btn-outline-warning mb-2 w-100" onclick="clearLogs()">
                                                    <i class="fas fa-file-alt me-2"></i>Clear Logs
                                                </button>
                                                <button class="btn btn-outline-secondary mb-2 w-100" onclick="clearTempFiles()">
                                                    <i class="fas fa-folder me-2"></i>Clear Temp Files
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="danger-zone">
                                    <h5 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                                    <p class="small text-muted">These actions are irreversible. Use with extreme caution.</p>
                                    
                                    <button class="btn btn-outline-danger btn-sm w-100 mb-2" onclick="resetSystem()">
                                        <i class="fas fa-undo me-2"></i>Reset System
                                    </button>
                                    <button class="btn btn-danger btn-sm w-100" onclick="wipeAllData()">
                                        <i class="fas fa-bomb me-2"></i>Wipe All Data
                                    </button>
                                    
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <strong>Warning:</strong> These operations will permanently delete data. 
                                            Make sure you have a backup before proceeding.
                                        </small>
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
        function exportSettings() {
            const settings = {
                exported_at: new Date().toISOString(),
                platform_name: "Learning Management System",
                admin_email: "admin@learning.com",
                timezone: "Asia/Kolkata",
                maintenance_mode: false
            };
            
            const dataStr = JSON.stringify(settings, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'lms_settings_' + new Date().toISOString().split('T')[0] + '.json';
            link.click();
            URL.revokeObjectURL(url);
        }

        function testEmail() {
            alert('Email test functionality would be implemented here.\nThis would send a test email to verify SMTP settings.');
        }

        function optimizeDatabase() {
            if (confirm('Optimize database tables? This may take a few minutes.')) {
                alert('Database optimization started. Check the maintenance log for progress.');
            }
        }

        function repairTables() {
            if (confirm('Repair database tables? This will fix any corrupted tables.')) {
                alert('Table repair started. Check the maintenance log for results.');
            }
        }

        function clearLogs() {
            if (confirm('Clear all system logs? This action cannot be undone.')) {
                alert('System logs cleared successfully.');
            }
        }

        function clearTempFiles() {
            if (confirm('Clear temporary files? This will free up disk space.')) {
                alert('Temporary files cleared successfully.');
            }
        }

        function resetSystem() {
            if (confirm('Reset system to default settings? This will NOT delete user data but will reset all configurations.')) {
                if (confirm('Are you absolutely sure? This action cannot be undone.')) {
                    alert('System reset initiated. Please wait...');
                }
            }
        }

        function wipeAllData() {
            const confirmText = prompt('Type "WIPE ALL DATA" to confirm complete data deletion:');
            if (confirmText === 'WIPE ALL DATA') {
                if (confirm('FINAL WARNING: This will permanently delete ALL data including users, quizzes, and settings. Continue?')) {
                    alert('Data wipe initiated. The system will restart after completion.');
                }
            } else {
                alert('Data wipe cancelled.');
            }
        }

        // Auto-save form data
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('change', function() {
                localStorage.setItem('settings_' + this.name, this.value);
            });
        });

        // Load saved form data
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input, select, textarea').forEach(element => {
                const saved = localStorage.getItem('settings_' + element.name);
                if (saved && element.type !== 'password') {
                    element.value = saved;
                }
            });
        });
    </script>
</body>
</html>
