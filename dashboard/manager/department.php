<?php
ob_start();
session_start();
require_once '../../config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../../login/manager.php");
    exit();
}

// Get manager details and department
$stmt = $pdo->prepare("
    SELECT e.*, d.dept_id, d.dept_name
    FROM employees e
    LEFT JOIN departments d ON e.dept_id = d.dept_id
    WHERE e.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$manager = $stmt->fetch();

$dept_id = $manager['dept_id'];

// Filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "SELECT e.*, u.email, u.status FROM employees e JOIN users u ON e.user_id = u.user_id WHERE e.dept_id = ?";
$params = [$dept_id];
if ($filter && $filter !== 'all') {
    $query .= " AND u.status = ?";
    $params[] = $filter;
}
if ($search) {
    $query .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.position LIKE ? OR u.email LIKE ? OR e.emp_id LIKE ?)";
    $params = array_merge($params, array_fill(0, 5, "%$search%"));
}
$query .= " ORDER BY e.first_name, e.last_name";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

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
    <title>Department Employees - Manager Dashboard</title>
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
        .dashboard-header, .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        .main-content {
            margin-left: 260px;
            padding-top: 70px;
            min-height: 100vh;
            background: #f8f9fc;
            transition: all 0.3s ease;
        }
        .content {
            margin-top: 0;
            padding: 2rem 1rem 1rem 1rem;
        }
        .employee-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 2rem;
        }
        .team-card {
            background: #fff;
            border-radius: 0.75rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 2rem 1.5rem 1.5rem 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: box-shadow 0.3s;
        }
        .team-card:hover {
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.15);
        }
        .team-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #f8f9fc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .team-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .team-card .name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        .team-card .position {
            font-size: 0.95rem;
            color: #6c757d;
        }
        .team-card .email {
            font-size: 0.9rem;
            color: #858796;
            margin-bottom: 0.5rem;
        }
        .badge-success { background: #24B47E; color: #fff; }
        .badge-warning { background: #FFB648; color: #fff; }
        .badge-secondary { background: #6c757d; color: #fff; }
        .badge-light { background: #f3f4f6; color: #22223b; }
        @media (max-width: 767.98px) {
            .employee-cards-grid { grid-template-columns: 1fr; }
            .content { padding: 1rem 0.5rem; }
            .main-content { margin-left: 0; padding-top: 60px; }
        }
    </style>
<!-- Sidebar will be included as on other manager pages -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Employees - Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary-blue: #4763E4;
        --success-green: #24B47E;
        --warning-yellow: #FFB648;
        --danger-red: #FF6B6B;
        --background-color: #F8F9FE;
        --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        --transition: all 0.3s ease;
    }
    body {
        background-color: var(--background-color);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        margin: 0;
        padding: 0;
    }
    .main-content {
        margin-left: 260px;
        min-height: 100vh;
        background: var(--background-color);
        transition: var(--transition);
    }
    .content {
        margin-top: 70px;
        padding: 2rem 1rem 1rem 1rem;
    }
    .filter-bar {
        padding: 1rem 1.5rem 0.5rem 1.5rem;
        background: transparent;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
    }
    .filter-bar .form-select, .filter-bar .form-control {
        min-width: 180px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }
    .employee-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    .team-card {
        background: #fff;
        border-radius: 14px;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
        border: 1px solid rgba(71, 99, 228, 0.1);
        box-shadow: 0 4px 20px rgba(71,99,228,0.07);
        min-height: 110px;
    }
    .team-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(71, 99, 228, 0.13);
        border-color: rgba(71, 99, 228, 0.2);
    }
    .team-avatar {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--primary-blue), #6282FF);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1.3rem;
        box-shadow: 0 4px 10px rgba(71, 99, 228, 0.13);
        overflow: hidden;
    }
    .team-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 14px;
    }
    .team-info {
        flex: 1;
        min-width: 0;
    }
    .team-name {
        font-weight: 600;
        color: #111827;
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 1.1rem;
    }
    .team-position {
        font-size: 0.95rem;
        color: #6B7280;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 2px;
    }
    .team-email {
        font-size: 0.95rem;
        color: #6B7280;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 2px;
    }
    .team-status {
        font-size: 0.85rem;
        font-weight: 500;
        display: inline-block;
        margin-top: 2px;
    }
    .badge-success { background: #24B47E; color: #fff; }
    .badge-warning { background: #FFB648; color: #fff; }
    .badge-secondary { background: #6c757d; color: #fff; }
    .badge-light { background: #f3f4f6; color: #22223b; }
    .page-header {
        background: white;
        padding: 1.5rem;
        border-radius: 0.75rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        margin-bottom: 1.5rem;
    }
    @media (max-width: 767.98px) {
        .employee-cards-grid { grid-template-columns: 1fr; }
        .content { padding: 1rem 0.5rem; }
    }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content" id="main-content" style="margin-left:260px; padding-top:70px; min-height:100vh; background:#f8f9fc;">
    <?php include 'includes/topbar.php'; ?>
    <div class="content container-fluid py-4">
        <div class="page-header mb-4">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="mb-0"><i class=""></i>Team</h1>
                </div>
            </div>
        </div>
        <!-- Filter Bar -->
        <form class="filter-bar row g-2 w-100" method="GET">
            <div class="col-md-4 col-12">
                <input type="text" class="form-control" name="search" placeholder="Search by name, email, position..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2 col-6">
                <select class="form-select" name="filter">
                    <option value="all" <?php if ($filter === 'all') echo 'selected'; ?>>All Status</option>
                    <option value="active" <?php if ($filter === 'active') echo 'selected'; ?>>Active</option>
                    <option value="on leave" <?php if ($filter === 'on leave') echo 'selected'; ?>>On Leave</option>
                    <option value="inactive" <?php if ($filter === 'inactive') echo 'selected'; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <button class="btn btn-primary w-100" type="submit"><i class="fa fa-search me-1"></i>Filter</button>
            </div>
        </form>
        <!-- Employee Cards Grid -->
        <div class="employee-cards-grid">
            <?php foreach ($employees as $emp): ?>
            <div class="team-card">
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
                <div class="team-info">
                    <div class="team-name"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></div>
                    <div class="team-position"><i class="fa fa-briefcase me-1"></i><?php echo htmlspecialchars($emp['position']); ?></div>
                    <div class="team-email"><i class="fa fa-envelope me-1"></i><?php echo htmlspecialchars($emp['email']); ?></div>
                    <div class="team-status">
                        <?php
                        switch (strtolower($emp['status'])) {
                            case 'active':
                                echo '<span class="badge badge-success">Active</span>';
                                break;
                            case 'on leave':
                                echo '<span class="badge badge-warning">On Leave</span>';
                                break;
                            case 'inactive':
                                echo '<span class="badge badge-secondary">Inactive</span>';
                                break;
                            default:
                                echo '<span class="badge badge-light">' . htmlspecialchars(ucfirst($emp['status'])) . '</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($employees)): ?>
            <div class="col-12 text-center text-muted">No employees found for this department.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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