<?php
session_start();
include 'db_connect.php'; // Ensure this path is correct

// Fetch product details
$product_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);


// Fetch random products excluding the current product
$stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT pi.image_url 
            FROM product_images pi 
            WHERE pi.product_id = p.id 
            LIMIT 1) as primary_image
    FROM products p 
    WHERE p.id != ? 
    ORDER BY RAND() 
    LIMIT 4
");
$stmt->execute([$product_id]);
$random_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch comments with user email
$stmt = $pdo->prepare("SELECT comments.id, comments.comment, comments.created_at, users.email AS email 
                       FROM comments 
                       INNER JOIN users ON comments.user_id = users.id 
                       WHERE comments.product_id = ? 
                       ORDER BY comments.created_at DESC");

$stmt->execute([$product_id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX request to add to cart
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_to_cart') {
    $quantity = (int)$_POST['quantity'];
    $response = ['status' => 'error', 'message' => 'Invalid quantity!'];

    if ($quantity > 0 && $quantity <= $product['stock']) {
        // Add to cart
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        $_SESSION['cart'][$product_id] = $quantity;

        $response = ['status' => 'success', 'message' => 'Product added to cart successfully!'];
    }

    echo json_encode($response);
    exit();
}

// Handle comment submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comment'])) {
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        if (isset($_SESSION['email'])) {
            $user_email = $_SESSION['email'];
            $comment = htmlspecialchars(trim($_POST['comment']));

            if (!empty($comment)) {
                // Fetch the user's ID based on their email
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$user_email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $stmt = $pdo->prepare("INSERT INTO comments (product_id, user_id, comment) VALUES (?, ?, ?)");
                    $stmt->execute([$product_id, $user['id'], $comment]);

                    header("Location: product.php?id=" . $product_id);
                    exit();
                } else {
                    echo "User not found.";
                }
            } else {
                echo "Comment cannot be empty.";
            }
        } else {
            echo "Session email not set.";
        }
    } else {
        echo "User not logged in.";
    }
}

