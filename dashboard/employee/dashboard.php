<?php
session_start();
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

try {
    // Get employee details
    $stmt = $pdo->prepare("
        SELECT e.*, d.dept_name, CONCAT(m.first_name, ' ', m.last_name) as manager_name
        FROM employees e
        LEFT JOIN departments d ON e.dept_id = d.dept_id
        LEFT JOIN employees m ON d.dept_head = m.user_id
        WHERE e.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $employee = $stmt->fetch();

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

    // Get leave statistics for current year
    $stmt = $pdo->prepare("
        SELECT 
            lt.leave_type_name,
            lt.default_days as total_leaves,
            COALESCE(elb.used_leaves, 0) as used_leaves
        FROM leave_types lt
        LEFT JOIN employee_leave_balance elb ON lt.leave_type_id = elb.leave_type_id 
            AND elb.emp_id = ? AND elb.year = YEAR(CURRENT_DATE)
    ");
    $stmt->execute([$employee['emp_id']]);
    $leave_stats = $stmt->fetchAll();

    // Get recent attendance records
    $stmt = $pdo->prepare("
        SELECT *
        FROM attendance
        WHERE emp_id = ?
        ORDER BY attendance_date DESC
        LIMIT 5
    ");
    $stmt->execute([$employee['emp_id']]);
    $recent_attendance = $stmt->fetchAll();

    // Get pending leave requests
    $stmt = $pdo->prepare("
        SELECT lr.*, lt.leave_type_name
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
        WHERE lr.emp_id = ? AND lr.status = 'pending'
        ORDER BY lr.start_date ASC
    ");
    $stmt->execute([$employee['emp_id']]);
    $pending_leaves = $stmt->fetchAll();

    // Get assigned projects
    $stmt = $pdo->prepare("
        SELECT p.*
        FROM projects p
        JOIN project_assignments pa ON p.project_id = pa.project_id
        WHERE pa.emp_id = ? AND p.status = 'in_progress'
        ORDER BY p.end_date ASC
    ");
    $stmt->execute([$employee['emp_id']]);
    $active_projects = $stmt->fetchAll();

    // Calculate leaves used and remaining
    $total_leaves_used = 0;
    $total_leaves_remaining = 0;
    foreach ($leave_stats as $stat) {
        $total_leaves_used += $stat['used_leaves'];
        $total_leaves_remaining += ($stat['total_leaves'] - $stat['used_leaves']);
    }
    $total_active_projects = count($active_projects);
    $total_pending_leaves = count($pending_leaves);
    $total_attendance_days = $attendance_stats['present_count'] + $attendance_stats['late_count'] + $attendance_stats['absent_count'] + $attendance_stats['halfday_count'];

    // Monthly Attendance Overview Data
    $monthly_present = array_fill(1, 12, 0);
    $monthly_absent = array_fill(1, 12, 0);
    $monthly_late = array_fill(1, 12, 0);
    $stmt = $pdo->prepare("
        SELECT 
            MONTH(attendance_date) as month,
            COUNT(CASE WHEN status IN ('present','half-day') THEN 1 END) as present_days,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
            COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days
        FROM attendance
        WHERE emp_id = ? AND YEAR(attendance_date) = YEAR(CURRENT_DATE)
        GROUP BY MONTH(attendance_date)
    ");
    $stmt->execute([$employee['emp_id']]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $month = (int)$row['month'];
        $monthly_present[$month] = (int)$row['present_days'];
        $monthly_absent[$month] = (int)$row['absent_days'];
        $monthly_late[$month] = (int)$row['late_days'];
    }

} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - EMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .stat-card.attendance {
            background: linear-gradient(45deg, var(--info-color), #5ccfe6);
        }

        .stat-card.leaves {
            background: linear-gradient(45deg, var(--warning-color), #f8d06b);
        }

        .stat-card.projects {
            background: linear-gradient(45deg, var(--success-color), #4cd4a3);
        }

        .stat-card.tasks {
            background: linear-gradient(45deg, var(--primary-color), #6f8de3);
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
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .chart-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
            height: 100%;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 0 1rem 0;
            border-bottom: 1px solid #e3e6f0;
            margin-bottom: 1rem;
        }

        .card-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            width: 100%;
        }

        .activity-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: none;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            margin-bottom: 1rem;
            background: var(--light-color);
            border-radius: 0.5rem;
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
<style>
    /* Match heights and paddings for activity-card body */
    .activity-card .activity-list {
        max-height: 260px;
        overflow-y: auto;
    }
    .card.card-stat {
        border-radius: 0.75rem;
        padding: 1.5rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        transition: all 0.3s ease;
        border: none;
        position: relative;
        overflow: hidden;
        color: white;
        min-height: 100%;
        background: linear-gradient(45deg, #4e73df, #224abe);
    }
    .card.card-stat.total {
        background: linear-gradient(45deg, #4e73df, #6f8de3);
    }
    .card.card-stat.paid {
        background: linear-gradient(45deg, #1cc88a, #4cd4a3);
    }
    .card.card-stat.pending {
        background: linear-gradient(45deg, #f6c23e, #f8d06b);
    }
    .card.card-stat.draft {
        background: linear-gradient(45deg, #36b9cc, #5ccfe6);
    }
    .stat-icon {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        margin-bottom: 0.75rem;
        font-size: 1.1rem;
        background: rgba(255,255,255,0.2);
        color: white;
    }
    .stat-value {
        font-size: 1.875rem;
        font-weight: 700;
        color: white;
        margin: 0.5rem 0;
    }
    .stat-label {
        color: rgba(255,255,255,0.8);
        font-size: 0.875rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
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
                <div class="dashboard-header mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h2 mb-0">Dashboard Overview</h1>
                            <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></p>
                        </div>
    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

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

                <!-- Statistics Row -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-stat total h-100">
                            <div class="stat-icon">
                                <i class="fas fa-leaf"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_leaves_used . ' / ' . $total_leaves_remaining; ?></div>
                            <div class="stat-label">Leaves Used / Remaining</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-stat paid h-100">
                            <div class="stat-icon">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_active_projects; ?></div>
                            <div class="stat-label">Active Projects</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-stat pending h-100">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_pending_leaves; ?></div>
                            <div class="stat-label">Pending Leave Requests</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-stat draft h-100">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-value"><?php echo $total_attendance_days; ?></div>
                            <div class="stat-label">Attendance Days (This Month)</div>
                        </div>
                    </div>
                </div>

               
                <!-- Add Chart.js -->
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

                <!-- After the statistics cards, add charts section -->
                <div class="row g-4 mt-4">
                    <!-- Monthly Attendance Overview Chart Section -->
                    <div class="col-xl-8">
                        <div class="activity-card mb-4">
                            <div class="card-header">
                                <h5 class="card-title">Monthly Attendance Overview</h5>
                                <div class="dropdown">
                                    <button class="btn btn-link dropdown-toggle p-0" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#">View Full Attendance</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body pt-4 pb-4 ps-4 pe-4">
                                <canvas id="attendanceOverviewChart" height="120"></canvas>
                            </div>
                        </div>
                    </div>
                    <!-- End Monthly Attendance Overview Chart Section -->

                    <div class="col-xl-4">
                        <div class="activity-card">
                            <div class="card-header">
                                <h5 class="card-title">Recent Activities</h5>
                                <div class="dropdown">
                                    <button class="btn btn-link dropdown-toggle p-0" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#">View All Activities</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="activity-list" style="max-height:260px; overflow-y:auto;">
                                <?php foreach ($recent_attendance as $attendance): ?>
                                <div class="activity-item">
                                    <div class="activity-icon attendance">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6 class="activity-title">Attendance Marked - <?php echo ucfirst($attendance['status']); ?></h6>
                                        <p class="activity-subtitle">
                                            <?php echo date('d M Y', strtotime($attendance['attendance_date'])); ?> at 
                                            <?php echo date('h:i A', strtotime($attendance['time_in'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <?php foreach ($pending_leaves as $leave): ?>
                                <div class="activity-item">
                                    <div class="activity-icon leave">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6 class="activity-title"><?php echo htmlspecialchars($leave['leave_type_name']); ?> Leave Request</h6>
                                        <p class="activity-subtitle">
                                            <?php echo date('d M Y', strtotime($leave['start_date'])); ?> - 
                                            <?php echo date('d M Y', strtotime($leave['end_date'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <?php foreach ($active_projects as $project): ?>
                                <div class="activity-item">
                                    <div class="activity-icon project">
                                        <i class="fas fa-project-diagram"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6 class="activity-title"><?php echo htmlspecialchars($project['project_name']); ?></h6>
                                        <p class="activity-subtitle">
                                            Due: <?php echo date('d M Y', strtotime($project['end_date'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="leaveBalanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mark Attendance Modal -->
    <div class="modal fade" id="markAttendanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mark Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process_attendance.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="mark">
                        <input type="hidden" name="emp_id" value="<?php echo $employee['emp_id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d'); ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" name="time" value="<?php echo date('H:i'); ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Mark Attendance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script>
        // Chart.js default configuration
        Chart.defaults.font.family = '"Inter", sans-serif';
        Chart.defaults.font.size = 13;
        Chart.defaults.color = '#858796';

        // Monthly Attendance Overview Chart
        const ctxAttendance = document.getElementById('attendanceOverviewChart').getContext('2d');
        // Data from PHP
        const monthlyPresent = <?php echo json_encode(array_values($monthly_present)); ?>;
        const monthlyAbsent = <?php echo json_encode(array_values($monthly_absent)); ?>;
        const monthlyLate = <?php echo json_encode(array_values($monthly_late)); ?>;
        const attendanceOverviewChart = new Chart(ctxAttendance, {
            type: 'bar',
            data: {
                labels: [
                    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
                ],
                datasets: [
                    {
                        label: 'Days Present',
                        data: monthlyPresent,
                        backgroundColor: 'rgba(78, 115, 223, 0.7)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        borderWidth: 1,
                        borderRadius: 6,
                    },
                    {
                        label: 'Days Late',
                        data: monthlyLate,
                        backgroundColor: 'rgba(241, 196, 15, 0.7)',
                        borderColor: 'rgba(241, 196, 15, 1)',
                        borderWidth: 1,
                        borderRadius: 6,
                    },
                    {
                        label: 'Days Absent',
                        data: monthlyAbsent,
                        backgroundColor: 'rgba(231, 76, 60, 0.7)',
                        borderColor: 'rgba(231, 76, 60, 1)',
                        borderWidth: 1,
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 18,
                            padding: 15
                        }
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 2
                        },
                        grid: {
                            color: '#f3f3f3'
                        }
                    }
                }
            }
        });
        Chart.defaults.plugins.legend.labels.usePointStyle = true;

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Mark Attendance Button
        document.getElementById('markAttendanceBtn').addEventListener('click', function() {
            new bootstrap.Modal(document.getElementById('markAttendanceModal')).show();
        });

        // Sidebar Toggle
        document.getElementById('toggle-sidebar').addEventListener('click', function() {
            document.querySelector('.wrapper').classList.toggle('sidebar-collapsed');
        });

        // Monthly Attendance Overview Chart (real data)
        const attendanceOverviewCtx = document.getElementById('attendanceOverviewChart').getContext('2d');
        new Chart(attendanceOverviewCtx, {
            type: 'bar',
            data: {
                labels: ['Present', 'Late', 'Absent', 'Half Day'],
                datasets: [{
                    label: 'Days',
                    data: [
                        <?php echo (int)$attendance_stats['present_count']; ?>,
                        <?php echo (int)$attendance_stats['late_count']; ?>,
                        <?php echo (int)$attendance_stats['absent_count']; ?>,
                        <?php echo (int)$attendance_stats['halfday_count']; ?>
                    ],
                    backgroundColor: [
                        '#4e73df',
                        '#f6c23e',
                        '#e74a3b',
                        '#36b9cc'
                    ],
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Leave Balance Chart
        const leaveBalanceCtx = document.getElementById('leaveBalanceChart').getContext('2d');
        new Chart(leaveBalanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Used', 'Remaining'],
                datasets: [{
                    data: [
                        <?php 
                        $total_used = 0;
                        foreach ($leave_stats as $stat) {
                            $total_used += $stat['used_leaves'];
                        }
                        echo $total_used;
                        ?>,
                        <?php 
                        $total_remaining = 0;
                        foreach ($leave_stats as $stat) {
                            $total_remaining += ($stat['total_leaves'] - $stat['used_leaves']);
                        }
                        echo $total_remaining;
                        ?>
                    ],
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a'
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
</body>
</html> 