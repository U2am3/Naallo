<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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

$success = $error = '';
$employee_id = $_SESSION['user_id'];

// Get employee's emp_id
$stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE user_id = ?");
$stmt->execute([$employee_id]);
$emp = $stmt->fetch();
$emp_id = $emp['emp_id'];

// --- Handle Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'apply_leave':
                    $stmt = $pdo->prepare("INSERT INTO leave_requests (emp_id, requested_by_role, leave_type_id, start_date, end_date, reason, status, created_at) VALUES (?, 'employee', ?, ?, ?, ?, 'pending', NOW())");
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

// Employee's own leave requests
$stmt = $pdo->prepare("SELECT lr.*, lt.leave_type_name, lr.admin_remarks FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE lr.emp_id = ? AND lr.requested_by_role = 'employee' ORDER BY lr.created_at DESC");
$stmt->execute([$emp_id]);
$my_leaves = $stmt->fetchAll();

// Stats
$total = count($my_leaves);
$approved = count(array_filter($my_leaves, function($r) { return $r['status'] === 'approved'; }));
$pending = count(array_filter($my_leaves, function($r) { return $r['status'] === 'pending'; }));
$rejected = count(array_filter($my_leaves, function($r) { return $r['status'] === 'rejected'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - Employee Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 25px 0 rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
            min-height: 100%;
            background: var(--primary-color);
            color: white;
        }
        .card.card-stat.total {
            background: #6366f1;
        }
        .card.card-stat.approved {
            background: #22c55e;
        }
        .card.card-stat.pending {
            background: #fbbf24;
        }
        .card.card-stat.rejected {
            background: #06b6d4;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: white;
        }
        .stat-label {
            color: rgba(255,255,255,0.9);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0;
        }
        .table-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        .table-card .card-header {
            background: white;
            padding: 1.25rem;
            border-bottom: 1px solid #e3e6f0;
        }
        .table-card .card-header h6 {
            color: var(--primary-color) !important;
            font-weight: 600;
            margin: 0;
        }
        .table-card .table {
            margin: 0;
        }
        .table-card .table th {
            border-top: none;
            font-weight: 600;
            padding: 1rem;
            color: var(--dark-color);
        }
        .badge {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.5rem;
        }
        .badge.approved { background: var(--success-color); color: white; }
        .badge.pending { background: var(--warning-color); color: white; }
        .badge.rejected { background: var(--danger-color); color: white; }
        .badge.cancelled { background: var(--dark-color); color: white; }
        .btn-action {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
    .dashboard-header {
    background: white;
    padding: 1.5rem 2rem;
    border-radius: 0.75rem;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.10);
    margin-bottom: 1.5rem;
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="container-fluid">
        <div class="dashboard-header mb-4 d-flex justify-content-between align-items-center">
  <div>
    <h1 class="mb-1">Leave Management</h1>
    <div class="text-muted" style="font-size: 1.1rem;">View and manage your leave requests</div>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#applyLeaveModal"><i class="fas fa-plus me-2"></i>Apply for Leave</button>
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

        <!-- My Leave Requests Table -->
        <div class="table-card">
            <div class="card-header d-flex align-items-center">
                <i class="fas fa-calendar-alt me-2 text-primary"></i>
                <h6 class="m-0 font-weight-bold">My Leave Requests</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="leaveRequestsTable">
                        <thead>
                            <tr>
                                <th style="background: #f8f9fa; color: #333;">Leave Type</th>
                                <th style="background: #f8f9fa; color: #333;">From</th>
                                <th style="background: #f8f9fa; color: #333;">To</th>
                                <th style="background: #f8f9fa; color: #333;">Status</th>
                                <th style="background: #f8f9fa; color: #333;">Reason</th>
                                <th style="background: #f8f9fa; color: #333;">Admin Remarks</th>
                                <th style="background: #f8f9fa; color: #333;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_leaves as $leave): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['start_date']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['end_date']); ?></td>
                                    <td><span class="badge badge-status <?php echo $leave['status']; ?>"><?php echo ucfirst($leave['status']); ?></span></td>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#leaveRequestsTable').DataTable({
        order: [[1, 'desc']],
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
        columnDefs: [{
            targets: 3,
            render: function(data, type, row) {
                const status = data.toLowerCase();
                const statusMap = {
                    'approved': 'text-success',
                    'pending': 'text-warning',
                    'rejected': 'text-danger',
                    'cancelled': 'text-secondary'
                };
                const colorClass = statusMap[status] || 'text-secondary';
                return `<span class="${colorClass}">${data}</span>`;
            }
        }]
    });
});
</script>
</body>
</html> 