<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['user_id'];
$message = '';
$debug_info = '';

// Handle meeting request
if (isset($_POST['request_meeting'])) {
    $debug_info .= "Meeting request form submitted. ";
    
    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $subject_id = !empty($_POST['subject_id']) ? intval($_POST['subject_id']) : NULL;
    $lesson_id = !empty($_POST['lesson_id']) ? intval($_POST['lesson_id']) : NULL;
    $meeting_date = $_POST['meeting_date'];
    $duration = intval($_POST['duration']);
    $max_participants = intval($_POST['max_participants']);
    $status = 'pending';
    $created_by = $student_id;
    
    $debug_info .= "Data: title='$title', date='$meeting_date', student_id=$student_id. ";
    
    if (empty($title) || empty($description) || empty($meeting_date)) {
        $message = '<div class="alert alert-danger">Please fill all required fields (Title, Description, Date)</div>';
    } else {
        // Build SQL with proper NULL handling
        $sql = "INSERT INTO meetings (title, description, subject_id, lesson_id, meeting_date, duration, max_participants, status, created_by) VALUES (";
        $sql .= "'$title', '$description', ";
        $sql .= ($subject_id ? $subject_id : "NULL") . ", ";
        $sql .= ($lesson_id ? $lesson_id : "NULL") . ", ";
        $sql .= "'$meeting_date', $duration, $max_participants, '$status', $created_by)";
        
        $debug_info .= "SQL: " . substr($sql, 0, 100) . "... ";
        
        if ($conn->query($sql)) {
            $request_id = $conn->insert_id;
            $debug_info .= "Meeting request created with ID: $request_id. ";
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Meeting request submitted successfully! (ID: ' . $request_id . ')</div>';
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error submitting request: ' . $conn->error . '</div>';
            $debug_info .= "Insert failed: " . $conn->error . ". ";
        }
    }
}

