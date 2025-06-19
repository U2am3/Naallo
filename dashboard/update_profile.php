<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = "First name, last name, and email are required fields.";
    } else {
        try {
            $pdo->beginTransaction();

            // Update employee information
            $stmt = $pdo->prepare("
                UPDATE employees 
                SET first_name = ?, last_name = ?, phone = ?, address = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$first_name, $last_name, $phone, $address, $user_id]);

            // Update user email
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
            $stmt->execute([$email, $user_id]);

            // Handle profile image upload
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $file_extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png'];

                if (in_array($file_extension, $allowed_extensions)) {
                    $upload_dir = __DIR__ . '/../uploads/profile_photos';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . '/' . $new_filename;

                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                        // Update profile image in database
                        $stmt = $pdo->prepare("UPDATE employees SET profile_image = ? WHERE user_id = ?");
                        $stmt->execute([$new_filename, $user_id]);
                    } else {
                        $error_message = "Error uploading profile image. Please try again.";
                    }
                } else {
                    $error_message = "Invalid file type. Only JPG, JPEG, and PNG files are allowed.";
                }
            }

            $pdo->commit();
            $success_message = "Profile updated successfully!";
            
            // Redirect back to the previous page
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    }
}

// If there's an error, redirect back with error message
if (!empty($error_message)) {
    $_SESSION['error_message'] = $error_message;
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
} 