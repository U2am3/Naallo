<?php
ob_start();
session_start();
require_once '../../config/database.php';
require_once '../admin/includes/functions.php';

// Check if user is logged in and is manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../../login.php");
    exit();
}

// Get manager details
$stmt = $pdo->prepare("
    SELECT e.*, u.username, d.dept_name as department_name 
    FROM employees e 
    JOIN users u ON e.user_id = u.user_id 
    JOIN departments d ON e.dept_id = d.dept_id 
    WHERE e.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$manager = $stmt->fetch();

// Get team size
$stmt = $pdo->prepare("
    SELECT COUNT(*) as team_size 
    FROM employees 
    WHERE dept_id = ? AND emp_id != ?
");
$stmt->execute([$manager['dept_id'], $manager['emp_id']]);
$team_size = $stmt->fetch()['team_size'];

// Get active projects
$stmt = $pdo->prepare("
    SELECT COUNT(*) as active_projects 
    FROM projects 
    WHERE manager_id = ? AND status = 'in_progress'
");
$stmt->execute([$manager['user_id']]);
$active_projects = $stmt->fetch()['active_projects'];

// Get pending leave requests
$stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_leaves 
    FROM leave_requests lr 
    JOIN employees e ON lr.emp_id = e.emp_id 
    WHERE e.dept_id = ? AND lr.status = 'pending'
");
$stmt->execute([$manager['dept_id']]);
$pending_leaves = $stmt->fetch()['pending_leaves'];

// Get today's attendance
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT COUNT(*) as present_count 
    FROM attendance a 
    JOIN employees e ON a.emp_id = e.emp_id 
    WHERE e.dept_id = ? AND a.attendance_date = ? AND a.status = 'present'
");
$stmt->execute([$manager['dept_id'], $today]);
$present_count = $stmt->fetch()['present_count'];

// Get recent activities
$stmt = $pdo->prepare("
    SELECT 
        'leave' as type,
        lr.created_at,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
        lr.reason as description
    FROM leave_requests lr
    JOIN employees e ON lr.emp_id = e.emp_id
    WHERE e.dept_id = ? AND lr.status = 'pending'
    UNION ALL
    SELECT 
        'attendance' as type,
        a.created_at,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
        CONCAT('Marked ', a.status, ' at ', TIME_FORMAT(a.time_in, '%h:%i %p')) as description
    FROM attendance a
    JOIN employees e ON a.emp_id = e.emp_id
    WHERE e.dept_id = ? AND a.attendance_date = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$manager['dept_id'], $manager['dept_id'], $today]);
$recent_activities = $stmt->fetchAll();

// Get team members with leave today
$stmt = $pdo->prepare("
    SELECT e.first_name, e.last_name, lt.leave_type_name
    FROM leave_requests lr
    JOIN employees e ON lr.emp_id = e.emp_id
    JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
    WHERE e.dept_id = ?
    AND lr.status = 'approved'
    AND CURDATE() BETWEEN lr.start_date AND lr.end_date
");
$stmt->execute([$manager['dept_id']]);
$on_leave_today = $stmt->fetchAll();

// Get recent projects
$stmt = $pdo->prepare("
    SELECT p.*, COUNT(pa.emp_id) as team_size
    FROM projects p
    LEFT JOIN project_assignments pa ON p.project_id = pa.project_id
    WHERE p.manager_id = ?
    GROUP BY p.project_id
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_projects = $stmt->fetchAll();

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - EMS</title>
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

        .stat-card.projects {
            background: linear-gradient(45deg, var(--success-color), #4cd4a3);
        }

        .stat-card.leaves {
            background: linear-gradient(45deg, var(--warning-color), #f8d06b);
        }

        .stat-card.attendance {
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

        .chart-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
            height: 100%;
        }

        .chart-card .card-header {
            background: none;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.5rem;
        }

        .chart-card .card-title {
            color: var(--dark-color);
            font-weight: 700;
            margin: 0;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
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

        .project-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.15);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .project-title {
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .project-status {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-in-progress { background: rgba(54, 185, 204, 0.1); color: var(--info-color); }
        .status-completed { background: rgba(28, 200, 138, 0.1); color: var(--success-color); }
        .status-pending { background: rgba(246, 194, 62, 0.1); color: var(--warning-color); }

        .project-progress {
            height: 0.5rem;
            background: var(--light-color);
            border-radius: 1rem;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .progress-bar {
            height: 100%;
            border-radius: 1rem;
            transition: width 0.3s ease;
        }

        .project-meta {
            display: flex;
            justify-content: space-between;
            color: var(--secondary-color);
            font-size: 0.875rem;
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

    <div class="main-content" style="margin-left:260px; padding-top:70px; min-height:100vh; background:#f8f9fc;">
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2 mb-0">Dashboard Overview</h1>
                    <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']); ?></p>
                </div>
                <div class="btn-group">
                    <!-- <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button> -->
                    <!-- <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-calendar"></i> This week
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#">Last Month</a></li>
                        <li><a class="dropdown-item" href="#">Last 3 Months</a></li>
                        <li><a class="dropdown-item" href="#">Last 6 Months</a></li>
                        <li><a class="dropdown-item" href="#">Last Year</a></li>
                    </ul> -->
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card team">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h6 class="stat-label">Team Size</h6>
                    <h3 class="stat-value"><?php echo $team_size; ?></h3>
                    <p class="text-muted mb-0">Active team members</p>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card projects">
                    <div class="stat-icon">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <h6 class="stat-label">Active Projects</h6>
                    <h3 class="stat-value"><?php echo $active_projects; ?></h3>
                    <p class="text-muted mb-0">Ongoing projects</p>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card leaves">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <h6 class="stat-label">Pending Leaves</h6>
                    <h3 class="stat-value"><?php echo $pending_leaves; ?></h3>
                    <p class="text-muted mb-0">Awaiting approval</p>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card attendance">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h6 class="stat-label">Present Today</h6>
                    <h3 class="stat-value"><?php echo $present_count; ?></h3>
                    <p class="text-muted mb-0">Team attendance</p>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="chart-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title">Team Performance Overview</h5>
                        <div class="dropdown">
                            <button class="btn btn-link dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                This Month
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Last Month</a></li>
                                <li><a class="dropdown-item" href="#">Last 3 Months</a></li>
                                <li><a class="dropdown-item" href="#">Last 6 Months</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="teamPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="chart-card">
                    <div class="card-header">
                        <h5 class="card-title">Attendance Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-4">
            <div class="col-xl-6">
                <div class="activity-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">Recent Activities</h5>
                        <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="activity-timeline">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas <?php echo $activity['type'] == 'leave' ? 'calendar-times' : 'clipboard-check'; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($activity['employee_name']); ?></div>
                                <div class="activity-time"><?php echo htmlspecialchars($activity['description']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="activity-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">Recent Projects</h5>
                        <a href="projects.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <?php foreach ($recent_projects as $project): ?>
                    <div class="project-card">
                        <div class="project-header">
                            <h6 class="project-title"><?php echo htmlspecialchars($project['project_name']); ?></h6>
                            <span class="project-status status-<?php echo strtolower($project['status']); ?>">
                                <?php echo ucfirst($project['status']); ?>
                            </span>
                        </div>
                        <div class="project-progress">
                            <div class="progress-bar bg-primary" style="width: <?php echo $project['progress'] ?? 0; ?>%"></div>
                        </div>
                        <div class="project-meta">
                            <span><i class="fas fa-users"></i> <?php echo $project['team_size']; ?> members</span>
                            <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($project['end_date'])); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    // Team Performance Chart
    const teamPerformanceCtx = document.getElementById('teamPerformanceChart').getContext('2d');
    new Chart(teamPerformanceCtx, {
        type: 'line',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                label: 'Present',
                data: [<?php echo $present_count; ?>, 18, 20, 22],
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Late',
                data: [<?php echo $pending_leaves; ?>, 5, 3, 2],
                borderColor: '#f6c23e',
                backgroundColor: 'rgba(246, 194, 62, 0.1)',
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

    // Attendance Distribution Chart
    const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(attendanceCtx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Late', 'Absent', 'Half Day'],
            datasets: [{
                data: [
                    <?php echo $present_count; ?>,
                    <?php echo $pending_leaves; ?>,
                    <?php echo $active_projects; ?>,
                    <?php echo $team_size; ?>
                ],
                backgroundColor: [
                    '#1cc88a',
                    '#f6c23e',
                    '#e74a3b',
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