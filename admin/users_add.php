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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    if (!empty($name) && !empty($email) && !empty($password)) {
        // Check if email already exists
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        
        if ($check_email->get_result()->num_rows > 0) {
            $message = '<div class="alert alert-danger">Email already exists!</div>';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
            
            if ($stmt->execute()) {
                header("Location: users.php?success=1");
                exit();
            } else {
                $message = '<div class="alert alert-danger">Failed to add user!</div>';
            }
        }
    } else {
        $message = '<div class="alert alert-danger">All fields are required!</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 15px 20px;
            margin: 5px 10px;
            border-radius: 8px;
        }
        .sidebar .nav-link:hover { background: rgba(255, 255, 255, 0.1); color: white; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center py-4">
                        <h4 class="text-white">Admin Panel</h4>
                        <p class="text-white-50">Welcome, <?php echo $_SESSION['name']; ?></p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="users.php"><i class="fas fa-users me-2"></i> Users</a></li>
                        <li class="nav-item"><a class="nav-link" href="quizzes.php"><i class="fas fa-question-circle me-2"></i> Quizzes</a></li>
                        <li class="nav-item"><a class="nav-link" href="lessons.php"><i class="fas fa-book me-2"></i> Lessons</a></li>
                        <li class="nav-item"><a class="nav-link" href="meetings.php"><i class="fas fa-video me-2"></i> Meetings</a></li>
                        <li class="nav-item"><a class="nav-link" href="chatroom.php"><i class="fas fa-comments me-2"></i> Chat Room</a></li>
                        <li class="nav-item"><a class="nav-link" href="leaderboard.php"><i class="fas fa-trophy me-2"></i> Leaderboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <h1 class="h2"><i class="fas fa-user-plus me-2"></i>Add New User</h1>
                    <a href="users.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Users
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-plus me-2"></i>User Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role</label>
                                        <select class="form-control" id="role" name="role" required>
                                            <option value="student">Student</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-save me-2"></i>Add User
                                    </button>
                                    <a href="users.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
