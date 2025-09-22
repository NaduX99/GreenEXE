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

// Handle user actions
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $status = $_POST['status'] ?? 'active';
        
        // Check if email already exists
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        
        if ($check_email->get_result()->num_rows > 0) {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Email already exists!</div>';
        } else {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssss", $name, $email, $password, $role, $status);
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>User added successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error creating user!</div>';
            }
        }
    }
    
    if ($_POST['action'] == 'edit') {
        $user_id = intval($_POST['user_id']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        if ($user_id != $_SESSION['user_id']) {
            // Check email uniqueness (excluding current user)
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email->bind_param("si", $email, $user_id);
            $check_email->execute();
            
            if ($check_email->get_result()->num_rows > 0) {
                $message = '<div class="alert alert-danger">Email already exists!</div>';
            } else {
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("ssssi", $name, $email, $role, $status, $user_id);
                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success">User updated successfully!</div>';
                }
            }
        } else {
            $message = '<div class="alert alert-warning">You cannot modify your own account from here!</div>';
        }
    }
    
    if ($_POST['action'] == 'delete') {
        $user_id = intval($_POST['user_id']);
        if ($user_id != $_SESSION['user_id']) {
            // Delete related data first
            $conn->query("DELETE FROM quiz_attempts WHERE user_id = $user_id");
            $conn->query("DELETE FROM lesson_completions WHERE user_id = $user_id");
            $conn->query("DELETE FROM chat_messages WHERE user_id = $user_id");
            
            // Delete user
            $conn->query("DELETE FROM users WHERE id = $user_id");
            $message = '<div class="alert alert-info"><i class="fas fa-trash me-2"></i>User and all related data deleted successfully!</div>';
        } else {
            $message = '<div class="alert alert-warning">You cannot delete your own account!</div>';
        }
    }
    
    if ($_POST['action'] == 'toggle_status') {
        $user_id = intval($_POST['user_id']);
        $new_status = $_POST['current_status'] == 'active' ? 'banned' : 'active';
        if ($user_id != $_SESSION['user_id']) {
            $conn->query("UPDATE users SET status = '$new_status', updated_at = NOW() WHERE id = $user_id");
            $message = '<div class="alert alert-success">User status updated successfully!</div>';
        }
    }
}

// Search and filter handling
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'ASC' : 'DESC';

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$sql = "SELECT * FROM users $where_clause ORDER BY $sort_by $order";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$users = $stmt->get_result();

// Store users in array to avoid multiple iterations
$users_data = [];
while ($user = $users->fetch_assoc()) {
    $users_data[] = $user;
}

