<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

$student_id = $_SESSION['user_id'];
$lesson_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($lesson_id == 0) {
    header("Location: lessons.php");
    exit();
}

// Get lesson details
try {
    $lesson_query = "SELECT l.*, 
        COALESCE(s.name, 'General') as subject_name, 
        COALESCE(s.color, '#007bff') as subject_color, 
        COALESCE(s.icon, 'book') as subject_icon 
        FROM lessons l 
        LEFT JOIN subjects s ON l.subject_id = s.id 
        WHERE l.id = $lesson_id";
    
    $lesson_result = $conn->query($lesson_query);
    
    if (!$lesson_result || $lesson_result->num_rows == 0) {
        header("Location: lessons.php");
        exit();
    }
    
    $lesson = $lesson_result->fetch_assoc();
} catch (Exception $e) {
    header("Location: lessons.php");
    exit();
}

// Create progress table
$create_progress_table = "CREATE TABLE IF NOT EXISTS lesson_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    lesson_id INT NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    progress_percentage INT DEFAULT 0,
    time_spent INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    UNIQUE KEY unique_student_lesson (student_id, lesson_id)
)";
$conn->query($create_progress_table);

// Get files
$lesson_files = [];

// Method 1: lesson_files table
$files_table_exists = $conn->query("SHOW TABLES LIKE 'lesson_files'")->num_rows > 0;
if ($files_table_exists) {
    $files_result = $conn->query("SELECT * FROM lesson_files WHERE lesson_id = $lesson_id ORDER BY uploaded_at DESC");
    if ($files_result) {
        while ($file = $files_result->fetch_assoc()) {
            if (file_exists($file['file_path'])) {
                $lesson_files[] = $file;
            }
        }
    }
}

// Method 2: presentation column
if (!empty($lesson['presentation'])) {
    $pres_path = '../' . $lesson['presentation'];
    if (file_exists($pres_path)) {
        $lesson_files[] = [
            'id' => 0, // Special ID for legacy files
            'lesson_id' => $lesson_id,
            'original_name' => 'Lesson Presentation - ' . basename($lesson['presentation']),
            'file_type' => 'presentation',
            'file_size' => filesize($pres_path),
            'file_path' => $pres_path,
            'uploaded_at' => $lesson['created_at']
        ];
    }
}

// Progress tracking
$progress_check = $conn->query("SELECT * FROM lesson_progress WHERE student_id = $student_id AND lesson_id = $lesson_id");
$existing_progress = $progress_check ? $progress_check->fetch_assoc() : null;

if (!$existing_progress) {
    $conn->query("INSERT INTO lesson_progress (student_id, lesson_id, status, progress_percentage) VALUES ($student_id, $lesson_id, 'in_progress', 25)");
    $progress_percentage = 25;
    $status = 'in_progress';
} else {
    $progress_percentage = max($existing_progress['progress_percentage'], 50);
    $conn->query("UPDATE lesson_progress SET progress_percentage = $progress_percentage WHERE student_id = $student_id AND lesson_id = $lesson_id");
    $status = $existing_progress['status'];
}

