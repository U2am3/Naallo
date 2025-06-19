<?php
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
    SELECT e.*, d.dept_name
    FROM employees e
    LEFT JOIN departments d ON e.dept_id = d.dept_id
    WHERE e.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$manager = $stmt->fetch();

// Get report statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_reports,
        COUNT(CASE WHEN report_type = 'monthly' THEN 1 END) as monthly_reports,
        COUNT(CASE WHEN report_type = 'department' THEN 1 END) as department_reports,
        COUNT(CASE WHEN report_type = 'employee' THEN 1 END) as employee_reports
    FROM attendance_reports
    WHERE department_id = ? OR created_by = ?
");
$stmt->execute([$manager['dept_id'], $_SESSION['user_id']]);
$report_stats = $stmt->fetch();

// Extract the statistics
$total_reports = $report_stats['total_reports'];
$monthly_reports = $report_stats['monthly_reports'];
$department_reports = $report_stats['department_reports'];
$employee_reports = $report_stats['employee_reports'];

// Get date filters
$current_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Get attendance statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT e.emp_id) as total_employees,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN a.status = 'half-day' THEN 1 ELSE 0 END) as halfday_count,
        AVG(a.total_hours) as avg_hours_worked
    FROM employees e
    LEFT JOIN attendance a ON e.emp_id = a.emp_id AND DATE(a.attendance_date) = ?
    WHERE e.dept_id = ?
");
$stmt->execute([$current_date, $manager['dept_id']]);
$attendance_stats = $stmt->fetch();

// Get monthly attendance summary
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

// Get project statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_projects,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_projects,
        SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing_projects,
        SUM(CASE WHEN status = 'planning' THEN 1 ELSE 0 END) as planning_projects,
        AVG(DATEDIFF(end_date, start_date)) as avg_duration
    FROM projects
    WHERE manager_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$project_stats = $stmt->fetch();

// Get leave statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_leaves,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_leaves,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_leaves,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_leaves,
        AVG(DATEDIFF(end_date, start_date)) as avg_duration
    FROM leave_requests lr
    JOIN employees e ON lr.emp_id = e.emp_id
    WHERE e.dept_id = ?
");
$stmt->execute([$manager['dept_id']]);
$leave_stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Manager Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .card-stat small {
            font-size: 0.75rem;
            opacity: 0.8;
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

        .alert {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
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

        <!-- Page Header -->
        <div class="page-header">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h3 mb-0 text-white">Reports</h1>
                    <div>
                        <button class="btn btn-light" onclick="downloadReport()">
                            <i class="fas fa-file-export me-2"></i>Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="container-fluid">
            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold">Select Date Range</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" value="<?php echo $current_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Month</label>
                            <input type="month" class="form-control" name="month" value="<?php echo $current_month; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics Row -->
            <div class="row mb-4">
                <!-- Total Reports Card -->
                <div class="col-md-3">
                    <div class="card card-stat bg-white h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                                        <i class="fa-solid fa-file-alt fa-2x"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="card-title text-muted mb-1">Total Reports</h6>
                                    <h2 class="mb-0 text-primary"><?php echo $total_reports; ?></h2>
                                    <small class="text-muted">All Reports</small>
                                </div>
                            </div>
                            <div class="progress mt-3">
                                <div class="progress-bar bg-primary" role="progressbar" 
                                     style="width: 100%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Reports Card -->
                <div class="col-md-3">
                    <div class="card card-stat bg-white h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stat-icon bg-success bg-opacity-10 text-success rounded-circle p-3">
                                        <i class="fa-solid fa-calendar-alt fa-2x"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="card-title text-muted mb-1">Monthly Reports</h6>
                                    <h2 class="mb-0 text-success"><?php echo $monthly_reports; ?></h2>
                                    <small class="text-muted">This Month</small>
                                </div>
                            </div>
                            <div class="progress mt-3">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo ($total_reports > 0) ? ($monthly_reports / $total_reports * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department Reports Card -->
                <div class="col-md-3">
                    <div class="card card-stat bg-white h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stat-icon bg-info bg-opacity-10 text-info rounded-circle p-3">
                                        <i class="fa-solid fa-building fa-2x"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="card-title text-muted mb-1">Department Reports</h6>
                                    <h2 class="mb-0 text-info"><?php echo $department_reports; ?></h2>
                                    <small class="text-muted">Department Specific</small>
                                </div>
                            </div>
                            <div class="progress mt-3">
                                <div class="progress-bar bg-info" role="progressbar" 
                                     style="width: <?php echo ($total_reports > 0) ? ($department_reports / $total_reports * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employee Reports Card -->
                <div class="col-md-3">
                    <div class="card card-stat bg-white h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-circle p-3">
                                        <i class="fa-solid fa-user-tie fa-2x"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="card-title text-muted mb-1">Employee Reports</h6>
                                    <h2 class="mb-0 text-warning"><?php echo $employee_reports; ?></h2>
                                    <small class="text-muted">Individual Reports</small>
                                </div>
                            </div>
                            <div class="progress mt-3">
                                <div class="progress-bar bg-warning" role="progressbar" 
                                     style="width: <?php echo ($total_reports > 0) ? ($employee_reports / $total_reports * 100) : 0; ?>%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Attendance Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Monthly Attendance Summary (<?php echo date('F Y', strtotime($current_month . '-01')); ?>)</h5>
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
                                    <th>Average Hours</th>
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
                                    <td><?php echo number_format($summary['avg_hours_worked'], 1); ?></td>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#monthlySummaryTable').DataTable();
        });

        // Function to download report
        function downloadReport() {
            // Create form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'generate_report.php';
            form.target = '_blank'; // Open in new tab
            
            // Add date input
            const dateInput = document.createElement('input');
            dateInput.type = 'hidden';
            dateInput.name = 'date';
            dateInput.value = '<?php echo $current_date; ?>';
            form.appendChild(dateInput);
            
            // Add month input
            const monthInput = document.createElement('input');
            monthInput.type = 'hidden';
            monthInput.name = 'month';
            monthInput.value = '<?php echo $current_month; ?>';
            form.appendChild(monthInput);
            
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
    </script>
</body>
</html> 