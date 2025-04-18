<?php
// index.php
session_start();
// Include database connection (update path if necessary)
include 'db_connect.php'; // Ensure this file sets up a PDO instance in $pdo
include 'chatbot.php';
// Include header after all redirects
include 'header.php';
// Initialize settings array
$settings = [];

// Fetch site settings
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("SELECT * FROM site_settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Error fetching site settings: " . $e->getMessage());
    }
}

// Parse social links from JSON
$social_links = !empty($settings['social_links']) ? json_decode($settings['social_links'], true) : [
    'facebook' => '',
    'twitter' => '',
    'instagram' => '',
    'linkedin' => ''
];


// Store product ID in session if redirecting to login
if (isset($_GET['add_to_wishlist'])) {
    $_SESSION['pending_wishlist_item'] = $_GET['add_to_wishlist'];
    header('Location: login.php?redirect=' . urlencode('index.php'));
    exit();
}

// Add pending wishlist item after login
if (isset($_SESSION['user_id']) && isset($_SESSION['pending_wishlist_item'])) {
    $product_id = $_SESSION['pending_wishlist_item'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    unset($_SESSION['pending_wishlist_item']);
}


// Fetch features from the database
$stmt = $pdo->prepare("SELECT title, description, photo, is_gold FROM features 
                       ORDER BY CASE 
                           WHEN title = 'Université Badji Mokhtar Annaba' THEN 0 
                           ELSE 1 
                       END, 
                       created_at DESC");
$stmt->execute();
$features = $stmt->fetchAll();

// Fetch products with their first image
$stmt = $pdo->prepare("
    SELECT p.*, 
           (SELECT pi.image_url 
            FROM product_images pi 
            WHERE pi.product_id = p.id 
            LIMIT 1) as primary_image
    FROM products p
    ORDER BY p.created_at DESC
");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get wishlist items for the current user if logged in
$wishlist_items = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT product_id FROM wishlists WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $wishlist_items = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

try {
    $stmt = $pdo->query("SELECT * FROM slider_photos");
    $slider_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: Unable to fetch slider photos. " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            /* Inherit colors from header.php */
            --primary-color: <?= htmlspecialchars($settings['primary_color'] ?? '#0d6efd') ?>;
            --accent-color: <?= htmlspecialchars($settings['accent_color'] ?? '#0b5ed7') ?>;
            --text-color: var(--header-text, #343a40);
            --text-secondary: var(--header-text-secondary, #6c757d);
            --bg-color: var(--header-bg, #FFFFFF);
            --bg-secondary: var(--dropdown-hover-bg, #f8f9fa);
            --border-color: var(--header-border, #dee2e6);
            --card-shadow: 0 15px 30px var(--dropdown-shadow, rgba(0, 0, 0, 0.1));
            --hover-shadow: 0 20px 40px var(--dropdown-shadow, rgba(0, 0, 0, 0.2));
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--bg-color);
        }

        /* Hero Section */
        .hero {
            width: 100%;
            height: 80vh;
            overflow: hidden;
            position: relative;
            background: var(--bg-secondary);
        }

        .slider {
            width: 100%;
            height: 100%;
            position: relative;
           
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .slides {
            display: flex;
            width: 100%;
            height: 100%;
            transition: transform 0.5s ease-in-out;
        }

        .slide {
            min-width: 100%;
            position: relative;
            overflow: hidden;
        }

        .slide::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(
                180deg,
                rgba(0,0,0,0.2) 0%,
                rgba(0,0,0,0.4) 100%
            );
            z-index: 1;
        }

        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.7s ease;
        }

        .slide:hover img {
            transform: scale(1.03);
        }

        .caption {
            position: absolute;
            bottom: 50px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.95);
            color: #1a1a1a;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1em;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            z-index: 2;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            min-width: 200px;
            text-align: center;
        }

        /* Features Section */
        .features {
            padding: 80px 20px;
            background: var(--bg-color);
        }

        .features h2 {
            text-align: center;
            margin-bottom: 50px;
            color: var(--text-color);
            font-size: 2.8em;
            font-weight: 600;
            position: relative;
        }

        .features h2::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .features-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .feature-item {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            height: 100%;
            width: 300px;
            background: var(--bg-color);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border: none;
            opacity: 1;
            transform: translateY(0);
            animation: none;
        }

        .feature-item.gold {
            border: 2px solid #FFD700;
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-color));
            position: relative;
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.15);
            transform-style: preserve-3d;
            transition: all 0.3s ease;
        }

        .feature-item.gold::before {
            content: '★';
            position: absolute;
            top: 15px;
            right: 15px;
            background: #FFD700;
            color: #000;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 0.9em;
            font-weight: 600;
            z-index: 2;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .feature-item.gold h3 {
            color: #B8860B;
            font-weight: 700;
            padding: 20px 15px 10px;
            font-size: 1.4em;
        }

        .feature-item.gold:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(255, 215, 0, 0.2);
        }

        .feature-item.gold p {
            color: #4a4a4a;
            font-weight: 500;
            font-size: 1.1em;
            line-height: 1.6;
            padding: 0 20px 25px;
        }

        .feature-item:hover {
            transform: translateY(-10px) scale(1.05);
            box-shadow: var(--hover-shadow);
        }

        .feature-image {
            height: 300px;
            overflow: hidden;
            position: relative;
            margin-bottom: 10px;
        }

        .feature-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
            border-bottom: 5px solid var(--primary-color);
        }

        .feature-item.gold img {
            border-bottom: 5px solid #FFD700;
        }

        .feature-item:hover img {
            transform: scale(1.1);
        }

        .feature-item h3 {
            padding: 10px;
            margin: 0;
            font-size: 1.2em;
            font-weight: 600;
            color: var(--text-color);
        }

        .feature-item p {
            padding: 0 10px 10px;
            color: var(--text-secondary);
            font-size: 0.9em;
            line-height: 1.4;
        }

        /* Product List */
        .product-list {
            padding: 80px 20px;
            background: var(--bg-secondary);
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 40px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .product-item {
            background: var(--bg-color);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all 0.4s ease;
            opacity: 0;
            transform: translateY(20px);
            animation: slideUp 0.6s ease forwards;
            color: inherit;
            text-decoration: none;
            position: relative;
        }

        .product-item:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--hover-shadow);
        }

        /* Prevent default link styles */
        .product-item a {
            color: inherit;
            text-decoration: none; /* Removes underline from links */
        }

        .product-item a:hover {
            color: var(--primary-color); /* Optional hover color for links */
        }

        .product-image {
            height: 280px;
            overflow: hidden;
            position: relative;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-item:hover .product-image img {
            transform: scale(1.1);
        }

        .product-item h3 {
            padding: 20px 20px 10px;
            margin: 0;
            font-size: 1.3em;
            font-weight: 600;
            color: var(--text-color);
        }

        .product-item p {
            padding: 0 20px 20px;
            color: var(--text-secondary);
            font-size: 1.1em;
        }

        .price {
            color: var(--primary-color);
            font-weight: bold;
        }

        .stock {
            color: var(--text-secondary);
        }

        .in-stock {
            color: #2ecc71;
        }

        .out-of-stock {
            color: #e74c3c;
        }

        .wishlist-heart {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 40px;
            height: 40px;
            background: var(--bg-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            text-decoration: none;
        }

        .wishlist-heart i {
            font-size: 20px;
            color: #e74c3c;
            transition: all 0.3s ease;
        }

        .wishlist-heart:hover {
            transform: scale(1.15);
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .wishlist-heart:hover i {
            transform: scale(1.1);
        }

        .wishlist-heart.active {
            background: #e74c3c;
        }

        .wishlist-heart.active i {
            color: white;
        }

        .wishlist-heart::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(231, 76, 60, 0.1);
            transform: scale(0);
            transition: transform 0.3s ease;
        }

        .wishlist-heart:hover::before {
            transform: scale(1.2);
        }

        @keyframes heartBeat {
            0% { transform: scale(1); }
            25% { transform: scale(1.2); }
            50% { transform: scale(1); }
            75% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .wishlist-heart.animate {
            animation: heartBeat 0.6s ease-in-out;
        }

        /* Enhanced Footer */
        .site-footer {
            background: #2c3e50;
            color: white;
            padding: 50px 20px;
            text-align: center;
        }

        .social-media {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 30px;
        }

        .social-media a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 30px;
            transition: all 0.3s ease;
        }

        .social-media a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-3px);
        }

        .social-media img {
            width: 24px;
            height: 24px;
            filter: brightness(0) invert(1);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero {
                height: 50vh;
            }

            .features h2, .product-list h2 {
                font-size: 2.2em;
            }

            .features-list, .product-grid {
                gap:25 px;
            }

            /* Hide slider buttons on mobile */
            .slider button {
                width: 20px;
                height: 20px;
            }
            
            /* Adjust caption for mobile */
            .caption {
                bottom: 30px;
                padding: 10px 20px;
                font-size: 0.9em;
                min-width: 160px;
            }
        }
        /* For screens smaller than 768px (typical mobile devices) */
