<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/attendance_functions.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login/admin.php");
    exit();
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
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

// Initialize variables
$success = $error = '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$department = isset($_GET['department']) ? $_GET['department'] : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';

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

try {
    // Get attendance policy
    $stmt = $pdo->query("SELECT * FROM attendance_policy ORDER BY policy_id DESC LIMIT 1");
    $policy = $stmt->fetch();

    // Build the base query with proper role filtering
    $query = "
        SELECT 
            a.*,
            e.first_name,
            e.last_name,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            e.position,
            d.dept_name,
            u.role
        FROM attendance a
        JOIN employees e ON a.emp_id = e.emp_id
        JOIN departments d ON e.dept_id = d.dept_id
        JOIN users u ON e.user_id = u.user_id
        WHERE a.attendance_date BETWEEN :start_date AND :end_date
    ";
    
    $params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];

    // Add department filter if selected
    if ($department) {
        $query .= " AND e.dept_id = :department";
        $params[':department'] = $department;
    }

    // Add role filter if selected
    if ($role) {
        $query .= " AND u.role = :role";
        $params[':role'] = $role;
    }

    $query .= " ORDER BY a.attendance_date DESC, e.first_name ASC";

    // Prepare and execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll();

    // Get attendance statistics based on the filtered records
    $stats = [
        'total_records' => count($attendance_records),
        'present_count' => 0,
        'late_count' => 0,
        'absent_count' => 0
    ];

    foreach ($attendance_records as $record) {
        switch ($record['status']) {
            case 'present':
                $stats['present_count']++;
                break;
            case 'late':
                $stats['late_count']++;
                break;
            case 'absent':
                $stats['absent_count']++;
                break;
        }
    }

    // Fetch departments for filter
    $stmt = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name");
    $departments = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}

