<?php
session_start();
include 'db_connect.php';

// AJAX handler for notifications
if (isset($_GET['ajax']) && $_GET['ajax'] === 'notifications') {
    $action = isset($_GET['action']) ? $_GET['action'] : 'fetch';
    
    if ($action === 'mark_read') {
        // Mark all notifications as read
        try {
            $sql = "UPDATE notifications SET is_read = 1 WHERE is_read = 0";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit; // Stop execution after handling AJAX request
    } else {
        // Fetch notifications (default action)
        try {
            $query = "SELECT id, message, created_at FROM notifications WHERE is_read = 0 ORDER BY created_at DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Return notifications as JSON
            header('Content-Type: application/json');
            echo json_encode($notifications);
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit; // Stop execution after handling AJAX request
    }
}

$isAdmin = false; // Default to false
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$_SESSION['email']]);
        $admin = $stmt->fetch();

        if ($admin) {
            $isAdmin = true; // Set true if email exists in the admins table
            $_SESSION['admin_id'] = $admin['id']; // Store admin ID in session
            $_SESSION['admin_role'] = $admin['role']; // Store admin role in session
        }
    } catch (PDOException $e) {
        echo "Error: Unable to verify admin status. " . $e->getMessage();
    }
}

// Check if the user is logged in and is an admin
if (!$isAdmin) {
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

// Fetch random product and feature
$stmt = $pdo->prepare('SELECT * FROM products ORDER BY RAND() LIMIT 1');
$stmt->execute();
$product = $stmt->fetch();

// Fetch analytics
// 1. Most sold product
$stmt = $pdo->prepare('
    SELECT products.name, SUM(order_details.quantity) AS total_sold 
    FROM order_details 
    JOIN products ON order_details.product_id = products.id 
    GROUP BY products.id 
    ORDER BY total_sold DESC 
    LIMIT 1
');
$stmt->execute();
$most_sold_product = $stmt->fetch();

// 2. Total capital (stock value)
$stmt = $pdo->prepare('SELECT SUM(price * stock) AS total_capital FROM products');
$stmt->execute();
$total_capital = $stmt->fetchColumn();

// 3. Total profit from products (only for superadmin)
$total_profit = 0;
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin') {
    // Calculate total profit based on the orders
    $stmt = $pdo->prepare('
        SELECT 
            SUM((products.price - products.buying_price) * order_details.quantity) AS total_profit
        FROM order_details
        JOIN products ON order_details.product_id = products.id');
    $stmt->execute();
    $total_profit = $stmt->fetchColumn();
}

// Get sales data for chart
$stmt = $pdo->prepare('
    SELECT products.name, SUM(order_details.quantity) AS total_sold 
    FROM order_details 
    JOIN products ON order_details.product_id = products.id 
    GROUP BY products.id 
    ORDER BY total_sold DESC
');
$stmt->execute();
$products_sales = $stmt->fetchAll();

// Prepare product names, quantities, and percentages for Chart.js
$product_names = [];
$sold_quantities = [];
$percentages = [];
$total_sales = 0;

foreach ($products_sales as $product) {
    $product_names[] = $product['name'];
    $sold_quantities[] = (int) $product['total_sold'];
    $total_sales += (int) $product['total_sold'];
}

// Calculate percentages
foreach ($sold_quantities as $quantity) {
    $percentages[] = ($total_sales > 0) ? ($quantity / $total_sales) * 100 : 0;
}

// Fetch unread notifications for the logged-in admin
$query = "SELECT id, message, created_at FROM notifications WHERE is_read = 0 ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();

// Fetch notifications
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count new notifications
$new_orders_count = count($notifications);
try {
    $sql = "UPDATE notifications SET is_read = 1 WHERE is_read = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
} catch (PDOException $e) {
    // Handle error
}

$settings = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT * FROM site_settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Error fetching site settings: " . $e->getMessage());
    }
}

// Default theme settings if not set in database
$default_theme = [
    'primary_color' => '#4361ee',
    'secondary_color' => '#6c757d',
    'accent_color' => '#3f37c9',
    'success_color' => '#2ecc71',
    'danger_color' => '#e74c3c',
    'warning_color' => '#f1c40f',
    'info_color' => '#3498db',
    'text_color' => '#2c3e50',
    'text_secondary' => '#6c757d',
    'background_color' => '#f4f6f9',
    'card_background' => '#ffffff',
    'border_color' => '#e9ecef',
    'theme_mode' => 'light',
    'font_family' => 'Poppins'
];

// Merge database settings with defaults
$theme = array_merge($default_theme, $settings);

// Apply dark mode overrides if enabled
if (($theme['theme_mode'] ?? 'light') === 'dark') {
    $theme = array_merge($theme, [
        'background_color' => '#1a1a1a',
        'card_background' => '#2d2d2d',
        'text_color' => '#f8f9fa',
        'text_secondary' => '#adb5bd',
        'border_color' => '#343a40'
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($theme['site_name'] ?? 'Admin Dashboard') ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=<?= str_replace(' ', '+', $theme['font_family']) ?>:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: <?= htmlspecialchars($theme['primary_color']) ?>;
            --secondary: <?= htmlspecialchars($theme['secondary_color']) ?>;
            --accent: <?= htmlspecialchars($theme['accent_color']) ?>;
            --success: <?= htmlspecialchars($theme['success_color']) ?>;
            --danger: <?= htmlspecialchars($theme['danger_color']) ?>;
            --warning: <?= htmlspecialchars($theme['warning_color']) ?>;
            --info: <?= htmlspecialchars($theme['info_color']) ?>;
            --text: <?= htmlspecialchars($theme['text_color']) ?>;
            --text-secondary: <?= htmlspecialchars($theme['text_secondary']) ?>;
            --background: <?= htmlspecialchars($theme['background_color']) ?>;
            --card-bg: <?= htmlspecialchars($theme['card_background']) ?>;
            --border: <?= htmlspecialchars($theme['border_color']) ?>;
            --radius: 8px;
            --shadow: 0 2px 4px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: '<?= htmlspecialchars($theme['font_family']) ?>', sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.6;
        }

        /* Top Navigation */
        .top-nav {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            padding: 0.85rem 1.75rem;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 40;
            display: flex;
            align-items: center;
            gap: 2rem;
            box-shadow: var(--shadow);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: max-content;
        }

        .nav-brand h1 {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-size: 1.4rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .nav-menu a {
            color: var(--text);
            text-decoration: none;
            padding: 0.6rem 0.9rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
        }

        .nav-menu a:hover {
            background: var(--background);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .nav-end {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: max-content;
        }

        /* Main Content */
        .main-content {
            margin-top: 4.5rem;
            padding: 2rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background: var(--card-bg);
            padding: 1.75rem;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }

        .card h2 {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .card p {
            color: var(--text);
            font-size: 1.5rem;
            font-weight: 600;
        }

        /* Chart */
        .chart-container {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.75rem;
            border: 1px solid var(--border);
            margin-top: 2rem;
            min-height: 400px;
            position: relative;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .chart-container:hover {
            box-shadow: var(--shadow);
        }

        .chart-container h2 {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .chart-container canvas {
            width: 100% !important;
            height: 350px !important;
        }

        /* Notifications */
        .notification-icon {
            background: var(--background);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .notification-icon svg {
            width: 18px;
            height: 18px;
            color: var(--text);
        }

        .notification-icon:hover {
            background: var(--primary);
            transform: scale(1.05);
        }

        .notification-icon:hover svg {
            color: white;
        }

        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: var(--danger);
            color: white;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
            border: 2px solid var(--card-bg);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .notification-popup {
            position: fixed;
            top: 4rem;
            right: 1.5rem;
            width: 350px;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            z-index: 1000;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            max-height: 450px;
            overflow-y: auto;
            border: 1px solid var(--border);
            display: none; /* Start hidden */
        }

        .notification-popup.show {
            opacity: 1;
            transform: translateY(0);
        }

        .popup-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
            border-top-left-radius: var(--radius);
            border-top-right-radius: var(--radius);
        }

        .popup-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--text);
            font-weight: 600;
        }

        .mark-read-btn {
            background: transparent;
            color: var(--primary);
            border: none;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            padding: 0.35rem 0.75rem;
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .mark-read-btn:hover {
            background: rgba(var(--primary), 0.1);
        }

        .notification-list {
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .notification-item {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
            cursor: pointer;
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background-color: #f0f4ff;
        }

        .notification-dot {
            width: 10px;
            height: 10px;
            background-color: var(--primary);
            border-radius: 50%;
            margin-top: 0.5rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.35rem;
        }

        .notification-message {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.4;
        }

        .notification-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.35rem;
        }

        .empty-notifications {
            padding: 2.5rem;
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
        }

        .welcome-section h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, var(--text), var(--primary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .date {
            color: var(--text-secondary);
            font-size: 0.925rem;
            font-weight: 500;
        }

        .quick-actions {
            display: flex;
            gap: 1rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1.75rem;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 10px rgba(var(--primary), 0.25);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(var(--primary), 0.35);
        }

        .action-btn svg {
            width: 18px;
            height: 18px;
        }

        .stats-overview {
            margin-bottom: 2.5rem;
        }

        .card {
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            padding: 1.75rem;
        }

        .card.highlight {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 10px 20px rgba(var(--primary), 0.2);
        }

        .card.highlight h3,
        .card.highlight p {
            color: white;
        }

        .card-icon {
            padding: 0.85rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius);
            backdrop-filter: blur(10px);
        }

        .card-content {
            flex: 1;
        }

        .card h3 {
            font-size: 0.925rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 0.65rem;
        }

        .number {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
        }

        .trend {
            font-size: 0.925rem;
            color: var(--success);
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .subtitle {
            font-size: 0.875rem;
            color: var(--text-secondary);
            opacity: 0.9;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2.25rem;
            margin-bottom: 2.5rem;
        }

        .chart-section {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 0;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .chart-section:hover {
            box-shadow: var(--shadow);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
        }

        .chart-actions {
            display: flex;
            gap: 0.5rem;
        }

        .chart-period {
            padding: 0.5rem 1.1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--background);
            color: var(--text);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.85rem;
        }

        .chart-period:hover {
            background: #edf2ff;
            border-color: var(--primary);
        }

        .chart-period.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 8px rgba(var(--primary), 0.25);
        }

        .recent-activity {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.75rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .recent-activity:hover {
            box-shadow: var(--shadow);
        }

        .recent-activity h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 1.25rem;
        }

        .activity-list {
            margin-top: 1.25rem;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1.1rem;
            padding: 1.1rem 0;
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }

        .activity-item:hover {
            transform: translateX(4px);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            padding: 0.65rem;
            border-radius: var(--radius);
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .activity-icon.processing {
            background: rgba(var(--warning), 0.15);
            color: var(--warning);
        }

        .activity-icon.completed {
            background: rgba(var(--success), 0.15);
            color: var(--success);
        }

        .activity-details {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: 0.35rem;
            color: var(--text);
        }

        .activity-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .activity-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .activity-status {
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .activity-status.processing {
            background: rgba(var(--warning), 0.15);
            color: var(--warning);
        }

        .activity-status.completed {
            background: rgba(var(--success), 0.15);
            color: var(--success);
        }

        .admin-insights {
            margin-top: 2.5rem;
        }

        .admin-insights h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            color: var(--text);
        }

        .insights-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2.25rem;
            margin-top: 1.25rem;
        }

        .insight-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.75rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .insight-card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-4px);
        }

        .insight-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 1.25rem;
        }

        .category-list {
            margin-top: 1.25rem;
        }

        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.1rem 0;
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }

        .category-item:hover {
            transform: translateX(4px);
        }

        .category-item:last-child {
            border-bottom: none;
        }

        .category-info h4 {
            font-weight: 600;
            margin-bottom: 0.35rem;
            color: var(--text);
        }

        .category-orders {
            font-size: 0.875rem;
            color: var(--text-secondary);
            padding: 0.35rem 0.75rem;
            background: var(--background);
            border-radius: var(--radius);
            font-weight: 500;
        }

        .inventory-status {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
            margin-top: 1.25rem;
        }

        .inventory-item {
            text-align: center;
            padding: 1.5rem 1rem;
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .inventory-item:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
        }

        .inventory-item.critical {
            background: rgba(var(--danger), 0.1);
            color: var(--danger);
        }

        .inventory-item.warning {
            background: rgba(var(--warning), 0.1);
            color: var(--warning);
        }

        .inventory-item.success {
            background: rgba(var(--success), 0.1);
            color: var(--success);
        }

        .inventory-item .count {
            display: block;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .inventory-item .label {
            font-size: 0.875rem;
            font-weight: 500;
        }

        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .insights-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.25rem;
            }

            .quick-actions {
                width: 100%;
            }

            .action-btn {
                flex: 1;
            }

            .grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="nav-brand">
            <h1><?= htmlspecialchars($settings['site_name'] ?? 'EcoTech') ?></h1>
        </div>
        <div class="nav-menu">
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin'): ?>
                <a href="add_admin.php">Admins</a>
            <?php endif; ?>
            <a href="add_feature.php">Features</a>
            <a href="dashboard_products.php">Products</a>
            <a href="orders.php">Orders</a>
            <a href="manage_recycle.php">Recycling</a>
            <a href="manage_slider.php">Slider</a>
            <a href="manage_marketplace.php">Marketplace</a>
            <a href="manage_categories.php">categories</a>
            <a href="settings.php">Customizing</a>
            <a href="index.php">back to Website</a>
        </div>
        <div class="nav-end">
            <div class="notification-icon" id="notificationIcon">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                <span class="notification-badge"><?php echo $new_orders_count; ?></span>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($admin['email']); ?></h1>
                <p class="date"><?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="quick-actions">
            <button class="action-btn" onclick="location.href='add_product.php'">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 5v14M5 12h14"/>
    </svg>
    Add Product
</button>



   
</div>
        </div>

        <div class="stats-overview">
            <div class="grid">
                <div class="card highlight">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12V8H6a2 2 0 01-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 000 4h4v-4z"/></svg>
                    </div>
                    <div class="card-content">
                        <h3>Total Revenue</h3>
                        <p class="number">
                            <?php
                                $stmt = $pdo->prepare('
                                    SELECT COALESCE(SUM(p.price * od.quantity), 0) as total_revenue
                                    FROM orders o
                                    JOIN order_details od ON o.id = od.order_id
                                    JOIN products p ON od.product_id = p.id
                                ');
                                $stmt->execute();
                                echo number_format($stmt->fetchColumn(), 2) . ' DZD';
                            ?>
                        </p>
                        <p class="trend">
                            <?php
                                $last_month = date('Y-m-d', strtotime('-1 month'));
                                $stmt = $pdo->prepare('
                                    SELECT COALESCE(SUM(p.price * od.quantity), 0) as last_month_revenue
                                    FROM orders o
                                    JOIN order_details od ON o.id = od.order_id
                                    JOIN products p ON od.product_id = p.id
                                    WHERE DATE(o.order_date) >= ?
                                ');
                                $stmt->execute([$last_month]);
                                $monthly_revenue = $stmt->fetchColumn();
                                echo 'Last 30 days: ' . number_format($monthly_revenue, 2) . ' DZD';
                            ?>
                        </p>
                    </div>
                </div>

                <div class="card">
    <div class="card-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-7L10 4H4a2 2 0 00-2 2v12c0 1.1.9 2 2 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/></svg>
    </div>
    <div class="card-content">
        <h3>Total Products</h3>
        <p class="number">
            <?php
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM products');
                $stmt->execute();
                echo $stmt->fetchColumn();
            ?>
        </p>
        <p class="subtitle">In Store</p>
    </div>
</div>

<div class="card">
    <div class="card-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
    </div>
    <div class="card-content">
        <h3>Total Sales</h3>
        <p class="number">
            <?php
                $stmt = $pdo->prepare('
                    SELECT COUNT(*) 
                    FROM orders
                ');
                $stmt->execute();
                echo $stmt->fetchColumn();
            ?>
        </p>
        <p class="subtitle">All Time</p>
    </div>
</div>

                <div class="card">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-7L10 4H4a2 2 0 00-2 2v12c0 1.1.9 2 2 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/></svg>
                    </div>
                    <div class="card-content">
                        <h3>Low Stock Items</h3>
                        <p class="number">
                            <?php
                                $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE stock <= 10');
                                $stmt->execute();
                                echo $stmt->fetchColumn();
                            ?>
                        </p>
                        <p class="subtitle">Need Attention</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="chart-section">
                <div class="chart-container">
                    <div class="chart-header">
                        <h2>Sales Overview</h2>
                        <div class="chart-actions">
                            <button class="chart-period active" data-period="week">Week</button>
                            <button class="chart-period" data-period="month">Month</button>
                            <button class="chart-period" data-period="year">Year</button>
                        </div>
                    </div>
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="recent-activity">
                <h2>Recent Activity</h2>
                <div class="activity-list">
                    <?php
                        // Fetch recent orders
                        $stmt = $pdo->prepare('
                            SELECT o.id, o.order_date, u.email, o.status,
                                   SUM(p.price * od.quantity) as total_amount
                            FROM orders o
                            JOIN users u ON o.user_id = u.id
                            JOIN order_details od ON o.id = od.order_id
                            JOIN products p ON od.product_id = p.id
                            GROUP BY o.id, o.order_date, u.email, o.status
                            ORDER BY o.order_date DESC
                            LIMIT 5
                        ');
                        $stmt->execute();
                        $recent_orders = $stmt->fetchAll();

                        foreach ($recent_orders as $order):
                    ?>
                    <div class="activity-item">
                        <div class="activity-icon <?php echo $order['status']; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/></svg>
                        </div>
                        <div class="activity-details">
                            <p class="activity-title">New order #<?php echo $order['id']; ?></p>
                            <p class="activity-meta">
                                <?php echo htmlspecialchars($order['email']); ?> •
                                <?php echo number_format($order['total_amount'], 2); ?> DZD
                            </p>
                            <p class="activity-time"><?php echo date('g:i A', strtotime($order['order_date'])); ?></p>
                        </div>
                        <div class="activity-status <?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin'): ?>
        <div class="admin-insights">
            <h2>Business Insights</h2>
            <div class="insights-grid">
                <div class="insight-card">
                    <h3>Top Performing Categories</h3>
                    <div class="category-list">
                        <?php
                            $stmt = $pdo->prepare('
                                SELECT c.name, COUNT(o.id) as order_count, SUM(p.price * od.quantity) as revenue
                                FROM categories c
                                JOIN products p ON c.id = p.category_id
                                JOIN order_details od ON p.id = od.product_id
                                JOIN orders o ON od.order_id = o.id
                                GROUP BY c.id
                                ORDER BY revenue DESC
                                LIMIT 3
                            ');
                            $stmt->execute();
                            $top_categories = $stmt->fetchAll();

                            foreach ($top_categories as $category):
                        ?>
                        <div class="category-item">
                            <div class="category-info">
                                <h4><?php echo htmlspecialchars($category['name']); ?></h4>
                                <p><?php echo number_format($category['revenue'], 2); ?> DZD</p>
                            </div>
                            <div class="category-orders">
                                <?php echo $category['order_count']; ?> orders
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="insight-card">
                    <h3>Inventory Status</h3>
                    <div class="inventory-status">
                        <?php
                            $stmt = $pdo->prepare('
                                SELECT 
                                    SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
                                    SUM(CASE WHEN stock BETWEEN 1 AND 10 THEN 1 ELSE 0 END) as low_stock,
                                    SUM(CASE WHEN stock > 10 THEN 1 ELSE 0 END) as in_stock
                                FROM products
                            ');
                            $stmt->execute();
                            $inventory = $stmt->fetch();
                        ?>
                        <div class="inventory-item critical">
                            <span class="count"><?php echo $inventory['out_of_stock']; ?></span>
                            <span class="label">Out of Stock</span>
                        </div>
                        <div class="inventory-item warning">
                            <span class="count"><?php echo $inventory['low_stock']; ?></span>
                            <span class="label">Low Stock</span>
                        </div>
                        <div class="inventory-item success">
                            <span class="count"><?php echo $inventory['in_stock']; ?></span>
                            <span class="label">In Stock</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

<div class="notification-popup" id="notificationPopup">
    <div class="popup-header">
        <h3>Notifications</h3>
        <button id="markAsRead" class="mark-read-btn">Mark all as read</button>
    </div>
    <div class="notification-list" id="notificationList">
        <!-- Notifications will be dynamically inserted here -->
        <div class="empty-notifications">
            <p>No new notifications</p>
        </div>
    </div>
</div>

<script>
    document.getElementById('notificationIcon').addEventListener('click', function(event) {
    const popup = document.getElementById('notificationPopup');
    const currentDisplay = window.getComputedStyle(popup).display;

    // Toggle popup visibility with smooth animation
    if (currentDisplay === 'none' || currentDisplay === '') {
        popup.style.display = 'block';
        // Fetch notifications when opening the popup
        fetchNotifications();
        setTimeout(() => popup.classList.add('show'), 10); // Ensure smooth fade-in
    } else {
        popup.classList.remove('show');
        setTimeout(() => popup.style.display = 'none', 300); // Hide after animation
    }
    
    event.stopPropagation(); // Prevent click propagation
});

// Close the popup if clicked outside
window.addEventListener('click', function(event) {
    const popup = document.getElementById('notificationPopup');
    const notificationIcon = document.getElementById('notificationIcon');
    
    // Ensure click outside of the notification popup or icon closes the popup
    if (!popup.contains(event.target) && !notificationIcon.contains(event.target)) {
        popup.classList.remove('show');
        setTimeout(() => popup.style.display = 'none', 300);
    }
});

// Handle the "Mark All as Read" functionality
document.getElementById('markAsRead').addEventListener('click', function() {
    console.log('Marking all as read...');
    // Make AJAX request to mark all notifications as read in the database
    fetch('dashboard.php?ajax=notifications&action=mark_read')
        .then(response => {
            console.log('Mark as read response:', response);
            return response.json();
        })
        .then(data => {
            console.log('Mark as read data:', data);
            if(data.success) {
                // After successfully marking as read in database, update the UI
                const notificationBadge = document.querySelector('.notification-badge');
                if(notificationBadge) {
                    notificationBadge.textContent = '0';
                    notificationBadge.style.display = 'none';
                }
                
                // Update the notification list to show no unread notifications
                document.getElementById('notificationList').innerHTML = 
                    '<div class="empty-notifications"><p>No new notifications</p></div>';
            }
        })
        .catch(error => console.error('Error marking notifications as read:', error));
});

// Function to fetch notifications
function fetchNotifications() {
    console.log('Fetching notifications...');
    fetch('dashboard.php?ajax=notifications')
        .then(response => {
            console.log('Fetch response:', response);
            return response.json();
        })
        .then(data => {
            console.log('Notification data:', data);
            const notificationList = document.getElementById('notificationList');
            notificationList.innerHTML = ''; // Clear existing notifications

            if (data && Array.isArray(data) && data.length > 0) {
                data.forEach(notification => {
                    // Create notification item with proper structure to match CSS
                    const notificationItem = document.createElement('div');
                    notificationItem.className = 'notification-item';
                    
                    // Format date to a more readable format
                    const createdDate = new Date(notification.created_at);
                    const formattedDate = createdDate.toLocaleString();
                    
                    notificationItem.innerHTML = `
                        <div class="notification-dot"></div>
                        <div class="notification-content">
                            <div class="notification-message">${notification.message}</div>
                            <div class="notification-time">${formattedDate}</div>
                        </div>
                    `;
                    
                    notificationList.appendChild(notificationItem);
                });
                
                // Also update badge
                const notificationBadge = document.querySelector('.notification-badge');
                if(notificationBadge) {
                    notificationBadge.textContent = data.length;
                    notificationBadge.style.display = data.length > 0 ? 'flex' : 'none';
                }
            } else {
                notificationList.innerHTML = '<div class="empty-notifications"><p>No new notifications</p></div>';
                
                // Hide badge when no notifications
                const notificationBadge = document.querySelector('.notification-badge');
                if(notificationBadge) {
                    notificationBadge.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
            document.getElementById('notificationList').innerHTML = 
                '<div class="empty-notifications"><p>Error loading notifications</p></div>';
        });
}

// Initialize popup as hidden
document.getElementById('notificationPopup').style.display = 'none';

// Initial fetch of notifications
fetchNotifications();

// Fetch notifications every 30 seconds
setInterval(fetchNotifications, 30000);
</script>



    <script>
        // Check if the data arrays are empty
        if (<?php echo count($product_names); ?> === 0 || <?php echo count($sold_quantities); ?> === 0) {
            alert('No sales data available for the chart');
        } else {
            // Pass PHP data to JavaScript
            const productNames = <?php echo json_encode($product_names); ?>;
            const soldQuantities = <?php echo json_encode($sold_quantities); ?>;
            const percentages = <?php echo json_encode($percentages); ?>;
            
            // Create the chart using Chart.js
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            // Create the chart with original options
            const salesChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: productNames,
                    datasets: [{
                        data: soldQuantities,
                        backgroundColor: [
                            '#4a69a5',    // Primary blue
                            '#2ecc71',    // Green
                            '#3498db',    // Light Blue
                            '#9b59b6',    // Purple
                            '#f1c40f',    // Yellow
                            '#e74c3c',    // Red
                            '#1abc9c',    // Turquoise
                            '#ff6f61'     // Coral
                        ],
                        borderWidth: 0,
                        hoverOffset: 15   // Increase hover effect
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: 20
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 8,
                                boxHeight: 8,
                                font: {
                                    family: "'Poppins', sans-serif",
                                    size: 12,
                                    weight: '500'
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(255, 255, 255, 0.95)',
                            titleColor: '#4a69a5',
                            bodyColor: '#2c3e50',
                            titleFont: {
                                family: "'Poppins', sans-serif",
                                size: 14,
                                weight: '600'
                            },
                            bodyFont: {
                                family: "'Poppins', sans-serif",
                                size: 13
                            },
                            borderColor: '#e2e8f0',
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            cornerRadius: 8,
                            displayColors: true,
                            boxWidth: 8,
                            boxHeight: 8,
                            usePointStyle: true,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const percentage = percentages[context.dataIndex].toFixed(1);
                                    return ` ${label}: ${value} units (${percentage}%)`;
                                },
                                labelPointStyle: function(context) {
                                    return {
                                        pointStyle: 'circle',
                                        rotation: 0
                                    };
                                }
                            }
                        }
                    },
                    cutout: '65%',
                    radius: '85%',
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    },
                    hover: {
                        mode: 'nearest',
                        intersect: true
                    }
                }
            });

            // Add event listeners to period buttons
            document.querySelectorAll('.chart-period').forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    document.querySelectorAll('.chart-period').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Get the selected period
                    const period = this.getAttribute('data-period');
                    
                    // Fetch data for the selected period
                    fetchSalesDataByPeriod(period);
                });
            });

            // Function to fetch sales data by period
            function fetchSalesDataByPeriod(period) {
                // Show loading state
                document.getElementById('salesChart').style.opacity = '0.5';
                
                // Make AJAX request to fetch data
                fetch(`get_sales_data.php?period=${period}`)
                    .then(response => response.json())
                    .then(data => {
                        // Update chart with new data
                        salesChart.data.labels = data.productNames;
                        salesChart.data.datasets[0].data = data.soldQuantities;
                        
                        // Calculate new percentages
                        const totalSales = data.soldQuantities.reduce((a, b) => a + b, 0);
                        const newPercentages = data.soldQuantities.map(qty => 
                            totalSales > 0 ? (qty / totalSales) * 100 : 0
                        );
                        
                        // Update chart tooltip to use new percentages
                        salesChart.options.plugins.tooltip.callbacks.label = function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const percentage = newPercentages[context.dataIndex].toFixed(1);
                            return ` ${label}: ${value} units (${percentage}%)`;
                        };
                        
                        // Update chart
                        salesChart.update();
                        
                        // Remove loading state
                        document.getElementById('salesChart').style.opacity = '1';
                    })
                    .catch(error => {
                        console.error('Error fetching sales data:', error);
                        // Remove loading state
                        document.getElementById('salesChart').style.opacity = '1';
                    });
            }
        }
    </script>

    <script>
        // Mobile menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');

            // Toggle menu when hamburger is clicked
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            });

            // Close menu when overlay is clicked
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });

            // Close menu when a nav link is clicked
            document.querySelectorAll('.sidebar nav a').forEach(link => {
                link.addEventListener('click', () => {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                });
            });
        });
    </script>
</body>
</html>