<?php
session_start();
require_once '../../config/database.php';
require_once '../admin/includes/functions.php';

// Create upload directory if it doesn't exist
$upload_dir = __DIR__ . '/../../uploads/profile_photos';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Check if user is logged in and is manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../../login/manager.php");
    exit();
}

// Get manager details
$stmt = $pdo->prepare("
    SELECT e.*, d.dept_name, u.username, u.email, u.status
    FROM employees e
    LEFT JOIN departments d ON e.dept_id = d.dept_id
    JOIN users u ON e.user_id = u.user_id
    WHERE e.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$manager = $stmt->fetch();

// After fetching $manager
if (!$manager) {
    die("Manager not found or user data missing.");
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

                // Update employee information
                $stmt = $pdo->prepare("
                    UPDATE employees 
                    SET first_name = ?, last_name = ?, phone = ?, address = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$first_name, $last_name, $phone, $address, $_SESSION['user_id']]);

                // Update user email
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
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
                            $error_message = "Error uploading profile image. Please try again.";
                        }
                    } else {
                        $error_message = "Invalid file type. Only JPG, JPEG, and PNG files are allowed.";
                    }
                }

                $pdo->commit();
                $success_message = "Profile updated successfully!";
                
                // Refresh manager data
                $stmt = $pdo->prepare("
                    SELECT e.*, d.dept_name, u.username, u.email, u.status
                    FROM employees e
                    LEFT JOIN departments d ON e.dept_id = d.dept_id
                    JOIN users u ON e.user_id = u.user_id
                    WHERE e.user_id = ?
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $manager = $stmt->fetch();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = "Error updating profile: " . $e->getMessage();
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
    <title>Manager Profile - EMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .profile-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
        }
        .profile-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
        }
        .profile-avatar {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            border: 5px solid white;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        .nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .nav-tabs .nav-link:hover {
            color: #4e73df;
            background: rgba(78, 115, 223, 0.1);
        }
        .nav-tabs .nav-link.active {
            color: #4e73df;
            border-bottom: 3px solid #4e73df;
            background: none;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        .btn-primary {
            background: #4e73df;
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: #224abe;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
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
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <div class="row">
                <div class="col-md-4">
                    <div class="profile-card mb-4">
                        <div class="profile-header text-center">
                            <div class="profile-avatar mb-3">
                                <img src="<?php echo !empty($manager['profile_image']) ? '../../uploads/profile_photos/' . htmlspecialchars($manager['profile_image']) : '../../assets/images/default-profile.png'; ?>" onerror="this.src='../../assets/images/default-profile.png';" alt="Profile Image">
                            </div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']); ?></h4>
                            <p class="mb-0">Department Manager</p>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Phone</span>
                                <span><?php echo htmlspecialchars($manager['phone']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Department</span>
                                <span><?php echo htmlspecialchars($manager['dept_name'] ?? 'Not Assigned'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Status</span>
                                <span class="badge bg-success">Active</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="profile-card">
                        <div class="card-body">
                            <ul class="nav nav-tabs mb-4" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" data-bs-toggle="tab" href="#personal">Personal Information</a>
                                </li>
                                <!--
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#security">Security</a>
                                </li>
                                -->
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="personal">
                                    <form action="" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="update_profile" value="1">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">First Name</label>
                                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($manager['first_name']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Last Name</label>
                                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($manager['last_name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Email</label>
                                                <input type="email" class="form-control" name="email" value="<?php echo isset($manager['email']) ? htmlspecialchars($manager['email']) : ''; ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Phone</label>
                                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($manager['phone']); ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Address</label>
                                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($manager['address'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Profile Image</label>
                                            <input type="file" class="form-control" name="profile_image" accept="image/jpeg,image/png">
                                            <small class="text-muted">Allowed formats: JPG, JPEG, PNG. Max size: 2MB</small>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Update Profile</button>
                                    </form>
                                </div>
                                <!--
                                <div class="tab-pane fade" id="security">
                                    <form action="" method="POST">
                                        <input type="hidden" name="action" value="update_password">
                                        <div class="mb-3">
                                            <label class="form-label">Current Password</label>
                                            <input type="password" class="form-control" name="current_password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">New Password</label>
                                            <input type="password" class="form-control" name="new_password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Change Password</button>
                                    </form>
                                </div>
                                -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Sidebar Toggle
        document.getElementById('toggle-sidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('main-content').classList.toggle('expanded');
            document.querySelector('.topbar').classList.toggle('expanded');
        });
    </script>
</body>
</html> 

