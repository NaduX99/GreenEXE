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

// Handle assignment submission
if (isset($_POST['submit_assignment'])) {
    $assignment_id = intval($_POST['assignment_id']);
    $submission_text = trim($_POST['submission_text']);
    $file_path = '';
    $original_filename = '';
    
    // Handle file upload
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == 0) {
        $upload_dir = '../uploads/submissions/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION);
        $unique_filename = $student_id . '_' . $assignment_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $unique_filename;
        
        if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $upload_path)) {
            $file_path = 'uploads/submissions/' . $unique_filename;
            $original_filename = $_FILES['submission_file']['name'];
        }
    }
    
    if (!empty($submission_text) || !empty($file_path)) {
        // Check if already submitted
        $check_submission = $conn->query("SELECT id FROM assignment_submissions WHERE assignment_id = $assignment_id AND student_id = $student_id");
        
        if ($check_submission->num_rows > 0) {
            // Update existing submission
            $stmt = $conn->prepare("UPDATE assignment_submissions SET submission_text = ?, file_path = ?, original_filename = ?, submitted_at = NOW() WHERE assignment_id = ? AND student_id = ?");
            $stmt->bind_param("sssii", $submission_text, $file_path, $original_filename, $assignment_id, $student_id);
        } else {
            // Insert new submission
            $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, file_path, original_filename) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $assignment_id, $student_id, $submission_text, $file_path, $original_filename);
        }
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Assignment submitted successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error submitting assignment.</div>';
        }
    } else {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Please provide either text submission or upload a file.</div>';
    }
}

// Get assignments with submission status
$assignments_query = "SELECT a.*, s.name as subject_name, l.title as lesson_title,
    sub.id as submission_id, sub.submitted_at, sub.marks_obtained, sub.feedback, sub.status as submission_status,
    CASE 
        WHEN a.due_date < NOW() AND sub.id IS NULL THEN 'overdue'
        WHEN sub.id IS NOT NULL THEN 'submitted'
        ELSE 'pending'
    END as assignment_status
    FROM assignments a 
    LEFT JOIN subjects s ON a.subject_id = s.id 
    LEFT JOIN lessons l ON a.lesson_id = l.id 
    LEFT JOIN assignment_submissions sub ON a.id = sub.assignment_id AND sub.student_id = $student_id
    WHERE a.status = 'published'
    ORDER BY a.due_date ASC";

$assignments = $conn->query($assignments_query);

if (!$assignments) {
    die("Error loading assignments: " . $conn->error);
}

// Get assignment statistics
$assignment_stats = $conn->query("SELECT 
    COUNT(CASE WHEN a.status = 'published' THEN 1 END) as total_assignments,
    COUNT(CASE WHEN sub.id IS NOT NULL THEN 1 END) as submitted_count,
    COUNT(CASE WHEN sub.status = 'graded' THEN 1 END) as graded_count,
    COUNT(CASE WHEN a.due_date < NOW() AND sub.id IS NULL THEN 1 END) as overdue_count
    FROM assignments a 
    LEFT JOIN assignment_submissions sub ON a.id = sub.assignment_id AND sub.student_id = $student_id
    WHERE a.status = 'published'")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments - Student Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
    background: linear-gradient(135deg, #1a202c 0%, #2d3748 50%, #1a365d 100%);
    min-height: 100vh;
    font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif; 
    color: #e2e8f0;
}

.header-section { 
    background: linear-gradient(135deg, #1a365d 0%, #2c5282 50%, #1a202c 100%);
    color: white; 
    padding: 60px 0;
    position: relative;
    overflow: hidden;
}

.header-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
}

.assignment-card { 
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 24px;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(0, 0, 0, 0.2) 100%);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    position: relative;
}
.modal-title{
    color: blueviolet;
}
.assignment-card::before {
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

.assignment-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.25);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, rgba(0, 0, 0, 0.1) 100%);
}

.assignment-card:hover::before {
    opacity: 1;
}

