<?php
include 'db_connect.php'; // Ensure this path is correct

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $entered_code = $_POST['verification_token'];

    // Get the stored verification code from the database
    $stmt = $pdo->prepare("SELECT verification_token FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $stored_code = $stmt->fetchColumn();

    if ($stored_code == $entered_code) {
        // Update the user's email_verified status
        $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE email = ?");
        if ($stmt->execute([$email])) {
            echo "Your email has been verified!";
        } else {
            echo "Error: Could not verify email.";
        }
    } else {
        echo "Invalid verification code.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Lokpix</title>
    <link rel="stylesheet" href="stylelog.css"> <!-- Ensure this path is correct -->
</head>
<body>
    <header>
        <div class="logo">Lokpix</div>
    </header>

    <main>
        <div class="wrapper">
            <div class="title">Verify Your Email</div>
            <form action="verify_code.php" method="post">
                <div class="field">
                    <input type="email" id="email" name="email" required>
                    <label for="email">Email</label>
                </div>
                <div class="field">
                    <input type="text" id="verification_code" name="verification_token" required>
                    <label for="verification_code">Enter Verification Code</label>
                </div>
                <div class="field">
                    <input type="submit" value="Verify">
                </div>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; 2024 Lokpix. All rights reserved.</p>
    </footer>
</body>
</html>
