<?php
session_start();
require_once '../../config/database.php';
require_once '../admin/includes/functions.php';

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

// Initialize variables
$team_members = [];
$team_stats = [
    'total_employees' => 0,
    'active_employees' => 0,
    'inactive_employees' => 0,
    'male_count' => 0,
    'female_count' => 0
];

// Fetch team members
try {
    // Get team statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_employees,
            SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_employees,
            SUM(CASE WHEN e.status = 'inactive' THEN 1 ELSE 0 END) as inactive_employees,
            SUM(CASE WHEN e.gender = 'male' THEN 1 ELSE 0 END) as male_count,
            SUM(CASE WHEN e.gender = 'female' THEN 1 ELSE 0 END) as female_count
        FROM employees e
        WHERE e.dept_id = ?
    ");
    $stmt->execute([$manager['dept_id']]);
    $team_stats = $stmt->fetch();

    // Get team members
    $stmt = $pdo->prepare("
        SELECT e.*, u.email, u.username, u.status as user_status
        FROM employees e
        JOIN users u ON e.user_id = u.user_id
        WHERE e.dept_id = ?
        ORDER BY e.first_name, e.last_name
    ");
    $stmt->execute([$manager['dept_id']]);
    $team_members = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Team - Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .team-card {
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            transition: transform 0.3s;
        }
        .team-card:hover {
            transform: translateY(-5px);
        }
        .team-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        .profile-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .profile-image-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #adb5bd;
            border: 3px solid #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .employee-info {
            margin-top: 1rem;
        }
        .employee-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .employee-position {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .employee-contact {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .employee-status {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .gender-icon {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }
        .male-icon {
            color: #0d6efd;
        }
        .female-icon {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content mt-4" id="main-content">
        <?php include 'includes/topbar.php'; ?>
        <div class="container-fluid">
            <div class="dashboard-header mb-4" style="border-radius: 16px; box-shadow: 0 2px 16px rgba(30,34,90,0.07); padding: 1.5rem; background: #fff;">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div class="pe-3">
                        <h1 class="fw-bold mb-1" style="font-size:2rem; color:#222;">My Team</h1>
                        <div class="text-muted" style="font-size:1.1rem;">Manage your team members and their details</div>
                    </div>
                    <button class="btn btn-primary fw-bold px-4 py-2 d-flex align-items-center" style="font-size:1rem; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                        <i class="fas fa-plus me-2"></i> ADD NEW TEAM MEMBER
                    </button>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Team Statistics -->
            <div class="row mb-4">
                <div class="col-md-3 col-6 mb-4">
                    <div class="card bg-primary text-white h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Total Employees</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $team_stats['total_employees']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-white-50"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 5px;">
                                <div class="progress-bar bg-white" role="progressbar" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-6 mb-4">
                    <div class="card bg-success text-white h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Active Employees</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $team_stats['active_employees']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-check fa-2x text-white-50"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 5px;">
                                <div class="progress-bar bg-white" role="progressbar" style="width: <?php echo min(100, ($team_stats['active_employees'] / max(1, $team_stats['total_employees'])) * 100); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-6 mb-4">
                    <div class="card bg-danger text-white h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Inactive Employees</div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo $team_stats['inactive_employees']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-times fa-2x text-white-50"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 5px;">
                                <div class="progress-bar bg-white" role="progressbar" style="width: <?php echo min(100, ($team_stats['inactive_employees'] / max(1, $team_stats['total_employees'])) * 100); ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-6 mb-4">
                    <div class="card bg-info text-white h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">Gender Distribution</div>
                                    <div class="h5 mb-0 font-weight-bold">
                                        <i class="fas fa-mars male-icon"></i> <?php echo $team_stats['male_count']; ?> / 
                                        <i class="fas fa-venus female-icon"></i> <?php echo $team_stats['female_count']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-venus-mars fa-2x text-white-50"></i>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 5px;">
                                <div class="progress-bar bg-white" role="progressbar" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Members -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Team Members</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($team_members as $member): ?>
                            <div class="col-md-4 col-lg-3 mb-4">
                                <div class="card team-card h-100">
                                    <div class="card-body text-center position-relative">
                                        <span class="badge bg-<?php echo $member['status'] === 'active' ? 'success' : 'danger'; ?> employee-status">
                                            <?php echo ucfirst($member['status']); ?>
                                        </span>
                                        
                                        <?php if (!empty($member['profile_image'])): ?>
                                            <img src="../../uploads/profile_images/<?php echo $member['profile_image']; ?>" alt="Profile" class="profile-image">
                                        <?php else: ?>
                                            <div class="profile-image-placeholder">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="employee-info">
                                            <div class="employee-name">
                                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                            </div>
                                            <div class="employee-position">
                                                <?php echo htmlspecialchars($member['position']); ?>
                                            </div>
                                            <div class="employee-contact">
                                                <div><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($member['email']); ?></div>
                                                <div><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($member['phone']); ?></div>
                                            </div>
                                            <div class="mt-2">
                                                <span class="badge bg-<?php echo $member['gender'] === 'male' ? 'primary' : 'danger'; ?>">
                                                    <i class="fas fa-<?php echo $member['gender'] === 'male' ? 'mars' : 'venus'; ?> me-1"></i>
                                                    <?php echo ucfirst($member['gender']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Team Members Table -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Team Members Details</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="teamTable" class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Gender</th>
                                    <th>Status</th>
                                    <th>Join Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($team_members as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($member['position']); ?></td>
                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td><?php echo htmlspecialchars($member['phone']); ?></td>
                                    <td>
                                        <i class="fas fa-<?php echo $member['gender'] === 'male' ? 'mars' : 'venus'; ?> me-1"></i>
                                        <?php echo ucfirst($member['gender']); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $member['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($member['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($member['join_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#teamTable').DataTable();
        });

        // Sidebar Toggle
        document.getElementById('toggle-sidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('main-content').classList.toggle('expanded');
            document.querySelector('.topbar').classList.toggle('expanded');
        });
    </script>
</body>
</html> 