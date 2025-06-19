<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get employee details
$stmt = $pdo->prepare("
    SELECT e.*, u.username, u.email, u.role, d.dept_name as department_name 
    FROM employees e 
    JOIN users u ON e.user_id = u.user_id 
    LEFT JOIN departments d ON e.dept_id = d.dept_id 
    WHERE e.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
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
            <button class="profile-btn" id="profileDropdownBtn" type="button">
                <div class="profile-avatar">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="../../uploads/profile_photos/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <i class="fas fa-user-circle"></i>
                    <?php endif; ?>
                </div>
            </button>
            <div class="profile-menu" id="profileMenu">
                <button class="dropdown-item w-100 text-start" id="openProfileModalBtn" type="button">
                    <i class="fas fa-user-cog me-2"></i> Profile Settings
                </button>
                <a href="../../logout.php" class="dropdown-item w-100 text-start">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow p-3">
      <div class="modal-header">
        <h5 class="modal-title" id="profileModalLabel">My Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4 align-items-center">
          <!-- Left: Profile Image & Name -->
          <div class="col-md-4 text-center">
            <div class="mb-3">
              <?php if (!empty($user['profile_image'])): ?>
                <img src="../../uploads/profile_photos/<?= htmlspecialchars($user['profile_image']) ?>" alt="Profile" class="rounded-circle shadow" style="width:120px;height:120px;object-fit:cover;">
              <?php else: ?>
                <i class="fas fa-user-circle fa-7x text-secondary"></i>
              <?php endif; ?>
            </div>
            <h4 class="mb-1"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h4>
            <span class="badge bg-primary mb-2 text-uppercase"><?= htmlspecialchars($user['role']) ?></span>
          </div>
          <!-- Right: User Info Grid -->
          <div class="col-md-8">
            <div class="row g-3">
              <div class="col-sm-6">
                <div class="mb-1 text-muted small">Email</div>
                <div class="fw-semibold mb-2"><?= htmlspecialchars($user['email']) ?></div>
              </div>
              <div class="col-sm-6">
                <div class="mb-1 text-muted small">Department</div>
                <div class="fw-semibold mb-2"><?= htmlspecialchars($user['department_name'] ?? 'Not Assigned') ?></div>
              </div>
              <div class="col-sm-6">
                <div class="mb-1 text-muted small">Phone</div>
                <div class="fw-semibold mb-2"><?= htmlspecialchars($user['phone']) ?></div>
              </div>
              <div class="col-sm-6">
                <div class="mb-1 text-muted small">Join Date</div>
                <div class="fw-semibold mb-2"><?= isset($user['hire_date']) ? date('F d, Y', strtotime($user['hire_date'])) : '' ?></div>
              </div>
              <div class="col-12">
                <div class="mb-1 text-muted small">Address</div>
                <div class="fw-semibold mb-2"><?= htmlspecialchars($user['address']) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-outline-primary" id="editProfileBtn">
          <i class="fas fa-edit me-2"></i> Edit
        </button>
        <button type="button" class="btn btn-outline-secondary" id="changePasswordBtn">
          <i class="fas fa-key me-2"></i> Change Password
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content shadow p-3">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title text-black" id="editProfileModalLabel">Edit Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="row g-4 align-items-center">
            <div class="col-md-4 text-center">
              <div class="mb-3">
                <?php if (!empty($user['profile_image'])): ?>
                  <img src="../../uploads/profile_photos/<?= htmlspecialchars($user['profile_image']) ?>" alt="Profile" class="rounded-circle shadow" style="width:120px;height:120px;object-fit:cover;">
                <?php else: ?>
                  <i class="fas fa-user-circle fa-7x text-secondary"></i>
                <?php endif; ?>
              </div>
              <div class="mb-3">
                <input type="file" class="form-control" name="profile_image" accept="image/*">
                <small class="text-muted">JPG, PNG, Max 2MB</small>
              </div>
            </div>
            <div class="col-md-8">
              <div class="row g-3">
                <div class="col-sm-6">
                  <label class="form-label">First Name</label>
                  <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Last Name</label>
                  <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="col-sm-6">
                  <label class="form-label">Phone</label>
                  <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone']) ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">Address</label>
                  <textarea class="form-control" name="address" rows="2"><?= htmlspecialchars($user['address']) ?></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0 d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="update_profile" class="btn btn-primary">
            <i class="fas fa-save me-2"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow p-3">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title text-black" id="changePasswordModalLabel">Change Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="">
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
        <div class="modal-footer border-0 pt-0 d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="change_password" class="btn btn-primary">
            <i class="fas fa-key me-2"></i> Change Password
          </button>
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

.profile-avatar {
    width: 40px;
    height: 40px;
    background: #4e73df;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    overflow: hidden;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.profile-avatar i {
    font-size: 1.5rem;
}

.profile-menu {
    position: absolute;
    top: 110%;
    right: 0;
    min-width: 180px;
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

.profile-menu .dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: #2c3e50;
    text-decoration: none;
    background: none;
    border: none;
    width: 100%;
    transition: background-color 0.3s ease;
}

.profile-menu .dropdown-item:hover {
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
}
</style>
<script>
// Dropdown toggle
const profileBtn = document.getElementById('profileDropdownBtn');
const profileMenu = document.getElementById('profileMenu');
profileBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    profileMenu.classList.toggle('show');
});
document.addEventListener('click', function(e) {
    if (!e.target.closest('.profile-dropdown')) {
        profileMenu.classList.remove('show');
    }
});
// Open modal on Profile Settings click
const openProfileModalBtn = document.getElementById('openProfileModalBtn');
openProfileModalBtn.addEventListener('click', function() {
    var modal = new bootstrap.Modal(document.getElementById('profileModal'));
    modal.show();
    profileMenu.classList.remove('show');
});
// Open Edit Profile modal
const editProfileBtn = document.getElementById('editProfileBtn');
editProfileBtn.addEventListener('click', function() {
    var modal = new bootstrap.Modal(document.getElementById('editProfileModal'));
    modal.show();
    var profileModal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
    if (profileModal) profileModal.hide();
});
// Open Change Password modal
const changePasswordBtn = document.getElementById('changePasswordBtn');
changePasswordBtn.addEventListener('click', function() {
    var modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
    modal.show();
    var profileModal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
    if (profileModal) profileModal.hide();
});
</script>
