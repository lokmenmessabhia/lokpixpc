<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    if (!empty($username) && !empty($email) && !empty($password) && !empty($role)) {
        $stmt = $pdo->prepare("INSERT INTO admins (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password, $role]);

        $message = "Admin added successfully!";
    } else {
        $message = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Admins - Lokpix</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>Manage Admins</h1>
    </header>
    <main>
        <form action="manage_admins.php" method="post">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <label for="role">Role:</label>
            <select id="role" name="role" required>
                <option value="admin">Admin</option>
                <option value="superadmin">Super Admin</option>
            </select>

            <button type="submit">Add Admin</button>
        </form>
        <?php if (isset($message)) echo "<p>$message</p>"; ?>
    </main>
</body>
</html>
