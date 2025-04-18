<?php
session_start();

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Include database connection
include 'db_connect.php';

// Include header before any HTML output
include 'header.php';

// Handle AJAX requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid request'];

    // Handle update cart quantities
    if (isset($_POST['update_cart']) && isset($_POST['quantity'])) {
        foreach ($_POST['quantity'] as $product_id => $quantity) {
            if (is_numeric($product_id) && is_numeric($quantity)) {
                // Fetch product stock
                $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    $quantity = max(0, min((int)$quantity, (int)$product['stock']));
                    if ($quantity > 0) {
                        $_SESSION['cart'][$product_id] = $quantity;
                    } else {
                        unset($_SESSION['cart'][$product_id]);
                    }
                }
            }
        }
        
        // Update cookie
        if (isset($_COOKIE['cookies_accepted']) && $_COOKIE['cookies_accepted'] === "true") {
            setcookie('cart', json_encode($_SESSION['cart']), [
                'expires' => time() + (30 * 24 * 60 * 60),
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
        
        $response = ['success' => true, 'message' => 'Cart updated successfully'];
    }
    
    // Handle remove item
    if (isset($_POST['remove_item'])) {
        $product_id = $_POST['remove_item'];
        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
            
            // Update cookie
            if (isset($_COOKIE['cookies_accepted']) && $_COOKIE['cookies_accepted'] === "true") {
                setcookie('cart', json_encode($_SESSION['cart']), [
                    'expires' => time() + (30 * 24 * 60 * 60),
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
            }
            
            $response = ['success' => true, 'message' => 'Item removed successfully'];
        }
    }

    echo json_encode($response);
    exit();
}

// Fetch cart items for display
$cart_items = [];
$total_price = 0;

foreach ($_SESSION['cart'] as $product_id => $quantity) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        // Fetch primary image
        $image_stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1");
        $image_stmt->execute([$product_id]);
        $image = $image_stmt->fetch(PDO::FETCH_ASSOC);

        $product['image_url'] = $image ? "uploads/products/" . htmlspecialchars($image['image_url']) : 'path/to/default-image.jpg';
        $product['quantity'] = $quantity;
        $product['total'] = $product['price'] * $quantity;
        $total_price += $product['total'];
        $cart_items[] = $product;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Ecotech</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
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
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1;
            padding: 20px;
            max-width: 1200px;
            margin: 40px auto;
            width: 100%;
            box-sizing: border-box;
        }

        h1 {
            color: var(--text-color);
            font-size: 2.5rem;
            margin-bottom: 30px;
            font-weight: 600;
            text-align: center;
            position: relative;
            padding-bottom: 15px;
        }

        h1:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            border-radius: 3px;
        }

        .cart-container {
            background: var(--bg-color);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 25px;
            border: 1px solid var(--border-color);
            background: var(--bg-color);
            border-radius: 15px;
            margin-bottom: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .cart-item img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 12px;
            margin-right: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .cart-item img:hover {
            transform: scale(1.05);
        }

        .cart-item .info {
            flex: 1;
            padding-right: 20px;
        }

        .cart-item h2 {
            color: var(--text-color);
            font-size: 1.3rem;
            margin: 0 0 12px 0;
            font-weight: 600;
        }

        .cart-item p {
            color: var(--text-secondary);
            margin: 8px 0;
            font-size: 1.1rem;
        }

        .quantity-input {
            width: 100px;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            background: var(--bg-color);
            color: var(--text-color);
            text-align: center;
            transition: all 0.3s ease;
            -moz-appearance: textfield;
        }

        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .quantity-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--header-accent-rgb), 0.1);
            transform: scale(1.02);
        }

        .remove-item {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 12px;
            border-radius: 50%;
            transition: all 0.3s ease;
            position: relative;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .remove-item:hover {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
            transform: scale(1.1);
        }

        .remove-item i {
            font-size: 1.2rem;
        }

        .total {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--summary-text);
            text-align: right;
            padding: 25px;
            border-top: 2px solid var(--border-color);
            margin-top: 25px;
            background: linear-gradient(to right, transparent, var(--bg-secondary));
            border-radius: 0 0 15px 15px;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 20px;
            margin-top: 30px;
            padding: 0 10px;
        }

        .checkout-button {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: #FFFFFF;
            border: none;
            padding: 18px 40px;
            border-radius: 12px;
            font-size: 1.2rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .checkout-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(var(--header-accent-rgb), 0.3);
        }

        .checkout-button:active {
            transform: translateY(1px);
        }

        .empty-cart-message {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            font-size: 1.4rem;
            background: var(--bg-secondary);
            border-radius: 15px;
            margin: 20px 0;
            animation: fadeIn 0.5s ease;
        }

        .alert {
            background-color: var(--warning-bg);
            border: 1px solid var(--warning-border);
            color: var(--warning-text);
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 30px;
            border-radius: 12px;
            color: #FFFFFF;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease forwards;
            font-weight: 500;
        }

        .toast.success {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        }

        .toast.error {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            main {
                padding: 15px;
                margin: 15px auto;
            }

            h1 {
                font-size: 1.8rem;
                margin-bottom: 20px;
            }

            h1:after {
                width: 80px;
            }

            .cart-container {
                padding: 15px;
                border-radius: 15px;
            }

            .cart-item {
                flex-direction: column;
                text-align: center;
                padding: 20px 15px;
                margin-bottom: 15px;
                position: relative;
            }

            .cart-item img {
                margin: 0 auto 15px;
                width: 140px;
                height: 140px;
            }

            .cart-item .info {
                padding: 0;
                margin-bottom: 20px;
    width: 100%;
            }

            .cart-item h2 {
                font-size: 1.2rem;
                margin-bottom: 10px;
                padding: 0 30px;
            }

            .quantity-input {
                width: 120px;
                margin: 10px auto;
                padding: 10px;
                font-size: 1.1rem;
            }

            .remove-item {
    position: absolute;
                top: 10px;
                right: 10px;
                width: 35px;
                height: 35px;
                padding: 8px;
                background: rgba(var(--header-accent-rgb), 0.05);
            }

            .total {
                text-align: center;
    font-size: 1.4rem;
                padding: 20px 15px;
                margin-top: 20px;
                border-radius: 12px;
            }

            .actions {
                flex-direction: column;
                padding: 0;
                margin-top: 20px;
            }

            .checkout-button {
                width: 100%;
                padding: 16px 20px;
                font-size: 1.1rem;
                margin-top: 0;
            }

            .alert {
                padding: 15px;
                margin: 10px 0;
                font-size: 0.95rem;
            }

            .empty-cart-message {
                padding: 40px 15px;
                font-size: 1.2rem;
            }

            .toast {
                left: 15px;
                right: 15px;
                width: auto;
                text-align: center;
                font-size: 0.95rem;
            }

            /* Add sticky total bar for mobile */
            .mobile-total-bar {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: var(--bg-color);
                padding: 15px;
                box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
                z-index: 100;
    display: flex;
    flex-direction: column;
                gap: 10px;
                border-top: 1px solid var(--border-color);
            }

            .mobile-total-bar .total-amount {
                font-size: 1.2rem;
                font-weight: 600;
                color: var(--text-color);
                text-align: center;
            }

            .mobile-total-bar .checkout-button {
                margin: 0;
                border-radius: 8px;
            }

            /* Add padding to main content to account for sticky bar */
    main {
                padding-bottom: 120px;
            }

            .actions.mobile-hidden {
                display: none;
    }
}
    </style>
