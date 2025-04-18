<?php
session_start();

// Return cart count as JSON
header('Content-Type: application/json');

$count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

echo json_encode([
    'success' => true,
    'count' => $count
]);
?>

    
