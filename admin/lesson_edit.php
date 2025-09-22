<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

$lesson_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Create lesson_files table for presentations and attachments
$create_files_table = "CREATE TABLE IF NOT EXISTS lesson_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_type ENUM('presentation', 'video', 'audio', 'document', 'image') NOT NULL,
    file_size INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_files_table);

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/lessons/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle file upload
if (isset($_POST['upload_file']) && isset($_FILES['lesson_file'])) {
    $file = $_FILES['lesson_file'];
    $file_type = $_POST['file_type'];
    
    // File validation
    $allowed_types = [
        'presentation' => ['ppt', 'pptx', 'pdf', 'odp'],
        'video' => ['mp4', 'avi', 'mov', 'wmv', 'webm'],
        'audio' => ['mp3', 'wav', 'ogg', 'm4a'],
        'document' => ['pdf', 'doc', 'docx', 'txt', 'odt'],
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp']
    ];
    
    $max_size = 20 * 1024 * 1024; // 20MB in bytes
    
    if ($file['error'] == 0) {
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_types[$file_type]) && $file['size'] <= $max_size) {
            $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Save file info to database
                $stmt = $conn->prepare("INSERT INTO lesson_files (lesson_id, file_name, original_name, file_type, file_size, file_path, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssiss", $lesson_id, $new_filename, $file['name'], $file_type, $file['size'], $file_path, $file['type']);
                $stmt->execute();
                
                $success_message = "File uploaded successfully!";
            } else {
                $error_message = "Failed to upload file.";
            }
        } else {
            $error_message = "Invalid file type or file too large (max 20MB).";
        }
    } else {
        $error_message = "File upload error: " . $file['error'];
    }
}

// Handle file deletion
if (isset($_POST['delete_file'])) {
    $file_id = intval($_POST['file_id']);
    
    // Get file info
    $file_info = $conn->query("SELECT * FROM lesson_files WHERE id = $file_id AND lesson_id = $lesson_id")->fetch_assoc();
    
    if ($file_info) {
        // Delete physical file
        if (file_exists($file_info['file_path'])) {
            unlink($file_info['file_path']);
        }
        
        // Delete database record
        $conn->query("DELETE FROM lesson_files WHERE id = $file_id");
        $success_message = "File deleted successfully!";
    }
}

if ($lesson_id == 0) {
    header("Location: lessons.php");
    exit();
}