.status-badge { 
    font-size: 0.75rem;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.overdue-card { 
    border-left: 4px solid #374151;
    box-shadow: 0 0 20px rgba(55, 65, 81, 0.3);
    background: linear-gradient(135deg, rgba(55, 65, 81, 0.2) 0%, rgba(17, 24, 39, 0.3) 100%);
}

.submitted-card { 
    border-left: 4px solid #d1d5db;
    box-shadow: 0 0 20px rgba(209, 213, 219, 0.2);
    background: linear-gradient(135deg, rgba(209, 213, 219, 0.1) 0%, rgba(107, 114, 128, 0.2) 100%);
}

.pending-card { 
    border-left: 4px solid #6b7280;
    box-shadow: 0 0 20px rgba(107, 114, 128, 0.3);
    background: linear-gradient(135deg, rgba(107, 114, 128, 0.15) 0%, rgba(75, 85, 99, 0.25) 100%);
}

.stat-card { 
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(0, 0, 0, 0.2) 100%);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 32px 24px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    border: 1px solid rgba(255, 255, 255, 0.15);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.05), transparent);
    transform: rotate(45deg);
    transition: transform 0.6s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 48px rgba(0,0,0,0.4);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.18) 0%, rgba(0, 0, 0, 0.1) 100%);
}

.stat-card:hover::before {
    transform: rotate(45deg) translate(50%, 50%);
}

.stat-number { 
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #f8fafc, #cbd5e1, #94a3b8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 8px;
}

.navbar-student { 
    background: linear-gradient(135deg, rgba(17, 24, 39, 0.95) 0%, rgba(31, 41, 55, 0.95) 100%);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    padding: 16px 0;
}

.navbar-student .navbar-brand, 
.navbar-student .nav-link { 
    color: #f1f5f9 !important;
    font-weight: 500;
    transition: all 0.3s ease;
}

.navbar-student .nav-link:hover {
    color: #cbd5e1 !important;
    text-shadow: 0 0 8px rgba(203, 213, 225, 0.5);
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(17, 24, 39, 0.5);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #374151, #4b5563);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #4b5563, #6b7280);
}

/* Glow effects */
.glow-on-hover {
    animation: glow 2s ease-in-out infinite alternate;
}

@keyframes glow {
    from { box-shadow: 0 0 20px rgba(148, 163, 184, 0.3); }
    to { box-shadow: 0 0 30px rgba(203, 213, 225, 0.4); }
}

/* Text colors */
h1, h2, h3, h4, h5, h6 {
    color: #f8fafc;
}

p{
    color: black;
}
span, div {
    color: #e2e8f0;
}

