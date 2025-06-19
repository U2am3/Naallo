<?php
session_start();
require_once '../../config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login/admin.php");
    exit();
}

// Handle department actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Check if department name already exists (case-insensitive)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE LOWER(dept_name) = LOWER(?)");
                    $stmt->execute([$_POST['dept_name']]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('A department with this name already exists.');
                    }

                    // Check if manager is already assigned to another department
                    if (!empty($_POST['dept_head'])) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE dept_head = ?");
                        $stmt->execute([$_POST['dept_head']]);
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception('This manager is already assigned to another department.');
                        }
                    }
                    $stmt = $pdo->prepare("INSERT INTO departments (dept_name, dept_head) VALUES (?, ?)");
                    $stmt->execute([$_POST['dept_name'], $_POST['dept_head']]);
                    $success = "Department added successfully!";
                } catch (PDOException $e) {
                    $error = "Error adding department: " . $e->getMessage();
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;

            case 'edit':
                try {
                    // Check if department name already exists for another department (case-insensitive)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE LOWER(dept_name) = LOWER(?) AND dept_id != ?");
                    $stmt->execute([$_POST['dept_name'], $_POST['dept_id']]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('A department with this name already exists.');
                    }

                    // Check if manager is already assigned to another department (excluding this department)
                    if (!empty($_POST['dept_head'])) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE dept_head = ? AND dept_id != ?");
                        $stmt->execute([$_POST['dept_head'], $_POST['dept_id']]);
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception('This manager is already assigned to another department.');
                        }
                    }
                    $stmt = $pdo->prepare("UPDATE departments SET dept_name = ?, dept_head = ? WHERE dept_id = ?");
                    $stmt->execute([$_POST['dept_name'], $_POST['dept_head'], $_POST['dept_id']]);
                    $success = "Department updated successfully!";
                } catch (PDOException $e) {
                    $error = "Error updating department: " . $e->getMessage();
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;

            case 'delete':
                try {
                    // First check if department has employees
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE dept_id = ?");
                    $stmt->execute([$_POST['dept_id']]);
                    $count = $stmt->fetchColumn();

                    if ($count > 0) {
                        $error = "Cannot delete department with assigned employees!";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM departments WHERE dept_id = ?");
                        $stmt->execute([$_POST['dept_id']]);
                        $success = "Department deleted successfully!";
                    }
                } catch (PDOException $e) {
                    $error = "Error deleting department: " . $e->getMessage();
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

// Fetch all departments with department head names
try {
    $stmt = $pdo->query("
        SELECT d.*, CONCAT(e.first_name, ' ', e.last_name) as head_name 
        FROM departments d 
        LEFT JOIN users u ON d.dept_head = u.user_id 
        LEFT JOIN employees e ON u.user_id = e.user_id 
        ORDER BY d.dept_name
    ");
    $departments = $stmt->fetchAll();

    // Fetch all managers for department head selection
    $stmt = $pdo->query("
        SELECT u.user_id, CONCAT(e.first_name, ' ', e.last_name) as full_name 
        FROM users u 
        JOIN employees e ON u.user_id = e.user_id 
        WHERE u.role = 'manager' AND u.status = 'active'
        ORDER BY e.first_name
    ");
    $managers = $stmt->fetchAll();

    // Fetch unassigned managers
    $stmt = $pdo->query("
        SELECT u.user_id, CONCAT(e.first_name, ' ', e.last_name) as full_name 
        FROM users u 
        JOIN employees e ON u.user_id = e.user_id 
        WHERE u.role = 'manager' 
        AND u.status = 'active'
        AND u.user_id NOT IN (SELECT dept_head FROM departments WHERE dept_head IS NOT NULL)
        ORDER BY e.first_name
    ");
    $unassigned_managers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Management - Admin Dashboard</title>
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
        .stat-card.departments {
            background: linear-gradient(45deg, var(--primary-color), #6f8de3);
        }
        .stat-card.unassigned {
            background: linear-gradient(45deg, var(--warning-color), #f8d06b);
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
        .modern-card {
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
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="dashboard-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-0">Department Management</h1>
                        <p class="text-muted mb-0">Manage all company departments and department heads</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                        <i class="fas fa-plus"></i> Add New Department
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
            <!-- Departments Table -->
            <div class="modern-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">Departments</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="departmentsTable" class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Department Name</th>
                                    <th>Department Head</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><?php echo $dept['dept_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($dept['dept_name']); ?></strong></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="team-avatar">
                                                <?php
                                                // Attempt to get head profile image and names
                                                $headImg = null;
                                                $headFirst = '';
                                                $headLast = '';
                                                if (!empty($dept['dept_head'])) {
                                                    $stmtHead = $pdo->prepare("SELECT e.profile_image, e.first_name, e.last_name FROM employees e JOIN users u ON e.user_id = u.user_id WHERE u.user_id = ?");
                                                    $stmtHead->execute([$dept['dept_head']]);
                                                    $headRow = $stmtHead->fetch();
                                                    if ($headRow) {
                                                        $headImg = $headRow['profile_image'] ?? null;
                                                        $headFirst = $headRow['first_name'] ?? '';
                                                        $headLast = $headRow['last_name'] ?? '';
                                                    }
                                                }
                                                if ($headImg) {
                                                    echo '<img src="../../uploads/profile_photos/' . htmlspecialchars($headImg) . '" alt="Profile" />';
                                                } elseif ($headFirst || $headLast) {
                                                    $initials = strtoupper(substr($headFirst, 0, 1) . substr($headLast, 0, 1));
                                                    echo $initials;
                                                } else {
                                                    echo 'NA';
                                                }
                                                ?>
                                            </div>
                                            <span>
                                                <?php
                                                if ($headFirst || $headLast) {
                                                    echo htmlspecialchars(trim($headFirst . ' ' . $headLast));
                                                } else {
                                                    echo 'NA';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($dept['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" title="Edit" onclick="editDepartment(<?php echo htmlspecialchars(json_encode($dept)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" title="Delete" onclick="deleteDepartment(<?php echo $dept['dept_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Unassigned Managers Section -->
            <div class="modern-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Unassigned Department Managers</h5>
                </div>
                <div class="card-body">
                    <?php if (count($unassigned_managers) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Manager Name</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unassigned_managers as $manager): ?>
                                    <tr>
                                        <td>
    <div class="d-flex align-items-center gap-2">
        <div class="team-avatar">
            <?php
            $img = null;
            $first = '';
            $last = '';
            if (!empty($manager['user_id'])) {
                $stmtImg = $pdo->prepare("SELECT profile_image, first_name, last_name FROM employees WHERE user_id = ?");
                $stmtImg->execute([$manager['user_id']]);
                $imgRow = $stmtImg->fetch();
                if ($imgRow) {
                    $img = $imgRow['profile_image'] ?? null;
                    $first = $imgRow['first_name'] ?? '';
                    $last = $imgRow['last_name'] ?? '';
                }
            }
            if ($img) {
                echo '<img src="../../uploads/profile_photos/' . htmlspecialchars($img) . '" alt="Profile" />';
            } elseif ($first || $last) {
                $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
                echo $initials;
            } else {
                echo 'NA';
            }
            ?>
        </div>
        <span>
            <?php
            if ($first || $last) {
                echo htmlspecialchars(trim($first . ' ' . $last));
            } else {
                echo 'NA';
            }
            ?>
        </span>
    </div>
</td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDeptModal" 
                                                onclick="preSelectManager('<?php echo $manager['user_id']; ?>')">
                                                <i class="fas fa-plus"></i> Assign to Department
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">All managers are currently assigned to departments.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal fade" id="addDeptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Department Name</label>
                            <input type="text" class="form-control" name="dept_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Head</label>
                            <select class="form-select" name="dept_head">
                                <option value="">Select Department Head</option>
                                <?php foreach ($managers as $manager): ?>
                                    <option value="<?php echo $manager['user_id']; ?>">
                                        <?php echo htmlspecialchars($manager['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div class="modal fade" id="editDeptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="dept_id" id="edit_dept_id">
                        <div class="mb-3">
                            <label class="form-label">Department Name</label>
                            <input type="text" class="form-control" name="dept_name" id="edit_dept_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Head</label>
                            <select class="form-select" name="dept_head" id="edit_dept_head">
                                <option value="">Select Department Head</option>
                                <?php foreach ($managers as $manager): ?>
                                    <option value="<?php echo $manager['user_id']; ?>">
                                        <?php echo htmlspecialchars($manager['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Department Modal -->
    <div class="modal fade" id="deleteDeptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="dept_id" id="delete_dept_id">
                        <p>Are you sure you want to delete this department? This action cannot be undone.</p>
                        <p class="text-danger">Note: Departments with assigned employees cannot be deleted.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
            $('#departmentsTable').DataTable();
        });

        // Pre-select manager in Add Department modal
        function preSelectManager(managerId) {
            document.querySelector('#addDeptModal select[name="dept_head"]').value = managerId;
        }

        // Sidebar Toggle
        document.getElementById('toggle-sidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('main-content').classList.toggle('expanded');
            document.querySelector('.topbar').classList.toggle('expanded');
        });

        // Edit Department
        function editDepartment(dept) {
            document.getElementById('edit_dept_id').value = dept.dept_id;
            document.getElementById('edit_dept_name').value = dept.dept_name;
            document.getElementById('edit_dept_head').value = dept.dept_head || '';
            new bootstrap.Modal(document.getElementById('editDeptModal')).show();
        }

        // Delete Department
        function deleteDepartment(deptId) {
            document.getElementById('delete_dept_id').value = deptId;
            new bootstrap.Modal(document.getElementById('deleteDeptModal')).show();
        }
    </script>
</body>
</html> 