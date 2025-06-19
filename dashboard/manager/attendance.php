<?php
session_start();
date_default_timezone_set('Asia/Dubai'); // Set timezone for correct check-in/out time
require_once '../../config/database.php';
require_once '../admin/includes/functions.php';

// Check if user is logged in and is manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../../login/manager.php");
    exit();
}

// Get manager details and department
$stmt = $pdo->prepare("
    SELECT e.*, d.dept_id, d.dept_name
    FROM employees e
    LEFT JOIN departments d ON e.dept_id = d.dept_id
    WHERE e.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$manager = $stmt->fetch();

// Handle profile update and password change from topbar modals
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Profile update
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $upload_dir = __DIR__ . '/../../uploads/profile_photos';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error_message = "First name, last name, and email are required fields.";
        } else {
            try {
                $pdo->beginTransaction();
                // Update employees table
                $stmt = $pdo->prepare("
                    UPDATE employees 
                    SET first_name = ?, last_name = ?, phone = ?, address = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$first_name, $last_name, $phone, $address, $_SESSION['user_id']]);
                // Update users table
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET email = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$email, $_SESSION['user_id']]);
                // Handle profile image upload
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $file_extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png'];
                    if (in_array($file_extension, $allowed_extensions)) {
                        $new_filename = 'manager_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . '/' . $new_filename;
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                            // Update profile image in database
                            $stmt = $pdo->prepare("UPDATE employees SET profile_image = ? WHERE user_id = ?");
                            $stmt->execute([$new_filename, $_SESSION['user_id']]);
                        } else {
                            throw new Exception("Error uploading profile image");
                        }
                    } else {
                        throw new Exception("Invalid file type. Only JPG, JPEG, and PNG files are allowed.");
                    }
                }
                $pdo->commit();
                $success_message = "Profile updated successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = "Error updating profile: " . $e->getMessage();
            }
        }
    }
    // Password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } else {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                if ($user && password_verify($current_password, $user['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Current password is incorrect.";
                }
            } catch (PDOException $e) {
                $error_message = "Error changing password: " . $e->getMessage();
            }
        }
    }
}

