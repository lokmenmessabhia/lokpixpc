<?php
session_start();
include 'db_connect.php'; // Ensure this path is correct

if (!isset($_SESSION['userid'])) {
    die('User not logged in');
}

$user_id = $_SESSION['userid'];

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (!empty($email)) {
        try {
            // Update email
            $stmt = $pdo->prepare("UPDATE users SET email = :email WHERE id = :user_id");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
        } catch (PDOException $e) {
            header("Location: profile.php?error=" . urlencode("Error updating email: " . $e->getMessage()));
            exit();
        }
    }

    if (!empty($new_password) && !empty($confirm_password)) {
        if ($new_password === $confirm_password) {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :user_id");
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($_POST['current_password'], $user['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :user_id");
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->execute();
                } else {
                    header("Location: profile.php?error=" . urlencode("Current password is incorrect."));
                    exit();
                }
            } catch (PDOException $e) {
                header("Location: profile.php?error=" . urlencode("Error updating password: " . $e->getMessage()));
                exit();
            }
        } else {
            header("Location: profile.php?error=" . urlencode("New passwords do not match."));
            exit();
        }
    }

    header("Location: profile.php?status=success");
    exit();
}
?>
