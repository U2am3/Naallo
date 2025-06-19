<?php
session_start();
require_once '../../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if required fields are provided
if (!isset($_POST['payroll_id']) || !isset($_POST['basic_salary']) || !isset($_POST['bonus_amount']) || 
    !isset($_POST['gross_salary']) || !isset($_POST['net_salary'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Update payroll record
    $stmt = $pdo->prepare("
        UPDATE payroll 
        SET basic_salary = ?, 
            gross_salary = ?, 
            net_salary = ?
        WHERE payroll_id = ?
    ");
    $stmt->execute([
        $_POST['basic_salary'],
        $_POST['gross_salary'],
        $_POST['net_salary'],
        $_POST['payroll_id']
    ]);
    
    // Update bonus adjustment if it exists
    $stmt = $pdo->prepare("
        UPDATE payroll_adjustments 
        SET amount = ?
        WHERE payroll_id = ? 
        AND adjustment_type = 'bonus'
    ");
    $stmt->execute([$_POST['bonus_amount'], $_POST['payroll_id']]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Payroll updated successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Error updating payroll: ' . $e->getMessage()]);
} 