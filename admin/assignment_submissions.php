<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

$assignment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

if ($assignment_id == 0) {
    header("Location: assignments.php");
    exit();
}

// Handle grading
if (isset($_POST['grade_submission'])) {
    $submission_id = intval($_POST['submission_id']);
    $marks_obtained = intval($_POST['marks_obtained']);
    $feedback = trim($_POST['feedback']);
    
    $stmt = $conn->prepare("UPDATE assignment_submissions SET marks_obtained = ?, feedback = ?, graded_by = ?, graded_at = NOW(), status = 'graded' WHERE id = ?");
    $stmt->bind_param("isii", $marks_obtained, $feedback, $_SESSION['user_id'], $submission_id);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Submission graded successfully!</div>';
    } else {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error grading submission.</div>';
    }
}

// Get assignment details
$assignment_query = "SELECT a.*, s.name as subject_name, l.title as lesson_title 
    FROM assignments a 
    LEFT JOIN subjects s ON a.subject_id = s.id 
    LEFT JOIN lessons l ON a.lesson_id = l.id 
    WHERE a.id = $assignment_id";

$assignment_result = $conn->query($assignment_query);
if (!$assignment_result || $assignment_result->num_rows == 0) {
    header("Location: assignments.php");
    exit();
}

$assignment = $assignment_result->fetch_assoc();

// Get all submissions for this assignment
$submissions_query = "SELECT sub.*, u.name as student_name, u.email as student_email,
    grader.name as grader_name
    FROM assignment_submissions sub
    LEFT JOIN users u ON sub.student_id = u.id
    LEFT JOIN users grader ON sub.graded_by = grader.id
    WHERE sub.assignment_id = $assignment_id
    ORDER BY sub.submitted_at DESC";

$submissions = $conn->query($submissions_query);

