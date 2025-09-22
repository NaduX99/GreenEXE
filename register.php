<?php
session_start();

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'learning_platform';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = '';
$success_message = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Email address already registered.";
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, 'student', 'active', NOW())");
            $stmt->bind_param("sss", $name, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $success_message = "Registration successful! You can now login.";
            } else {
                $error_message = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Learning Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
    background: linear-gradient(135deg, #1a202c 0%, #2d3748 50%, #1a365d 100%);
    min-height: 100vh;
    font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
    color: #e2e8f0;
}

.register-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    position: relative;
}

.register-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.05)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.05)"/><circle cx="40" cy="80" r="1" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
}

.register-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(0, 0, 0, 0.2) 100%);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1);
    padding: 48px;
    width: 100%;
    max-width: 450px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    position: relative;
    z-index: 1;
}

.register-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    border-radius: 24px 24px 0 0;
}

.register-header {
    text-align: center;
    margin-bottom: 36px;
}

.logo {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #1a365d 0%, #2c5282 50%, #374151 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    color: white;
    font-size: 32px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.1);
    position: relative;
}

.logo::before {
    content: '';
    position: absolute;
    inset: -2px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent, rgba(255,255,255,0.1));
    z-index: -1;
}

.form-control {
    border-radius: 16px;
    border: 2px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.05);
    padding: 16px 18px;
    font-size: 16px;
    margin-bottom: 24px;
    color: #f1f5f9;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.form-control::placeholder {
    color: #9ca3af;
}

.form-control:focus {
    border-color: rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.1);
    outline: none;
}

.btn-register {
    background: linear-gradient(135deg, #374151 0%, #1f2937 50%, #111827 100%);
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 16px;
    font-size: 16px;
    font-weight: 600;
    width: 100%;
    color: #f8fafc;
    margin-bottom: 24px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-register::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.5s ease;
}

.btn-register:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.3);
    background: linear-gradient(135deg, #4b5563 0%, #374151 50%, #1f2937 100%);
    border-color: rgba(255, 255, 255, 0.2);
    color: #ffffff;
}

.btn-register:hover::before {
    left: 100%;
}

.alert {
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 24px;
    background: rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(10px);
    color: #f1f5f9;
}

.alert-danger {
    background: linear-gradient(135deg, rgba(55, 65, 81, 0.3) 0%, rgba(17, 24, 39, 0.3) 100%);
    border-color: rgba(107, 114, 128, 0.3);
    color: #d1d5db;
}

.alert-success {
    background: linear-gradient(135deg, rgba(209, 213, 219, 0.1) 0%, rgba(107, 114, 128, 0.2) 100%);
    border-color: rgba(209, 213, 219, 0.3);
    color: #f3f4f6;
}

h1, h2, h3 {
    color: #f8fafc;
    font-weight: 700;
}

.text-muted {
    color: #9ca3af !important;
}

/* Form labels */
label {
    color: #e5e7eb;
    font-weight: 500;
    margin-bottom: 8px;
    display: block;
}

/* Links */
a {
    color: #cbd5e1;
    transition: color 0.3s ease;
}

a:hover {
    color: #f1f5f9;
    text-decoration: none;
}
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2>Create Account</h2>
                <p class="text-muted">Join our Learning Platform today</p>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="name" class="form-label"><i class="fas fa-user me-2"></i>Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label"><i class="fas fa-envelope me-2"></i>Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn btn-register">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
            </form>
            
            <div class="text-center">
                <p>Already have an account? <a href="login.php" class="text-decoration-none">Sign in here</a></p>
            </div>
        </div>
    </div>
</body>
</html>
