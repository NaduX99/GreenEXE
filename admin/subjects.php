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

// Handle subject actions
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (!empty($name)) {
            $stmt = $conn->prepare("INSERT INTO subjects (name, description, created_by) VALUES (?, ?, ?)");
            $created_by = $_SESSION['user_id'];
            $stmt->bind_param("ssi", $name, $description, $created_by);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Subject created successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Error: ' . $conn->error . '</div>';
            }
        }
    }
    
    if ($_POST['action'] == 'delete') {
        $subject_id = intval($_POST['subject_id']);
        $conn->query("UPDATE lessons SET subject_id = NULL WHERE subject_id = $subject_id");
        $conn->query("UPDATE quizzes SET subject_id = NULL WHERE subject_id = $subject_id");
        $conn->query("DELETE FROM subjects WHERE id = $subject_id");
        $message = '<div class="alert alert-info">Subject deleted successfully!</div>';
    }
}

// Get subjects with counts
$subjects = $conn->query("
    SELECT s.*, 
    (SELECT COUNT(*) FROM lessons WHERE subject_id = s.id) as lesson_count,
    (SELECT COUNT(*) FROM quizzes WHERE subject_id = s.id) as quiz_count
    FROM subjects s 
    ORDER BY s.name ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects - Admin Panel</title>
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
                    <h1 class="h2"><i class="fas fa-graduation-cap me-2 text-primary"></i>Subject Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                        <i class="fas fa-plus me-2"></i>Add Subject
                    </button>
                </div>
                
                <?php echo $message; ?>
                
                <div class="row">
                    <?php if ($subjects && $subjects->num_rows > 0): ?>
                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title text-primary"><?php echo htmlspecialchars($subject['name']); ?></h5>
                                    <p class="card-text text-muted"><?php echo htmlspecialchars(substr($subject['description'] ?? 'No description', 0, 100)) . '...'; ?></p>
                                    
                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <h4 class="text-info"><?php echo $subject['lesson_count']; ?></h4>
                                            <small>Lessons</small>
                                        </div>
                                        <div class="col-6">
                                            <h4 class="text-success"><?php echo $subject['quiz_count']; ?></h4>
                                            <small>Quizzes</small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="lessons.php?subject=<?php echo $subject['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-book me-1"></i>View Lessons
                                        </a>
                                        <a href="quizzes.php?subject=<?php echo $subject['id']; ?>" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-question-circle me-1"></i>View Quizzes
                                        </a>
                                        <div class="btn-group">
                                            <a href="lesson_add.php?subject=<?php echo $subject['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus"></i> Lesson
                                            </a>
                                            <a href="quiz_create.php?subject=<?php echo $subject['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-plus"></i> Quiz
                                            </a>
                                        </div>
                                        
                                        <form method="POST" onsubmit="return confirm('Delete this subject?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center p-5">
                                    <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                                    <h4>No Subjects Found</h4>
                                    <p class="text-muted">Create your first subject to get started.</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                                        <i class="fas fa-plus me-2"></i>Add First Subject
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Subject Modal -->
    <div class="modal fade" id="addSubjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="name" class="form-label">Subject Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
