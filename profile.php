<?php
session_start();
ob_start();
include 'db_connect.php';
include 'header.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Updated login check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo "<div class='login-message' style='text-align: center; padding: 20px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; margin: 20px auto; max-width: 400px;'>";
    echo "Please login to continue. <a href='login.php' style='color: #0d6efd; text-decoration: none; margin-left: 5px;'>Login here</a>";
    echo "</div>";
    exit;
}

$user_id = $_SESSION['user_id']; // Changed from 'userid' to 'user_id'

// Fetch current user details
try {
    $stmt = $pdo->prepare("SELECT email, created_at, profile_picture, phone, email_verified FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die('User not found');
    }
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}

// Add this after fetching current user details
if (isset($_POST['change_email'])) {
    $new_email = $_POST['new_email'];
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :user_id");
    $stmt->execute([
        ':email' => $new_email,
        ':user_id' => $user_id
    ]);
    
    if ($stmt->fetchColumn() > 0) {
        $error = "This email is already in use by another account.";
    } elseif (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET email = :new_email, email_verified = 0 WHERE id = :user_id");
            $stmt->execute([
                ':new_email' => $new_email,
                ':user_id' => $user_id
            ]);
            $success_msg = "Email updated successfully! Please verify your new email.";
            $user['email'] = $new_email;
            $user['email_verified'] = 0;
        } catch (PDOException $e) {
            $error = "Error updating email: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error = "Invalid email format!";
    }
}

// Fetch user orders with product details
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT o.id AS order_id, 
               o.total_price, 
               o.order_date, 
               o.status, 
               o.tracking_number,
               GROUP_CONCAT(p.name) AS product_names,
               GROUP_CONCAT(pi.image_url) AS product_photos
        FROM orders o
        LEFT JOIN order_details od ON o.id = od.order_id
        LEFT JOIN products p ON od.product_id = p.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        WHERE o.user_id = :user_id
        GROUP BY o.id, o.total_price, o.order_date, o.status, o.tracking_number
        ORDER BY o.order_date DESC
    ");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process the orders to split concatenated values
    foreach ($orders as &$order) {
        $order['product_names'] = explode(',', $order['product_names']);
        $order['product_photos'] = explode(',', $order['product_photos']);
        // Take the first product name and photo for display
        $order['product_name'] = $order['product_names'][0] ?? 'Unknown Product';
        $order['product_photo'] = $order['product_photos'][0] ?? 'default-product.jpg';
    }
} catch (PDOException $e) {
    error_log('Error fetching orders: ' . $e->getMessage());
    $orders = [];
}

// Remake the recycling requests fetch system
try {
    $logged_in_user_id = $_SESSION['user_id'];
    
    // Prepare the query to fetch ALL requests for this user
    $stmt = $pdo->prepare("
        SELECT id, 
               user_id,
               email,
               phone,
               category_id,
               subcategory_id,
               component_condition,
               photo,
               pickup_option,
               submitted_at,
               status,
               part_name,
               buying_year
        FROM recycle_requests
        WHERE user_id = ?
        ORDER BY submitted_at DESC
    ");
    
    // Execute with the logged-in user's ID
    $stmt->execute([$logged_in_user_id]);
    
    // Fetch all requests
    $recycling_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug output
    error_log("Found " . count($recycling_requests) . " total recycling requests for user ID: " . $logged_in_user_id);

} catch (PDOException $e) {
    error_log('Error in recycling requests fetch: ' . $e->getMessage());
    $recycling_requests = [];
}

// Debug: Log the results
if (!empty($recycling_requests)) {
    error_log('First request status: ' . $recycling_requests[0]['status']);
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];
    $uploadDir = 'uploads/profiles/'; // Changed to a specific directory for profile pictures
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxFileSize = 2 * 1024 * 1024; // 2 MB
    $fileName = time() . '_' . basename($file['name']); // Add timestamp to prevent name conflicts
    $uploadFile = $uploadDir . $fileName;

    try {
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
        }

        if ($file['size'] > $maxFileSize) {
            throw new Exception('File is too large. Maximum size is 2MB.');
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadFile)) {
            throw new Exception('Failed to upload file. Please try again.');
        }

        // Delete old profile picture if it exists
        if (!empty($user['profile_picture'])) {
            $oldFile = $uploadDir . $user['profile_picture'];
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        // Update database with new profile picture
        $stmt = $pdo->prepare("UPDATE users SET profile_picture = :profile_picture WHERE id = :user_id");
        $stmt->execute([
            ':profile_picture' => $fileName,
            ':user_id' => $user_id
        ]);

        $_SESSION['success_msg'] = 'Profile picture updated successfully!';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Handle profile picture removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_profile_picture'])) {
    try {
        // Delete the profile picture file if it exists
        if (!empty($user['profile_picture'])) {
            $filePath = 'uploads/profiles/' . $user['profile_picture'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // Update database to remove profile picture
        $stmt = $pdo->prepare("UPDATE users SET profile_picture = NULL WHERE id = :user_id");
        $stmt->execute([':user_id' => $user_id]);

        $_SESSION['success_msg'] = 'Profile picture removed successfully!';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error removing profile picture: ' . $e->getMessage();
    }
}

// Handle phone number update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phone'])) {
    $new_phone = $_POST['phone'];
    // Validate phone number (example validation, adjust as needed)
    if (preg_match('/^[0-9]{10}$/', $new_phone)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET phone = :phone WHERE id = :user_id");
            $stmt->bindParam(':phone', $new_phone);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $success_msg = "Phone number updated successfully!";
            $user['phone'] = $new_phone; // Update the phone number in the displayed profile
        } catch (PDOException $e) {
            $error = 'Error: ' . htmlspecialchars($e->getMessage());
        }
    } else {
        $error = "Invalid phone number format.";
    }
}

