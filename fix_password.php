<?php
// Database connection (since includes/config.php path issue)
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'learning_platform';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Generate correct hash for password "12345678"
$email = 'admin@learning.com';
$correct_password = '12345678';
$correct_hash = password_hash($correct_password, PASSWORD_DEFAULT);

echo "Updating password for: " . $email . "<br>";
echo "New password: " . $correct_password . "<br>";
echo "New hash: " . $correct_hash . "<br><br>";

// Update the database
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("ss", $correct_hash, $email);

if ($stmt->execute()) {
    echo "✅ Password updated successfully!<br><br>";
    
    // Test the new password immediately
    echo "<h3>Testing new password:</h3>";
    
    $check_stmt = $conn->prepare("SELECT password FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $verification_test = password_verify($correct_password, $row['password']);
        echo "Password verification test: " . ($verification_test ? '✅ SUCCESS' : '❌ FAILED') . "<br>";
        
        if ($verification_test) {
            echo "<br><strong style='color: green;'>SUCCESS! You can now login with:</strong><br>";
            echo "Email: admin@learning.com<br>";
            echo "Password: 12345678<br>";
        }
    }
    
    $check_stmt->close();
} else {
    echo "❌ Error updating password: " . $conn->error;
}

$stmt->close();
$conn->close();

echo "<br><br><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";
?>