// Handle attendance actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'check_in':
                $date = date('Y-m-d');
                $time = date('H:i:s');
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $device_info = $_SERVER['HTTP_USER_AGENT'];
                
                try {
                    // Check if attendance already marked for today
                    $stmt = $pdo->prepare("
                        SELECT * FROM attendance 
                        WHERE emp_id = ? AND attendance_date = ?
                    ");
                    $stmt->execute([$manager['emp_id'], $date]);
                    $existing_attendance = $stmt->fetch();
                    
                    if ($existing_attendance) {
                        $error = "You have already checked in today!";
                    } else {
                        // Get attendance policy
                        $stmt = $pdo->query("SELECT * FROM attendance_policy ORDER BY created_at DESC LIMIT 1");
                        $policy = $stmt->fetch();
                        
                        // Calculate status based on time
                        $status = 'present';
                        if (strtotime($time) > strtotime($policy['grace_period'])) {
                            $status = 'late';
                        }
                        
                        // Insert new attendance
                        $stmt = $pdo->prepare("
                            INSERT INTO attendance (emp_id, attendance_date, time_in, status, ip_address, device_info)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $manager['emp_id'], 
                            $date, 
                            $time, 
                            $status,
                            $ip_address,
                            $device_info
                        ]);
                        
                        // Create notification
                        $stmt = $pdo->prepare("
                            INSERT INTO attendance_notifications (emp_id, notification_type, notification_date)
                            VALUES (?, 'check_in', ?)
                        ");
                        $stmt->execute([$manager['emp_id'], $date]);
                        
                        $success = "Check-in successful!";
                    }
                } catch (PDOException $e) {
                    $error = "Error checking in: " . $e->getMessage();
                }
                break;

            case 'check_out':
                $date = date('Y-m-d');
                $time = date('H:i:s');
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $device_info = $_SERVER['HTTP_USER_AGENT'];
                
                try {
                    // Check if attendance exists for today
                    $stmt = $pdo->prepare("
                        SELECT * FROM attendance 
                        WHERE emp_id = ? AND attendance_date = ?
                    ");
                    $stmt->execute([$manager['emp_id'], $date]);
                    $existing_attendance = $stmt->fetch();
                    
                    if ($existing_attendance) {
                        // Calculate total hours worked
                        $time_in = strtotime($existing_attendance['time_in']);
                        $time_out = strtotime($time);
                        $total_hours = ($time_out - $time_in) / 3600;
                        
                        // Get attendance policy
                        $stmt = $pdo->query("SELECT * FROM attendance_policy ORDER BY created_at DESC LIMIT 1");
                        $policy = $stmt->fetch();
                        
                        // Update status based on total hours
                        $status = $existing_attendance['status'];
                        if ($total_hours < $policy['min_hours_half_day']) {
                            $status = 'half-day';
                        }
                        
                        // Update existing attendance with time out
                        $stmt = $pdo->prepare("
                            UPDATE attendance 
                            SET time_out = ?, total_hours = ?, auto_status = ?, ip_address = ?, device_info = ?
                            WHERE emp_id = ? AND attendance_date = ?
                        ");
                        $stmt->execute([
                            $time,
                            $total_hours,
                            $status,
                            $ip_address,
                            $device_info,
                            $manager['emp_id'],
                            $date
                        ]);
                        
                        // Create notification
                        $stmt = $pdo->prepare("
                            INSERT INTO attendance_notifications (emp_id, notification_type, notification_date)
                            VALUES (?, 'check_out', ?)
                        ");
                        $stmt->execute([$manager['emp_id'], $date]);
                        
                        $success = "Check-out successful!";
                    } else {
                        $error = "You need to check in first!";
                    }
                } catch (PDOException $e) {
                    $error = "Error checking out: " . $e->getMessage();
                }
                break;

            case 'update_status':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE attendance 
                        SET status = ?, remarks = ?
                        WHERE attendance_id = ?
                    ");
                    $stmt->execute([
                        $_POST['status'],
                        $_POST['remarks'],
                        $_POST['attendance_id']
                    ]);
                    $success = "Attendance status updated successfully!";
                } catch (PDOException $e) {
                    $error = "Error updating attendance: " . $e->getMessage();
                }
                break;

            case 'add':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (
                            emp_id, 
                            attendance_date, 
                            time_in, 
                            time_out, 
                            status, 
                            total_hours,
                            auto_status,
                            ip_address,
                            device_info
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    // Calculate total hours if both time_in and time_out are provided
                    $total_hours = null;
                    if ($_POST['time_in'] && $_POST['time_out']) {
                        $time_in = strtotime($_POST['time_in']);
                        $time_out = strtotime($_POST['time_out']);
                        $total_hours = ($time_out - $time_in) / 3600;
                    }
                    
                    // Get attendance policy
                    $stmt_policy = $pdo->query("SELECT * FROM attendance_policy ORDER BY created_at DESC LIMIT 1");
                    $policy = $stmt_policy->fetch();
                    
                    // Calculate auto status based on total hours
                    $auto_status = $_POST['status'];
                    if ($total_hours !== null && $total_hours < $policy['min_hours_half_day']) {
                        $auto_status = 'half-day';
                    }
                    
                    $stmt->execute([
                        $_POST['emp_id'],
                        $_POST['attendance_date'],
                        $_POST['time_in'],
                        $_POST['time_out'],
                        $_POST['status'],
                        $total_hours,
                        $auto_status,
                        $_SERVER['REMOTE_ADDR'],
                        $_SERVER['HTTP_USER_AGENT']
                    ]);
                    
                    // Create notification
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance_notifications (emp_id, notification_type, notification_date)
                        VALUES (?, 'manual_entry', ?)
                    ");
                    $stmt->execute([$_POST['emp_id'], $_POST['attendance_date']]);
                    
                    $success = "Attendance record added successfully!";
                } catch (PDOException $e) {
                    $error = "Error adding attendance: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get date filters
$current_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Get manager's today's attendance
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT a.*, e.first_name, e.last_name, e.position, d.dept_name
    FROM attendance a
    JOIN employees e ON a.emp_id = e.emp_id
    JOIN departments d ON e.dept_id = d.dept_id
    WHERE a.emp_id = ? AND a.attendance_date = ?
");
$stmt->execute([$manager['emp_id'], $today]);
$today_attendance = $stmt->fetch();

// Get attendance statistics for the department
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT e.emp_id) as total_employees,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN a.status = 'half-day' THEN 1 ELSE 0 END) as halfday_count,
        AVG(a.total_hours) as avg_hours_worked
    FROM employees e
    LEFT JOIN attendance a ON e.emp_id = a.emp_id AND a.attendance_date = ?
    WHERE e.dept_id = ?
");
$stmt->execute([$today, $manager['dept_id']]);
$attendance_stats = $stmt->fetch();

// Get today's attendance records for all department employees
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        e.first_name,
        e.last_name,
        e.position,
        d.dept_name
    FROM employees e
    LEFT JOIN attendance a ON e.emp_id = a.emp_id AND a.attendance_date = ?
    JOIN departments d ON e.dept_id = d.dept_id
    WHERE e.dept_id = ?
    ORDER BY e.first_name ASC
");
$stmt->execute([$today, $manager['dept_id']]);
$today_attendance_records = $stmt->fetchAll();

// Get monthly attendance summary
$current_month = date('Y-m');
$stmt = $pdo->prepare("
    SELECT 
        e.emp_id,
        e.first_name,
        e.last_name,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN DATE(a.attendance_date) END) as present_days,
        COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN DATE(a.attendance_date) END) as absent_days,
        COUNT(DISTINCT CASE WHEN a.status = 'late' THEN DATE(a.attendance_date) END) as late_days,
        COUNT(DISTINCT CASE WHEN a.status = 'half-day' THEN DATE(a.attendance_date) END) as halfday_days,
        AVG(a.total_hours) as avg_hours_worked
    FROM employees e
    LEFT JOIN attendance a ON e.emp_id = a.emp_id 
        AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
    WHERE e.dept_id = ?
    GROUP BY e.emp_id, e.first_name, e.last_name
    ORDER BY e.first_name
");
$stmt->execute([$current_month, $manager['dept_id']]);
$monthly_summary = $stmt->fetchAll();

// Initialize variables
$department_employees = [];
$attendance_stats = [
    'total_employees' => 0,
    'present_count' => 0,
    'absent_count' => 0,
    'late_count' => 0,
    'halfday_count' => 0
];
$today_attendance_records = [];
$monthly_summary = [];

// Fetch department employees
try {
    $stmt = $pdo->prepare("
        SELECT e.emp_id, e.first_name, e.last_name, e.position, u.email, e.phone
        FROM employees e
        JOIN users u ON e.user_id = u.user_id
        WHERE e.dept_id = ?
        ORDER BY e.first_name
    ");
    $stmt->execute([$manager['dept_id']]);
    $department_employees = $stmt->fetchAll();

    // Get attendance statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_employees,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN a.status = 'half-day' THEN 1 ELSE 0 END) as halfday_count
        FROM employees e
        LEFT JOIN attendance a ON e.emp_id = a.emp_id AND DATE(a.attendance_date) = ?
        WHERE e.dept_id = ?
    ");
    $stmt->execute([$current_date, $manager['dept_id']]);
    $attendance_stats = $stmt->fetch();

    // Get today's attendance records
    $stmt = $pdo->prepare("
        SELECT a.*, e.first_name, e.last_name, e.position
        FROM attendance a
        JOIN employees e ON a.emp_id = e.emp_id
        WHERE e.dept_id = ? AND DATE(a.attendance_date) = ?
        ORDER BY a.time_in DESC
    ");
    $stmt->execute([$manager['dept_id'], $current_date]);
    $today_attendance_records = $stmt->fetchAll();

    // Get monthly attendance summary
    $stmt = $pdo->prepare("
        SELECT 
            e.emp_id,
            e.first_name,
            e.last_name,
            COUNT(DISTINCT CASE WHEN a.status = 'present' THEN DATE(a.attendance_date) END) as present_days,
            COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN DATE(a.attendance_date) END) as absent_days,
            COUNT(DISTINCT CASE WHEN a.status = 'late' THEN DATE(a.attendance_date) END) as late_days,
            COUNT(DISTINCT CASE WHEN a.status = 'half-day' THEN DATE(a.attendance_date) END) as halfday_days
        FROM employees e
        LEFT JOIN attendance a ON e.emp_id = a.emp_id 
            AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
        WHERE e.dept_id = ?
        GROUP BY e.emp_id, e.first_name, e.last_name
        ORDER BY e.first_name
    ");
    $stmt->execute([$current_month, $manager['dept_id']]);
    $monthly_summary = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}

// Get attendance notifications
$stmt = $pdo->prepare("
    SELECT * FROM attendance_notifications 
    WHERE emp_id = ? AND is_read = 0
    ORDER BY created_at DESC
");
$stmt->execute([$manager['emp_id']]);
$notifications = $stmt->fetchAll();

// Get attendance policy
$stmt = $pdo->query("SELECT * FROM attendance_policy ORDER BY created_at DESC LIMIT 1");
$attendance_policy = $stmt->fetch();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
        }

        .main-content {
            background-color: #f8f9fc;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            color: white;
            border-radius: 0 0 1rem 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .card-stat {
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
            color: white;
            min-height: 100%;
        }
        .card-stat.present {
            background: linear-gradient(45deg, #1cc88a, #4cd4a3);
        }
        .card-stat.absent {
            background: linear-gradient(45deg, #e74a3b, #f87171);
        }
        .card-stat.late {
            background: linear-gradient(45deg, #f6c23e, #f8d06b);
        }
        .card-stat.halfday {
            background: linear-gradient(45deg, #36b9cc, #5ccfe6);
        }
        .card-stat .stat-icon {
            width: 36px;
            height: 36px;
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
        }
        .card-stat .card-title {
            color: rgba(255,255,255,0.8);
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        .card-stat h2 {
            font-size: 1.875rem;
            font-weight: 700;
            color: white;
            margin: 0.5rem 0;
        }
        .card-stat small {
            color: rgba(255,255,255,0.8);
            font-size: 0.75rem;
        }

        .attendance-card {
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }

        .attendance-card .card-header {
            background-color: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 1.25rem 1.5rem;
        }

        .attendance-card .card-header h6 {
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .attendance-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .attendance-time {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .attendance-date {
            color: var(--secondary-color);
            font-size: 0.9rem;
        }

        .progress {
            height: 0.5rem;
            border-radius: 0.25rem;
            background-color: #e3e6f0;
        }

        .progress-bar {
            border-radius: 0.25rem;
        }

        .table {
            margin-bottom: 0;
        }
        table th {
    background: none !important;
}
        .table th, .table thead th {
            /* background-color: #4e73df !important; */
            color: black !important;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem;
            border-bottom: 2px solid #e3e6f0;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: #f8f9fc;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-radius: 0.35rem;
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            border: none;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #13855c 100%);
            border: none;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #be2617 100%);
            border: none;
        }

        .modal-content {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
        }

        .modal-header {
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .modal-header .btn-close {
            /* Remove custom filter for close button */
        }

        /* Check In/Out Card Styles */
        .check-in-out-card {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            height: 100%;
            overflow: hidden;
        }

        .check-in-out-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .card-content {
            padding: 2rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 300px;
        }

        .icon-wrapper {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .time {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 1rem 0;
            font-family: 'Arial', sans-serif;
        }

        .date {
            font-size: 1.1rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .action-button {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 10px;
            width: 100%;
            max-width: 250px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .status-badge {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-align: center;
            white-space: nowrap;
        }

        .checked-in {
            background-color: #1cc88a;
            color: #ffffff;
        }

        .checked-out {
            background-color: #4e73df;
            color: #ffffff;
        }

        .not-checked {
            background-color: #e74a3b;
            color: #ffffff;
        }

        @media (max-width: 768px) {
            .card-content {
                padding: 1.5rem;
                min-height: auto;
            }

            .time {
                font-size: 2rem;
            }

            .action-button {
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
            }

            .icon-wrapper {
                width: 60px;
                height: 60px;
            }

            .icon-wrapper i {
                font-size: 1.5rem;
            }
          
        }
        @media (max-width: 1024px) {
    .card-content {
                padding: 1.5rem;
                min-height: auto;
            }

            .time {
                font-size: 2rem;
            }

            .action-button {
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
            }

            .icon-wrapper {
                width: 60px;
                height: 60px;
            }

            .icon-wrapper i {
                font-size: 1.5rem;
            }
}

        /* Table Styles */
        .table {
            width: 100%;
            margin-bottom: 0;
            vertical-align: middle;
            font-size: 0.875rem;
        }

        .table th {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: #6f42c1;
            color: white;
            padding: 1rem;
            border-bottom: 2px solid #e3e6f0;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: #f8f9fc;
        }

        /* Avatar Circle */
        .avatar-circle {
            width: 40px;
            height: 40px;
            background-color: #4e73df;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 50rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }

        .status-present {
            background-color: #1cc88a;
            color: white;
        }

        .status-late {
            background-color: #f6c23e;
            color: white;
        }

        .status-absent {
            background-color: #e74a3b;
            color: white;
        }

        .status-half-day {
            background-color: #36b9cc;
            color: white;
        }

        /* Action Buttons */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 0.25rem;
        }

        /* Responsive Table */
        .table-responsive {
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .dashboard-header {
    background: white;
    padding: 1.5rem;
    border-radius: 0.75rem;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    margin-bottom: 1.5rem;
}

        @media (max-width: 768px) {
            .table {
                font-size: 0.8rem;
            }
            
            .table td, .table th {
                padding: 0.75rem;
            }
            
            .avatar-circle {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
            
            .status-badge {
                padding: 0.3rem 0.6rem;
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
        <!-- Topbar -->
        <?php include 'includes/topbar.php'; ?>
<!-- Main Content -->
    <div class="main-content" style="margin-left:260px; padding-top:70px; min-height:100vh; background:#f8f9fc;">
        <!-- Page Header -->
        <div class="page-header">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h3 mb-0 text-black">Attendance Management</h1>
                    <!-- <div>
                        <button class="btn btn-light" onclick="downloadReport()">
                            <i class="fas fa-file-export me-2"></i>Generate Report
                        </button>
                    </div> -->
                </div>
            </div>
        </div>

        <!-- Check In/Out Section -->
        <div class="container-fluid">
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="check-in-out-card">
                        <div class="card-content">
                            <div class="icon-wrapper">
                                <i class="fas fa-sign-in-alt text-primary fa-3x"></i>
                            </div>
                            <div class="time">
                                <?php echo $today_attendance ? date('h:i A', strtotime($today_attendance['time_in'])) : '--:--'; ?>
                            </div>
                            <div class="date mb-4">Time In</div>
                            <?php if (!$today_attendance): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="check_in">
                                    <button type="submit" class="btn btn-primary btn-lg action-button">
                                        <i class="fas fa-clock me-2"></i>Check In Now
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="status-badge checked-in">
                                    <i class="fas fa-check-circle me-2"></i>Checked In
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="check-in-out-card">
                        <div class="card-content">
                            <div class="icon-wrapper">
                                <i class="fas fa-sign-out-alt text-danger fa-3x"></i>
                            </div>
                            <div class="time">
                                <?php echo $today_attendance && $today_attendance['time_out'] ? date('h:i A', strtotime($today_attendance['time_out'])) : '--:--'; ?>
                            </div>
                            <div class="date mb-4">Time Out</div>
                            <?php if ($today_attendance && !$today_attendance['time_out']): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="check_out">
                                    <button type="submit" class="btn btn-danger btn-lg action-button">
                                        <i class="fas fa-clock me-2"></i>Check Out Now
                                    </button>
                                </form>
                            <?php elseif ($today_attendance && $today_attendance['time_out']): ?>
                                <div class="status-badge checked-out">
                                    <i class="fas fa-check-circle me-2"></i>Checked Out
                                </div>
                            <?php else: ?>
                                <div class="status-badge not-checked">
                                    <i class="fas fa-clock me-2"></i>Not Checked In
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card card-stat present h-100">
                        <div class="stat-icon">
                            <i class="fa-solid fa-user-check fa-2x"></i>
                        </div>
                        <h6 class="card-title">Present</h6>
                        <h2><?php echo $attendance_stats['present_count']; ?></h2>
                        <small>On Time Attendance</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-stat absent h-100">
                        <div class="stat-icon">
                            <i class="fa-solid fa-user-xmark fa-2x"></i>
                        </div>
                        <h6 class="card-title">Absent</h6>
                        <h2><?php echo $attendance_stats['absent_count']; ?></h2>
                        <small>No Attendance Record</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-stat late h-100">
                        <div class="stat-icon">
                            <i class="fa-solid fa-clock fa-2x"></i>
                        </div>
                        <h6 class="card-title">Late</h6>
                        <h2><?php echo $attendance_stats['late_count']; ?></h2>
                        <small>Delayed Check-ins</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-stat halfday h-100">
                        <div class="stat-icon">
                            <i class="fa-solid fa-hourglass-half fa-2x"></i>
                        </div>
                        <h6 class="card-title">Half Day</h6>
                        <h2><?php echo $attendance_stats['halfday_count']; ?></h2>
                        <small>Partial Attendance</small>
                    </div>
                </div>
            </div>

            <!-- Attendance Policy Card -->
            <!-- <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="card attendance-card">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Attendance Policy</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-clock text-primary fa-2x"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0">Minimum Hours (Present)</h6>
                                            <p class="text-muted mb-0"><?php echo $attendance_policy['min_hours_present']; ?> hours</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-hourglass-half text-warning fa-2x"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0">Minimum Hours (Half Day)</h6>
                                            <p class="text-muted mb-0"><?php echo $attendance_policy['min_hours_half_day']; ?> hours</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-exclamation-circle text-danger fa-2x"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0">Grace Period</h6>
                                            <p class="text-muted mb-0"><?php echo date('h:i A', strtotime($attendance_policy['grace_period'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-calendar-alt text-info fa-2x"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0">Last Updated</h6>
                                            <p class="text-muted mb-0"><?php echo date('M d, Y', strtotime($attendance_policy['updated_at'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->

            <!-- Department Attendance -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Department Attendance (<?php echo date('F d, Y', strtotime($current_date)); ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="todayAttendanceTable" class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Total Hours</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($department_employees as $employee): 
                                    // Skip if this is the manager's own record
                                    if ($employee['emp_id'] == $manager['emp_id']) continue;
                                    
                                    $attendance = null;
                                    foreach ($today_attendance_records as $record) {
                                        if ($record['emp_id'] == $employee['emp_id']) {
                                            $attendance = $record;
                                            break;
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php
// Fetch profile image for this employee (if not already included)
$profileImg = null;
try {
    if (isset($employee['profile_image'])) {
        $profileImg = $employee['profile_image'];
    } else {
        $stmtImg = $pdo->prepare("SELECT profile_image FROM employees WHERE emp_id = ?");
        $stmtImg->execute([$employee['emp_id']]);
        $imgRow = $stmtImg->fetch();
        $profileImg = $imgRow && !empty($imgRow['profile_image']) ? $imgRow['profile_image'] : null;
    }
} catch (Exception $e) { $profileImg = null; }
?>
<div class="team-avatar" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#4e73df;color:white;font-weight:600;font-size:1.1rem;overflow:hidden;">
    <?php if ($profileImg): ?>
        <img src="../../uploads/profile_photos/<?php echo htmlspecialchars($profileImg); ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />
    <?php else: ?>
        <?php $initials = strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1));
        echo $initials; ?>
    <?php endif; ?>
</div>
                                            <div class="ms-3">
                                                <div class="fw-bold"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($employee['position']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($attendance): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-sign-in-alt text-primary me-2"></i>
                                                <?php echo date('h:i A', strtotime($attendance['time_in'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($attendance && $attendance['time_out']): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-sign-out-alt text-danger me-2"></i>
                                                <?php echo date('h:i A', strtotime($attendance['time_out'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($attendance && $attendance['total_hours']): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-clock text-info me-2"></i>
                                                <?php echo number_format($attendance['total_hours'], 2); ?> hrs
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($attendance): ?>
                                            <span class="status-badge status-<?php echo strtolower($attendance['status']); ?>">
                                                <?php echo ucfirst($attendance['status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-absent">Not Marked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($attendance): ?>
                                            <button class="btn btn-sm btn-primary" onclick='updateAttendance(<?php echo json_encode($attendance); ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-success" onclick='addAttendance(<?php echo json_encode($employee); ?>)'>
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Monthly Summary -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Monthly Summary (<?php echo date('F Y', strtotime($current_month . '-01')); ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="monthlySummaryTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Present Days</th>
                                    <th>Absent Days</th>
                                    <th>Late Days</th>
                                    <th>Half Days</th>
                                    <th>Attendance Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_summary as $summary): 
                                    $total_days = $summary['present_days'] + $summary['absent_days'] + $summary['late_days'] + $summary['halfday_days'];
                                    $attendance_rate = $total_days > 0 ? round(($summary['present_days'] + ($summary['halfday_days'] * 0.5)) / $total_days * 100) : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($summary['first_name'] . ' ' . $summary['last_name']); ?></td>
                                    <td><?php echo $summary['present_days']; ?></td>
                                    <td><?php echo $summary['absent_days']; ?></td>
                                    <td><?php echo $summary['late_days']; ?></td>
                                    <td><?php echo $summary['halfday_days']; ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $attendance_rate >= 90 ? 'success' : ($attendance_rate >= 75 ? 'warning' : 'danger'); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $attendance_rate; ?>%"
                                                 aria-valuenow="<?php echo $attendance_rate; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?php echo $attendance_rate; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Attendance Modal -->
    <div class="modal fade" id="updateAttendanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Employee Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="attendance_id" id="update_attendance_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <input type="text" class="form-control" id="update_employee_name" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="text" class="form-control" id="update_attendance_date" readonly>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time In</label>
                                <input type="text" class="form-control" id="update_time_in" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time Out</label>
                                <input type="text" class="form-control" id="update_time_out" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="update_status" required>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                                <option value="half-day">Half Day</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="update_remarks" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Attendance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Attendance Modal -->
    <div class="modal fade" id="addAttendanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Employee Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="emp_id" id="add_emp_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <input type="text" class="form-control" id="add_employee_name" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="attendance_date" value="<?php echo $current_date; ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time In</label>
                                <input type="time" class="form-control" name="time_in" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time Out</label>
                                <input type="time" class="form-control" name="time_out">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                                <option value="half-day">Half Day</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Attendance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Function to download attendance report
        function downloadReport() {
            // Create form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'generate_attendance_report.php';
            form.target = '_blank'; // Open in new tab
            
            // Add date input
            const dateInput = document.createElement('input');
            dateInput.type = 'hidden';
            dateInput.name = 'date';
            dateInput.value = '<?php echo $current_date; ?>';
            form.appendChild(dateInput);
            
            // Add department ID input
            const deptInput = document.createElement('input');
            deptInput.type = 'hidden';
            deptInput.name = 'dept_id';
            deptInput.value = '<?php echo $manager['dept_id']; ?>';
            form.appendChild(deptInput);
            
            // Submit form
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // Initialize DataTables
        $(document).ready(function() {
            $('#todayAttendanceTable').DataTable();
            $('#monthlySummaryTable').DataTable();
        });

        // Update Attendance
        function updateAttendance(attendance) {
            document.getElementById('update_attendance_id').value = attendance.attendance_id;
            document.getElementById('update_employee_name').value = attendance.first_name + ' ' + attendance.last_name;
            document.getElementById('update_attendance_date').value = new Date(attendance.attendance_date).toLocaleDateString();
            document.getElementById('update_time_in').value = attendance.time_in ? new Date('2000-01-01 ' + attendance.time_in).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '-';
            document.getElementById('update_time_out').value = attendance.time_out ? new Date('2000-01-01 ' + attendance.time_out).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '-';
            document.getElementById('update_status').value = attendance.status;
            document.getElementById('update_remarks').value = attendance.remarks || '';
            
            new bootstrap.Modal(document.getElementById('updateAttendanceModal')).show();
        }

        // Add Attendance
        function addAttendance(employee) {
            document.getElementById('add_emp_id').value = employee.emp_id;
            document.getElementById('add_employee_name').value = employee.first_name + ' ' + employee.last_name;
            
            new bootstrap.Modal(document.getElementById('addAttendanceModal')).show();
        }
    </script>
</body>
</html>