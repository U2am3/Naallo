<?php
session_start();
date_default_timezone_set('Asia/Dubai'); // Set timezone for correct check-in/out time
require_once '../../config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../login/employee.php");
    exit();
}

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
                        $new_filename = 'employee_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
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

// Get employee details
$stmt = $pdo->prepare("
    SELECT e.*, d.dept_name
    FROM employees e
    LEFT JOIN departments d ON e.dept_id = d.dept_id
    WHERE e.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

// Handle attendance marking
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['check_in'])) {
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
            $stmt->execute([$employee['emp_id'], $date]);
            $existing_attendance = $stmt->fetch();
            
            if ($existing_attendance) {
                $error_message = "You have already checked in today!";
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
                    $employee['emp_id'], 
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
                $stmt->execute([$employee['emp_id'], $date]);
                
                $success_message = "Check-in successful!";
            }
        } catch (PDOException $e) {
            $error_message = "Error checking in: " . $e->getMessage();
        }
    } elseif (isset($_POST['check_out'])) {
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
            $stmt->execute([$employee['emp_id'], $date]);
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
                    $employee['emp_id'],
                    $date
                ]);
                
                // Create notification
                $stmt = $pdo->prepare("
                    INSERT INTO attendance_notifications (emp_id, notification_type, notification_date)
                    VALUES (?, 'check_out', ?)
                ");
                $stmt->execute([$employee['emp_id'], $date]);
                
                $success_message = "Check-out successful!";
            } else {
                $error_message = "You need to check in first!";
            }
        } catch (PDOException $e) {
            $error_message = "Error checking out: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_status'])) {
        $date = date('Y-m-d');
        $status = $_POST['status'];
        $notes = trim($_POST['notes']);
        
        try {
            // Update status and notes
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET status = ?, notes = ?
                WHERE emp_id = ? AND attendance_date = ?
            ");
            $stmt->execute([$status, $notes, $employee['emp_id'], $date]);
            $success_message = "Attendance status updated successfully!";
        } catch (PDOException $e) {
            $error_message = "Error updating status: " . $e->getMessage();
        }
    }
}

// Get today's attendance
$today = date('Y-m-d');

