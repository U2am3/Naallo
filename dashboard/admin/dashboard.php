<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login/admin.php");
    exit();
}

// Fetch some basic statistics
try {
    $stats = [
        'total_employees' => $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn(),
        'total_departments' => $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn(),
        'pending_leaves' => $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn(),
        'active_projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'in_progress'")->fetchColumn(),
        'pending_payroll' => $pdo->query("SELECT COUNT(*) FROM payroll WHERE status = 'draft'")->fetchColumn()
    ];
} catch (PDOException $e) {
    $error = "Error fetching statistics";
}

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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            background-color: var(--light-color);
            font-family: 'Inter', sans-serif;
        }
        .dashboard-header {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
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
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.15);
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
        .stat-card.payroll {
            background: linear-gradient(45deg, var(--danger-color), #e57373);
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
        .activity-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: none;
            height: 100%;
        }
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            margin-bottom: 1rem;
            background: var(--light-color);
            border-radius: 0.75rem;
            transition: all 0.3s ease;
        }
        .activity-item:hover {
            background: white;
            transform: translateX(5px);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 1rem;
            background: rgba(78, 115, 223, 0.1);
            color: var(--primary-color);
            flex-shrink: 0;
        }
        .activity-content {
            flex: 1;
        }
        .activity-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }
        .activity-time {
            font-size: 0.8rem;
            color: var(--secondary-color);
        }
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 1rem;
            }
            .activity-item {
                padding: 0.75rem;
            }
            .activity-icon {
                width: 32px;
                height: 32px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/topbar.php'; ?>
    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Dashboard Header -->
            <div class="dashboard-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-0">Admin Dashboard</h1>
                        <p class="text-muted mb-0">Welcome back, Admin!</p>
                    </div>
                   
                </div>
            </div>
            <!-- Stat Cards -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card team">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h6 class="stat-label">Total Employees</h6>
                        <h3 class="stat-value"><?php echo $stats['total_employees']; ?></h3>
                        <p class="text-muted mb-0">Active Staff Members</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card departments">
                        <div class="stat-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <h6 class="stat-label">Departments</h6>
                        <h3 class="stat-value"><?php echo $stats['total_departments']; ?></h3>
                        <p class="text-muted mb-0">Active Departments</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card leaves">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h6 class="stat-label">Pending Leaves</h6>
                        <h3 class="stat-value"><?php echo $stats['pending_leaves']; ?></h3>
                        <p class="text-muted mb-0">Leave Requests</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card projects">
                        <div class="stat-icon">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <h6 class="stat-label">Active Projects</h6>
                        <h3 class="stat-value"><?php echo $stats['active_projects']; ?></h3>
                        <p class="text-muted mb-0">Ongoing Projects</p>
                    </div>
                </div>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card payroll">
                        <div class="stat-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <h6 class="stat-label">Pending Payroll</h6>
                        <h3 class="stat-value"><?php echo $stats['pending_payroll']; ?></h3>
                        <p class="text-muted mb-0">Payroll Drafts</p>
                    </div>
                </div>
            </div>
            <!-- Charts Section -->
            <div class="row g-4 mt-4 mb-4">
                <div class="col-xl-8">
                    <div class="activity-card">
                        <div class="card-header d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Employee Growth</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height:300px;">
                                <canvas id="employeeGrowthChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="activity-card">
                        <div class="card-header mb-3">
                            <h5 class="card-title mb-0">Department Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height:300px;">
                                <canvas id="departmentChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div> <!-- /.container-fluid -->
    </div> <!-- /.main-content -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Employee Growth Chart (Line)
    const employeeGrowthCtx = document.getElementById('employeeGrowthChart').getContext('2d');
    new Chart(employeeGrowthCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
            datasets: [{
                label: 'Employees Added',
                data: [5, 8, 6, 10, 12, 9, 14, 11],
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    // Department Distribution Chart (Doughnut)
    const departmentCtx = document.getElementById('departmentChart').getContext('2d');
    new Chart(departmentCtx, {
        type: 'doughnut',
        data: {
            labels: ['IT', 'Marketing', 'Finance', 'HR'],
            datasets: [{
                data: [20, 15, 10, 8],
                backgroundColor: [
                    '#4e73df',
                    '#1cc88a',
                    '#f6c23e',
                    '#36b9cc'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    </script>
    <?php if (!empty($success_message)): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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