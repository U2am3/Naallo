<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get employee details
$stmt = $pdo->prepare("
    SELECT e.*, u.username 
    FROM employees e 
    JOIN users u ON e.user_id = u.user_id 
    WHERE e.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

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
    
    <div class="sidebar-menu">
        <ul class="menu-items" style="list-style:none;padding:0;margin:0;">
            <li><a href="dashboard.php" class="sidebar-link home"><span class="sidebar-icon home"><i class="fas fa-home"></i></span>Dashboard</a></li>
            <li><a href="attendance.php" class="sidebar-link attendance"><span class="sidebar-icon attendance"><i class="fas fa-calendar-check"></i></span>Attendance</a></li>
            <li><a href="leave.php" class="sidebar-link leave"><span class="sidebar-icon leave"><i class="fas fa-calendar-alt"></i></span>Leave</a></li>
            <li><a href="projects.php" class="sidebar-link projects"><span class="sidebar-icon projects"><i class="fas fa-tasks"></i></span>Projects</a></li>
            <li><a href="payslip.php" class="sidebar-link payslip"><span class="sidebar-icon payslip"><i class="fas fa-file-invoice-dollar"></i></span>Payslips</a></li>
            <!-- <li><a href="profile.php" class="sidebar-link profile"><span class="sidebar-icon profile"><i class="fas fa-user-cog"></i></span>Profile</a></li> -->
        </ul>
    </div>
    <div class="sidebar-bottom" style="width:100%;border-top:1px solid #f0f0f0;margin-top:auto;padding-top:16px;display:flex;flex-direction:column;align-items:center;">
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
.sidebar-link.leave .sidebar-icon { color: #f6c23e; }
.sidebar-link.projects .sidebar-icon { color: #36b9cc; }
.sidebar-link.payslip .sidebar-icon { color: #36b9cc; }
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
.sidebar-menu {
    flex: 1 1 auto;
}
.sidebar-bottom {
    width: 100%;
    border-top: 1px solid #f0f0f0;
    margin-top: auto;
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