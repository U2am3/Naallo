<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../../config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../../login/manager.php");
    exit();
}

$success = $error = '';
$manager_id = $_SESSION['user_id'];

// Get manager's emp_id and department
$stmt = $pdo->prepare("SELECT emp_id, dept_id FROM employees WHERE user_id = ?");
$stmt->execute([$manager_id]);
$manager = $stmt->fetch();
$emp_id = $manager['emp_id'];
$dept_id = $manager['dept_id'];

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

// --- Handle Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'apply_leave':
                    $stmt = $pdo->prepare("INSERT INTO leave_requests (emp_id, requested_by_role, leave_type_id, start_date, end_date, reason, status, created_at) VALUES (?, 'manager', ?, ?, ?, ?, 'pending', NOW())");
                    $stmt->execute([
                        $emp_id,
                        $_POST['leave_type_id'],
                        $_POST['start_date'],
                        $_POST['end_date'],
                        $_POST['reason']
                    ]);
                    $success = "Leave request submitted.";
                    break;
                case 'cancel_leave':
                    $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE leave_id = ? AND emp_id = ? AND status = 'pending'");
                    $stmt->execute([
                        $_POST['leave_id'],
                        $emp_id
                    ]);
                    $success = "Leave request cancelled.";
                    break;
                case 'approve_emp_leave':
                    $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'approved', admin_remarks = ?, approved_by = ?, approved_at = NOW() WHERE leave_id = ? AND status = 'pending'");
                    $stmt->execute([
                        $_POST['manager_remarks'],
                        $manager_id,
                        $_POST['leave_id']
                    ]);
                    $success = "Employee leave approved.";
                    break;
                case 'reject_emp_leave':
                    $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'rejected', admin_remarks = ?, approved_by = ?, approved_at = NOW() WHERE leave_id = ? AND status = 'pending'");
                    $stmt->execute([
                        $_POST['manager_remarks'],
                        $manager_id,
                        $_POST['leave_id']
                    ]);
                    $success = "Employee leave rejected.";
                    break;
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// --- Fetch Data ---
// Leave Types
$stmt = $pdo->query("SELECT * FROM leave_types ORDER BY leave_type_id ASC");
$leave_types = $stmt->fetchAll();

// Manager's own leave requests
$stmt = $pdo->prepare("SELECT lr.*, lt.leave_type_name, lr.admin_remarks FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE lr.emp_id = ? AND lr.requested_by_role = 'manager' ORDER BY lr.created_at DESC");
$stmt->execute([$emp_id]);
$my_leaves = $stmt->fetchAll();

// Stats
$total = count($my_leaves);
$approved = count(array_filter($my_leaves, function($r) { return $r['status'] === 'approved'; }));
$pending = count(array_filter($my_leaves, function($r) { return $r['status'] === 'pending'; }));
$rejected = count(array_filter($my_leaves, function($r) { return $r['status'] === 'rejected'; }));

// Employee leave requests in manager's department
$emp_status = $_GET['emp_status'] ?? '';
$emp_start = $_GET['emp_start'] ?? '';
$emp_end = $_GET['emp_end'] ?? '';
$emp_emp = $_GET['emp_emp'] ?? '';

$query = "
    SELECT lr.*, lt.leave_type_name, e.first_name, e.last_name, e.emp_id
    FROM leave_requests lr
    JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
    JOIN employees e ON lr.emp_id = e.emp_id
    JOIN users u ON e.user_id = u.user_id
    WHERE e.dept_id = ? AND u.role = 'employee'
";
$params = [$dept_id];
if ($emp_status) {
    $query .= " AND lr.status = ?";
    $params[] = $emp_status;
}
if ($emp_start) {
    $query .= " AND lr.start_date >= ?";
    $params[] = $emp_start;
}
if ($emp_end) {
    $query .= " AND lr.end_date <= ?";
    $params[] = $emp_end;
}
if ($emp_emp) {
    $query .= " AND e.emp_id = ?";
    $params[] = $emp_emp;
}
$query .= " ORDER BY lr.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$emp_leaves = $stmt->fetchAll();

