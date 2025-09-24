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
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .header-section {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            margin: 30px 0;
            padding: 40px;
            color: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
            color: white;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.18);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .assignment-card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: none;
            color: white;
        }

        .assignment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.18);
        }

        .card-title {
            font-weight: 700;
            color: white;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 0.95);
        }

        .modal-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            padding: 20px 25px;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            padding: 20px 25px;
        }

        .btn {
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #4361ee;
            border: none;
        }

        .btn-primary:hover {
            background: #3f37c9;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #4cc9f0;
            border: none;
        }

        .form-control, .form-select {
            border-radius: 12px;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
            border-color: #4299e1;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 20px;
        }

        /* Custom colors for status */
        .bg-overdue { background: linear-gradient(45deg, #e63946, #f56565); }
        .bg-submitted { background: linear-gradient(45deg, #4cc9f0, #48bb78); }
        .bg-pending { background: linear-gradient(45deg, #f72585, #ed8936); }
        .bg-graded { background: linear-gradient(45deg, #4895ef, #4299e1); }

        /* Animation for cards */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .assignment-card {
            animation: fadeIn 0.5s ease-out;
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

        /* Enhanced responsive design */
        @media (max-width: 768px) {
            .header-section {
                padding: 20px;
                margin: 15px 0;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
  <div class="dashboard-container">
         <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2 text-primary"></i>Learn Arena-Assingment
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
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <!-- Header -->
        <div class="header-section text-center">
            <div class="row align-items-center">
                <div class="col-md-8 text-start">
                    <h1 class="display-5 fw-bold mb-2">My Assignments</h1>
                    <p class="lead mb-0 opacity-75">Track, manage, and submit your assignments</p>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-tasks fa-5x opacity-50"></i>
                </div>
            </div>
        </div>

        <?php echo $message; ?>

        <!-- Assignment Statistics -->
        <div class="row mb-4 g-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon mb-3">
                        <i class="fas fa-tasks fa-2x text-primary"></i>
                    </div>
                    <div class="stat-number text-primary"><?php echo $assignment_stats['total_assignments']; ?></div>
                    <small class="text-muted">Total Assignments</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon mb-3">
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                    </div>
                    <div class="stat-number text-success"><?php echo $assignment_stats['submitted_count']; ?></div>
                    <small class="text-muted">Submitted</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon mb-3">
                        <i class="fas fa-star fa-2x text-warning"></i>
                    </div>
                    <div class="stat-number text-warning"><?php echo $assignment_stats['graded_count']; ?></div>
                    <small class="text-muted">Graded</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon mb-3">
                        <i class="fas fa-clock fa-2x text-danger"></i>
                    </div>
                    <div class="stat-number text-danger"><?php echo $assignment_stats['overdue_count']; ?></div>
                    <small class="text-muted">Overdue</small>
                </div>
            </div>
        </div>

        <!-- Assignments List -->
        <?php if ($assignments && $assignments->num_rows > 0): ?>
        <div class="row g-4">
            <?php while ($assignment = $assignments->fetch_assoc()): ?>
            <?php
            $is_overdue = $assignment['assignment_status'] == 'overdue';
            $is_submitted = $assignment['assignment_status'] == 'submitted';
            $is_graded = $assignment['submission_status'] == 'graded';
            $status_class = $is_overdue ? 'bg-overdue' : ($is_submitted ? ($is_graded ? 'bg-graded' : 'bg-submitted') : 'bg-pending');
            $status_text = $is_overdue ? 'Overdue' : ($is_submitted ? ($is_graded ? 'Graded' : 'Submitted') : 'Pending');
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card assignment-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="card-title mb-0"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                            <span class="badge <?php echo $status_class; ?> status-badge text-white">
                                <?php echo $status_text; ?>
                            </span>
                        </div>
                        
                        <p class="card-text text-muted mb-2 small">
                            <?php echo substr(htmlspecialchars($assignment['description']), 0, 100) . '...'; ?>
                        </p>
                        
                        <div class="mb-2 small">
                            <div>
                                <i class="fas fa-book me-1 text-primary"></i>
                                <strong><?php echo htmlspecialchars($assignment['subject_name']); ?></strong>
                            </div>
                            <?php if ($assignment['lesson_title']): ?>
                            <div>
                                <i class="fas fa-bookmark me-1 text-info"></i>
                                <?php echo htmlspecialchars($assignment['lesson_title']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-2 small">
                            <i class="fas fa-calendar me-1 <?php echo $is_overdue ? 'text-danger' : 'text-warning'; ?>"></i>
                            Due: <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?>
                        </div>
                        
                        <div class="mb-3 small">
                            <i class="fas fa-star me-1 text-success"></i>
                            Max Marks: <?php echo $assignment['max_marks']; ?>
                            <?php if ($is_graded): ?>
                            <span class="text-primary ms-2">Obtained: <?php echo $assignment['marks_obtained']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($is_submitted): ?>
                        <div class="alert alert-success py-2 small mb-3">
                            <i class="fas fa-check me-1"></i>
                            Submitted on <?php echo date('M j, Y g:i A', strtotime($assignment['submitted_at'])); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($is_graded && !empty($assignment['feedback'])): ?>
                        <div class="alert alert-info py-2 small mb-3">
                            <strong>Feedback:</strong><br>
                            <?php echo htmlspecialchars($assignment['feedback']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-light btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $assignment['id']; ?>">
                                <i class="fas fa-eye me-1"></i>Details
                            </button>
                            <?php if (!$is_submitted && !$is_overdue): ?>
                            <button class="btn btn-success btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#submitModal<?php echo $assignment['id']; ?>">
                                <i class="fas fa-upload me-1"></i>Submit
                            </button>
                            <?php elseif ($is_submitted && !$is_graded): ?>
                            <button class="btn btn-warning btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#submitModal<?php echo $assignment['id']; ?>">
                                <i class="fas fa-edit me-1"></i>Resubmit
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
                            <h5 class="modal-title fw-bold" style="color: black;"><?php echo htmlspecialchars($assignment['title']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="fw-bold" style="color: black;">Description</h6>
                                    <p  style="color: black;"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                                    
                                    <?php if (!empty($assignment['instructions'])): ?>
                                    <h6 class="fw-bold mt-4" style="color: black;">Instructions</h6>
                                    <p  style="color: black;"><?php echo nl2br(htmlspecialchars($assignment['instructions'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="fw-bold" style="color: black;">Assignment Details</h6>
                                            <ul class="list-unstyled small">
                                                <li class="mb-2" style="color: black;"><strong>Subject:</strong> <?php echo htmlspecialchars($assignment['subject_name']); ?></li>
                                                <li class="mb-2"style="color: black;"><strong>Due Date:</strong> <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?></li>
                                                <li class="mb-2"style="color: black;"><strong>Max Marks:</strong> <?php echo $assignment['max_marks']; ?></li>
                                                <li class="mb-2"style="color: black;"><strong>Status:</strong> 
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </li>
                                            </ul>
                                            
                                            <?php if (!empty($assignment['attachment_path'])): ?>
                                            <h6 class="fw-bold mt-3"style="color: green;">Assignment File</h6>
                                            <a href="../<?php echo $assignment['attachment_path']; ?>" target="_blank" class="btn btn-primary btn-sm w-100">
                                                <i class="fas fa-download me-1"></i>Download
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
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
                            <h5 class="modal-title fw-bold" style="color: black;">Submit Assignment: <?php echo htmlspecialchars($assignment['title']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="modal-body">
                                <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                
                                <div class="mb-4">
                                    <label for="submission_text<?php echo $assignment['id']; ?>" style="color: black;" class="form-label fw-bold">Text Submission</label>
                                    <textarea class="form-control" id="submission_text<?php echo $assignment['id']; ?>" name="submission_text" rows="6" placeholder="Enter your submission text here..."></textarea>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="submission_file<?php echo $assignment['id']; ?>" class="form-label fw-bold" style="color: black;">File Upload</label>
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
            <div class="card bg-light">
                <div class="card-body py-5">
                    <i class="fas fa-tasks fa-4x text-muted mb-3"></i>
                    <h4 class="fw-bold">No Assignments Available</h4>
                    <p class="text-muted">Your instructor hasn't posted any assignments yet, or you may need to check your enrollment status.</p>
                    <a href="dashboard.php" class="btn btn-primary mt-3">
                        <i class="fas fa-home me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
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