// Add new form handling for email and password
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Handle verification code submission
        if (isset($_POST['verify_code'])) {
            $submitted_code = $_POST['verification_token'];
            $stmt = $pdo->prepare("SELECT verification_token FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $stored_code = $stmt->fetchColumn();

            if ($submitted_code === $stored_code) {
                // Update user verification status
                $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = :user_id");
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $success_msg = "Email successfully verified!";
                $user['email_verified'] = 1; // Update current session
            } else {
                $error = "Invalid verification code. Please try again.";
            }
        }

        // Handle send verification email
        if (isset($_POST['verify_email'])) {
            // Generate 6-digit code
            $verification_token = sprintf("%06d", mt_rand(1, 999999));
            
            // Store the verification code
            $stmt = $pdo->prepare("UPDATE users SET verification_token = :code WHERE id = :user_id");
            $stmt->bindParam(':code', $verification_token);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            // Send verification email using PHPMailer
           

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'lokmen13.messabhia@gmail.com';
                $mail->Password = 'dfbk qkai wlax rscb';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('lokmen13.messabhia@gmail.com', 'Lokpix');
                $mail->addAddress($user['email']);
                $mail->isHTML(true);
                $mail->Subject = "Email Verification Code";
                $mail->Body = "Your verification code is: " . $verification_token;

                $mail->send();
                $show_verification_popup = true;
                $success_msg = "Verification code sent! Please check your inbox.";
            } catch (Exception $e) {
                $error = "Failed to send verification email. Error: " . $mail->ErrorInfo;
            }
        }
        
        // Email Update Form
        if (isset($_POST['change_email'])) {
            $new_email = $_POST['new_email'];
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :user_id");
            $stmt->execute([
                ':email' => $new_email,
                ':user_id' => $user_id
            ]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = "This email is already in use by another account.";
            } elseif (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET email = :new_email, email_verified = 0 WHERE id = :user_id");
                    $stmt->execute([
                        ':new_email' => $new_email,
                        ':user_id' => $user_id
                    ]);
                    $success_msg = "Email updated successfully! Please verify your new email.";
                    $user['email'] = $new_email;
                    $user['email_verified'] = 0;
                } catch (PDOException $e) {
                    $error = "Error updating email: " . htmlspecialchars($e->getMessage());
                }
            } else {
                $error = "Invalid email format!";
            }
        }

        // Change Password
        if (isset($_POST['change_password'])) {
            $old_password = $_POST['old_password'];
            $new_password = $_POST['new_password'];

            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $user_pwd = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($old_password, $user_pwd['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = :new_password WHERE id = :user_id");
                $stmt->bindParam(':new_password', $hashed_password);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
               
                $success_msg = "Password updated successfully!";
                
            } else {
                $error = "Old password is incorrect!";
            }
        }

        // Update Phone Number
        if (isset($_POST['update_phone'])) {
            $new_phone = $_POST['phone'];
            // Validate phone number
            if (preg_match('/^[0-9]{10}$/', $new_phone)) {
                $stmt = $pdo->prepare("UPDATE users SET phone = :new_phone WHERE id = :user_id");
                $stmt->bindParam(':new_phone', $new_phone);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                header("Location: " . $_SERVER['PHP_SELF']);
                $success_msg = "Phone number updated successfully!";
                $user['phone'] = $new_phone;
            } else {
                $error = "Invalid phone number format!";
            }
        }
    } catch (PDOException $e) {
        $error = "Error: " . htmlspecialchars($e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Lokpix</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Base Styles */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e5eaf2 100%);
            color: #334155;
            line-height: 1.6;
            transition: all 0.3s ease;
        }

        .profile-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
            transition: all 0.3s ease;
        }

        /* Left Sidebar */
        .profile-sidebar {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 2rem;
            height: fit-content;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .profile-sidebar:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px -1px rgba(0, 0, 0, 0.15);
        }

        .profile-picture {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #3b82f6;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 0 auto 2rem;
        }

        .profile-picture img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            text-align: center;
            position: relative;
        }

        .email-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .verified-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: #3b82f6;
            border-radius: 50%;
            color: white;
            font-size: 12px;
            position: relative;
            cursor: help;
        }

        .verified-badge::before {
            content: '✓';
            font-weight: bold;
        }

        .verified-badge:hover::after {
            content: 'Verified Email';
            position: absolute;
            background: #1e293b;
            color: white;
            padding: 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            white-space: nowrap;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-top: 0.5rem;
            z-index: 10;
        }

        /* Add a subtle animation for the badge */
        @keyframes verifiedPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .verified-badge:hover {
            animation: verifiedPulse 1s infinite;
        }

        .profile-info p {
            margin: 0.5rem 0;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 0.5rem;
        }

        .profile-info strong {
            color: #3b82f6;
            display: block;
            margin-bottom: 0.25rem;
        }

        /* Main Content Area */
        .main-content {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            gap: 2rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .main-content:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px -1px rgba(0, 0, 0, 0.15);
        }

        /* Settings Container */
        .settings-container {
            background: #f8fafc;
            border-radius: 0.8rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        /* Profile Actions Container */
        .profile-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-top: 2rem;
        }

        /* Profile Action Buttons - Complete Reset */
        .profile-action-btn {
            all: unset !important;
            box-sizing: border-box !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 2rem 1.5rem !important;
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
            transition: all 0.3s ease !important;
            color: inherit !important;
            text-decoration: none !important;
        }

        .profile-action-btn i {
            font-size: 1.5rem !important;
            margin-bottom: 0.5rem !important;
        }

        .profile-action-btn span {
            color: #4b5563 !important;
            font-size: 1rem !important;
            font-weight: 500 !important;
        }

        /* Individual Button Colors and Hover States */
        .wishlist-btn i { 
            color: #f43f5e !important;
        }

        .recycle-btn i { 
            color: #10b981 !important;
        }

        .orders-btn i { 
            color: #6366f1 !important;
        }

        /* Hover States */
        .wishlist-btn:hover {
            background: #fff5f5 !important;
            transform: translateY(-2px) !important;
        }

        .recycle-btn:hover {
            background: #f0fff4 !important;
            transform: translateY(-2px) !important;
        }

        .orders-btn:hover {
            background: #f5f5ff !important;
            transform: translateY(-2px) !important;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .profile-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .profile-actions {
                grid-template-columns: 1fr;
            }
            
            .profile-action-btn {
                padding: 1.5rem;
            }
            
            .profile-action-btn i {
                font-size: 2rem;
            }
            
            .profile-action-btn span {
                font-size: 1rem;
            }
        }

        /* Forms */
        .form-group {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.5rem;
        }

        /* Input Container */
        .input-container {
            flex: 1;
        }

        /* Form Labels */
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #1e293b;
        }

        /* Form Inputs */
        .form-group input {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            font-size: 0.95rem;
        }

        /* Simple Button Style */
        .form-group button {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            color: white;
            cursor: pointer;
            transition: background-color 0.2s;
            min-width: 120px;
            height: fit-content;
            align-self: flex-end;
        }

        /* Button Colors */
        button[name="verify_email"] {
            background-color: #10b981;
        }

        button[name="change_email"] {
            background-color: #6366f1;
        }

        button[name="change_password"] {
            background-color: #f43f5e;
        }

        button[name="update_phone"] {
            background-color: #8b5cf6;
        }

        /* Button Hover States */
        button[name="verify_email"]:hover {
            background-color: #059669;
        }

        button[name="change_email"]:hover {
            background-color: #4f46e5;
        }

        button[name="change_password"]:hover {
            background-color: #e11d48;
        }

        button[name="update_phone"]:hover {
            background-color: #7c3aed;
        }

        /* Responsive Design */
        @media (max-width: 640px) {
            .form-group {
                flex-direction: column;
            }
            
            .form-group button {
                width: 100%;
                margin-top: 0.5rem;
                align-self: center;
            }
        }

        /* Section Headers */
        .section-header {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
            position: relative;
        }

        .section-header::after {
            
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100px;
            height: 2px;
            background: #3b82f6;
        }

        /* Cards */
        .order-card, .recycling-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .order-card:hover, .recycling-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.validated { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }

        /* Upload Form Styling */
        .upload-form {
            margin-top: 2rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .custom-file-upload {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            border: none;
            width: 100%;
        }

        .custom-file-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            filter: brightness(1.1);
        }

        .custom-file-upload::before {
            
            margin-right: 0.5rem;
            font-size: 1.1em;
        }

        .upload-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            border: none;
            width: 100%;
        }

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            filter: brightness(1.1);
        }

        .upload-btn::before {
            
            margin-right: 0.5rem;
            font-size: 1.1em;
        }

        /* File input status text */
        .file-selected {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 0.5rem;
            display: none;
        }

        /* Loading state for upload button */
        .upload-btn.loading {
            position: relative;
            color: transparent;
        }

        .upload-btn.loading::after {
            
            position: absolute;
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid white;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Disabled state */
        .upload-btn:disabled,
        .custom-file-upload.disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Focus states for accessibility */
        .custom-file-upload:focus-within,
        .upload-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .profile-container {
                grid-template-columns: 1fr;
            }

            .profile-sidebar {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .profile-container {
                margin: 1rem;
                padding: 1rem;
            }

            .profile-actions {
                grid-template-columns: 1fr;
            }

            .order-card, .recycling-card {
                flex-direction: column;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .main-content {
            animation: fadeIn 0.3s ease-out;
        }

        /* New Popup Styling */
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            animation: fadeIn 0.3s ease-out;
        }

        .popup {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            width: 90%;
            max-width: 400px;
            animation: slideIn 0.4s ease-out;
        }

        .popup h3 {
            color: #1e293b;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .popup p {
            color: #64748b;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .popup input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .popup input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .popup button {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .popup button:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }

        .close-popup {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f1f5f9;
        }

        .close-popup:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        /* Popup Animations */
        @keyframes slideIn {
            from {
                transform: translate(-50%, -60%);
                opacity: 0;
            }
            to {
                transform: translate(-50%, -50%);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Update responsive design for buttons */
        @media (max-width: 640px) {
            .profile-actions {
                grid-template-columns: 1fr;
            }

            .popup {
                width: 95%;
                padding: 2rem;
            }
        }

        /* Settings Container Buttons */
        .settings-container button {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
            width: auto;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .settings-container button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
            filter: brightness(1.1);
        }

        /* Verification Button Specific Style */
        .verify-button {
            background: linear-gradient(135deg, #10b981, #059669) !important;
        }

        /* Email Update Button */
        button[name="change_email"] {
            background: linear-gradient(135deg, #6366f1, #4f46e5) !important;
        }

        /* Password Update Button */
        button[name="change_password"] {
            background: linear-gradient(135deg, #f43f5e, #e11d48) !important;
        }

        /* Phone Update Button */
        button[name="update_phone"] {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important;
        }

        /* Add icons to buttons */
        button[name="verify_email"]::before {
            
            margin-right: 0.5rem;
        }

        button[name="change_email"]::before {
            
            margin-right: 0.5rem;
        }

        button[name="change_password"]::before {
            
            margin-right: 0.5rem;
        }

        button[name="update_phone"]::before {
            
            margin-right: 0.5rem;
        }

        /* Button focus states */
        .settings-container button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
        }

        /* Button disabled state */
        .settings-container button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Loading state for buttons */
        .settings-container button.loading {
            position: relative;
            color: transparent;
        }

        .settings-container button.loading::after {
            
            position: absolute;
            width: 1rem;
            height: 1rem;
            border: 2px solid white;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .form-group button {
                position: static;
                width: 100%;
                margin-top: 1rem;
            }
        }

        /* Drag and Drop Zone Styles */
        .drag-drop-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8fafc;
            margin-bottom: 1rem;
        }

        .drag-drop-zone.dragover {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .drag-drop-text {
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .file-input {
            display: none;
        }

        /* File Info Display */
        .file-info {
            display: none;
            margin-top: 1rem;
            padding: 0.5rem;
            background: #f1f5f9;
            border-radius: 0.25rem;
            color: #475569;
        }

        /* Orders Section Styling */
        #orders-section {
            padding: 2rem;
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-top: 2rem;
        }

        .filter-container {
            margin-bottom: 2rem;
        }

        .status-filter {
            padding: 0.75rem 1.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background: white;
            color: #1e293b;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .status-filter:hover {
            border-color: #3b82f6;
        }

        .order-card-container {
            display: grid;
            gap: 1.5rem;
        }

        .order-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            display: grid;
            grid-template-columns: 300px 1fr;
            max-width: 100%;
        }

        .order-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e1;
        }

        .order-image {
            position: relative;
            height: 100%;
            min-height: 300px;
            overflow: hidden;
        }

        .order-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .order-card:hover .order-image img {
            transform: scale(1.05);
        }

        .order-details {
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .order-id {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.processing { background: #e0f2fe; color: #075985; }
        .status-badge.shipped { background: #f0fdf4; color: #166534; }
        .status-badge.delivered { background: #dcfce7; color: #166534; }
        .status-badge.cancelled { background: #fee2e2; color: #991b1b; }

        .order-info {
            display: grid;
            gap: 1.5rem;
        }

        .info-group {
            display: grid;
            gap: 0.5rem;
        }

        .info-group strong {
            color: #64748b;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .product-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 0.5rem;
        }

        .product-list li {
            color: #1e293b;
            font-size: 1rem;
            padding: 0.5rem;
            background: #f8fafc;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }

        .product-list li:hover {
            background: #f1f5f9;
            padding-left: 1rem;
        }

        .price {
            font-size: 1.25rem;
            color: #10b981;
            font-weight: 600;
        }

        .tracking-info {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.5rem;
        }

        .tracking-info strong {
            color: #64748b;
        }

        .track-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border-radius: 0.5rem;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }

        .track-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px -2px rgba(37, 99, 235, 0.3);
            filter: brightness(1.1);
        }

        .track-button i {
            font-size: 1.1rem;
        }

        .no-orders {
            text-align: center;
            padding: 3rem;
            background: #f8fafc;
            border-radius: 1rem;
            border: 2px dashed #e2e8f0;
        }

        .browse-products-btn {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .browse-products-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .order-card {
                grid-template-columns: 250px 1fr;
            }
        }

        @media (max-width: 768px) {
            .order-card {
                grid-template-columns: 1fr;
            }

            .order-image {
                height: 250px;
                min-height: unset;
            }

            .order-details {
                padding: 1.5rem;
            }

            .tracking-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .track-button {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            #orders-section {
                padding: 1rem;
            }

            .order-details {
                padding: 1rem;
            }

            .order-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .status-badge {
                width: 100%;
                text-align: center;
            }
        }

        /* Recycling Section Styling */
        .recycling-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            padding: 1rem;
        }

        .recycling-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            max-width: 400px;
            margin: 0 auto;
            width: 100%;
        }

        .recycling-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e1;
        }

        .recycling-image {
            position: relative;
            width: 100%;
            height: 250px;
            overflow: hidden;
        }

        .recycling-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .recycling-card:hover .recycling-image img {
            transform: scale(1.05);
        }

        .recycling-details {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .recycling-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .request-id {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
        }

        .recycling-info {
            display: grid;
            gap: 0.75rem;
        }

        .recycling-info p {
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .recycling-info strong {
            color: #64748b;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Status badges for recycling requests */
        .recycling-card .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .recycling-card .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .recycling-card .status-badge.validated {
            background: #dcfce7;
            color: #166534;
        }

        .recycling-card .status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Empty state styling */
        .no-recycling {
            text-align: center;
            padding: 3rem;
            background: #f8fafc;
            border-radius: 1rem;
            border: 2px dashed #e2e8f0;
            margin: 2rem auto;
            max-width: 600px;
        }

        .submit-recycling-btn {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .submit-recycling-btn:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .recycling-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .recycling-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.5rem;
            }

            .recycling-image {
                height: 200px;
            }
        }

        @media (max-width: 480px) {
            .recycling-grid {
                grid-template-columns: 1fr;
            }

            .recycling-card {
                max-width: 100%;
            }

            .recycling-details {
                padding: 1rem;
            }

            .recycling-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .status-badge {
                width: 100%;
                text-align: center;
            }
        }

        /* Add these styles to hide sections by default */
        .dropdown-section {
            display: none;
        }

        .dropdown-section.active {
            display: block;
        }

        .remove-profile-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            border: none;
            width: 100%;
            margin-top: 1rem;
        }

        .remove-profile-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
            filter: brightness(1.1);
        }

        .remove-profile-btn i {
            font-size: 1.1em;
        }
    </style>
    <!-- Option 1: Using jsDelivr CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">

    <!-- Option 2: Using unpkg CDN -->
    <link rel="stylesheet" href="https://unpkg.com/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">

    <!-- Option 3: Using Font Awesome Kit (replace [your-kit-code] with actual kit code) -->
    <script src="https://kit.fontawesome.com/[your-kit-code].js" crossorigin="anonymous"></script>
</head>
<body>
    <main>
        <div class="profile-container">
            <!-- Left Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-picture">
                    <img src="<?php 
                        if ($user['profile_picture']) {
                            echo 'uploads/profiles/' . htmlspecialchars($user['profile_picture']);
                        } else {
                            echo 'https://i.top4top.io/p_3273sk4691.jpg';
                        }
                    ?>" alt="Profile Picture">
                </div>
                
                <div class="profile-info">
                    <div class="email-container">
                        <p>
                            <strong>Email Address</strong>
                            <?php echo htmlspecialchars($user['email']); ?>
                            <?php if ($user['email_verified']): ?>
                                <span class="verified-badge" title="Verified Email"></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <p><strong>Date of Creation</strong> <?php echo htmlspecialchars($user['created_at']); ?></p>
                    <p><strong>Phone Number</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
                </div>

                <div class="upload-form">
                    <form action="profile.php" method="post" enctype="multipart/form-data" id="uploadForm">
                        <div class="drag-drop-zone" id="dragDropZone">
                            <p class="drag-drop-text">Drag and drop your profile picture here</p>
                            <p class="drag-drop-text">or</p>
                            <button type="button" class="btn custom-file-upload" onclick="document.getElementById('profile_picture').click()">
                                Choose File
                            </button>
                            <input type="file" id="profile_picture" name="profile_picture" class="file-input" accept="image/*">
                        </div>
                        <div class="file-info" id="fileInfo"></div>
                        <button type="submit" class="btn upload-btn" id="uploadBtn" disabled>
                            Upload Picture
                        </button>
                    </form>
                    <?php if (!empty($user['profile_picture'])): ?>
                        <form action="profile.php" method="post" style="margin-top: 1rem;">
                            <button type="submit" name="remove_profile_picture" class="remove-profile-btn">
                                <i class="fas fa-trash"></i> Remove Profile Picture
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="main-content">
                <div class="settings-container">
                    <h2 class="section-header">Account Settings</h2>
                    
                    <!-- Email Verification Form -->
                    <?php if (!$user['email_verified']): ?>
                        <form method="post" action="">
                            <div class="form-group verification-group">
                                <div class="verification-info">
                                    <span class="verification-badge">Unverified Email</span>
                                    <p>Your email (<?php echo htmlspecialchars($user['email']); ?>) is not verified.</p>
                                </div>
                                <button type="submit" name="verify_email" class="btn btn-primary form-submit-btn verify-button">
                                    Send Verification Email
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <!-- Email Update Form -->
                    <form method="post" action="">
                        <div class="form-group">
                            <div class="input-container">
                                <label for="new_email">Change Email</label>
                                <input type="email" id="new_email" name="new_email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <button type="submit" name="change_email">Update</button>
                        </div>
                    </form>
                    
                    <!-- Password Change Form -->
                    <form method="post" action="">
                        <div class="form-group">
                            <div class="input-container">
                                <label for="old_password">Old Password</label>
                                <input type="password" id="old_password" name="old_password" required>
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required>
                            </div>
                            <button type="submit" name="change_password">Update</button>
                        </div>
                    </form>

                    <!-- Phone Number Form -->
                    <form method="post" action="">
                        <div class="form-group">
                            <div class="input-container">
                                <label for="phone">Edit Phone Number</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                            </div>
                            <button type="submit" name="update_phone">Update</button>
                        </div>
                    </form>

                    <!-- Profile Actions -->
                    <div class="profile-actions">
                        <a href="wishlist.php" class="profile-action-btn wishlist-btn">
                            <i class="fas fa-heart"></i>
                            <span>My Wishlist</span>
                        </a>
                        <a href="#" class="profile-action-btn recycle-btn" onclick="handleActionButtonClick('recycling-section'); return false;">
                            <i class="fas fa-recycle"></i>
                            <span>My Recycling</span>
                        </a>
                        <a href="#" class="profile-action-btn orders-btn" onclick="handleActionButtonClick('orders-section'); return false;">
                            <i class="fas fa-box"></i>
                            <span>My Orders</span>
                        </a>
                    </div>
                </div>

                <!-- Replace the existing dropdown sections with this updated structure -->
                <div class="dropdown-sections">
                    <!-- Orders Section -->
                    <div class="dropdown-section" id="orders-section" style="display: none;">
                        <h2 class="section-header">Your Orders</h2>
                        <div class="filter-container">
                            <select class="status-filter" onchange="filterOrders(this.value)">
                                <option value="all">All Orders</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        
                        <?php if (!empty($orders)): ?>
                            <div class="order-card-container">
                                <?php foreach ($orders as $order): ?>
                                    <div class="order-card" data-status="<?php echo strtolower($order['status']); ?>">
                                        <div class="order-image">
                                            <img src="<?php echo file_exists('uploads/products/' . $order['product_photo']) 
                                                ? 'uploads/products/' . htmlspecialchars($order['product_photo'])
                                                : 'path/to/default-image.jpg'; ?>" 
                                                alt="<?php echo htmlspecialchars($order['product_name']); ?>">
                                        </div>
                                        <div class="order-details">
                                            <div class="order-header">
                                                <h3 class="order-id">Order #<?php echo htmlspecialchars($order['order_id']); ?></h3>
                                                <span class="status-badge <?php echo strtolower($order['status']); ?>">
                                                    <?php echo htmlspecialchars($order['status']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="order-info">
                                                <div class="info-group">
                                                    <strong>Products:</strong>
                                                    <ul class="product-list">
                                                        <?php foreach ($order['product_names'] as $index => $name): ?>
                                                            <li><?php echo htmlspecialchars($name); ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                                
                                                <div class="info-group">
                                                    <strong>Order Date:</strong>
                                                    <span><?php echo date('F j, Y', strtotime($order['order_date'])); ?></span>
                                                </div>
                                                
                                                <div class="info-group">
                                                    <strong>Total Price:</strong>
                                                    <span class="price"><?php echo number_format($order['total_price'], 2); ?> DA</span>
                                                </div>
                                                
                                                <?php if (!empty($order['tracking_number'])): ?>
                                                    <div class="tracking-info">
                                                        <strong>Tracking Number:</strong>
                                                        <span><?php echo htmlspecialchars($order['tracking_number']); ?></span>
                                                        <a href="track_order.php?tracking_number=<?php echo htmlspecialchars($order['tracking_number']); ?>" 
                                                           class="track-button">
                                                            <i class="fas fa-truck"></i> Track Order
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-orders">
                                <p>You haven't placed any orders yet.</p>
                                <a href="index.php" class="browse-products-btn">Browse Products</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recycling Section -->
                    <div class="dropdown-section" id="recycling-section" style="display: none;">
                        <h2 class="section-header">Your Recycling Requests</h2>
                        <div class="filter-container">
                            <select class="status-filter" onchange="filterRecycling(this.value)">
                                <option value="all">All Requests</option>
                                <option value="pending">Pending</option>
                                <option value="validated">Validated</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        
                        <?php if (!empty($recycling_requests)): ?>
                            <div class="recycling-grid">
                                <?php foreach ($recycling_requests as $request): ?>
                                    <div class="recycling-card" data-status="<?php echo strtolower($request['status']); ?>">
                                        <div class="recycling-image">
                                            <img src="<?php echo htmlspecialchars($request['photo']); ?>" alt="Recycling Item">
                                        </div>
                                        <div class="recycling-details">
                                            <div class="recycling-header">
                                                <span class="request-id">Request #<?php echo htmlspecialchars($request['id']); ?></span>
                                                <span class="status-badge <?php echo strtolower($request['status']); ?>">
                                                    <?php echo htmlspecialchars($request['status']); ?>
                                                </span>
                                            </div>
                                            <div class="recycling-info">
                                                <p><strong>Part Name:</strong> <?php echo htmlspecialchars($request['part_name']); ?></p>
                                                <p><strong>Condition:</strong> <?php echo htmlspecialchars($request['component_condition']); ?></p>
                                                <p><strong>Buying Year:</strong> <?php echo htmlspecialchars($request['buying_year']); ?></p>
                                                <p><strong>Submitted:</strong> <?php echo date('F j, Y', strtotime($request['submitted_at'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-recycling">
                                <p>You haven't submitted any recycling requests yet.</p>
                                <a href="recycle.php" class="submit-recycling-btn">Submit a Recycling Request</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="popup-overlay" id="verificationPopup">
        <div class="popup">
            <span class="close-popup" onclick="closePopup()">&times;</span>
            <h3>Verify Your Email</h3>
            <p>Please enter the 6-digit verification code sent to your email address.</p>
            <form method="post" action="">
                <input type="text" 
                       name="verification_token" 
                       placeholder="Enter verification code" 
                       maxlength="6" 
                       pattern="\d{6}" 
                       required>
                <button type="submit" name="verify_code">Verify Email</button>
            </form>
        </div>
    </div>
   
    <script>
        function closePopup() {
            document.getElementById('verificationPopup').style.display = 'none';
        }

        <?php if (isset($show_verification_popup) && $show_verification_popup): ?>
        document.getElementById('verificationPopup').style.display = 'block';
        <?php endif; ?>
    </script>
<?php   include'footer.php' ?>
    <button class="back-to-top" aria-label="Back to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
        // Show the button when scrolling down
        window.onscroll = function() {
            const button = document.getElementById('backToTop');
            if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
                button.style.display = "block";
            } else {
                button.style.display = "none";
            }
        };

        // Scroll to the top of the document
        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        // Add this JavaScript for the Back to Top button
        const backToTopButton = document.querySelector('.back-to-top');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) { // Show button after scrolling 300px
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        });

        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>

    <!-- Add Font Awesome for the upload icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script>
        // Update the handleActionButtonClick function
        function handleActionButtonClick(sectionId) {
            const sectionsContainer = document.querySelector('.dropdown-sections');
            const section = document.getElementById(sectionId);
            const allSections = document.querySelectorAll('.dropdown-section');
            
            // Hide all sections first
            allSections.forEach(s => {
                s.style.display = 'none';
                s.classList.remove('active');
            });
            
            // Toggle the clicked section
            if (section.style.display === 'none') {
                section.style.display = 'block';
                section.classList.add('active');
                // Smooth scroll to the section
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                section.style.display = 'none';
                section.classList.remove('active');
            }
        }

        // Remove or comment out the existing toggleDropdown function since we're not using it anymore
        // function toggleDropdown(contentId) { ... }

        // Add this function for filtering recycling requests
        function filterRecycling(status) {
            const recyclingCards = document.querySelectorAll('.recycling-card');
            
            recyclingCards.forEach(card => {
                if (status === 'all' || card.dataset.status === status.toLowerCase()) {
                    card.style.display = 'grid';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const dragDropZone = document.getElementById('dragDropZone');
        const fileInput = document.getElementById('profile_picture');
        const fileInfo = document.getElementById('fileInfo');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadForm = document.getElementById('uploadForm');

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dragDropZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        // Highlight drop zone when dragging over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dragDropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dragDropZone.addEventListener(eventName, unhighlight, false);
        });

        // Handle dropped files
        dragDropZone.addEventListener('drop', handleDrop, false);

        // Handle file input change
        fileInput.addEventListener('change', handleFiles, false);

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function highlight(e) {
            dragDropZone.classList.add('dragover');
        }

        function unhighlight(e) {
            dragDropZone.classList.remove('dragover');
        }

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles({ target: { files: files } });
        }

        function handleFiles(e) {
            const files = e.target.files;
            if (files.length > 0) {
                const file = files[0];
                
                // Validate file type
                if (!file.type.match('image.*')) {
                    alert('Please upload an image file');
                    return;
                }

                // Validate file size (2MB max)
                if (file.size > 2 * 1024 * 1024) {
                    alert('File size should not exceed 2MB');
                    return;
                }

                // Update file input
                fileInput.files = files;
                
                // Show file info
                fileInfo.style.display = 'block';
                fileInfo.textContent = `Selected: ${file.name}`;
                
                // Enable upload button
                uploadBtn.disabled = false;
            }
        }

        // Handle form submission
        uploadForm.addEventListener('submit', function(e) {
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
        });
    });
    </script>

    <script>
        function filterOrders(status) {
            const orderCards = document.querySelectorAll('.order-card');
            
            orderCards.forEach(card => {
                if (status === 'all' || card.dataset.status === status.toLowerCase()) {
                    card.style.display = 'grid';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>