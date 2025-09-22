<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection with error checking
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to prevent encoding issues
$conn->set_charset("utf8");

$message = '';
$debug_info = '';

// Check if tables exist, if not create them
$tables_exist = $conn->query("SHOW TABLES LIKE 'assignments'")->num_rows > 0;

if (!$tables_exist) {
    // Create assignments table with unique constraint to prevent duplicates
    $create_assignments = "CREATE TABLE assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        subject_id INT NOT NULL,
        lesson_id INT DEFAULT NULL,
        due_date DATETIME NOT NULL,
        max_marks INT DEFAULT 100,
        instructions TEXT,
        attachment_path VARCHAR(500),
        status ENUM('draft', 'published', 'closed') DEFAULT 'draft',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_assignment (title, subject_id, created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    if (!$conn->query($create_assignments)) {
        die("Error creating assignments table: " . $conn->error);
    }
    
    // Create submissions table
    $create_submissions = "CREATE TABLE IF NOT EXISTS assignment_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assignment_id INT NOT NULL,
        student_id INT NOT NULL,
        submission_text TEXT,
        file_path VARCHAR(500),
        original_filename VARCHAR(255),
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        marks_obtained INT DEFAULT NULL,
        feedback TEXT,
        graded_by INT DEFAULT NULL,
        graded_at TIMESTAMP NULL,
        status ENUM('submitted', 'graded', 'late') DEFAULT 'submitted',
        UNIQUE KEY unique_submission (assignment_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    if (!$conn->query($create_submissions)) {
        die("Error creating submissions table: " . $conn->error);
    }
    
    $debug_info .= "Tables created successfully. ";
}

// Handle assignment deletion
if (isset($_POST['delete_assignment'])) {
    $assignment_id = intval($_POST['assignment_id']);
    
    // Get assignment title for confirmation
    $assignment_info = $conn->query("SELECT title FROM assignments WHERE id = $assignment_id");
    $assignment_title = $assignment_info ? $assignment_info->fetch_assoc()['title'] : 'Unknown';
    
    // Delete submissions first (foreign key constraint)
    $conn->query("DELETE FROM assignment_submissions WHERE assignment_id = $assignment_id");
    
    // Delete assignment
    $delete_sql = "DELETE FROM assignments WHERE id = $assignment_id";
    
    if ($conn->query($delete_sql)) {
        $message = '<div class="alert alert-success"><i class="fas fa-trash me-2"></i>Assignment "' . htmlspecialchars($assignment_title) . '" deleted successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger">Error deleting assignment: ' . $conn->error . '</div>';
    }
}

// Handle assignment creation with duplicate prevention
if (isset($_POST['create_assignment'])) {
    $debug_info .= "Form submitted. ";
    
    // Get form data with proper sanitization
    $title = $conn->real_escape_string(trim($_POST['title']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $subject_id = intval($_POST['subject_id']);
    $lesson_id = !empty($_POST['lesson_id']) ? intval($_POST['lesson_id']) : NULL;
    $due_date = $_POST['due_date'];
    $max_marks = intval($_POST['max_marks']);
    $instructions = $conn->real_escape_string(trim($_POST['instructions']));
    $status = $_POST['status'];
    $created_by = $_SESSION['user_id'];
    
    $debug_info .= "Data: title='$title', subject_id=$subject_id, created_by=$created_by. ";
    
    // Check for duplicate assignment (same title and subject by same user)
    $duplicate_check = $conn->query("SELECT id FROM assignments WHERE title = '$title' AND subject_id = $subject_id AND created_by = $created_by");
    
    if ($duplicate_check && $duplicate_check->num_rows > 0) {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>An assignment with this title already exists for the selected subject.</div>';
    } elseif (empty($title) || empty($description) || $subject_id <= 0 || empty($due_date)) {
        $message = '<div class="alert alert-danger">Please fill all required fields</div>';
    } else {
        // Handle file upload
        $attachment_path = '';
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $upload_dir = '../uploads/assignments/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'];
            
            if (in_array($file_extension, $allowed_types) && $_FILES['attachment']['size'] <= 25*1024*1024) {
                $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $unique_filename;
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                    $attachment_path = 'uploads/assignments/' . $unique_filename;
                    $debug_info .= "File uploaded. ";
                }
            }
        }
        
        // Insert assignment
        $lesson_id_sql = $lesson_id ? $lesson_id : 'NULL';
        $sql = "INSERT INTO assignments (title, description, subject_id, lesson_id, due_date, max_marks, instructions, attachment_path, status, created_by) 
                VALUES ('$title', '$description', $subject_id, $lesson_id_sql, '$due_date', $max_marks, '$instructions', '$attachment_path', '$status', $created_by)";
        
        $debug_info .= "SQL: " . substr($sql, 0, 100) . "... ";
        
        if ($conn->query($sql)) {
            $assignment_id = $conn->insert_id;
            $debug_info .= "Assignment inserted with ID: $assignment_id. ";
            
            // Verify the insert
            $verify = $conn->query("SELECT id, title FROM assignments WHERE id = $assignment_id");
            if ($verify && $verify->num_rows > 0) {
                $row = $verify->fetch_assoc();
                $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Assignment "' . htmlspecialchars($row['title']) . '" created successfully! (ID: ' . $assignment_id . ')</div>';
                $debug_info .= "Verification successful. ";
            } else {
                $message = '<div class="alert alert-warning">Assignment may not have been saved properly.</div>';
                $debug_info .= "Verification failed. ";
            }
        } else {
            if (strpos($conn->error, 'Duplicate entry') !== false) {
                $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>This assignment already exists. Please use a different title.</div>';
            } else {
                $message = '<div class="alert alert-danger">Database error: ' . $conn->error . '</div>';
            }
            $debug_info .= "Insert failed: " . $conn->error . ". ";
        }
    }
}

// Get subjects for dropdown
$subjects = $conn->query("SELECT id, name FROM subjects ORDER BY name ASC");

// Get assignments with proper grouping to prevent duplicates
$assignments_sql = "SELECT a.*, s.name as subject_name,
    (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count
    FROM assignments a 
    LEFT JOIN subjects s ON a.subject_id = s.id 
    GROUP BY a.id, a.title, a.description, a.subject_id, a.lesson_id, a.due_date, a.max_marks, a.instructions, a.attachment_path, a.status, a.created_by, a.created_at, a.updated_at, s.name
    ORDER BY a.created_at DESC";
    
$assignments = $conn->query($assignments_sql);

if (!$assignments) {
    $debug_info .= "Failed to load assignments: " . $conn->error . ". ";
    $assignments = false;
}

// Get assignment statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT a.id) as total_assignments,
    COUNT(DISTINCT CASE WHEN a.status = 'published' THEN a.id END) as published_count,
    COUNT(DISTINCT CASE WHEN a.status = 'draft' THEN a.id END) as draft_count,
    COUNT(DISTINCT CASE WHEN a.due_date < NOW() THEN a.id END) as overdue_count
    FROM assignments a";

$stats_result = $conn->query($stats_sql);
$assignment_stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total_assignments' => 0,
    'published_count' => 0,
    'draft_count' => 0,
    'overdue_count' => 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Management - Admin Panel</title>
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
        .assignment-card { transition: all 0.3s ease; }
        .debug-info { background: #e8f5e8; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-size: 0.9rem; border: 1px solid #4caf50; }
        .database-status { background: #fff3e0; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .nav-section { color: rgba(255, 255, 255, 0.6); font-size: 11px; font-weight: 600; text-transform: uppercase; padding: 15px 20px 5px; margin-bottom: 0; }
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
                            <a class="nav-link" href="dashboard.php">
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
                            <a class="nav-link active" href="assignments.php">
                                <i class="fas fa-tasks"></i>Assignments
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
                        <h1 class="h2"><i class="fas fa-tasks me-2 text-primary"></i>Assignment Management</h1>
                        <p class="text-muted">Create and manage assignments for students</p>
                    </div>
                    <div class="btn-toolbar">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                            <i class="fas fa-plus me-2"></i>Create Assignment
                        </button>
                    </div>
                </div>

                <?php echo $message; ?>

              

                <!-- Database Status -->
                <div class="database-status">
                    <strong>Database Status:</strong> 
                    Connected ✓ | 
                    Assignment Table: <?php echo $conn->query("SHOW TABLES LIKE 'assignments'")->num_rows > 0 ? 'Exists ✓' : 'Missing ✗'; ?> |
                    Total Records: <?php echo $assignment_stats['total_assignments']; ?>
                </div>

                <!-- Assignment Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body stat-card">
                                <i class="fas fa-tasks fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $assignment_stats['total_assignments']; ?></div>
                                <small>Total Assignments</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body stat-card">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $assignment_stats['published_count']; ?></div>
                                <small>Published</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body stat-card">
                                <i class="fas fa-edit fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $assignment_stats['draft_count']; ?></div>
                                <small>Drafts</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body stat-card">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $assignment_stats['overdue_count']; ?></div>
                                <small>Overdue</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assignments List -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>All Assignments 
                            (<?php echo $assignments ? $assignments->num_rows : 0; ?> found)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($assignments && $assignments->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Subject</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Marks</th>
                                        <th>Submissions</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($assignment = $assignments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $assignment['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($assignment['title']); ?></strong>
                                            <br><small class="text-muted"><?php echo substr(htmlspecialchars($assignment['description']), 0, 50); ?>...</small>
                                        </td>
                                        <td><?php echo htmlspecialchars($assignment['subject_name'] ?? 'No Subject'); ?></td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?>
                                            <br><small><?php echo date('g:i A', strtotime($assignment['due_date'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $assignment['status'] == 'published' ? 'success' : 
                                                    ($assignment['status'] == 'draft' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($assignment['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $assignment['max_marks']; ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $assignment['submission_count']; ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-info btn-sm" onclick="viewSubmissions(<?php echo $assignment['id']; ?>)" title="View Submissions">
                                                    <i class="fas fa-file-alt"></i>
                                                </button>
                                                <button class="btn btn-warning btn-sm" onclick="editAssignment(<?php echo $assignment['id']; ?>)" title="Edit Assignment">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['title']); ?>')" title="Delete Assignment">
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
                            <i class="fas fa-tasks fa-4x text-muted mb-3"></i>
                            <h4>No Assignments Found</h4>
                            <p class="text-muted">Create your first assignment to get started.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                                <i class="fas fa-plus me-2"></i>Create Assignment
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Assignment Modal -->
    <div class="modal fade" id="createAssignmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create New Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="assignmentForm">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Assignment titles must be unique within each subject.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Assignment Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" required 
                                           placeholder="Enter unique assignment title" maxlength="255">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" required 
                                              placeholder="Describe the assignment"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="instructions" class="form-label">Instructions</label>
                                    <textarea class="form-control" id="instructions" name="instructions" rows="3" 
                                              placeholder="Detailed instructions for students"></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="subject_id" class="form-label">Subject *</label>
                                    <select class="form-select" id="subject_id" name="subject_id" required>
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
                                    <label for="lesson_id" class="form-label">Related Lesson</label>
                                    <select class="form-select" id="lesson_id" name="lesson_id">
                                        <option value="">No specific lesson</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="due_date" class="form-label">Due Date *</label>
                                    <input type="datetime-local" class="form-control" id="due_date" name="due_date" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="max_marks" class="form-label">Maximum Marks</label>
                                    <input type="number" class="form-control" id="max_marks" name="max_marks" 
                                           value="100" min="1" max="1000">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="draft">Draft</option>
                                        <option value="published">Published</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="attachment" class="form-label">Attachment (Max 25MB)</label>
                            <input type="file" class="form-control" id="attachment" name="attachment" 
                                   accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip,.rar">
                            <small class="text-muted">Allowed formats: PDF, DOC, PPT, Images, ZIP, RAR</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_assignment" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Assignment
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
                    <p>Are you sure you want to delete the assignment:</p>
                    <p class="fw-bold text-danger" id="assignmentToDelete"></p>
                    <p class="text-muted">This will also delete all student submissions for this assignment.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" id="deleteForm" class="d-inline">
                        <input type="hidden" name="assignment_id" id="deleteAssignmentId">
                        <button type="submit" name="delete_assignment" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Yes, Delete Assignment
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set minimum due date
        document.addEventListener('DOMContentLoaded', function() {
            const dueDateInput = document.getElementById('due_date');
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            dueDateInput.min = tomorrow.toISOString().slice(0, 16);
        });

        function viewSubmissions(id) {
            window.location.href = 'assignment_submissions.php?id=' + id;
        }

        function editAssignment(id) {
            alert('Edit assignment functionality will be implemented. Assignment ID: ' + id);
        }

        // Delete confirmation function
        function confirmDelete(assignmentId, assignmentTitle) {
            document.getElementById('deleteAssignmentId').value = assignmentId;
            document.getElementById('assignmentToDelete').textContent = assignmentTitle;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            deleteModal.show();
        }

        // Form validation
        document.getElementById('assignmentForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const subject_id = document.getElementById('subject_id').value;
            const due_date = document.getElementById('due_date').value;
            
            if (!title || !description || !subject_id || !due_date) {
                e.preventDefault();
                alert('Please fill all required fields');
                return false;
            }
            
            console.log('Form validation passed');
        });
    </script>
</body>
</html>
