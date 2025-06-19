<?php
require_once 'database.php';

try {
    // First, check if admin user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();

    if (!$admin) {
        // Create admin user if it doesn't exist
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, status) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $password, 'admin@example.com', 'admin', 'active']);
        echo "Admin user created successfully!<br>";
    } else {
        // Reset admin password and ensure active status
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, status = 'active', role = 'admin' WHERE username = 'admin'");
        $stmt->execute([$password]);
        echo "Admin user password reset successfully!<br>";
    }

    // Verify the admin user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin) {
        echo "Admin user details:<br>";
        echo "Username: " . $admin['username'] . "<br>";
        echo "Role: " . $admin['role'] . "<br>";
        echo "Status: " . $admin['status'] . "<br>";
        echo "Email: " . $admin['email'] . "<br>";
    }

    echo "Admin reset completed successfully!";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?> 