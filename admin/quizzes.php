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

// Handle quiz actions
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'delete') {
        $quiz_id = intval($_POST['quiz_id']);
        // Delete quiz questions first
        $conn->query("DELETE FROM quiz_questions WHERE quiz_id = $quiz_id");
        // Delete quiz attempts
        $conn->query("DELETE FROM quiz_attempts WHERE quiz_id = $quiz_id");
        // Then delete quiz
        $conn->query("DELETE FROM quizzes WHERE id = $quiz_id");
        $message = '<div class="alert alert-info">Quiz deleted successfully!</div>';
    }
    
    if ($_POST['action'] == 'toggle_status') {
        $quiz_id = intval($_POST['quiz_id']);
        $new_status = $_POST['status'] == 'active' ? 'inactive' : 'active';
        $conn->query("UPDATE quizzes SET status = '$new_status' WHERE id = $quiz_id");
        $message = '<div class="alert alert-success">Quiz status updated!</div>';
    }
}

// Build query with subject filter
$where_clause = '';
if ($selected_subject > 0) {
    $where_clause = "WHERE q.subject_id = $selected_subject";
}

$quizzes = $conn->query("
    SELECT q.*, s.name as subject_name, l.title as lesson_title, u.name as creator_name,
    (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) as question_count,
    (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id) as attempt_count
    FROM quizzes q 
    LEFT JOIN subjects s ON q.subject_id = s.id
    LEFT JOIN lessons l ON q.lesson_id = l.id
    LEFT JOIN users u ON q.created_by = u.id 
    $where_clause
    ORDER BY q.created_at DESC
");

// Get subjects for filter
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name ASC");

// Get quiz statistics
$quiz_stats = $conn->query("SELECT 
    COUNT(*) as total_quizzes,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_count,
    (SELECT COUNT(*) FROM quiz_questions) as total_questions,
    (SELECT COUNT(*) FROM quiz_attempts WHERE DATE(started_at) = CURDATE()) as today_attempts
    FROM quizzes")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Management - Admin Panel</title>
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
        .quiz-card { border-left: 4px solid #007bff; }
        .quiz-card.inactive { border-left-color: #6c757d; }
        .stat-card { text-align: center; padding: 20px; }
        .stat-number { font-size: 2rem; font-weight: bold; }
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
                        <h1 class="h2"><i class="fas fa-question-circle me-2 text-primary"></i>Quiz Management</h1>
                        <?php if ($selected_subject > 0): ?>
                            <?php 
                            $subject_name = $conn->query("SELECT name FROM subjects WHERE id = $selected_subject")->fetch_assoc()['name'];
                            ?>
                            <p class="text-muted">Showing quizzes for: <strong><?php echo htmlspecialchars($subject_name); ?></strong></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="quiz_create.php" class="btn btn-success me-2">
                            <i class="fas fa-plus-circle me-2"></i>Create MCQ Quiz
                        </a>
                        <a href="subjects.php" class="btn btn-outline-info">
                            <i class="fas fa-graduation-cap me-2"></i>Subjects
                        </a>
                    </div>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Quiz Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body stat-card">
                                <i class="fas fa-question-circle fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $quiz_stats['total_quizzes']; ?></div>
                                <small>Total Quizzes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2-4">
                        <div class="card text-white bg-success">
                            <div class="card-body stat-card">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $quiz_stats['active_count']; ?></div>
                                <small>Active</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2-4">
                        <div class="card text-white bg-secondary">
                            <div class="card-body stat-card">
                                <i class="fas fa-pause-circle fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $quiz_stats['inactive_count']; ?></div>
                                <small>Inactive</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2-4">
                        <div class="card text-white bg-info">
                            <div class="card-body stat-card">
                                <i class="fas fa-list fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $quiz_stats['total_questions']; ?></div>
                                <small>Questions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2-4">
                        <div class="card text-white bg-warning">
                            <div class="card-body stat-card">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $quiz_stats['today_attempts']; ?></div>
                                <small>Today's Attempts</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subject Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter by Subject</h6>
                            </div>
                            <div class="col-md-6">
                                <select class="form-select" onchange="window.location='quizzes.php?subject=' + this.value">
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

                <!-- Quizzes List -->
                <div class="row">
                    <?php if ($quizzes->num_rows > 0): ?>
                        <?php while ($quiz = $quizzes->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card quiz-card <?php echo $quiz['status'] == 'inactive' ? 'inactive' : ''; ?> h-100">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 text-primary"><?php echo htmlspecialchars($quiz['subject_name']); ?></h6>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                                            <?php if ($quiz['lesson_title']): ?>
                                            <small class="text-muted">Lesson: <?php echo htmlspecialchars($quiz['lesson_title']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php if ($quiz['question_count'] > 0): ?>
                                                <li><a class="dropdown-item" href="quiz_questions_manage.php?quiz_id=<?php echo $quiz['id']; ?>">
                                                    <i class="fas fa-list me-2"></i>Manage Questions (<?php echo $quiz['question_count']; ?>)</a></li>
                                                <?php else: ?>
                                                <li><a class="dropdown-item" href="quiz_questions_manage.php?quiz_id=<?php echo $quiz['id']; ?>">
                                                    <i class="fas fa-plus me-2"></i>Add Questions</a></li>
                                                <?php endif; ?>
                                                
                                                <?php if ($quiz['question_count'] >= 3): ?>
                                                <li><a class="dropdown-item" href="quiz_preview.php?quiz_id=<?php echo $quiz['id']; ?>" target="_blank">
                                                    <i class="fas fa-eye me-2"></i>Preview Quiz</a></li>
                                                <?php endif; ?>
                                                
                                                <li><hr class="dropdown-divider"></li>
                                                
                                                <li>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                                        <input type="hidden" name="status" value="<?php echo $quiz['status']; ?>">
                                                        <button type="submit" class="dropdown-item">
                                                            <?php if ($quiz['status'] == 'active'): ?>
                                                            <i class="fas fa-pause me-2"></i>Deactivate
                                                            <?php else: ?>
                                                            <i class="fas fa-play me-2"></i>Activate
                                                            <?php endif; ?>
                                                        </button>
                                                    </form>
                                                </li>
                                                
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                                        <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Delete this quiz and all its questions?')">
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
                                        <?php echo htmlspecialchars(substr($quiz['description'] ?? 'No description', 0, 100)) . (strlen($quiz['description'] ?? '') > 100 ? '...' : ''); ?>
                                    </p>
                                    
                                    <!-- Quiz Info -->
                                    <div class="row text-center mb-3">
                                        <div class="col-4">
                                            <div class="text-primary">
                                                <i class="fas fa-list fa-lg"></i>
                                                <div><strong><?php echo $quiz['question_count']; ?></strong></div>
                                                <small>Questions</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-info">
                                                <i class="fas fa-clock fa-lg"></i>
                                                <div><strong><?php echo $quiz['time_limit']; ?>m</strong></div>
                                                <small>Time</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-success">
                                                <i class="fas fa-users fa-lg"></i>
                                                <div><strong><?php echo $quiz['attempt_count']; ?></strong></div>
                                                <small>Attempts</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="d-grid gap-2">
                                        <?php if ($quiz['question_count'] == 0): ?>
                                        <a href="quiz_questions_manage.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Add Questions First
                                        </a>
                                        <?php else: ?>
                                        <a href="quiz_questions_manage.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit me-1"></i>Manage Questions (<?php echo $quiz['question_count']; ?>)
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($quiz['question_count'] >= 3): ?>
                                        <a href="quiz_preview.php?quiz_id=<?php echo $quiz['id']; ?>" target="_blank" class="btn btn-success btn-sm">
                                            <i class="fas fa-eye me-1"></i>Preview Quiz
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="badge bg-<?php echo $quiz['status'] == 'active' ? 'success' : 'secondary'; ?> fs-6">
                                            <?php echo ucfirst($quiz['status']); ?>
                                        </span>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($quiz['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center p-5">
                                    <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                                    <h4>No Quizzes Found</h4>
                                    <p class="text-muted">
                                        <?php if ($selected_subject > 0): ?>
                                        No quizzes found for this subject. Create your first quiz!
                                        <?php else: ?>
                                        Start by creating your first MCQ quiz.
                                        <?php endif; ?>
                                    </p>
                                    <a href="quiz_create.php" class="btn btn-success btn-lg">
                                        <i class="fas fa-plus-circle me-2"></i>Create Your First Quiz
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
    <script>
        // Auto refresh every 60 seconds
        setInterval(function() {
            if (!document.querySelector('form')) {
                location.reload();
            }
        }, 60000);
        
        // Confirmation for dangerous actions
        document.querySelectorAll('button[onclick*="confirm"]').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this quiz and all its questions?')) {
                    e.preventDefault();
                    return false;
                }
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
