<?php
// Connect to the database
include 'db_connect.php';

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form inputs
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $role = isset($_POST['role']) ? $_POST['role'] : '';

    // Validate inputs
    if (empty($email) || empty($password) || empty($role)) {
        echo "All fields are required.";
        exit;
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Prepare the SQL statement
    $stmt = $pdo->prepare("INSERT INTO admins (email, password, role) VALUES (?, ?, ?)");

    // Execute the statement with the hashed password
    try {
        $stmt->execute([$email, $hashed_password, $role]);
        echo "Admin added successfully.";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Admin</title>
    <link rel="stylesheet" href="admin_style.css"> <!-- Ensure this path is correct -->
</head>
<body>
    <header>
        <h1>Setup Admin</h1>
    </header>
    <main>
        <form action="setup_admin.php" method="post">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="admin">Admin</option>
                    <!-- Add other roles if needed -->
                </select>
            </div>
            <button type="submit">Setup Admin</button>
        </form>
    </main>
</body>
</html>
