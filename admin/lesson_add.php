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

// Handle lesson creation
if (isset($_POST['action']) && $_POST['action'] == 'create_lesson') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $content = trim($_POST['content']);
    $subject_id = intval($_POST['subject_id']);
    $lesson_order = intval($_POST['lesson_order']);
    $status = $_POST['status'];
    $presentation = '';
    
    // Handle presentation file upload with INCREASED SIZE LIMIT
    if (isset($_FILES['presentation']) && $_FILES['presentation']['error'] == 0) {
        $allowed_types = [
            'application/vnd.ms-powerpoint', 
            'application/vnd.openxmlformats-officedocument.presentationml.presentation', 
            'application/pdf',
            'video/mp4',
            'video/avi',
            'video/quicktime',
            'audio/mpeg',
            'audio/wav',
            'image/jpeg',
            'image/png',
            'image/gif'
        ];
        
        $max_size = 50 * 1024 * 1024; // INCREASED TO 50MB (was 10MB)
        
        $file_type = $_FILES['presentation']['type'];
        $file_size = $_FILES['presentation']['size'];
        $file_name = $_FILES['presentation']['name'];
        
        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            $upload_dir = '../uploads/presentations/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $unique_filename;
            
            if (move_uploaded_file($_FILES['presentation']['tmp_name'], $upload_path)) {
                $presentation = 'uploads/presentations/' . $unique_filename;
            } else {
                $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Failed to upload presentation file!</div>';
            }
        } else {
            if ($file_size > $max_size) {
                $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>File too large! Maximum size allowed is ' . ($max_size / 1024 / 1024) . 'MB.</div>';
            } else {
                $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Invalid file type. Please upload PPT, PPTX, PDF, MP4, MP3, or image files.</div>';
            }
        }
    } else if (isset($_FILES['presentation']) && $_FILES['presentation']['error'] != 4) {
        // Handle upload errors
        $error_messages = [
            1 => 'File too large (server limit)',
            2 => 'File too large (form limit)', 
            3 => 'File partially uploaded',
            6 => 'No temporary folder',
            7 => 'Failed to write file',
            8 => 'Upload stopped by extension'
        ];
        
        $error_code = $_FILES['presentation']['error'];
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Upload error: ' . ($error_messages[$error_code] ?? 'Unknown error') . '</div>';
    }
    
    if (empty($message)) {
        // Check if lesson order already exists for this subject
        $check_order = $conn->prepare("SELECT id FROM lessons WHERE subject_id = ? AND lesson_order = ?");
        $check_order->bind_param("ii", $subject_id, $lesson_order);
        $check_order->execute();
        
        if ($check_order->get_result()->num_rows > 0) {
            $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Lesson order already exists for this subject. Please choose a different order.</div>';
        } else {
            $stmt = $conn->prepare("INSERT INTO lessons (title, description, content, subject_id, lesson_order, presentation, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssiiss", $title, $description, $content, $subject_id, $lesson_order, $presentation, $status);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Lesson created successfully!</div>';
                // Clear form data
                $_POST = array();
            } else {
                $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error creating lesson: ' . $conn->error . '</div>';
            }
        }
    }
}

// Get all subjects for dropdown
$subjects = $conn->query("SELECT id, name FROM subjects ORDER BY name ASC");

