<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Calculate total hours worked based on time in and time out
 * @param string $time_in Time in (H:i:s format)
 * @param string $time_out Time out (H:i:s format)
 * @return float Total hours worked
 */
function calculateTotalHours($time_in, $time_out) {
    if (empty($time_in) || empty($time_out)) {
        return 0;
    }
    
    $time_in = strtotime($time_in);
    $time_out = strtotime($time_out);
    
    $diff = $time_out - $time_in;
    return round($diff / 3600, 2);
}

/**
 * Calculate attendance status based on hours worked and policy
 * @param float $total_hours Total hours worked
 * @return string Attendance status
 */
function calculateAttendanceStatus($total_hours) {
    global $pdo;
    
    // Get attendance policy
    $stmt = $pdo->query("SELECT * FROM attendance_policy ORDER BY policy_id DESC LIMIT 1");
    $policy = $stmt->fetch();
    
    if ($total_hours >= $policy['min_hours_present']) {
        return 'present';
    } elseif ($total_hours >= $policy['min_hours_late']) {
        return 'late';
    } elseif ($total_hours > 0) {
        return 'half-day';
    } else {
        return 'absent';
    }
}

/**
 * Get attendance statistics for a given date range
 * @param string $start_date Start date (Y-m-d format)
 * @param string $end_date End date (Y-m-d format)
 * @param int|null $department_id Department ID (optional)
 * @return array Attendance statistics
 */
function getAttendanceStatistics($start_date, $end_date, $department_id = null) {
    global $pdo;
    
    $query = "
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN status = 'half-day' THEN 1 ELSE 0 END) as halfday_count,
            AVG(total_hours) as avg_hours_worked
        FROM attendance a
        JOIN employees e ON a.emp_id = e.emp_id
        WHERE a.attendance_date BETWEEN ? AND ?
    ";
    
    $params = [$start_date, $end_date];
    
    if ($department_id) {
        $query .= " AND e.dept_id = ?";
        $params[] = $department_id;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetch();
}

/**
 * Check if employee needs attendance notification
 * @param int $emp_id Employee ID
 * @return array Array of notifications needed
 */
function checkAttendanceNotifications($emp_id) {
    global $pdo;
    
    $notifications = [];
    $today = date('Y-m-d');
    
    // Check if employee has checked in today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM attendance 
        WHERE emp_id = ? AND attendance_date = ?
    ");
    $stmt->execute([$emp_id, $today]);
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $notifications[] = [
            'type' => 'check_in_reminder',
            'message' => 'You haven\'t checked in today'
        ];
    }
    
    // Check if employee has checked out today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM attendance 
        WHERE emp_id = ? AND attendance_date = ? AND time_out IS NULL
    ");
    $stmt->execute([$emp_id, $today]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        $notifications[] = [
            'type' => 'check_out_reminder',
            'message' => 'You haven\'t checked out today'
        ];
    }
    
    return $notifications;
}

/**
 * Generate attendance report
 * @param string $report_type Type of report (daily, monthly, department, employee)
 * @param string $start_date Start date (Y-m-d format)
 * @param string $end_date End date (Y-m-d format)
 * @param int|null $department_id Department ID (optional)
 * @param int|null $emp_id Employee ID (optional)
 * @return array Report data
 */
function generateAttendanceReport($report_type, $start_date, $end_date, $department_id = null, $emp_id = null) {
    global $pdo;
    
    $query = "
        SELECT 
            a.*,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            d.dept_name,
            e.position,
            u.role
        FROM attendance a
        JOIN employees e ON a.emp_id = e.emp_id
        JOIN users u ON e.user_id = u.user_id
        LEFT JOIN departments d ON e.dept_id = d.dept_id
        WHERE a.attendance_date BETWEEN ? AND ?
    ";
    
    $params = [$start_date, $end_date];
    
    if ($department_id) {
        $query .= " AND e.dept_id = ?";
        $params[] = $department_id;
    }
    
    if ($emp_id) {
        $query .= " AND e.emp_id = ?";
        $params[] = $emp_id;
    }
    
    $query .= " ORDER BY a.attendance_date DESC, a.time_in DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    
    // Calculate statistics
    $stats = getAttendanceStatistics($start_date, $end_date, $department_id);
    
    return [
        'records' => $records,
        'statistics' => $stats
    ];
}

/**
 * Export attendance data to CSV
 * @param array $data Attendance data
 * @param string $filename Output filename
 */
function exportAttendanceToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    fputcsv($output, [
        'Date',
        'Employee Name',
        'Department',
        'Position',
        'Time In',
        'Time Out',
        'Total Hours',
        'Status',
        'Notes'
    ]);
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, [
            $row['attendance_date'],
            $row['employee_name'],
            $row['dept_name'],
            $row['position'],
            $row['time_in'],
            $row['time_out'],
            $row['total_hours'],
            $row['status'],
            $row['notes']
        ]);
    }
    
    fclose($output);
    exit;
} 