<?php
session_start();
require_once '../../config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login/admin.php");
    exit();
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

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $error = null;
        $error = null;
        try {
            switch ($_POST['action']) {
            case 'update_profile':
                // Update admin profile
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, password=? WHERE user_id=?");
                    $stmt->execute([$_POST['username'], $_POST['email'], $password, $_SESSION['user_id']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username=?, email=? WHERE user_id=?");
                    $stmt->execute([$_POST['username'], $_POST['email'], $_SESSION['user_id']]);
                }
                $success = "Profile updated successfully!";
                break;

            case 'update_leave_types':
                // Update leave types
                $stmt = $pdo->prepare("UPDATE leave_types SET default_days=? WHERE leave_type_id=?");
                foreach ($_POST['leave_days'] as $leave_type_id => $days) {
                    if ((int)$days <= 0) {
                        throw new Exception("Default days must be greater than 0.");
                    }
                    $stmt->execute([$days, $leave_type_id]);
                }
                $success = "Leave types updated successfully!";
                break;

            case 'add_leave_type':
                // Add new leave type
                if ((int)$_POST['default_days'] <= 0) {
                    throw new Exception("Default days must be greater than 0.");
                }
                $stmt = $pdo->prepare("INSERT INTO leave_types (leave_type_name, description, default_days) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['leave_type_name'], $_POST['description'], $_POST['default_days']]);
                $success = "New leave type added successfully!";
                break;

            case 'delete_leave_type':
                // Delete leave type
                $stmt = $pdo->prepare("DELETE FROM leave_types WHERE leave_type_id = ?");
                $stmt->execute([$_POST['leave_type_id']]);
                $success = "Leave type deleted successfully!";
                break;

            case 'update_system_settings':
                // Update system settings (you can add more settings as needed)
                $settings = [
                    'company_name' => $_POST['company_name'],
                    'company_email' => $_POST['company_email'],
                    'company_address' => $_POST['company_address'],
                    'work_hours' => $_POST['work_hours'],
                    'late_threshold' => $_POST['late_threshold'],
                    'company_phone' => $_POST['company_phone']
                ];
                // Handle logo upload
                if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
                    $logoTmpPath = $_FILES['company_logo']['tmp_name'];
                    $logoName = basename($_FILES['company_logo']['name']);
                    $logoExt = strtolower(pathinfo($logoName, PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (in_array($logoExt, $allowedExts)) {
                        $newLogoName = 'company_logo_' . time() . '.' . $logoExt;
                        $destPath = __DIR__ . '/../../assets/images/' . $newLogoName;
                        if (move_uploaded_file($logoTmpPath, $destPath)) {
                            $settings['company_logo'] = $newLogoName;
                        }
                    }
                }
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) 
                                     VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                
                foreach ($settings as $key => $value) {
                    $stmt->execute([$key, $value]);
                }
                $success = "System settings updated successfully!";
                break;
            }
            if ($error) {
                $pdo->rollBack();
            } else {
                $pdo->commit();
            }
        } catch (Exception $ex) {
            $pdo->rollBack();
            $error = $ex->getMessage();
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Fetch current settings
try {
    // Get admin profile
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();

    // Get leave types
    $stmt = $pdo->query("SELECT * FROM leave_types ORDER BY leave_type_name");
    $leave_types = $stmt->fetchAll();

    // Get system settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $error = "Error fetching settings: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .nav-tabs .nav-link {
            color: #495057;
            background-color: transparent;
            border: 1px solid transparent;
            border-top-left-radius: .25rem;
            border-top-right-radius: .25rem;
            padding: 0.5rem 1rem;
            margin-bottom: -1px;
            text-decoration: none;
        }
        .nav-tabs .nav-link:hover {
            border-color: #e9ecef #e9ecef #dee2e6;
            isolation: isolate;
        }
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
        }
        .tab-content {
            padding: 20px;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-top: none;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>

        <div class="container-fluid">
            <h2 class="mb-4">Settings</h2>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo (!isset($_POST['action']) || $_POST['action'] === 'update_profile') ? 'active' : ''; ?>" 
                                    id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" 
                                    type="button" role="tab">
                                <i class="fas fa-user me-2"></i>Profile
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo (isset($_POST['action']) && in_array($_POST['action'], ['update_leave_types', 'add_leave_type', 'delete_leave_type'])) ? 'active' : ''; ?>" 
                                    id="leaveTypes-tab" data-bs-toggle="tab" data-bs-target="#leaveTypes" 
                                    type="button" role="tab">
                                <i class="fas fa-calendar-alt me-2"></i>Leave Types
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo (isset($_POST['action']) && $_POST['action'] === 'update_system_settings') ? 'active' : ''; ?>" 
                                    id="systemSettings-tab" data-bs-toggle="tab" data-bs-target="#systemSettings" 
                                    type="button" role="tab">
                                <i class="fas fa-cog me-2"></i>System Settings
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mt-4">
                        <!-- Profile Settings -->
                        <div class="tab-pane fade <?php echo (!isset($_POST['action']) || $_POST['action'] === 'update_profile') ? 'show active' : ''; ?>" 
                             id="profile" role="tabpanel" aria-labelledby="profile-tab">
                            <form action="" method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current password">
                                </div>

                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </form>
                        </div>

                        <!-- Leave Types Settings -->
                        <div class="tab-pane fade <?php echo (isset($_POST['action']) && in_array($_POST['action'], ['update_leave_types', 'add_leave_type', 'delete_leave_type'])) ? 'show active' : ''; ?>" 
                             id="leaveTypes" role="tabpanel" aria-labelledby="leaveTypes-tab">
                            <div class="mb-4">
                                <h5>Current Leave Types</h5>
                                <form action="" method="POST">
                                    <input type="hidden" name="action" value="update_leave_types">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Leave Type</th>
                                                    <th>Description</th>
                                                    <th>Default Days</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($leave_types as $type): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($type['leave_type_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($type['description']); ?></td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm" 
                                                               name="leave_days[<?php echo $type['leave_type_id']; ?>]" 
                                                               value="<?php echo $type['default_days']; ?>" min="0">
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="deleteLeaveType(<?php echo $type['leave_type_id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Update Leave Types</button>
                                </form>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <h5>Add New Leave Type</h5>
                                    <form action="" method="POST">
                                        <input type="hidden" name="action" value="add_leave_type">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Leave Type Name</label>
                                            <input type="text" class="form-control" name="leave_type_name" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea class="form-control" name="description" rows="2"></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Default Days</label>
                                            <input type="number" class="form-control" name="default_days" value="0" min="0" required>
                                        </div>

                                        <button type="submit" class="btn btn-success">Add Leave Type</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- System Settings -->
                        <div class="tab-pane fade <?php echo (isset($_POST['action']) && $_POST['action'] === 'update_system_settings') ? 'show active' : ''; ?>" 
                             id="systemSettings" role="tabpanel" aria-labelledby="systemSettings-tab">
                            <form action="" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_system_settings">
                                
                                <div class="mb-3">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" class="form-control" name="company_name" 
                                           value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Company Email</label>
                                    <input type="email" class="form-control" name="company_email" 
                                           value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Company Address</label>
                                    <textarea class="form-control" name="company_address" rows="2"><?php 
                                        echo htmlspecialchars($settings['company_address'] ?? ''); 
                                    ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Work Hours (per day)</label>
                                    <input type="number" class="form-control" name="work_hours" 
                                           value="<?php echo htmlspecialchars($settings['work_hours'] ?? '8'); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Late Threshold (minutes)</label>
                                    <input type="number" class="form-control" name="late_threshold" 
                                           value="<?php echo htmlspecialchars($settings['late_threshold'] ?? '15'); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Company Phone</label>
                                    <input type="text" class="form-control" name="company_phone" 
                                           value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Company Logo</label><br>
                                    <?php if (!empty($settings['company_logo'])): ?>
                                        <img src="../../assets/images/<?php echo htmlspecialchars($settings['company_logo']); ?>" alt="Company Logo" style="height:40px; margin-bottom:10px;">
                                    <?php endif; ?>
                                    <input type="file" class="form-control" name="company_logo" accept="image/*">
                                    <small class="text-muted">Upload a new logo to replace the current one.</small>
                                </div>

                                <button type="submit" class="btn btn-primary">Update System Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Leave Type Modal -->
    <div class="modal fade" id="deleteLeaveTypeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Leave Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_leave_type">
                        <input type="hidden" name="leave_type_id" id="delete_leave_type_id">
                        <p>Are you sure you want to delete this leave type? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Delete Leave Type Function
        function deleteLeaveType(leaveTypeId) {
            document.getElementById('delete_leave_type_id').value = leaveTypeId;
            new bootstrap.Modal(document.getElementById('deleteLeaveTypeModal')).show();
        }

        // Sidebar Toggle
        $('#toggle-sidebar').on('click', function(e) {
            e.preventDefault();
            $('.sidebar').toggleClass('collapsed');
            $('.main-content').toggleClass('expanded');
            $('.topbar').toggleClass('expanded');
        });

        // Initialize active tab based on URL hash or form submission
        document.addEventListener('DOMContentLoaded', function() {
            // Get active tab from URL hash or form action
            let activeTab = window.location.hash;
            if (!activeTab) {
                const action = '<?php echo isset($_POST["action"]) ? $_POST["action"] : ""; ?>';
                if (action.includes('leave_type')) {
                    activeTab = '#leaveTypes';
                } else if (action === 'update_system_settings') {
                    activeTab = '#systemSettings';
                } else {
                    activeTab = '#profile';
                }
            }

            // Activate the correct tab
            const tab = new bootstrap.Tab(document.querySelector(`button[data-bs-target="${activeTab}"]`));
            tab.show();
        });
    </script>

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
</body>
</html>