<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get admin details
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();
?>

<!-- Topbar -->
<nav class="topbar">
    <div class="topbar-left">
        <button data-toggle="sidebar" class="toggle-btn">
            <i class="fas fa-bars"></i>
        </button>
        <div class="page-title">
            <?php
            $current_page = basename($_SERVER['PHP_SELF'], '.php');
            echo ucfirst($current_page);
            ?>
        </div>
    </div>

    <div class="topbar-right">
        <div class="profile-dropdown">
            <button class="profile-btn" onclick="toggleProfileMenu()">
                <div class="profile-info">
                    <span class="profile-name"><?php echo htmlspecialchars($admin['username']); ?></span>
                    <span class="profile-department">System Administrator</span>
                </div>
                <div class="profile-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
            </button>
            <div class="profile-menu" id="profileMenu">
                <a href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                    <i class="fas fa-user-cog"></i>
                    Profile Settings
                </a>
                <a href="../../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Profile</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <div class="mb-3">
          <span class="profile-avatar" style="font-size:3rem;"><i class="fas fa-user-circle"></i></span>
        </div>
        <h5><?php echo htmlspecialchars($admin['username']); ?></h5>
        <span class="badge bg-primary mb-2">Admin</span>
        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($admin['email']); ?></p>
        <button class="btn btn-outline-primary mt-3" data-bs-toggle="modal" data-bs-target="#editProfileModal" data-bs-dismiss="modal">Edit Profile</button>
        <button class="btn btn-outline-secondary mt-3" data-bs-toggle="modal" data-bs-target="#changePasswordModal" data-bs-dismiss="modal">Change Password</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Edit Profile</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" name="update_admin_profile">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Change Password</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
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
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" name="change_admin_password">Change Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
/* Topbar Base */
.topbar {
    position: fixed;
    top: 0;
    right: 0;
    left: 250px;
    height: 70px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1.5rem;
    z-index: 1030;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

/* Left Section */
.topbar-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.toggle-btn {
    display: none;
    background: none;
    border: none;
    color: #2c3e50;
    font-size: 1.25rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 0.375rem;
    transition: all 0.3s ease;
    z-index: 1050;
}

.toggle-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.page-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #2c3e50;
}

/* Right Section */
.topbar-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}

/* Profile Dropdown */
.profile-dropdown {
    position: relative;
}

.profile-btn {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: none;
    border: none;
    padding: 0.5rem;
    cursor: pointer;
    border-radius: 0.375rem;
    transition: background-color 0.3s ease;
}

.profile-btn:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.profile-info {
    text-align: right;
}

.profile-name {
    display: block;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9rem;
}

.profile-department {
    display: block;
    color: #6c757d;
    font-size: 0.75rem;
}

.profile-avatar {
    width: 40px;
    height: 40px;
    background: #4e73df;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}

.profile-avatar i {
    font-size: 1.5rem;
}

.profile-menu {
    position: absolute;
    top: 100%;
    right: 0;
    width: 200px;
    background: #fff;
    border-radius: 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    padding: 0.5rem 0;
    display: none;
    z-index: 1040;
}

.profile-menu.show {
    display: block;
}

.profile-menu a {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: #2c3e50;
    text-decoration: none;
    transition: background-color 0.3s ease;
}

.profile-menu a:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.profile-menu i {
    width: 16px;
    text-align: center;
    color: #6c757d;
}

/* Responsive Design */
@media (max-width: 768px) {
    .topbar {
        left: 0;
    }

    .toggle-btn {
        display: block;
    }

    .profile-info {
        display: none;
    }
}
</style>

<script>
function toggleProfileMenu() {
    const menu = document.getElementById('profileMenu');
    menu.classList.toggle('show');

    // Close menu when clicking outside
    document.addEventListener('click', function closeMenu(e) {
        if (!e.target.closest('.profile-dropdown')) {
            menu.classList.remove('show');
            document.removeEventListener('click', closeMenu);
        }
    });
}
</script> 