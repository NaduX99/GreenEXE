<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

$student_id = $_SESSION['user_id'];

// Update tables safely - add missing columns without losing existing data
$tables_result = $conn->query("SHOW TABLES");
$tables_check = [];
while ($row = $tables_result->fetch_array()) {
    $tables_check[] = $row[0];
}

if (in_array('subjects', $tables_check)) {
    // Check if color column exists
    $color_check = $conn->query("SHOW COLUMNS FROM subjects LIKE 'color'");
    if ($color_check->num_rows == 0) {
        $conn->query("ALTER TABLE subjects ADD COLUMN color VARCHAR(7) DEFAULT '#007bff'");
    }
    
    // Check if icon column exists
    $icon_check = $conn->query("SHOW COLUMNS FROM subjects LIKE 'icon'");
    if ($icon_check->num_rows == 0) {
        $conn->query("ALTER TABLE subjects ADD COLUMN icon VARCHAR(50) DEFAULT 'book'");
    }
    
    // Check if status column exists
    $status_check = $conn->query("SHOW COLUMNS FROM subjects LIKE 'status'");
    if ($status_check->num_rows == 0) {
        $conn->query("ALTER TABLE subjects ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
    }
} else {
    // Create subjects table
    $create_subjects = "CREATE TABLE subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        color VARCHAR(7) DEFAULT '#007bff',
        icon VARCHAR(50) DEFAULT 'book',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($create_subjects);
}

// Update lessons table if needed
if (in_array('lessons', $tables_check)) {
    // Check if difficulty column exists
    $difficulty_check = $conn->query("SHOW COLUMNS FROM lessons LIKE 'difficulty'");
    if ($difficulty_check->num_rows == 0) {
        $conn->query("ALTER TABLE lessons ADD COLUMN difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner'");
    }
    
    // Check if duration column exists
    $duration_check = $conn->query("SHOW COLUMNS FROM lessons LIKE 'duration'");
    if ($duration_check->num_rows == 0) {
        $conn->query("ALTER TABLE lessons ADD COLUMN duration INT DEFAULT 15");
    }
    
    // Check if status column exists
    $status_check = $conn->query("SHOW COLUMNS FROM lessons LIKE 'status'");
    if ($status_check->num_rows == 0) {
        $conn->query("ALTER TABLE lessons ADD COLUMN status ENUM('draft', 'published') DEFAULT 'published'");
    }
}

// Create default subject if no subjects exist
$subject_count_check = $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
if ($subject_count_check == 0) {
    $default_subjects = [
        "INSERT INTO subjects (name, description, color, icon, status) VALUES ('General', 'General lessons and topics', '#007bff', 'book', 'active')",
        "INSERT INTO subjects (name, description, color, icon, status) VALUES ('Mathematics', 'Mathematical concepts and problem solving', '#28a745', 'calculator', 'active')",
        "INSERT INTO subjects (name, description, color, icon, status) VALUES ('Science', 'Scientific principles and discoveries', '#dc3545', 'flask', 'active')",
        "INSERT INTO subjects (name, description, color, icon, status) VALUES ('English', 'Language arts and literature', '#ffc107', 'book-open', 'active')",
        "INSERT INTO subjects (name, description, color, icon, status) VALUES ('Computer Science', 'Programming and technology', '#6f42c1', 'laptop-code', 'active')"
    ];
    
    foreach ($default_subjects as $sql) {
        $conn->query($sql);
    }
}

// Update existing lessons to have a valid subject_id if they don't
$conn->query("UPDATE lessons SET subject_id = 1 WHERE subject_id IS NULL OR subject_id = 0");
$conn->query("UPDATE lessons SET status = 'published' WHERE status IS NULL");
$conn->query("UPDATE lessons SET difficulty = 'beginner' WHERE difficulty IS NULL");
$conn->query("UPDATE lessons SET duration = 15 WHERE duration IS NULL OR duration = 0");

// Get filter parameters
$subject_filter = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Apply filters if any are set
$where_conditions = [];

if ($subject_filter > 0) {
    $where_conditions[] = "l.subject_id = $subject_filter";
}

if (!empty($difficulty_filter) && $difficulty_filter != '') {
    $difficulty_safe = mysqli_real_escape_string($conn, $difficulty_filter);
    $where_conditions[] = "l.difficulty = '$difficulty_safe'";
}

if (!empty($search)) {
    $search_safe = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(l.title LIKE '%$search_safe%' OR l.content LIKE '%$search_safe%')";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$filtered_lessons_query = "SELECT l.*, 
    COALESCE(s.name, 'General') as subject_name, 
    COALESCE(s.color, '#007bff') as subject_color, 
    COALESCE(s.icon, 'book') as subject_icon 
    FROM lessons l 
    LEFT JOIN subjects s ON l.subject_id = s.id 
    $where_clause
    ORDER BY l.id DESC";

$lessons = $conn->query($filtered_lessons_query);
$filtered_lessons_found = $lessons->num_rows;

// Group lessons by subject
$lessons_by_subject = [];
if ($lessons && $lessons->num_rows > 0) {
    while ($lesson = $lessons->fetch_assoc()) {
        $subject_name = $lesson['subject_name'] ?? 'General';
        if (!isset($lessons_by_subject[$subject_name])) {
            $lessons_by_subject[$subject_name] = [
                'subject_info' => $lesson,
                'lessons' => []
            ];
        }
        $lessons_by_subject[$subject_name]['lessons'][] = $lesson;
    }
}

// Get subjects for dropdown
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name ASC");

// Get statistics
$lesson_stats = $conn->query("SELECT 
    COUNT(*) as total_lessons,
    COUNT(CASE WHEN difficulty = 'beginner' THEN 1 END) as beginner_lessons,
    COUNT(CASE WHEN difficulty = 'intermediate' THEN 1 END) as intermediate_lessons,
    COUNT(CASE WHEN difficulty = 'advanced' THEN 1 END) as advanced_lessons,
    COUNT(DISTINCT subject_id) as subjects_count
    FROM lessons")->fetch_assoc();

    // ✅ Define total lessons found for dropdown
$total_lessons_found = $lesson_stats['total_lessons'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lessons - Student Portal</title>
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

        /* Card styles */
        .card { 
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            color: white;
        }

        .card:hover { 
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.18);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.25);
        }

        .stats-card {
            text-align: center; 
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
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

        .stats-card:hover::before {
            opacity: 1;
        }

        .stat-number { 
            font-size: 2.5rem; 
            font-weight: 800; 
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .lesson-card { 
            transition: all 0.3s ease; 
            border-left: 4px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .lesson-card:hover { 
            transform: translateY(-5px);
            border-left-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
        }

        .subject-badge, .difficulty-badge { 
            font-size: 0.8rem;
            background: rgba(255, 255, 255, 0.15);
            color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .subject-header { 
            padding: 2rem; 
            margin-bottom: 2rem; 
            border-radius: 20px; 
            position: relative;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .subject-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, transparent 50%, rgba(255, 255, 255, 0.05) 100%);
            pointer-events: none;
        }

        .subject-icon { 
            font-size: 3rem; 
            opacity: 0.3; 
            position: absolute; 
            right: 2rem; 
            top: 50%; 
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
        }

        .lesson-meta { 
            font-size: 0.85rem; 
            color: rgba(255, 255, 255, 0.7);
        }

        .debug-info { 
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 1.5rem; 
            border-radius: 20px; 
            margin-bottom: 2rem; 
            color: rgba(255, 255, 255, 0.9);
        }

        /* Form elements */
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            backdrop-filter: blur(10px);
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.1);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        /* Buttons */
        .btn {
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-primary {
            background: rgba(13, 110, 253, 0.8);
            border-color: rgba(13, 110, 253, 0.5);
            backdrop-filter: blur(10px);
        }

        .btn-primary:hover {
            background: rgba(13, 110, 253, 0.9);
            border-color: rgba(13, 110, 253, 0.7);
            transform: translateY(-2px);
        }

        .btn-outline-info, .btn-outline-secondary {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .btn-outline-info:hover, .btn-outline-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Custom Scrollbar */
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
                        <a href="lessons.php" class="nav-link active">
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
            <!-- Page Header -->
            <div class="welcome-header mb-4">
                <h1><i class="fas fa-book-open me-3"></i>Available Lessons</h1>
                <p>Explore our comprehensive lesson collection and continue your learning journey</p>
            </div>

            <?php if (isset($_GET['debug'])): ?>
            <!-- Debug Information -->
            <div class="debug-info">
                <h6><i class="fas fa-bug me-2"></i>Debug Information</h6>
                <p><strong>Total lessons in database:</strong> <?php echo $total_lessons_found; ?></p>
                <p><strong>Lessons after filtering:</strong> <?php echo $filtered_lessons_found; ?></p>
                <p><strong>Subjects found:</strong> <?php echo count($lessons_by_subject); ?></p>
                <p><strong>Active filters:</strong> 
                    Subject: <?php echo $subject_filter > 0 ? $subject_filter : 'None'; ?>, 
                    Difficulty: <?php echo !empty($difficulty_filter) ? $difficulty_filter : 'None'; ?>, 
                    Search: <?php echo !empty($search) ? $search : 'None'; ?>
                </p>
                <details>
                    <summary>All Lessons Raw Data</summary>
                    <pre><?php
                    $all_lessons->data_seek(0);
                    while ($row = $all_lessons->fetch_assoc()) {
                        echo "ID: {$row['id']}, Title: {$row['title']}, Subject: {$row['subject_name']}\n";
                    }
                    ?></pre>
                </details>
            </div>
            <?php endif; ?>

            <!-- Learning Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <i class="fas fa-book-open fa-2x mb-2"></i>
                            <div class="stat-number"><?php echo $lesson_stats['total_lessons']; ?></div>
                            <small>Total Lessons</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                            <div class="stat-number"><?php echo $lesson_stats['subjects_count']; ?></div>
                            <small>Subjects</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <i class="fas fa-star fa-2x mb-2"></i>
                            <div class="stat-number"><?php echo $lesson_stats['beginner_lessons']; ?></div>
                            <small>Beginner</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <i class="fas fa-fire fa-2x mb-2"></i>
                            <div class="stat-number"><?php echo $lesson_stats['advanced_lessons']; ?></div>
                            <small>Advanced</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="subject" class="form-label">Subject</label>
                            <select class="form-select" id="subject" name="subject">
                                <option value="0" style="  color: black !important;">All Subjects (<?php echo $total_lessons_found; ?> lessons)</option>
                                <?php 
                                $subjects->data_seek(0); // Reset pointer
                                while ($subject = $subjects->fetch_assoc()): ?>
                                <option value="<?php echo $subject['id']; ?>" style="  color: black !important;"<?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="difficulty" class="form-label">Difficulty</label>
                            <select class="form-select" id="difficulty" name="difficulty" >
                                <option value="" style="  color: black !important;">All Levels</option>
                                <option value="beginner" style="  color: black !important;"<?php echo $difficulty_filter == 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                <option value="intermediate" style="  color: black !important;"<?php echo $difficulty_filter == 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                <option value="advanced" style="  color: black !important;"<?php echo $difficulty_filter == 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Search lessons..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label d-block">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </form>
                    
                    <?php if ($subject_filter > 0 || !empty($difficulty_filter) || !empty($search)): ?>
                    <div class="mt-3">
                        <a href="lessons.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times me-1"></i>Clear Filters (Show All <?php echo $total_lessons_found; ?> Lessons)
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lessons Display -->
            <?php if (!empty($lessons_by_subject)): ?>
                <?php foreach ($lessons_by_subject as $subject_name => $subject_data): ?>
                <div class="subject-section mb-5">
                    <!-- Subject Header -->
                    <div class="subject-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="mb-1">
                                    <i class="fas fa-<?php echo $subject_data['subject_info']['subject_icon']; ?> me-2"></i>
                                    <?php echo htmlspecialchars($subject_name); ?>
                                </h3>
                                <p class="mb-0 opacity-75"><?php echo count($subject_data['lessons']); ?> lessons available</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="subject-icon">
                                    <i class="fas fa-<?php echo $subject_data['subject_info']['subject_icon']; ?>"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subject Lessons -->
                    <div class="row">
                        <?php foreach ($subject_data['lessons'] as $lesson): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card lesson-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge subject-badge">
                                            <i class="fas fa-<?php echo $lesson['subject_icon']; ?> me-1"></i>
                                            <?php echo htmlspecialchars($lesson['subject_name']); ?>
                                        </span>
                                        <span class="badge difficulty-badge">
                                            <?php echo ucfirst($lesson['difficulty'] ?? 'beginner'); ?>
                                        </span>
                                    </div>
                                    
                                    <h5 class="card-title"><?php echo htmlspecialchars($lesson['title']); ?></h5>
                                    
                                    <p class="card-text">
                                        <?php 
                                        $content = strip_tags($lesson['content']);
                                        echo htmlspecialchars(substr($content, 0, 120));
                                        echo strlen($content) > 120 ? '...' : '';
                                        ?>
                                    </p>
                                    
                                    <div class="lesson-meta mb-3">
                                        <small>
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo $lesson['duration'] ?? 15; ?> minutes
                                            <i class="fas fa-calendar ms-3 me-1"></i>
                                            <?php echo date('M j', strtotime($lesson['created_at'])); ?>
                                        </small>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <a href="lesson_view.php?id=<?php echo $lesson['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-play me-1"></i>Start Learning
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-exclamation-triangle fa-4x text-warning mb-4"></i>
                        <h4>No Lessons Found</h4>
                        <p>
                            <?php if ($subject_filter > 0 || !empty($difficulty_filter) || !empty($search)): ?>
                                <strong>Found <?php echo $total_lessons_found; ?> total lessons, but <?php echo $filtered_lessons_found; ?> match your filters.</strong><br>
                                Try adjusting your search criteria or clear the filters to see all lessons.
                            <?php else: ?>
                                <strong>Total lessons in database: <?php echo $total_lessons_found; ?></strong><br>
                                There might be a database structure issue. Click "Debug Info" above for more details.
                            <?php endif; ?>
                        </p>
                        <div class="mt-4">
                            <a href="lessons.php" class="btn btn-primary me-2">
                                <i class="fas fa-refresh me-2"></i>Show All Lessons
                            </a>
                            <a href="?debug=1" class="btn btn-outline-info">
                                <i class="fas fa-bug me-2"></i>Debug Info
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit form on filter change
        document.getElementById('subject').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('difficulty').addEventListener('change', function() {
            this.form.submit();
        });

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
    </script>
</body>
</html>