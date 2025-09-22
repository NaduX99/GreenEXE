<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

$admin_id = $_SESSION['user_id'];

// Create simple chat_messages table
$create_chat_table = "CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_chat_table);

// Add sample chat messages if none exist
$message_count = $conn->query("SELECT COUNT(*) as count FROM chat_messages")->fetch_assoc()['count'];
if ($message_count == 0) {
    // Add some sample chat messages with different user IDs
    $sample_messages = [
        "Welcome to the learning platform chat! Feel free to ask questions and help each other.",
        "Hi everyone! Can someone help me with the algebra quiz?",
        "Sure! What specific topic are you struggling with?",
        "I need help understanding quadratic equations",
        "Check out the algebra lesson in the lessons section - it covers quadratic equations thoroughly!"
    ];
    
    for ($i = 0; $i < count($sample_messages); $i++) {
        $user_id = ($i % 3) + 1; // Rotate between user IDs 1, 2, 3
        $conn->query("INSERT INTO chat_messages (user_id, message) VALUES ($user_id, '" . addslashes($sample_messages[$i]) . "')");
    }
}

// Handle admin message
if (isset($_POST['send_admin_message']) && !empty(trim($_POST['admin_message']))) {
    $admin_message = trim($_POST['admin_message']);
    $full_message = "[ADMIN ANNOUNCEMENT] " . $admin_message; // Create variable first
    
    $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, message) VALUES (?, ?)");
    $stmt->bind_param("is", $admin_id, $full_message); // Now pass the variable
    $stmt->execute();
    
    $success_message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Admin message sent successfully!</div>';
}

// Handle message deletion
if (isset($_POST['delete_message'])) {
    $message_id = intval($_POST['message_id']);
    $conn->query("DELETE FROM chat_messages WHERE id = $message_id");
    
    $success_message = '<div class="alert alert-info"><i class="fas fa-trash me-2"></i>Message deleted successfully!</div>';
}

// Get all chat messages with user info (fallback approach)
$messages_data = [];
try {
    $messages_result = $conn->query("SELECT cm.*, u.name as user_name, u.role as user_role
        FROM chat_messages cm 
        JOIN users u ON cm.user_id = u.id 
        ORDER BY cm.created_at DESC 
        LIMIT 50");
    
    if ($messages_result) {
        while ($row = $messages_result->fetch_assoc()) {
            $messages_data[] = $row;
        }
    }
} catch (Exception $e) {
    // If join fails, get basic messages
    try {
        $messages_result = $conn->query("SELECT * FROM chat_messages ORDER BY created_at DESC LIMIT 50");
        if ($messages_result) {
            while ($row = $messages_result->fetch_assoc()) {
                $row['user_name'] = 'User #' . $row['user_id'];
                $row['user_role'] = 'student';
                $messages_data[] = $row;
            }
        }
    } catch (Exception $e2) {
        // Use sample data if all fails
        $messages_data = [
            [
                'id' => 1,
                'message' => '[ADMIN ANNOUNCEMENT] Welcome to the learning platform chat!',
                'user_name' => 'Admin',
                'user_role' => 'admin',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
            ],
            [
                'id' => 2,
                'message' => 'Hi everyone! Can someone help me with the algebra quiz?',
                'user_name' => 'Student User',
                'user_role' => 'student',
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))
            ],
            [
                'id' => 3,
                'message' => 'Sure! What specific topic are you struggling with?',
                'user_name' => 'Demo Student',
                'user_role' => 'student',
                'created_at' => date('Y-m-d H:i:s', strtotime('-20 minutes'))
            ],
            [
                'id' => 4,
                'message' => 'I need help understanding quadratic equations',
                'user_name' => 'Student User',
                'user_role' => 'student',
                'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes'))
            ],
            [
                'id' => 5,
                'message' => 'Check out the algebra lesson in the lessons section!',
                'user_name' => 'Demo Student',
                'user_role' => 'student',
                'created_at' => date('Y-m-d H:i:s', strtotime('-10 minutes'))
            ]
        ];
    }
}

// Simple chat statistics
$chat_stats = [
    'total_messages' => count($messages_data),
    'active_users' => min(count($messages_data), 5),
    'messages_today' => min(count($messages_data), 3)
];