// Employees in department for filter
$stmt = $pdo->prepare("SELECT emp_id, first_name, last_name FROM employees WHERE dept_id = ?");
$stmt->execute([$dept_id]);
$dept_employees = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --info-color: #36b9cc;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
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
        .card.card-stat.approved {
            background: linear-gradient(45deg, #1cc88a, #4cd4a3);
        }
        .card.card-stat.pending {
            background: linear-gradient(45deg, #f6c23e, #f8d06b);
        }
        .card.card-stat.rejected {
            background: linear-gradient(45deg, #e74a3b, #f87171);
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
        .table th, .table thead th {
            /* background-color: #4e73df !important; */
            color: black !important;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem;
            border-bottom: 2px solid #e3e6f0;
        }
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5em;
            font-size: 1rem;
            font-weight: 600;
            padding: 0.5em 1.2em;
            border-radius: 2em;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            letter-spacing: 0.03em;
            margin: 0.1em 0;
        }
        .badge-status.approved {
            background: #1cc88a;
            color: #fff;
        }
        .badge-status.pending {
            background: #f6c23e;
            color: #fff;
        }
        .badge-status.rejected {
            background: #e74a3b;
            color: #fff;
        }
        .badge-status.cancelled {
            background: #6c757d;
            color: #fff;
        }
        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
    
    </style>
<style>
table th {
    background: none !important;
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="container-fluid">
        <div class="page-header mb-4">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="mb-0"><i class=""></i>Leave Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal"><i class="fas fa-plus me-2"></i>Apply for Leave</button>
                </div>
            </div>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card card-stat total h-100">
                    <div class="stat-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stat-value"><?php echo $total; ?></div>
                    <div class="stat-label">My Requests</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat approved h-100">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $approved; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat pending h-100">
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-value"><?php echo $pending; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat rejected h-100">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $rejected; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>
        <!-- After stats cards, add this section: -->
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center">
                <i class="fas fa-users me-2 text-primary"></i>
                <h6 class="m-0 font-weight-bold">Employees on Leave</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Leave Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->prepare("SELECT e.first_name, e.last_name, lt.leave_type_name, lr.start_date, lr.end_date, lr.status FROM leave_requests lr JOIN employees e ON lr.emp_id = e.emp_id JOIN users u ON e.user_id = u.user_id JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE u.role = 'employee' AND lr.status = 'approved' AND lr.end_date >= CURDATE() AND e.dept_id = ? ORDER BY lr.start_date ASC");
                            $stmt->execute([$dept_id]);
                            $employees_on_leave = $stmt->fetchAll();
                            foreach ($employees_on_leave as $leave): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['start_date']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['end_date']); ?></td>
                                    <td><span class="badge badge-status approved"><i class="fas fa-check-circle me-1"></i>Approved</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- My Leave Requests Table -->
        <div class="card mb-4">
            <div class="card-header"><h6 class="m-0 font-weight-bold">My Leave Requests</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Leave Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th>Admin Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_leaves as $leave): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['start_date']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['end_date']); ?></td>
                                    <td><span class="badge badge-status <?php echo $leave['status']; ?>">
                                        <?php if ($leave['status'] === 'approved'): ?><i class="fas fa-check-circle me-1"></i><?php endif; ?>
                                        <?php if ($leave['status'] === 'pending'): ?><i class="fas fa-hourglass-half me-1"></i><?php endif; ?>
                                        <?php if ($leave['status'] === 'rejected'): ?><i class="fas fa-times-circle me-1"></i><?php endif; ?>
                                        <?php if ($leave['status'] === 'cancelled'): ?><i class="fas fa-ban me-1"></i><?php endif; ?>
                                        <?php echo ucfirst($leave['status']); ?>
                                    </span></td>
                                    <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['admin_remarks']); ?></td>
                                    <td>
                                        <?php if ($leave['status'] === 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="cancel_leave">
                                                <input type="hidden" name="leave_id" value="<?php echo $leave['leave_id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this leave request?');"><i class="fas fa-times"></i> Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Employee Leave Requests Table -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Employee Leave Requests (My Department)</h6>
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-auto">
                        <select class="form-select" name="emp_status">
                            <option value="">All Status</option>
                            <option value="pending" <?php if ($emp_status === 'pending') echo 'selected'; ?>>Pending</option>
                            <option value="approved" <?php if ($emp_status === 'approved') echo 'selected'; ?>>Approved</option>
                            <option value="rejected" <?php if ($emp_status === 'rejected') echo 'selected'; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <input type="date" class="form-control" name="emp_start" value="<?php echo htmlspecialchars($emp_start); ?>">
                    </div>
                    <div class="col-auto">
                        <input type="date" class="form-control" name="emp_end" value="<?php echo htmlspecialchars($emp_end); ?>">
                    </div>
                    <div class="col-auto">
                        <select class="form-select" name="emp_emp">
                            <option value="">All Employees</option>
                            <?php foreach ($dept_employees as $emp): ?>
                                <option value="<?php echo $emp['emp_id']; ?>" <?php if ($emp_emp == $emp['emp_id']) echo 'selected'; ?>><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary" type="submit"><i class="fa fa-search me-1"></i>Filter</button>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th>Manager Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emp_leaves as $leave): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['start_date']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['end_date']); ?></td>
                                    <td><span class="badge badge-status <?php echo $leave['status']; ?>">
                                        <?php if ($leave['status'] === 'approved'): ?><i class="fas fa-check-circle me-1"></i><?php endif; ?>
                                        <?php if ($leave['status'] === 'pending'): ?><i class="fas fa-hourglass-half me-1"></i><?php endif; ?>
                                        <?php if ($leave['status'] === 'rejected'): ?><i class="fas fa-times-circle me-1"></i><?php endif; ?>
                                        <?php if ($leave['status'] === 'cancelled'): ?><i class="fas fa-ban me-1"></i><?php endif; ?>
                                        <?php echo ucfirst($leave['status']); ?>
                                    </span></td>
                                    <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['admin_remarks']); ?></td>
                                    <td>
                                        <?php if ($leave['status'] === 'pending'): ?>
                                            <button class="btn btn-success btn-sm approve-emp-btn" data-id="<?php echo $leave['leave_id']; ?>" data-bs-toggle="modal" data-bs-target="#approveEmpModal"><i class="fas fa-check"></i></button>
                                            <button class="btn btn-danger btn-sm reject-emp-btn" data-id="<?php echo $leave['leave_id']; ?>" data-bs-toggle="modal" data-bs-target="#rejectEmpModal"><i class="fas fa-times"></i></button>
                                        <?php endif; ?>
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
<!-- Apply Leave Modal -->
<div class="modal fade" id="applyLeaveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Apply for Leave</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="apply_leave">
                    <div class="mb-3">
                        <label class="form-label">Leave Type</label>
                        <select class="form-select" name="leave_type_id" required>
                            <option value="">Select Leave Type</option>
                            <?php foreach ($leave_types as $type): ?>
                                <option value="<?php echo $type['leave_type_id']; ?>"><?php echo htmlspecialchars($type['leave_type_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">From</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">To</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Approve/Reject Employee Leave Modals -->
<div class="modal fade" id="approveEmpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Employee Leave</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="approve_emp_leave">
                    <input type="hidden" name="leave_id" id="approve_emp_leave_id">
                    <div class="mb-3">
                        <label class="form-label">Manager Remarks (optional)</label>
                        <textarea class="form-control" name="manager_remarks" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="rejectEmpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Employee Leave</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject_emp_leave">
                    <input type="hidden" name="leave_id" id="reject_emp_leave_id">
                    <div class="mb-3">
                        <label class="form-label">Manager Remarks (optional)</label>
                        <textarea class="form-control" name="manager_remarks" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    // Approve/Reject employee leave modals
    $('.approve-emp-btn').click(function() {
        $('#approve_emp_leave_id').val($(this).data('id'));
    });
    $('.reject-emp-btn').click(function() {
        $('#reject_emp_leave_id').val($(this).data('id'));
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