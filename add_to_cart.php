<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Initialize cart from cookie if session cart is empty
if (!isset($_SESSION['cart']) && isset($_COOKIE['cart'])) {
    $_SESSION['cart'] = json_decode($_COOKIE['cart'], true) ?? [];
}

include 'db_connect.php'; // Ensure this path is correct

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    $response = ['success' => false, 'message' => 'Invalid request'];

    try {
        if ($quantity > 0) {
            // Fetch product details
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $stock = (int)$product['stock'];

                if ($quantity <= $stock) {
                    // Load existing cart from session
                    if (!isset($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }

                    // Add/update cart in session
                    if (isset($_SESSION['cart'][$product_id])) {
                        $_SESSION['cart'][$product_id] += $quantity;
                    } else {
                        $_SESSION['cart'][$product_id] = $quantity;
                    }
                    
                    // Always save cart to cookies with secure settings
                    $cart_json = json_encode($_SESSION['cart']);
                    setcookie('cart', $cart_json, [
                        'expires' => time() + (30 * 24 * 60 * 60), // 30 days expiration
                        'path' => '/',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);

                    $response = [
                        'success' => true,
                        'message' => 'Product added to cart successfully',
                        'cart_count' => count($_SESSION['cart'])
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Not enough stock available'
                    ];
                }
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Product not found'
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'Invalid quantity'
            ];
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    } finally {
        // Check if it's an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            // For regular form submissions, set a flash message and redirect
            $_SESSION['cart_message'] = $response['message'];
            $_SESSION['cart_status'] = $response['success'];
            
            if ($response['success']) {
                header("Location: cart.php");
            } else {
                $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
                header("Location: " . $referer . "?error=" . urlencode($response['message']));
            }
        }
        exit();
    }
}
exit();
?>
