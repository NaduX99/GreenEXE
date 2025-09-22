<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

$student_id = $_SESSION['user_id'];

// Create chat_messages table if it doesn't exist
$create_chat_table = "CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'system') DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";
$conn->query($create_chat_table);

// Handle new message
if (isset($_POST['send_message']) && !empty(trim($_POST['message']))) {
    $message = trim($_POST['message']);
    
    $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, message) VALUES (?, ?)");
    $stmt->bind_param("is", $student_id, $message);
    $stmt->execute();
}

// Get recent messages (last 50)
$messages = $conn->query("SELECT cm.*, u.name as user_name, u.role 
    FROM chat_messages cm 
    JOIN users u ON cm.user_id = u.id 
    ORDER BY cm.created_at DESC 
    LIMIT 50");

// Get online users (active in last 10 minutes)
$online_users = $conn->query("SELECT DISTINCT u.name, u.role 
    FROM users u 
    JOIN chat_messages cm ON u.id = cm.user_id 
    WHERE cm.created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) 
    ORDER BY u.name ASC");

// Count total messages
$message_count = $conn->query("SELECT COUNT(*) as count FROM chat_messages")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Room - Learning Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Dashboard CSS Styles with Background Image */
        body { 
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background: url('../1.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            position: relative;
        }

        /* Background overlay for better readability */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border-right: 1px solid rgba(255, 255, 255, 0.25);
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        }

        .sidebar-header {
            padding: 2rem 1.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            text-align: center;
            background: rgba(255, 255, 255, 0.08);
        }

        .sidebar-header h4 {
            color: white;
            margin: 0;
            font-weight: 700;
            font-size: 1.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .sidebar-header small {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.8rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .nav-section {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 1.5rem 1.5rem 0.5rem;
            margin: 0;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav .nav-item {
            margin: 0.2rem 1rem;
        }

        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            padding: 0.8rem 1rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        .sidebar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .sidebar-nav .nav-link.active {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .sidebar-nav .nav-link i {
            width: 20px;
            margin-right: 0.8rem;
            text-align: center;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 2rem;
            overflow-x: hidden;
        }

        .welcome-header {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }

        .welcome-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, transparent 50%, rgba(255, 255, 255, 0.05) 100%);;
            pointer-events: none;
        }

        .welcome-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        .welcome-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        /* Chat Room Specific Styles */
        .chat-container {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chat-messages-container {
            flex: 2;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            height: 600px;
        }

        .chat-sidebar {
            flex: 1;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            height: 600px;
        }

        .chat-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.08);
        }

        .chat-header h5 {
            margin: 0;
            color: white;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message {
            max-width: 80%;
            padding: 1rem 1.5rem;
            border-radius: 18px;
            position: relative;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-incoming {
            align-self: flex-start;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }

        .message-outgoing {
            align-self: flex-end;
            background: rgba(59, 130, 246, 0.3);
            border: 1px solid rgba(96, 165, 250, 0.4);
            color: white;
        }

        .message-system {
            align-self: center;
            background: rgba(139, 92, 246, 0.2);
            border: 1px solid rgba(167, 139, 250, 0.3);
            color: rgba(255, 255, 255, 0.9);
            font-style: italic;
            max-width: 90%;
            text-align: center;
            font-size: 0.9rem;
        }

        .message-sender {
            font-weight: 600;
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.5rem;
            text-align: right;
        }

        .message-input-container {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.08);
        }

        .online-users-list {
            padding: 1.5rem;
            flex: 1;
            overflow-y: auto;
        }

        .online-user {
            display: flex;
            align-items: center;
            padding: 0.8rem 1rem;
            margin-bottom: 0.8rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .online-user:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-weight: 600;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            color: white;
            font-weight: 500;
            margin: 0;
            font-size: 0.95rem;
        }

        .user-status {
            font-size: 0.8rem;
            opacity: 0.8;
            display: flex;
            align-items: center;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .status-online {
            background: #10b981;
            box-shadow: 0 0 8px #10b981;
        }

        .status-offline {
            background: #6b7280;
        }

        .status-busy {
            background: #ef4444;
        }

        .chat-info {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }

        .chat-guidelines {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .guideline-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .guideline-item:last-child {
            margin-bottom: 0;
        }

        .guideline-icon {
            color: rgba(96, 165, 250, 0.9);
            margin-right: 0.8rem;
            font-size: 0.9rem;
            margin-top: 0.2rem;
        }

        .guideline-text {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            margin: 0;
            flex: 1;
        }

        /* Text styles with better readability */
        h1, h2, h3, h4, h5, h6 {
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        p, span, div, small {
            color: rgba(255, 255, 255, 0.9);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        .text-muted {
            color: rgba(255, 255, 255, 0.7) !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        /* Enhanced responsive design */
        @media (max-width: 992px) {
            .chat-container {
                flex-direction: column;
            }
            
            .chat-messages-container,
            .chat-sidebar {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1000;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            body {
                background-attachment: scroll;
            }
            
            .message {
                max-width: 90%;
            }
        }

        /* Custom Scrollbar with enhanced styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        /* Enhanced glassmorphism effects */
        .glass-effect {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }
        
        /* Form styling */
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 12px;
            padding: 0.8rem 1rem;
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(96, 165, 250, 0.6);
            box-shadow: 0 0 0 0.25rem rgba(96, 165, 250, 0.25);
            color: white;
        }
        
        .btn-primary {
            background: rgba(59, 130, 246, 0.3);
            border-color: rgba(96, 165, 250, 0.4);
            border-radius: 12px;
            padding: 0.8rem 1.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: rgba(59, 130, 246, 0.4);
            border-color: rgba(96, 165, 250, 0.6);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h4><i class="fas fa-graduation-cap me-2"></i>LearnArena</h4>
                <small>Welcome, Student Name</small>
            </div>
            
            <nav>
                <ul class="sidebar-nav">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <i class="fas fa-home"></i>Dashboard
                        </a>
                    </li>
                    
                    <div class="nav-section">Learning</div>
                    <li class="nav-item">
                        <a href="lessons.php" class="nav-link">
                            <i class="fas fa-book-open"></i>Lessons
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="quizzes.php" class="nav-link">
                            <i class="fas fa-question-circle"></i>Quizzes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="assignments.php" class="nav-link">
                            <i class="fas fa-tasks"></i>Assignments
                        </a>
                    </li>
                    
                    <div class="nav-section">Communication</div>
                    <li class="nav-item">
                        <a href="meetings.php" class="nav-link">
                            <i class="fas fa-video"></i>Meetings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="chat.php" class="nav-link active">
                            <i class="fas fa-comments"></i>Chat Room
                        </a>
                    </li>
                    
                    <div class="nav-section">Progress</div>
                    <li class="nav-item">
                        <a href="leaderboard.php" class="nav-link">
                            <i class="fas fa-trophy"></i>Leaderboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="certificates.php" class="nav-link">
                            <i class="fas fa-certificate"></i>Certificates
                        </a>
                    </li>
                    
                    <div class="nav-section">Account</div>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link">
                            <i class="fas fa-user"></i>Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Welcome Header -->
            <div class="welcome-header">
                <h1><i class="fas fa-comments me-3 text-info"></i>Chat Room</h1>
                <p>Connect with fellow students and instructors in real-time.</p>
            </div>

            <!-- Chat Container -->
            <div class="chat-container">
                <!-- Chat Messages -->
                <div class="chat-messages-container">
                    <div class="chat-header">
                        <h5><i class="fas fa-comments me-2"></i>Group Discussion</h5>
                        <div>
                            <button class="btn btn-sm btn-outline-light me-2" id="clearChat">
                                <i class="fas fa-trash-alt me-1"></i>Clear
                            </button>
                            <button class="btn btn-sm btn-outline-light" id="refreshChat">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                    
                    <div class="messages-container" id="messagesContainer">
                        <!-- System message -->
                        <div class="message message-system">
                            <div>Welcome to the chat room! Be respectful and keep conversations relevant to learning.</div>
                        </div>
                    </div>
                    
                    <div class="message-input-container">
                        <form id="messageForm">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Type your message..." id="messageInput">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Online Users Sidebar -->
                <div class="chat-sidebar">
                    <div class="chat-header">
                        <h5><i class="fas fa-users me-2"></i>Online Users</h5>
                        <span class="badge bg-info">6 online</span>
                    </div>
                    
                    <div class="online-users-list">
                        <!-- Online user -->
                        <div class="online-user">
                            <div class="user-avatar">DS</div>
                            <div class="user-info">
                                <div class="user-name">Dr. Smith</div>
                                <div class="user-status">
                                    <span class="status-indicator status-online"></span>
                                    <span>Instructor</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Online user -->
                        <div class="online-user">
                            <div class="user-avatar">SJ</div>
                            <div class="user-info">
                                <div class="user-name">Sarah Johnson</div>
                                <div class="user-status">
                                    <span class="status-indicator status-online"></span>
                                    <span>Online</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Online user -->
                        <div class="online-user">
                            <div class="user-avatar">MJ</div>
                            <div class="user-info">
                                <div class="user-name">Michael Johnson</div>
                                <div class="user-status">
                                    <span class="status-indicator status-online"></span>
                                    <span>Online</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Online user -->
                        <div class="online-user">
                            <div class="user-avatar">EP</div>
                            <div class="user-info">
                                <div class="user-name">Emma Parker</div>
                                <div class="user-status">
                                    <span class="status-indicator status-busy"></span>
                                    <span>Busy</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Offline user -->
                        <div class="online-user">
                            <div class="user-avatar">RJ</div>
                            <div class="user-info">
                                <div class="user-name">Robert Jones</div>
                                <div class="user-status">
                                    <span class="status-indicator status-offline"></span>
                                    <span>Offline</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chat-info">
                        <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Chat Guidelines</h6>
                        
                        <div class="chat-guidelines">
                            <div class="guideline-item">
                                <div class="guideline-icon"><i class="fas fa-check-circle"></i></div>
                                <p class="guideline-text">Be respectful to all participants</p>
                            </div>
                            <div class="guideline-item">
                                <div class="guideline-icon"><i class="fas fa-check-circle"></i></div>
                                <p class="guideline-text">Keep conversations relevant to learning topics</p>
                            </div>
                            <div class="guideline-item">
                                <div class="guideline-icon"><i class="fas fa-check-circle"></i></div>
                                <p class="guideline-text">Use appropriate language at all times</p>
                            </div>
                            <div class="guideline-item">
                                <div class="guideline-icon"><i class="fas fa-check-circle"></i></div>
                                <p class="guideline-text">Ask questions and help others when possible</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }

        // Add mobile menu button
        if (window.innerWidth <= 768) {
            const mobileMenuBtn = document.createElement('button');
            mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            mobileMenuBtn.className = 'btn btn-primary position-fixed';
            mobileMenuBtn.style.cssText = 'top: 1rem; left: 1rem; z-index: 1001; backdrop-filter: blur(10px);';
            mobileMenuBtn.onclick = toggleSidebar;
            document.body.appendChild(mobileMenuBtn);
        }

        // Chat functionality
        document.addEventListener('DOMContentLoaded', function() {
            const messageForm = document.getElementById('messageForm');
            const messageInput = document.getElementById('messageInput');
            const messagesContainer = document.getElementById('messagesContainer');
            const refreshButton = document.getElementById('refreshChat');
            const clearButton = document.getElementById('clearChat');
            
        
            
            // Add message to chat
            function addMessage(message, type, sender, role, time) {
                const messageEl = document.createElement('div');
                
                if (type === 'system') {
                    messageEl.className = 'message message-system';
                    messageEl.innerHTML = `<div>${message}</div>`;
                } else {
                    messageEl.className = `message message-${type}`;
                    
                    let senderHtml = '';
                    if (type === 'incoming' && sender) {
                        senderHtml = `<div class="message-sender">${sender}${role ? ' <span class="badge bg-success ms-1">' + role + '</span>' : ''}</div>`;
                    }
                    
                    messageEl.innerHTML = `
                        ${senderHtml}
                        <div>${message}</div>
                        <div class="message-time">${time || getCurrentTime()}</div>
                    `;
                }
                
                messagesContainer.appendChild(messageEl);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
            
            // Get current time in HH:MM AM/PM format
            function getCurrentTime() {
                const now = new Date();
                let hours = now.getHours();
                let minutes = now.getMinutes();
                const ampm = hours >= 12 ? 'PM' : 'AM';
                
                hours = hours % 12;
                hours = hours ? hours : 12; // Convert 0 to 12
                minutes = minutes < 10 ? '0' + minutes : minutes;
                
                return `${hours}:${minutes} ${ampm}`;
            }
            
            // Handle message form submission
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const message = messageInput.value.trim();
                if (message) {
                    addMessage(message, 'outgoing', null, null);
                    messageInput.value = '';
                    
                    // Simulate a response after a short delay
                    setTimeout(() => {
                        // Randomly select a response type
                        const random = Math.random();
                        if (random < 0.3) {
                            addMessage('Thanks for your message!', 'incoming', 'System', null, getCurrentTime());
                        } else if (random < 0.6) {
                            addMessage('That\'s a good question. Let me think about it.', 'incoming', 'Dr. Smith', 'Instructor', getCurrentTime());
                        } else {
                            addMessage('I was wondering about that too!', 'incoming', 'Sarah Johnson', null, getCurrentTime());
                        }
                    }, 1000 + Math.random() * 2000);
                }
            });
            
            // Refresh chat with sample messages
            refreshButton.addEventListener('click', function() {
                sampleMessages.forEach(msg => {
                    addMessage(
                        msg.content, 
                        msg.type, 
                        msg.sender, 
                        msg.role, 
                        msg.time
                    );
                });
            });
            
            // Clear chat
            clearButton.addEventListener('click', function() {
                while (messagesContainer.firstChild) {
                    messagesContainer.removeChild(messagesContainer.firstChild);
                }
                
                // Add system message back
                addMessage('Welcome to the chat room! Be respectful and keep conversations relevant to learning.', 'system');
            });
            
            // Focus on message input
            messageInput.focus();
        });
    </script>
</body>
</html>