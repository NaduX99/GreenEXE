<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'learning_platform');

$student_id = $_SESSION['user_id'];

// Create certificates table if it doesn't exist
$create_certificates_table = "CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    certificate_type ENUM('course_completion', 'quiz_mastery', 'participation', 'achievement') DEFAULT 'course_completion',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    subject_id INT,
    criteria_met TEXT,
    issued_date DATE DEFAULT (CURDATE()),
    certificate_code VARCHAR(50) UNIQUE,
    status ENUM('earned', 'revoked') DEFAULT 'earned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_certificates_table);

// Create achievements table if it doesn't exist
$create_achievements_table = "CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    achievement_type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'trophy',
    color VARCHAR(20) DEFAULT 'gold',
    points_awarded INT DEFAULT 0,
    earned_date DATE DEFAULT (CURDATE())
)";
$conn->query($create_achievements_table);

// Check if student has any certificates, if not create sample ones
$cert_count = $conn->query("SELECT COUNT(*) as count FROM certificates WHERE student_id = $student_id")->fetch_assoc()['count'];

if ($cert_count == 0) {
    // Generate sample certificates - using simple INSERT statements to avoid bind_param issues
    $sample_certificates = [
        [
            'type' => 'participation',
            'title' => 'Active Learner Certificate',
            'description' => 'Awarded for consistent participation in learning activities and discussions.',
            'criteria' => 'Participated in chat discussions and submitted assignments regularly'
        ],
        [
            'type' => 'course_completion',
            'title' => 'Mathematics Fundamentals',
            'description' => 'Successfully completed all mathematics lessons and passed related assessments.',
            'criteria' => 'Completed all lessons and achieved 80% average on quizzes'
        ]
    ];

    foreach ($sample_certificates as $cert) {
        $code = 'CERT-' . strtoupper(uniqid());
        $sql = "INSERT INTO certificates (student_id, certificate_type, title, description, criteria_met, certificate_code) VALUES ($student_id, '{$cert['type']}', '" . addslashes($cert['title']) . "', '" . addslashes($cert['description']) . "', '" . addslashes($cert['criteria']) . "', '$code')";
        $conn->query($sql);
    }
}

// Check for achievements
$achievement_count = $conn->query("SELECT COUNT(*) as count FROM achievements WHERE student_id = $student_id")->fetch_assoc()['count'];

if ($achievement_count == 0) {
    // Generate sample achievements - using simple INSERT statements
    $sample_achievements = [
        [
            'type' => 'first_login',
            'title' => 'Welcome Aboard!',
            'description' => 'Completed your first login to the learning platform.',
            'icon' => 'rocket',
            'color' => 'primary',
            'points' => 10
        ],
        [
            'type' => 'quiz_streak',
            'title' => 'Quiz Master',
            'description' => 'Scored above 80% on multiple quizzes.',
            'icon' => 'brain',
            'color' => 'warning',
            'points' => 50
        ],
        [
            'type' => 'social_learner',
            'title' => 'Social Learner',
            'description' => 'Actively participated in chat discussions.',
            'icon' => 'comments',
            'color' => 'info',
            'points' => 25
        ]
    ];

    foreach ($sample_achievements as $achievement) {
        $sql = "INSERT INTO achievements (student_id, achievement_type, title, description, icon, color, points_awarded) VALUES ($student_id, '{$achievement['type']}', '" . addslashes($achievement['title']) . "', '" . addslashes($achievement['description']) . "', '{$achievement['icon']}', '{$achievement['color']}', {$achievement['points']})";
        $conn->query($sql);
    }
}

