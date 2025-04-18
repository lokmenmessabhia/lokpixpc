<?php
// Start session if not already started
    session_start();
// Include necessary files
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get the user ID from the session
$user_id = $_SESSION['user_id'];

// Fetch user email and phone
try {
    $stmt = $pdo->prepare("SELECT id, email, phone FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("Error: User not found.");
    }
    
    $user_email = $user['email'];
    $user_phone = $user['phone'];
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    die("Error: Unable to fetch user information.");
}

// Generate a secure token if it doesn't exist in session
if (!isset($_SESSION['order_token'])) {
    $_SESSION['order_token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['order_token'];

// Handle checkout form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
    // Validate input
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $wilaya_id = filter_input(INPUT_POST, 'wilaya', FILTER_VALIDATE_INT);
    $delivery_type = $_POST['delivery_type'] ?? '';
        $submitted_token = $_POST['token'] ?? '';
    $status = 'pending';
    
    // Validate required fields
    if (empty($phone) || empty($address) || !$wilaya_id || empty($delivery_type)) {
            throw new Exception("All fields are required. Please fill in all the information.");
    }
    
    // Validate token
    if (empty($submitted_token) || $submitted_token !== $_SESSION['order_token']) {
            throw new Exception("Invalid form submission. Please try again.");
    }
    
    // Check if cart is empty
    if (empty($_SESSION['cart'])) {
            throw new Exception("Your cart is empty. Add items before checkout.");
    }
    
    // Begin transaction
        $pdo->beginTransaction();
        
        // Calculate total price and prepare order items
        $total_price = 0;
        $order_items = [];
        $product_stmt = $pdo->prepare("SELECT id, price, stock FROM products WHERE id = ?");
        
        foreach ($_SESSION['cart'] as $product_id => $quantity) {
            $product_stmt->execute([$product_id]);
            $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new Exception("Product not found: ID " . $product_id);
            }
            
            if ($product['stock'] < $quantity) {
                throw new Exception("Not enough stock for product ID: " . $product_id);
            }
            
            $item_total = $product['price'] * $quantity;
            $total_price += $item_total;
            
            $order_items[] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'price' => $product['price']
            ];
        }
        
        // Insert the order
        $order_stmt = $pdo->prepare("
            INSERT INTO orders 
            (user_id, email, phone, address, wilaya_id, delivery_type, total_price, status, qrtoken) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $order_stmt->execute([
            $user_id, 
            $user_email, 
            $phone, 
            $address, 
            $wilaya_id, 
            $delivery_type, 
            $total_price, 
            $status,
            $token
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        if (!$order_id) {
            throw new Exception("Failed to create order.");
        }
        
        // Insert order details and update product stock
        $detail_stmt = $pdo->prepare("
            INSERT INTO order_details 
            (order_id, product_id, quantity, price) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stock_update_stmt = $pdo->prepare("
            UPDATE products 
            SET stock = stock - ? 
            WHERE id = ? AND stock >= ?
        ");
        
        foreach ($order_items as $item) {
            // Insert order detail
            $detail_stmt->execute([
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $item['price']
            ]);
            
            // Update product stock
            $stock_update_stmt->execute([
                $item['quantity'],
                $item['product_id'],
                $item['quantity']
            ]);
        }
        
        // Send order details to Telegram
        $telegram_message = "New Order:\n\n";
        $telegram_message .= "Order ID: $order_id\n";
        $telegram_message .= "Email: $user_email\n";
        $telegram_message .= "Phone: $phone\n";
        $telegram_message .= "Address: $address\n";
        $telegram_message .= "Wilaya ID: $wilaya_id\n";
        $telegram_message .= "Delivery Type: $delivery_type\n";
        $telegram_message .= "Total Price: $total_price\n";
        $telegram_message .= "Verification Token: $token\n";
        
        $telegram_token = '7322742533:AAEEYMpmOGhkwuOyfU-6Y4c6UtjK09ti9vE';
        $chat_id = '-1002458122628';
        $telegram_url = "https://api.telegram.org/bot$telegram_token/sendMessage";
        
        $ch = curl_init($telegram_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $chat_id,
            'text' => $telegram_message
        ]);
        $telegram_result = curl_exec($ch);
        curl_close($ch);
        
        // Commit transaction
        $pdo->commit();
        
        // Clear cart and generate new token
        unset($_SESSION['cart']);
        $_SESSION['order_token'] = bin2hex(random_bytes(32));
        
        // Store order information in session
        $_SESSION['order_success'] = true;
        $_SESSION['order_id'] = $order_id;
        $_SESSION['order_total'] = $total_price;

        // Debug log
        error_log("Order created successfully. Order ID: " . $order_id . ", User ID: " . $_SESSION['user_id']);
        
        // Redirect to order confirmation
        header("Location: order-confirmation.php?order_id=" . $order_id);
        exit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
        $pdo->rollBack();
        }
        $error_message = "Error processing your order: " . $e->getMessage();
        error_log("Checkout error: " . $error_message);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Ecotech</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <?php include 'header.php'; ?>
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($settings['primary_color'] ?? '#0d6efd') ?>;
            --accent-color: <?= htmlspecialchars($settings['accent_color'] ?? '#0b5ed7') ?>;
            --text-color: var(--header-text);
            --text-secondary: var(--header-text-secondary);
            --border-color: var(--header-border);
            --bg-color: var(--header-bg);
            --bg-secondary: var(--dropdown-hover-bg);
            --summary-text: <?= ($settings['theme_mode'] ?? 'light') === 'dark' ? '#FFFFFF' : '#000000' ?>;
            --summary-text-secondary: <?= ($settings['theme_mode'] ?? 'light') === 'dark' ? '#E5E5E5' : '#4A5568' ?>;
        }

        body {
            font-family: <?= htmlspecialchars($settings['font_family'] ?? 'Poppins, sans-serif') ?>;
            background: linear-gradient(135deg, var(--bg-color) 0%, var(--bg-secondary) 100%);
            margin: 0;
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
        }

        .checkout-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
        }

        .checkout-form, .order-summary {
            background: var(--bg-color);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 1.5rem;
            color: var(--text-color);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--bg-color);
            color: var(--text-color);
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--header-accent-rgb), 0.1);
        }

        .checkout-button {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: #FFFFFF;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 500;
            width: 100%;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 20px;
        }

        .checkout-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(var(--header-accent-rgb), 0.3);
        }

        .error-message {
            background-color: var(--warning-bg);
            border: 1px solid var(--warning-border);
            color: var(--warning-text);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .cart-items {
            margin-bottom: 25px;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 15px;
        }

        .item-details {
            flex-grow: 1;
        }

        .item-name {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 5px;
        }

        .item-price {
            color: var(--primary-color);
            font-weight: 600;
        }

        .item-quantity {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .order-summary-details {
            margin-top: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-row span {
            color: var(--summary-text);
        }

        .summary-row span.secondary-text {
            color: var(--summary-text-secondary);
        }

        .summary-row:last-child {
            border-bottom: none;
            padding-top: 20px;
            margin-top: 10px;
            border-top: 2px solid var(--border-color);
        }

        .summary-row.total {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--summary-text);
        }

        .summary-row.total span {
            color: var(--summary-text);
        }

        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .secure-badge svg {
            width: 20px;
            height: 20px;
            color: var(--primary-color);
        }

        select option {
            background: var(--bg-color);
            color: var(--text-color);
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="checkout-form">
            <h2 class="section-title">Shipping Information</h2>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
            <label for="email">Email</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($user_email); ?>" disabled>
                </div>

                <div class="form-group">
            <label for="phone">Phone</label>
                    <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($user_phone); ?>" required>
                </div>

                <div class="form-group">
            <label for="address">Address</label>
            <input type="text" name="address" id="address" required>
                </div>

                <div class="form-group">
            <label for="wilaya">Wilaya</label>
            <select name="wilaya" id="wilaya" required>
                <option value="">Select Wilaya</option>
                <?php
                        $stmt = $pdo->query("SELECT id, name FROM wilayas ORDER BY name");
                while ($wilaya = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo '<option value="' . htmlspecialchars($wilaya['id']) . '">' . 
                                 htmlspecialchars($wilaya['name']) . '</option>';
                }
                ?>
            </select>
                </div>

                <div class="form-group">
            <label for="delivery_type">Delivery Type</label>
            <select name="delivery_type" id="delivery_type" required>
                        <option value="Standard">Standard Delivery</option>
                        <option value="Express">Express Delivery</option>
            </select>
                </div>

            <button type="submit" class="checkout-button">
                    Complete Order
                </button>

                <div class="secure-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                    Secure Checkout
                </div>
        </form>
        </div>

        <div class="order-summary">
            <h2 class="section-title">Order Summary</h2>
            
            <div class="cart-items">
                <?php
                $total = 0;
                if (!empty($_SESSION['cart'])) {
                    $product_stmt = $pdo->prepare("
                        SELECT p.id, p.name, p.price, pi.image_url 
                        FROM products p 
                        LEFT JOIN product_images pi ON p.id = pi.product_id 
                        WHERE p.id = ? AND (pi.is_primary = 1 OR pi.is_primary IS NULL)
                        LIMIT 1
                    ");
                    
                    foreach ($_SESSION['cart'] as $product_id => $quantity) {
                        $product_stmt->execute([$product_id]);
                        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($product) {
                            $subtotal = $product['price'] * $quantity;
                            $total += $subtotal;
                            ?>
                            <div class="cart-item">
                                <img src="uploads/products/<?php echo htmlspecialchars($product['image_url'] ?? 'assets/images/no-image.png'); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="item-image">
                                <div class="item-details">
                                    <div class="item-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="item-quantity">Quantity: <?php echo $quantity; ?></div>
                                    <div class="item-price"><?php echo number_format($product['price'], 2); ?> DZD</div>
                                </div>
                            </div>
                            <?php
                        }
                    }
                }
                ?>
            </div>

            <div class="order-summary-details">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span><?php echo number_format($total, 2); ?> DZD</span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span class="secondary-text">Calculated at next step</span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span><?php echo number_format($total, 2); ?> DZD</span>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Form submission handling
        document.querySelector('form').addEventListener('submit', function(e) {
            const button = this.querySelector('.checkout-button');
            button.textContent = 'Processing...';
            button.disabled = true;
        });
    </script>
</body>
</html>
