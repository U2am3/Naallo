<?php
session_start();
require_once '../../config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login/admin.php");
    exit();
}

// Handle manager actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Begin transaction
                    $pdo->beginTransaction();

                    // First create user account
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, 'manager', 'active')");
                    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt->execute([$_POST['username'], $hashedPassword, $_POST['email']]);
                    $userId = $pdo->lastInsertId();

                    // Then create employee record
                    $stmt = $pdo->prepare("INSERT INTO employees (user_id, first_name, last_name, phone, hire_date, basic_salary, gender) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $userId,
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['phone'],
                        $_POST['hire_date'],
                        $_POST['basic_salary'],
                        $_POST['gender']
                    ]);

                    $pdo->commit();
                    $success = "Manager added successfully!";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Error adding manager: " . $e->getMessage();
                }
                break;

            case 'edit':
                try {
                    $pdo->beginTransaction();

                    // Update employee information
                    $stmt = $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ?, phone = ?, hire_date = ?, basic_salary = ?, gender = ? WHERE user_id = ?");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['phone'],
                        $_POST['hire_date'],
                        $_POST['basic_salary'],
                        $_POST['gender'],
                        $_POST['user_id']
                    ]);

                    // Update username and email if changed
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
                    $stmt->execute([$_POST['username'], $_POST['email'], $_POST['user_id']]);

                    // Update password if provided
                    if (!empty($_POST['password'])) {
                        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $stmt->execute([$hashedPassword, $_POST['user_id']]);
                    }

                    $pdo->commit();
                    $success = "Manager updated successfully!";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Error updating manager: " . $e->getMessage();
                }
                break;

            case 'delete':
                try {
                    // Check if manager is assigned to any department
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE dept_head = ?");
                    $stmt->execute([$_POST['user_id']]);
                    $count = $stmt->fetchColumn();

                    if ($count > 0) {
                        $error = "Cannot delete manager who is assigned as department head!";
                    } else {
                        $pdo->beginTransaction();

                        // Set user status to inactive instead of deleting
                        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
                        $stmt->execute([$_POST['user_id']]);

                        $pdo->commit();
                        $success = "Manager set to inactive successfully!";
                    }
                } catch (PDOException $e) {
                    if (isset($pdo)) $pdo->rollBack();
                    $error = "Error setting manager inactive: " . $e->getMessage();
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

// Fetch all managers
try {
    $stmt = $pdo->query("
        SELECT u.user_id, u.username, u.email, u.status,
               e.first_name, e.last_name, e.phone, e.hire_date, e.basic_salary, e.gender,
               d.dept_name
        FROM users u
        JOIN employees e ON u.user_id = e.user_id
        LEFT JOIN departments d ON d.dept_head = u.user_id
        WHERE u.role = 'manager' AND u.status = 'active'
        ORDER BY e.first_name, e.last_name
    ");
    $managers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
    $managers = []; // Initialize empty array if query fails
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Management - Admin Dashboard</title>
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
                        <h1 class="fw-bold mb-1" style="font-size:2rem; color:#222;">Manager Management</h1>
                        <div class="text-muted" style="font-size:1.1rem;">Manage all company managers and their details</div>
                    </div>
                    <button class="btn btn-primary fw-bold px-4 py-2 d-flex align-items-center" style="font-size:1rem; border-radius:8px; box-shadow:0 2px 8px rgba(78,115,223,0.08);" data-bs-toggle="modal" data-bs-target="#addManagerModal">
                        <i class="fas fa-plus me-2"></i> ADD NEW MANAGER
                    </button>
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

            <!-- Managers Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="managersTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Profile</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Gender</th>
                                    <th>Department</th>
                                    <th>Hire Date</th>
                                    <th>Basic Salary</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($managers as $manager): ?>
                                <tr>
                                    <td><?php echo $manager['user_id']; ?></td>
                                    <td>
                                        <?php
                                        // Try to get profile image from employees table if available
                                        $img = null;
                                        try {
                                            $stmtImg = $pdo->prepare("SELECT profile_image FROM employees WHERE user_id = ?");
                                            $stmtImg->execute([$manager['user_id']]);
                                            $imgRow = $stmtImg->fetch();
                                            $img = $imgRow && !empty($imgRow['profile_image']) ? $imgRow['profile_image'] : null;
                                        } catch (Exception $e) { $img = null; }
                                        ?>
                                        <div class="team-avatar">
    <?php if ($img): ?>
        <img src="../../uploads/profile_photos/<?php echo htmlspecialchars($img); ?>" alt="Profile" />
    <?php else: ?>
        <?php
        $initials = strtoupper(substr($manager['first_name'], 0, 1) . substr($manager['last_name'], 0, 1));
        echo $initials;
        ?>
    <?php endif; ?>
</div>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($manager['username']); ?></td>
                                    <td><?php echo htmlspecialchars($manager['email']); ?></td>
                                    <td><?php echo htmlspecialchars($manager['phone']); ?></td>
                                    <td><?php echo ucfirst($manager['gender']); ?></td>
                                    <td><?php echo htmlspecialchars($manager['dept_name'] ?? 'Not Assigned'); ?></td>
                                    <td class="text-nowrap"><?php echo date('Y-m-d', strtotime($manager['hire_date'])); ?></td>
                                    <td><?php echo number_format($manager['basic_salary'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $manager['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($manager['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-no-wrap align-items-center">
                                            <button class="btn btn-sm btn-outline-primary" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;" onclick='editManager(<?php echo json_encode($manager); ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;" onclick='viewManager(<?php echo json_encode($manager); ?>)'>
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;" onclick="deleteManager(<?php echo $manager['user_id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

    <!-- Add Manager Modal -->
    <div class="modal fade" id="addManagerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Manager</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" pattern="[0-9]{10,15}" title="Please enter a valid phone number (10-15 digits)" required>
                            <small class="text-muted">Enter phone number (10-15 digits)</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hire Date</label>
                            <input type="date" class="form-control" name="hire_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Basic Salary</label>
                            <input type="number" class="form-control" name="basic_salary" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Manager</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Manager Modal -->
    <div class="modal fade" id="editManagerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Manager</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender" id="edit_gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" id="edit_phone" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hire Date</label>
                            <input type="date" class="form-control" name="hire_date" id="edit_hire_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Basic Salary</label>
                            <input type="number" class="form-control" name="basic_salary" id="edit_basic_salary" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" id="edit_username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" name="password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Manager</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Manager Modal -->
    <div class="modal fade" id="deleteManagerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Manager</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <p>Are you sure you want to delete this manager? This action cannot be undone.</p>
                        <p class="text-danger">Note: Managers assigned as department heads cannot be deleted.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Manager</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Manager Modal -->
    <div class="modal fade" id="viewManagerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manager Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <span id="view_name"></span></p>
                            <p><strong>Email:</strong> <span id="view_email"></span></p>
                            <p><strong>Gender:</strong> <span id="view_gender"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Phone:</strong> <span id="view_phone"></span></p>
                            <p><strong>Hire Date:</strong> <span id="view_hire_date"></span></p>
                            <p><strong>Basic Salary:</strong> <span id="view_basic_salary"></span></p>
                        </div>
                    </div>
                </div>
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
            $('#managersTable').DataTable();
        });

        // Sidebar Toggle
        document.getElementById('toggle-sidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('main-content').classList.toggle('expanded');
            document.querySelector('.topbar').classList.toggle('expanded');
        });

        // Edit Manager
        function editManager(manager) {
            document.getElementById('edit_user_id').value = manager.user_id;
            document.getElementById('edit_first_name').value = manager.first_name;
            document.getElementById('edit_last_name').value = manager.last_name;
            document.getElementById('edit_email').value = manager.email;
            document.getElementById('edit_phone').value = manager.phone;
            document.getElementById('edit_hire_date').value = manager.hire_date.split(' ')[0];
            document.getElementById('edit_username').value = manager.username;
            document.getElementById('edit_basic_salary').value = manager.basic_salary;
            document.getElementById('edit_gender').value = manager.gender || '';
            new bootstrap.Modal(document.getElementById('editManagerModal')).show();
        }

        // Delete Manager
        function deleteManager(userId) {
            document.getElementById('delete_user_id').value = userId;
            new bootstrap.Modal(document.getElementById('deleteManagerModal')).show();
        }

        // View Manager
        function viewManager(manager) {
            document.getElementById('view_name').textContent = manager.first_name + ' ' + manager.last_name;
            document.getElementById('view_email').textContent = manager.email;
            document.getElementById('view_gender').textContent = manager.gender ? manager.gender.charAt(0).toUpperCase() + manager.gender.slice(1) : '';
            document.getElementById('view_phone').textContent = manager.phone;
            document.getElementById('view_hire_date').textContent = manager.hire_date.split(' ')[0];
            document.getElementById('view_basic_salary').textContent = manager.basic_salary;
            new bootstrap.Modal(document.getElementById('viewManagerModal')).show();
        }
    </script>
</body>
</html> 