// Get submission statistics
$submission_stats = $conn->query("SELECT 
    COUNT(*) as total_submissions,
    COUNT(CASE WHEN status = 'graded' THEN 1 END) as graded_count,
    COUNT(CASE WHEN status = 'submitted' THEN 1 END) as pending_count,
    AVG(CASE WHEN marks_obtained IS NOT NULL THEN marks_obtained END) as average_marks
    FROM assignment_submissions 
    WHERE assignment_id = $assignment_id")->fetch_assoc();

// Get total enrolled students (assuming all users with role 'student')
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Submissions - <?php echo htmlspecialchars($assignment['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .header-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 0; }
        .submission-card { transition: all 0.3s ease; border-radius: 15px; }
        .submission-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .graded-card { border-left: 4px solid #28a745; }
        .pending-card { border-left: 4px solid #ffc107; }
        .late-card { border-left: 4px solid #dc3545; }
        .stat-card { background: white; border-radius: 15px; padding: 20px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2rem; font-weight: bold; }
        .file-preview { max-width: 200px; max-height: 150px; border-radius: 10px; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb text-white mb-2">
                            <li class="breadcrumb-item"><a href="dashboard.php" class="text-white">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="assignments.php" class="text-white">Assignments</a></li>
                            <li class="breadcrumb-item active">Submissions</li>
                        </ol>
                    </nav>
                    <h1 class="display-6 fw-bold mb-2"><?php echo htmlspecialchars($assignment['title']); ?></h1>
                    <p class="lead mb-0">
                        <i class="fas fa-book me-2"></i><?php echo htmlspecialchars($assignment['subject_name']); ?>
                        <?php if ($assignment['lesson_title']): ?>
                        • <i class="fas fa-bookmark me-1"></i><?php echo htmlspecialchars($assignment['lesson_title']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-file-alt fa-6x opacity-25"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <?php echo $message; ?>

        <!-- Assignment Info -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5><i class="fas fa-info-circle me-2"></i>Assignment Details</h5>
                        <p><?php echo htmlspecialchars($assignment['description']); ?></p>
                        <div class="row">
                            <div class="col-md-6">
                                <small>
                                    <strong>Due Date:</strong> <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?><br>
                                    <strong>Max Marks:</strong> <?php echo $assignment['max_marks']; ?><br>
                                    <strong>Status:</strong> <span class="badge bg-<?php echo $assignment['status'] == 'published' ? 'success' : 'warning'; ?>"><?php echo ucfirst($assignment['status']); ?></span>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <?php if (!empty($assignment['attachment_path'])): ?>
                                <small>
                                    <strong>Assignment File:</strong><br>
                                    <a href="../<?php echo $assignment['attachment_path']; ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-download me-1"></i>Download Attachment
                                    </a>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <!-- Submission Statistics -->
                <div class="stat-card">
                    <h6><i class="fas fa-chart-bar me-2"></i>Submission Stats</h6>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="stat-number text-primary"><?php echo $submission_stats['total_submissions']; ?></div>
                            <small>Submitted</small>
                        </div>
                        <div class="col-6">
                            <div class="stat-number text-success"><?php echo $submission_stats['graded_count']; ?></div>
                            <small>Graded</small>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <div class="stat-number text-warning"><?php echo number_format($submission_stats['average_marks'] ?? 0, 1); ?></div>
                        <small>Average Marks</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submissions List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-list me-2"></i>Student Submissions (<?php echo $submissions->num_rows; ?>)</h5>
                <div>
                    <span class="badge bg-primary"><?php echo $submission_stats['total_submissions']; ?> / <?php echo $total_students; ?> students</span>
                </div>
            </div>
            <div class="card-body">
                <?php if ($submissions && $submissions->num_rows > 0): ?>
                <div class="row">
                    <?php while ($submission = $submissions->fetch_assoc()): ?>
                    <?php
                    $is_late = strtotime($submission['submitted_at']) > strtotime($assignment['due_date']);
                    $card_class = $submission['status'] == 'graded' ? 'graded-card' : 
                                  ($is_late ? 'late-card' : 'pending-card');
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card submission-card <?php echo $card_class; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="card-title mb-0"><?php echo htmlspecialchars($submission['student_name']); ?></h6>
                                    <span class="badge bg-<?php 
                                        echo $submission['status'] == 'graded' ? 'success' : 
                                            ($is_late ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo $is_late ? 'Late' : ucfirst($submission['status']); ?>
                                    </span>
                                </div>
                                
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($submission['student_email']); ?></p>
                                
                                <div class="mb-2">
                                    <small>
                                        <i class="fas fa-calendar me-1"></i>
                                        Submitted: <?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?>
                                        <?php if ($is_late): ?>
                                        <br><i class="fas fa-exclamation-triangle text-danger me-1"></i>
                                        <span class="text-danger">Submitted late</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                
                                <?php if ($submission['status'] == 'graded'): ?>
                                <div class="alert alert-success py-2 mb-2">
                                    <small>
                                        <strong>Marks:</strong> <?php echo $submission['marks_obtained']; ?>/<?php echo $assignment['max_marks']; ?>
                                        <br><strong>Graded by:</strong> <?php echo htmlspecialchars($submission['grader_name']); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Text Submission -->
                                <?php if (!empty($submission['submission_text'])): ?>
                                <div class="mb-2">
                                    <small><strong>Text Submission:</strong></small>
                                    <div class="border rounded p-2" style="max-height: 100px; overflow-y: auto; font-size: 0.85rem;">
                                        <?php echo nl2br(htmlspecialchars(substr($submission['submission_text'], 0, 200))); ?>
                                        <?php if (strlen($submission['submission_text']) > 200): ?>
                                        <br><span class="text-muted">... <a href="#" onclick="showFullText(<?php echo $submission['id']; ?>)">Show more</a></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- File Submission -->
                                <?php if (!empty($submission['file_path'])): ?>
                                <div class="mb-2">
                                    <small><strong>File:</strong> <?php echo htmlspecialchars($submission['original_filename']); ?></small><br>
                                    <a href="../<?php echo $submission['file_path']; ?>" target="_blank" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-download me-1"></i>Download
                                    </a>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Grading Section -->
                                <div class="mt-3">
                                    <?php if ($submission['status'] != 'graded'): ?>
                                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#gradeModal<?php echo $submission['id']; ?>">
                                        <i class="fas fa-star me-1"></i>Grade
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#gradeModal<?php echo $submission['id']; ?>">
                                        <i class="fas fa-edit me-1"></i>Re-grade
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-info btn-sm" onclick="viewSubmission(<?php echo $submission['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i>View Full
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grading Modal -->
                    <div class="modal fade" id="gradeModal<?php echo $submission['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Grade Submission - <?php echo htmlspecialchars($submission['student_name']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label for="marks<?php echo $submission['id']; ?>" class="form-label">Marks Obtained *</label>
                                            <input type="number" class="form-control" id="marks<?php echo $submission['id']; ?>" 
                                                   name="marks_obtained" min="0" max="<?php echo $assignment['max_marks']; ?>" 
                                                   value="<?php echo $submission['marks_obtained'] ?? ''; ?>" required>
                                            <div class="form-text">Out of <?php echo $assignment['max_marks']; ?> marks</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="feedback<?php echo $submission['id']; ?>" class="form-label">Feedback</label>
                                            <textarea class="form-control" id="feedback<?php echo $submission['id']; ?>" 
                                                      name="feedback" rows="4" placeholder="Provide feedback to the student..."><?php echo htmlspecialchars($submission['feedback'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <small>
                                                <strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?><br>
                                                <?php if (!empty($submission['submission_text'])): ?>
                                                <strong>Text:</strong> <?php echo substr(htmlspecialchars($submission['submission_text']), 0, 100); ?>...<br>
                                                <?php endif; ?>
                                                <?php if (!empty($submission['file_path'])): ?>
                                                <strong>File:</strong> <?php echo htmlspecialchars($submission['original_filename']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="grade_submission" class="btn btn-success">
                                            <i class="fas fa-save me-2"></i>Save Grade
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
                    <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                    <h4>No Submissions Yet</h4>
                    <p class="text-muted">Students haven't submitted their assignments yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-4 text-center">
            <a href="assignments.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Assignments
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewSubmission(submissionId) {
            alert('Full submission view will be implemented. Submission ID: ' + submissionId);
        }

        function showFullText(submissionId) {
            alert('Show full text functionality will be implemented. Submission ID: ' + submissionId);
        }
    </script>
</body>
</html>
