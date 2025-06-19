<?php
function handleProfileUpdate($pdo, $userId, $role) {
    $result = [
        'success_message' => '',
        'error_message' => ''
    ];
    
    $upload_dir = __DIR__ . '/../../uploads/profile_photos';

    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    try {
        $pdo->beginTransaction();

        // Validate and sanitize inputs
        $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
        $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);

        // Update users table
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
        $stmt->execute([$email, $userId]);

        // Update employees table
        $stmt = $pdo->prepare("UPDATE employees SET 
            first_name = ?, 
            last_name = ?, 
            phone = ?, 
            address = ? 
            WHERE user_id = ?");
        $stmt->execute([$first_name, $last_name, $phone, $address, $userId]);

        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_ext, $allowed)) {
                $new_filename = uniqid('profile_') . '.' . $file_ext;
                $destination = $upload_dir . '/' . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Update profile_image in database
                    $stmt = $pdo->prepare("UPDATE employees SET profile_image = ? WHERE user_id = ?");
                    $stmt->execute([$new_filename, $userId]);
                }
            }
        }

        $pdo->commit();
        $result['success_message'] = "Profile updated successfully!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $result['error_message'] = "Error: " . $e->getMessage();
    }

    return $result;
        }

        // Handle password change
        if (isset($_POST['change_password'])) {
            try {
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    throw new Exception("All password fields are required.");
                }

                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords do not match.");
                }

                if (strlen($new_password) < 8) {
                    throw new Exception("Password must be at least 8 characters long.");
                }

                if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/", $new_password)) {
                    throw new Exception("Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.");
                }

                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if (!password_verify($current_password, $user['password'])) {
                    throw new Exception("Current password is incorrect.");
                }

                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([$hashed_password, $userId]);

                // Log activity
                $stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, activity_type, description, ip_address)
                    VALUES (?, 'password_change', 'Changed account password', ?)
                ");
                $stmt->execute([$userId, $_SERVER['REMOTE_ADDR']]);

                $success_message = "Password changed successfully!";

            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    }

    return ['success' => $success_message, 'error' => $error_message];
}

function getUserProfile($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            d.dept_name,
            d.dept_code,
            u.username,
            u.email,
            u.role,
            u.status,
            u.created_at as account_created,
            COALESCE(
                (SELECT COUNT(*) 
                FROM attendance 
                WHERE emp_id = e.emp_id 
                AND MONTH(date) = MONTH(CURRENT_DATE)
                AND status = 'present'),
            0) as present_days,
            COALESCE(
                (SELECT COUNT(*) 
                FROM leave_requests 
                WHERE emp_id = e.emp_id 
                AND status = 'approved'
                AND YEAR(start_date) = YEAR(CURRENT_DATE)),
            0) as leaves_taken
        FROM employees e
        LEFT JOIN departments d ON e.dept_id = d.dept_id
        JOIN users u ON e.user_id = u.user_id
        WHERE e.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getEmployeeStats($pdo, $empId) {
    $stats = [];
    
    // Get attendance statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
        FROM attendance
        WHERE emp_id = ?
        AND MONTH(date) = MONTH(CURRENT_DATE)
        AND YEAR(date) = YEAR(CURRENT_DATE)
    ");
    $stmt->execute([$empId]);
    $stats['attendance'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get leave statistics
    $stmt = $pdo->prepare("
        SELECT 
            lt.type_name,
            COUNT(lr.leave_id) as count
        FROM leave_types lt
        LEFT JOIN leave_requests lr ON lt.type_id = lr.leave_type_id
        AND lr.emp_id = ?
        AND YEAR(lr.start_date) = YEAR(CURRENT_DATE)
        AND lr.status = 'approved'
        GROUP BY lt.type_id, lt.type_name
    ");
    $stmt->execute([$empId]);
    $stats['leaves'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get recent activities
    $stmt = $pdo->prepare("
        SELECT activity_type, description, created_at
        FROM activity_logs
        WHERE user_id = (SELECT user_id FROM employees WHERE emp_id = ?)
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$empId]);
    $stats['recent_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $stats;
}
?>
