<?php
session_start();


$conn = new mysqli('localhost', 'root', '', 'learning_platform');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';


$create_table = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') DEFAULT 'student',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_table);


$admin_exists = $conn->query("SELECT id FROM users WHERE email = 'admin@admin.com'")->num_rows;
if ($admin_exists == 0) {
    $admin_hash = password_hash('admin', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (name, email, password, role) VALUES ('Admin', 'admin@admin.com', '$admin_hash', 'admin')");
}

$student_exists = $conn->query("SELECT id FROM users WHERE email = 'student@student.com'")->num_rows;
if ($student_exists == 0) {
    $student_hash = password_hash('student', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (name, email, password, role) VALUES ('Student', 'student@student.com', '$student_hash', 'student')");
}


if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] == 'admin') {
                header("Location: admin/dashboard.php");
                exit();
            } else {
                header("Location: student/dashboard.php");
                exit();
            }
        } else {
            $message = '<div class="alert alert-danger">Wrong password!</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Email not found!</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      body {
    background: linear-gradient(135deg, #1a202c 0%, #2d3748 50%, #1a365d 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
    position: relative;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
    pointer-events: none;
}

.login-box {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(0, 0, 0, 0.2) 100%);
    backdrop-filter: blur(20px);
    padding: 50px 40px;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4), 
                0 0 0 1px rgba(255,255,255,0.15);
    max-width: 420px;
    width: 100%;
    position: relative;
    overflow: hidden;
    color: #f8fafc;
}

.login-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: shimmer 3s ease-in-out infinite;
}

.login-box h2, .login-box h3, .login-box h4 {
    color: #f8fafc;
    text-align: center;
    margin-bottom: 30px;
    font-weight: 700;
}

.form-control {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.08);
    color: #f8fafc;
    font-size: 16px;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.form-control:focus {
    outline: none;
    border-color: rgba(255, 255, 255, 0.4);
    background: rgba(255, 255, 255, 0.12);
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.1);
    transform: translateY(-2px);
}

.form-control::placeholder {
    color: #cbd5e1;
    opacity: 0.8;
}

.btn {
    padding: 15px 30px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 16px;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
    overflow: hidden;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.btn-primary {
    background: linear-gradient(135deg, #374151 0%, #4b5563 50%, #6b7280 100%);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #f8fafc;
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.4);
    background: linear-gradient(135deg, #4b5563 0%, #6b7280 50%, #9ca3af 100%);
}

.btn-primary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s ease;
}

.btn-primary:hover::before {
    left: 100%;
}

.form-label {
    color: #e2e8f0;
    font-weight: 500;
    margin-bottom: 8px;
    display: block;
}

.text-muted {
    color: #9ca3af !important;
}

.alert {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
}

a {
    color: #cbd5e1;
    text-decoration: none;
    transition: color 0.3s ease;
}

a:hover {
    color: #f8fafc;
    text-shadow: 0 0 8px rgba(248, 250, 252, 0.5);
}
p{
    margin-top: 10px;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    50% { transform: translateX(100%); }
    100% { transform: translateX(-100%); }
}

/* Responsive design */
@media (max-width: 480px) {
    .login-box {
        padding: 40px 30px;
        margin: 20px;
    }
}
    </style>
</head>
<body>
    <div class="login-box">
        <div class="text-center mb-4">
            <h2>🎓 Learning Portal</h2>
            <p class="text-muted">Sign in to continue</p>
        </div>
        
        <?php echo $message; ?>
        
        <form method="POST">
            <input type="email" name="email" class="form-control" placeholder="Email Address" required>
            <input type="password" name="password" class="form-control" placeholder="Password" required>
            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
        </form>
        <div class="text-center">
                <p>Create a account? <a href="register.php" class="text-decoration-none">Sign up here</a></p>
            </div>
    <script>
        function fillForm(email, password) {
            document.querySelector('input[name="email"]').value = email;
            document.querySelector('input[name="password"]').value = password;
        }
    </script>
</body>
</html>

