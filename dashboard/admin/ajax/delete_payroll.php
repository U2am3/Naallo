<?php
session_start();
require_once '../../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if payroll_id is provided
if (!isset($_POST['payroll_id'])) {
    echo json_encode(['success' => false, 'error' => 'Payroll ID is required']);
    exit();
}

$payroll_id = $_POST['payroll_id'];

try {
    // First check if the payroll exists and is not paid
    $stmt = $pdo->prepare("SELECT status FROM payroll WHERE payroll_id = ?");
    $stmt->execute([$payroll_id]);
    $payroll = $stmt->fetch();

    if (!$payroll) {
        echo json_encode(['success' => false, 'error' => 'Payroll record not found']);
        exit();
    }

    if ($payroll['status'] === 'paid') {
        echo json_encode(['success' => false, 'error' => 'Cannot delete a paid payroll record']);
        exit();
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Delete payroll adjustments first (if any)
    $stmt = $pdo->prepare("DELETE FROM payroll_adjustments WHERE payroll_id = ?");
    $stmt->execute([$payroll_id]);

    // Delete the payroll record
    $stmt = $pdo->prepare("DELETE FROM payroll WHERE payroll_id = ?");
    $stmt->execute([$payroll_id]);

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} 