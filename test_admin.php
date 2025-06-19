<?php
require_once 'config/database.php';

try {
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin) {
        echo "Admin user details:<br>";
        echo "Username: " . $admin['username'] . "<br>";
        echo "Role: " . $admin['role'] . "<br>";
        echo "Status: " . $admin['status'] . "<br>";
        echo "Email: " . $admin['email'] . "<br>";
        
        // Test password verification
        $test_password = 'admin123';
        if (password_verify($test_password, $admin['password'])) {
            echo "Password verification successful!";
        } else {
            echo "Password verification failed!";
        }
    } else {
        echo "Admin user not found!";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 