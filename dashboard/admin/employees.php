<?php
session_start();
require_once '../../config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login/admin.php");
    exit();
}

// Handle employee actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $pdo->beginTransaction();

                    // First create user account
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, 'employee', 'active')");
                    $stmt->execute([$_POST['username'], $password, $_POST['email']]);
                    $userId = $pdo->lastInsertId();

                    // Then create employee record
                    $stmt = $pdo->prepare("INSERT INTO employees (user_id, first_name, last_name, dept_id, position, hire_date, basic_salary, phone, address, gender) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $userId,
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['dept_id'],
                        $_POST['position'],
                        $_POST['hire_date'],
                        $_POST['basic_salary'],
                        $_POST['phone'],
                        $_POST['address'],
                        $_POST['gender']
                    ]);

                    $pdo->commit();
                    $success = "Employee added successfully!";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Error adding employee: " . $e->getMessage();
                }
                break;

            case 'edit':
                try {
                    $pdo->beginTransaction();

                    // Update user account
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE user_id = ?");
                        $stmt->execute([$_POST['username'], $_POST['email'], $password, $_POST['user_id']]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
                        $stmt->execute([$_POST['username'], $_POST['email'], $_POST['user_id']]);
                    }

                    // Update employee record
                    $stmt = $pdo->prepare("UPDATE employees SET 
                        first_name = ?, 
                        last_name = ?, 
                        dept_id = ?, 
                        position = ?, 
                        hire_date = ?, 
                        basic_salary = ?, 
                        phone = ?, 
                        address = ?, 
                        gender = ?
                        WHERE user_id = ?");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['dept_id'],
                        $_POST['position'],
                        $_POST['hire_date'],
                        $_POST['basic_salary'],
                        $_POST['phone'],
                        $_POST['address'],
                        $_POST['gender'],
                        $_POST['user_id']
                    ]);

                    $pdo->commit();
                    $success = "Employee updated successfully!";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Error updating employee: " . $e->getMessage();
                }
                break;

            case 'delete':
                try {
                    $pdo->beginTransaction();

                    // Set user status to inactive instead of deleting
                    $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
                    $stmt->execute([$_POST['user_id']]);

                    $pdo->commit();
                    $success = "Employee set to inactive successfully!";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Error setting employee inactive: " . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch all employees with their department names (only role = 'employee')
try {
    $stmt = $pdo->query("
        SELECT e.*, u.username, u.email, u.role, d.dept_name 
        FROM employees e 
        JOIN users u ON e.user_id = u.user_id 
        LEFT JOIN departments d ON e.dept_id = d.dept_id 
        WHERE u.role = 'employee' AND u.status = 'active'
        ORDER BY e.first_name
    ");
    $employees = $stmt->fetchAll();

    // Fetch departments for dropdown
    $stmt = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
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
    <title>Employee Management - Admin Dashboard</title>
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
        .-no-wrap {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <?php include 'includes/topbar.php'; ?>

        <!-- Page Content -->
        <div class="container-fluid">
            <div class="dashboard-header mb-4" style="border-radius: 16px; box-shadow: 0 2px 16px rgba(30,34,90,0.07); padding: 2rem 2rem 1.5rem 2rem; background: #fff;">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div class="pe-3">
                        <h1 class="fw-bold mb-1" style="font-size:2rem; color:#222;">Employee Management</h1>
                        <div class="text-muted" style="font-size:1.1rem;">Manage all company employees and their details</div>
                    </div>
                    <button class="btn btn-primary fw-bold px-4 py-2 d-flex align-items-center" style="font-size:1rem; border-radius:8px; box-shadow:0 2px 8px rgba(78,115,223,0.08);" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                        <i class="fas fa-plus me-2"></i> ADD NEW EMPLOYEE
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

            <!-- Employees Table -->
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
    <label class="form-label">Filter by Department:</label>
    <select class="form-select" id="departmentFilter">
        <option value="">All Departments</option>
        <?php foreach ($departments as $dept): ?>
            <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['dept_name']); ?></option>
        <?php endforeach; ?>
    </select>
</div>
                    <table id="employeesTable" class="table table-striped">
                        <thead>
                            <tr>
    <th>ID</th>
    <th>Profile Image</th>
    <th>Name</th>
    <th>Department</th>
    <th>Position</th>
    <th>Gender</th>
    <th>Role</th>
    <th>Email</th>
    <th>Phone</th>
    <th>Hire Date</th>
    <th>Actions</th>
</tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
<tr>
    <td><?php echo $emp['emp_id']; ?></td>
    <td>
        <div class="team-avatar">
    <div class="team-avatar">
    <?php if (!empty($emp['profile_image'])): ?>
        <img src="../../uploads/profile_photos/<?php echo htmlspecialchars($emp['profile_image']); ?>" alt="Profile" />
    <?php else: ?>
        <?php
        $initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
        echo $initials;
        ?>
    <?php endif; ?>
</div>
</div>
    </td>
    <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($emp['dept_name'] ?? 'Not Assigned'); ?></td>
                                <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                <td><?php echo ucfirst($emp['gender']); ?></td>
                                <td><?php echo ucfirst($emp['role']); ?></td>
                                <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                <td><?php echo htmlspecialchars($emp['phone']); ?></td>
                                <td class="text-nowrap"><?php echo date('Y-m-d', strtotime($emp['hire_date'])); ?></td>
                                <td>
                                    <div class="d-flex gap-1 flex-no-wrap align-items-center">
                                        <button class="btn btn-sm btn-info" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;" onclick="viewEmployee(<?php echo htmlspecialchars(json_encode($emp)); ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;" onclick="editEmployee(<?php echo htmlspecialchars(json_encode($emp)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;" onclick="deleteEmployee(<?php echo $emp['user_id']; ?>)">
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

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Employee</h5>
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
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="dept_id" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['dept_id']; ?>">
                                            <?php echo htmlspecialchars($dept['dept_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-control" name="position" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Hire Date</label>
                                <input type="date" class="form-control" name="hire_date" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Basic Salary</label>
                                <input type="number" class="form-control" name="basic_salary" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" pattern="[0-9]{10,15}" title="Please enter a valid phone number (10-15 digits)" required>
                                <small class="text-muted">Enter phone number (10-15 digits)</small>
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
                            <div class="col-md-6 mb-3">
                                <!-- Empty column for alignment -->
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <!-- Same form fields as Add Employee Modal -->
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
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" id="edit_username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password (leave blank to keep current)</label>
                                <input type="password" class="form-control" name="password">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="dept_id" id="edit_dept_id" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['dept_id']; ?>">
                                            <?php echo htmlspecialchars($dept['dept_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-control" name="position" id="edit_position" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Hire Date</label>
                                <input type="date" class="form-control" name="hire_date" id="edit_hire_date" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Basic Salary</label>
                                <input type="number" class="form-control" name="basic_salary" id="edit_basic_salary" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" id="edit_phone" required>
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
                            <div class="col-md-6 mb-3">
                                <!-- Empty column for alignment -->
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="edit_address" rows="2" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Employee Modal -->
    <div class="modal fade" id="viewEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <span id="view_name"></span></p>
                            <p><strong>Email:</strong> <span id="view_email"></span></p>
                            <p><strong>Department:</strong> <span id="view_department"></span></p>
                            <p><strong>Position:</strong> <span id="view_position"></span></p>
                            <p><strong>Gender:</strong> <span id="view_gender"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Hire Date:</strong> <span id="view_hire_date"></span></p>
                            <p><strong>Phone:</strong> <span id="view_phone"></span></p>
                            <p><strong>Basic Salary:</strong> <span id="view_basic_salary"></span></p>
                            <p><strong>Address:</strong> <span id="view_address"></span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Employee Modal -->
    <div class="modal fade" id="deleteEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <p>Are you sure you want to delete this employee? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Employee</button>
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
        // Initialize DataTable with role filter
        $(document).ready(function() {
            var table = $('#employeesTable').DataTable();
            
            // Add role filter functionality
            $('#roleFilter').on('change', function() {
                var role = $(this).val();
                table.column(4).search(role).draw();
            });
        });

        // Sidebar Toggle
        document.getElementById('toggle-sidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('main-content').classList.toggle('expanded');
            document.querySelector('.topbar').classList.toggle('expanded');
        });

        // View Employee
        function viewEmployee(emp) {
            document.getElementById('view_name').textContent = emp.first_name + ' ' + emp.last_name;
            document.getElementById('view_email').textContent = emp.email;
            document.getElementById('view_department').textContent = emp.dept_name || 'Not Assigned';
            document.getElementById('view_position').textContent = emp.position;
            document.getElementById('view_gender').textContent = emp.gender ? emp.gender.charAt(0).toUpperCase() + emp.gender.slice(1) : '';
            document.getElementById('view_hire_date').textContent = emp.hire_date;
            document.getElementById('view_phone').textContent = emp.phone;
            document.getElementById('view_basic_salary').textContent = emp.basic_salary;
            document.getElementById('view_address').textContent = emp.address;
            new bootstrap.Modal(document.getElementById('viewEmployeeModal')).show();
        }

        // Edit Employee
        function editEmployee(emp) {
            document.getElementById('edit_user_id').value = emp.user_id;
            document.getElementById('edit_first_name').value = emp.first_name;
            document.getElementById('edit_last_name').value = emp.last_name;
            document.getElementById('edit_username').value = emp.username;
            document.getElementById('edit_email').value = emp.email;
            document.getElementById('edit_dept_id').value = emp.dept_id;
            document.getElementById('edit_position').value = emp.position;
            document.getElementById('edit_hire_date').value = emp.hire_date;
            document.getElementById('edit_basic_salary').value = emp.basic_salary;
            document.getElementById('edit_phone').value = emp.phone;
            document.getElementById('edit_address').value = emp.address;
            document.getElementById('edit_gender').value = emp.gender || '';
            new bootstrap.Modal(document.getElementById('editEmployeeModal')).show();
        }

        // Delete Employee
        function deleteEmployee(userId) {
            document.getElementById('delete_user_id').value = userId;
            new bootstrap.Modal(document.getElementById('deleteEmployeeModal')).show();
        }
    </script>
</body>
</html> 