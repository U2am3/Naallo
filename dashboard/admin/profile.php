<?php
session_start();
require_once '../../config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login/admin.php");
    exit();
}

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        switch ($_POST['action']) {
            case 'update_profile':
                // Update user information
                $stmt = $pdo->prepare("
                    UPDATE employees SET 
                        first_name = ?,
                        last_name = ?,
                        date_of_birth = ?,
                        gender = ?,
                        phone = ?,
                        address = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['date_of_birth'],
                    $_POST['gender'],
                    $_POST['phone'],
                    $_POST['address'],
                    $_SESSION['user_id']
                ]);

                // Update email in users table
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
                $stmt->execute([$_POST['email'], $_SESSION['user_id']]);

                $success = "Profile updated successfully!";
                break;

            case 'update_password':
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();

                if (password_verify($_POST['current_password'], $user['password'])) {
                    if ($_POST['new_password'] === $_POST['confirm_password']) {
                        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $stmt->execute([$new_password, $_SESSION['user_id']]);
                        $success = "Password updated successfully!";
                    } else {
                        $error = "New passwords do not match!";
                    }
                } else {
                    $error = "Current password is incorrect!";
                }
                break;
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error updating profile: " . $e->getMessage();
    }
}

// Fetch user profile
try {
    $stmt = $pdo->prepare("
        SELECT u.*, e.*, d.dept_name
        FROM users u
        LEFT JOIN employees e ON u.user_id = e.user_id
        LEFT JOIN departments d ON e.dept_id = d.dept_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch();
} catch (PDOException $e) {
    $error = "Error fetching profile: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 5px solid white;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .profile-avatar i {
            font-size: 4rem;
            color: #4e73df;
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
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>

        <div class="container-fluid py-4">
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

            <div class="row">
                <div class="col-md-4">
                    <div class="profile-card mb-4">
                        <div class="profile-header text-center">
                            <div class="profile-avatar mb-3">
                                <?php if (!empty($profile['profile_image'])): ?>
                                    <img src="../../uploads/profile_photos/<?php echo htmlspecialchars($profile['profile_image']); ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                            </div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></h4>
                            <p class="mb-0"><?php echo ucfirst($profile['role']); ?></p>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Email</span>
                                <span><?php echo htmlspecialchars($profile['email']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Phone</span>
                                <span><?php echo htmlspecialchars($profile['phone']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted">Department</span>
                                <span><?php echo htmlspecialchars($profile['dept_name'] ?? 'Not Assigned'); ?></span>
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
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#security">Security</a>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <!-- Personal Information Tab -->
                                <div class="tab-pane fade show active" id="personal">
                                    <form action="" method="POST">
                                        <input type="hidden" name="action" value="update_profile">
                                        
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">First Name</label>
                                                <input type="text" class="form-control" name="first_name" 
                                                       value="<?php echo htmlspecialchars($profile['first_name']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Last Name</label>
                                                <input type="text" class="form-control" name="last_name" 
                                                       value="<?php echo htmlspecialchars($profile['last_name']); ?>" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Email</label>
                                                <input type="email" class="form-control" name="email" 
                                                       value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Phone</label>
                                                <input type="tel" class="form-control" name="phone" 
                                                       value="<?php echo htmlspecialchars($profile['phone']); ?>">
                                            </div>
                                        </div>

                                        <!-- <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Date of Birth</label>
                                                <input type="date" class="form-control" name="date_of_birth" 
                                                       value="<?php echo $profile['date_of_birth']; ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Gender</label>
                                                <select class="form-select" name="gender">
                                                    <option value="male" <?php echo $profile['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                                    <option value="female" <?php echo $profile['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                                    <option value="other" <?php echo $profile['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div> -->

                                        <div class="mb-3">
                                            <label class="form-label">Address</label>
                                            <textarea class="form-control" name="address" rows="3"><?php 
                                                echo htmlspecialchars($profile['address']); 
                                            ?></textarea>
                                        </div>

                                        <button type="submit" class="btn btn-primary">Update Profile</button>
                                    </form>
                                </div>

                                <!-- Security Tab -->
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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Sidebar Toggle
            $('#toggle-sidebar').on('click', function(e) {
                e.preventDefault();
                $('.sidebar').toggleClass('collapsed');
                $('.main-content').toggleClass('expanded');
                $('.topbar').toggleClass('expanded');
            });
        });
    </script>
</body>
</html> 