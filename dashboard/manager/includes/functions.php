<?php
// dashboard/manager/includes/functions.php
// Utility and helper functions for manager pages

// Example: Sanitize input
function manager_sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Handle manager profile update
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return array Result with success or error message
 */
function handle_manager_profile_update($pdo, $userId) {
    $result = [
        'success_message' => '',
        'error_message' => ''
    ];
    
    $upload_dir = __DIR__ . '/../../../uploads/profile_photos';

    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    try {
        $pdo->beginTransaction();

        // Validate and sanitize inputs
        $first_name = manager_sanitize(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
        $last_name = manager_sanitize(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = manager_sanitize(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
        $address = manager_sanitize(filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING));

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            throw new Exception('Email already exists');
        }

        // Update users table
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ? AND role = 'manager'");
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

            if (!in_array($file_ext, $allowed)) {
                throw new Exception('Invalid file type. Only JPG, PNG and GIF allowed');
            }

            $max_size = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $max_size) {
                throw new Exception('File size too large. Maximum size is 5MB');
            }

            $new_filename = uniqid('profile_') . '.' . $file_ext;
            $destination = $upload_dir . '/' . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Update profile_image in database
                $stmt = $pdo->prepare("UPDATE employees SET profile_image = ? WHERE user_id = ?");
                $stmt->execute([$new_filename, $userId]);
            } else {
                throw new Exception('Failed to upload profile image');
            }
        }

        $pdo->commit();
        $result['success_message'] = "Profile updated successfully!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $result['error_message'] = $e->getMessage();
    }

    return $result;
}

// Add more manager-specific functions below as needed 