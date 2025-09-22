<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$debug_info = '';

// Ensure meetings table exists with proper structure
$check_table = $conn->query("SHOW TABLES LIKE 'meetings'");
if ($check_table->num_rows == 0) {
    $create_meetings_table = "CREATE TABLE meetings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        subject_id INT DEFAULT NULL,
        lesson_id INT DEFAULT NULL,
        meeting_date DATETIME NOT NULL,
        duration INT DEFAULT 60,
        meeting_link VARCHAR(500),
        meeting_password VARCHAR(100),
        max_participants INT DEFAULT 50,
        status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
        created_by INT NOT NULL,
        approved_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    if ($conn->query($create_meetings_table)) {
        $debug_info .= "Meetings table created successfully. ";
    } else {
        $debug_info .= "Error creating meetings table: " . $conn->error . ". ";
    }
}

// Ensure participants table exists
$check_participants = $conn->query("SHOW TABLES LIKE 'meeting_participants'");
if ($check_participants->num_rows == 0) {
    $create_participants_table = "CREATE TABLE meeting_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        meeting_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP NULL,
        left_at TIMESTAMP NULL,
        attendance_status ENUM('registered', 'attended', 'absent') DEFAULT 'registered',
        UNIQUE KEY unique_participant (meeting_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    if ($conn->query($create_participants_table)) {
        $debug_info .= "Participants table created successfully. ";
    } else {
        $debug_info .= "Error creating participants table: " . $conn->error . ". ";
    }
}

// Handle meeting creation
if (isset($_POST['create_meeting'])) {
    $debug_info .= "Form submitted. ";
    
    // Get and sanitize form data
    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $subject_id = !empty($_POST['subject_id']) ? intval($_POST['subject_id']) : NULL;
    $lesson_id = !empty($_POST['lesson_id']) ? intval($_POST['lesson_id']) : NULL;
    $meeting_date = $_POST['meeting_date'];
    $duration = intval($_POST['duration']);
    $meeting_link = mysqli_real_escape_string($conn, trim($_POST['meeting_link']));
    $meeting_password = mysqli_real_escape_string($conn, trim($_POST['meeting_password']));
    $max_participants = intval($_POST['max_participants']);
    $status = 'approved'; // Admin meetings are auto-approved
    $created_by = $_SESSION['user_id'];
    
    $debug_info .= "Data: title='$title', date='$meeting_date', created_by=$created_by. ";
    
    // Validate required fields
    if (empty($title)) {
        $message = '<div class="alert alert-danger">Title is required</div>';
    } elseif (empty($meeting_date)) {
        $message = '<div class="alert alert-danger">Meeting date is required</div>';
    } else {
        // Build SQL with proper NULL handling
        $sql = "INSERT INTO meetings (title, description, subject_id, lesson_id, meeting_date, duration, meeting_link, meeting_password, max_participants, status, created_by, approved_by) VALUES (";
        $sql .= "'$title', '$description', ";
        $sql .= ($subject_id ? $subject_id : "NULL") . ", ";
        $sql .= ($lesson_id ? $lesson_id : "NULL") . ", ";
        $sql .= "'$meeting_date', $duration, '$meeting_link', '$meeting_password', $max_participants, '$status', $created_by, $created_by)";
        
        $debug_info .= "SQL: " . substr($sql, 0, 100) . "... ";
        
        if ($conn->query($sql)) {
            $meeting_id = $conn->insert_id;
            $debug_info .= "Meeting created with ID: $meeting_id. ";
            
            // Verify insertion
            $verify = $conn->query("SELECT id, title FROM meetings WHERE id = $meeting_id");
            if ($verify && $verify->num_rows > 0) {
                $row = $verify->fetch_assoc();
                $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Meeting "' . htmlspecialchars($row['title']) . '" created successfully! (ID: ' . $meeting_id . ')</div>';
                $debug_info .= "Verification successful. ";
            } else {
                $debug_info .= "Verification failed. ";
            }
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Database error: ' . $conn->error . '</div>';
            $debug_info .= "Insert failed: " . $conn->error . ". ";
        }
    }
}

// Handle meeting approval/rejection
if (isset($_POST['approve_meeting'])) {
    $meeting_id = intval($_POST['meeting_id']);
    $action = $_POST['action'];
    $admin_id = $_SESSION['user_id'];
    
    $sql = "UPDATE meetings SET status = '$action', approved_by = $admin_id WHERE id = $meeting_id";
    
    if ($conn->query($sql)) {
        $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Meeting ' . $action . ' successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error updating meeting status: ' . $conn->error . '</div>';
    }
}

// Handle meeting deletion
if (isset($_POST['delete_meeting'])) {
    $meeting_id = intval($_POST['meeting_id']);
    
    // Get meeting title for confirmation message
    $meeting_info = $conn->query("SELECT title FROM meetings WHERE id = $meeting_id");
    $meeting_title = $meeting_info ? $meeting_info->fetch_assoc()['title'] : 'Unknown';
    
    // First delete all participants for this meeting
    $delete_participants = "DELETE FROM meeting_participants WHERE meeting_id = $meeting_id";
    $conn->query($delete_participants);
    
    // Then delete the meeting
    $delete_meeting = "DELETE FROM meetings WHERE id = $meeting_id";
    
    if ($conn->query($delete_meeting)) {
        if ($conn->affected_rows > 0) {
            $message = '<div class="alert alert-success"><i class="fas fa-trash me-2"></i>Meeting "' . htmlspecialchars($meeting_title) . '" deleted successfully!</div>';
            $debug_info .= "Meeting ID $meeting_id deleted successfully. ";
        } else {
            $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Meeting not found or already deleted.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error deleting meeting: ' . $conn->error . '</div>';
        $debug_info .= "Delete failed: " . $conn->error . ". ";
    }
}

// Get subjects for dropdown
$subjects = $conn->query("SELECT id, name FROM subjects ORDER BY name ASC");

// Get all meetings with participant count
$meetings_query = "SELECT m.*, 
    COALESCE(s.name, 'No Subject') as subject_name, 
    COALESCE(l.title, 'No Lesson') as lesson_title, 
    creator.name as creator_name, 
    approver.name as approver_name,
    COUNT(mp.id) as participant_count
    FROM meetings m
    LEFT JOIN subjects s ON m.subject_id = s.id
    LEFT JOIN lessons l ON m.lesson_id = l.id
    LEFT JOIN users creator ON m.created_by = creator.id
    LEFT JOIN users approver ON m.approved_by = approver.id
    LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
    GROUP BY m.id, m.title, m.description, m.subject_id, m.lesson_id, m.meeting_date, m.duration, m.meeting_link, m.meeting_password, m.max_participants, m.status, m.created_by, m.approved_by, m.created_at, m.updated_at, s.name, l.title, creator.name, approver.name
    ORDER BY m.created_at DESC";

$meetings = $conn->query($meetings_query);

// Get meeting statistics
$meeting_stats_query = "SELECT 
    COUNT(*) as total_meetings,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count
    FROM meetings";

$stats_result = $conn->query($meeting_stats_query);
$meeting_stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total_meetings' => 0,
    'approved_count' => 0,
    'pending_count' => 0,
    'completed_count' => 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.9); padding: 12px 20px; margin: 2px 8px; border-radius: 8px; transition: all 0.3s ease; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255, 255, 255, 0.2); color: white; }
        .sidebar .nav-link i { width: 20px; text-align: center; margin-right: 10px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); }
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .sidebar-brand h4 { color: white; margin: 0; }
        .stat-card { text-align: center; padding: 20px; }
        .stat-number { font-size: 1.8rem; font-weight: bold; }
        .debug-info { background: #d1ecf1; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-size: 0.9rem; color: #0c5460; }
        .delete-btn:hover { background-color: #dc3545 !important; border-color: #dc3545 !important; }
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
                        <h1 class="h2"><i class="fas fa-video me-2 text-primary"></i>Meeting Management</h1>
                        <p class="text-muted">Create and manage virtual meetings for students</p>
                    </div>
                    <div class="btn-toolbar">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMeetingModal">
                            <i class="fas fa-plus me-2"></i>Create Meeting
                        </button>
                    </div>
                </div>

                <?php echo $message; ?>

                <!-- Debug Information -->
                

                <!-- Meeting Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body stat-card">
                                <i class="fas fa-video fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $meeting_stats['total_meetings']; ?></div>
                                <small>Total Meetings</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body stat-card">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $meeting_stats['approved_count']; ?></div>
                                <small>Approved</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body stat-card">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $meeting_stats['pending_count']; ?></div>
                                <small>Pending</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body stat-card">
                                <i class="fas fa-flag-checkered fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $meeting_stats['completed_count']; ?></div>
                                <small>Completed</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Meetings List -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>All Meetings (<?php echo $meetings ? $meetings->num_rows : 0; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($meetings && $meetings->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Subject</th>
                                        <th>Date & Time</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Participants</th>
                                        <th>Created By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($meeting = $meetings->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $meeting['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($meeting['title']); ?></strong>
                                            <?php if (!empty($meeting['description'])): ?>
                                            <br><small class="text-muted"><?php echo substr(htmlspecialchars($meeting['description']), 0, 50); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($meeting['subject_name']); ?></td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($meeting['meeting_date'])); ?>
                                            <br><small><?php echo date('g:i A', strtotime($meeting['meeting_date'])); ?></small>
                                        </td>
                                        <td><?php echo $meeting['duration']; ?> min</td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $meeting['status'] == 'approved' ? 'success' : 
                                                    ($meeting['status'] == 'pending' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($meeting['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $meeting['participant_count']; ?></span>
                                            <small class="text-muted">/ <?php echo $meeting['max_participants']; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($meeting['creator_name']); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($meeting['status'] == 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                                                    <input type="hidden" name="action" value="approved">
                                                    <button type="submit" name="approve_meeting" class="btn btn-success btn-sm" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                                                    <input type="hidden" name="action" value="rejected">
                                                    <button type="submit" name="approve_meeting" class="btn btn-warning btn-sm" title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-info btn-sm" onclick="viewMeeting(<?php echo $meeting['id']; ?>)" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($meeting['status'] == 'approved' && !empty($meeting['meeting_link'])): ?>
                                                <button class="btn btn-primary btn-sm" onclick="joinMeeting('<?php echo htmlspecialchars($meeting['meeting_link']); ?>')" title="Join Meeting">
                                                    <i class="fas fa-video"></i>
                                                </button>
                                                <?php endif; ?>
                                                
                                                <!-- Delete Button -->
                                                <button class="btn btn-danger btn-sm delete-btn" onclick="confirmDelete(<?php echo $meeting['id']; ?>, '<?php echo htmlspecialchars($meeting['title']); ?>')" title="Delete Meeting">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-video fa-4x text-muted mb-3"></i>
                            <h4>No Meetings Yet</h4>
                            <p class="text-muted">Create your first meeting to get started.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMeetingModal">
                                <i class="fas fa-plus me-2"></i>Create Meeting
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Meeting Modal -->
    <div class="modal fade" id="createMeetingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create New Meeting</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="meetingForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Meeting Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" required 
                                           placeholder="e.g., Mathematics - Chapter 5 Discussion" maxlength="255">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" 
                                              placeholder="Brief description of the meeting topic..."></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="meeting_link" class="form-label">Meeting Link</label>
                                    <input type="url" class="form-control" id="meeting_link" name="meeting_link" 
                                           placeholder="https://zoom.us/j/123456789 or https://meet.google.com/abc-defg-hij">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="meeting_password" class="form-label">Meeting Password</label>
                                    <input type="text" class="form-control" id="meeting_password" name="meeting_password" 
                                           placeholder="Optional meeting password">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="subject_id" class="form-label">Subject</label>
                                    <select class="form-select" id="subject_id" name="subject_id">
                                        <option value="">Select Subject</option>
                                        <?php 
                                        if ($subjects) {
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
                                    <label for="meeting_date" class="form-label">Meeting Date & Time *</label>
                                    <input type="datetime-local" class="form-control" id="meeting_date" name="meeting_date" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="duration" class="form-label">Duration (minutes)</label>
                                    <input type="number" class="form-control" id="duration" name="duration" 
                                           value="60" min="15" max="480">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="max_participants" class="form-label">Max Participants</label>
                                    <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                           value="50" min="2" max="500">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_meeting" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Meeting
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-warning me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone!
                    </div>
                    <p>Are you sure you want to delete the meeting:</p>
                    <p class="fw-bold text-danger" id="meetingToDelete"></p>
                    <p class="text-muted">This will also remove all participant registrations for this meeting.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" id="deleteForm" class="d-inline">
                        <input type="hidden" name="meeting_id" id="deleteMeetingId">
                        <button type="submit" name="delete_meeting" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Yes, Delete Meeting
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set minimum meeting date to current time
        document.addEventListener('DOMContentLoaded', function() {
            const meetingDateInput = document.getElementById('meeting_date');
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            meetingDateInput.min = now.toISOString().slice(0, 16);
        });

        // Form validation
        document.getElementById('meetingForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const meetingDate = document.getElementById('meeting_date').value;
            
            if (!title) {
                e.preventDefault();
                alert('Please enter a meeting title');
                return false;
            }
            if (!meetingDate) {
                e.preventDefault();
                alert('Please select a meeting date and time');
                return false;
            }
            
            console.log('Form submitted with:', {title, meetingDate});
        });

        // Delete confirmation function
        function confirmDelete(meetingId, meetingTitle) {
            document.getElementById('deleteMeetingId').value = meetingId;
            document.getElementById('meetingToDelete').textContent = meetingTitle;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            deleteModal.show();
        }

        // View meeting function
        function viewMeeting(meetingId) {
            // You can implement a detailed view modal here
            alert('View meeting details - ID: ' + meetingId + '\n\nDetailed view modal can be implemented here.');
        }

        // Join meeting function
        function joinMeeting(meetingLink) {
            if (meetingLink) {
                window.open(meetingLink, '_blank');
            } else {
                alert('Meeting link not available');
            }
        }
    </script>
</body>
</html>
