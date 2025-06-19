<?php
session_start();
require_once '../../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if required fields are provided
if (!isset($_POST['payroll_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Update payroll status
    $stmt = $pdo->prepare("
        UPDATE payroll 
        SET status = ?,
            updated_at = NOW()
        WHERE payroll_id = ?
    ");
    $stmt->execute([$_POST['status'], $_POST['payroll_id']]);
    
    // If status is 'paid', update payroll period status as well
    if ($_POST['status'] === 'paid') {
        $stmt = $pdo->prepare("
            UPDATE payroll_periods pp
            JOIN payroll p ON pp.period_id = p.period_id
            SET pp.status = 'closed'
            WHERE p.payroll_id = ?
        ");
        $stmt->execute([$_POST['payroll_id']]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Payroll status updated successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Error updating payroll status: ' . $e->getMessage()]);
} 