// Update the delete comment handler
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_comment') {
    // Check if user is logged in and get their admin status from database
    if (isset($_SESSION['email'])) {
        $stmt = $pdo->prepare("SELECT 1 FROM admins WHERE email = ?");
        $stmt->execute([$_SESSION['email']]);
        $isAdmin = $stmt->fetchColumn();
        
        if ($isAdmin) {
            $comment_id = $_POST['comment_id'];
            
            try {
                $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
                $result = $stmt->execute([$comment_id]);
                
                if ($result) {
                    $_SESSION['message'] = "Comment deleted successfully.";
                } else {
                    $_SESSION['error'] = "Failed to delete comment.";
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error deleting comment.";
            }
        } else {
            $_SESSION['error'] = "You don't have permission to delete comments.";
        }
        
        // Redirect back to the same page
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
}


// Fetch product images
$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ?");
$stmt->execute([$product_id]);
$product_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch related products (optional)
$category_id = $product['category_id'];
$stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? LIMIT 4");
$stmt->execute([$category_id, $product_id]);
$related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Lokpix</title>
    <!-- <link rel="stylesheet" href="producs.css"> -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
     <style>
        /* Core Page Styling */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fafafa;
            color: #1a1a1a;
        }

        main {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Product Container */
        .product-page {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            background: white;
            padding: 3rem;
            border-radius: 16px;
            margin-bottom: 3rem;
            border: 1px solid #f0f0f0;
        }

        /* Product Images Section */
        .product-images {
            width: 100%;
        }

        .slider {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            background: #f8f9fa;
            margin-bottom: 1rem;
        }

        /* Smaller Product Image Slider Controls */
        .slider .prev,
        .slider .next {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: #333;
            transition: all 0.2s ease;
            z-index: 2;
            opacity: 0.8;
        }

        .slider .prev {
            left: 0.75rem;
        }

        .slider .next {
            right: 0.75rem;
        }

        .slider .prev:hover,
        .slider .next:hover {
            background: #007bff;
            color: white;
            opacity: 1;
        }

        .main-image {
            width: 100%;
            position: relative;
            padding-top: 100%; /* 1:1 Aspect Ratio */
        }

        .main-image img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 1rem;
        }

        /* Enhanced Thumbnail Grid Styling */
        .thumbnail-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.75rem;
            margin-top: 1rem;
            padding: 0 0.5rem;
        }

        .thumbnail {
            aspect-ratio: 1;
            width: 100%;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s ease;
            background: #f8f9fa;
        }

        .thumbnail:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }

        .thumbnail.active {
            border-color: #007bff;
        }

        /* Product Info Section */
        .product-info {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .product-info h1 {
            font-size: 2rem;
            font-weight: 600;
            margin: 0;
            color: #1a1a1a;
            line-height: 1.3;
        }

        .price {
            font-size: 1.8rem;
            font-weight: 600;
            color: #007bff;
            margin: 0;
        }

        /* Quantity Selector */
        .quantity-selector {
            display: flex;
            align-items: center;
            background: #f0f2f5;
            border-radius: 8px;
            padding: 0.5rem;
            width: fit-content;
            gap: 0.75rem;
        }

        .quantity-selector button {
            width: 28px;
            height: 28px;
            border: none;
            background: white;
            border-radius: 6px;
            font-size: 1rem;
            color: #333;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .quantity-selector button:hover {
            background: #007bff;
            color: white;
        }

        .quantity-selector input {
            width: 40px;
            text-align: center;
            font-size: 1rem;
            font-weight: 500;
            border: none;
            background: transparent;
            color: #333;
            -moz-appearance: textfield;
        }

        /* Button Group */
        .button-group {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
        }

        .add-to-cart-btn {
            flex: 1;
            background: #007bff;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .add-to-cart-btn:hover {
            background: #0056b3;
        }

        .wishlist-heart {
            background: white;
            color: #ff4757;
            border: 2px solid #ff4757;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .wishlist-heart:hover {
            background: #ff4757;
            color: white;
        }

        /* Comments Section */
        .comments-section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            margin-top: 3rem;
            border: 1px solid #f0f0f0;
        }

        .comments-section h2 {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        .comment {
            padding: 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
        }

        .comment:last-child {
            border-bottom: none;
        }

        .username {
            font-weight: 500;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
        }

        .comment-date {
            font-size: 0.875rem;
            color: #666;
        }

        .comment-text {
            margin: 0;
            line-height: 1.5;
        }

        /* Comment Form */
        #comment-form {
            margin-top: 2rem;
        }

        #comment-form textarea {
            width: 100%;
            padding: 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        .submit-comment-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            margin-top: 1rem;
            transition: background-color 0.2s ease;
        }

        .submit-comment-btn:hover {
            background: #0056b3;
        }

        /* Related Products */
        .random-products-section {
            margin-top: 3rem;
        }

        .random-products-section h2 {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        .random-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
        }

        .random-product {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            border: 1px solid #f0f0f0;
            transition: border-color 0.2s ease;
        }

        .random-product:hover {
            border-color: #007bff;
        }

        .random-product img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        .random-product-info {
            padding: 1.25rem;
        }

        .random-product-name {
            font-size: 1rem;
            font-weight: 500;
            margin: 0 0 0.5rem 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }

        .random-product-price {
            font-size: 1.125rem;
            font-weight: 600;
            color: #007bff;
            margin: 0;
        }

        /* Responsive Design */
        @media (max-width: 968px) {
            .product-page {
                grid-template-columns: 1fr;
                gap: 2rem;
                padding: 1.5rem;
            }

            .main-image {
                padding-top: 75%; /* 4:3 Aspect Ratio for tablets */
            }

            .product-info h1 {
                font-size: 1.75rem;
            }

            .price {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            main {
                padding: 1rem;
            }

            .random-products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .slider {
                margin: 0 -1rem;
                border-radius: 0;
            }

            .main-image {
                padding-top: 100%; /* Back to 1:1 for mobile */
            }

            .main-image img {
                padding: 0.5rem;
            }

            .thumbnail-grid {
                grid-template-columns: repeat(5, 1fr);
                padding: 0 1rem;
                margin-top: 0.75rem;
            }

            .slider .prev,
            .slider .next {
                width: 36px;
                height: 36px;
                background: rgba(255, 255, 255, 0.9);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .slider .prev {
                left: 0.5rem;
            }

            .slider .next {
                right: 0.5rem;
            }

            .comments-section {
                padding: 1.5rem;
            }

            .comment {
                padding: 1.25rem;
            }

            .delete-comment-btn {
                width: 28px;
                height: 28px;
                right: 15px;
                top: 12px;
            }

            .modal {
                width: 95%;
                padding: 1.5rem;
            }

            .button-group {
                flex-direction: column;
                gap: 0.75rem;
            }

            .add-to-cart-btn,
            .wishlist-heart {
                width: 100%;
                height: 48px;
            }

            .wishlist-heart {
                border-radius: 8px;
            }

            .quantity-selector {
                width: 100%;
                justify-content: center;
                margin-bottom: 1rem;
            }

            .product-info {
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .product-page {
                padding: 1rem;
                border-radius: 12px;
            }

            .random-products-grid {
                grid-template-columns: 1fr;
            }

            .random-products-section h2,
            .comments-section h2 {
                font-size: 1.25rem;
                margin-bottom: 1.5rem;
            }

            .thumbnail-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 0.5rem;
            }

            .main-image {
                padding-top: 100%; /* Maintain 1:1 ratio */
            }

            .product-info h1 {
                font-size: 1.5rem;
            }

            .price {
                font-size: 1.25rem;
            }

            .product-images h3 {
                font-size: 1rem;
            }

            .slider .prev,
            .slider .next {
                width: 32px;
                height: 32px;
            }

            .comment {
                padding: 1rem;
            }

            .username {
                font-size: 0.9rem;
            }

            .comment-date {
                font-size: 0.8rem;
            }

            .comment-text {
                font-size: 0.9rem;
            }

            #comment-form textarea {
                min-height: 80px;
                padding: 0.75rem;
            }

            .submit-comment-btn {
                width: 100%;
                padding: 0.75rem;
            }

            .modal-buttons {
                flex-direction: column;
            }

            .modal-btn {
                width: 100%;
            }

            .modal h3 {
                font-size: 1.25rem;
            }

            .modal p {
                font-size: 0.9rem;
            }
        }

        /* Add smooth transitions for better mobile experience */
        .product-page,
        .main-image,
        .thumbnail,
        .button-group,
        .comment,
        .modal,
        .random-product {
            transition: all 0.3s ease;
        }

        /* Improve touch targets for mobile */
        .thumbnail,
        .slider .prev,
        .slider .next,
        .delete-comment-btn,
        .add-to-cart-btn,
        .wishlist-heart,
        .modal-btn {
            min-height: 44px; /* Minimum touch target size */
        }

        /* Add pull-to-refresh smooth scroll behavior */
        html {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        .delete-comment-btn {
            background: #ff4757;
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(255, 71, 87, 0.2);
            position: absolute;
            right: 20px;
            top: 15px;
        }

        .delete-comment-btn:hover {
            background: #ff6b81;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(255, 71, 87, 0.3);
        }

        .delete-comment-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(255, 71, 87, 0.2);
        }

        .popup-notification {
            position: fixed;
            top: 20px;
            right: -300px; /* Start off-screen */
            background: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            transition: right 0.3s ease-in-out;
        }

        .popup-notification.slide-in {
            right: 20px;
        }

        .popup-notification.slide-out {
            right: -300px;
        }

        .popup-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .popup-content i {
            font-size: 20px;
            color: #28a745;
        }

        .popup-message {
            margin: 0;
            color: #333;
            font-size: 14px;
        }

        /* Add this CSS for the modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            backdrop-filter: blur(4px);
        }

        .modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            text-align: center;
            max-width: 400px;
            width: 90%;
            z-index: 1001;
        }

        .modal h3 {
            margin: 0 0 1rem 0;
            color: #333;
        }

        .modal p {
            margin: 0 0 1.5rem 0;
            color: #666;
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .modal-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .modal-btn.cancel {
            background: #f1f1f1;
            color: #333;
        }

        .modal-btn.delete {
            background: #ff4757;
            color: white;
        }

        .modal-btn:hover {
            transform: translateY(-1px);
        }

        /* Add this at the beginning of your CSS */
        * {
            text-decoration: none !important;
        }

        a {
            text-decoration: none !important;
            color: inherit;
        }

        .random-product {
            text-decoration: none !important;
            color: inherit;
        }

        .random-product:hover {
            text-decoration: none !important;
        }

        .random-product-name {
            text-decoration: none !important;
        }

        .submit-comment-btn {
            text-decoration: none !important;
        }

        .add-to-cart-btn {
            text-decoration: none !important;
        }

        .modal-btn {
            text-decoration: none !important;
        }

        .username {
            text-decoration: none !important;
        }

        /* Ensure links in comments don't have decoration */
        .comment a {
            text-decoration: none !important;
        }

        /* Ensure header links don't have decoration */
        header a {
            text-decoration: none !important;
        }

        /* Ensure footer links don't have decoration */
        footer a {
            text-decoration: none !important;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <main>
        <div class="product-page">
        <div class="product-images">
    <h3>Product Images</h3>
    <div class="slider">
        <div class="main-image">
            <img id="primary-image" src="uploads/products/<?php echo htmlspecialchars($product_images[0]['image_url']); ?>" alt="Primary Product Image">
        </div>
        <button class="prev" onclick="changeImage(-1)">&#10094;</button>
        <button class="next" onclick="changeImage(1)">&#10095;</button>
    </div>
    <div class="thumbnail-grid">
        <?php if (!empty($product_images)): ?>
            <?php foreach ($product_images as $index => $image): ?>
                <img class="thumbnail" 
                     src="uploads/products/<?php echo htmlspecialchars($image['image_url']); ?>" 
                     alt="Product Image" 
                     onclick="setPrimaryImage(this, <?php echo $index; ?>)"
                     loading="lazy">
            <?php endforeach; ?>
        <?php else: ?>
            <p>No images available for this product.</p>
        <?php endif; ?>
    </div>
</div>
            
            <div class="product-info">
                <div class="product-header">
                    <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                   
                </div>
                <p class="price"><?php echo htmlspecialchars($product['price']); ?>   DZD  </p>
                <p><?php echo htmlspecialchars($product['description']); ?></p>

                <div class="order-wishlist-container">
                    <?php if ($product['stock'] == 0): ?>
                        <p class="out-of-stock">Currently Out of Stock</p>
                    <?php else: ?>
                        <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                            <form id="add-to-cart-form">
                                <div class="quantity-selector">
                                    <button type="button" id="decrease-qty">-</button>
                                    <input type="text" id="quantity" name="quantity" value="1">
                                    <button type="button" id="increase-qty">+</button>
                                </div>
                                <div class="button-group">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <button type="submit" class="add-to-cart-btn">Add to Cart</button>
                                    <?php 
                                    // Check if product is in user's wishlist
                                    $stmt = $pdo->prepare("SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?");
                                    $stmt->execute([$_SESSION['user_id'], $product_id]);
                                    $inWishlist = $stmt->fetchColumn();
                                    ?>
                                    <button type="button" 
                                            class="wishlist-heart <?php echo $inWishlist ? 'active' : ''; ?>" 
                                            data-product-id="<?php echo $product_id; ?>"
                                            title="<?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="button-group">
                                <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                                   class="add-to-cart-btn" style="display: inline-block; text-decoration: none; text-align: center;">
                                    Login to Add to Cart
                                </a>
                                <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                                   class="wishlist-heart"
                                   title="Login to add to wishlist">
                                    <i class="fas fa-heart"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Comments Section -->
        <div class="comments-section">
            <h2>Comments</h2>
            
            <?php 
            // Check if user is logged in and is admin
            $isAdmin = false;
            if (isset($_SESSION['email'])) {
                $stmt = $pdo->prepare("SELECT 1 FROM admins WHERE email = ?");
                $stmt->execute([$_SESSION['email']]);
                $isAdmin = $stmt->fetchColumn();
            }
            ?>

            <?php if (count($comments) > 0): ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment">
                        <p class="username">
                            <?php echo htmlspecialchars($comment['email']); ?>
                            <span class="comment-date">
                                <?php echo date('M d, Y', strtotime($comment['created_at'])); ?>
                            </span>
                            <?php if ($isAdmin): ?>
                                <button type="button" class="delete-comment-btn" 
                                        onclick="showDeleteModal(<?php echo $comment['id']; ?>)"
                                        title="Delete comment">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            <?php endif; ?>
                        </p>
                        <p class="comment-text">
                            <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No comments yet. Be the first to comment!</p>
            <?php endif; ?>

            <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                <form id="comment-form" action="product.php?id=<?php echo $product_id; ?>" method="POST">
                    <textarea 
                        name="comment" 
                        id="comment" 
                        placeholder="Share your thoughts about this product..."
                        required
                    ></textarea>
                    <button type="submit" class="submit-comment-btn">Post Comment</button>
                </form>
            <?php else: ?>
                <p>
                    <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                       class="submit-comment-btn">
                        Login to leave a comment
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <!-- Random Products Section -->
        <div class="random-products-section">
            <h2>You Might Also Like</h2>
            <div class="random-products-grid">
                <?php foreach ($random_products as $random_product): ?>
                    <a href="product.php?id=<?php echo $random_product['id']; ?>" class="random-product">
                        <?php if (!empty($random_product['primary_image'])): ?>
                            <img src="uploads/products/<?php echo htmlspecialchars($random_product['primary_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($random_product['name']); ?>">
                        <?php else: ?>
                            <img src="default-image.jpg" 
                                 alt="<?php echo htmlspecialchars($random_product['name']); ?>">
                        <?php endif; ?>
                        <div class="random-product-info">
                            <p class="random-product-name">
                                <?php echo htmlspecialchars($random_product['name']); ?>
                            </p>
                            <p class="random-product-price"><?php echo htmlspecialchars($random_product['price']); ?>   DZD
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="toast" id="toast"></div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let quantity = 1;
            const maxStock = <?php echo htmlspecialchars($product['stock']); ?>;

            document.getElementById('increase-qty').addEventListener('click', function() {
                if (quantity < maxStock) {
                    quantity++;
                    document.getElementById('quantity').value = quantity;
                }
            });

            document.getElementById('decrease-qty').addEventListener('click', function() {
                if (quantity > 1) {
                    quantity--;
                    document.getElementById('quantity').value = quantity;
                }
            });

            document.getElementById('add-to-cart-form').addEventListener('submit', function(event) {
                event.preventDefault();

                const formData = new FormData(this);
                formData.append('quantity', document.getElementById('quantity').value);

                fetch('product.php?id=<?php echo $product_id; ?>', {
                    method: 'POST',
                    body: formData,
                })
                .then(response => response.json())
                .then(data => {
                    const popup = document.getElementById('cartPopup');
                    const popupMessage = popup.querySelector('.popup-message');
                    
                    // Set the message
                    popupMessage.textContent = data.message;
                    
                    // Show the popup
                    popup.classList.add('slide-in');
                    
                    // Remove the popup after 3 seconds
                    setTimeout(() => {
                        popup.classList.remove('slide-in');
                        popup.classList.add('slide-out');
                        
                        // Reset classes after animation
                        setTimeout(() => {
                            popup.classList.remove('slide-out');
                        }, 300);
                    }, 3000);
                })
                .catch(() => {
                    const popup = document.getElementById('cartPopup');
                    const popupMessage = popup.querySelector('.popup-message');
                    popupMessage.textContent = 'An error occurred. Please try again.';
                    popup.classList.add('slide-in');
                    
                    setTimeout(() => {
                        popup.classList.remove('slide-in');
                        popup.classList.add('slide-out');
                        setTimeout(() => {
                            popup.classList.remove('slide-out');
                        }, 300);
                    }, 3000);
                });
            });

            // Initialize wishlist hearts
            document.querySelectorAll('.wishlist-heart').forEach(heart => {
                if (heart.classList.contains('active')) {
                    heart.style.background = '#e74c3c';
                    heart.querySelector('i').style.color = 'white';
                }
            });

            // Wishlist functionality
            const toast = document.getElementById('toast');
            
            function showToast(message, success = true) {
                toast.textContent = message;
                toast.style.background = success ? '#2ecc71' : '#e74c3c';
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 3000);
            }

            // Event delegation for wishlist hearts
            document.addEventListener('click', function(e) {
                const wishlistHeart = e.target.closest('.wishlist-heart');
                if (!wishlistHeart || wishlistHeart.tagName.toLowerCase() === 'a') return;

                e.preventDefault();
                const action = wishlistHeart.classList.contains('active') ? 'remove' : 'add';
                const productId = wishlistHeart.dataset.productId;

                // Add animation class
                wishlistHeart.classList.add('animate');
                setTimeout(() => wishlistHeart.classList.remove('animate'), 600);

                // Update the heart immediately
                wishlistHeart.classList.toggle('active');
                wishlistHeart.title = wishlistHeart.classList.contains('active') ? 'Remove from Wishlist' : 'Add to Wishlist';
                wishlistHeart.style.background = wishlistHeart.classList.contains('active') ? '#e74c3c' : 'white';
                wishlistHeart.querySelector('i').style.color = wishlistHeart.classList.contains('active') ? 'white' : '#e74c3c';

                fetch('wishlist.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=${action}&product_id=${productId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message);
                    } else {
                        // Revert the heart state if the action failed
                        wishlistHeart.classList.toggle('active');
                        wishlistHeart.title = wishlistHeart.classList.contains('active') ? 'Remove from Wishlist' : 'Add to Wishlist';
                        wishlistHeart.style.background = wishlistHeart.classList.contains('active') ? '#e74c3c' : 'white';
                        wishlistHeart.querySelector('i').style.color = wishlistHeart.classList.contains('active') ? 'white' : '#e74c3c';
                        showToast(data.message, false);
                    }
                })
    
            });
        });
    </script>

    <script>
        let currentIndex = 0;
        const images = <?php echo json_encode(array_column($product_images, 'image_url')); ?>;

        function setPrimaryImage(thumbnail, index) {
            currentIndex = index;
            const primaryImage = document.getElementById('primary-image');
            primaryImage.style.opacity = '0';
            setTimeout(() => {
                primaryImage.src = thumbnail.src;
                primaryImage.style.opacity = '1';
            }, 200);
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => thumb.classList.remove('active'));
            thumbnail.classList.add('active');
        }

        function changeImage(direction) {
            currentIndex += direction;
            if (currentIndex < 0) currentIndex = images.length - 1;
            if (currentIndex >= images.length) currentIndex = 0;
            
            const primaryImage = document.getElementById('primary-image');
            primaryImage.style.opacity = '0';
            setTimeout(() => {
                primaryImage.src = 'uploads/products/' + images[currentIndex];
                primaryImage.style.opacity = '1';
            }, 200);
            
            // Update active thumbnail
            const thumbnails = document.querySelectorAll('.thumbnail');
            thumbnails.forEach(thumb => thumb.classList.remove('active'));
            thumbnails[currentIndex].classList.add('active');
        }

        // Add touch swipe support
        let touchStartX = 0;
        let touchEndX = 0;

        document.querySelector('.slider').addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        });

        document.querySelector('.slider').addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });

        function handleSwipe() {
            const swipeThreshold = 50;
            if (touchEndX < touchStartX - swipeThreshold) changeImage(1); // Swipe left
            if (touchEndX > touchStartX + swipeThreshold) changeImage(-1); // Swipe right
        }
    </script>
   
   <?php
include 'footer.php';
?>
    
</body>
</html>

<!-- Add this right before the closing </body> tag -->
<div class="popup-notification" id="cartPopup">
    <div class="popup-content">
        <i class="fas fa-check-circle"></i>
        <p class="popup-message"></p>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <h3>Delete Comment</h3>
        <p>Are you sure you want to delete this comment?</p>
        <div class="modal-buttons">
            <button class="modal-btn cancel" onclick="hideDeleteModal()">Cancel</button>
            <form method="POST" style="display: inline;" id="deleteCommentForm">
                <input type="hidden" name="action" value="delete_comment">
                <input type="hidden" name="comment_id" id="deleteCommentId">
                <button type="submit" class="modal-btn delete">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
function showDeleteModal(commentId) {
    document.getElementById('deleteCommentId').value = commentId;
    document.getElementById('deleteModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent scrolling
}

function hideDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
}

// Close modal when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideDeleteModal();
    }
});
</script>