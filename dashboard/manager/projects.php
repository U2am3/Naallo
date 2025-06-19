<?php
session_start();
require_once '../../config/database.php';
require_once '../admin/includes/functions.php';

// Check if user is logged in and is manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../../login.php");
    exit();
}

// Get manager details
$stmt = $pdo->prepare("
    SELECT e.*, d.dept_name
    FROM employees e
    LEFT JOIN departments d ON e.dept_id = d.dept_id
    WHERE e.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$manager = $stmt->fetch();

// Handle project status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        try {
            $stmt = $pdo->prepare("
                UPDATE projects 
                SET status = ?, updated_at = NOW()
                WHERE project_id = ? AND manager_id = ?
            ");
            $stmt->execute([
                $_POST['status'],
                $_POST['project_id'],
                $_SESSION['user_id']
            ]);

            // Add status update notification for team members
            $notify_stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, message, reference_id, created_at)
                SELECT 
                    e.user_id,
                    'project_update',
                    CONCAT('Project \"', p.project_name, '\" status updated to: ', ?, ' by manager'),
                    p.project_id,
                    NOW()
                FROM project_assignments pa
                JOIN employees e ON pa.emp_id = e.emp_id
                JOIN projects p ON pa.project_id = p.project_id
                WHERE p.project_id = ?
            ");
            $notify_stmt->execute([$_POST['status'], $_POST['project_id']]);

            $success = "Project status updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating project status: " . $e->getMessage();
        }
    }
}