@media (max-width: 768px) {
  .product-grid {
    grid-template-columns: repeat(2, 1fr); /* 2 columns for mobile */
    gap: 20px; /* Adjust the gap for smaller screens */
  }

  /* Optional: Adjust product item styles for mobile */
  .product-item {
    opacity: 1; /* Ensure items are visible */
    transform: translateY(0); /* Reset any animations */
  }

  .product-image {
    height: 200px; /* Adjust image height for mobile */
  }

  .product-item h3 {
    font-size: 1.1em; /* Adjust font size for mobile */
  }

  .product-item p {
    font-size: 0.9em; /* Adjust font size for mobile */
  }
}

        @media (max-width: 480px) {
            .hero {
                height: 40vh;
                
            }

            .caption {
                bottom: 20px;
                padding: 8px 16px;
                font-size: 0.8em;
                min-width: 140px;
            }

            .features h2, .product-list h2 {
                font-size: 1.8em;
            }

            .feature-item img {
                height: 200px;
            }

            /* Adjust slider button size for mobile */
            .slider button {
                width: 10px;
                height: 10px;
            }
        }
/* For screens smaller than 480px (very small mobile devices) */
@media (max-width: 480px) {
  .product-grid {
    grid-template-columns: repeat(2, 1fr); /* 2 columns for mobile */
    gap: 20px; /* Adjust the gap for smaller screens */
  }

  /* Optional: Adjust product item styles for mobile */
  .product-item {
    opacity: 1; /* Ensure items are visible */
    transform: translateY(0); /* Reset any animations */
  }

  .product-image {
    height: 200px; /* Adjust image height for mobile */
  }

  .product-item h3 {
    font-size: 1.1em; /* Adjust font size for mobile */
  }

  .product-item p {
    font-size: 0.9em; /* Adjust font size for mobile */
  }
}
        /* Animations */
        @keyframes slideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Smooth transitions */
        * {
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Enhanced Slider Buttons */
        .slider button {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            z-index: 10;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .slider .prev {
            left: 25px;
        }

        .slider .next {
            right: 25px;
        }

        .slider button:hover {
            background: white;
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        }

        .slider button:active {
            transform: translateY(-50%) scale(0.95);
        }

        /* Style Option 1 - Modern Gradient */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(145deg, var(--primary-color), var(--accent-color));
            color: var(--bg-color);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 1000;
            border: none;
        }

        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
        }

        .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            background: linear-gradient(145deg, var(--accent-color), var(--primary-color));
        }

        .back-to-top i {
            font-size: 20px;
            transition: transform 0.3s ease;
        }

        .back-to-top:hover i {
            transform: translateY(-2px);
        }

        /* Optional: Add a pulse animation */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(52, 152, 219, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(52, 152, 219, 0);
            }
        }

        .back-to-top.visible {
            animation: pulse 2s infinite;
        }

        .cookie-banner {
            display: none;
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-color);
            max-width: 400px;
            width: 90%;
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            z-index: 1000;
        }

        .cookie-content {
            text-align: center;
        }

        .cookie-content p {
            margin-bottom: 15px;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .cookie-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .cookie-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .cookie-btn.accept {
            background: var(--primary-color);
            color: var(--bg-color);
        }

        .cookie-btn.decline {
            background: var(--bg-secondary);
            color: var(--text-color);
        }

        .cookie-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--bg-color);
            color: var(--text-color);
            padding: 12px 24px;
            border-radius: 4px;
            box-shadow: var(--card-shadow);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        /* Section Headers */
        .section-header h2 {
            color: var(--text-color);
        }

        .section-header p {
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <section class="hero">
        <div class="slider">
            <div class="slides">
                <?php foreach ($slider_photos as $photo): ?>
                    <div class="slide">
                        <img src="<?php echo htmlspecialchars($photo['photo_url']); ?>" alt="<?php echo htmlspecialchars($photo['caption']); ?>">
                        <?php if (!empty($photo['caption'])): ?>
                            <div class="caption"><?php echo htmlspecialchars($photo['caption']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="prev" aria-label="Previous Slide">&#10094;</button>
            <button class="next" aria-label="Next Slide">&#10095;</button>
        </div>
    </section>

    <section class="features">
        <h2>Featured Items</h2>
        
        <?php if (!empty($features)) : ?>
            <div class="features-list">
                <?php foreach ($features as $feature) : ?>
                    <div class="feature-item <?php echo $feature['is_gold'] ? 'gold' : ''; ?>">
                        <div class="feature-image">
                            <?php if (!empty($feature['photo'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($feature['photo']); ?>" alt="<?php echo htmlspecialchars($feature['title']); ?>">
                            <?php endif; ?>
                        </div>
                        <h3><?php echo htmlspecialchars($feature['title']); ?></h3>
                        <p><?php echo htmlspecialchars($feature['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p>No features available.</p>
        <?php endif; ?>
    </section>

    <section class="product-list">
        <h2>Featured Products</h2>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-item">
                    <div class="product-image">
                        <a href="product.php?id=<?php echo $product['id']; ?>">
                            <img src="<?php echo !empty($product['primary_image']) ? 'uploads/products/' . htmlspecialchars($product['primary_image']) : 'placeholder.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </a>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="wishlist-heart <?php echo in_array($product['id'], $wishlist_items) ? 'active' : ''; ?>" 
                                 data-product-id="<?php echo $product['id']; ?>"
                                 title="<?php echo in_array($product['id'], $wishlist_items) ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                <i class="fas fa-heart"></i>
                            </div>
                        <?php else: ?>
                            <a href="index.php?add_to_wishlist=<?php echo $product['id']; ?>" 
                               class="wishlist-heart"
                               title="Login to add to wishlist">
                                <i class="fas fa-heart"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="price"><?php echo number_format($product['price'], 2); ?> DZD</p>
                        <?php if ($product['stock'] > 0): ?>
                            <p class="stock in-stock">In Stock</p>
                        <?php else: ?>
                            <p class="stock out-of-stock">Out of Stock</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <div id="cookie-banner" class="cookie-banner">
        <div class="cookie-content">
            <p>We use cookies to enhance your experience. By continuing to visit this site you agree to our use of cookies.</p>
            <div class="cookie-buttons">
                <button id="accept-cookies" class="cookie-btn accept">Accept</button>
                <button id="decline-cookies" class="cookie-btn decline">Decline</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const cookieBanner = document.getElementById("cookie-banner");
            
            // Check if user has already made a choice
            if (!localStorage.getItem("cookieChoice")) {
                cookieBanner.style.display = "block";
            }

            document.getElementById("accept-cookies").addEventListener("click", function() {
                // Set cookie with secure flags
                document.cookie = "cookies_accepted=true; path=/; max-age=31536000; secure; samesite=Strict";
                localStorage.setItem("cookieChoice", "accepted");
                cookieBanner.style.display = "none";
            });

            document.getElementById("decline-cookies").addEventListener("click", function() {
                document.cookie = "cookies_accepted=false; path=/; max-age=31536000; secure; samesite=Strict";
                localStorage.setItem("cookieChoice", "declined");
                cookieBanner.style.display = "none";
            });
        });
    </script>

    <?php include 'footer.php'; ?>
    <button class="back-to-top" aria-label="Back to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
        // Slider functionality
        document.addEventListener('DOMContentLoaded', function() {
            const slides = document.querySelector('.slides');
            const slideCount = slides.children.length;
            let index = 0;

            function showSlide() {
                index = (index + 1) % slideCount;
                const offset = -index * 100;
                slides.style.transform = `translateX(${offset}%)`;
            }

            function showPreviousSlide() {
                index = (index - 1 + slideCount) % slideCount;
                const offset = -index * 100;
                slides.style.transform = `translateX(${offset}%)`;
            }

            document.querySelector('.next').addEventListener('click', showSlide);
            document.querySelector('.prev').addEventListener('click', showPreviousSlide);

            setInterval(showSlide, 5000);
        });

        // Add this JavaScript for the Back to Top button
        const backToTopButton = document.querySelector('.back-to-top');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) { // Show button after scrolling 300px
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        });

        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            document.querySelector('.product-grid').addEventListener('click', function(e) {
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
                wishlistHeart.style.background = wishlistHeart.classList.contains('active') ? '#e74c3c' : 'rgba(255, 255, 255, 0.95)';
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
                        wishlistHeart.style.background = wishlistHeart.classList.contains('active') ? '#e74c3c' : 'rgba(255, 255, 255, 0.95)';
                        wishlistHeart.querySelector('i').style.color = wishlistHeart.classList.contains('active') ? 'white' : '#e74c3c';
                        showToast(data.message, false);
                    }
                })
                .catch(error => {
                    showToast('An error occurred. Please try again.', false);
                });
            });
        });
    </script>

    <div class="toast" id="toast"></div>
</body>
</html>