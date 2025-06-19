<?php
session_start();
require_once '../../config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login/admin.php");
    exit();
}

// Handle date filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$activity_type = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';

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
    // Build the query
    $query = "
        SELECT al.*, 
               u.username,
               CONCAT(e.first_name, ' ', e.last_name) as full_name,
               u.role
        FROM activity_logs al
        JOIN users u ON al.user_id = u.user_id
        LEFT JOIN employees e ON u.user_id = e.user_id
        WHERE DATE(al.created_at) BETWEEN ? AND ?
    ";
    
    $params = [$start_date, $end_date];

    if ($activity_type) {
        $query .= " AND al.activity_type = ?";
        $params[] = $activity_type;
    }

    if ($user_id) {
        $query .= " AND al.user_id = ?";
        $params[] = $user_id;
    }

    $query .= " ORDER BY al.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Get unique activity types for filter
    $stmt = $pdo->query("SELECT DISTINCT activity_type FROM activity_logs ORDER BY activity_type");
    $activity_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get users for filter
    $stmt = $pdo->query("
        SELECT u.user_id, u.username, CONCAT(e.first_name, ' ', e.last_name) as full_name
        FROM users u
        LEFT JOIN employees e ON u.user_id = e.user_id
        ORDER BY u.username
    ");
    $users = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching logs: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
        .card-header h6, .card-header h2, .card-header h5 {
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }
        .table-responsive {
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
        .table tbody tr:hover {
            background-color: #f8f9fc;
        }
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
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
        .alert {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>

        <div class="container-fluid">
            <h2 class="mb-4">Activity Logs</h2>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold">Filter Activity Logs</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Activity Type</label>
                            <select class="form-select" name="activity_type">
                                <option value="">All Types</option>
                                <?php foreach ($activity_types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $activity_type === $type ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">User</label>
                            <select class="form-select" name="user_id">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>" <?php echo $user_id == $user['user_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username'] . ' (' . $user['full_name'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold">Activity Logs</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="logsTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Activity Type</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($log['username']); ?>
                                        <?php if ($log['full_name']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($log['full_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getRoleBadgeClass($log['role']); ?>">
                                            <?php echo ucfirst($log['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getActivityTypeBadgeClass($log['activity_type']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $log['activity_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['description']); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
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
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable with export buttons
            $('#logsTable').DataTable({
                dom: '<"d-flex justify-content-between"B<"d-flex align-items-center"l<"ms-2"f>>>rtip',
                buttons: [
                    {
                        extend: 'collection',
                        text: '<i class="fas fa-download"></i> Export',
                        buttons: [
                            {
                                extend: 'csv',
                                text: '<i class="fas fa-file-csv"></i> CSV',
                                className: 'btn-sm'
                            },
                            {
                                extend: 'excel',
                                text: '<i class="fas fa-file-excel"></i> Excel',
                                className: 'btn-sm'
                            },
                            {
                                extend: 'pdf',
                                text: '<i class="fas fa-file-pdf"></i> PDF',
                                className: 'btn-sm'
                            },
                            {
                                extend: 'print',
                                text: '<i class="fas fa-print"></i> Print',
                                className: 'btn-sm'
                            }
                        ],
                        className: 'btn btn-primary btn-sm'
                    }
                ],
                order: [[0, 'desc']],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                responsive: true,
                language: {
                    search: "<i class='fas fa-search'></i>",
                    searchPlaceholder: "Search logs..."
                }
            });

            // Initialize date pickers
            flatpickr('input[type="date"]', {
                dateFormat: 'Y-m-d',
                maxDate: 'today'
            });

            // Sidebar Toggle
            $('#toggle-sidebar').on('click', function(e) {
                e.preventDefault();
                $('.sidebar').toggleClass('collapsed');
                $('.main-content').toggleClass('expanded');
                $('.topbar').toggleClass('expanded');
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