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
$selected_subject = isset($_GET['subject']) ? intval($_GET['subject']) : 0;

// Handle lesson actions
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'delete') {
        $lesson_id = intval($_POST['lesson_id']);
        if ($conn->query("DELETE FROM lessons WHERE id = $lesson_id")) {
            $message = '<div class="alert alert-info">Lesson deleted successfully!</div>';
        }
    }
}

// Build query with subject filter
$where_clause = '';
if ($selected_subject > 0) {
    $where_clause = "WHERE l.subject_id = $selected_subject";
}

$lessons = $conn->query("
    SELECT l.*, s.name as subject_name, u.name as creator_name
    FROM lessons l 
    LEFT JOIN subjects s ON l.subject_id = s.id
    LEFT JOIN users u ON l.created_by = u.id 
    $where_clause
    ORDER BY s.name ASC, l.created_at DESC
");

// Get subjects for filter
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lessons - Admin Panel</title>
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
                    <div>
                        <h1 class="h2"><i class="fas fa-book me-2 text-primary"></i>Lesson Management</h1>
                        <?php if ($selected_subject > 0): ?>
                            <?php 
                            $subject_name = $conn->query("SELECT name FROM subjects WHERE id = $selected_subject")->fetch_assoc()['name'];
                            ?>
                            <p class="text-muted">Showing lessons for: <strong><?php echo htmlspecialchars($subject_name); ?></strong></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="lesson_add.php" class="btn btn-primary me-2">
                            <i class="fas fa-plus me-2"></i>Add Lesson
                        </a>
                        <a href="subjects.php" class="btn btn-outline-info">
                            <i class="fas fa-graduation-cap me-2"></i>Subjects
                        </a>
                    </div>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Subject Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter by Subject</h6>
                            </div>
                            <div class="col-md-6">
                                <select class="form-select" onchange="window.location='lessons.php?subject=' + this.value">
                                    <option value="0">All Subjects</option>
                                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                                    <option value="<?php echo $subject['id']; ?>" <?php echo $selected_subject == $subject['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lessons Grid -->
                <div class="row">
                    <?php if ($lessons->num_rows > 0): ?>
                        <?php while ($lesson = $lessons->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 text-primary"><?php echo htmlspecialchars($lesson['subject_name']); ?></h6>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($lesson['title']); ?></h5>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="lesson_edit.php?id=<?php echo $lesson['id']; ?>">
                                                    <i class="fas fa-edit me-2"></i>Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                                                        <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Delete this lesson?')">
                                                            <i class="fas fa-trash me-2"></i>Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text text-muted small">
                                        <?php echo htmlspecialchars(substr($lesson['content'], 0, 100)) . (strlen($lesson['content']) > 100 ? '...' : ''); ?>
                                    </p>
                                    
                                    <!-- Resource Links -->
                                    <div class="mb-3">
                                        <?php if (!empty($lesson['google_form_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($lesson['google_form_url']); ?>" target="_blank" class="btn btn-outline-primary btn-sm me-1 mb-1">
                                            <i class="fab fa-google me-1"></i>Form
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($lesson['presentation_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($lesson['presentation_url']); ?>" target="_blank" class="btn btn-outline-success btn-sm me-1 mb-1">
                                            <i class="fas fa-presentation me-1"></i>Slides
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (empty($lesson['google_form_url']) && empty($lesson['presentation_url'])): ?>
                                        <small class="text-muted">No additional resources</small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-<?php echo $lesson['status'] == 'active' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($lesson['status']); ?>
                                        </span>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($lesson['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center p-5">
                                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                    <h4>No Lessons Found</h4>
                                    <p class="text-muted">Start by creating your first lesson.</p>
                                    <a href="lesson_add.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Create First Lesson
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
