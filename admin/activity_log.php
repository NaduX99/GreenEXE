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

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filter options
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$user_filter = isset($_GET['user']) ? $_GET['user'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query conditions
$where_conditions = [];
$params = [];
$types = '';

if (!empty($action_filter)) {
    $where_conditions[] = "al.action = ?";
    $params[] = $action_filter;
    $types .= 's';
}

if (!empty($user_filter)) {
    $where_conditions[] = "al.user_id = ?";
    $params[] = $user_filter;
    $types .= 'i';
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(al.created_at) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get activity logs (simulated - you'd need to create this table)
$logs_query = "SELECT 
    'login' as action,
    u.id as user_id,
    u.name as user_name,
    u.role,
    'User logged in' as description,
    u.created_at
    FROM users u
    WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?";

$stmt = $conn->prepare($logs_query);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$logs = $stmt->get_result();

// Get users for filter
$users = $conn->query("SELECT id, name FROM users ORDER BY name ASC");

// Activity types
$activity_types = ['login', 'logout', 'user_created', 'quiz_created', 'lesson_created', 'meeting_created', 'user_banned'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - Admin Panel</title>
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
        .activity-item { border-left: 4px solid #e9ecef; margin-bottom: 10px; }
        .activity-login { border-left-color: #28a745; }
        .activity-create { border-left-color: #007bff; }
        .activity-delete { border-left-color: #dc3545; }
        .activity-update { border-left-color: #ffc107; }
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
                        <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i> Reports</a></li>
                        <li class="nav-item"><a class="nav-link" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                        <li class="nav-item"><a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                        <li class="nav-item"><a class="nav-link active" href="activity_log.php"><i class="fas fa-list-alt me-2"></i> Activity Log</a></li>
                        <li class="nav-item"><a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <h1 class="h2"><i class="fas fa-list-alt me-2"></i>Activity Log</h1>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6><i class="fas fa-filter me-2"></i>Filters</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="action" class="form-label">Action Type</label>
                                <select class="form-control" id="action" name="action">
                                    <option value="">All Actions</option>
                                    <?php foreach ($activity_types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $action_filter == $type ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="user" class="form-label">User</label>
                                <select class="form-control" id="user" name="user">
                                    <option value="">All Users</option>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo $date_filter; ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-2"></i>Filter
                                </button>
                                <a href="activity_log.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Activity Log -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($logs->num_rows > 0): ?>
                            <?php while ($log = $logs->fetch_assoc()): ?>
                            <div class="card activity-item activity-<?php echo $log['action']; ?>">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-1">
                                                <i class="fas fa-<?php echo $log['action'] == 'login' ? 'sign-in-alt' : 'user-plus'; ?> text-muted me-2"></i>
                                                <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                                                <span class="badge bg-<?php echo $log['role'] == 'admin' ? 'danger' : 'info'; ?> ms-2">
                                                    <?php echo ucfirst($log['role']); ?>
                                                </span>
                                            </div>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars($log['description']); ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="ms-3">
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                            
                            <!-- Pagination placeholder -->
                            <nav aria-label="Activity pagination" class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item disabled">
                                        <span class="page-link">Previous</span>
                                    </li>
                                    <li class="page-item active">
                                        <span class="page-link">1</span>
                                    </li>
                                    <li class="page-item disabled">
                                        <span class="page-link">Next</span>
                                    </li>
                                </ul>
                            </nav>
                        <?php else: ?>
                        <div class="text-center p-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No activity logs found for the selected filters.</p>
                            <small class="text-muted">Note: This is a demo version. In production, you would implement proper activity logging.</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