</head>
<body>
    <main>
        <h1>Shopping Cart</h1>
        <?php if (!isset($_COOKIE['cookies_accepted']) || $_COOKIE['cookies_accepted'] != "true"): ?>
            <div class="alert">Cookies are required to load the cart!</div>
        <?php endif; ?>

        <div class="cart-container">
        <form id="cart-form" action="cart.php" method="POST">
        <?php if (!empty($cart_items)) : ?>
    <?php foreach ($cart_items as $item) : ?>
        <div class="cart-item">
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>">
            <div class="info">
                <h2><?php echo htmlspecialchars($item['name']); ?></h2>
                                <p>Price: <?php echo number_format($item['price'], 2); ?> DZD</p>
                <p>
                    Quantity:
                                    <input type="number" 
                                           name="quantity[<?php echo htmlspecialchars($item['id']); ?>]" 
                           value="<?php echo htmlspecialchars($item['quantity']); ?>" 
                                           min="1" 
                                           max="99"
                                           class="quantity-input">
                                </p>
                                <p style="color: var(--primary-color); font-weight: 600; margin-top: 10px;">
                                    Subtotal: <?php echo number_format($item['price'] * $item['quantity'], 2); ?> DZD
                </p>
            </div>
                            <button type="button" 
                                    class="remove-item" 
                                    data-id="<?php echo htmlspecialchars($item['id']); ?>"
                                    title="Remove item">
                                <i class="fas fa-trash"></i>
                </button>
        </div>
    <?php endforeach; ?>
                    <div class="total">Total Price: <?php echo number_format($total_price, 2); ?> DZD</div>
    <div class="actions">
        <a href="checkout.php" class="checkout-button">Proceed to Checkout</a>
    </div>
