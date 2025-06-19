<?php
require_once 'config/database.php';

try {
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'admin'");
    $admin = $stmt->fetch();
    echo "Database connection successful! Admin user exists.";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
} 