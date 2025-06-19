<?php
session_start();
require_once '../../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if payroll ID is provided
if (!isset($_GET['payroll_id'])) {
    echo json_encode(['error' => 'Payroll ID is required']);
    exit();
}

try {
    // Fetch payroll details with employee and department information
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            d.dept_name as department,
            CONCAT(DATE_FORMAT(pp.start_date, '%M %d, %Y'), ' - ', DATE_FORMAT(pp.end_date, '%M %d, %Y')) as period,
            COALESCE(pa.amount, 0) as bonus_amount
        FROM payroll p
        JOIN employees e ON p.employee_id = e.emp_id
        JOIN departments d ON e.dept_id = d.dept_id
        JOIN payroll_periods pp ON p.period_id = pp.period_id
        LEFT JOIN payroll_adjustments pa ON p.payroll_id = pa.payroll_id AND pa.adjustment_type = 'bonus'
        WHERE p.payroll_id = ?
    ");
    
    $stmt->execute([$_GET['payroll_id']]);
    $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($payroll) {
        // Format currency values
        $payroll['basic_salary'] = '₱' . number_format($payroll['basic_salary'], 2);
        $payroll['gross_salary'] = '₱' . number_format($payroll['gross_salary'], 2);
        $payroll['net_salary'] = '₱' . number_format($payroll['net_salary'], 2);
        $payroll['bonus_amount'] = '₱' . number_format($payroll['bonus_amount'], 2);
        
        echo json_encode($payroll);
    } else {
        echo json_encode(['error' => 'Payroll not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 