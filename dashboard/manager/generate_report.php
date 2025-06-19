<?php
session_start();
require_once '../../config/database.php';
require_once '../admin/includes/functions.php';

// Check if user is logged in and is manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../../login/manager.php");
    exit();
}

// Get manager details
$stmt = $pdo->prepare("
    SELECT e.*, d.dept_id, d.dept_name
    FROM employees e
    LEFT JOIN departments d ON e.dept_id = d.dept_id
    WHERE e.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$manager = $stmt->fetch();

// Get report parameters
$report_type = $_POST['report_type'] ?? 'daily';
$start_date = $_POST['start_date'] ?? date('Y-m-d');
$end_date = $_POST['end_date'] ?? date('Y-m-d');
$department_id = $_POST['department_id'] ?? $manager['dept_id'];

// Generate report data
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            e.first_name,
            e.last_name,
            e.position,
            d.dept_name
        FROM attendance a
        JOIN employees e ON a.emp_id = e.emp_id
        JOIN departments d ON e.dept_id = d.dept_id
        WHERE e.dept_id = ? 
        AND a.attendance_date BETWEEN ? AND ?
        ORDER BY a.attendance_date DESC, e.first_name ASC
    ");
    $stmt->execute([$department_id, $start_date, $end_date]);
    $attendance_data = $stmt->fetchAll();

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');

    // Create CSV file
    $output = fopen('php://output', 'w');

    // Add CSV headers
    fputcsv($output, [
        'Employee Name',
        'Position',
        'Department',
        'Date',
        'Time In',
        'Time Out',
        'Total Hours',
        'Status',
        'Auto Status',
        'IP Address',
        'Device Info'
    ]);

    // Add data rows
    foreach ($attendance_data as $row) {
        fputcsv($output, [
            $row['first_name'] . ' ' . $row['last_name'],
            $row['position'],
            $row['dept_name'],
            $row['attendance_date'],
            $row['time_in'],
            $row['time_out'],
            $row['total_hours'],
            $row['status'],
            $row['auto_status'],
            $row['ip_address'],
            $row['device_info']
        ]);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    $_SESSION['error'] = "Error generating report: " . $e->getMessage();
    header("Location: attendance.php");
    exit();
} 