// Get user statistics
$user_stats = $conn->query("SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
    COUNT(CASE WHEN role = 'student' THEN 1 END) as student_count,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
    COUNT(CASE WHEN status = 'banned' THEN 1 END) as banned_count,
    COUNT(CASE WHEN status = 'online' THEN 1 END) as online_count,
    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_registered
    FROM users")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
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
        .user-avatar { width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: inline-flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.2rem; }
        .stat-card { text-align: center; padding: 25px 15px; }
        .stat-number { font-size: 2rem; font-weight: bold; margin-bottom: 5px; }
        .user-actions { white-space: nowrap; }
        .table-responsive { border-radius: 15px; overflow: hidden; }
        .search-box { border-radius: 25px; padding: 12px 20px; }
        .btn-group-actions .btn { margin: 0 2px; }
        .user-card { transition: all 0.3s ease; cursor: pointer; }
        .user-card:hover { transform: translateY(-3px); }
        
        /* Fix modal flickering */
        .modal { animation: none !important; }
        .modal-dialog { animation: none !important; }
        .modal.fade .modal-dialog { transition: transform 0.3s ease-out; transform: translateY(-50px); }
        .modal.show .modal-dialog { transform: translateY(0); }
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
                        <h1 class="h2"><i class="fas fa-users me-2 text-primary"></i>User Management</h1>
                        <p class="text-muted">Manage system users and their permissions</p>
                    </div>
                    <div class="btn-toolbar">
                        <button class="btn btn-success me-2" onclick="openAddUserModal()">
                            <i class="fas fa-user-plus me-2"></i>Add New User
                        </button>
                        <button class="btn btn-outline-secondary" onclick="location.reload()">
                            <i class="fas fa-sync me-2"></i>Refresh
                        </button>
                    </div>
                </div>
                
                <?php echo $message; ?>
                
                <!-- User Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-white bg-primary">
                            <div class="card-body stat-card">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $user_stats['total_users']; ?></div>
                                <small>Total Users</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-danger">
                            <div class="card-body stat-card">
                                <i class="fas fa-user-shield fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $user_stats['admin_count']; ?></div>
                                <small>Admins</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-info">
                            <div class="card-body stat-card">
                                <i class="fas fa-user-graduate fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $user_stats['student_count']; ?></div>
                                <small>Students</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-success">
                            <div class="card-body stat-card">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $user_stats['active_count']; ?></div>
                                <small>Active</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-warning">
                            <div class="card-body stat-card">
                                <i class="fas fa-ban fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $user_stats['banned_count']; ?></div>
                                <small>Banned</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-dark">
                            <div class="card-body stat-card">
                                <i class="fas fa-user-clock fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $user_stats['today_registered']; ?></div>
                                <small>Today</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row align-items-center">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" name="search" class="form-control search-box" placeholder="Search users by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select name="role" class="form-select">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="student" <?php echo $role_filter == 'student' ? 'selected' : ''; ?>>Student</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="banned" <?php echo $status_filter == 'banned' ? 'selected' : ''; ?>>Banned</option>
                                    <option value="online" <?php echo $status_filter == 'online' ? 'selected' : ''; ?>>Online</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="sort" class="form-select">
                                    <option value="created_at" <?php echo $sort_by == 'created_at' ? 'selected' : ''; ?>>Date Joined</option>
                                    <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name</option>
                                    <option value="email" <?php echo $sort_by == 'email' ? 'selected' : ''; ?>>Email</option>
                                    <option value="role" <?php echo $sort_by == 'role' ? 'selected' : ''; ?>>Role</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                    <a href="users.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-table me-2"></i>All Users (<?php echo count($users_data); ?> found)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($users_data)): ?>
                                        <?php foreach ($users_data as $user): ?>
                                        <tr>
                                            <td><strong>#<?php echo $user['id']; ?></strong></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3">
                                                        <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></div>
                                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                        <small class="text-primary"><i class="fas fa-crown me-1"></i>You</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'info'; ?> fs-6">
                                                    <i class="fas fa-<?php echo $user['role'] == 'admin' ? 'shield-alt' : 'user'; ?> me-1"></i>
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : ($user['status'] == 'online' ? 'primary' : 'danger'); ?> fs-6">
                                                    <i class="fas fa-<?php echo $user['status'] == 'online' ? 'circle' : ($user['status'] == 'active' ? 'check' : 'ban'); ?> me-1"></i>
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($user['created_at'])); ?></small>
                                            </td>
                                            <td class="user-actions">
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <!-- View Profile -->
                                                    <button class="btn btn-outline-info" onclick="openViewModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    
                                                    <!-- Edit User -->
                                                    <button class="btn btn-outline-primary" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" title="Edit User">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <!-- Toggle Status -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="current_status" value="<?php echo $user['status']; ?>">
                                                        <button type="submit" class="btn btn-outline-<?php echo $user['status'] == 'active' ? 'warning' : 'success'; ?>" title="<?php echo $user['status'] == 'active' ? 'Ban' : 'Activate'; ?> User">
                                                            <i class="fas fa-<?php echo $user['status'] == 'active' ? 'ban' : 'check'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <!-- Delete User -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this user and all their data?')" title="Delete User">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                                <?php else: ?>
                                                <span class="badge bg-primary">Current User</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center p-5">
                                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                <h5 class="text-muted">No Users Found</h5>
                                                <p class="text-muted">No users match your current filters.</p>
                                                <button class="btn btn-primary" onclick="openAddUserModal()">
                                                    <i class="fas fa-user-plus me-2"></i>Add First User
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="add_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="add_name" name="name" required placeholder="Enter full name">
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="add_email" name="email" required placeholder="Enter email address">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_role" class="form-label">Role *</label>
                                    <select class="form-control" id="add_role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="student">Student</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="add_status" class="form-label">Status</label>
                                    <select class="form-control" id="add_status" name="status">
                                        <option value="active">Active</option>
                                        <option value="banned">Banned</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="add_password" name="password" required placeholder="Enter password">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-user-plus me-2"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalTitle">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewModalBody">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalTitle">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_role" class="form-label">Role</label>
                                    <select class="form-control" id="edit_role" name="role" required>
                                        <option value="student">Student</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_status" class="form-label">Status</label>
                                    <select class="form-control" id="edit_status" name="status" required>
                                        <option value="active">Active</option>
                                        <option value="banned">Banned</option>
                                        <option value="online">Online</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fixed JavaScript - No more blinking/flickering
        
        function openAddUserModal() {
            const modal = new bootstrap.Modal(document.getElementById('addUserModal'));
            // Clear form
            document.getElementById('add_name').value = '';
            document.getElementById('add_email').value = '';
            document.getElementById('add_role').value = '';
            document.getElementById('add_status').value = 'active';
            document.getElementById('add_password').value = '';
            modal.show();
        }

        function openViewModal(user) {
            const modal = new bootstrap.Modal(document.getElementById('viewUserModal'));
            
            document.getElementById('viewModalTitle').innerHTML = 'User Details: ' + user.name;
            
            const modalBody = document.getElementById('viewModalBody');
            modalBody.innerHTML = `
                <div class="text-center mb-3">
                    <div class="user-avatar mx-auto" style="width: 80px; height: 80px; font-size: 2rem;">
                        ${user.name.substring(0, 2).toUpperCase()}
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6"><strong>User ID:</strong> #${user.id}</div>
                    <div class="col-md-6"><strong>Role:</strong> ${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-md-6">
                        <strong>Status:</strong> 
                        <span class="badge bg-${user.status === 'active' ? 'success' : (user.status === 'online' ? 'primary' : 'danger')}">
                            ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                        </span>
                    </div>
                    <div class="col-md-6">
                        <strong>Joined:</strong> ${new Date(user.created_at).toLocaleDateString()}
                    </div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-md-12">
                        <strong>Email:</strong> ${user.email}
                    </div>
                </div>
            `;
            
            modal.show();
        }

        function openEditModal(user) {
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            
            document.getElementById('editModalTitle').innerHTML = 'Edit User: ' + user.name;
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            
            modal.show();
        }

        // Prevent modal backdrop click issues
        document.addEventListener('DOMContentLoaded', function() {
            // Remove any existing modal backdrops on page load
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                backdrop.remove();
            });
        });

        // Enhanced search with debounce
        let searchTimeout;
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 3 || this.value.length === 0) {
                        this.form.submit();
                    }
                }, 500);
            });
        }
    </script>
</body>
</html>