// Get lesson statistics
$lesson_stats = $conn->query("SELECT 
    COUNT(*) as total_lessons,
    COUNT(CASE WHEN status = 'published' THEN 1 END) as published_count,
    COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_count,
    COUNT(CASE WHEN presentation IS NOT NULL AND presentation != '' THEN 1 END) as with_presentation
    FROM lessons")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Lesson - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
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
        .form-floating { margin-bottom: 20px; }
        .stat-card { text-align: center; padding: 20px; }
        .stat-number { font-size: 1.8rem; font-weight: bold; }
        .upload-area { border: 2px dashed #dee2e6; border-radius: 15px; padding: 40px; text-align: center; transition: all 0.3s ease; }
        .upload-area:hover { border-color: #007bff; background-color: #f8f9ff; }
        .upload-area.dragover { border-color: #007bff; background-color: #e3f2fd; }
        .file-preview { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 15px; padding: 20px; margin-top: 15px; }
        .required { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <nav class="col-md-2 d-md-block sidebar p-0">
                <div class="position-sticky pt-0">
                    <div class="sidebar-brand">
                        <h4><i class="fas fa-graduation-cap me-2"></i>LMS Admin</h4>
                        <small class="text-white-50">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></small>
                    </div>

                    <ul class="nav flex-column py-3">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>Dashboard
                            </a>
                        </li>

                        <div class="nav-section">Content Management</div>
                        <li class="nav-item">
                            <a class="nav-link" href="subjects.php">
                                <i class="fas fa-graduation-cap"></i>Subjects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="lessons.php">
                                <i class="fas fa-book"></i>Lessons
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="quizzes.php">
                                <i class="fas fa-question-circle"></i>Quizzes
                            </a>
                        </li>

                        <div class="nav-section">User Management</div>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i>Users
                            </a>
                        </li>

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
                        <h1 class="h2"><i class="fas fa-plus-circle me-2 text-success"></i>Add New Lesson</h1>
                        <p class="text-muted">Create engaging educational content for your students</p>
                    </div>
                    <div class="btn-toolbar">
                        <a href="lessons.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Back to Lessons
                        </a>
                        <button class="btn btn-outline-info" onclick="previewLesson()">
                            <i class="fas fa-eye me-2"></i>Preview
                        </button>
                    </div>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Lesson Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body stat-card">
                                <i class="fas fa-book fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $lesson_stats['total_lessons']; ?></div>
                                <small>Total Lessons</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body stat-card">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $lesson_stats['published_count']; ?></div>
                                <small>Published</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body stat-card">
                                <i class="fas fa-edit fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $lesson_stats['draft_count']; ?></div>
                                <small>Drafts</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body stat-card">
                                <i class="fas fa-file-upload fa-2x mb-2"></i>
                                <div class="stat-number"><?php echo $lesson_stats['with_presentation']; ?></div>
                                <small>With Files</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lesson Creation Form -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-edit me-2"></i>Lesson Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="lessonForm">
                            <input type="hidden" name="action" value="create_lesson">
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <!-- Basic Information -->
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="title" name="title" placeholder="Lesson Title" required value="<?php echo $_POST['title'] ?? ''; ?>">
                                                <label for="title">Lesson Title <span class="required">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating">
                                                <select class="form-select" id="subject_id" name="subject_id" required>
                                                    <option value="">Choose Subject</option>
                                                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                                                    <option value="<?php echo $subject['id']; ?>" <?php echo ($_POST['subject_id'] ?? '') == $subject['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($subject['name']); ?>
                                                    </option>
                                                    <?php endwhile; ?>
                                                </select>
                                                <label for="subject_id">Subject <span class="required">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <textarea class="form-control" id="description" name="description" placeholder="Brief description of the lesson" style="height: 100px;"><?php echo $_POST['description'] ?? ''; ?></textarea>
                                        <label for="description">Lesson Description</label>
                                    </div>
                                    
                                    <!-- Rich Text Editor for Content -->
                                    <div class="mb-3">
                                        <label for="content" class="form-label">Lesson Content <span class="required">*</span></label>
                                        <textarea id="content" name="content" class="form-control"><?php echo $_POST['content'] ?? ''; ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <!-- Lesson Settings -->
                                    <div class="card bg-light">
                                        <div class="card-header">
                                            <h6><i class="fas fa-cog me-2"></i>Lesson Settings</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-floating mb-3">
                                                <input type="number" class="form-control" id="lesson_order" name="lesson_order" min="1" value="<?php echo $_POST['lesson_order'] ?? '1'; ?>" required>
                                                <label for="lesson_order">Lesson Order <span class="required">*</span></label>
                                            </div>
                                            
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="status" name="status">
                                                    <option value="draft" <?php echo ($_POST['status'] ?? '') == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                    <option value="published" <?php echo ($_POST['status'] ?? '') == 'published' ? 'selected' : ''; ?>>Published</option>
                                                </select>
                                                <label for="status">Status</label>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    <i class="fas fa-info-circle me-1"></i>Publishing Status
                                                </label>
                                                <div class="small text-muted">
                                                    <div><strong>Draft:</strong> Visible only to admins</div>
                                                    <div><strong>Published:</strong> Visible to all students</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- File Upload Section -->
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6><i class="fas fa-cloud-upload-alt me-2"></i>File Upload (Optional)</h6>
                                            <small class="text-muted">Upload presentations, videos, audio files, or images</small>
                                        </div>
                                        <div class="card-body">
                                            <div class="upload-area" id="uploadArea">
                                                <i class="fas fa-cloud-upload-alt fa-4x text-muted mb-3"></i>
                                                <h4>Upload Learning Material</h4>
                                                <p class="text-muted mb-3">
                                                    Drag and drop your file here, or 
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('presentation').click()">
                                                        <i class="fas fa-folder-open me-1"></i>Browse Files
                                                    </button>
                                                </p>
                                                <input type="file" class="form-control d-none" id="presentation" name="presentation" 
                                                       accept=".ppt,.pptx,.pdf,.mp4,.avi,.mov,.mp3,.wav,.jpg,.jpeg,.png,.gif">
                                                
                                                <!-- File Type Information -->
                                                <div class="row mt-4">
                                                    <div class="col-md-12">
                                                        <div class="alert alert-info">
                                                            <h6><i class="fas fa-info-circle me-2"></i>Supported File Types & Size Limits</h6>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <ul class="mb-0">
                                                                        <li><strong>Presentations:</strong> PPT, PPTX, PDF</li>
                                                                        <li><strong>Videos:</strong> MP4, AVI, MOV</li>
                                                                        <li><strong>Audio:</strong> MP3, WAV</li>
                                                                    </ul>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <ul class="mb-0">
                                                                        <li><strong>Images:</strong> JPG, PNG, GIF</li>
                                                                        <li><strong>Maximum Size:</strong> <span class="text-danger fw-bold">50MB</span></li>
                                                                        <li><strong>Upload Time:</strong> ~1-3 minutes for large files</li>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div id="filePreview" class="file-preview d-none">
                                                <div class="row align-items-center">
                                                    <div class="col-md-1">
                                                        <i class="fas fa-file fa-3x text-primary" id="fileIcon"></i>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <div class="fw-bold" id="fileName"></div>
                                                        <div class="text-muted" id="fileSize"></div>
                                                        <div class="progress mt-2" id="uploadProgress" style="display: none;">
                                                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                                 role="progressbar" style="width: 0%" id="progressBar"></div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 text-end">
                                                        <button type="button" class="btn btn-outline-danger" onclick="removeFile()">
                                                            <i class="fas fa-trash me-1"></i>Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <button type="button" class="btn btn-outline-secondary" onclick="saveDraft()">
                                                <i class="fas fa-save me-2"></i>Save as Draft
                                            </button>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-outline-danger me-2" onclick="clearForm()">
                                                <i class="fas fa-trash me-2"></i>Clear Form
                                            </button>
                                            <button type="submit" class="btn btn-success btn-lg">
                                                <i class="fas fa-plus-circle me-2"></i>Create Lesson
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    
    <script>
        // Initialize Summernote rich text editor
        $(document).ready(function() {
            $('#content').summernote({
                height: 300,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ],
                placeholder: 'Enter your lesson content here...'
            });
        });

        // File upload handling with INCREASED LIMITS
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('presentation');
        const filePreview = document.getElementById('filePreview');
        const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB limit

        // Drag and drop functionality
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });

        // File input change
        fileInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        function handleFileSelect(file) {
            const allowedTypes = {
                // Presentations
                'application/vnd.ms-powerpoint': 'file-powerpoint text-danger',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'file-powerpoint text-danger',
                'application/pdf': 'file-pdf text-danger',
                
                // Videos
                'video/mp4': 'file-video text-primary',
                'video/avi': 'file-video text-primary', 
                'video/quicktime': 'file-video text-primary',
                
                // Audio
                'audio/mpeg': 'file-audio text-success',
                'audio/wav': 'file-audio text-success',
                
                // Images
                'image/jpeg': 'file-image text-warning',
                'image/png': 'file-image text-warning',
                'image/gif': 'file-image text-warning'
            };
            
            if (!allowedTypes[file.type]) {
                alert('❌ Unsupported file type!\n\nSupported formats:\n• Presentations: PPT, PPTX, PDF\n• Videos: MP4, AVI, MOV\n• Audio: MP3, WAV\n• Images: JPG, PNG, GIF');
                return;
            }
            
            if (file.size > MAX_FILE_SIZE) {
                alert(`❌ File too large!\n\nFile size: ${formatFileSize(file.size)}\nMaximum allowed: ${formatFileSize(MAX_FILE_SIZE)}\n\nPlease choose a smaller file.`);
                return;
            }
            
            // Show file preview
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = formatFileSize(file.size);
            
            // Set appropriate icon
            const iconClass = allowedTypes[file.type];
            document.getElementById('fileIcon').className = `fas fa-${iconClass} fa-3x`;
            
            uploadArea.classList.add('d-none');
            filePreview.classList.remove('d-none');
            
            // Show estimated upload time for large files
            if (file.size > 10 * 1024 * 1024) { // Files larger than 10MB
                const estimatedTime = Math.round(file.size / (1024 * 1024)); // Rough estimate: 1MB per second
                document.getElementById('fileSize').innerHTML += 
                    `<br><small class="text-info"><i class="fas fa-clock me-1"></i>Estimated upload time: ~${estimatedTime} seconds</small>`;
            }
        }

        function removeFile() {
            fileInput.value = '';
            uploadArea.classList.remove('d-none');
            filePreview.classList.add('d-none');
            
            // Reset progress bar
            const progressBar = document.getElementById('progressBar');
            const uploadProgress = document.getElementById('uploadProgress');
            progressBar.style.width = '0%';
            uploadProgress.style.display = 'none';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Enhanced form submission with upload progress
        document.getElementById('lessonForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const content = $('#content').summernote('code').trim();
            const subjectId = document.getElementById('subject_id').value;
            
            if (!title || !content || !subjectId) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            if (content === '<p><br></p>' || content === '') {
                e.preventDefault();
                alert('Please enter lesson content.');
                return false;
            }
            
            // Show upload progress for large files
            const fileInput = document.getElementById('presentation');
            if (fileInput.files.length > 0 && fileInput.files[0].size > 5 * 1024 * 1024) {
                showUploadProgress();
            }
        });

        function showUploadProgress() {
            const uploadProgress = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('progressBar');
            
            uploadProgress.style.display = 'block';
            
            let progress = 0;
            const interval = setInterval(() => {
                progress += 2;
                progressBar.style.width = progress + '%';
                
                if (progress >= 90) {
                    clearInterval(interval);
                    // Keep at 90% until actual upload completes
                }
            }, 100);
        }

        function saveDraft() {
            document.getElementById('status').value = 'draft';
            document.getElementById('lessonForm').submit();
        }

        function clearForm() {
            if (confirm('Are you sure you want to clear the form? All data will be lost.')) {
                document.getElementById('lessonForm').reset();
                $('#content').summernote('reset');
                removeFile();
                localStorage.removeItem('lesson_draft');
            }
        }

        function previewLesson() {
            const title = document.getElementById('title').value;
            const content = $('#content').summernote('code');
            
            if (!title || !content) {
                alert('Please enter lesson title and content to preview.');
                return;
            }
            
            const previewWindow = window.open('', '_blank');
            previewWindow.document.write(`
                <html>
                <head>
                    <title>Lesson Preview: ${title}</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>body { padding: 20px; }</style>
                </head>
                <body>
                    <div class="container">
                        <h1>${title}</h1>
                        <hr>
                        ${content}
                    </div>
                </body>
                </html>
            `);
        }

        // Auto-save functionality (save to localStorage)
        setInterval(function() {
            const formData = {
                title: document.getElementById('title').value,
                description: document.getElementById('description').value,
                content: $('#content').summernote('code'),
                subject_id: document.getElementById('subject_id').value,
                lesson_order: document.getElementById('lesson_order').value,
                status: document.getElementById('status').value
            };
            
            localStorage.setItem('lesson_draft', JSON.stringify(formData));
        }, 30000); // Auto-save every 30 seconds

        // Load draft on page load
        window.addEventListener('load', function() {
            const savedDraft = localStorage.getItem('lesson_draft');
            if (savedDraft && confirm('Would you like to restore your saved draft?')) {
                const formData = JSON.parse(savedDraft);
                
                document.getElementById('title').value = formData.title || '';
                document.getElementById('description').value = formData.description || '';
                document.getElementById('subject_id').value = formData.subject_id || '';
                document.getElementById('lesson_order').value = formData.lesson_order || '1';
                document.getElementById('status').value = formData.status || 'draft';
                
                $('#content').summernote('code', formData.content || '');
            }
        });

        // Clear draft after successful submission
        <?php if (strpos($message, 'success') !== false): ?>
        localStorage.removeItem('lesson_draft');
        <?php endif; ?>
    </script>
</body>
</html>
