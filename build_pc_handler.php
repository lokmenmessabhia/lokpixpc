<?php
// Include database connection
include 'db_connect.php';

// Fetch selected product IDs from the form submission
$selectedProductIds = [];
foreach ($_POST as $key => $value) {
    if (!empty($value) && is_numeric($value)) {
        $selectedProductIds[] = (int) $value; // Collect the selected product IDs
    }
}

// Retrieve email, phone, and wilaya from POST data
$email = isset($_POST['email']) ? $_POST['email'] : 'N/A';
$phone = isset($_POST['phone']) ? $_POST['phone'] : 'N/A';
$wilaya = isset($_POST['wilaya']) ? $_POST['wilaya'] : 'N/A';

$response = [];

if (!empty($selectedProductIds)) {
    // Prepare the query to fetch selected products with their prices
    $placeholders = implode(',', array_fill(0, count($selectedProductIds), '?'));
    $stmt = $pdo->prepare("SELECT name, price FROM products WHERE id IN ($placeholders)");
    $stmt->execute($selectedProductIds);

    $selectedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate the total price
    $totalPrice = 0;
    $productDetails = '';
    foreach ($selectedProducts as $product) {
        $totalPrice += $product['price'];
        $productDetails .= $product['name'] . ' - $' . $product['price'] . "\n";
    }

    // Format the message
    $message = "Build PC Order:\n\n";
    $message .= "Email: $email\n";
    $message .= "Phone: $phone\n";
    $message .= "Wilaya: $wilaya\n\n";
    $message .= "Selected Components:\n" . $productDetails;
    $message .= "Total Price: $" . number_format($totalPrice, 2) . "\n";

    // Telegram Bot Token and Chat ID
    $token = "7322742533:AAEEYMpmOGhkwuOyfU-6Y4c6UtjK09ti9vE";
    $chatId = "2110723601"; // Replace with your actual chat ID

    // Send the message to Telegram
    $telegramApiUrl = "https://api.telegram.org/bot$token/sendMessage";
    $params = [
        'chat_id' => $chatId,
        'text' => $message,
    ];

 $ch = curl_init($telegramApiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response !== false) {
        echo "<script>showPopup('Order details sent successfully!');</script>";
    } else {
        echo "<script>showPopup('Error sending order details.');</script>";
    }
} else {
    echo "<script>showPopup('No products selected.');</script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - Lokpix</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f4f4f4;
        }
        .container {
            text-align: center;
            background-color: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #4CAF50;
        }
        p {
            font-size: 1.2rem;
            margin: 1rem 0;
        }
        .back-button {
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 20px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
        }
        .back-button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Thank You!</h1>
        <p>Your order has been placed successfully.</p>
        <a href="buildyourpc.php" class="back-button">Back to Build PC</a>
    </div>
</body>
</html>
