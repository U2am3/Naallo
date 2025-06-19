<?php
session_start();
require_once '../../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

// Check if role is provided
if (!isset($_GET['role'])) {
    http_response_code(400);
    exit('Role parameter is required');
}

try {
    $role = $_GET['role'];
    $department = isset($_GET['department']) ? $_GET['department'] : null;
    
    // Base query
    $query = "
        SELECT e.emp_id, e.basic_salary, CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM employees e
        JOIN users u ON e.user_id = u.user_id
        WHERE u.role = ?
    ";
    
    $params = [$role];
    
    // Add department filter if provided
    if ($department) {
        $query .= " AND e.dept_id = ?";
        $params[] = $department;
    }
    
    $query .= " ORDER BY e.first_name, e.last_name";
    
    // Fetch employees based on role and department
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($employees);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 