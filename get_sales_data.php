<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get the requested period
$period = isset($_GET['period']) ? $_GET['period'] : 'week';

// Set the date range based on the period
$date_from = date('Y-m-d');
switch ($period) {
    case 'week':
        $date_from = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'month':
        $date_from = date('Y-m-d', strtotime('-30 days'));
        break;
    case 'year':
        $date_from = date('Y-m-d', strtotime('-1 year'));
        break;
    default:
        $date_from = date('Y-m-d', strtotime('-7 days'));
}

try {
    // Get sales data for the specified period
    $stmt = $pdo->prepare('
        SELECT products.name, SUM(order_details.quantity) AS total_sold 
        FROM order_details 
        JOIN products ON order_details.product_id = products.id 
        JOIN orders ON order_details.order_id = orders.id
        WHERE orders.order_date >= ?
        GROUP BY products.id 
        ORDER BY total_sold DESC
    ');
    $stmt->execute([$date_from]);
    $products_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data arrays
    $productNames = [];
    $soldQuantities = [];

    foreach ($products_sales as $product) {
        $productNames[] = $product['name'];
        $soldQuantities[] = (int) $product['total_sold'];
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'productNames' => $productNames,
        'soldQuantities' => $soldQuantities
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 