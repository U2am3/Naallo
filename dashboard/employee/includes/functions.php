<?php
/**
 * Employee Dashboard Functions
 */

/**
 * Get status badge class based on attendance status
 * @param string $status The attendance status
 * @return string Bootstrap badge class
 */
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'present':
            return 'success';
        case 'late':
            return 'warning';
        case 'absent':
            return 'danger';
        case 'half-day':
            return 'info';
        default:
            return 'secondary';
    }
}

/**
 * Format date to a readable format
 * @param string $date The date to format
 * @return string Formatted date
 */
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

/**
 * Format time to 12-hour format
 * @param string $time The time to format
 * @return string Formatted time
 */
function formatTime($time) {
    return $time ? date('h:i A', strtotime($time)) : '-';
}

/**
 * Calculate leave percentage
 * @param int $used Number of used leave days
 * @param int $total Total number of leave days
 * @return float Percentage of used leaves
 */
function calculateLeavePercentage($used, $total) {
    if ($total == 0) return 0;
    return ($used / $total) * 100;
}

/**
 * Get leave progress bar color based on percentage
 * @param float $percentage The percentage of used leaves
 * @return string Bootstrap color class
 */
function getLeaveProgressColor($percentage) {
    if ($percentage > 80) return 'danger';
    if ($percentage > 60) return 'warning';
    return 'success';
}

/**
 * Check if attendance can be marked for today
 * @return bool True if attendance can be marked, false otherwise
 */
function canMarkAttendanceToday() {
    global $pdo;
    $emp_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM attendance 
        WHERE emp_id = ? 
        AND DATE(attendance_date) = CURDATE()
    ");
    $stmt->execute([$emp_id]);
    $result = $stmt->fetch();
    
    return $result['count'] == 0;
}

/**
 * Get employee's current project count
 * @param int $emp_id Employee ID
 * @return int Number of active projects
 */
function getActiveProjectCount($emp_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM project_assignments pa
        JOIN projects p ON pa.project_id = p.project_id
        WHERE pa.emp_id = ? AND p.status = 'ongoing'
    ");
    $stmt->execute([$emp_id]);
    $result = $stmt->fetch();
    
    return $result['count'];
}

/**
 * Get employee's pending leave requests count
 * @param int $emp_id Employee ID
 * @return int Number of pending leave requests
 */
function getPendingLeaveCount($emp_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM leave_requests 
        WHERE emp_id = ? AND status = 'pending'
    ");
    $stmt->execute([$emp_id]);
    $result = $stmt->fetch();
    
    return $result['count'];
} 