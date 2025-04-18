<?php
// Include your database connection
include('db_connect.php'); // Make sure you have the correct path to your db connection file

// Check if the qrtoken is provided in the URL
if (isset($_GET['qrtoken'])) {
    $qrtoken = $_GET['qrtoken'];

    // Query to get the order information based on the qrtoken
    $sql = "SELECT * FROM orders WHERE qrtoken = :qrtoken";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':qrtoken', $qrtoken, PDO::PARAM_STR);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if the order exists
    if ($order) {
        // Fetch order details (now using the correct table name `order_details`)
        $order_id = $order['id'];
        $order_items_sql = "SELECT od.*, p.name, p.price AS unit_price
                            FROM order_details od 
                            JOIN products p ON od.product_id = p.id 
                            WHERE od.order_id = :order_id";
        $stmt = $pdo->prepare($order_items_sql);
        $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt->execute();
        $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Display the order details
        echo "<h1>Order Details</h1>";
        echo "<p><strong>Order ID:</strong> " . $order['id'] . "</p>";
        echo "<p><strong>Email:</strong> " . $order['email'] . "</p>";
        echo "<p><strong>Phone:</strong> " . $order['phone'] . "</p>";
        echo "<p><strong>Address:</strong> " . $order['address'] . "</p>";
        echo "<p><strong>Delivery Type:</strong> " . $order['delivery_type'] . "</p>";
        echo "<p><strong>Total Price:</strong> $" . $order['total_price'] . "</p>";
        echo "<p><strong>Order Date:</strong> " . $order['order_date'] . "</p>";
        echo "<p><strong>Status:</strong> " . $order['status'] . "</p>";

        // Display the ordered items
        echo "<h2>Ordered Items</h2>";
        echo "<table border='1' cellpadding='10' cellspacing='0'>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total Price</th>
                </tr>";
        foreach ($order_items as $item) {
            // Calculate the total price per item
            $item_total = $item['quantity'] * $item['unit_price'];

            echo "<tr>
                    <td>" . htmlspecialchars($item['name']) . "</td>
                    <td>" . $item['quantity'] . "</td>
                    <td>$" . number_format($item['unit_price'], 2) . "</td>
                    <td>$" . number_format($item_total, 2) . "</td>
                </tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Order not found. Please check the QR code and try again.</p>";
    }
} else {
    echo "<p>Invalid request. No QR token found.</p>";
}
?>