if (isset($_POST['complete_lesson'])) {
    $conn->query("UPDATE lesson_progress SET status = 'completed', progress_percentage = 100, completed_at = NOW() WHERE student_id = $student_id AND lesson_id = $lesson_id");
    $progress_percentage = 100;
    $status = 'completed';
    $completion_message = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson['title']); ?> - Lesson</title>
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
            color: rgba(255, 255, 255, 0.9);
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

        /* Text styles with better readability */
        h1, h2, h3, h4, h5, h6 {
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

         span, div, small {
            color: rgba(255, 255, 255, 0.9);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        .text-muted {
            color: rgba(255, 255, 255, 0.7) !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }

        /* Enhanced glassmorphism effects */
        .glass-effect {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        /* Lesson Header */
        .lesson-header { 
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(25px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.25);
            color: white; 
            padding: 40px 0;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .lesson-content { 
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            padding: 40px;
            margin: 30px 0;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .lesson-content:hover {
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.25);
            transform: translateY(-5px);
        }

        .progress-bar-custom { 
            height: 8px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }

        .difficulty-badge { 
            font-size: 0.85rem;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.15);
            color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .completion-celebration { 
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(25px);
            color: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .lesson-meta { 
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .floating-progress { 
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .floating-progress:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }

        .file-section { 
            margin: 40px 0;
            padding: 30px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .file-item { 
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
        }

        .file-item:hover {
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.25);
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.25);
        }

        .file-icon { 
            font-size: 3rem;
            color: rgba(255, 255, 255, 0.8);
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
        }
        p{
            color:white !important;
        }

        .preview-frame { 
            width: 100%;
            height: 600px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 15px;
            margin-top: 20px;
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .badge {
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-shadow: none;
        }

        /* Button styles */
        .btn {
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        /* Card styles */
        .card {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            color: rgba(255, 255, 255, 0.9);
        }

        .card-header {
            background: rgba(255, 255, 255, 0.15) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
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

        /* Responsive design */
        @media (max-width: 768px) {
            .lesson-content {
                padding: 20px;
            }
            
            .file-section {
                padding: 20px;
            }
            
            .preview-frame {
                height: 400px;
            }
            
            .floating-progress {
                bottom: 10px;
                right: 10px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Lesson Header -->
    <div class="lesson-header">
        <div class="container">
            
            
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-6 fw-bold mb-3"><?php echo htmlspecialchars($lesson['title']); ?></h1>
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-white text-dark me-3">
                            <i class="fas fa-<?php echo $lesson['subject_icon']; ?> me-1"></i>
                            <?php echo htmlspecialchars($lesson['subject_name']); ?>
                        </span>
                        <span class="badge difficulty-badge bg-<?php 
                            echo ($lesson['difficulty'] ?? 'beginner') == 'beginner' ? 'success' : 
                                (($lesson['difficulty'] ?? 'beginner') == 'intermediate' ? 'warning' : 'danger'); 
                        ?> me-3">
                            <?php echo ucfirst($lesson['difficulty'] ?? 'beginner'); ?>
                        </span>
                        <small>
                            <i class="fas fa-clock me-1"></i>
                            <?php echo $lesson['duration'] ?? 15; ?> minutes
                        </small>
                    </div>
                    
                    <div class="mb-2">
                        <div class="d-flex justify-content-between">
                            <small>Lesson Progress</small>
                            <small><?php echo $progress_percentage; ?>% Complete</small>
                        </div>
                        <div class="progress progress-bar-custom">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $progress_percentage; ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-<?php echo $lesson['subject_icon']; ?> fa-6x opacity-25"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($completion_message)): ?>
        <div class="completion-celebration">
            <h2><i class="fas fa-trophy me-2"></i>Lesson Completed!</h2>
            <p class="lead mb-0">🎉 Congratulations! You've successfully completed this lesson!</p>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="lesson-content">
                    <div class="lesson-meta">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="fas fa-book-open fa-2x text-primary mb-2"></i>
                                    <h6>Reading</h6>
                                    <small class="text-muted"><?php echo $lesson['duration'] ?? 15; ?> min</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="fas fa-graduation-cap fa-2x text-success mb-2"></i>
                                    <h6>Level</h6>
                                    <small class="text-muted"><?php echo ucfirst($lesson['difficulty'] ?? 'beginner'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="fas fa-file fa-2x text-info mb-2"></i>
                                    <h6>Materials</h6>
                                    <small class="text-muted"><?php echo count($lesson_files); ?> files</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <i class="fas fa-chart-line fa-2x text-warning mb-2"></i>
                                    <h6>Progress</h6>
                                    <small class="text-muted"><?php echo $progress_percentage; ?>%</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lesson Content -->
                    <div class="lesson-text">
                        <h3 class="mb-4">📚 Lesson Content</h3>
                        <div class="content-body" style="font-size: 1.1rem; line-height: 1.8;">
                            <?php echo nl2br($lesson['content']); ?>

                        </div>
                    </div>

                    <!-- Lesson Actions -->
                    <div class="lesson-actions mt-5 text-center">
                        <?php if ($status != 'completed'): ?>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="complete_lesson" class="btn btn-success btn-lg me-3">
                                <i class="fas fa-check me-2"></i>Mark as Completed
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Lesson Completed!</strong> Great job!
                        </div>
                        <?php endif; ?>
                        
                        <a href="lessons.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-arrow-left me-2"></i>Back to Lessons
                        </a>
                    </div>
                </div>

                <!-- FILES SECTION - WORKING VERSION -->
                <?php if (!empty($lesson_files)): ?>
                <div class="file-section">
                    <h2 class="mb-4">
                        <i class="fas fa-folder-open me-2"></i>
                        Lesson Materials 
                        <span class="badge bg-primary"><?php echo count($lesson_files); ?> files</span>
                    </h2>

                    <?php foreach ($lesson_files as $index => $file): ?>
                    <div class="file-item">
                    

                        <div class="row align-items-center">
                            <div class="col-md-1">
                                <?php
                                $icon = 'file';
                                $color = 'secondary';
                                switch($file['file_type']) {
                                    case 'presentation':
                                        $icon = 'file-powerpoint';
                                        $color = 'danger';
                                        break;
                                    case 'video':
                                        $icon = 'file-video';
                                        $color = 'primary';
                                        break;
                                    case 'audio':
                                        $icon = 'file-audio';
                                        $color = 'success';
                                        break;
                                    case 'document':
                                        $icon = 'file-alt';
                                        $color = 'info';
                                        break;
                                    case 'image':
                                        $icon = 'file-image';
                                        $color = 'warning';
                                        break;
                                }
                                ?>
                                <i class="fas fa-<?php echo $icon; ?> file-icon text-<?php echo $color; ?>"></i>
                            </div>
                            <div class="col-md-7">
                                <h5 class="mb-2"><?php echo htmlspecialchars($file['original_name']); ?></h5>
                                <div>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Size: <?php echo number_format($file['file_size'] / 1024 / 1024, 2); ?> MB •
                                        Type: <?php echo ucfirst($file['file_type']); ?> •
                                        Added: <?php echo date('M j, Y', strtotime($file['uploaded_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <!-- WORKING BUTTONS -->
                                <?php if ($file['id'] > 0): ?>
                                    <!-- Files from lesson_files table -->
                                    <a href="file_handler.php?file_id=<?php echo $file['id']; ?>&action=preview" target="_blank" class="btn btn-<?php echo $color; ?> btn-sm me-2">
                                        <i class="fas fa-eye me-1"></i>View Online
                                    </a>
                                    <a href="file_handler.php?file_id=<?php echo $file['id']; ?>&action=download" class="btn btn-outline-<?php echo $color; ?> btn-sm me-2">
                                        <i class="fas fa-download me-1"></i>Download
                                    </a>
                                    <button class="btn btn-info btn-sm" onclick="showInlinePreview(<?php echo $index; ?>, <?php echo $file['id']; ?>, 'file')">
                                        <i class="fas fa-expand me-1"></i>Show Here
                                    </button>
                                <?php else: ?>
                                    <!-- Files from lessons table -->
                                    <a href="file_handler.php?lesson_id=<?php echo $lesson_id; ?>&action=preview" target="_blank" class="btn btn-<?php echo $color; ?> btn-sm me-2">
                                        <i class="fas fa-eye me-1"></i>View Online
                                    </a>
                                    <a href="file_handler.php?lesson_id=<?php echo $lesson_id; ?>&action=download" class="btn btn-outline-<?php echo $color; ?> btn-sm me-2">
                                        <i class="fas fa-download me-1"></i>Download
                                    </a>
                                    <button class="btn btn-info btn-sm" onclick="showInlinePreview(<?php echo $index; ?>, <?php echo $lesson_id; ?>, 'lesson')">
                                        <i class="fas fa-expand me-1"></i>Show Here
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Inline Preview Area -->
                        <div id="preview_<?php echo $index; ?>" style="display: none; margin-top: 20px;">
                            <div class="text-center mb-2">
                                <button class="btn btn-danger btn-sm" onclick="hideInlinePreview(<?php echo $index; ?>)">
                                    <i class="fas fa-times me-1"></i>Close Preview
                                </button>
                            </div>
                            <div id="preview_content_<?php echo $index; ?>">
                                <!-- Content loaded here -->
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="file-section">
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                        <h4>No Additional Materials</h4>
                        <p class="text-muted">No supplementary files have been uploaded for this lesson.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4" style="margin-top: 30px; padding-left:140px;">
                <div class="card">
                    <div class="card-header" style="background-color: <?php echo $lesson['subject_color']; ?>; color: white;">
                        <h6 class="mb-0">
                            <i class="fas fa-<?php echo $lesson['subject_icon']; ?> me-2"></i>
                            Learning Resources
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($lesson_files)): ?>
                            <h6>📁 Available Files (<?php echo count($lesson_files); ?>)</h6>
                            <ul class="list-unstyled">
                                <?php foreach ($lesson_files as $file): ?>
                                <li class="mb-1">
                                    <i class="fas fa-file text-primary me-2"></i>
                                    <small><?php echo htmlspecialchars($file['original_name']); ?></small>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">No additional files available.</p>
                        <?php endif; ?>
                        
                        <hr>
                        <h6>💡 File Access</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-eye text-primary me-2"></i>
                                <small><strong>View Online:</strong> Opens in new tab</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-download text-success me-2"></i>
                                <small><strong>Download:</strong> Saves to device</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-expand text-info me-2"></i>
                                <small><strong>Show Here:</strong> Preview on page</small>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress -->
    <div class="floating-progress">
        <div class="text-center">
            <div class="h6 mb-2">Progress</div>
            <div class="progress progress-bar-custom mb-2" style="width: 100px;">
                <div class="progress-bar bg-success" style="width: <?php echo $progress_percentage; ?>%"></div>
            </div>
            <small><?php echo $progress_percentage; ?>%</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show inline preview
        function showInlinePreview(index, id, type) {
            const previewDiv = document.getElementById('preview_' + index);
            const contentDiv = document.getElementById('preview_content_' + index);
            
            if (previewDiv && contentDiv) {
                previewDiv.style.display = 'block';
                
                let url;
                if (type === 'file') {
                    url = 'file_handler.php?file_id=' + id + '&action=preview';
                } else {
                    url = 'file_handler.php?lesson_id=' + id + '&action=preview';
                }
                
                contentDiv.innerHTML = `
                    <iframe src="${url}" class="preview-frame" frameborder="0">
                        <p>Cannot display this file. <a href="${url}" target="_blank">Open in new tab</a></p>
                    </iframe>
                `;
                
                previewDiv.scrollIntoView({ behavior: 'smooth' });
            }
        }

        // Hide inline preview
        function hideInlinePreview(index) {
            const previewDiv = document.getElementById('preview_' + index);
            const contentDiv = document.getElementById('preview_content_' + index);
            
            if (previewDiv && contentDiv) {
                previewDiv.style.display = 'none';
                contentDiv.innerHTML = '';
            }
        }
    </script>
</body>
</html>