.text-muted {
    color: #9ca3af !important;
}
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-student">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>Student Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="lessons.php">
                            <i class="fas fa-book me-1"></i>Lessons
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="assignments.php">
                            <i class="fas fa-tasks me-1"></i>Assignments
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="header-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-6 fw-bold mb-2">My Assignments</h1>
                    <p class="lead mb-0">Track and submit your assignments</p>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-tasks fa-6x opacity-25"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <?php echo $message; ?>

        <!-- Assignment Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-tasks fa-2x text-primary mb-2"></i>
                    <div class="stat-number text-primary"><?php echo $assignment_stats['total_assignments']; ?></div>
                    <small class="text-muted">Total Assignments</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <div class="stat-number text-success"><?php echo $assignment_stats['submitted_count']; ?></div>
                    <small class="text-muted">Submitted</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-star fa-2x text-warning mb-2"></i>
                    <div class="stat-number text-warning"><?php echo $assignment_stats['graded_count']; ?></div>
                    <small class="text-muted">Graded</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-clock fa-2x text-danger mb-2"></i>
                    <div class="stat-number text-danger"><?php echo $assignment_stats['overdue_count']; ?></div>
                    <small class="text-muted">Overdue</small>
                </div>
            </div>
        </div>

        

        <!-- Assignments List -->
        <?php if ($assignments && $assignments->num_rows > 0): ?>
        <div class="row">
            <?php while ($assignment = $assignments->fetch_assoc()): ?>
            <?php
            $is_overdue = $assignment['assignment_status'] == 'overdue';
            $is_submitted = $assignment['assignment_status'] == 'submitted';
            $is_graded = $assignment['submission_status'] == 'graded';
            $card_class = $is_overdue ? 'overdue-card' : ($is_submitted ? 'submitted-card' : 'pending-card');
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card assignment-card <?php echo $card_class; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="card-title mb-0"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                            <span class="badge bg-<?php 
                                echo $is_overdue ? 'danger' : ($is_submitted ? 'success' : 'warning'); 
                            ?> status-badge">
                                <?php echo ucfirst($assignment['assignment_status']); ?>
                            </span>
                        </div>
                        
                        <p class="card-text text-muted mb-2">
                            <?php echo substr(htmlspecialchars($assignment['description']), 0, 100) . '...'; ?>
                        </p>
                        
                        <div class="mb-2">
                            <small>
                                <i class="fas fa-book me-1 text-primary"></i>
                                <strong><?php echo htmlspecialchars($assignment['subject_name']); ?></strong>
                                <?php if ($assignment['lesson_title']): ?>
                                <br><i class="fas fa-bookmark me-1 text-info"></i>
                                <?php echo htmlspecialchars($assignment['lesson_title']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <div class="mb-2">
                            <small>
                                <i class="fas fa-calendar me-1 text-<?php echo $is_overdue ? 'danger' : 'warning'; ?>"></i>
                                Due: <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?>
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <small>
                                <i class="fas fa-star me-1 text-success"></i>
                                Max Marks: <?php echo $assignment['max_marks']; ?>
                                <?php if ($is_graded): ?>
                                <span class="text-primary"> | Obtained: <?php echo $assignment['marks_obtained']; ?></span>
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <?php if ($is_submitted): ?>
                        <div class="alert alert-success py-2">
                            <small>
                                <i class="fas fa-check me-1"></i>
                                Submitted on <?php echo date('M j, Y g:i A', strtotime($assignment['submitted_at'])); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($is_graded && !empty($assignment['feedback'])): ?>
                        <div class="alert alert-info py-2">
                            <small>
                                <strong>Feedback:</strong><br>
                                <?php echo htmlspecialchars($assignment['feedback']); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <button class="btn btn-primary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $assignment['id']; ?>">
                                <i class="fas fa-eye me-1"></i>View Details
                            </button>
                            <?php if (!$is_submitted && !$is_overdue): ?>
                            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#submitModal<?php echo $assignment['id']; ?>">
                                <i class="fas fa-upload me-1"></i>Submit
                            </button>
                            <?php elseif ($is_submitted && !$is_graded): ?>
                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#submitModal<?php echo $assignment['id']; ?>">
                                <i class="fas fa-edit me-1"></i>Re-submit
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Details Modal -->
            <div class="modal fade" id="viewModal<?php echo $assignment['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><?php echo htmlspecialchars($assignment['title']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6>Description</h6>
                                    <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                                    
                                    <?php if (!empty($assignment['instructions'])): ?>
                                    <h6>Instructions</h6>
                                    <p><?php echo nl2br(htmlspecialchars($assignment['instructions'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <h6>Assignment Details</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Subject:</strong> <?php echo htmlspecialchars($assignment['subject_name']); ?></li>
                                        <li><strong>Due Date:</strong> <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?></li>
                                        <li><strong>Max Marks:</strong> <?php echo $assignment['max_marks']; ?></li>
                                        <li><strong>Status:</strong> 
                                            <span class="badge bg-<?php echo $is_submitted ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($assignment['assignment_status']); ?>
                                            </span>
                                        </li>
                                    </ul>
                                    
                                    <?php if (!empty($assignment['attachment_path'])): ?>
                                    <h6>Assignment File</h6>
                                    <a href="../<?php echo $assignment['attachment_path']; ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-download me-1"></i>Download
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submission Modal -->
            <div class="modal fade" id="submitModal<?php echo $assignment['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Submit Assignment: <?php echo htmlspecialchars($assignment['title']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="modal-body">
                                <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                
                                <div class="mb-3">
                                    <label for="submission_text<?php echo $assignment['id']; ?>" class="form-label">Text Submission</label>
                                    <textarea class="form-control" id="submission_text<?php echo $assignment['id']; ?>" name="submission_text" rows="6" placeholder="Enter your submission text here..."></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="submission_file<?php echo $assignment['id']; ?>" class="form-label">File Upload</label>
                                    <input type="file" class="form-control" id="submission_file<?php echo $assignment['id']; ?>" name="submission_file" 
                                           accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip,.rar,.txt">
                                    <div class="form-text">Supported: PDF, DOC, PPT, Images, ZIP, TXT (Max: 10MB)</div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> You must provide either text submission or upload a file (or both).
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="submit_assignment" class="btn btn-success">
                                    <i class="fas fa-upload me-2"></i>Submit Assignment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-tasks fa-4x text-muted mb-3"></i>
            <h4>No Assignments Available</h4>
            <p class="text-muted">Your instructor hasn't posted any assignments yet, or you may need to check your enrollment status.</p>
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Back to Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File size validation
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const maxSize = 10 * 1024 * 1024; // 10MB
                if (this.files[0] && this.files[0].size > maxSize) {
                    alert('File size must be less than 10MB');
                    this.value = '';
                }
            });
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const textArea = this.querySelector('textarea[name="submission_text"]');
                const fileInput = this.querySelector('input[name="submission_file"]');
                
                if ((!textArea.value.trim()) && (!fileInput.files.length)) {
                    e.preventDefault();
                    alert('Please provide either text submission or upload a file.');
                }
            });
        });
    </script>
</body>
</html>
