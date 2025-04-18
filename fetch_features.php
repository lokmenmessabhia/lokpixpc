<?php
include 'db_connect.php'; // Ensure this path is correct

header('Content-Type: application/json');

// Fetch features from the database
$stmt = $pdo->prepare("SELECT title, description, photo FROM features ORDER BY created_at DESC");
$stmt->execute();
$features = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($features);
?>