<?php else : ?>
                    <div class="empty-cart-message">Your cart is empty.</div>
<?php endif; ?>
        </form>
        </div>
    </main>

    <!-- Add mobile total bar -->
    <?php if (!empty($cart_items)): ?>
        <div class="mobile-total-bar" style="display: none;">
            <div class="total-amount">Total: <?php echo number_format($total_price, 2); ?> DZD</div>
            <a href="checkout.php" class="checkout-button">Proceed to Checkout</a>
        </div>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cartForm = document.getElementById('cart-form');
            const quantityInputs = document.querySelectorAll('.quantity-input');
            const removeButtons = document.querySelectorAll('.remove-item');
            const desktopActions = document.querySelector('.actions');

            function showToast(message, isSuccess = true) {
                const toast = document.createElement('div');
                toast.className = `toast ${isSuccess ? 'success' : 'error'}`;
                toast.textContent = message;
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }

            quantityInputs.forEach(input => {
                let timeout;
                input.addEventListener('change', function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        const formData = new FormData();
                        formData.append('update_cart', '1');
                        formData.append(`quantity[${this.name.match(/\d+/)[0]}]`, this.value);

                        fetch('cart.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast(data.message);
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                showToast(data.message, false);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showToast('An error occurred while updating the cart', false);
                        });
                    }, 500);
                });
            });

            removeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.dataset.id;
                        const formData = new FormData();
                        formData.append('remove_item', productId);

                        fetch('cart.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast(data.message);
                                const item = this.closest('.cart-item');
                                item.style.animation = 'fadeOut 0.3s ease forwards';
                                setTimeout(() => {
                                    location.reload();
                                }, 300);
                            } else {
                                showToast(data.message, false);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showToast('An error occurred while removing the item', false);
                        });
                });
            });

            // Show/hide mobile total bar based on screen size
            function toggleMobileTotalBar() {
                const mobileBar = document.querySelector('.mobile-total-bar');
                if (mobileBar) {
                    if (window.innerWidth <= 768) {
                        mobileBar.style.display = 'flex';
                        if (desktopActions) {
                            desktopActions.classList.add('mobile-hidden');
                        }
                    } else {
                        mobileBar.style.display = 'none';
                        if (desktopActions) {
                            desktopActions.classList.remove('mobile-hidden');
                        }
                    }
                }
            }

            // Initial check
            toggleMobileTotalBar();

            // Listen for window resize
            window.addEventListener('resize', toggleMobileTotalBar);
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>