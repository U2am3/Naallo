<?php
session_start();
require_once '../../config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login/admin.php");
    exit();
}

// Handle leave actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                try {
                    $pdo->beginTransaction();

                    // Get leave request details
                    $stmt = $pdo->prepare("
                        SELECT lr.*, lt.default_days, e.emp_id
                        FROM leave_requests lr
                        JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                        JOIN employees e ON lr.emp_id = e.emp_id
                        WHERE lr.leave_id = ?
                    ");
                    $stmt->execute([$_POST['leave_id']]);
                    $leave_request = $stmt->fetch();

                    // Update leave request status
                    $stmt = $pdo->prepare("
                        UPDATE leave_requests 
                        SET status = ?, admin_remarks = ?, approved_by = ? 
                        WHERE leave_id = ?
                    ");
                    $stmt->execute([
                        $_POST['status'],
                        $_POST['admin_remarks'],
                        $_SESSION['user_id'],
                        $_POST['leave_id']
                    ]);

                    // If approved, update employee leave balance
                    if ($_POST['status'] === 'approved') {
                        // Calculate days
                        $start = new DateTime($leave_request['start_date']);
                        $end = new DateTime($leave_request['end_date']);
                        $interval = $start->diff($end);
                        $days = $interval->days + 1; // +1 to include both start and end dates

                        // Check if employee already has a record for this leave type and year
                        $stmt = $pdo->prepare("
                            SELECT * FROM employee_leave_balance 
                            WHERE emp_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)
                        ");
                        $stmt->execute([$leave_request['emp_id'], $leave_request['leave_type_id']]);
                        $balance = $stmt->fetch();

                        if ($balance) {
                            // Update existing record
                            $stmt = $pdo->prepare("
                                UPDATE employee_leave_balance 
                                SET used_leaves = used_leaves + ? 
                                WHERE emp_id = ? AND leave_type_id = ? AND year = YEAR(CURRENT_DATE)
                            ");
                            $stmt->execute([$days, $leave_request['emp_id'], $leave_request['leave_type_id']]);
                        } else {
                            // Create new record
                            $stmt = $pdo->prepare("
                                INSERT INTO employee_leave_balance (emp_id, leave_type_id, year, used_leaves) 
                                VALUES (?, ?, YEAR(CURRENT_DATE), ?)
                            ");
                            $stmt->execute([$leave_request['emp_id'], $leave_request['leave_type_id'], $days]);
                        }
                    }

                    // Log the action
                    $stmt = $pdo->prepare("
                        INSERT INTO activity_logs (user_id, activity_type, description) 
                        VALUES (?, 'leave_update', ?)
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        "Updated leave request #" . $_POST['leave_id'] . " status to " . $_POST['status']
                    ]);

                    $pdo->commit();
                    $success = "Leave request status updated successfully!";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Error updating leave request: " . $e->getMessage();
                }
                break;

            case 'delete':
                try {
                    $stmt = $pdo->prepare("DELETE FROM leave_requests WHERE leave_id = ?");
                    $stmt->execute([$_POST['leave_id']]);
                    $success = "Leave request deleted successfully!";
                } catch (PDOException $e) {
                    $error = "Error deleting leave request: " . $e->getMessage();
                }
                break;
        }
    }
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

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$department = isset($_GET['department']) ? $_GET['department'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Fetch leave requests with employee details
try {
    $query = "
        SELECT lr.*, 
               CONCAT(e.first_name, ' ', e.last_name) as employee_name,
               d.dept_name,
               e.position,
               lt.leave_type_name,
               lt.default_days,
               COALESCE(elb.used_leaves, 0) as used_leaves,
               u.username as approved_by_name
        FROM leave_requests lr
        JOIN employees e ON lr.emp_id = e.emp_id
        LEFT JOIN departments d ON e.dept_id = d.dept_id
        JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
        LEFT JOIN employee_leave_balance elb ON lt.leave_type_id = elb.leave_type_id 
            AND elb.emp_id = e.emp_id AND elb.year = YEAR(CURRENT_DATE)
        LEFT JOIN users u ON lr.approved_by = u.user_id
        WHERE lr.start_date BETWEEN ? AND ?
    ";
    
    $params = [$start_date, $end_date];
    
    if ($status) {
        $query .= " AND lr.status = ?";
        $params[] = $status;
    }
    
    if ($department) {
        $query .= " AND d.dept_id = ?";
        $params[] = $department;
    }
    
    $query .= " ORDER BY lr.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $leave_requests = $stmt->fetchAll();

    // Fetch departments for filter
    $stmt = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name");
    $departments = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
                        <h1 class="fw-bold mb-1" style="font-size:2rem; color:#222;">Leave Management</h1>
                        <div class="text-muted" style="font-size:1.1rem;">Manage and track employee leave requests</div>
                    </div>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Form -->
            <div class="card mb-4">
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
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Leave Requests Table -->
            <div class="card">
                <div class="card-body">
                    <table id="leaveTable" class="table table-striped">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Leave Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leave_requests as $leave): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($leave['employee_name']); ?></td>
                                <td><?php echo htmlspecialchars($leave['dept_name']); ?></td>
                                <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></td>
                                <td><?php echo (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / (60 * 60 * 24) + 1; ?></td>
                                <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo getLeaveStatusBadgeClass($leave['status']); ?>">
                                        <?php echo ucfirst($leave['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" 
                                            onclick="viewLeave(<?php echo htmlspecialchars(json_encode($leave)); ?>)"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($leave['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-success" 
                                            onclick="updateLeaveStatus(<?php echo $leave['leave_id']; ?>, 'approved', '<?php echo htmlspecialchars($leave['employee_name']); ?>')"
                                            title="Approve Leave">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" 
                                            onclick="updateLeaveStatus(<?php echo $leave['leave_id']; ?>, 'rejected', '<?php echo htmlspecialchars($leave['employee_name']); ?>')"
                                            title="Reject Leave">
                                        <i class="fas fa-times"></i>
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
    </div>

    <!-- View Leave Modal -->
    <div class="modal fade" id="viewLeaveModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Leave Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Employee:</strong> <span id="view_employee"></span></p>
                            <p><strong>Department:</strong> <span id="view_department"></span></p>
                            <p><strong>Position:</strong> <span id="view_position"></span></p>
                            <p><strong>Leave Type:</strong> <span id="view_leave_type"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Start Date:</strong> <span id="view_start_date"></span></p>
                            <p><strong>End Date:</strong> <span id="view_end_date"></span></p>
                            <p><strong>Status:</strong> <span id="view_status"></span></p>
                            <p><strong>Applied On:</strong> <span id="view_applied_date"></span></p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p><strong>Reason:</strong></p>
                        <p id="view_reason"></p>
                    </div>
                    <div class="mt-3">
                        <p><strong>Admin Remarks:</strong></p>
                        <p id="view_admin_remarks"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Leave Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="leave_id" id="update_leave_id">
                        <input type="hidden" name="status" id="update_status">
                        
                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <input type="text" class="form-control" id="update_employee_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <input type="text" class="form-control" id="update_status_display" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Remarks</label>
                            <textarea class="form-control" name="admin_remarks" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add SweetAlert2 feedback after main content -->
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#leaveTable').DataTable({
                order: [[3, 'desc']],
                responsive: true,
                pageLength: 10,
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
        });

        // View Leave Details
        function viewLeave(leave) {
            document.getElementById('view_employee').textContent = leave.employee_name;
            document.getElementById('view_department').textContent = leave.dept_name;
            document.getElementById('view_position').textContent = leave.position;
            document.getElementById('view_leave_type').textContent = leave.leave_type_name;
            document.getElementById('view_start_date').textContent = new Date(leave.start_date).toLocaleDateString();
            document.getElementById('view_end_date').textContent = new Date(leave.end_date).toLocaleDateString();
            document.getElementById('view_status').textContent = leave.status.charAt(0).toUpperCase() + leave.status.slice(1);
            document.getElementById('view_applied_date').textContent = new Date(leave.created_at).toLocaleString();
            document.getElementById('view_reason').textContent = leave.reason;
            document.getElementById('view_admin_remarks').textContent = leave.admin_remarks || 'No remarks';
            new bootstrap.Modal(document.getElementById('viewLeaveModal')).show();
        }

        // Update Leave Status
        function updateLeaveStatus(leaveId, status, employeeName) {
            document.getElementById('update_leave_id').value = leaveId;
            document.getElementById('update_status').value = status;
            document.getElementById('update_employee_name').value = employeeName;
            document.getElementById('update_status_display').value = status.charAt(0).toUpperCase() + status.slice(1);
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }

        // Sidebar Toggle
        document.getElementById('toggle-sidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('main-content').classList.toggle('expanded');
            document.querySelector('.topbar').classList.toggle('expanded');
        });
    </script>
</body>
</html> 