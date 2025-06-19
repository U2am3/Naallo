<?php
session_start();
require_once './config/database.php';

$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['confirm_password'])) {
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error = "Passwords do not match.";
        exit;
    }

    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        // Verify token and update password
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Update password and clear reset token
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE user_id = ?");
            $stmt->execute([$new_password, $user['user_id']]);
            
            $success = "Your password has been successfully reset. You can now login with your new password.";
        } else {
            $error = "Invalid or expired reset token.";
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
    <title>Reset Password - Naallo</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .reset-card {
            background: white;
            border-radius: 10px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .reset-header {
            margin-bottom: 30px;
        }

        .reset-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
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
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .btn {
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

        .btn:hover {
            background-color: var(--secondary-color);
        }

        .error, .success {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }

        .error {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .success {
            background-color: #dcfce7;
            color: #166534;
        }
    </style>
</head>
<body>
    <div id="loading-spinner-overlay" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(255,255,255,0.85);z-index:9999;justify-content:center;align-items:center;flex-direction:column;">
        <div class="spinner-border text-primary" role="status" style="width: 3.5rem; height: 3.5rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="spinner-text" style="margin-top:15px;color:#1e40af;font-weight:500;font-size:1.1rem;">Processing your request...</div>
    </div>
    <div class="reset-card">
        <div class="reset-header">
            <h1>Reset Password</h1>
            <p>Please enter your new password below.</p>
        </div>
        <?php if (!isset($success)): ?>
        <form method="POST" action="" id="resetForm">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn">Reset Password</button>
        </form>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="success">Your password has been successfully reset. You can now <a href="login.php">login</a> with your new password.</div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    var form = document.getElementById('resetForm');
    if (form) {
        form.addEventListener('submit', function() {
            document.getElementById('loading-spinner-overlay').style.display = 'flex';
        });
    }
    </script>
    <?php if (isset($error)): ?>
    <script>
    document.getElementById('loading-spinner-overlay').style.display = 'none';
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: <?php echo json_encode($error); ?>,
        confirmButtonColor: '#e74a3b'
    });
    </script>
    <?php endif; ?>
    <?php if (isset($success)): ?>
    <script>
    document.getElementById('loading-spinner-overlay').style.display = 'none';
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: <?php echo json_encode($success); ?>,
        confirmButtonColor: '#4e73df'
    });
    </script>
    <?php endif; ?>
</body>
</html>