// Get lesson details
$lesson_result = $conn->query("SELECT l.*, s.name as subject_name 
    FROM lessons l 
    LEFT JOIN subjects s ON l.subject_id = s.id 
    WHERE l.id = $lesson_id");

if (!$lesson_result || $lesson_result->num_rows == 0) {
    header("Location: lessons.php");
    exit();
}

$lesson = $lesson_result->fetch_assoc();

// Handle lesson update
if (isset($_POST['update_lesson'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $subject_id = intval($_POST['subject_id']);
    $difficulty = $_POST['difficulty'];
    $duration = intval($_POST['duration']);
    $status = $_POST['status'];
    
    if (!empty($title) && !empty($content)) {
        $stmt = $conn->prepare("UPDATE lessons SET title = ?, content = ?, subject_id = ?, difficulty = ?, duration = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssisssi", $title, $content, $subject_id, $difficulty, $duration, $status, $lesson_id);
        
        if ($stmt->execute()) {
            $success_message = "Lesson updated successfully!";
            // Refresh lesson data
            $lesson_result = $conn->query("SELECT l.*, s.name as subject_name 
                FROM lessons l 
                LEFT JOIN subjects s ON l.subject_id = s.id 
                WHERE l.id = $lesson_id");
            $lesson = $lesson_result->fetch_assoc();
        } else {
            $error_message = "Failed to update lesson.";
        }
    } else {
        $error_message = "Title and content are required.";
    }
}

// Get subjects for dropdown - FIX FOR THE ERROR
$subjects_result = $conn->query("SELECT * FROM subjects ORDER BY name ASC");
$subjects_array = [];
if ($subjects_result) {
    while ($row = $subjects_result->fetch_assoc()) {
        $subjects_array[] = $row;
    }
}

// Get lesson files
$lesson_files = $conn->query("SELECT * FROM lesson_files WHERE lesson_id = $lesson_id ORDER BY uploaded_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lesson - Admin Panel</title>
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
        
        .file-upload-area { border: 2px dashed #dee2e6; border-radius: 10px; padding: 30px; text-align: center; transition: all 0.3s ease; }
        .file-upload-area:hover { border-color: #007bff; background-color: #f8f9fa; }
        .file-upload-area.dragover { border-color: #28a745; background-color: #d4edda; }
        .file-item { border: 1px solid #dee2e6; border-radius: 10px; padding: 15px; margin-bottom: 10px; transition: all 0.3s ease; }
        .file-item:hover { box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .file-icon { font-size: 2rem; margin-right: 15px; }
        .file-size { font-size: 0.85rem; color: #6c757d; }
        .upload-progress { display: none; }
        .content-editor { min-height: 300px; }
        .preview-area { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block sidebar p-0">
                <div class="position-sticky pt-0">
                    <div class="sidebar-brand">
                        <h4><i class="fas fa-cogs me-2"></i>Admin Panel</h4>
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
                                <i class="fas fa-book"></i>Subjects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="lessons.php">
                                <i class="fas fa-book-open"></i>Lessons
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="quizzes.php">
                                <i class="fas fa-question-circle"></i>Quizzes
                            </a>
                        </li>

                        <div class="nav-section">User Management</div>
                        <li class="nav-item">
                            <a class="nav-link" href="students.php">
                                <i class="fas fa-users"></i>Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="assignments.php">
                                <i class="fas fa-tasks"></i>Assignments
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

                        <div class="nav-section">Settings</div>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user"></i>Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
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
                        <h1 class="h2"><i class="fas fa-edit me-2"></i>Edit Lesson</h1>
                        <p class="text-muted">Update lesson content and manage presentations</p>
                    </div>
                    <div class="btn-toolbar">
                        <a href="lessons.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Back to Lessons
                        </a>
                        <a href="../student/lesson_view.php?id=<?php echo $lesson_id; ?>" target="_blank" class="btn btn-outline-info">
                            <i class="fas fa-eye me-2"></i>Preview Lesson
                        </a>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Lesson Content -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-book-open me-2"></i>Lesson Content</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Lesson Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($lesson['title']); ?>" required>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="subject_id" class="form-label">Subject *</label>
                                            <select class="form-select" id="subject_id" name="subject_id" required>
                                                <?php foreach ($subjects_array as $subject): ?>
                                                <option value="<?php echo $subject['id']; ?>" 
                                                        <?php echo $lesson['subject_id'] == $subject['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($subject['name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="difficulty" class="form-label">Difficulty</label>
                                            <select class="form-select" id="difficulty" name="difficulty">
                                                <option value="beginner" <?php echo $lesson['difficulty'] == 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                                <option value="intermediate" <?php echo $lesson['difficulty'] == 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                                <option value="advanced" <?php echo $lesson['difficulty'] == 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="duration" class="form-label">Duration (mins)</label>
                                            <input type="number" class="form-control" id="duration" name="duration" 
                                                   value="<?php echo $lesson['duration'] ?? 15; ?>" min="1" max="300">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="content" class="form-label">Lesson Content *</label>
                                        <textarea class="form-control content-editor" id="content" name="content" 
                                                  rows="12" required placeholder="Enter the lesson content here..."><?php echo htmlspecialchars($lesson['content']); ?></textarea>
                                        <div class="form-text">
                                            You can use plain text or basic formatting. Line breaks will be preserved.
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft" <?php echo $lesson['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="published" <?php echo $lesson['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                                        </select>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" name="update_lesson" class="btn btn-success btn-lg">
                                            <i class="fas fa-save me-2"></i>Update Lesson
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Content Preview -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6><i class="fas fa-eye me-2"></i>Content Preview</h6>
                            </div>
                            <div class="card-body">
                                <div class="preview-area" id="contentPreview">
                                    <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- File Management -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-file-upload me-2"></i>File Uploads</h5>
                                <small class="text-muted">Max file size: 20MB</small>
                            </div>
                            <div class="card-body">
                                <!-- File Upload Form -->
                                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                    <div class="mb-3">
                                        <label for="file_type" class="form-label">File Type</label>
                                        <select class="form-select" id="file_type" name="file_type" required>
                                            <option value="presentation">📊 Presentation (PPT, PDF)</option>
                                            <option value="video">🎥 Video (MP4, AVI, MOV)</option>
                                            <option value="audio">🎵 Audio (MP3, WAV, OGG)</option>
                                            <option value="document">📄 Document (PDF, DOC, TXT)</option>
                                            <option value="image">🖼️ Image (JPG, PNG, GIF)</option>
                                        </select>
                                    </div>

                                    <div class="file-upload-area" id="fileUploadArea">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                        <h5>Drop files here or click to browse</h5>
                                        <p class="text-muted">Supported formats: PPT, PDF, MP4, MP3, Images<br>Maximum size: 20MB</p>
                                        <input type="file" class="form-control" id="lesson_file" name="lesson_file" 
                                               accept=".ppt,.pptx,.pdf,.mp4,.mp3,.wav,.jpg,.jpeg,.png,.gif,.doc,.docx,.txt" 
                                               style="display: none;">
                                        <button type="button" class="btn btn-primary" onclick="document.getElementById('lesson_file').click()">
                                            <i class="fas fa-folder-open me-2"></i>Browse Files
                                        </button>
                                    </div>

                                    <div class="upload-progress mt-3">
                                        <div class="progress">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                 role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <div class="text-center mt-2">
                                            <small class="text-muted">Uploading... <span id="uploadPercent">0%</span></small>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <button type="submit" name="upload_file" class="btn btn-success" id="uploadBtn" disabled>
                                            <i class="fas fa-upload me-2"></i>Upload File
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Uploaded Files -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6><i class="fas fa-files me-2"></i>Uploaded Files</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($lesson_files && $lesson_files->num_rows > 0): ?>
                                    <?php while ($file = $lesson_files->fetch_assoc()): ?>
                                    <div class="file-item">
                                        <div class="d-flex align-items-center">
                                            <div class="file-icon text-<?php 
                                                echo $file['file_type'] == 'presentation' ? 'danger' : 
                                                    ($file['file_type'] == 'video' ? 'primary' : 
                                                    ($file['file_type'] == 'audio' ? 'success' : 
                                                    ($file['file_type'] == 'image' ? 'warning' : 'info'))); 
                                            ?>">
                                                <i class="fas fa-<?php 
                                                    echo $file['file_type'] == 'presentation' ? 'file-powerpoint' : 
                                                        ($file['file_type'] == 'video' ? 'file-video' : 
                                                        ($file['file_type'] == 'audio' ? 'file-audio' : 
                                                        ($file['file_type'] == 'image' ? 'file-image' : 'file'))); 
                                                ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($file['original_name']); ?></h6>
                                                <div class="file-size">
                                                    <?php echo ucfirst($file['file_type']); ?> • 
                                                    <?php echo number_format($file['file_size'] / 1024 / 1024, 2); ?> MB • 
                                                    <?php echo date('M j, Y', strtotime($file['uploaded_at'])); ?>
                                                </div>
                                            </div>
                                            <div class="file-actions">
                                                <a href="<?php echo $file['file_path']; ?>" target="_blank" 
                                                   class="btn btn-outline-primary btn-sm me-1" title="View/Download">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                                <form method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete this file?')">
                                                    <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                    <button type="submit" name="delete_file" class="btn btn-outline-danger btn-sm" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-file-plus fa-3x mb-3"></i>
                                        <p>No files uploaded yet.<br>Add presentations, videos, or other materials to enhance this lesson.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- File Type Guide -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6><i class="fas fa-info-circle me-2"></i>Supported File Types</h6>
                            </div>
                            <div class="card-body">
                                <small>
                                    <strong>📊 Presentations:</strong> .ppt, .pptx, .pdf, .odp<br>
                                    <strong>🎥 Videos:</strong> .mp4, .avi, .mov, .wmv, .webm<br>
                                    <strong>🎵 Audio:</strong> .mp3, .wav, .ogg, .m4a<br>
                                    <strong>📄 Documents:</strong> .pdf, .doc, .docx, .txt, .odt<br>
                                    <strong>🖼️ Images:</strong> .jpg, .jpeg, .png, .gif, .svg, .webp<br>
                                    <br>
                                    <em>Maximum file size: 20MB per file</em>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload handling
        const fileInput = document.getElementById('lesson_file');
        const uploadArea = document.getElementById('fileUploadArea');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadForm = document.getElementById('uploadForm');
        const uploadProgress = document.querySelector('.upload-progress');
        const progressBar = document.querySelector('.progress-bar');
        const uploadPercent = document.getElementById('uploadPercent');

        // File input change handler
        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                const file = this.files[0];
                const maxSize = 20 * 1024 * 1024; // 20MB
                
                if (file.size > maxSize) {
                    alert('⚠️ File too large! Maximum size is 20MB.');
                    this.value = '';
                    uploadBtn.disabled = true;
                    return;
                }
                
                uploadBtn.disabled = false;
                uploadArea.innerHTML = `
                    <i class="fas fa-file fa-3x text-success mb-3"></i>
                    <h6>${file.name}</h6>
                    <p class="text-muted">Size: ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearFile()">
                        <i class="fas fa-times me-1"></i>Remove
                    </button>
                `;
            }
        });

        // Clear selected file
        function clearFile() {
            fileInput.value = '';
            uploadBtn.disabled = true;
            resetUploadArea();
        }

        // Reset upload area
        function resetUploadArea() {
            uploadArea.innerHTML = `
                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                <h5>Drop files here or click to browse</h5>
                <p class="text-muted">Supported formats: PPT, PDF, MP4, MP3, Images<br>Maximum size: 20MB</p>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('lesson_file').click()">
                    <i class="fas fa-folder-open me-2"></i>Browse Files
                </button>
            `;
        }

        // Drag and drop functionality
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });

        // Content preview
        const contentTextarea = document.getElementById('content');
        const contentPreview = document.getElementById('contentPreview');

        contentTextarea.addEventListener('input', function() {
            const content = this.value;
            contentPreview.innerHTML = content.replace(/\n/g, '<br>');
        });

        // File type change handler
        document.getElementById('file_type').addEventListener('change', function() {
            const fileType = this.value;
            let acceptedTypes = '';
            
            switch(fileType) {
                case 'presentation':
                    acceptedTypes = '.ppt,.pptx,.pdf,.odp';
                    break;
                case 'video':
                    acceptedTypes = '.mp4,.avi,.mov,.wmv,.webm';
                    break;
                case 'audio':
                    acceptedTypes = '.mp3,.wav,.ogg,.m4a';
                    break;
                case 'document':
                    acceptedTypes = '.pdf,.doc,.docx,.txt,.odt';
                    break;
                case 'image':
                    acceptedTypes = '.jpg,.jpeg,.png,.gif,.svg,.webp';
                    break;
            }
            
            fileInput.setAttribute('accept', acceptedTypes);
            clearFile();
        });

        // Auto-resize textarea
        contentTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.max(300, this.scrollHeight) + 'px';
        });
    </script>
</body>
</html>
