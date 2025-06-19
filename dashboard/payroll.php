<?php
session_start();
require_once '../config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit();
}

// Initialize variables
$success = $error = '';
$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$role = $_SESSION['role'];

// Fetch payroll records based on role
try {
    if ($role === 'admin') {
        // Admin can see all payroll records
        $query = "
            SELECT 
                p.*,
                CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                d.dept_name as department,
                pp.start_date,
                pp.end_date,
                COALESCE(pa.amount, 0) as bonus_amount
            FROM payroll p
            JOIN employees e ON p.employee_id = e.emp_id
            JOIN departments d ON e.dept_id = d.dept_id
            JOIN payroll_periods pp ON p.period_id = pp.period_id
            LEFT JOIN payroll_adjustments pa ON p.payroll_id = pa.payroll_id AND pa.adjustment_type = 'bonus'
            WHERE DATE_FORMAT(pp.start_date, '%Y-%m') = ?
            ORDER BY pp.start_date DESC, p.created_at DESC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$current_month]);
        $payrolls = $stmt->fetchAll();
    } else {
        // Manager and employee can only see their own payroll records
        $query = "
            SELECT 
                p.*,
                CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                d.dept_name as department,
                pp.start_date,
                pp.end_date,
                COALESCE(pa.amount, 0) as bonus_amount
            FROM payroll p
            JOIN employees e ON p.employee_id = e.emp_id
            JOIN departments d ON e.dept_id = d.dept_id
            JOIN payroll_periods pp ON p.period_id = pp.period_id
            LEFT JOIN payroll_adjustments pa ON p.payroll_id = pa.payroll_id AND pa.adjustment_type = 'bonus'
            WHERE p.employee_id = ?
            AND DATE_FORMAT(pp.start_date, '%Y-%m') = ?
            ORDER BY pp.start_date DESC, p.created_at DESC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['user_id'], $current_month]);
        $payrolls = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error = "Error fetching payroll data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($role); ?> Payroll - EMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
        }

        .main-content {
            background-color: #f8f9fc;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            padding: 2rem 0;
            margin-bottom: 2rem;
            color: white;
            border-radius: 0 0 1rem 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 1.25rem 1.5rem;
        }

        .card-header h6 {
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .payslip {
            background: white;
            border-radius: 0.5rem;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .payslip-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e3e6f0;
        }

        .payslip-header h4 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .payslip-header p {
            color: var(--secondary-color);
            margin-bottom: 0;
        }

        .payslip-details {
            margin-bottom: 2rem;
        }

        .payslip-details .row {
            margin-bottom: 1rem;
        }

        .payslip-details label {
            font-weight: 600;
            color: var(--dark-color);
        }

        .payslip-details .value {
            text-align: right;
            color: var(--dark-color);
        }

        .payslip-summary {
            background-color: #f8f9fc;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }

        .payslip-summary h5 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .payslip-summary .row {
            margin-bottom: 0.5rem;
        }

        .payslip-summary label {
            font-weight: 600;
            color: var(--dark-color);
        }

        .payslip-summary .value {
            text-align: right;
            font-weight: 700;
            color: var(--dark-color);
        }

        .payslip-summary .total {
            border-top: 2px solid #e3e6f0;
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .payslip-summary .total .value {
            color: var(--primary-color);
            font-size: 1.25rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-radius: 0.35rem;
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            border: none;
        }

        .form-control, .form-select {
            border-radius: 0.35rem;
            border: 1px solid #d1d3e2;
            padding: 0.75rem 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .alert {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        @media print {
            .page-header, .card, .btn {
                display: none !important;
            }
            
            .payslip {
                box-shadow: none;
                padding: 0;
                margin: 0;
            }
            
            body {
                background-color: white;
            }
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

        <!-- Page Header -->
        <div class="page-header">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h3 mb-0 text-white"><?php echo ucfirst($role); ?> Payroll</h1>
                    <div>
                        <button class="btn btn-light" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Payslip
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="container-fluid">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold">Select Pay Period</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Month</label>
                            <input type="month" class="form-control" name="month" value="<?php echo $current_month; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payroll Records -->
            <?php if (count($payrolls) > 0): ?>
                <?php foreach ($payrolls as $payroll): ?>
                    <div class="payslip">
                        <div class="payslip-header">
                            <h4>PAYSLIP</h4>
                            <p>For the period of <?php echo date('F Y', strtotime($payroll['start_date'])); ?></p>
                        </div>

                        <div class="payslip-details">
                            <div class="row">
                                <div class="col-md-6">
                                    <label>Employee Name:</label>
                                    <p class="value"><?php echo htmlspecialchars($payroll['employee_name']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label>Status:</label>
                                    <p class="value">
                                        <span class="badge bg-<?php 
                                            echo $payroll['status'] === 'paid' ? 'success' : 
                                                ($payroll['status'] === 'approved' ? 'info' : 'warning'); 
                                        ?>">
                                            <?php echo ucfirst($payroll['status']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <label>Pay Period:</label>
                                    <p class="value">
                                        <?php echo date('M d, Y', strtotime($payroll['start_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($payroll['end_date'])); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <label>Payslip ID:</label>
                                    <p class="value">#<?php echo str_pad($payroll['payroll_id'], 6, '0', STR_PAD_LEFT); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="payslip-summary">
                            <h5>Salary Summary</h5>
                            <div class="row">
                                <div class="col-6">
                                    <label>Basic Salary:</label>
                                </div>
                                <div class="col-6">
                                    <p class="value">₱<?php echo number_format($payroll['basic_salary'], 2); ?></p>
                                </div>
                            </div>
                            <?php if ($payroll['bonus_amount'] > 0): ?>
                                <div class="row">
                                    <div class="col-6">
                                        <label>Attendance Bonus:</label>
                                    </div>
                                    <div class="col-6">
                                        <p class="value">₱<?php echo number_format($payroll['bonus_amount'], 2); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="row total">
                                <div class="col-6">
                                    <label>Net Salary:</label>
                                </div>
                                <div class="col-6">
                                    <p class="value">₱<?php echo number_format($payroll['net_salary'], 2); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <button class="btn btn-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print Payslip
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No payroll records found for the selected period.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 