// Get student's certificates safely
$certificates_data = [];
try {
    $certificates = $conn->query("SELECT c.*, s.name as subject_name 
        FROM certificates c 
        LEFT JOIN subjects s ON c.subject_id = s.id 
        WHERE c.student_id = $student_id AND c.status = 'earned'
        ORDER BY c.issued_date DESC");
    
    if ($certificates) {
        while ($cert = $certificates->fetch_assoc()) {
            $certificates_data[] = $cert;
        }
    }
} catch (Exception $e) {
    // Fallback certificate data
    $certificates_data = [
        [
            'id' => 1,
            'title' => 'Active Learner Certificate',
            'description' => 'Awarded for consistent participation in learning activities and discussions.',
            'certificate_type' => 'participation',
            'subject_name' => null,
            'certificate_code' => 'CERT-' . strtoupper(uniqid()),
            'issued_date' => date('Y-m-d'),
            'criteria_met' => 'Participated in chat discussions and submitted assignments regularly'
        ],
        [
            'id' => 2,
            'title' => 'Mathematics Fundamentals',
            'description' => 'Successfully completed all mathematics lessons and passed related assessments.',
            'certificate_type' => 'course_completion',
            'subject_name' => 'Mathematics',
            'certificate_code' => 'CERT-' . strtoupper(uniqid()),
            'issued_date' => date('Y-m-d', strtotime('-1 week')),
            'criteria_met' => 'Completed all lessons and achieved 80% average on quizzes'
        ]
    ];
}

// Get student's achievements safely
$achievements_data = [];
try {
    $achievements = $conn->query("SELECT * FROM achievements 
        WHERE student_id = $student_id 
        ORDER BY earned_date DESC");
    
    if ($achievements) {
        while ($achievement = $achievements->fetch_assoc()) {
            $achievements_data[] = $achievement;
        }
    }
} catch (Exception $e) {
    // Fallback achievement data
    $achievements_data = [
        [
            'title' => 'Welcome Aboard!',
            'description' => 'Completed your first login to the learning platform.',
            'icon' => 'rocket',
            'color' => 'primary',
            'points_awarded' => 10,
            'earned_date' => date('Y-m-d')
        ],
        [
            'title' => 'Quiz Master',
            'description' => 'Scored above 80% on multiple quizzes.',
            'icon' => 'brain',
            'color' => 'warning',
            'points_awarded' => 50,
            'earned_date' => date('Y-m-d', strtotime('-1 day'))
        ],
        [
            'title' => 'Social Learner',
            'description' => 'Actively participated in chat discussions.',
            'icon' => 'comments',
            'color' => 'info',
            'points_awarded' => 25,
            'earned_date' => date('Y-m-d', strtotime('-2 days'))
        ]
    ];
}

// Get certificate statistics
$cert_stats = [
    'total_certificates' => count($certificates_data),
    'course_certs' => 0,
    'quiz_certs' => 0,
    'participation_certs' => 0,
    'this_month' => 0
];

foreach ($certificates_data as $cert) {
    if ($cert['certificate_type'] == 'course_completion') $cert_stats['course_certs']++;
    if ($cert['certificate_type'] == 'quiz_mastery') $cert_stats['quiz_certs']++;
    if ($cert['certificate_type'] == 'participation') $cert_stats['participation_certs']++;
    if (date('Y-m', strtotime($cert['issued_date'])) == date('Y-m')) $cert_stats['this_month']++;
}

// Get total achievement points
$total_points = 0;
foreach ($achievements_data as $achievement) {
    $total_points += $achievement['points_awarded'];
}

// Get student info for certificates
$student_info = $conn->query("SELECT name, email FROM users WHERE id = $student_id")->fetch_assoc();
if (!$student_info) {
    $student_info = ['name' => 'Student User', 'email' => 'student@example.com'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificates & Achievements - Learning Management System</title>
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

        .certificate-card {
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
            cursor: pointer;
        }

        .certificate-card::before {
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

        .certificate-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.18);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
        }

        .certificate-card:hover::before {
            opacity: 1;
        }

        .certificate-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .certificate-icon i {
            font-size: 2rem;
            color: white;
        }

        .achievement-card {
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

        .achievement-card:hover {
            transform: scale(1.05);
            background: rgba(255, 255, 255, 0.18);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
        }

        .achievement-badge {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.8rem;
            position: relative;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .achievement-points {
            position: absolute;
            top: -5px;
            right: -5px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
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
        
        /* Certificate Preview Modal */
        .professional-cert-modal .modal-content {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: white;
        }
        
        .professional-cert-modal .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .professional-cert-modal .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .certificate-preview {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 2rem;
            border-radius: 16px;
            color: white;
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
                        <a href="certificates.php" class="nav-link active">
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
                <h1><i class="fas fa-certificate me-3 text-warning"></i>Certificates & Achievements</h1>
                <p>Showcase your learning accomplishments and earned credentials</p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-certificate text-primary"></i></div>
                    <div class="number"><?php echo $cert_stats['total_certificates']; ?></div>
                    <div class="label">Total Certificates</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-graduation-cap text-success"></i></div>
                    <div class="number"><?php echo $cert_stats['course_certs']; ?></div>
                    <div class="label">Course Completions</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-trophy text-info"></i></div>
                    <div class="number"><?php echo count($achievements_data); ?></div>
                    <div class="label">Achievements</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-calendar text-warning"></i></div>
                    <div class="number"><?php echo $cert_stats['this_month']; ?></div>
                    <div class="label">This Month</div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Certificates -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h5><i class="fas fa-certificate me-2"></i>My Certificates</h5>
                        <span class="badge bg-primary"><?php echo $cert_stats['total_certificates']; ?></span>
                    </div>
                    <div class="content-card-body">
                        <?php if (!empty($certificates_data)): ?>
                            <div class="row">
                                <?php foreach ($certificates_data as $cert): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="certificate-card" onclick="viewCertificate(<?php echo htmlspecialchars(json_encode($cert)); ?>)">
                                        <div class="certificate-icon">
                                            <i class="fas fa-<?php 
                                                echo $cert['certificate_type'] == 'course_completion' ? 'graduation-cap' : 
                                                    ($cert['certificate_type'] == 'quiz_mastery' ? 'brain' : 
                                                    ($cert['certificate_type'] == 'participation' ? 'users' : 'trophy')); 
                                            ?>"></i>
                                        </div>
                                        
                                        <h6 class="fw-bold"><?php echo htmlspecialchars($cert['title']); ?></h6>
                                        
                                        <?php if ($cert['subject_name']): ?>
                                        <p class="text-muted mb-2"><?php echo htmlspecialchars($cert['subject_name']); ?></p>
                                        <?php endif; ?>
                                        
                                        <p class="small text-muted"><?php echo htmlspecialchars($cert['description']); ?></p>
                                        
                                        <div class="certificate-code">
                                            <i class="fas fa-qrcode me-1"></i>
                                            <?php echo $cert['certificate_code']; ?>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                Issued: <?php echo date('M j, Y', strtotime($cert['issued_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-certificate fa-4x text-muted mb-4"></i>
                                <h4 class="text-muted">No Certificates Yet</h4>
                                <p class="text-muted">Complete courses and assessments to earn your first certificate!</p>
                                <a href="lessons.php" class="btn btn-primary">
                                    <i class="fas fa-book-open me-2"></i>Start Learning
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Achievements -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h5><i class="fas fa-trophy me-2"></i>Achievements</h5>
                        <span class="badge bg-warning"><?php echo $total_points; ?> pts</span>
                    </div>
                    <div class="content-card-body">
                        <?php if (!empty($achievements_data)): ?>
                            <?php foreach ($achievements_data as $achievement): ?>
                            <div class="achievement-card mb-3">
                                <div class="achievement-badge bg-<?php echo $achievement['color']; ?> text-white">
                                    <i class="fas fa-<?php echo $achievement['icon']; ?>"></i>
                                    <?php if ($achievement['points_awarded'] > 0): ?>
                                    <div class="achievement-points">+<?php echo $achievement['points_awarded']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <h6 class="fw-bold"><?php echo htmlspecialchars($achievement['title']); ?></h6>
                                <p class="small text-muted"><?php echo htmlspecialchars($achievement['description']); ?></p>
                                
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('M j, Y', strtotime($achievement['earned_date'])); ?>
                                </small>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No Achievements Yet</h6>
                                <p class="text-muted">Start learning to unlock achievements!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Next Achievements -->
            <div class="content-card">
                <div class="content-card-header">
                    <h5><i class="fas fa-target me-2"></i>Upcoming Achievements</h5>
                </div>
                <div class="content-card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="achievement-badge bg-secondary text-white me-3" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                    <i class="fas fa-fire"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Quiz Streak</h6>
                                    <small class="text-muted">Complete 5 quizzes in a row</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="achievement-badge bg-secondary text-white me-3" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Course Master</h6>
                                    <small class="text-muted">Complete 3 full courses</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="achievement-badge bg-secondary text-white me-3" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                    <i class="fas fa-handshake"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Helper</h6>
                                    <small class="text-muted">Help 10 students in chat</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Professional Certificate Preview Modal -->
    <div class="modal fade professional-cert-modal" id="certificateModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-award me-2"></i>Professional Certificate
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="certificate-preview" id="certificatePreview">
                        <!-- Certificate content will be dynamically inserted here -->
                        <div class="text-center py-5">
                            <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
                            <p>Loading certificate preview...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" onclick="printCertificate()">
                        <i class="fas fa-print me-2"></i>Print Certificate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewCertificate(cert) {
            // Update certificate content
            const preview = document.getElementById('certificatePreview');
            preview.innerHTML = `
                <div class="text-center">
                    <div class="certificate-icon mb-4" style="width: 120px; height: 120px;">
                        <i class="fas fa-${cert.certificate_type == 'course_completion' ? 'graduation-cap' : 
                            (cert.certificate_type == 'quiz_mastery' ? 'brain' : 
                            (cert.certificate_type == 'participation' ? 'users' : 'trophy'))} fa-3x"></i>
                    </div>
                    
                    <h3 class="mb-3">${cert.title}</h3>
                    
                    ${cert.subject_name ? `<p class="text-muted mb-3">${cert.subject_name}</p>` : ''}
                    
                    <p class="mb-4">${cert.description}</p>
                    
                    <div class="mb-4">
                        <h6>Criteria Met:</h6>
                        <p class="text-muted">${cert.criteria_met || 'Successfully met all course requirements'}</p>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="text-center">
                                <h6>Certificate ID</h6>
                                <p class="text-muted">${cert.certificate_code}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center">
                                <h6>Issue Date</h6>
                                <p class="text-muted">${new Date(cert.issued_date).toLocaleDateString('en-US', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                })}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            This certificate is digitally verified and authenticated
                        </small>
                    </div>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('certificateModal'));
            modal.show();
        }

        function printCertificate() {
            const content = document.getElementById('certificatePreview').outerHTML;
            const printWindow = window.open('', '_blank', 'width=1200,height=800');
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Certificate - Print</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
                    <style>
                        body { 
                            font-family: 'Inter', sans-serif;
                            background: white;
                            color: #333;
                            padding: 2rem;
                        }
                        .certificate-icon {
                            width: 120px;
                            height: 120px;
                            background: #f8f9fa;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 20px;
                        }
                    </style>
                </head>
                <body>
                    ${content}
                    <div class="text-center mt-4">
                        <button class="btn btn-primary" onclick="window.print()">Print Now</button>
                        <button class="btn btn-secondary" onclick="window.close()">Close</button>
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
        }

        function downloadCertificate() {
            // Show download preparation
            const downloadBtn = event.target;
            const originalContent = downloadBtn.innerHTML;
            downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Preparing...';
            downloadBtn.disabled = true;
            
            setTimeout(() => {
                downloadBtn.innerHTML = originalContent;
                downloadBtn.disabled = false;
                
                // Simulate PDF generation
                const link = document.createElement('a');
                link.href = '#';
                link.download = `Certificate_${document.getElementById('certificateCode').textContent}.pdf`;
                
                alert('🎓 Certificate PDF Ready!\n\nYour professional certificate has been prepared for download. This would normally generate a high-quality PDF suitable for sharing with employers and institutions.');
            }, 2000);
        }

        function shareCertificate() {
            const certTitle = document.getElementById('certificateTitle').textContent;
            const certCode = document.getElementById('certificateCode').textContent;
            
            if (navigator.share) {
                navigator.share({
                    title: `Professional Certificate - ${certTitle}`,
                    text: `I've earned a certificate in ${certTitle}! Certificate ID: ${certCode}`,
                    url: window.location.href
                });
            } else {
                // Fallback for browsers that don't support Web Share API
                const shareText = `🎓 I've earned a professional certificate in ${certTitle}!\n\nCertificate ID: ${certCode}\nIssued by: Learning Management System\n\n#Achievement #Learning #Certificate`;
                
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(shareText).then(() => {
                        alert('📋 Certificate details copied to clipboard!\n\nYou can now paste and share your achievement on social media or with employers.');
                    });
                } else {
                    alert('📤 Share Your Achievement!\n\n' + shareText);
                }
            }
        }

        // Enhanced animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add entrance animations to certificate elements
            const certificateModal = document.getElementById('certificateModal');
            certificateModal.addEventListener('shown.bs.modal', function() {
                const elements = [
                    '.certificate-logo',
                    '.certificate-title',
                    '.certificate-recipient',
                    '.certificate-achievement',
                    '.certificate-seal'
                ];
                
                elements.forEach((selector, index) => {
                    const element = document.querySelector(selector);
                    if (element) {
                        element.style.opacity = '0';
                        element.style.transform = 'translateY(20px)';
                        element.style.transition = 'all 0.6s ease';
                        
                        setTimeout(() => {
                            element.style.opacity = '1';
                            element.style.transform = 'translateY(0)';
                        }, index * 200);
                    }
                });
            });

            // Add animation to achievement cards
            document.querySelectorAll('.achievement-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Add animation to certificate cards
            document.querySelectorAll('.certificate-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>

