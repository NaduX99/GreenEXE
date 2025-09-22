<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'learning_platform';

echo "<h2>🔌 Database Connection Test</h2>";
echo "<hr>";

// Test 1: Basic Connection
echo "<h3>1. Testing Basic Connection</h3>";
$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    echo "❌ Connection to MySQL failed: " . $conn->connect_error . "<br>";
    die();
} else {
    echo "✅ Connected to MySQL server successfully<br>";
}

// Test 2: Database Selection
echo "<h3>2. Testing Database Selection</h3>";
if ($conn->select_db($database)) {
    echo "✅ Database '$database' selected successfully<br>";
} else {
    echo "❌ Database '$database' not found<br>";
    echo "Creating database '$database'...<br>";
    
    if ($conn->query("CREATE DATABASE $database")) {
        echo "✅ Database '$database' created successfully<br>";
        $conn->select_db($database);
    } else {
        echo "❌ Failed to create database: " . $conn->error . "<br>";
        die();
    }
}

// Test 3: Check Tables
echo "<h3>3. Checking Required Tables</h3>";
$required_tables = ['users', 'quizzes', 'lessons', 'meetings', 'chat_messages', 'daily_leaderboard'];

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "✅ Table '$table' exists<br>";
    } else {
        echo "❌ Table '$table' missing<br>";
    }
}

// Test 4: Create Users Table if Missing
$users_table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($users_table_check->num_rows == 0) {
    echo "<h3>4. Creating Users Table</h3>";
    $create_users_table = "
    CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'student') NOT NULL DEFAULT 'student',
        status ENUM('active', 'inactive', 'banned', 'online', 'offline') NOT NULL DEFAULT 'active',
        chat_banned TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($create_users_table)) {
        echo "✅ Users table created successfully<br>";
    } else {
        echo "❌ Failed to create users table: " . $conn->error . "<br>";
    }
}

// Test 5: Check Admin User
echo "<h3>5. Checking Admin User</h3>";
$admin_check = $conn->query("SELECT * FROM users WHERE email = 'admin@learning.com'");

if ($admin_check->num_rows == 0) {
    echo "❌ Admin user not found. Creating...<br>";
    
    $admin_password = '12345678';
    $admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);
    
    $insert_admin = "INSERT INTO users (name, email, password, role, status, created_at) VALUES 
                     ('Administrator', 'admin@learning.com', '$admin_hash', 'admin', 'active', NOW())";
    
    if ($conn->query($insert_admin)) {
        echo "✅ Admin user created successfully<br>";
        echo "Email: admin@learning.com<br>";
        echo "Password: 12345678<br>";
    } else {
        echo "❌ Failed to create admin user: " . $conn->error . "<br>";
    }
} else {
    echo "✅ Admin user exists<br>";
    $admin = $admin_check->fetch_assoc();
    echo "Name: " . $admin['name'] . "<br>";
    echo "Email: " . $admin['email'] . "<br>";
    echo "Role: " . $admin['role'] . "<br>";
    echo "Status: " . $admin['status'] . "<br>";
}

// Test 6: Password Verification Test
echo "<h3>6. Password Verification Test</h3>";
$test_password = '12345678';
$user_result = $conn->query("SELECT password FROM users WHERE email = 'admin@learning.com'");

if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $verify_result = password_verify($test_password, $user_data['password']);
    
    if ($verify_result) {
        echo "✅ Password verification works correctly<br>";
    } else {
        echo "❌ Password verification failed. Fixing...<br>";
        
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        $update_password = "UPDATE users SET password = '$new_hash' WHERE email = 'admin@learning.com'";
        
        if ($conn->query($update_password)) {
            echo "✅ Password hash updated successfully<br>";
        } else {
            echo "❌ Failed to update password: " . $conn->error . "<br>";
        }
    }
}

// Test 7: PHP Configuration
echo "<h3>7. PHP Configuration Check</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Session support: " . (extension_loaded('session') ? '✅ Enabled' : '❌ Disabled') . "<br>";
echo "MySQLi support: " . (extension_loaded('mysqli') ? '✅ Enabled' : '❌ Disabled') . "<br>";
echo "Password hashing: " . (function_exists('password_hash') ? '✅ Available' : '❌ Not available') . "<br>";

// Test 8: Session Test
echo "<h3>8. Session Test</h3>";
if (!session_id()) {
    session_start();
}
echo "Session ID: " . session_id() . "<br>";
echo "Session status: " . session_status() . "<br>";
$_SESSION['test'] = 'working';
echo "Session write test: " . (isset($_SESSION['test']) && $_SESSION['test'] == 'working' ? '✅ Success' : '❌ Failed') . "<br>";

echo "<hr>";
echo "<h3>📋 Summary</h3>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px;'>";
echo "<strong>Connection Status:</strong> " . ($conn->ping() ? '✅ Active' : '❌ Lost') . "<br>";
echo "<strong>Database:</strong> $database<br>";
echo "<strong>Admin Login:</strong><br>";
echo "&nbsp;&nbsp;Email: admin@learning.com<br>";
echo "&nbsp;&nbsp;Password: 12345678<br>";
echo "</div>";

echo "<br><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔑 Go to Login Page</a>";

$conn->close();
?>
