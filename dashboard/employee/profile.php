<?php
session_start();
require_once '../../config/database.php';
require_once 'includes/functions.php';

// Create upload directory if it doesn't exist
$upload_dir = __DIR__ . '/../../uploads/profile_photos';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../login/employee.php");
    exit();
}

// Get employee details
try {
    $stmt = $pdo->prepare("
        SELECT 
            e.first_name,
            e.last_name,
            e.phone,
            e.address,
            e.profile_image,
            e.hire_date,
            e.emp_id,
            d.dept_name,
            u.email,
            u.username,
            u.role
        FROM employees e
        LEFT JOIN departments d ON e.dept_id = d.dept_id
        INNER JOIN users u ON e.user_id = u.user_id
        WHERE e.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug line - remove in production
    if (!$employee) {
        die("No employee data found");
    }
} catch (PDOException $e) {
    die("Error fetching employee data: " . $e->getMessage());
}

// Handle profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        // Validate inputs
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error_message = "First name, last name, and email are required fields.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Update employee table
                $stmt = $pdo->prepare("
                    UPDATE employees 
                    SET first_name = ?, 
                        last_name = ?, 
                        phone = ?, 
                        address = ?
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
                
                // Refresh employee data
                $stmt = $pdo->prepare("
                    SELECT 
                        e.first_name,
                        e.last_name,
                        e.phone,
                        e.address,
                        e.profile_image,
                        e.hire_date,
                        e.emp_id,
                        d.dept_name,
                        u.email,
                        u.username,
                        u.role
                    FROM employees e
                    LEFT JOIN departments d ON e.dept_id = d.dept_id
                    INNER JOIN users u ON e.user_id = u.user_id
                    WHERE e.user_id = ?
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = "Error updating profile: " . $e->getMessage();
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
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
                
                if (password_verify($current_password, $user['password'])) {
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
    <title>Profile - Employee Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .profile-header {
            background-color: #f8f9fa;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .profile-card {
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .profile-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        .profile-info-item {
            margin-bottom: 1rem;
        }
        .profile-info-label {
            font-weight: 600;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <?php include 'includes/topbar.php'; ?>

            <!-- Page Content -->
            <div class="container-fluid">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <?php 
                                $profile_image_path = !empty($employee['profile_image'])
                                    ? '../../uploads/profile_photos/' . htmlspecialchars($employee['profile_image'])
                                    : '../../assets/images/default-profile.png';
                                ?>
                                <img src="<?php echo $profile_image_path; ?>" 
                                     class="profile-image mb-3"
                                     onerror="this.src='../../assets/images/default-profile.png';">
                            </div>
                            <div class="col-md-9">
                                <h1 class="mb-2"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h1>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-id-badge me-2"></i> Employee ID: <?php echo htmlspecialchars($employee['emp_id']); ?>
                                </p>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-building me-2"></i> Department: <?php echo htmlspecialchars($employee['dept_name'] ?? 'Not Assigned'); ?>
                                </p>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-calendar-alt me-2"></i> Joined: <?php echo date('F d, Y', strtotime($employee['hire_date'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="container">
                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-lg-6">
                            <div class="card profile-card">
                                <div class="card-header">
                                    <i class="fas fa-user me-2"></i> Personal Information
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="" enctype="multipart/form-data">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="first_name" class="form-label">First Name</label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="last_name" class="form-label">Last Name</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Phone</label>
                                            <input type="text" class="form-control" id="phone" name="phone" 
                                                value="<?php echo isset($employee['phone']) ? htmlspecialchars($employee['phone']) : ''; ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="profile_image" class="form-label">Profile Image</label>
                                            <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/jpeg,image/png">
                                            <small class="text-muted">Allowed formats: JPG, JPEG, PNG. Max size: 2MB</small>
                                        </div>
                                        <button type="submit" name="update_profile" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Update Profile
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="col-lg-6">
                            <div class="card profile-card">
                                <div class="card-header">
                                    <i class="fas fa-lock me-2"></i> Change Password
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                        <button type="submit" name="change_password" class="btn btn-primary">
                                            <i class="fas fa-key me-2"></i> Change Password
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script>
        // Toggle sidebar
        document.getElementById('toggle-sidebar').addEventListener('click', function() {
            document.querySelector('.wrapper').classList.toggle('sidebar-collapsed');
        });
    </script>
</body>
</html> 