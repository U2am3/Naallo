<?php
session_start();
require_once './config/database.php';
require_once './config/site_config.php';
$config = require './config/site_config.php';
require_once './assets/PHPMailer-master/src/Exception.php';
require_once './assets/PHPMailer-master/src/PHPMailer.php';
require_once './assets/PHPMailer-master/src/SMTP.php';

// Use the PHPMailer namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    
    try {
        // Check if email exists in database
        $stmt = $pdo->prepare("SELECT user_id, username, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate reset token
            $reset_token = bin2hex(random_bytes(32));
            
            // Store reset token in database
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE user_id = ?");
            $stmt->execute([$reset_token, $user['user_id']]);

            // Send email using PHPMailer
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'faarisfun@gmail.com'; // Replace with your email
                $mail->Password = 'iamj bhgr gzgm atgg'; // Replace with your app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Recipients
                $mail->setFrom('faarisfun@gmail.com', 'Naallo Support');
                $mail->addAddress($user['email']);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                // Get the server URL
                $server_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                // Build reset link dynamically for LAN/online use
                $reset_link = $server_url . $config['reset_url_path'] . "?token=" . urlencode($reset_token);
                $mail->Body = "
                    <h2>Password Reset Request</h2>
                    <p>Hello " . $user['username'] . ",</p>
                    <p>You have requested to reset your password. Click the link below to reset your password:</p>
                    <p><a href='" . $reset_link . "'>Reset Password</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you did not request this password reset, please ignore this email.</p>
                ";

                $mail->send();
                $success = "An email with instructions to reset your password has been sent to your email address.";
            } catch (Exception $e) {
                $error = "Message could not be sent. Mailer Error: " . $mail->ErrorInfo;
            }
        } else {
            $error = "No account found with this email address.";
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
    <title>Forgot Password - Naallo</title>
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
            <h1>Forgot Password</h1>
            <p>Enter your email address and we'll send you instructions to reset your password.</p>
        </div>
        <form method="POST" action="" id="resetForm">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="btn">Send Reset Instructions</button>
        </form>
        <div class="back-link">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.getElementById('resetForm').addEventListener('submit', function() {
        document.getElementById('loading-spinner-overlay').style.display = 'flex';
    });
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
