<?php
require 'vendor/autoload.php'; // Include DOMPDF

use Dompdf\Dompdf;
use Dompdf\Options;

// Initialize DOMPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true); // Enable PHP if necessary for custom functions
$options->set('isJavascriptEnabled', true); // Enable JavaScript for interactive features if needed
$dompdf = new Dompdf($options);

// Fetch order details from the database
$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : 0; // Get order_id from URL parameter

include 'db_connect.php';

// Ensure order_id is passed via GET
if ($order_id === 0) {
    echo "Error: Order ID is missing.";
    exit();
}

// Prepare and execute SQL query to get the order details
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :order_id');
$stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
$stmt->execute();

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if ($order) {
    // Extract order details
    $customer_name = $order['user_id']; // Assuming user_id stores the customer name or ID
    $email = $order['email'];
    $phone = $order['phone'];
    $delivery_address = $order['delivery_address'];
    $total_price = $order['total_price'];
} else {
    // Handle case when order is not found
    echo "Order not found.";
    exit;
}

// Your HTML content with updated dynamic customer info and bill title
$html = "
<html>
<head>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
            color: #333;
        }
        .container {
            width: 80%;
            margin: 30px auto;
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            font-size: 28px;
            color: #4CAF50;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .section {
            margin-bottom: 15px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        input[type='text'], input[type='email'], input[type='tel'], input[type='number'] {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #f9f9f9;
            font-size: 14px;
        }
        .section:last-child {
            text-align: center;
        }
        .button {
            padding: 12px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #45a049;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            margin-top: 30px;
        }
        .footer a {
            color: #4CAF50;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            {$customer_name}'s Bill
        </div>

        <div class='section'>
            <label for='order_id'>Order ID:</label>
            <input type='text' id='order_id' name='order_id' value='{$order_id}' readonly>
        </div>

        <div class='section'>
            <label for='customer_name'>Customer Name:</label>
            <input type='text' id='customer_name' name='customer_name' value='{$customer_name}' readonly>
        </div>

        <div class='section'>
            <label for='email'>Email:</label>
            <input type='email' id='email' name='email' value='{$email}' readonly>
        </div>

        <div class='section'>
            <label for='phone'>Phone Number:</label>
            <input type='tel' id='phone' name='phone' value='{$phone}' readonly>
        </div>

        <div class='section'>
            <label for='delivery_address'>Delivery Address:</label>
            <input type='text' id='delivery_address' name='delivery_address' value='{$delivery_address}' readonly>
        </div>

        <div class='section'>
            <label for='total_price'>Total Price:</label>
            <input type='text' id='total_price' name='total_price' value='{$total_price}' readonly>
        </div>

        <div class='section'>
            <button class='button'>Submit Order</button>
        </div>
        
        <div class='footer'>
            <p>&copy; 2024 Lokpix. All rights reserved. <br> For any inquiries, <a href='mailto:support@lokpix.com'>contact support</a>.</p>
        </div>
    </div>
</body>
</html>
";

// Load HTML content
$dompdf->loadHtml($html);

// (Optional) Set paper size
$dompdf->setPaper('A4', 'portrait');

// Render PDF (first pass to parse HTML to PDF)
$dompdf->render();

// Output the generated PDF (force download)
$dompdf->stream("{$customer_name}_bill.pdf", array("Attachment" => 0));
?>
