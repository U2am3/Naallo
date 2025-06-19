<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get admin details
$stmt = $pdo->prepare("
    SELECT u.* 
    FROM users u 
    WHERE u.user_id = ? AND u.role = 'admin'
");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Fetch system settings for logo
$stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt_settings->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="sidebar" id="sidebar" style="background:#fff;">
    <div class="sidebar-logo" style="display:flex;flex-direction:column;align-items:center;justify-content:center;margin-top:32px;margin-bottom:24px;">
        <div style="background:#fafbfc;border-radius:24px;width:180px;height:180px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.03);">
            <img src="../../assets/images/<?php echo !empty($settings['company_logo']) ? htmlspecialchars($settings['company_logo']) : 'LOGO.jpg'; ?>" alt="System Logo" style="width:140px;height:140px;object-fit:contain;" />
        </div>
    </div>
    
    <div class="sidebar-menu" style="overflow-y:auto;max-height:calc(100vh - 300px);">
        <ul class="menu-items" style="list-style:none;padding:0;margin:0;">
            <li><a href="dashboard.php" class="sidebar-link home"><span class="sidebar-icon home"><i class="fas fa-home"></i></span>Dashboard</a></li>
            <li><a href="attendance.php" class="sidebar-link attendance"><span class="sidebar-icon attendance"><i class="fas fa-calendar-check"></i></span>Attendance</a></li>
            <li><a href="departments.php" class="sidebar-link departments"><span class="sidebar-icon departments"><i class="fas fa-building"></i></span>Departments</a></li>
            <li><a href="managers.php" class="sidebar-link managers"><span class="sidebar-icon managers"><i class="fas fa-user-tie"></i></span>Managers</a></li>
            <li><a href="employees.php" class="sidebar-link employees"><span class="sidebar-icon employees"><i class="fas fa-user"></i></span>Employees</a></li>
            <li><a href="leaves.php" class="sidebar-link leaves"><span class="sidebar-icon leaves"><i class="fas fa-calendar-alt"></i></span>Leave Management</a></li>
            <li><a href="payroll.php" class="sidebar-link payroll"><span class="sidebar-icon payroll"><i class="fas fa-file-invoice-dollar"></i></span>Payroll</a></li>
            <li><a href="projects.php" class="sidebar-link projects"><span class="sidebar-icon projects"><i class="fas fa-project-diagram"></i></span>Projects</a></li>
            <li><a href="reports.php" class="sidebar-link reports"><span class="sidebar-icon reports"><i class="fas fa-chart-bar"></i></span>Reports</a></li>
            <li><a href="users.php" class="sidebar-link users"><span class="sidebar-icon users"><i class="fas fa-users"></i></span>Users</a></li>
            <li><a href="settings.php" class="sidebar-link settings"><span class="sidebar-icon settings"><i class="fas fa-cog"></i></span>Settings</a></li>
            <!-- <li><a href="profile.php" class="sidebar-link profile"><span class="sidebar-icon profile"><i class="fas fa-user-cog"></i></span>Profile</a></li> -->
        </ul>
    </div>
    <div class="sidebar-bottom" style="width:100%;border-top:1px solid #f0f0f0;margin-top:24px;padding-top:16px;display:flex;flex-direction:column;align-items:center;">
        <a href="../../logout.php" class="sidebar-link logout"><span class="sidebar-icon logout"><i class="fas fa-sign-out-alt"></i></span>Logout</a>
    </div>
</div>

<!-- Add backdrop for mobile -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<style>
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 250px;
    background: #fff;
    color: #222;
    z-index: 1040;
    transition: all 0.3s ease;
    box-shadow: 0 0 20px rgba(78, 115, 223, 0.07);
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}
.sidebar-logo img {
    width: 140px;
    height: 140px;
    border-radius: 24px;
    object-fit: contain;
    background: none;
}
.sidebar-search input {
    background: #f3f4f6;
    color: #222;
    border: none;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 1rem;
}
.sidebar-search input::placeholder {
    color: #bbb;
}
.menu-items li {
    margin-bottom: 6px;
}
.sidebar-link {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 32px;
    border-radius: 12px;
    color: #222;
    font-weight: 500;
    font-size: 1rem;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
}
.sidebar-icon {
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    transition: background 0.2s, color 0.2s;
}
.sidebar-link.home .sidebar-icon { color: #4e73df; }
.sidebar-link.attendance .sidebar-icon { color: #6c63ff; }
.sidebar-link.employees .sidebar-icon { color: #1cc88a; }
.sidebar-link.departments .sidebar-icon { color: #f6c23e; }
.sidebar-link.projects .sidebar-icon { color: #36b9cc; }
.sidebar-link.payroll .sidebar-icon { color: #36b9cc; }
.sidebar-link.profile .sidebar-icon { color: #e74a3b; }
.sidebar-link.logout .sidebar-icon { color: #858796; }
.sidebar-link.logout { color: #858796; }
.sidebar-link:hover, .sidebar-link.active {
    background: #4e73df;
    color: #fff !important;
}
.sidebar-link:hover .sidebar-icon, .sidebar-link.active .sidebar-icon {
    color: #fff !important;
}
.sidebar-link.logout:hover {
    background: #e74a3b;
    color: #fff !important;
}
.sidebar-link.logout:hover .sidebar-icon {
    color: #fff !important;
}
.sidebar-bottom {
    width: 100%;
    border-top: 1px solid #f0f0f0;
    margin-top: 24px;
    padding-top: 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
}
@media (max-width: 1024px) {
    .sidebar {
        left: -260px;
        transition: left 0.3s;
    }
    .sidebar.show {
        left: 0;
    }
    .main-content {
        margin-left: 0 !important;
    }
}
.sidebar-menu {
    overflow-y: auto;
    max-height: calc(100vh - 300px);
}
.sidebar-menu::-webkit-scrollbar {
    width: 8px;
    background: #f3f4f6;
    border-radius: 8px;
}
.sidebar-menu::-webkit-scrollbar-thumb {
    background: #e0e7ef;
    border-radius: 8px;
    transition: background 0.3s;
}
.sidebar-menu::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
/* For Firefox */
.sidebar-menu {
    scrollbar-width: thin;
    scrollbar-color: #e0e7ef #f3f4f6;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggleBtns = document.querySelectorAll('[data-toggle="sidebar"]');

    function toggleSidebar() {
        sidebar.classList.toggle('show');
        backdrop.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
    }

    // Add click event to all toggle buttons
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
        });
    });

    // Close sidebar when clicking the backdrop
    backdrop.addEventListener('click', function() {
        toggleSidebar();
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768) {
            const isClickInside = sidebar.contains(event.target);
            const isToggleButton = event.target.closest('[data-toggle="sidebar"]');
            
            if (!isClickInside && !isToggleButton && sidebar.classList.contains('show')) {
                toggleSidebar();
            }
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
});
</script> 