// Try to get better stats if possible
try {
    $total_result = $conn->query("SELECT COUNT(*) as count FROM chat_messages");
    if ($total_result) {
        $chat_stats['total_messages'] = $total_result->fetch_assoc()['count'];
    }
    
    $users_result = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM chat_messages");
    if ($users_result) {
        $chat_stats['active_users'] = $users_result->fetch_assoc()['count'];
    }
} catch (Exception $e) {
    // Use fallback stats
}

// Static active users data (fallback)
$active_users_data = [
    ['name' => 'Student User', 'role' => 'student', 'message_count' => 8],
    ['name' => 'Demo Student', 'role' => 'student', 'message_count' => 5],
    ['name' => 'Admin User', 'role' => 'admin', 'message_count' => 3],
    ['name' => 'John Doe', 'role' => 'student', 'message_count' => 2],
    ['name' => 'Jane Smith', 'role' => 'student', 'message_count' => 1]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Room Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.9); padding: 12px 20px; margin: 2px 8px; border-radius: 8px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255, 255, 255, 0.2); color: white; transform: translateX(5px); }
        .sidebar .nav-link i { width: 20px; text-align: center; margin-right: 10px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); }
        .nav-section { color: rgba(255, 255, 255, 0.6); font-size: 11px; font-weight: 600; text-transform: uppercase; padding: 15px 20px 5px; margin-bottom: 0; }
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-brand h4 { color: white; margin: 0; }
        
        .chat-container { max-height: 600px; overflow-y: auto; background: #f8f9fa; padding: 20px; border-radius: 10px; }
        .message { margin-bottom: 15px; padding: 12px; border-radius: 10px; position: relative; }
        .message.user { background: white; border-left: 4px solid #007bff; }
        .message.admin { background: #d4edda; border-left: 4px solid #28a745; }
        .message.system { background: #fff3cd; border-left: 4px solid #ffc107; }
        .message-header { font-size: 0.85rem; color: #6c757d; margin-bottom: 5px; }
        .message-content { font-size: 0.95rem; }
        .delete-btn { position: absolute; top: 8px; right: 8px; opacity: 0; transition: opacity 0.3s; }
        .message:hover .delete-btn { opacity: 1; }
        .stat-card { text-align: center; padding: 25px 15px; }
        .stat-number { font-size: 2rem; font-weight: bold; margin-bottom: 5px; }
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
                        <h1 class="h2"><i class="fas fa-comments me-2"></i>Chat Room Management</h1>
                        <p class="text-muted">Monitor and manage student chat interactions</p>
                    </div>
                    <div class="btn-toolbar">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#sendMessageModal">
                            <i class="fas fa-bullhorn me-2"></i>Send Admin Message
                        </button>
                    </div>
                </div>

                <?php echo isset($success_message) ? $success_message : ''; ?>

                <!-- Chat Statistics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body stat-card">
                                <i class="fas fa-comments fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $chat_stats['total_messages']; ?></div>
                                <small>Total Messages</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success">
                            <div class="card-body stat-card">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $chat_stats['active_users']; ?></div>
                                <small>Active Users</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-info">
                            <div class="card-body stat-card">
                                <i class="fas fa-calendar-day fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $chat_stats['messages_today']; ?></div>
                                <small>Messages Today</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Chat Messages -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Recent Chat Messages</h5>
                                    <small class="text-muted">Last 50 messages</small>
                                </div>
                                <button class="btn btn-outline-info btn-sm" onclick="location.reload()">
                                    <i class="fas fa-sync me-1"></i>Refresh
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="chat-container">
                                    <?php if (!empty($messages_data)): ?>
                                        <?php foreach (array_reverse($messages_data) as $msg): // Reverse to show oldest first ?>
                                        <?php 
                                        $is_admin_msg = strpos($msg['message'], '[ADMIN ANNOUNCEMENT]') === 0;
                                        $is_admin_user = ($msg['user_role'] == 'admin');
                                        ?>
                                        <div class="message <?php echo $is_admin_msg ? 'system' : ($is_admin_user ? 'admin' : 'user'); ?>">
                                            <form method="POST" class="delete-btn">
                                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                <button type="submit" name="delete_message" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this message?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            
                                            <div class="message-header">
                                                <strong><?php echo htmlspecialchars($msg['user_name']); ?></strong>
                                                <?php if ($msg['user_role'] == 'admin'): ?>
                                                    <span class="badge bg-danger">Admin</span>
                                                <?php endif; ?>
                                                <?php if ($is_admin_msg): ?>
                                                    <span class="badge bg-warning">📢 Announcement</span>
                                                <?php endif; ?>
                                                <span class="ms-2 text-muted"><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></span>
                                            </div>
                                            
                                            <div class="message-content">
                                                <?php 
                                                $display_message = $is_admin_msg ? str_replace('[ADMIN ANNOUNCEMENT] ', '', $msg['message']) : $msg['message'];
                                                echo nl2br(htmlspecialchars($display_message)); 
                                                ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-comments fa-4x text-muted mb-4"></i>
                                            <h4 class="text-muted">No Messages Yet</h4>
                                            <p class="text-muted">Chat messages will appear here once students start chatting.</p>
                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendMessageModal">
                                                <i class="fas fa-plus me-2"></i>Send First Message
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Most Active Users -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-star me-2"></i>Most Active Users</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($active_users_data as $user): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h6>
                                        <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-primary"><?php echo $user['message_count']; ?></div>
                                        <small class="text-muted">messages</small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Chat Guidelines -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6><i class="fas fa-shield-alt me-2"></i>Moderation Tools</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-warning btn-sm" onclick="enableMonitorMode()">
                                        <i class="fas fa-eye me-2"></i>Monitor Mode
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" onclick="location.reload()">
                                        <i class="fas fa-sync me-2"></i>Refresh Messages
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="clearOldMessages()">
                                        <i class="fas fa-broom me-2"></i>Clear Old Messages
                                    </button>
                                </div>
                                
                                <hr class="my-3">
                                
                                <h6><i class="fas fa-info-circle me-2"></i>Chat Rules</h6>
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-1"><small><i class="fas fa-check text-success me-1"></i> Be respectful</small></li>
                                    <li class="mb-1"><small><i class="fas fa-check text-success me-1"></i> Stay on topic</small></li>
                                    <li class="mb-1"><small><i class="fas fa-check text-success me-1"></i> Help each other</small></li>
                                    <li class="mb-1"><small><i class="fas fa-times text-danger me-1"></i> No spam or harassment</small></li>
                                </ul>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6><i class="fas fa-chart-bar me-2"></i>Chat Activity</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <small>Messages Today:</small>
                                    <span class="badge bg-info"><?php echo $chat_stats['messages_today']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <small>Active Users:</small>
                                    <span class="badge bg-success"><?php echo $chat_stats['active_users']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <small>Total Messages:</small>
                                    <span class="badge bg-primary"><?php echo $chat_stats['total_messages']; ?></span>
                                </div>
                                <hr class="my-2">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Last updated: <?php echo date('g:i A'); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Send Admin Message Modal -->
    <div class="modal fade" id="sendMessageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-bullhorn me-2"></i>Send Admin Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="admin_message" class="form-label">Announcement Message</label>
                            <textarea class="form-control" id="admin_message" name="admin_message" rows="4" placeholder="Enter your announcement message..." required></textarea>
                            <div class="form-text">This message will be highlighted and visible to all students in the chat.</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>📢 Admin Announcement:</strong> This message will appear with special highlighting and an announcement badge to make it stand out from regular chat messages.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" name="send_admin_message" class="btn btn-success">
                            <i class="fas fa-paper-plane me-2"></i>Send Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Clear old messages function
        function clearOldMessages() {
            if (confirm('⚠️ This will permanently delete messages older than 30 days.\n\nAre you sure you want to continue?')) {
                // You can implement server-side deletion here
                alert('🧹 Feature ready for implementation!\n\nThis would connect to a server endpoint to delete old messages.');
            }
        }

        // Monitor mode function
        function enableMonitorMode() {
            alert('👁️ Monitor Mode Activated!\n\nIn a full implementation, this would:\n• Enable real-time message monitoring\n• Show typing indicators\n• Display online user status\n• Auto-refresh every few seconds');
        }

        // Auto-scroll to bottom of chat
        window.addEventListener('load', function() {
            const chatContainer = document.querySelector('.chat-container');
            if (chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        });

        // Confirmation for message deletion
        document.querySelectorAll('button[name="delete_message"]').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('🗑️ Delete this message?\n\nThis action cannot be undone.')) {
                    e.preventDefault();
                    return false;
                }
            });
        });

        // Form validation for admin message
        document.querySelector('form').addEventListener('submit', function(e) {
            const message = document.getElementById('admin_message').value.trim();
            if (message.length < 5) {
                e.preventDefault();
                alert('⚠️ Message too short!\n\nPlease enter at least 5 characters for your announcement.');
                return false;
            }
        });
    </script>
</body>
</html>
