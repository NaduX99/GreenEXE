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
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$user_filter = isset($_GET['user']) ? $_GET['user'] : '';

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(cm.created_at) = ?";
    $params[] = $date_filter;
    $types .= 's';
}

if (!empty($user_filter)) {
    $where_conditions[] = "cm.user_id = ?";
    $params[] = $user_filter;
    $types .= 'i';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get messages
$query = "SELECT cm.*, u.name as user_name, u.chat_banned 
          FROM chat_messages cm 
          LEFT JOIN users u ON cm.user_id = u.id 
          $where_clause 
          ORDER BY cm.created_at DESC 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}

$stmt->execute();
$messages = $stmt->get_result();

// Get total count
$count_query = "SELECT COUNT(*) as total FROM chat_messages cm LEFT JOIN users u ON cm.user_id = u.id $where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($where_conditions)) {
    $count_types = substr($types, 0, -2); // Remove 'ii' for limit/offset
    $count_params = array_slice($params, 0, -2);
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
}
$count_stmt->execute();
$total_messages = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_messages / $limit);

// Get users for filter
$users = $conn->query("SELECT id, name FROM users ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat History - Admin Panel</title>
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
        .message-card { margin-bottom: 10px; }
        .banned-user { border-left: 4px solid #dc3545; }
        .message-content { background: #f8f9fa; padding: 10px; border-radius: 8px; }
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
                    <h1 class="h2"><i class="fas fa-history me-2"></i>Chat History</h1>
                    <a href="chatroom.php" class="btn btn-outline-secondary">
                        <i class="fas fa-comments me-2"></i>Live Chat Moderation
                    </a>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6><i class="fas fa-filter me-2"></i>Filters</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo $date_filter; ?>">
                            </div>
                            <div class="col-md-4">
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
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-2"></i>Filter
                                </button>
                                <a href="chat_history.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Messages -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-comment-alt me-2"></i>Chat Messages (<?php echo $total_messages; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($messages->num_rows > 0): ?>
                            <?php while ($message = $messages->fetch_assoc()): ?>
                            <div class="card message-card <?php echo $message['chat_banned'] ? 'banned-user' : ''; ?>">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($message['user_name']); ?></strong>
                                            <?php if ($message['chat_banned']): ?>
                                            <span class="badge bg-danger ms-2">Banned</span>
                                            <?php endif; ?>
                                            <small class="text-muted ms-2">
                                                <?php echo date('Y-m-d H:i:s', strtotime($message['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="message-content mt-2">
                                        <?php echo htmlspecialchars($message['message']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Chat pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&date=<?php echo $date_filter; ?>&user=<?php echo $user_filter; ?>">Previous</a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&date=<?php echo $date_filter; ?>&user=<?php echo $user_filter; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&date=<?php echo $date_filter; ?>&user=<?php echo $user_filter; ?>">Next</a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        <?php else: ?>
                        <div class="text-center p-4">
                            <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No chat messages found for the selected filters.</p>
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
