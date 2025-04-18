<?php
include 'db_connect.php';

try {
    // Disable foreign key checks temporarily
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Drop existing tables if they exist
    $pdo->exec("DROP TABLE IF EXISTS order_details");
    $pdo->exec("DROP TABLE IF EXISTS orders");

    // Create new orders table
    $pdo->exec("
        CREATE TABLE orders (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            address TEXT NOT NULL,
            wilaya_id INT(11) NOT NULL,
            delivery_type VARCHAR(50) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            order_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            qrtoken VARCHAR(255) NOT NULL,
            tracking_number VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY qrtoken (qrtoken),
            KEY user_id (user_id),
            KEY wilaya_id (wilaya_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    echo "<p>Orders table created successfully.</p>";

    // Create new order_details table
    $pdo->exec("
        CREATE TABLE order_details (
            id INT(11) NOT NULL AUTO_INCREMENT,
            order_id INT(11) NOT NULL,
            product_id INT(11) NOT NULL,
            quantity INT(11) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            CONSTRAINT order_details_ibfk_1 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT order_details_ibfk_2 FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    echo "<p>Order details table created successfully.</p>";

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Verify the table structures
    $stmt = $pdo->query("SHOW CREATE TABLE orders");
    $ordersInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SHOW CREATE TABLE order_details");
    $orderDetailsInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Tables created successfully!</h2>";
    
    echo "<h3>Orders Table Structure:</h3>";
    echo "<pre>" . htmlspecialchars($ordersInfo['Create Table']) . "</pre>";
    
    echo "<h3>Order Details Table Structure:</h3>";
    echo "<pre>" . htmlspecialchars($orderDetailsInfo['Create Table']) . "</pre>";

   
} catch (PDOException $e) {
    echo "<h2>Error creating tables:</h2>";
    echo "<pre>Error message: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "Error code: " . htmlspecialchars($e->getCode()) . "</pre>";
    error_log("Error in create_orders_tables.php: " . $e->getMessage());
    
    // Re-enable foreign key checks in case of error
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (Exception $e) {
        // Ignore any errors here
    }
} 