<?php
session_start();
require_once '../../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if required fields are provided
if (!isset($_POST['employee_id']) || !isset($_POST['pay_period']) || !isset($_POST['basic_salary'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Create payroll period if it doesn't exist
    $stmt = $pdo->prepare("
        INSERT INTO payroll_periods (start_date, end_date, status, created_by) 
        VALUES (?, ?, 'draft', ?)
    ");
    $first_day = date('Y-m-01', strtotime($_POST['pay_period']));
    $last_day = date('Y-m-t', strtotime($_POST['pay_period']));
    $stmt->execute([$first_day, $last_day, $_SESSION['user_id']]);
    $period_id = $pdo->lastInsertId();
    
    // Get attendance performance for the period
    $stmt = $pdo->prepare("
        SELECT * FROM attendance_performance 
        WHERE emp_id = ? AND month = ? AND year = ?
    ");
    $month = date('m', strtotime($_POST['pay_period']));
    $year = date('Y', strtotime($_POST['pay_period']));
    $stmt->execute([$_POST['employee_id'], $month, $year]);
    $attendance = $stmt->fetch();
    
    // Calculate bonus based on attendance
    $bonus_percentage = 0;
    if ($attendance['days_present'] >= 22) {
        $bonus_percentage = 10;
    } elseif ($attendance['days_present'] >= 15) {
        $bonus_percentage = 5;
    }
    $bonus_amount = $_POST['basic_salary'] * ($bonus_percentage / 100);
    
    // Calculate gross and net salary
    $gross_salary = $_POST['basic_salary'] + $bonus_amount;
    $net_salary = $gross_salary; // No deductions in this simplified version
    
    // Create payroll record
    $stmt = $pdo->prepare("
        INSERT INTO payroll (
            employee_id, period_id, basic_salary, gross_salary, net_salary, status
        ) VALUES (?, ?, ?, ?, ?, 'draft')
    ");
    $stmt->execute([
        $_POST['employee_id'],
        $period_id,
        $_POST['basic_salary'],
        $gross_salary,
        $net_salary
    ]);
    $payroll_id = $pdo->lastInsertId();
    
    // Add bonus as adjustment if applicable
    if ($bonus_amount > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO payroll_adjustments (
                payroll_id, employee_id, adjustment_type, amount, description, status, approved_by
            ) VALUES (?, ?, 'bonus', ?, ?, 'approved', ?)
        ");
        $stmt->execute([
            $payroll_id,
            $_POST['employee_id'],
            $bonus_amount,
            "Attendance bonus for {$attendance['days_present']} days present",
            $_SESSION['user_id']
        ]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Payroll created successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Error creating payroll: ' . $e->getMessage()]);
} 