<?php
session_start();
include 'db_connect.php';

// Debug information - remove in production
echo "<pre>";
echo "Session Data:\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "\n";
echo "Order ID in Session: " . ($_SESSION['order_id'] ?? 'Not set') . "\n";
echo "GET Data:\n";
echo "Order ID in URL: " . ($_GET['order_id'] ?? 'Not set') . "\n";
echo "</pre>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in, redirecting to login");
    header("Location: login.php");
    exit();
}

// Get order ID from URL or session
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id && isset($_SESSION['order_id'])) {
    $order_id = (int)$_SESSION['order_id'];
}

// Debug log
error_log("Attempting to view order. ID from GET: " . ($_GET['order_id'] ?? 'Not set') . 
         ", Filtered ID: " . ($order_id ?? 'Not set') . 
         ", Session ID: " . ($_SESSION['order_id'] ?? 'Not set'));

if (!$order_id) {
    error_log("No valid order ID found. Redirecting to index.");
    echo "<pre>Error: No valid order ID found.</pre>";
    sleep(3); // Add delay to see debug info
    header("Location: index.php");
    exit();
}

try {
    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT o.*, w.name as wilaya_name 
        FROM orders o 
        LEFT JOIN wilayas w ON o.wilaya_id = w.id 
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        error_log("Order not found or doesn't belong to user. Order ID: $order_id, User ID: " . $_SESSION['user_id']);
        echo "<pre>Error: Order not found or doesn't belong to you.</pre>";
        sleep(3); // Add delay to see debug info
        header("Location: index.php");
        exit();
    }

} catch (PDOException $e) {
    error_log("Error verifying order: " . $e->getMessage());
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success - Ecotech</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #f0f2f5 0%, #e5e9f0 100%);
        }

        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            animation: fadeIn 0.3s ease forwards;
        }

        .popup-content {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 500px;
            width: 90%;
            text-align: center;
            transform: scale(0.7);
            animation: scaleIn 0.3s ease forwards;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            background: #10B981;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .success-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }

        .popup-title {
            color: #1F2937;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .popup-message {
            color: #6B7280;
            margin-bottom: 1.5rem;
        }

        .popup-button {
            background: #10B981;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .popup-button:hover {
            background: #059669;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes scaleIn {
            from { transform: scale(0.7); }
            to { transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="popup-overlay">
        <div class="popup-content">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h3 class="popup-title">Order Placed Successfully!</h3>
            <p class="popup-message">Thank you for your order. You will receive a confirmation email shortly.</p>
            <a href="index.php" class="popup-button">Continue Shopping</a>
        </div>
    </div>

    <script>
        // Clear session order data after showing popup
        setTimeout(() => {
            window.location.href = 'index.php';
        }, 5000); // Redirect after 5 seconds
    </script>
</body>
</html> 