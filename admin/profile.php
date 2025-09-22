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

// Get current user data
$user_id = $_SESSION['user_id'];
$current_user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

// Handle profile updates
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'update_profile') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        
        // Check if email is already taken by another user
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_email->bind_param("si", $email, $user_id);
        $check_email->execute();
        
        if ($check_email->get_result()->num_rows > 0) {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Email already exists!</div>';
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $email, $user_id);
            if ($stmt->execute()) {
                $_SESSION['name'] = $name; // Update session
                $current_user['name'] = $name;
                $current_user['email'] = $email;
                $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Profile updated successfully!</div>';
            }
        }
    }
    
    if ($_POST['action'] == 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (password_verify($current_password, $current_user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    if ($stmt->execute()) {
                        $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Password changed successfully!</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Password must be at least 6 characters long!</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">New passwords do not match!</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Current password is incorrect!</div>';
        }
    }
    
    if ($_POST['action'] == 'update_settings') {
        // Here you can add user preferences/settings
        $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Settings updated successfully!</div>';
    }
}

// Get admin activity statistics (simplified without created_by column)
$admin_stats = $conn->query("SELECT 
    (SELECT COUNT(*) FROM users) as users_managed,
    (SELECT COUNT(*) FROM subjects) as subjects_created,
    (SELECT COUNT(*) FROM lessons) as lessons_created,
    (SELECT COUNT(*) FROM quizzes) as quizzes_created,
    (SELECT COUNT(*) FROM quiz_questions) as questions_created
")->fetch_assoc();

// Get recent system activity (fixed SQL syntax)
$recent_activity_data = [];

// Get recent quizzes
$quiz_activities = $conn->query("SELECT 'Quiz Created' as activity, title as item, created_at as activity_time FROM quizzes ORDER BY created_at DESC LIMIT 5");
if ($quiz_activities) {
    while ($activity = $quiz_activities->fetch_assoc()) {
        $recent_activity_data[] = $activity;
    }
}

// Get recent lessons
$lesson_activities = $conn->query("SELECT 'Lesson Created' as activity, title as item, created_at as activity_time FROM lessons ORDER BY created_at DESC LIMIT 5");
if ($lesson_activities) {
    while ($activity = $lesson_activities->fetch_assoc()) {
        $recent_activity_data[] = $activity;
    }
}

// Get recent subjects
$subject_activities = $conn->query("SELECT 'Subject Created' as activity, name as item, created_at as activity_time FROM subjects ORDER BY created_at DESC LIMIT 5");
if ($subject_activities) {
    while ($activity = $subject_activities->fetch_assoc()) {
        $recent_activity_data[] = $activity;
    }
}

// Sort by activity time
usort($recent_activity_data, function($a, $b) {
    return strtotime($b['activity_time']) - strtotime($a['activity_time']);
});

// Keep only top 10
$recent_activity_data = array_slice($recent_activity_data, 0, 10);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Admin Panel</title>
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
        
        .profile-avatar { width: 150px; height: 150px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 3rem; margin: 0 auto 20px; border: 5px solid white; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .profile-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 0; text-align: center; border-radius: 15px 15px 0 0; }
        .stat-card { text-align: center; padding: 25px 15px; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-number { font-size: 2rem; font-weight: bold; margin-bottom: 8px; }
        .activity-item { padding: 15px; border-left: 4px solid #007bff; margin-bottom: 12px; background: #f8f9fa; border-radius: 0 10px 10px 0; transition: all 0.3s ease; }
        .activity-item:hover { background: #e9ecef; transform: translateX(5px); }
        .profile-tabs .nav-link { border-radius: 10px 10px 0 0; padding: 15px 25px; font-weight: 600; }
        .profile-tabs .nav-link.active { background: white; border-color: #dee2e6 #dee2e6 white; }
        .form-floating { margin-bottom: 20px; }
        .settings-section { padding: 20px; border: 1px solid #dee2e6; border-radius: 10px; margin-bottom: 20px; }
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
                    <h1 class="h2"><i class="fas fa-user-circle me-2 text-primary"></i>My Profile</h1>
                    <div class="btn-toolbar">
                        <button class="btn btn-outline-primary me-2" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Profile
                        </button>
                        <button class="btn btn-outline-secondary" onclick="location.reload()">
                            <i class="fas fa-sync me-2"></i>Refresh
                        </button>
                    </div>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Profile Header -->
                <div class="card mb-4">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($current_user['name'], 0, 2)); ?>
                        </div>
                        <h3><?php echo htmlspecialchars($current_user['name']); ?></h3>
                        <p class="mb-0">
                            <i class="fas fa-shield-alt me-2"></i>System Administrator
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($current_user['email']); ?>
                        </p>
                        <p class="mb-3">
                            <i class="fas fa-calendar me-2"></i>Member since <?php echo date('F Y', strtotime($current_user['created_at'])); ?>
                        </p>
                        <div>
                            <span class="badge bg-success fs-6 me-2">
                                <i class="fas fa-check-circle me-1"></i><?php echo ucfirst($current_user['status']); ?>
                            </span>
                            <span class="badge bg-info fs-6">
                                <i class="fas fa-crown me-1"></i>Administrator
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Activity Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body stat-card">
                                <i class="fas fa-users fa-2x mb-3"></i>
                                <div class="stat-number"><?php echo $admin_stats['users_managed']; ?></div>
                                <small>Total Users</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2-4">
                        <div class="card text-white bg-success">
                            <div class="card-body stat-card">
                                <i class="fas fa-graduation-cap fa-2x mb-3"></i>
                                <div class="stat-number"><?php echo $admin_stats['subjects_created']; ?></div>
                                <small>Total Subjects</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2-4">
                        <div class="card text-white bg-info">
                            <div class="card-body stat-card">
                                <i class="fas fa-book fa-2x mb-3"></i>
                                <div class="stat-number"><?php echo $admin_stats['lessons_created']; ?></div>
                                <small>Total Lessons</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2-4">
                        <div class="card text-white bg-warning">
                            <div class="card-body stat-card">
                                <i class="fas fa-question-circle fa-2x mb-3"></i>
                                <div class="stat-number"><?php echo $admin_stats['quizzes_created']; ?></div>
                                <small>Total Quizzes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2-4">
                        <div class="card text-white bg-secondary">
                            <div class="card-body stat-card">
                                <i class="fas fa-list fa-2x mb-3"></i>
                                <div class="stat-number"><?php echo $admin_stats['questions_created']; ?></div>
                                <small>Quiz Questions</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Management Tabs -->
                <div class="card">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs profile-tabs" id="profileTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                                    <i class="fas fa-user me-2"></i>Profile Information
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                    <i class="fas fa-lock me-2"></i>Security
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                                    <i class="fas fa-clock me-2"></i>System Activity
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">
                                    <i class="fas fa-cog me-2"></i>Preferences
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="profileTabsContent">
                            <!-- Profile Information Tab -->
                            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5 class="mb-4"><i class="fas fa-edit me-2"></i>Edit Profile Information</h5>
                                        
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_profile">
                                            
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($current_user['name']); ?>" required>
                                                <label for="name">Full Name</label>
                                            </div>
                                            
                                            <div class="form-floating mb-3">
                                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                                                <label for="email">Email Address</label>
                                            </div>
                                            
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="role" value="Administrator" disabled>
                                                <label for="role">Role</label>
                                            </div>
                                            
                                            <div class="form-floating mb-4">
                                                <input type="text" class="form-control" id="member_since" value="<?php echo date('F j, Y g:i A', strtotime($current_user['created_at'])); ?>" disabled>
                                                <label for="member_since">Member Since</label>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="fas fa-save me-2"></i>Update Profile
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-header">
                                                <h6><i class="fas fa-info-circle me-2"></i>Account Information</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <strong>Account ID:</strong><br>
                                                    <code>#<?php echo $current_user['id']; ?></code>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>Account Status:</strong><br>
                                                    <span class="badge bg-<?php echo $current_user['status'] == 'active' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($current_user['status']); ?>
                                                    </span>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>Last Login:</strong><br>
                                                    <small class="text-muted"><?php echo date('M j, Y g:i A'); ?></small>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>Account Type:</strong><br>
                                                    <span class="badge bg-danger">Administrator</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Tab -->
                            <div class="tab-pane fade" id="security" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5 class="mb-4"><i class="fas fa-shield-alt me-2"></i>Change Password</h5>
                                        
                                        <form method="POST" id="passwordForm">
                                            <input type="hidden" name="action" value="change_password">
                                            
                                            <div class="form-floating mb-3">
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                                <label for="current_password">Current Password</label>
                                            </div>
                                            
                                            <div class="form-floating mb-3">
                                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                                <label for="new_password">New Password</label>
                                            </div>
                                            
                                            <div class="form-floating mb-4">
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                                <label for="confirm_password">Confirm New Password</label>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-warning btn-lg">
                                                <i class="fas fa-key me-2"></i>Change Password
                                            </button>
                                        </form>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-header">
                                                <h6><i class="fas fa-shield-alt me-2"></i>Security Guidelines</h6>
                                            </div>
                                            <div class="card-body">
                                                <h6>Password Requirements:</h6>
                                                <ul class="small">
                                                    <li>Minimum 6 characters</li>
                                                    <li>Use a mix of letters and numbers</li>
                                                    <li>Include special characters</li>
                                                    <li>Don't reuse old passwords</li>
                                                </ul>
                                                
                                                <div class="mt-3">
                                                    <h6>Security Tips:</h6>
                                                    <ul class="small">
                                                        <li>Change password regularly</li>
                                                        <li>Don't share your account</li>
                                                        <li>Log out from shared devices</li>
                                                        <li>Monitor login activities</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Activity Tab -->
                            <div class="tab-pane fade" id="activity" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-12">
                                        <h5 class="mb-4"><i class="fas fa-history me-2"></i>Recent System Activities</h5>
                                        
                                        <div style="max-height: 500px; overflow-y: auto;">
                                            <?php if (!empty($recent_activity_data)): ?>
                                                <?php foreach ($recent_activity_data as $activity): ?>
                                                <div class="activity-item">
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <i class="fas fa-<?php 
                                                                echo $activity['activity'] == 'Quiz Created' ? 'question-circle' : 
                                                                    ($activity['activity'] == 'Lesson Created' ? 'book' : 'graduation-cap'); 
                                                            ?> fa-2x text-primary"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($activity['activity']); ?></h6>
                                                            <p class="mb-1"><strong><?php echo htmlspecialchars($activity['item']); ?></strong></p>
                                                            <small class="text-muted">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?php echo date('F j, Y g:i A', strtotime($activity['activity_time'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-center p-5">
                                                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No Recent Activity</h5>
                                                    <p class="text-muted">System activities will appear here when content is created.</p>
                                                    <a href="dashboard.php" class="btn btn-primary">
                                                        <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Preferences Tab -->
                            <div class="tab-pane fade" id="preferences" role="tabpanel">
                                <h5 class="mb-4"><i class="fas fa-sliders-h me-2"></i>System Preferences</h5>
                                
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_settings">
                                    
                                    <div class="settings-section">
                                        <h6><i class="fas fa-bell me-2"></i>Notifications</h6>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="email_notifications" checked>
                                            <label class="form-check-label" for="email_notifications">
                                                Email notifications for new users
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="quiz_notifications" checked>
                                            <label class="form-check-label" for="quiz_notifications">
                                                Notify when new quiz attempts are made
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="system_alerts">
                                            <label class="form-check-label" for="system_alerts">
                                                System maintenance alerts
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="settings-section">
                                        <h6><i class="fas fa-palette me-2"></i>Interface</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Theme</label>
                                                <select class="form-select">
                                                    <option value="light" selected>Light Theme</option>
                                                    <option value="dark">Dark Theme</option>
                                                    <option value="auto">Auto (System)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Language</label>
                                                <select class="form-select">
                                                    <option value="en" selected>English</option>
                                                    <option value="es">Spanish</option>
                                                    <option value="fr">French</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="settings-section">
                                        <h6><i class="fas fa-chart-line me-2"></i>Dashboard</h6>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="show_statistics" checked>
                                            <label class="form-check-label" for="show_statistics">
                                                Show detailed statistics on dashboard
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="auto_refresh" checked>
                                            <label class="form-check-label" for="auto_refresh">
                                                Auto-refresh dashboard data
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save me-2"></i>Save Preferences
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });

        // Real-time password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            }
        });

        // Auto-save preferences
        document.querySelectorAll('#preferences input, #preferences select').forEach(element => {
            element.addEventListener('change', function() {
                localStorage.setItem('admin_pref_' + this.id, this.type === 'checkbox' ? this.checked : this.value);
            });
        });

        // Load saved preferences
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('#preferences input, #preferences select').forEach(element => {
                const saved = localStorage.getItem('admin_pref_' + element.id);
                if (saved !== null) {
                    if (element.type === 'checkbox') {
                        element.checked = saved === 'true';
                    } else {
                        element.value = saved;
                    }
                }
            });
        });

        // Tab persistence
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = localStorage.getItem('activeProfileTab');
            if (activeTab) {
                const tabButton = document.querySelector(`button[data-bs-target="${activeTab}"]`);
                if (tabButton) {
                    bootstrap.Tab.getOrCreateInstance(tabButton).show();
                }
            }
        });

        document.querySelectorAll('#profileTabs button').forEach(button => {
            button.addEventListener('shown.bs.tab', function(e) {
                localStorage.setItem('activeProfileTab', e.target.getAttribute('data-bs-target'));
            });
        });
    </script>

    <style>
        .col-md-2-4 {
            flex: 0 0 auto;
            width: 20%;
        }
        @media (max-width: 767.98px) {
            .col-md-2-4 {
                width: 50%;
                margin-bottom: 1rem;
            }
        }
    </style>
</body>
</html>
