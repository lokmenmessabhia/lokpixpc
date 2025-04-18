<?php
session_start();

// Connect to the database
include 'db_connect.php';

// Check if the user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch the logged-in admin's details
$stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

// If the admin is not found, destroy the session and redirect to login
if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle admin modification or deletion
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete'])) {
        // Delete admin
        $stmt = $pdo->prepare('DELETE FROM admins WHERE id = ?');
        $stmt->execute([$_POST['admin_id']]);
        echo "Admin deleted successfully!";
    } elseif (isset($_POST['update_role'])) {
        // Update admin role
        $stmt = $pdo->prepare('UPDATE admins SET role = ? WHERE id = ?');
        $stmt->execute([$_POST['new_role'], $_POST['admin_id']]);
        echo "Admin role updated successfully!";
    }
}

// Fetch all admins for display
$stmt = $pdo->prepare('SELECT * FROM admins');
$stmt->execute();
$admins = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Settings</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <header>
        <h1>Dashboard Settings</h1>
        <a href="dashboard.php" class="back-button">Back to Dashboard</a>
    </header>
    <main>
        <h2>Manage Admins</h2>
        <table>
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                <tr>
                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                    <td><?php echo htmlspecialchars($admin['role']); ?></td>
                    <td>
                        <form action="dashsettings.php" method="post" style="display:inline;">
                            <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                            <select name="new_role">
                                <option value="admin" <?php if ($admin['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                            </select>
                            <button type="submit" name="update_role">Update Role</button>
                        </form>
                        <form action="dashsettings.php" method="post" style="display:inline;">
                            <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                            <button type="submit" name="delete">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
    <footer>
        <p>&copy; 2024 Lokpix. All rights reserved.</p>
    </footer>
</body>
</html>
