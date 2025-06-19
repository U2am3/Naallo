<?php
session_start();
require_once '../../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if department ID is provided
if (!isset($_POST['dept_id'])) {
    echo json_encode(['error' => 'Department ID is required']);
    exit();
}

try {
    // Fetch employees for the selected department
    $stmt = $pdo->prepare("
        SELECT 
            e.emp_id,
            e.first_name,
            e.last_name,
            e.basic_salary
        FROM employees e
        JOIN users u ON e.user_id = u.user_id
        WHERE e.dept_id = ? AND u.status = 'active'
        ORDER BY e.first_name, e.last_name
    ");
    
    $stmt->execute([$_POST['dept_id']]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($employees);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 