// Get recent attendance records
$stmt = $pdo->prepare("
    SELECT * FROM attendance 
    WHERE emp_id = ? 
    ORDER BY attendance_date DESC 
    LIMIT 10
");
$stmt->execute([$employee['emp_id']]);
$attendance_records = $stmt->fetchAll();

// Get today's attendance
$stmt = $pdo->prepare("
    SELECT a.*, e.first_name, e.last_name, e.position, d.dept_name
    FROM attendance a
    JOIN employees e ON a.emp_id = e.emp_id
    JOIN departments d ON e.dept_id = d.dept_id
    WHERE a.emp_id = ? AND a.attendance_date = ?
");
$stmt->execute([$employee['emp_id'], $today]);
$today_attendance = $stmt->fetch();

// Get attendance notifications
$stmt = $pdo->prepare("
    SELECT * FROM attendance_notifications 
    WHERE emp_id = ? AND is_read = 0
    ORDER BY created_at DESC
");
$stmt->execute([$employee['emp_id']]);
$notifications = $stmt->fetchAll();

// Get attendance policy
$stmt = $pdo->query("SELECT * FROM attendance_policy ORDER BY created_at DESC LIMIT 1");
$attendance_policy = $stmt->fetch();

// Get attendance statistics for current month
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
        COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
        COUNT(CASE WHEN status = 'half-day' THEN 1 END) as halfday_count
    FROM attendance 
    WHERE emp_id = ? 
    AND MONTH(attendance_date) = MONTH(CURRENT_DATE)
    AND YEAR(attendance_date) = YEAR(CURRENT_DATE)
");
$stmt->execute([$employee['emp_id']]);
$attendance_stats = $stmt->fetch();

// Get recent attendance records
$stmt = $pdo->prepare("
    SELECT *
    FROM attendance
    WHERE emp_id = ?
    ORDER BY attendance_date DESC
    LIMIT 10
");
$stmt->execute([$employee['emp_id']]);
$recent_attendance = $stmt->fetchAll();

// Get attendance calendar data for the current month
$current_month = date('m');
$current_year = date('Y');
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);

$stmt = $pdo->prepare("
    SELECT attendance_date, status
    FROM attendance
    WHERE emp_id = ?
    AND MONTH(attendance_date) = ?
    AND YEAR(attendance_date) = ?
");
$stmt->execute([$employee['emp_id'], $current_month, $current_year]);
$attendance_dates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Employee Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }

        .main-content {
            width: 100%;
            min-height: 100vh;
            transition: all 0.3s;
        }

        .dashboard-header {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }

        .page-header h1 {
            color: white;
            font-weight: 700;
            margin: 0;
        }

        .attendance-card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: transform 0.2s;
            margin-bottom: 1.5rem;
            background: white;
        }

        .attendance-card:hover {
            transform: translateY(-5px);
        }

        .attendance-card .card-header {
            background-color: white;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
            padding: 1.25rem;
        }

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

        .btn-primary {
            background: #4e73df;
            border-color: #4e73df;
        }

        .btn-primary:hover {
            background: #2e59d9;
            border-color: #2e59d9;
        }

        .btn-danger {
            background: #e74a3b;
            border-color: #e74a3b;
        }

        .btn-danger:hover {
            background: #be2617;
            border-color: #be2617;
        }

        .status-badge {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Present Status */
        .status-present {
            background-color: #1cc88a;
            color: #ffffff;
        }

        /* Late Status */
        .status-late {
            background-color: #f6c23e;
            color: #ffffff;
        }

        /* Absent Status */
        .status-absent {
            background-color: #e74a3b;
            color: #ffffff;
        }

        /* Half-day Status */
        .status-half-day {
            background-color: #36b9cc;
            color: #ffffff;
        }

        /* Not Checked In Status */
        .status-not-checked {
            background-color: #858796;
            color: #ffffff;
        }

        /* Check In Status */
        .checked-in {
            background-color: #1cc88a;
            color: #ffffff;
        }

        /* Check Out Status */
        .checked-out {
            background-color: #4e73df;
            color: #ffffff;
        }

        /* Hover effect for all status badges */
        .status-badge:hover {
            opacity: 0.9;
        }

        .policy-card {
            background: white;
            border-radius: 0.35rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .policy-icon {
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.35rem;
            margin-right: 1rem;
            font-size: 1.5rem;
        }

        .policy-info h6 {
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark-color);
        }

        .policy-info p {
            color: var(--secondary-color);
            margin-bottom: 0;
            font-size: 0.9rem;
        }

        .table-responsive {
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .table th {
            background-color: #f8f9fc;
            border-bottom: 2px solid #e3e6f0;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: var(--dark-color);
        }

        .table td {
            vertical-align: middle;
            color: var(--dark-color);
        }

        .alert {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
            }
            
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
            
            .policy-card {
                padding: 1rem;
            }
            
            .policy-icon {
                width: 2.5rem;
                height: 2.5rem;
                font-size: 1.25rem;
            }
            
            .status-badge {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }

        /* Table Styles */
        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8f9fc;
            border-bottom: 2px solid #e3e6f0;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #4e73df;
            vertical-align: middle;
        }

        .table td {
            vertical-align: middle;
            padding: 1rem;
        }

        /* Status Badge Styles */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-align: center;
            white-space: nowrap;
        }

        /* Present Status */
        .status-present {
            background-color: #1cc88a;
            color: #ffffff;
        }

        /* Late Status */
        .status-late {
            background-color: #f6c23e;
            color: #ffffff;
        }

        /* Absent Status */
        .status-absent {
            background-color: #e74a3b;
            color: #ffffff;
        }

        /* Half-day Status */
        .status-half-day {
            background-color: #36b9cc;
            color: #ffffff;
        }

        /* Not Checked In Status */
        .status-not-checked {
            background-color: #858796;
            color: #ffffff;
        }

        /* Hover effect for all status badges */
        .status-badge:hover {
            opacity: 0.9;
        }

        /* Table Responsive Styles */
        .table-responsive {
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        @media (max-width: 768px) {
            .table td, .table th {
                padding: 0.75rem;
            }
            
            .status-badge {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <?php include 'includes/topbar.php'; ?>

            <!-- Page Content -->
            <div class="container-fluid">
                <!-- Welcome Message -->
                <div class="dashboard-header mb-4 d-flex justify-content-between align-items-center">
  <div>
    <h1 class="mb-1">Attendance</h1>
    <div class="text-muted" style="font-size: 1.1rem;">View and manage your daily attendance records</div>
  </div>
</div>
                 

                <!-- Alert Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Check In/Out Section -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-4">
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
                                        <button type="submit" name="check_in" class="btn btn-primary btn-lg action-button">
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
                    <div class="col-lg-6 mb-4">
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
                                        <button type="submit" name="check_out" class="btn btn-danger btn-lg action-button">
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

                <!-- Recent Attendance Records -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="attendance-card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Attendance Records</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th style="color: #6c757d;">Date</th>
                                                <th style="color: #28a745;">Time In</th>
                                                <th style="color: #dc3545;">Time Out</th>
                                                <th style="color: #007bff;">Total Hours</th>
                                                <th style="color: #6c757d;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance_records as $record): ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <?php echo date('M d, Y', strtotime($record['attendance_date'])); ?>
                                                            <div class="text-muted small"><?php echo date('l', strtotime($record['attendance_date'])); ?></div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-sign-in-alt text-success me-2"></i>
                                                            <?php echo $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-'; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-sign-out-alt text-danger me-2"></i>
                                                            <?php echo $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-'; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-clock text-primary me-2"></i>
                                                            <?php echo $record['total_hours'] ? number_format($record['total_hours'], 2) . ' hrs' : '-'; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $statusClass = '';
                                                        switch(strtolower($record['status'])) {
                                                            case 'present':
                                                                $statusClass = 'text-success';
                                                                $icon = 'fa-check-circle';
                                                                break;
                                                            case 'late':
                                                                $statusClass = 'text-warning';
                                                                $icon = 'fa-clock';
                                                                break;
                                                            case 'absent':
                                                                $statusClass = 'text-danger';
                                                                $icon = 'fa-times-circle';
                                                                break;
                                                            case 'half-day':
                                                                $statusClass = 'text-info';
                                                                $icon = 'fa-adjust';
                                                                break;
                                                            default:
                                                                $statusClass = 'text-secondary';
                                                                $icon = 'fa-question-circle';
                                                        }
                                                        ?>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas <?php echo $icon; ?> <?php echo $statusClass; ?> me-2"></i>
                                                            <span class="<?php echo $statusClass; ?>"><?php echo ucfirst($record['status']); ?></span>
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
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script>
        // Update current time every second
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            document.getElementById('current-time').textContent = timeString;
        }

        setInterval(updateTime, 1000);
        updateTime();

        // Sidebar Toggle
        document.getElementById('toggle-sidebar').addEventListener('click', function() {
            document.querySelector('.wrapper').classList.toggle('sidebar-collapsed');
        });
    </script>
    <?php if (!empty($success_message)): ?>
    <script>
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: <?php echo json_encode($success_message); ?>,
        confirmButtonColor: '#4e73df'
    });
    </script>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
    <script>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: <?php echo json_encode($error_message); ?>,
        confirmButtonColor: '#e74a3b'
    });
    </script>
    <?php endif; ?>
</body>
</html> 