<?php
session_start();
require_once '../../config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

// Handle project actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Validate dates
                    $start_date = new DateTime($_POST['start_date']);
                    $end_date = new DateTime($_POST['end_date']);
                    
                    if ($end_date < $start_date) {
                        throw new Exception("End date cannot be before start date");
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO projects (
                            project_name, description, start_date, end_date, 
                            status, manager_id, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    
                    $stmt->execute([
                        $_POST['project_name'],
                        $_POST['description'],
                        $_POST['start_date'],
                        $_POST['end_date'],
                        $_POST['status'],
                        $_POST['manager_id']
                    ]);
                    
                    $project_id = $pdo->lastInsertId();
                    
                    // Add assigned employees
                    if (!empty($_POST['assigned_employees'])) {
                        $stmt = $pdo->prepare("
                            INSERT INTO project_assignments (
                                project_id, emp_id, assigned_date
                            ) VALUES (?, ?, NOW())
                        ");
                        
                        foreach ($_POST['assigned_employees'] as $emp_id) {
                            $stmt->execute([$project_id, $emp_id]);
                        }

                        // Add notifications for assigned employees
                        $notify_stmt = $pdo->prepare("
                            INSERT INTO notifications (
                                user_id, type, message, reference_id, created_at
                            ) VALUES (?, 'project_assignment', ?, ?, NOW())
                        ");

                        foreach ($_POST['assigned_employees'] as $emp_id) {
                            // Get employee's user_id
                            $user_stmt = $pdo->prepare("SELECT user_id FROM employees WHERE emp_id = ?");
                            $user_stmt->execute([$emp_id]);
                            $user = $user_stmt->fetch();
                            
                            if ($user) {
                                $message = "You have been assigned to project: " . $_POST['project_name'];
                                $notify_stmt->execute([$user['user_id'], $message, $project_id]);
                            }
                        }
                    }
                    
                    // Add notification for assigned manager
                    $notify_stmt = $pdo->prepare("
                        INSERT INTO notifications (
                            user_id, type, message, reference_id, created_at
                        ) VALUES (?, 'project_assignment', ?, ?, NOW())
                    ");
                    
                    $message = "You have been assigned as manager for project: " . $_POST['project_name'];
                    $notify_stmt->execute([$_POST['manager_id'], $message, $project_id]);
                    
                    $success = "Project added successfully.";
                } catch (Exception $e) {
                    $error = "Error adding project: " . $e->getMessage();
                }
                break;
                
            case 'update':
                try {
                    // Start transaction
                    $pdo->beginTransaction();

                    // Validate dates
                    $start_date = new DateTime($_POST['start_date']);
                    $end_date = new DateTime($_POST['end_date']);
                    
                    if ($end_date < $start_date) {
                        throw new Exception("End date cannot be before start date");
                    }

                    // Validate manager_id exists in users table
                    $stmt = $pdo->prepare("
                        SELECT u.user_id 
                        FROM users u 
                        WHERE u.user_id = ? AND u.role = 'manager'
                    ");
                    $stmt->execute([$_POST['manager_id']]);
                    if (!$stmt->fetch()) {
                        throw new Exception("Invalid manager selected");
                    }
                    
                    // Get current project details for comparison
                    $stmt = $pdo->prepare("SELECT manager_id FROM projects WHERE project_id = ?");
                    $stmt->execute([$_POST['project_id']]);
                    $current_project = $stmt->fetch();
                    
                    // Update project details
                    $stmt = $pdo->prepare("
                        UPDATE projects SET 
                            project_name = ?,
                            description = ?,
                            start_date = ?,
                            end_date = ?,
                            status = ?,
                            manager_id = ?,
                            updated_at = NOW()
                        WHERE project_id = ?
                    ");
                    
                    $stmt->execute([
                        $_POST['project_name'],
                        $_POST['description'],
                        $_POST['start_date'],
                        $_POST['end_date'],
                        $_POST['status'],
                        $_POST['manager_id'],
                        $_POST['project_id']
                    ]);
                    
                    // If manager changed, add notification
                    if ($current_project['manager_id'] != $_POST['manager_id']) {
                        $notify_stmt = $pdo->prepare("
                            INSERT INTO notifications (
                                user_id, type, message, reference_id, created_at
                            ) VALUES (?, 'project_assignment', ?, ?, NOW())
                        ");
                        
                        $message = "You have been assigned as manager for project: " . $_POST['project_name'];
                        $notify_stmt->execute([$_POST['manager_id'], $message, $_POST['project_id']]);
                    }
                    
                    // Update assigned employees
                    if (!empty($_POST['assigned_employees'])) {
                        // Validate employee IDs
                        $placeholders = str_repeat('?,', count($_POST['assigned_employees']) - 1) . '?';
                        $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE emp_id IN ($placeholders)");
                        $stmt->execute($_POST['assigned_employees']);
                        $valid_employees = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        if (count($valid_employees) !== count($_POST['assigned_employees'])) {
                            throw new Exception("One or more selected employees do not exist");
                        }
                        
                        // Delete existing assignments
                        $stmt = $pdo->prepare("DELETE FROM project_assignments WHERE project_id = ?");
                        $stmt->execute([$_POST['project_id']]);
                        
                        // Insert new assignments
                        $stmt = $pdo->prepare("
                            INSERT INTO project_assignments (
                                project_id, emp_id, assigned_date
                            ) VALUES (?, ?, NOW())
                        ");
                        
                        foreach ($valid_employees as $emp_id) {
                            $stmt->execute([$_POST['project_id'], $emp_id]);
                        }
                    } else {
                        // Remove all assignments if no employees selected
                        $stmt = $pdo->prepare("DELETE FROM project_assignments WHERE project_id = ?");
                        $stmt->execute([$_POST['project_id']]);
                    }
                    
                    // Commit transaction
                    $pdo->commit();
                    $success = "Project updated successfully.";
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $pdo->rollBack();
                    $error = "Error updating project: " . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    // Delete project assignments first
                    $stmt = $pdo->prepare("DELETE FROM project_assignments WHERE project_id = ?");
                    $stmt->execute([$_POST['project_id']]);
                    
                    // Delete project notifications
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE reference_id = ? AND type = 'project_assignment'");
                    $stmt->execute([$_POST['project_id']]);
                    
                    // Then delete the project
                    $stmt = $pdo->prepare("DELETE FROM projects WHERE project_id = ?");
                    $stmt->execute([$_POST['project_id']]);
                    
                    $success = "Project deleted successfully.";
                } catch (PDOException $e) {
                    $error = "Error deleting project: " . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch projects with filters
try {
    $query = "
        SELECT p.*, 
               CONCAT(m.first_name, ' ', m.last_name) as manager_name,
               d.dept_name as department_name,
               COUNT(DISTINCT pa.emp_id) as assigned_employees_count,
               GROUP_CONCAT(DISTINCT CONCAT(e.first_name, ' ', e.last_name) SEPARATOR ', ') as team_members
        FROM projects p
        LEFT JOIN users u ON p.manager_id = u.user_id
        LEFT JOIN employees m ON u.user_id = m.user_id
        LEFT JOIN departments d ON m.dept_id = d.dept_id
        LEFT JOIN project_assignments pa ON p.project_id = pa.project_id
        LEFT JOIN employees e ON pa.emp_id = e.emp_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apply filters
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $query .= " AND p.status = ?";
        $params[] = $_GET['status'];
    }
    
    if (isset($_GET['department']) && $_GET['department'] !== '') {
        $query .= " AND d.dept_id = ?";
        $params[] = $_GET['department'];
    }
    
    if (isset($_GET['manager']) && $_GET['manager'] !== '') {
        $query .= " AND p.manager_id = ?";
        $params[] = $_GET['manager'];
    }
    
    if (isset($_GET['start_date']) && $_GET['start_date'] !== '') {
        $query .= " AND p.start_date >= ?";
        $params[] = $_GET['start_date'];
    }
    
    if (isset($_GET['end_date']) && $_GET['end_date'] !== '') {
        $query .= " AND p.end_date <= ?";
        $params[] = $_GET['end_date'];
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

    // Fetch departments for filter
    $stmt = $pdo->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name");
    $departments = $stmt->fetchAll();

    // Fetch managers for filter and selection
    $stmt = $pdo->query("
        SELECT e.emp_id, e.user_id, CONCAT(e.first_name, ' ', e.last_name) as manager_name,
               d.dept_name
        FROM employees e 
        JOIN users u ON e.user_id = u.user_id 
        LEFT JOIN departments d ON e.dept_id = d.dept_id
        WHERE u.role = 'manager' 
        ORDER BY e.first_name
    ");
    $managers = $stmt->fetchAll();

    // Fetch employees for assignment
    $stmt = $pdo->query("
        SELECT e.emp_id, CONCAT(e.first_name, ' ', e.last_name) as employee_name,
               d.dept_name, e.position
        FROM employees e 
        JOIN users u ON e.user_id = u.user_id 
        LEFT JOIN departments d ON e.dept_id = d.dept_id
        WHERE u.role = 'employee' AND u.status = 'active'
        ORDER BY e.first_name
    ");
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
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

    /* Header Section */
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

    .page-header .btn-light {
        background: rgba(255, 255, 255, 0.15);
        border: none;
        color: white;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        backdrop-filter: blur(10px);
        transition: var(--transition);
    }

    .page-header .btn-light:hover {
        background: rgba(255, 255, 255, 0.25);
        transform: translateY(-2px);
    }

    /* Stats Cards */
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        transition: var(--transition);
        border: none;
        margin-bottom: 1.5rem;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
    }

    .stat-icon.blue {
        background: rgba(71, 99, 228, 0.1);
        color: var(--primary-blue);
    }

    .stat-icon.green {
        background: rgba(36, 180, 126, 0.1);
        color: var(--success-green);
    }

    .stat-icon.yellow {
        background: rgba(255, 182, 72, 0.1);
        color: var(--warning-yellow);
    }

    .stat-icon.red {
        background: rgba(255, 107, 107, 0.1);
        color: var(--danger-red);
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
        line-height: 1;
    }

    .stat-label {
        color: #6B7280;
        font-size: 0.875rem;
    }

    /* Filter Section */
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

    .form-control, .form-select {
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        transition: var(--transition);
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(71, 99, 228, 0.1);
    }

    .form-label {
        font-weight: 500;
        color: #374151;
        margin-bottom: 0.5rem;
    }

    /* Project Cards */
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

    .project-status.not-started { background: rgba(107, 114, 128, 0.1); color: #6B7280; }
    .project-status.in-progress { background: rgba(71, 99, 228, 0.1); color: var(--primary-blue); }
    .project-status.completed { background: rgba(36, 180, 126, 0.1); color: var(--success-green); }

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

    .project-info-item span {
        color: #374151;
        font-weight: 500;
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

    .btn-primary {
        background: var(--primary-blue);
        border: none;
    }

    .btn-success {
        background: var(--success-green);
        border: none;
    }

    .btn-warning {
        background: var(--warning-yellow);
        border: none;
    }

    .btn-danger {
        background: var(--danger-red);
        border: none;
    }

    /* Select2 Customization */
    .select2-container {
        width: 100% !important;
    }

    .select2-container--bootstrap5 .select2-selection {
        min-height: 50px !important;
        border: 1px solid rgba(71, 99, 228, 0.15) !important;
        border-radius: 15px !important;
        background: #fff !important;
        transition: all 0.3s ease;
        padding: 0.5rem !important;
    }

    .select2-container--bootstrap5 .select2-selection:hover {
        border-color: rgba(71, 99, 228, 0.3) !important;
        box-shadow: 0 2px 8px rgba(71, 99, 228, 0.1);
    }

    .select2-container--bootstrap5 .select2-selection--single,
    .select2-container--bootstrap5 .select2-selection--multiple {
        height: auto !important;
        min-height: 50px !important;
    }

    .select2-container--bootstrap5 .select2-selection--multiple .select2-selection__choice {
        background: #fff !important;
        border: 1px solid rgba(71, 99, 228, 0.15) !important;
        border-radius: 12px !important;
        padding: 0.4rem 0.75rem !important;
        margin: 0.2rem !important;
        color: #374151 !important;
        font-size: 0.875rem !important;
        font-weight: 500 !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
    }

    .select2-container--bootstrap5 .select2-selection_choice_remove {
        border: none !important;
        background: none !important;
        color: #9CA3AF !important;
        padding: 0 !important;
        margin-left: 0 !important;
        order: 2 !important;
    }

    .select2-container--bootstrap5 .select2-selection_choice_remove:hover {
        background: none !important;
        color: #EF4444 !important;
    }

    .select2-dropdown {
        border: none !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1) !important;
        border-radius: 12px !important;
        overflow: hidden !important;
        margin-top: 8px !important;
    }

    .select2-search--dropdown {
        padding: 1rem !important;
        background: #fff !important;
        border-bottom: 1px solid rgba(71, 99, 228, 0.1) !important;
    }

    .select2-search__field {
        border: 1px solid rgba(71, 99, 228, 0.15) !important;
        border-radius: 10px !important;
        padding: 0.75rem 1rem !important;
        font-size: 0.875rem !important;
        transition: all 0.3s ease !important;
    }

    .select2-search__field:focus {
        border-color: var(--primary-blue) !important;
        box-shadow: 0 0 0 3px rgba(71, 99, 228, 0.1) !important;
    }

    .select2-results__options {
        padding: 0.5rem !important;
        max-height: 300px !important;
    }

    .select2-results__option {
        margin: 0.25rem !important;
        border-radius: 10px !important;
        transition: all 0.2s ease !important;
    }

    .select2-results__option--highlighted {
        background: rgba(71, 99, 228, 0.05) !important;
        color: #374151 !important;
    }

    .select2-results__option--selected {
        background: rgba(71, 99, 228, 0.1) !important;
        color: var(--primary-blue) !important;
    }

    /* Custom user card template styles */
    .user-card {
        display: flex !important;
        align-items: center !important;
        padding: 0.75rem !important;
        gap: 1rem !important;
        background: #fff !important;
        border-radius: 10px !important;
        transition: all 0.2s ease !important;
    }

    .user-card:hover {
        background: rgba(71, 99, 228, 0.02) !important;
    }

    .user-avatar {
        width: 45px !important;
        height: 45px !important;
        background: #F3F4F6 !important;
        border-radius: 8px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-weight: 600 !important;
        color: var(--primary-blue) !important;
        font-size: 1rem !important;
        flex-shrink: 0 !important;
    }

    .user-info {
        flex: 1 !important;
        min-width: 0 !important;
    }

    .user-name {
        font-weight: 500 !important;
        color: #111827 !important;
        margin-bottom: 0.25rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .user-role {
        font-size: 0.75rem !important;
        color: #6B7280 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    /* Enhanced Team Members Display */
    .team-members-container {
        margin-top: 1.5rem;
        background: rgba(71, 99, 228, 0.02);
        border-radius: 16px;
        padding: 1.5rem;
        border: 1px solid rgba(71, 99, 228, 0.1);
    }

    .team-members-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .team-members-title {
        font-weight: 600;
        color: #111827;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .team-members-count {
        background: rgba(71, 99, 228, 0.1);
        color: var(--primary-blue);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .team-members-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
    }

    .team-member-card {
        background: white;
        border-radius: 14px;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
        border: 1px solid rgba(71, 99, 228, 0.1);
    }

    .team-member-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(71, 99, 228, 0.1);
        border-color: rgba(71, 99, 228, 0.2);
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
        box-shadow: 0 4px 10px rgba(71, 99, 228, 0.2);
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

    /* Modal Customization */
    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    }

    .modal-header {
        background: var(--primary-blue);
        color: white;
        border-radius: 16px 16px 0 0;
        padding: 1.5rem;
    }

    .modal-header .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.75;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        padding: 1.25rem 1.5rem;
        background: rgba(71, 99, 228, 0.02);
        border-top: 1px solid rgba(71, 99, 228, 0.1);
    }

    /* Alert Styling */
    .alert {
        border-radius: 12px;
        border: none;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .alert-success {
        background: rgba(36, 180, 126, 0.1);
        color: var(--success-green);
    }

    .alert-danger {
        background: rgba(255, 107, 107, 0.1);
        color: var(--danger-red);
    }

    /* Responsive Design */
    @media (max-width: 991.98px) {
        .stat-card {
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

    @media (max-width: 767.98px) {
        .page-header {
            padding: 1.5rem 0;
        }
        
        .project-status {
            position: static;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .project-title {
            padding-right: 0;
        }
    }

    @media (max-width: 575.98px) {
        .project-info-item {
            flex-direction: column;
            align-items: flex-start;
            text-align: center;
            padding: 1rem;
        }
        
        .project-info-item i {
            margin: 0 auto 0.5rem;
        }
    }

    /* Manager Selection Styles */
    .manager-selection-container {
        background: #f8f9fe;
        border-radius: 16px;
        padding: 1rem;
        border: 1px solid rgba(71, 99, 228, 0.1);
    }

    .manager-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
        max-height: 300px;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .manager-card {
        background: white;
        border-radius: 12px;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.3s ease;
        position: relative;
    }

    .manager-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(71, 99, 228, 0.1);
    }

    .manager-card.selected {
        border-color: var(--primary-blue);
        background: rgba(71, 99, 228, 0.02);
    }

    .manager-avatar {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, var(--primary-blue), #6282FF);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .manager-info {
        flex: 1;
        min-width: 0;
    }

    .manager-name {
        font-weight: 600;
        color: #111827;
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .manager-dept {
        font-size: 0.875rem;
        color: #6B7280;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .manager-dept i {
        color: var(--primary-blue);
        opacity: 0.7;
    }

    .manager-select-indicator {
        position: absolute;
        top: 0.75rem;
        right: 0.75rem;
        color: var(--primary-blue);
        opacity: 0;
        transition: all 0.3s ease;
    }

    .manager-card.selected .manager-select-indicator {
        opacity: 1;
    }

    /* Team Selection Styles */
    .team-selection-container {
        background: #f8f9fe;
        border-radius: 16px;
        padding: 1.5rem;
        border: 1px solid rgba(71, 99, 228, 0.1);
    }

    .search-box {
        position: relative;
        margin-bottom: 1rem;
    }

    .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #6B7280;
    }

    .search-input {
        padding-left: 2.5rem;
        height: 48px;
        border-radius: 12px;
        border: 1px solid rgba(71, 99, 228, 0.15);
        background: white;
    }

    .search-input:focus {
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(71, 99, 228, 0.1);
    }

    .team-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
        max-height: 400px;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .team-card {
        background: white;
        border-radius: 14px;
        padding: 1.25rem;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }

    .team-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(71, 99, 228, 0.1);
    }

    .team-card.selected {
        border-color: var(--primary-blue);
        background: rgba(71, 99, 228, 0.02);
    }

    .team-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .team-avatar {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, var(--primary-blue), #6282FF);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 1.2rem;
    }

    .team-select-checkbox {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        border: 2px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        transition: all 0.3s ease;
    }

    .team-card.selected .team-select-checkbox {
        background: var(--primary-blue);
        border-color: var(--primary-blue);
    }

    .team-card.selected .team-select-checkbox i {
        opacity: 1;
        transform: scale(1);
    }

    .team-select-checkbox i {
        opacity: 0;
        transform: scale(0.5);
        transition: all 0.3s ease;
    }

    .team-info {
        min-width: 0;
    }

    .team-name {
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .team-position,
    .team-department {
        font-size: 0.875rem;
        color: #6B7280;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 4px;
    }

    .team-position i,
    .team-department i {
        color: var(--primary-blue);
        opacity: 0.7;
    }

    .selected-count {
        text-align: right;
    }

    .selected-count .badge {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
    }

    /* Project View Modal Styles */
    .project-details {
        padding: 0.5rem;
    }

    .project-details h3 {
        color: var(--bs-dark);
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .project-details .badge {
        font-size: 0.85rem;
        padding: 0.5rem 1rem;
        border-radius: 6px;
    }

    .info-group {
        background: var(--bs-light);
        padding: 1rem;
        border-radius: 8px;
        height: 100%;
        transition: all 0.3s ease;
    }

    .info-group:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .info-group h6 {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-group p {
        margin-bottom: 0;
        font-weight: 500;
    }

    .progress {
        background-color: var(--bs-light);
        border-radius: 8px;
        overflow: hidden;
    }

    .progress-bar {
        transition: width 0.6s ease;
        border-radius: 8px;
    }

    .team-members-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .team-member-card {
        background: var(--bs-light);
        padding: 1rem;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: all 0.3s ease;
    }

    .team-member-card:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .team-member-avatar {
        width: 40px;
        height: 40px;
        background: var(--bs-primary);
        color: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1rem;
    }

    .team-member-info {
        flex: 1;
    }

    .team-member-name {
        font-weight: 500;
        margin-bottom: 0.25rem;
        color: var(--bs-dark);
        font-size: 0.9rem;
        line-height: 1.2;
    }

    .team-member-role {
        color: var(--bs-gray-600);
        font-size: 0.8rem;
        margin: 0;
    }

    #viewProjectModal .modal-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    #viewProjectModal .modal-footer {
        border-top: 1px solid rgba(0, 0, 0, 0.1);
    }

    @media (max-width: 768px) {
        .team-members-grid {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        }
        
        .team-member-card {
            flex-direction: column;
            text-align: center;
            padding: 0.75rem;
        }
        
        .team-member-avatar {
            margin: 0 auto;
        }
    }

    /* View Modal Styles */
    #viewProjectModal .modal-header {
        border-radius: 0.5rem 0.5rem 0 0;
        padding: 1.5rem;
    }

    #viewProjectModal .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
    }

    #viewProjectModal #view_project_name {
        font-size: 1.75rem;
        font-weight: 600;
        color: #2d3748;
    }

    #viewProjectModal .badge {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        border-radius: 0.5rem;
    }

    .project-info-section,
    .project-progress-section {
        background: #f8fafc;
        border-radius: 1rem;
        padding: 1.5rem;
    }

    .section-title {
        color: #2d3748;
        font-weight: 600;
        font-size: 1rem;
    }

    .info-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 0.75rem;
        background: white;
        border-radius: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .info-item i {
        width: 2rem;
        height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(79, 70, 229, 0.1);
        border-radius: 0.5rem;
        color: #4f46e5;
    }

    .info-item label {
        font-size: 0.875rem;
        color: #64748b;
        margin-bottom: 0.25rem;
    }

    .info-item p {
        font-size: 1rem;
        color: #1e293b;
        margin: 0;
        font-weight: 500;
    }

    .progress {
        background-color: rgba(79, 70, 229, 0.1);
        border-radius: 1rem;
        overflow: hidden;
    }

    .progress-bar {
        background: linear-gradient(to right, #4f46e5, #6366f1);
        border-radius: 1rem;
    }

    #view_progress_text {
        color: #4f46e5;
        font-weight: 500;
    }

    .team-section {
        background: #f8fafc;
        border-radius: 1rem;
        padding: 1.5rem;
        margin-top: 1.5rem;
    }

    .team-members-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .team-member-card {
        background: white;
        border-radius: 0.75rem;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
    }

    .team-member-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .member-avatar {
        width: 2.5rem;
        height: 2.5rem;
        background: linear-gradient(135deg, #4f46e5, #6366f1);
        border-radius: 0.5rem;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1rem;
    }

    .member-info {
        flex: 1;
        min-width: 0;
    }

    .member-name {
        font-weight: 500;
        color: #1e293b;
        margin-bottom: 0.25rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .member-role {
        font-size: 0.875rem;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .member-role i {
        font-size: 0.75rem;
        opacity: 0.75;
    }

    @media (max-width: 768px) {
        .team-members-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        }
    }

    /* Project Cards Styling */
    .project-card-wrapper {
        transition: transform 0.3s ease;
    }

    .project-card-wrapper:hover {
        transform: translateY(-5px);
    }

    .project-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .project-card-header {
        padding: 1.5rem;
        background: rgba(79, 70, 229, 0.03);
        border-bottom: 1px solid rgba(79, 70, 229, 0.1);
        position: relative;
    }

    .project-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 1rem;
        line-height: 1.2;
    }

    .project-status {
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .project-card-body {
        padding: 1.5rem;
        flex: 1;
    }

    .project-info {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .info-item {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 0.75rem;
        background: #f8fafc;
        border-radius: 0.75rem;
    }

    .info-item i {
        width: 2rem;
        height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(79, 70, 229, 0.1);
        border-radius: 0.5rem;
        color: #4f46e5;
    }

    .info-item label {
        font-size: 0.75rem;
        color: #64748b;
        margin-bottom: 0.25rem;
        display: block;
    }

    .info-item p {
        font-size: 0.875rem;
        color: #1e293b;
        margin: 0;
        font-weight: 500;
    }

    .project-card-footer {
        padding: 1rem 1.5rem;
        background: #f8fafc;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
    }

    .project-card-footer .btn {
        width: 2.5rem;
        height: 2.5rem;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.5rem;
        transition: all 0.3s ease;
    }

    .project-card-footer .btn:hover {
        transform: translateY(-2px);
    }

    .search-box {
        position: relative;
        max-width: 300px;
    }

    .search-box i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
    }

    .search-box input {
        padding-left: 2.5rem;
        border-radius: 0.75rem;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

    #projectsPerPage {
        border-radius: 0.5rem;
        border: 1px solid rgba(0, 0, 0, 0.1);
        padding: 0.375rem 2rem 0.375rem 1rem;
    }

    @media (max-width: 768px) {
        .project-card-wrapper {
            margin-bottom: 1rem;
        }
    }

    /* Updated Project Cards Styling */
    .project-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .project-card-header {
        padding: 1.5rem;
        background: white;
    }

    .project-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .status-badge.completed {
        background: #dcfce7;
        color: #166534;
    }

    .status-badge.in-progress {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-badge.not-started {
        background: #f3f4f6;
        color: #374151;
    }

    .manager-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .avatar-wrapper {
        flex-shrink: 0;
    }

    .avatar {
        width: 48px;
        height: 48px;
        background: #4f46e5;
        color: white;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1.125rem;
    }

    .manager-details {
        flex: 1;
        min-width: 0;
    }

    .manager-details .label {
        font-size: 0.875rem;
        color: #64748b;
        margin-bottom: 0.25rem;
    }

    .manager-details .name {
        font-weight: 500;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .project-card-body {
        padding: 0 1.5rem 1.5rem;
        flex: 1;
    }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }

    .info-column {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .info-item .label {
        font-size: 0.875rem;
        color: #64748b;
        margin-bottom: 0.25rem;
    }

    .info-item .value {
        font-weight: 500;
        color: #1e293b;
    }

    .team-avatars {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .team-avatar {
        width: 32px;
        height: 32px;
        background: #4f46e5;
        color: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .team-avatar.more {
        background: #f3f4f6;
        color: #4f46e5;
    }

    .project-card-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid #f3f4f6;
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
    }

    .btn-icon {
        width: 36px;
        height: 36px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        background: white;
        color: #4f46e5;
        transition: all 0.2s ease;
    }

    .btn-icon:hover {
        background: #4f46e5;
        color: white;
        border-color: #4f46e5;
    }

    .btn-icon.btn-danger {
        color: #ef4444;
        border-color: #fecaca;
    }

    .btn-icon.btn-danger:hover {
        background: #ef4444;
        color: white;
        border-color: #ef4444;
    }

    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
    }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>

        <div class="container-fluid">
            <div class="dashboard-header mb-4" style="border-radius: 16px; box-shadow: 0 2px 16px rgba(30,34,90,0.07); padding: 2rem 2rem 1.5rem 2rem; background: #fff;">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div class="pe-3">
                        <h1 class="fw-bold mb-1" style="font-size:2rem; color:#222;">Project Management</h1>
                        <div class="text-muted" style="font-size:1.1rem;">Manage all company projects and assignments</div>
                    </div>
                    <button class="btn btn-primary fw-bold px-4 py-2 d-flex align-items-center" style="font-size:1rem; border-radius:8px; box-shadow:0 2px 8px rgba(78,115,223,0.08);" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                        <i class="fas fa-plus me-2"></i> ADD NEW PROJECT
                    </button>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success; ?></span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Add statistics cards -->
            <div class="container-fluid">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon blue">
                                <i class="fas fa-tasks fa-lg"></i>
                            </div>
                            <div class="stat-value text-primary"><?php echo count($projects); ?></div>
                            <div class="stat-label">Total Projects</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon green">
                                <i class="fas fa-check-circle fa-lg"></i>
                            </div>
                            <div class="stat-value text-success">
                                <?php echo count(array_filter($projects, function($p) { return $p['status'] === 'completed'; })); ?>
                            </div>
                            <div class="stat-label">Completed Projects</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon yellow">
                                <i class="fas fa-clock fa-lg"></i>
                            </div>
                            <div class="stat-value text-warning">
                                <?php echo count(array_filter($projects, function($p) { return $p['status'] === 'in_progress'; })); ?>
                            </div>
                            <div class="stat-label">In Progress</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon red">
                                <i class="fas fa-hourglass-start fa-lg"></i>
                            </div>
                            <div class="stat-value text-danger">
                                <?php echo count(array_filter($projects, function($p) { return $p['status'] === 'not_started'; })); ?>
                            </div>
                            <div class="stat-label">Not Started</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-card">
                <div class="filter-header">
                    <i class="fas fa-filter me-2"></i>Filter Projects
                </div>
                <div class="filter-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['dept_id']; ?>" 
                                            <?php echo isset($_GET['department']) && $_GET['department'] == $dept['dept_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Manager</label>
                            <select class="form-select" name="manager">
                                <option value="">All Managers</option>
                                <?php foreach ($managers as $manager): ?>
                                    <option value="<?php echo $manager['user_id']; ?>" 
                                            <?php echo isset($_GET['manager']) && $_GET['manager'] == $manager['user_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($manager['manager_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="not_started" <?php echo isset($_GET['status']) && $_GET['status'] === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                                <option value="in_progress" <?php echo isset($_GET['status']) && $_GET['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $_GET['start_date'] ?? ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $_GET['end_date'] ?? ''; ?>">
                        </div>
                        <div class="col-12">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Search projects..." value="<?php echo $_GET['search'] ?? ''; ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search me-2"></i>Filter
                                </button>
                                <?php if (!empty($_GET)): ?>
                                    <a href="projects.php" class="btn btn-secondary">
                                        <i class="fas fa-undo me-2"></i>Reset
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Replace the existing project card HTML -->
            <div class="container-fluid">
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <label class="mb-0">Show</label>
                                <select class="form-select form-select-sm w-auto" id="projectsPerPage">
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <label class="mb-0">entries</label>
                            </div>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" id="projectSearch" placeholder="Search projects...">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" id="projectsGrid">
                    <?php if (isset($projects)) : ?>
                        <?php foreach ($projects as $project): ?>
                            <div class="col-md-6 col-lg-4 mb-4 project-card-wrapper">
                                <div class="project-card">
                                    <div class="project-card-header">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="project-title"><?php echo htmlspecialchars($project['project_name']); ?></h5>
                                            <span class="status-badge <?php 
                                                echo $project['status'] === 'completed' ? 'completed' : 
                                                    ($project['status'] === 'in_progress' ? 'in-progress' : 
                                                    'not-started'); 
                                            ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $project['status'])); ?>
                                            </span>
                                        </div>
                                        <div class="manager-info">
                                            <div class="avatar-wrapper">
                                                <div class="avatar">
                                                    <?php
                                                    $managerName = $project['manager_name'] ?? 'Not Assigned';
                                                    $initials = implode('', array_map(function($name) {
                                                        return strtoupper(substr($name, 0, 1));
                                                    }, explode(' ', $managerName)));
                                                    echo $initials;
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="manager-details">
                                                <div class="label">Project Manager</div>
                                                <div class="name"><?php echo htmlspecialchars($managerName); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="project-card-body">
                                        <div class="info-grid">
                                            <div class="info-column">
                                                <div class="info-item">
                                                    <div class="label">Start Date</div>
                                                    <div class="value"><?php echo date('M d, Y', strtotime($project['start_date'])); ?></div>
                                                </div>
                                                <div class="info-item">
                                                    <div class="label">Team Size</div>
                                                    <div class="value"><?php echo $project['assigned_employees_count']; ?> Members</div>
                                                </div>
                                            </div>
                                            <div class="info-column">
                                                <div class="info-item">
                                                    <div class="label">End Date</div>
                                                    <div class="value"><?php echo date('M d, Y', strtotime($project['end_date'])); ?></div>
                                                </div>
                                                <div class="info-item">
                                                    <div class="label">Department</div>
                                                    <div class="value"><?php echo htmlspecialchars($project['department_name'] ?? 'Not Assigned'); ?></div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if (!empty($project['team_members'])): ?>
                                            <div class="team-avatars mt-3">
                                                <?php
                                                $teamMembers = explode(', ', $project['team_members']);
                                                foreach (array_slice($teamMembers, 0, 3) as $member):
                                                    $memberInitials = implode('', array_map(function($name) {
                                                        return strtoupper(substr($name, 0, 1));
                                                    }, explode(' ', $member)));
                                                ?>
                                                    <div class="team-avatar"><?php echo $memberInitials; ?></div>
                                                <?php endforeach; ?>
                                                <?php if (count($teamMembers) > 3): ?>
                                                    <div class="team-avatar more">+<?php echo count($teamMembers) - 3; ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="project-card-footer">
                                        <button class="btn btn-icon" onclick="viewProject(<?php echo htmlspecialchars(json_encode($project)); ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-icon" onclick="editProject(<?php echo htmlspecialchars(json_encode($project)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-icon btn-danger" onclick="deleteProject(<?php echo $project['project_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <script>
            // Add this to your existing JavaScript
            $(document).ready(function() {
                // Search functionality
                $('#projectSearch').on('input', function() {
                    const searchTerm = $(this).val().toLowerCase();
                    $('.project-card-wrapper').each(function() {
                        const projectName = $(this).find('.project-title').text().toLowerCase();
                        const managerName = $(this).find('.info-item:first p').text().toLowerCase();
                        const shouldShow = projectName.includes(searchTerm) || managerName.includes(searchTerm);
                        $(this).toggle(shouldShow);
                    });
                });

                // Entries per page functionality
                $('#projectsPerPage').on('change', function() {
                    const perPage = parseInt($(this).val());
                    $('.project-card-wrapper').each(function(index) {
                        $(this).toggle(index < perPage);
                    });
                });
            });
            </script>

            <!-- Add/Edit Project Modal -->
            <div class="modal fade" id="addProjectModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New Project</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add">
                                
                                <div class="mb-3">
                                    <label class="form-label">Project Name</label>
                                    <input type="text" class="form-control" name="project_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3" required></textarea>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Project Manager</label>
                                        <div class="manager-selection-container">
                                            <div class="manager-cards-grid">
                                                <?php foreach ($managers as $manager): ?>
                                                    <div class="manager-card" data-value="<?php echo $manager['user_id']; ?>">
                                                        <div class="manager-avatar">
                                                            <?php
                                                            $initials = implode('', array_map(function($name) {
                                                                return strtoupper(substr($name, 0, 1));
                                                            }, explode(' ', $manager['manager_name'])));
                                                            echo $initials;
                                                            ?>
                                                        </div>
                                                        <div class="manager-info">
                                                            <div class="manager-name"><?php echo htmlspecialchars($manager['manager_name']); ?></div>
                                                            <div class="manager-dept">
                                                                <i class="fas fa-building"></i>
                                                                <?php echo htmlspecialchars($manager['dept_name'] ?? 'No Department'); ?>
                                                            </div>
                                                        </div>
                                                        <div class="manager-select-indicator">
                                                            <i class="fas fa-check-circle"></i>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <input type="hidden" name="manager_id" id="selected_manager_id" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status" required>
                                            <option value="not_started">Not Started</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="completed">Completed</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Team Members</label>
                                    <div class="team-selection-container">
                                        <div class="search-box mb-3">
                                            <i class="fas fa-search search-icon"></i>
                                            <input type="text" class="form-control search-input" placeholder="Search team members...">
                                        </div>
                                        <div class="team-cards-grid">
                                            <?php foreach ($employees as $employee): ?>
                                                <div class="team-card" data-value="<?php echo $employee['emp_id']; ?>" 
                                                     data-name="<?php echo strtolower($employee['employee_name']); ?>"
                                                     data-department="<?php echo strtolower($employee['dept_name']); ?>">
                                                    <div class="team-card-header">
                                                        <div class="team-avatar">
                                                            <?php
                                                            $initials = implode('', array_map(function($name) {
                                                                return strtoupper(substr($name, 0, 1));
                                                            }, explode(' ', $employee['employee_name'])));
                                                            echo $initials;
                                                            ?>
                                                        </div>
                                                        <div class="team-select-checkbox">
                                                            <i class="fas fa-check"></i>
                                                        </div>
                                                    </div>
                                                    <div class="team-info">
                                                        <div class="team-name"><?php echo htmlspecialchars($employee['employee_name']); ?></div>
                                                        <div class="team-position">
                                                            <i class="fas fa-briefcase"></i>
                                                            <?php echo htmlspecialchars($employee['position']); ?>
                                                        </div>
                                                        <div class="team-department">
                                                            <i class="fas fa-building"></i>
                                                            <?php echo htmlspecialchars($employee['dept_name'] ?? 'No Department'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="assigned_employees[]" id="selected_team_members" required>
                                    </div>
                                    <div class="selected-count mt-2">
                                        <span class="badge bg-primary">0 members selected</span>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Project</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- View Project Modal -->
            <div class="modal fade" id="viewProjectModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">Project Details</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-4">
                                <h3 id="view_project_name" class="mb-2"></h3>
                                <span id="view_project_status" class="badge"></span>
                            </div>

                            <p id="view_project_description" class="text-muted mb-4"></p>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="project-info-section">
                                        <h6 class="section-title mb-3">Project Information</h6>
                                        <div class="info-item mb-3">
                                        <i class="fas fa-calendar-alt"></i>
                                            <div>
                                                <label>Start Date</label>
                                                <p id="view_start_date"></p>
                                            </div>
                                        </div>
                                        <div class="info-item mb-3">
                                        <i class="fas fa-calendar-check"></i>
                                            <div>
                                                <label>End Date</label>
                                                <p id="view_end_date"></p>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-building text-primary"></i>
                                            <div>
                                                <label>Department</label>
                                                <p id="view_department"></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="project-progress-section">
                                        <h6 class="section-title mb-3">Progress</h6>
                                        <div class="progress-info mb-2">
                                            <div class="progress" style="height: 10px;">
                                                <div id="view_progress_bar" class="progress-bar" role="progressbar"></div>
                                            </div>
                                            <p id="view_progress_text" class="text-end mt-2 mb-0"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="team-section">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="section-title mb-0">Team Members</h6>
                                    <span id="view_team_count" class="badge bg-primary"></span>
                                </div>
                                <div id="view_team_members" class="team-members-grid"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Project Modal -->
            <div class="modal fade" id="editProjectModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Project</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="project_id" id="edit_project_id">
                                
                                <div class="mb-3">
                                    <label class="form-label">Project Name</label>
                                    <input type="text" class="form-control" name="project_name" id="edit_project_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" id="edit_description" rows="3" required></textarea>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date" id="edit_start_date" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date" id="edit_end_date" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Project Manager</label>
                                        <div class="manager-selection-container">
                                            <div class="manager-cards-grid">
                                                <?php foreach ($managers as $manager): ?>
                                                    <div class="manager-card" data-value="<?php echo $manager['user_id']; ?>">
                                                        <div class="manager-avatar">
                                                            <?php
                                                            $initials = implode('', array_map(function($name) {
                                                                return strtoupper(substr($name, 0, 1));
                                                            }, explode(' ', $manager['manager_name'])));
                                                            echo $initials;
                                                            ?>
                                                        </div>
                                                        <div class="manager-info">
                                                            <div class="manager-name"><?php echo htmlspecialchars($manager['manager_name']); ?></div>
                                                            <div class="manager-dept">
                                                                <i class="fas fa-building"></i>
                                                                <?php echo htmlspecialchars($manager['dept_name'] ?? 'No Department'); ?>
                                                            </div>
                                                        </div>
                                                        <div class="manager-select-indicator">
                                                            <i class="fas fa-check-circle"></i>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <input type="hidden" name="manager_id" id="edit_selected_manager_id" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status" id="edit_status" required>
                                            <option value="not_started">Not Started</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="completed">Completed</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Team Members</label>
                                    <div class="team-selection-container">
                                        <div class="search-box mb-3">
                                            <i class="fas fa-search search-icon"></i>
                                            <input type="text" class="form-control search-input" placeholder="Search team members...">
                                        </div>
                                        <div class="team-cards-grid">
                                            <?php foreach ($employees as $employee): ?>
                                                <div class="team-card" data-value="<?php echo $employee['emp_id']; ?>" 
                                                     data-name="<?php echo strtolower($employee['employee_name']); ?>"
                                                     data-department="<?php echo strtolower($employee['dept_name']); ?>">
                                                    <div class="team-card-header">
                                                        <div class="team-avatar">
                                                            <?php
                                                            $initials = implode('', array_map(function($name) {
                                                                return strtoupper(substr($name, 0, 1));
                                                            }, explode(' ', $employee['employee_name'])));
                                                            echo $initials;
                                                            ?>
                                                        </div>
                                                        <div class="team-select-checkbox">
                                                            <i class="fas fa-check"></i>
                                                        </div>
                                                    </div>
                                                    <div class="team-info">
                                                        <div class="team-name"><?php echo htmlspecialchars($employee['employee_name']); ?></div>
                                                        <div class="team-position">
                                                            <i class="fas fa-briefcase"></i>
                                                            <?php echo htmlspecialchars($employee['position']); ?>
                                                        </div>
                                                        <div class="team-department">
                                                            <i class="fas fa-building"></i>
                                                            <?php echo htmlspecialchars($employee['dept_name'] ?? 'No Department'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="hidden" name="assigned_employees[]" id="edit_selected_team_members" required>
                                    </div>
                                    <div class="selected-count mt-2">
                                        <span class="badge bg-primary">0 members selected</span>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Project</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div class="modal fade" id="deleteModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Delete Project</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete this project? This action cannot be undone.</p>
                        </div>
                        <div class="modal-footer">
                            <form method="POST" id="deleteProjectForm">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="project_id" id="delete_project_id">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
            <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
            <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

            <script>
            $(document).ready(function() {
                // Initialize Select2
                $('.select2').select2({
                    theme: 'bootstrap-5'
                });

                // Common function to handle manager card selection
                function handleManagerSelection(modalId) {
                    $(`${modalId} .manager-card`).off('click').on('click', function() {
                        const managerId = $(this).data('value');
                        $(`${modalId} .manager-card`).removeClass('selected');
                        $(this).addClass('selected');
                        $(`${modalId} #${modalId === '#editProjectModal' ? 'edit_' : ''}selected_manager_id`).val(managerId);
                    });
                }

                // Common function to handle team member card selection
                function handleTeamMemberSelection(modalId) {
                    $(`${modalId} .team-card`).off('click').on('click', function() {
                        $(this).toggleClass('selected');
                        updateSelectedMembersCount(modalId);
                    });

                    // Search functionality
                    $(`${modalId} .search-input`).off('input').on('input', function() {
                        const searchTerm = $(this).val().toLowerCase();
                        $(`${modalId} .team-card`).each(function() {
                            const name = $(this).data('name').toLowerCase();
                            const department = $(this).data('department').toLowerCase();
                            const shouldShow = name.includes(searchTerm) || department.includes(searchTerm);
                            $(this).toggle(shouldShow);
                        });
                    });
                }

                // Update selected members count and hidden input
                function updateSelectedMembersCount(modalId) {
                    const selectedCards = $(`${modalId} .team-card.selected`);
                    const selectedIds = selectedCards.map(function() {
                        return $(this).data('value');
                    }).get();
                    
                    const prefix = modalId === '#editProjectModal' ? 'edit_' : '';
                    $(`${modalId} #${prefix}selected_team_members`).val(selectedIds.join(','));
                    $(`${modalId} .selected-count .badge`).text(selectedIds.length + ' members selected');
                }

                // Initialize handlers for both modals
                handleManagerSelection('#addProjectModal');
                handleManagerSelection('#editProjectModal');
                handleTeamMemberSelection('#addProjectModal');
                handleTeamMemberSelection('#editProjectModal');

                // Edit project function
                window.editProject = function(project) {
                    // Set basic form values
                    $('#edit_project_id').val(project.project_id);
                    $('#edit_project_name').val(project.project_name);
                    $('#edit_description').val(project.description);
                    $('#edit_start_date').val(project.start_date.split(' ')[0]);
                    $('#edit_end_date').val(project.end_date.split(' ')[0]);
                    $('#edit_status').val(project.status);
                    
                    // Reset all selections first
                    $('#editProjectModal .manager-card').removeClass('selected');
                    $('#editProjectModal .team-card').removeClass('selected');
                    
                    // Set selected manager
                    const managerCard = $(`#editProjectModal .manager-card[data-value="${project.manager_id}"]`);
                    if (managerCard.length) {
                        managerCard.addClass('selected');
                        $('#edit_selected_manager_id').val(project.manager_id);
                    }
                    
                    // Set selected team members
                    if (project.team_members) {
                        const teamMembers = project.team_members.split(', ');
                        let selectedCount = 0;
                        
                        teamMembers.forEach(memberName => {
                            const memberCard = $('#editProjectModal .team-card').filter(function() {
                                return $(this).find('.team-name').text().trim() === memberName.trim();
                            });
                            
                            if (memberCard.length) {
                                memberCard.addClass('selected');
                                selectedCount++;
                            }
                        });
                        
                        // Update the count and hidden input
                        updateSelectedMembersCount('#editProjectModal');
                    } else {
                        $('#editProjectModal .selected-count .badge').text('0 members selected');
                        $('#edit_selected_team_members').val('');
                    }

                    // Reinitialize handlers for the edit modal
                    handleManagerSelection('#editProjectModal');
                    handleTeamMemberSelection('#editProjectModal');
                    
                    // Show the modal
                    $('#editProjectModal').modal('show');
                };

                // Form validation for both modals
                $('#addProjectModal form, #editProjectModal form').on('submit', function(e) {
                    const modalId = '#' + $(this).closest('.modal').attr('id');
                    const startDate = new Date($(this).find('input[name="start_date"]').val());
                    const endDate = new Date($(this).find('input[name="end_date"]').val());
                    
                    if (endDate < startDate) {
                        e.preventDefault();
                        alert('End date cannot be before start date');
                        return false;
                    }
                    
                    const prefix = modalId === '#editProjectModal' ? 'edit_' : '';
                    const managerId = $(`${modalId} #${prefix}selected_manager_id`).val();
                    if (!managerId) {
                        e.preventDefault();
                        alert('Please select a project manager');
                        return false;
                    }
                    
                    const teamMembers = $('#edit_selected_team_members').val();
                    if (!teamMembers) {
                        e.preventDefault();
                        alert('Please select at least one team member');
                        return false;
                    }
                });

                // Delete project function
                window.deleteProject = function(projectId) {
                    $('#delete_project_id').val(projectId);
                    $('#deleteModal').modal('show');
                };

                // Initialize DataTable
                $('#projectsTable').DataTable({
                    responsive: true,
                    order: [[2, 'desc']], // Sort by start date by default
                    pageLength: 10,
                    language: {
                        search: "",
                        searchPlaceholder: "Search projects..."
                    }
                });

                // View project details
                window.viewProject = function(project) {
                    // Set basic project information
                    $('#view_project_name').text(project.project_name);
                    $('#view_project_description').text(project.description || 'No description available');
                    $('#view_manager_name').text(project.manager_name || 'Not Assigned');
                    $('#view_start_date').text(new Date(project.start_date).toLocaleDateString());
                    $('#view_end_date').text(new Date(project.end_date).toLocaleDateString());
                    $('#view_department').text(project.department_name || 'N/A');
                    
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

                    $('#view_team_count').text(`${teamMembers.length} Members`);
                    
                    // Show the modal
                    $('#viewProjectModal').modal('show');
                };

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

                // Update form submission handling
                $('#editProjectModal form').on('submit', function(e) {
                    e.preventDefault();
                    
                    const startDate = new Date($('#edit_start_date').val());
                    const endDate = new Date($('#edit_end_date').val());
                    
                    if (endDate < startDate) {
                        alert('End date cannot be before start date');
                        return false;
                    }
                    
                    const managerId = $('#edit_selected_manager_id').val();
                    if (!managerId) {
                        alert('Please select a project manager');
                        return false;
                    }
                    
                    // Get all selected team members
                    const selectedCards = $('#editProjectModal .team-card.selected');
                    if (selectedCards.length === 0) {
                        alert('Please select at least one team member');
                        return false;
                    }
                    
                    // Create hidden inputs for each selected team member
                    $('#editProjectModal input[name="assigned_employees[]"]').remove();
                    selectedCards.each(function() {
                        const empId = $(this).data('value');
                        $('#editProjectModal form').append(
                            `<input type="hidden" name="assigned_employees[]" value="${empId}">`
                        );
                    });
                    
                    // Submit the form
                    this.submit();
                });
            });
            </script>
        </div>
    </div>
</body>
</html>