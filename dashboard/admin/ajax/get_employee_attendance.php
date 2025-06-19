<?php
session_start();
require_once '../../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if (!isset($_POST['employee_id']) || !isset($_POST['pay_period'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$employee_id = $_POST['employee_id'];
$pay_period = $_POST['pay_period'];
$month = date('m', strtotime($pay_period));
$year = date('Y', strtotime($pay_period));

$stmt = $pdo->prepare("SELECT days_present, days_late, days_absent FROM attendance_performance WHERE emp_id = ? AND month = ? AND year = ?");
$stmt->execute([$employee_id, $month, $year]);
$row = $stmt->fetch();

if ($row) {
    // Calculate bonus percentage
    $bonus_percentage = 0;
    if ($row['days_present'] >= 22) {
        $bonus_percentage = 10;
    } elseif ($row['days_present'] >= 15) {
        $bonus_percentage = 5;
    }
    echo json_encode([
        'success' => true,
        'days_present' => $row['days_present'],
        'days_late' => $row['days_late'],
        'days_absent' => $row['days_absent'],
        'bonus_percentage' => $bonus_percentage
    ]);
} else {
    echo json_encode([
        'success' => true,
        'days_present' => 0,
        'days_late' => 0,
        'days_absent' => 0,
        'bonus_percentage' => 0
    ]);
}