// Fetch all projects managed by this manager with filters
try {
    $query = "
        SELECT p.*, 
               COUNT(DISTINCT pa.emp_id) as team_size,
               GROUP_CONCAT(DISTINCT CONCAT(e.first_name, ' ', e.last_name) SEPARATOR ', ') as team_members,
               d.dept_name
        FROM projects p
        LEFT JOIN project_assignments pa ON p.project_id = pa.project_id
        LEFT JOIN employees e ON pa.emp_id = e.emp_id
        LEFT JOIN departments d ON e.dept_id = d.dept_id
        WHERE p.manager_id = ?
    ";
    
    $params = [$_SESSION['user_id']];
    
    // Apply filters
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $query .= " AND p.status = ?";
        $params[] = $_GET['status'];
    }
    
    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $query .= " AND (p.project_name LIKE ? OR p.description LIKE ?)";
        $search_term = '%' . $_GET['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $query .= " GROUP BY p.project_id ORDER BY p.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}

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
    <title>Project Management - Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
    }

    .page-header {
        background: var(--primary-blue);
        padding: 2rem 0;
        margin-bottom: 2.5rem;
        border-radius: 0 0 25px 25px;
        box-shadow: 0 4px 20px rgba(71, 99, 228, 0.2);
    }

    .page-header h1 {
        color: white;
        font-size: 2rem;
        font-weight: 600;
        margin: 0;
    }

    .project-card {
        background: white;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        transition: var(--transition);
        margin-bottom: 1.5rem;
        border: none;
        overflow: hidden;
    }

    .project-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .project-header {
        padding: 1.5rem;
        background: rgba(71, 99, 228, 0.02);
        border-bottom: 1px solid rgba(71, 99, 228, 0.1);
        position: relative;
    }

    .project-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #111827;
        margin-bottom: 0.5rem;
        padding-right: 100px;
    }

    .project-status {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .project-body {
        padding: 1.5rem;
    }

    .project-info-item {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
        padding: 0.75rem;
        background: rgba(71, 99, 228, 0.02);
        border-radius: 12px;
        transition: var(--transition);
    }

    .project-info-item:hover {
        background: rgba(71, 99, 228, 0.05);
        transform: translateX(5px);
    }

    .project-info-item i {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(71, 99, 228, 0.1);
        color: var(--primary-blue);
        border-radius: 8px;
        margin-right: 1rem;
    }

    .progress {
        height: 8px;
        background: rgba(71, 99, 228, 0.1);
        border-radius: 4px;
        overflow: hidden;
        margin-top: 1rem;
    }

    .progress-bar {
        background: linear-gradient(to right, var(--primary-blue), #6282FF);
        border-radius: 4px;
        transition: width 1s ease-in-out;
    }

    .project-actions {
        padding: 1rem 1.5rem;
        background: rgba(71, 99, 228, 0.02);
        border-top: 1px solid rgba(71, 99, 228, 0.1);
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
    }

    .project-actions .btn {
        padding: 0.75rem 1.25rem;
        border-radius: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: var(--transition);
    }

    .project-actions .btn:hover {
        transform: translateY(-2px);
    }

    .filter-card {
        background: white;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        margin-bottom: 2rem;
        border: none;
    }

    .filter-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .filter-header h6 {
        font-weight: 600;
        margin: 0;
        color: #111827;
    }

    .filter-body {
        padding: 1.5rem;
    }

    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    }

    .modal-header {
        border-radius: 16px 16px 0 0;
        padding: 1.5rem;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        padding: 1.25rem 1.5rem;
        background: rgba(71, 99, 228, 0.02);
        border-top: 1px solid rgba(71, 99, 228, 0.1);
    }

    .team-members {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .team-member-card {
        background: white;
        border-radius: 14px;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: var(--transition);
        border: 1px solid rgba(71, 99, 228, 0.1);
    }

    .team-member-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(71, 99, 228, 0.1);
    }

    .member-avatar {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--primary-blue), #6282FF);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .member-info {
        flex: 1;
        min-width: 0;
    }

    .member-name {
        font-weight: 600;
        color: #111827;
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .member-role {
        font-size: 0.875rem;
        color: #6B7280;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .member-role i {
        color: var(--primary-blue);
        opacity: 0.7;
    }

    .dashboard-header {
        background: white;
        padding: 1.5rem;
        border-radius: 0.75rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        margin-bottom: 1.5rem;
    }

    @media (max-width: 768px) {
        .project-status {
            position: static;
            margin-bottom: 1rem;
        }
        
        .project-actions {
            flex-wrap: wrap;
        }
        
        .project-actions .btn {
            flex: 1;
            justify-content: center;
        }
    }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>

        <div class="dashboard-header mb-4" style="background: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58,59,69,0.15);">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="pe-3">
                    <h1 class="fw-bold mb-1" style="font-size:2rem; color:#222;">Project Management</h1>
                    <div class="text-muted" style="font-size:1.1rem;">Manage all your projects and assignments</div>
                </div>
                <button class="btn btn-primary fw-bold px-4 py-2 d-flex align-items-center" style="font-size:1rem; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    <i class="fas fa-plus me-2"></i> ADD NEW PROJECT
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

        <!-- Filters -->
        <div class="filter-card">
            <div class="filter-header">
                <i class="fas fa-filter me-2"></i> Filter Projects
            </div>
            <div class="filter-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="not_started" <?php echo isset($_GET['status']) && $_GET['status'] === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                            <option value="in_progress" <?php echo isset($_GET['status']) && $_GET['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Search projects..." value="<?php echo $_GET['search'] ?? ''; ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Projects Grid -->
        <div class="row">
            <?php if (empty($projects)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> You don't have any projects assigned yet.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="project-card">
                            <div class="project-header">
                                <h5 class="project-title"><?php echo htmlspecialchars($project['project_name']); ?></h5>
                                <span class="badge bg-<?php 
                                    echo $project['status'] === 'completed' ? 'success' : 
                                        ($project['status'] === 'in_progress' ? 'primary' : 'secondary'); 
                                ?> project-status">
                                    <?php echo ucwords(str_replace('_', ' ', $project['status'])); ?>
                                </span>
                            </div>
                            <div class="project-body">
                                <div class="project-info">
                                    <div class="project-info-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span><?php echo date('M d, Y', strtotime($project['start_date'])); ?> - <?php echo date('M d, Y', strtotime($project['end_date'])); ?></span>
                                    </div>
                                    <div class="project-info-item">
                                        <i class="fas fa-users"></i>
                                        <span><?php echo $project['team_size']; ?> team members</span>
                                    </div>
                                    <div class="project-info-item">
                                        <i class="fas fa-building"></i>
                                        <span><?php echo htmlspecialchars($project['dept_name'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>

                                <?php
                                // Calculate project progress
                                $start = strtotime($project['start_date']);
                                $end = strtotime($project['end_date']);
                                $today = time();
                                
                                $total_days = ($end - $start) / (60 * 60 * 24);
                                $days_passed = ($today - $start) / (60 * 60 * 24);
                                
                                $progress = min(100, max(0, ($days_passed / $total_days) * 100));
                                ?>

                                <div class="progress">
                                    <div class="progress-bar bg-<?php 
                                        echo $project['status'] === 'completed' ? 'success' : 
                                            ($project['status'] === 'in_progress' ? 'primary' : 'secondary'); 
                                    ?>" role="progressbar" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                            <div class="project-actions">
                                <button class="btn btn-sm btn-info" onclick="viewProject(<?php echo htmlspecialchars(json_encode($project)); ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                <button class="btn btn-sm btn-primary" onclick="updateStatus(<?php echo htmlspecialchars(json_encode($project)); ?>)">
                                    <i class="fas fa-edit"></i> Update Status
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Project Modal -->
<div class="modal fade" id="viewProjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Project Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <h4 id="view_project_name"></h4>
                        <p class="text-muted" id="view_project_description"></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge" id="view_project_status"></span>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>Project Information</h6>
                        <div class="project-info-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Start Date: <span id="view_start_date"></span></span>
                        </div>
                        <div class="project-info-item">
                            <i class="fas fa-calendar-check"></i>
                            <span>End Date: <span id="view_end_date"></span></span>
                        </div>
                        <div class="project-info-item">
                            <i class="fas fa-building"></i>
                            <span>Department: <span id="view_department"></span></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Progress</h6>
                        <div class="progress mb-2">
                            <div class="progress-bar" id="view_progress_bar" role="progressbar"></div>
                        </div>
                        <small class="text-muted" id="view_progress_text"></small>
                    </div>
                </div>
                
                <hr>
                
                <h6>Team Members</h6>
                <div id="view_team_members" class="team-members"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Project Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="project_id" id="update_project_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Project Name</label>
                        <input type="text" class="form-control" id="update_project_name" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Current Status</label>
                        <input type="text" class="form-control" id="update_current_status" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select class="form-select" name="status" id="update_status" required>
                            <option value="not_started">Not Started</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
function viewProject(project) {
    // Set basic project information
    $('#view_project_name').text(project.project_name);
    $('#view_project_description').text(project.description || 'No description available');
    $('#view_start_date').text(new Date(project.start_date).toLocaleDateString());
    $('#view_end_date').text(new Date(project.end_date).toLocaleDateString());
    $('#view_department').text(project.dept_name || 'N/A');
    
    // Set status badge with proper styling
    const statusBadge = $('#view_project_status');
    const statusText = project.status.replace(/_/g, ' ').toUpperCase();
    statusBadge.text(statusText);
    statusBadge.removeClass().addClass('badge').addClass('bg-' + getStatusClass(project.status));
    
    // Calculate and set progress
    const start = new Date(project.start_date);
    const end = new Date(project.end_date);
    const today = new Date();
    
    const totalDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
    const daysPassed = Math.ceil((today - start) / (1000 * 60 * 60 * 24));
    const progress = Math.min(100, Math.max(0, (daysPassed / totalDays) * 100));
    
    // Update progress bar with animation
    const progressBar = $('#view_progress_bar');
    progressBar
        .css('width', '0%')
        .removeClass()
        .addClass('progress-bar bg-' + getStatusClass(project.status))
        .attr('aria-valuenow', progress);
    
    setTimeout(() => {
        progressBar.css('width', progress + '%');
    }, 100);
    
    $('#view_progress_text').text(Math.round(progress) + '% Complete');
    
    // Display team members with enhanced styling
    const teamMembers = project.team_members ? project.team_members.split(', ') : [];
    const teamContainer = $('#view_team_members');
    teamContainer.empty();
    
    teamMembers.forEach(member => {
        const initials = member.split(' ').map(n => n[0]).join('');
        teamContainer.append(`
            <div class="team-member-card">
                <div class="member-avatar">${initials}</div>
                <div class="member-info">
                    <div class="member-name">${member}</div>
                    <div class="member-role">
                        <i class="fas fa-user"></i>
                        Team Member
                    </div>
                </div>
            </div>
        `);
    });
    
    // Show the modal
    $('#viewProjectModal').modal('show');
}

function updateStatus(project) {
    $('#update_project_id').val(project.project_id);
    $('#update_project_name').val(project.project_name);
    $('#update_current_status').val(project.status.replace('_', ' ').toUpperCase());
    $('#update_status').val(project.status);
    
    $('#updateStatusModal').modal('show');
}

function getStatusClass(status) {
    switch (status) {
        case 'completed':
            return 'success';
        case 'in_progress':
            return 'primary';
        case 'not_started':
            return 'secondary';
        default:
            return 'secondary';
    }
}
</script>

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