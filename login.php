<?php
session_start();
require_once './config/database.php';

// Check if user is inactive
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT status FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && in_array($user['status'], ['inactive', 'on_leave'])) {
            session_destroy();
            header("Location: ./index.php?error=inactive_user");
            exit();
        }
    } catch (PDOException $e) {
        // Log error but allow access
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
        $stmt->execute([$username, $role]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password']) && !in_array($user['status'], ['inactive', 'on_leave'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();

            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);

            // Redirect based on role
            switch($role) {
                case 'admin':
                    header("Location: ./dashboard/admin/dashboard.php");
                    break;
                case 'manager':
                    header("Location: ./dashboard/manager/dashboard.php");
                    break;
                case 'employee':
                    header("Location: ./dashboard/employee/dashboard.php");
                    break;
            }
            exit();
        } else {
            $error = "Invalid username, password, or role combination";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Naallo</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1e40af;
            --secondary-color: #0ea5e9;
            --dark-color: #0f172a;
            --light-color: #f1f5f9;
            --accent-color: #f97316;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: white;
            border-radius: 10px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .login-header {
            margin-bottom: 30px;
        }

        .login-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #666;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #e1e1e1;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e1e1e1;
            border-radius: 6px;
            font-size: 14px;
            background-color: white;
            cursor: pointer;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-login:hover {
            background-color: var(--secondary-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #64748b;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .password-container {
            position: relative;
            width: 100%;
        }

        .password-container input {
            padding-right: 40px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #64748b;
            font-size: 16px;
        }

        .toggle-password:hover {
            color: var(--primary-color);
        }

        .forgot-password {
            margin-top: 5px;
            text-align: right;
        }

        .forgot-password a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .back-link {
            margin-top: 15px;
            text-align: center;
        }

        .back-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 12px;
            border-radius: 6px;
            background-color: #ffe5e5;
            color: #ff3333;
            margin-bottom: 20px;
            font-size: 14px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <?php if (isset($_GET['error']) && $_GET['error'] === 'inactive_user'): ?>
            <div class="alert alert-danger">
                <p style="color: #dc2626; margin-bottom: 10px;">This user is either inactive or on leave. Please contact your administrator.</p>
            </div>
        <?php endif; ?>
        <div class="login-header">
            <h1>Welcome to Naallo</h1>
            <p style="color: #666; margin-top: 5px;">Sign in to your account</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="role">Role</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="">Select your role</option>
                    <option value="admin">Administrator</option>
                    <option value="manager">Manager</option>
                    <option value="employee">Employee</option>
                </select>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required>
                    <span class="toggle-password" onclick="togglePassword()">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                <div class="forgot-password">
                    <a href="forget-password.php">Forgot Password?</a>
                </div>
            </div>

            <button type="submit" class="btn-login">Login</button>
            <div class="back-link">
                <a href="../h/index.php">Back to Landing</a>
            </div>
        </form>
    </div>

    <script>
        function togglePassword() {
            var passwordInput = document.getElementById('password');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        }
    </script>
</body>
</html> 