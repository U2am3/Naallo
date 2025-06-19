<?php
session_start();
require_once '../../config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login/admin.php");
    exit();
}

// Initialize variables
$success = $error = '';
$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$department = isset($_GET['department']) ? $_GET['department'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Fetch departments for filter
$stmt = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name");
$departments = $stmt->fetchAll();

// Handle admin profile update and password change from topbar modals
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Profile update
    if (isset($_POST['update_admin_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        if (empty($username) || empty($email)) {
            $error_message = "Username and email are required.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ? AND role = 'admin'");
                $stmt->execute([$username, $email, $_SESSION['user_id']]);
                $success_message = "Profile updated successfully!";
                $_SESSION['username'] = $username;
            } catch (PDOException $e) {
                $error_message = "Error updating profile: " . $e->getMessage();
            }
        }
    }
    // Password change
    if (isset($_POST['change_admin_password'])) {
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
                $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ? AND role = 'admin'");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                if ($user && password_verify($current_password, $user['password'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ? AND role = 'admin'");
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
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_payroll':
                try {
                    // Check if employee is active
                    $stmt = $pdo->prepare("SELECT u.status FROM employees e JOIN users u ON e.user_id = u.user_id WHERE e.emp_id = ?");
                    $stmt->execute([$_POST['employee_id']]);
                    $userStatus = $stmt->fetchColumn();
                    if ($userStatus !== 'active') {
                        throw new Exception('Cannot create payroll for an inactive employee.');
                    }
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
                    $success = "Payroll created successfully!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Error creating payroll: " . $e->getMessage();
                }
                break;
                
            case 'update_status':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE payroll SET status = ? WHERE payroll_id = ?
                    ");
                    $stmt->execute([$_POST['status'], $_POST['payroll_id']]);
                    $success = "Payroll status updated successfully!";
                } catch (PDOException $e) {
                    $error = "Error updating payroll status: " . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch payroll records with filters
try {
    $query = "
        SELECT 
            p.payroll_id,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            d.dept_name as department,
            pp.start_date,
            pp.end_date,
            p.basic_salary,
            p.gross_salary,
            p.net_salary,
            p.status,
            pa.amount as bonus_amount,
            ap.days_present,
            ap.days_late,
            ap.days_absent
        FROM payroll p
        JOIN employees e ON p.employee_id = e.emp_id
        JOIN departments d ON e.dept_id = d.dept_id
        JOIN payroll_periods pp ON p.period_id = pp.period_id
        LEFT JOIN payroll_adjustments pa ON p.payroll_id = pa.payroll_id AND pa.adjustment_type = 'bonus'
        LEFT JOIN attendance_performance ap ON e.emp_id = ap.emp_id 
            AND MONTH(pp.start_date) = ap.month 
            AND YEAR(pp.start_date) = ap.year
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($current_month) {
        $query .= " AND DATE_FORMAT(pp.start_date, '%Y-%m') = ?";
        $params[] = $current_month;
    }
    
    if ($department) {
        $query .= " AND e.dept_id = ?";
        $params[] = $department;
    }
    
    if ($status) {
        $query .= " AND p.status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY pp.start_date DESC, e.first_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payrolls = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error fetching payroll data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Management - EMS Admin</title>
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
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            padding: 2rem 0;
            margin-bottom: 2rem;
            color: white;
            border-radius: 0 0 1rem 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .card-stat {
            transition: all 0.3s ease;
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            overflow: hidden;
        }

        .card-stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
        }

        .card-stat .card-body {
            padding: 1.5rem;
        }

        .card-stat .card-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }

        .card-stat h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 1.25rem 1.5rem;
        }

        .card-header h6 {
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .table-responsive {
            max-height: 600px;
            border-radius: 0.5rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: #f8f9fc;
            border-bottom: 2px solid #e3e6f0;
            color: var(--dark-color);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.1em;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: #f8f9fc;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .action-buttons .btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 0.35rem;
            transition: all 0.2s ease;
        }

        .action-buttons .btn:hover {
            transform: translateY(-2px);
        }

        .form-control, .form-select {
            border-radius: 0.35rem;
            border: 1px solid #d1d3e2;
            padding: 0.75rem 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
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

        .btn-info {
            background: linear-gradient(135deg, var(--info-color) 0%, #258391 100%);
            border: none;
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #dda20a 100%);
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
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .alert {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
            border-radius: 0.35rem;
            border: none;
            background: white;
            color: var(--dark-color) !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white !important;
        }

        .dataTables_wrapper .dataTables_filter input {
            border-radius: 0.35rem;
            border: 1px solid #d1d3e2;
            padding: 0.5rem 1rem;
        }

        .dataTables_wrapper .dataTables_length select {
            border-radius: 0.35rem;
            border: 1px solid #d1d3e2;
            padding: 0.5rem 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .card-stat:hover .stat-icon {
            transform: scale(1.1);
        }

        .card-stat .card-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }

        .card-stat h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .card-stat small {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        .bg-opacity-10 {
            background-color: rgba(var(--bs-primary-rgb), 0.1);
        }

        .text-primary {
            color: var(--primary-color) !important;
        }

        .text-success {
            color: var(--success-color) !important;
        }

        .text-warning {
            color: var(--warning-color) !important;
        }

        .text-danger {
            color: var(--danger-color) !important;
        }

        .bg-primary {
            background-color: var(--primary-color) !important;
        }

        .bg-success {
            background-color: var(--success-color) !important;
        }

        .bg-warning {
            background-color: var(--warning-color) !important;
        }

        .bg-danger {
            background-color: var(--danger-color) !important;
        }

        /* Stat Card Styles from index.php */
        .stat-card {
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
            height: 100%;
            color: white;
            background: linear-gradient(45deg, #4e73df, #6f8de3);
        }
        .stat-card.team {
            background: linear-gradient(45deg, var(--primary-color), #6f8de3);
        }
        .stat-card.departments {
            background: linear-gradient(45deg, var(--success-color), #4cd4a3);
        }
        .stat-card.leaves {
            background: linear-gradient(45deg, var(--warning-color), #f8d06b);
        }
        .stat-card.projects {
            background: linear-gradient(45deg, var(--info-color), #5ccfe6);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        .stat-value {
            font-size: 1.875rem;
            font-weight: 700;
            color: white;
            margin: 0.5rem 0;
        }
        .stat-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .stat-card p.text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.15);
        }
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Topbar -->
        <?php include 'includes/topbar.php'; ?>

        <!-- Page Content -->
        <div class="container-fluid">
            <!-- Modern Page Header (matches other admin pages) -->
            <div class="dashboard-header mb-4" style="border-radius: 16px; box-shadow: 0 2px 16px rgba(30,34,90,0.07); padding: 2rem 2rem 1.5rem 2rem; background: #fff;">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div class="pe-3">
                        <h1 class="fw-bold mb-1" style="font-size:2rem; color:#222;">Payroll Management</h1>
                        <div class="text-muted" style="font-size:1.1rem;">Manage and process employee payroll</div>
                    </div>
                    <button class="btn btn-primary fw-bold px-4 py-2 d-flex align-items-center" style="font-size:1rem; border-radius:8px; box-shadow:0 2px 8px rgba(78,115,223,0.08);" data-bs-toggle="modal" data-bs-target="#payrollModal">
                        <i class="fas fa-plus me-2"></i> CREATE PAYROLL
                    </button>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card team">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h6 class="stat-label">Total Payroll</h6>
                        <h3 class="stat-value">$<?php echo number_format(array_sum(array_column($payrolls, 'net_salary'))); ?></h3>
                        <p class="text-muted mb-0">This Month</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card departments">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h6 class="stat-label">Paid</h6>
                        <h3 class="stat-value"><?php echo count(array_filter($payrolls, function($p) { return $p['status'] === 'paid'; })); ?></h3>
                        <p class="text-muted mb-0">Completed Payrolls</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card leaves">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h6 class="stat-label">Pending</h6>
                        <h3 class="stat-value"><?php echo count(array_filter($payrolls, function($p) { return $p['status'] === 'draft'; })); ?></h3>
                        <p class="text-muted mb-0">Draft Payrolls</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card projects">
                        <div class="stat-icon">
                            <i class="fas fa-gift"></i>
                        </div>
                        <h6 class="stat-label">Total Bonus</h6>
                        <h3 class="stat-value">$<?php echo number_format(array_sum(array_column($payrolls, 'bonus_amount'))); ?></h3>
                        <p class="text-muted mb-0">This Month</p>
                    </div>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold">Filter Records</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Month</label>
                            <input type="month" class="form-control" name="month" value="<?php echo $current_month; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['dept_id']; ?>" 
                                            <?php echo $department == $dept['dept_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payroll Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">Payroll Records</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="payrollTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Period</th>
                                    <th>Basic Salary</th>
                                    <th>Bonus</th>
                                    <th>Gross Salary</th>
                                    <th>Net Salary</th>
                                    <th>Attendance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payrolls as $payroll): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payroll['employee_name']); ?></td>
                                        <td><?php echo htmlspecialchars($payroll['department']); ?></td>
                                        <td><?php echo date('M Y', strtotime($payroll['start_date'])); ?></td>
                                        <td>$<?php echo number_format($payroll['basic_salary'], 2); ?></td>
                                        <td>$<?php echo number_format($payroll['bonus_amount'] ?? 0, 2); ?></td>
                                        <td>$<?php echo number_format($payroll['gross_salary'], 2); ?></td>
                                        <td>$<?php echo number_format($payroll['net_salary'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $payroll['days_present']; ?> Present</span>
                                            <span class="badge bg-warning"><?php echo $payroll['days_late']; ?> Late</span>
                                            <span class="badge bg-danger"><?php echo $payroll['days_absent']; ?> Absent</span>
                                        </td>
                                        <td>
                                            <span class="status-badge bg-<?php 
                                                echo $payroll['status'] === 'paid' ? 'success' : 
                                                    ($payroll['status'] === 'approved' ? 'info' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($payroll['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-info" onclick="viewPayroll(<?php echo $payroll['payroll_id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($payroll['status'] !== 'paid'): ?>
                                                    <button class="btn btn-sm btn-primary" onclick="editPayroll(<?php echo $payroll['payroll_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="updatePayrollStatus(<?php echo $payroll['payroll_id']; ?>, 'paid')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
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

    <!-- Create Payroll Modal -->
    <div class="modal fade" id="payrollModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="payrollForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_payroll">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <select class="form-select" id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['dept_id']; ?>">
                                            <?php echo htmlspecialchars($dept['dept_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Employee</label>
                                <select class="form-select" id="employee_id" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Pay Period</label>
                                <input type="month" class="form-control" name="pay_period" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Basic Salary</label>
                                <input type="number" class="form-control" id="basic_salary" name="basic_salary" readonly>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Bonus will be automatically calculated based on attendance:
                            <ul class="mb-0 mt-2">
                                <li>22+ days present = 10% bonus</li>
                                <li>15-21 days present = 5% bonus</li>
                                <li>Less than 15 days = No bonus</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Payroll</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Payroll Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="ajax/export_payroll.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Report Type</label>
                            <select class="form-select" name="report_type" required>
                                <option value="monthly">Monthly Report</option>
                                <option value="department">Department Report</option>
                                <option value="employee">Employee Report</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Month</label>
                            <input type="month" class="form-control" name="month" value="<?php echo $current_month; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department" id="exportDepartment">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['dept_id']; ?>">
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Format</label>
                            <select class="form-select" name="format" required>
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Export</button>
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
        $(document).ready(function() {
            // Initialize DataTable with proper configuration
            $('#payrollTable').DataTable({
                order: [[2, 'desc']], // Sort by period by default
                pageLength: 10,
                language: {
                    search: "Search records:",
                    lengthMenu: "Show _MENU_ records per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ records",
                    infoEmpty: "No records available",
                    infoFiltered: "(filtered from _MAX_ total records)"
                },
                dom: '<"top"lf>rt<"bottom"ip>',
                responsive: true
            });

            // Function to load employees based on department
            function loadEmployees() {
                const deptId = $('#department').val();
                const employeeSelect = $('#employee_id');
                
                // Clear current options
                employeeSelect.html('<option value="">Select Employee</option>');
                
                if (deptId) {
                    // Fetch employees for selected department
                    $.ajax({
                        url: 'ajax/get_department_employees.php',
                        method: 'POST',
                        data: { dept_id: deptId },
                        success: function(response) {
                            try {
                                const employees = JSON.parse(response);
                                employees.forEach(function(emp) {
                                    employeeSelect.append(
                                        $('<option></option>')
                                            .val(emp.emp_id)
                                            .text(emp.first_name + ' ' + emp.last_name)
                                            .data('salary', emp.basic_salary)
                                    );
                                });
                            } catch (e) {
                                console.error('Error parsing employee data:', e);
                                alert('Error loading employees. Please try again.');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error loading employees:', error);
                            alert('Error loading employees. Please try again.');
                        }
                    });
                }
            }

            // Event listeners
            $('#department').on('change', loadEmployees);
            
            $('#employee_id').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const basicSalary = selectedOption.data('salary');
                if (basicSalary) {
                    $('#basic_salary').val(basicSalary);
                } else {
                    $('#basic_salary').val('');
                }
            });

            // Function to view payroll details
            window.viewPayroll = function(payrollId) {
                $.ajax({
                    url: 'ajax/get_payroll_details.php',
                    type: 'GET',
                    data: { payroll_id: payrollId },
                    success: function(response) {
                        try {
                            const payroll = JSON.parse(response);
                            // Create and show modal with payroll details
                            let modal = `
                                <div class="modal fade" id="viewPayrollModal" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Payroll Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p><strong>Employee:</strong> ${payroll.employee_name}</p>
                                                        <p><strong>Department:</strong> ${payroll.department}</p>
                                                        <p><strong>Period:</strong> ${payroll.period}</p>
                                                        <p><strong>Status:</strong> <span class="badge bg-${payroll.status === 'paid' ? 'success' : 'warning'}">${payroll.status}</span></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Basic Salary:</strong> $${payroll.basic_salary}</p>
                                                        <p><strong>Bonus:</strong> $${payroll.bonus_amount}</p>
                                                        <p><strong>Gross Salary:</strong> $${payroll.gross_salary}</p>
                                                        <p><strong>Net Salary:</strong> $${payroll.net_salary}</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                ${payroll.status !== 'paid' ? 
                                                    `<button type="button" class="btn btn-success" onclick="updatePayrollStatus(${payrollId}, 'paid')">Mark as Paid</button>` : 
                                                    ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>`;
                            
                            // Remove existing modal if any
                            $('#viewPayrollModal').remove();
                            
                            // Add new modal to body and show it
                            $('body').append(modal);
                            new bootstrap.Modal('#viewPayrollModal').show();
                        } catch (e) {
                            console.error('Error parsing payroll details:', e);
                            alert('Error loading payroll details. Please try again.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading payroll details:', error);
                        alert('Error loading payroll details. Please try again.');
                    }
                });
            };

            // Function to update payroll status
            window.updatePayrollStatus = function(payrollId, status) {
                if (confirm('Are you sure you want to mark this payroll as ' + status + '?')) {
                    $.ajax({
                        url: 'ajax/update_payroll_status.php',
                        type: 'POST',
                        data: {
                            payroll_id: payrollId,
                            status: status
                        },
                        success: function(response) {
                            try {
                                const result = JSON.parse(response);
                                if (result.success) {
                                    // Reload page to show updated status
                                    window.location.reload();
                                } else {
                                    alert('Error updating payroll status: ' + result.error);
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                alert('Error updating payroll status. Please try again.');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error updating payroll status:', error);
                            alert('Error updating payroll status. Please try again.');
                        }
                    });
                }
            };

            // Function to edit payroll
            window.editPayroll = function(payrollId) {
                // Show edit modal
                $.ajax({
                    url: 'ajax/get_payroll_details.php',
                    type: 'GET',
                    data: { payroll_id: payrollId },
                    success: function(response) {
                        try {
                            const payroll = JSON.parse(response);
                            // Create and show edit modal
                            let modal = `
                                <div class="modal fade" id="editPayrollModal" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Payroll</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form id="editPayrollForm">
                                                <div class="modal-body">
                                                    <input type="hidden" name="payroll_id" value="${payrollId}">
                                                    <input type="hidden" name="action" value="edit_payroll">
                                                    
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Employee</label>
                                                            <input type="text" class="form-control" value="${payroll.employee_name}" readonly>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Department</label>
                                                            <input type="text" class="form-control" value="${payroll.department}" readonly>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Basic Salary</label>
                                                            <input type="number" class="form-control" name="basic_salary" value="${payroll.basic_salary.replace(/[^0-9.-]+/g, '')}" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Bonus Amount</label>
                                                            <input type="number" class="form-control" name="bonus_amount" value="${payroll.bonus_amount.replace(/[^0-9.-]+/g, '')}" required readonly>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Gross Salary</label>
                                                            <input type="number" class="form-control" name="gross_salary" value="${payroll.gross_salary.replace(/[^0-9.-]+/g, '')}" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Net Salary</label>
                                                            <input type="number" class="form-control" name="net_salary" value="${payroll.net_salary.replace(/[^0-9.-]+/g, '')}" required>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Update Payroll</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>`;
                            
                            // Remove existing modal if any
                            $('#editPayrollModal').remove();
                            
                            // Add new modal to body and show it
                            $('body').append(modal);
                            new bootstrap.Modal('#editPayrollModal').show();

                            // Handle edit form submission
                            $('#editPayrollForm').on('submit', function(e) {
                                e.preventDefault();
                                
                                $.ajax({
                                    url: 'ajax/update_payroll.php',
                                    type: 'POST',
                                    data: $(this).serialize(),
                                    success: function(response) {
                                        try {
                                            const result = JSON.parse(response);
                                            if (result.success) {
                                                // Close modal and reload page
                                                $('#editPayrollModal').modal('hide');
                                                window.location.reload();
                                            } else {
                                                alert('Error updating payroll: ' + result.error);
                                            }
                                        } catch (e) {
                                            console.error('Error parsing response:', e);
                                            alert('Error updating payroll. Please try again.');
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        console.error('Error updating payroll:', error);
                                        alert('Error updating payroll. Please try again.');
                                    }
                                });
                            });
                        } catch (e) {
                            console.error('Error parsing payroll details:', e);
                            alert('Error loading payroll details. Please try again.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading payroll details:', error);
                        alert('Error loading payroll details. Please try again.');
                    }
                });
            };

            // Handle payroll form submission
            $('#payrollForm').on('submit', function(e) {
                e.preventDefault();
                
                // Validate form
                if (!$('#department').val() || !$('#employee_id').val() || !$('input[name="pay_period"]').val()) {
                    alert('Please fill in all required fields');
                    return;
                }

                // Submit form
                $.ajax({
                    url: 'ajax/create_payroll.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                // Close modal and reload page
                                $('#payrollModal').modal('hide');
                                window.location.reload();
                            } else {
                                alert('Error creating payroll: ' + result.error);
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('Error creating payroll. Please try again.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error creating payroll:', error);
                        alert('Error creating payroll. Please try again.');
                    }
                });
            });
        });
    </script>
</body>
</html> 