// Handle attendance actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        switch ($_POST['action']) {
            case 'update_policy':
                $stmt = $pdo->prepare("
                    UPDATE attendance_policy 
                    SET min_hours_present = ?, 
                        min_hours_late = ?, 
                        grace_period_minutes = ?
                    WHERE policy_id = ?
                ");
                $stmt->execute([
                    $_POST['min_hours_present'],
                    $_POST['min_hours_late'],
                    $_POST['grace_period_minutes'],
                    $_POST['policy_id']
                ]);
                $success = "Attendance policy updated successfully!";
                break;

            case 'export_report':
                $report_data = generateAttendanceReport(
                    $_POST['report_type'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['department_id'] ?? null,
                    $_POST['emp_id'] ?? null
                );
                exportAttendanceToCSV($report_data['records'], 'attendance_report.csv');
                break;
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        .stat-card.present {
            background: linear-gradient(45deg, var(--success-color), #4cd4a3);
        }
        .stat-card.late {
            background: linear-gradient(45deg, var(--warning-color), #f8d06b);
        }
        .stat-card.absent {
            background: linear-gradient(45deg, var(--danger-color), #e57373);
        }
        .stat-card.total {
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
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .stat-card p.text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        .activity-card, .modern-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: none;
            margin-bottom: 1.5rem;
        }
        .modern-card .card-header {
            background: none;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.5rem 1rem 0;
        }
        .modern-card .card-title {
            color: var(--dark-color);
            font-weight: 700;
            margin: 0;
        }
        .table-responsive {
            max-height: 600px;
            border-radius: 0.5rem;
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
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
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
        .alert {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content" id="main-content">
        <?php include 'includes/topbar.php'; ?>
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="dashboard-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-0">Attendance Management</h1>
                        <p class="text-muted mb-0">Monitor and manage employee attendance records</p>
                    </div>
                    <div>
                        <button class="btn btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#policyModal">
                            <i class="fas fa-cog"></i> View Policy
                        </button>
                       
                    </div>
                </div>
            </div>
            <!-- Stat Cards -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card total">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h6 class="stat-label">Total Records</h6>
                        <h3 class="stat-value"><?php echo number_format($stats['total_records'] ?? 0); ?></h3>
                        <p class="text-muted mb-0">This Month</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card present">
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h6 class="stat-label">Present</h6>
                        <h3 class="stat-value"><?php echo number_format($stats['present_count'] ?? 0); ?></h3>
                        <p class="text-muted mb-0">On Time Attendance</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card late">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h6 class="stat-label">Late</h6>
                        <h3 class="stat-value"><?php echo number_format($stats['late_count'] ?? 0); ?></h3>
                        <p class="text-muted mb-0">Delayed Check-ins</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card absent">
                        <div class="stat-icon">
                            <i class="fas fa-user-xmark"></i>
                        </div>
                        <h6 class="stat-label">Absent</h6>
                        <h3 class="stat-value"><?php echo number_format($stats['absent_count'] ?? 0); ?></h3>
                        <p class="text-muted mb-0">No Attendance Record</p>
                    </div>
                </div>
            </div>
            <!-- Filter Form -->
            <div class="modern-card mb-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">Filter Records</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
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
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role">
                                <option value="">All Roles</option>
                                <option value="manager" <?php echo $role === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                <option value="employee" <?php echo $role === 'employee' ? 'selected' : ''; ?>>Employee</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Attendance Table -->
            <div class="modern-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">Attendance Records</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="attendanceTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Role</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Total Hours</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($attendance_records) && is_array($attendance_records)): ?>
                                    <?php foreach ($attendance_records as $record): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d', strtotime($record['attendance_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($record['employee_name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($record['dept_name'] ?? 'Not Assigned'); ?></td>
                                            <td><?php echo ucfirst($record['role'] ?? ''); ?></td>
                                            <td><?php echo $record['time_in'] ? date('H:i:s', strtotime($record['time_in'])) : '-'; ?></td>
                                            <td><?php echo $record['time_out'] ? date('H:i:s', strtotime($record['time_out'])) : '-'; ?></td>
                                            <td><?php echo $record['total_hours'] ?? '-'; ?></td>
                                            <td>
                                                <span class="status-badge bg-<?php echo getStatusBadgeClass($record['status'] ?? ''); ?>">
                                                    <?php echo ucfirst($record['status'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Policy Modal -->
    <div class="modal fade" id="policyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Attendance Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_policy">
                        <input type="hidden" name="policy_id" value="<?php echo $policy['policy_id'] ?? ''; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Minimum Hours for Present</label>
                            <input type="number" class="form-control" name="min_hours_present" 
                                   value="<?php echo $policy['min_hours_present'] ?? 8.00; ?>" step="0.5" min="0" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Minimum Hours for Late</label>
                            <input type="number" class="form-control" name="min_hours_late" 
                                   value="<?php echo $policy['min_hours_late'] ?? 5.00; ?>" step="0.5" min="0" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Grace Period (minutes)</label>
                            <input type="number" class="form-control" name="grace_period_minutes" 
                                   value="<?php echo $policy['grace_period_minutes'] ?? 15; ?>" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Policy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<!--     
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Attendance Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="export_report">
                        
                        <div class="mb-3">
                            <label class="form-label">Report Type</label>
                            <select class="form-select" name="report_type" required>
                                <option value="daily">Daily Report</option>
                                <option value="monthly">Monthly Report</option>
                                <option value="department">Department Report</option>
                                <option value="employee">Employee Report</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id" id="exportDepartment">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['dept_id']; ?>">
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <select class="form-select" name="emp_id" id="exportEmployee">
                                <option value="">All Employees</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Export</button>
                    </div> -->
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#attendanceTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                language: {
                    search: "Search records:",
                    lengthMenu: "Show _MENU_ records per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ records",
                    infoEmpty: "No records available",
                    infoFiltered: "(filtered from _MAX_ total records)"
                }
            });

            // Handle department change in export modal
            $('#exportDepartment').change(function() {
                const deptId = $(this).val();
                const employeeSelect = $('#exportEmployee');
                
                // Clear current options
                employeeSelect.html('<option value="">All Employees</option>');
                
                if (deptId) {
                    // Fetch employees for selected department
                    $.ajax({
                        url: 'ajax/get_department_employees.php',
                        method: 'POST',
                        data: { dept_id: deptId },
                        success: function(response) {
                            const employees = JSON.parse(response);
                            employees.forEach(function(emp) {
                                employeeSelect.append(
                                    $('<option></option>')
                                        .val(emp.emp_id)
                                        .text(emp.employee_name)
                                );
                            });
                        }
                    });
                }
            });
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