<?php
session_start();
include 'db_connect.php'; // Ensure this path is correct

if (isset($_POST['verification_token'])) {
    $verification_token = $_POST['verification_token'];
    $email = $_SESSION['email'];

    // Debugging: Log the received values
    error_log("Received verification token: " . $verification_token);
    error_log("User email from session: " . $email);

    // Get the user's stored verification token
    $stmt = $pdo->prepare("SELECT verification_token, email_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        error_log("Stored verification token: " . $user['verification_token']); // Debugging output
        if ($user['verification_token'] == $verification_token) {
            // Mark email as verified
            $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE email = ?");
            $stmt->execute([$email]);

            // Provide feedback to AJAX
            echo 'success';
        } else {
            echo 'failure'; // Return failure message if token doesn't match
        }
    } else {
        echo 'failure'; // User not found, failure
    }
}
?>
