<?php
session_start();
include 'db_connect.php'; // Ensure you include the database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $current_password = $_POST['current_password'];
    $new_email = $_POST['email'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Fetch current password hash
    $query = $pdo->prepare("SELECT password FROM users WHERE id = :user_id");
    $query->execute(['user_id' => $user_id]);
    $user = $query->fetch();

    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        header('Location: settings.php?error=Current password is incorrect.');
        exit;
    }

    // Validate new password
    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            header('Location: settings.php?error=Passwords do not match.');
            exit;
        }
        $new_password = password_hash($new_password, PASSWORD_DEFAULT); // Hash new password
    } else {
        $new_password = $user['password']; // No change to password
    }

    // Update user information
    $update_query = $pdo->prepare("UPDATE users SET email = :email, password = :password WHERE id = :user_id");
    $update_query->execute([
        'email' => $new_email,
        'password' => $new_password,
        'user_id' => $user_id
    ]);

    header('Location: settings.php?status=success');
}
?>
