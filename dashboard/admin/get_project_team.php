<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['project_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID is required']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            e.emp_id,
            e.user_id,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            d.dept_name,
            e.position,
            pa.assigned_date
        FROM project_assignments pa
        JOIN employees e ON pa.emp_id = e.emp_id
        LEFT JOIN departments d ON e.dept_id = d.dept_id
        WHERE pa.project_id = ?
        ORDER BY e.first_name
    ");
    
    $stmt->execute([$_GET['project_id']]);
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($team_members);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 