// Handle meeting registration
if (isset($_POST['register_meeting'])) {
    $meeting_id = intval($_POST['meeting_id']);
    
    $check_registration = $conn->query("SELECT id FROM meeting_participants WHERE meeting_id = $meeting_id AND user_id = $student_id");
    
    if ($check_registration->num_rows == 0) {
        $sql = "INSERT INTO meeting_participants (meeting_id, user_id, attendance_status) VALUES ($meeting_id, $student_id, 'registered')";
        
        if ($conn->query($sql)) {
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Successfully registered for the meeting!</div>';
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error registering: ' . $conn->error . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>You are already registered for this meeting.</div>';
    }
}

// Debug database information
$tables_exist = $conn->query("SHOW TABLES LIKE 'meetings'")->num_rows > 0;
$debug_info .= "Meetings table exists: " . ($tables_exist ? 'YES' : 'NO') . ". ";

if ($tables_exist) {
    $total_meetings = $conn->query("SELECT COUNT(*) as count FROM meetings")->fetch_assoc()['count'];
    $approved_meetings_count = $conn->query("SELECT COUNT(*) as count FROM meetings WHERE status = 'approved'")->fetch_assoc()['count'];
    $debug_info .= "Total: $total_meetings, Approved: $approved_meetings_count. ";
}

// Get subjects for dropdown
$subjects = $conn->query("SELECT id, name FROM subjects ORDER BY name ASC");

// Get approved meetings
$approved_meetings_query = "SELECT m.*, 
    COALESCE(s.name, 'General') as subject_name, 
    creator.name as creator_name,
    creator.role as creator_role
    FROM meetings m
    LEFT JOIN subjects s ON m.subject_id = s.id
    LEFT JOIN users creator ON m.created_by = creator.id
    WHERE m.status = 'approved'
    ORDER BY m.meeting_date ASC";

$approved_meetings = $conn->query($approved_meetings_query);

if (!$approved_meetings) {
    $debug_info .= "Query error: " . $conn->error . ". ";
}

// Get student's meeting requests
$my_meetings_query = "SELECT m.*, s.name as subject_name, approver.name as approver_name
    FROM meetings m
    LEFT JOIN subjects s ON m.subject_id = s.id
    LEFT JOIN users approver ON m.approved_by = approver.id
    WHERE m.created_by = $student_id
    ORDER BY m.created_at DESC";

$my_meetings = $conn->query($my_meetings_query);

// Calculate statistics
$meeting_stats = [
    'available_meetings' => $approved_meetings ? $approved_meetings->num_rows : 0,
    'my_requests' => $my_meetings ? $my_meetings->num_rows : 0,
    'my_approved' => 0,
    'registered_meetings' => 0
];

// Get registered meetings count
$registered_result = $conn->query("SELECT COUNT(*) as count FROM meeting_participants WHERE user_id = $student_id");
$meeting_stats['registered_meetings'] = $registered_result ? $registered_result->fetch_assoc()['count'] : 0;

// Get approved requests count
$approved_requests = $conn->query("SELECT COUNT(*) as count FROM meetings WHERE created_by = $student_id AND status = 'approved'");
$meeting_stats['my_approved'] = $approved_requests ? $approved_requests->fetch_assoc()['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meetings - Student Portal</title>
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
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border-right: 1px solid rgba(255, 255, 255, 0.25);
            padding: 0;
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
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, transparent 50%, rgba(255, 255, 255, 0.05) 100%);
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            color: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, transparent 50%, rgba(255, 255, 255, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.18);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.9;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        .stat-card .label {
            font-size: 0.9rem;
            opacity: 0.8;
            font-weight: 500;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .content-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            padding: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.25);
        }

        .content-card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.08);
        }

        .content-card-header h5 {
            margin: 0;
            color: white;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        .content-card-body {
            padding: 2rem;
            color: white;
        }

        .lesson-item, .meeting-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .lesson-item:hover, .meeting-item:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(5px);
        }

        .lesson-item:last-child, .meeting-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .quick-action-btn {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .quick-action-btn:hover {
            background: rgba(255, 255, 255, 0.18);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
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

        .badge {
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-shadow: none;
        }

        /* Enhanced responsive design */
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

            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            body {
                background-attachment: scroll;
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

        /* Loading animation for background */
        .bg-loading {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { opacity: 0.8; }
            50% { opacity: 1; }
            100% { opacity: 0.8; }
        }

        /* Enhanced glassmorphism effects */
        .glass-effect {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        /* Meeting specific styles */
        .meeting-card { 
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 24px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            position: relative;
        }

        .meeting-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .meeting-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.25);
            background: rgba(255, 255, 255, 0.18);
        }

        .meeting-card:hover::before {
            opacity: 1;
        }

        .approved-card { 
            border-left: 4px solid #d1d5db;
            box-shadow: 0 0 20px rgba(209, 213, 219, 0.2);
            background: rgba(209, 213, 219, 0.1);
        }

        .admin-created { 
            border-left: 4px solid #6b7280;
            box-shadow: 0 0 20px rgba(107, 114, 128, 0.3);
            background: rgba(107, 114, 128, 0.15);
        }

        .debug-info { 
            background: rgba(55, 65, 81, 0.3);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            color: #cbd5e1;
            border: 1px solid rgba(107, 114, 128, 0.3);
        }

        /* Navbar styling */
        .navbar-student { 
            background: rgba(17, 24, 39, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            padding: 16px 0;
        }

        .navbar-student .nav-link { 
            color: #f1f5f9 !important;
            font-weight: 500;
            padding: 12px 24px;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .navbar-student .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            transform: scale(0);
            transition: transform 0.3s ease;
        }

        .navbar-student .nav-link:hover::before,
        .navbar-student .nav-link.active::before {
            transform: scale(1);
        }

        .navbar-student .nav-link:hover, 
        .navbar-student .nav-link.active { 
            color: #ffffff !important;
            text-shadow: 0 0 8px rgba(255, 255, 255, 0.3);
        }

        .navbar-student .navbar-brand { 
            color: #f8fafc !important;
            font-size: 1.5rem;
            font-weight: 800;
        }
    </style>
</head>
<body>


    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h4><i class="fas fa-graduation-cap me-2"></i>LearnArena</h4>
                <small>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></small>
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
                        <a href="meetings.php" class="nav-link active">
                            <i class="fas fa-video"></i>Meetings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="chat.php" class="nav-link">
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
                <h1><i class="fas fa-video me-3 text-info"></i>Virtual Meetings</h1>
                <p>Join approved meetings and request new ones</p>
            </div>

            <?php echo $message; ?>


            <!-- Meeting Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-video text-primary"></i></div>
                    <div class="number"><?php echo $meeting_stats['available_meetings']; ?></div>
                    <div class="label">Available Meetings</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-user-check text-success"></i></div>
                    <div class="number"><?php echo $meeting_stats['registered_meetings']; ?></div>
                    <div class="label">Registered</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-paper-plane text-warning"></i></div>
                    <div class="number"><?php echo $meeting_stats['my_requests']; ?></div>
                    <div class="label">My Requests</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-check-circle text-info"></i></div>
                    <div class="number"><?php echo $meeting_stats['my_approved']; ?></div>
                    <div class="label">My Approved</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="content-card mb-4">
                <div class="content-card-header">
                    <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="content-card-body">
                    <div class="quick-actions">
                        <button class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#requestMeetingModal">
                            <i class="fas fa-plus fa-2x mb-2 d-block"></i>
                            Request Meeting
                        </button>
                        <button class="quick-action-btn" onclick="showMyRequests()">
                            <i class="fas fa-list fa-2x mb-2 d-block"></i>
                            My Requests
                        </button>
                        <button class="quick-action-btn" onclick="showAvailableMeetings()">
                            <i class="fas fa-video fa-2x mb-2 d-block"></i>
                            Available Meetings
                        </button>
                    </div>
                </div>
            </div>

            <!-- Available Meetings Section -->
            <div id="availableMeetingsSection">
                <div class="content-card mb-4">
                    <div class="content-card-header">
                        <h5><i class="fas fa-video me-2"></i>Available Meetings (<?php echo $approved_meetings ? $approved_meetings->num_rows : 0; ?>)</h5>
                    </div>
                    <div class="content-card-body">
                        <?php if ($approved_meetings && $approved_meetings->num_rows > 0): ?>
                        <div class="row">
                            <?php while ($meeting = $approved_meetings->fetch_assoc()): ?>
                            <?php $card_class = $meeting['creator_role'] == 'admin' ? 'admin-created' : 'approved-card'; ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="meeting-card <?php echo $card_class; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title"><?php echo htmlspecialchars($meeting['title']); ?></h6>
                                            <div>
                                                <span class="badge bg-success">Available</span>
                                                <?php if ($meeting['creator_role'] == 'admin'): ?>
                                                <br><span class="badge bg-primary mt-1">Official</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($meeting['description'])): ?>
                                        <p class="card-text small text-muted mb-2">
                                            <?php echo substr(htmlspecialchars($meeting['description']), 0, 100) . '...'; ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <div class="mb-2">
                                            <small>
                                                <?php if ($meeting['subject_name'] && $meeting['subject_name'] != 'General'): ?>
                                                <i class="fas fa-book me-1 text-primary"></i>
                                                <strong><?php echo htmlspecialchars($meeting['subject_name']); ?></strong><br>
                                                <?php endif; ?>
                                                <i class="fas fa-user me-1 text-secondary"></i>
                                                Host: <?php echo htmlspecialchars($meeting['creator_name']); ?>
                                                <?php if ($meeting['creator_role'] == 'admin'): ?>
                                                <span class="badge bg-primary ms-1">Admin</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <small>
                                                <i class="fas fa-calendar me-1 text-warning"></i>
                                                <?php echo date('M j, Y g:i A', strtotime($meeting['meeting_date'])); ?><br>
                                                <i class="fas fa-clock me-1 text-info"></i>
                                                Duration: <?php echo $meeting['duration']; ?> minutes
                                            </small>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                                                <button type="submit" name="register_meeting" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-user-plus me-1"></i>Register
                                                </button>
                                            </form>
                                            <?php if (!empty($meeting['meeting_link'])): ?>
                                            <button class="btn btn-success btn-sm ms-1" onclick="joinMeeting('<?php echo htmlspecialchars($meeting['meeting_link']); ?>')">
                                                <i class="fas fa-video me-1"></i>Join
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-video fa-3x text-muted mb-3"></i>
                            <h5>No Available Meetings</h5>
                            <p class="text-muted">No meetings are currently available.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestMeetingModal">
                                <i class="fas fa-plus me-2"></i>Request a Meeting
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- My Requests Section -->
            <div id="myRequestsSection" style="display: none;">
                <div class="content-card">
                    <div class="content-card-header">
                        <h5><i class="fas fa-list me-2"></i>My Meeting Requests (<?php echo $my_meetings ? $my_meetings->num_rows : 0; ?>)</h5>
                    </div>
                    <div class="content-card-body">
                        <?php if ($my_meetings && $my_meetings->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Date & Time</th>
                                        <th>Status</th>
                                        <th>Approved By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($meeting = $my_meetings->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $meeting['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($meeting['title']); ?></strong>
                                            <?php if (!empty($meeting['description'])): ?>
                                            <br><small class="text-muted"><?php echo substr(htmlspecialchars($meeting['description']), 0, 50); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($meeting['meeting_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $meeting['status'] == 'approved' ? 'success' : 
                                                    ($meeting['status'] == 'pending' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($meeting['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($meeting['approver_name'] ?? 'Pending'); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-paper-plane fa-3x text-muted mb-3"></i>
                            <h5>No Meeting Requests</h5>
                            <p class="text-muted">You haven't requested any meetings yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <!-- Request Meeting Modal -->
    <div class="modal fade" id="requestMeetingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 style="color:black !important;" class="modal-title"><i class="fas fa-plus me-2"></i>Request New Meeting</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="requestForm">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Your meeting request will be sent to administrators for approval.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label style="color:black !important;" for="title" class="form-label">Meeting Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" required 
                                           placeholder="e.g., Study Group - Mathematics Chapter 5">
                                </div>
                                
                                <div class="mb-3">
                                    <label style="color:black !important;"  for="description" class="form-label">Description/Purpose *</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required
                                              placeholder="Explain the purpose of this meeting..."></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label style="color:black !important;"  for="subject_id" class="form-label">Subject</label>
                                    <select class="form-select" id="subject_id" name="subject_id">
                                        <option value="">Select Subject</option>
                                        <?php 
                                        if ($subjects) {
                                            $subjects->data_seek(0);
                                            while ($subject = $subjects->fetch_assoc()): 
                                        ?>
                                        <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                                        <?php 
                                            endwhile;
                                        } 
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label style="color:black !important;"  for="meeting_date" class="form-label">Preferred Date & Time *</label>
                                    <input type="datetime-local" class="form-control" id="meeting_date" name="meeting_date" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label style="color:black !important;" for="duration" class="form-label">Duration (minutes)</label>
                                    <input type="number" class="form-control" id="duration" name="duration" 
                                           value="60" min="15" max="240">
                                </div>
                                
                                <div class="mb-3">
                                    <label style="color:black !important;"  for="max_participants" class="form-label">Expected Participants</label>
                                    <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                           value="10" min="2" max="100">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="request_meeting" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const meetingDateInput = document.getElementById('meeting_date');
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            meetingDateInput.min = now.toISOString().slice(0, 16);
        });

        // Form validation
        document.getElementById('requestForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const meetingDate = document.getElementById('meeting_date').value;
            
            if (!title || !description || !meetingDate) {
                e.preventDefault();
                alert('Please fill all required fields');
                return false;
            }
            
            console.log('Form submitted with:', {title, description, meetingDate});
        });

        function showAvailableMeetings() {
            document.getElementById('availableMeetingsSection').style.display = 'block';
            document.getElementById('myRequestsSection').style.display = 'none';
        }

        function showMyRequests() {
            document.getElementById('availableMeetingsSection').style.display = 'none';
            document.getElementById('myRequestsSection').style.display = 'block';
        }

        function joinMeeting(meetingLink) {
            if (meetingLink) {
                window.open(meetingLink, '_blank');
            } else {
                alert('Meeting link not available yet.');
            }
        }
    </script>
</body>
</html>
