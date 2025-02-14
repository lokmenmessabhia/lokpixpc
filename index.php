<?php
session_start();
include 'db_connect.php';
include 'header.php';

// Fetch features from the database
$stmt = $pdo->prepare("SELECT title, description, photo FROM features ORDER BY created_at DESC");
$stmt->execute();
$features = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM products");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Home - EcoTech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Enhanced Reset and Base Styles */
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
            color: #2c3e50;
            background-color: #f8f9fa;
            color: inherit;
        }

        /* Enhanced Hero Section */
        .hero {
            width: 100%;
            height: 80vh;
            overflow: hidden;
            position: relative;
            
            background: #f8f9fa;
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

        /* Enhanced Features Section */
        .features {
            padding: 80px 20px;
            background: white;
        }

        .features h2 {
            text-align: center;
            margin-bottom: 50px;
            color: #2c3e50;
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
            background: #3498db;
            border-radius: 2px;
        }

        .features-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 40px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .feature-item {
            background: #ffffff;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: none;
            opacity: 1;
            transform: translateY(0);
            animation: none;
        }

        .feature-item:hover {
            transform: translateY(-10px) scale(1.05);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
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
            border-bottom: 5px solid #3498db;
        }

        .feature-item:hover img {
            transform: scale(1.1);
        }

        .feature-item h3 {
            padding: 15px 15px 5px;
            margin: 0;
            font-size: 1.5em;
            font-weight: 700;
            color: #2c3e50;
        }

        .feature-item p {
            padding: 0 15px 20px;
            color: #666;
            font-size: 1.1em;
            line-height: 1.5;
        }

        /* Enhanced Product List */
        .product-list {
    padding: 80px 20px;
    background: #f8f9fa;
    color: inherit;
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
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    transition: all 0.4s ease;
    opacity: 0;
    transform: translateY(20px);
    animation: slideUp 0.6s ease forwards;
    color: inherit;
    text-decoration: none; /* Prevent underline for links inside product items */
}

.product-item:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: var(--shadow-hover);
}

/* Prevent default link styles */
.product-item a {
    color: inherit;
    text-decoration: none; /* Removes underline from links */
}

.product-item a:hover {
    color: #1a73e8; /* Optional hover color for links */
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
}

.product-item p {
    padding: 0 20px 20px;
    color: #666;
    font-size: 1.1em;
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
                gap: 25px;
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
            background: linear-gradient(145deg, #3498db, #2980b9);
            color: white;
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
            background: linear-gradient(145deg, #2980b9, #3498db);
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
            background: white;
            max-width: 400px;
            width: 90%;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }

        .cookie-content {
            text-align: center;
        }

        .cookie-content p {
            margin-bottom: 15px;
            color: #333;
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
            background: #3498db;
            color: white;
        }

        .cookie-btn.decline {
            background: #f1f1f1;
            color: #333;
        }

        .cookie-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
                    <div class="feature-item">
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
            <?php foreach ($products as $product) : 
                $stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = :product_id");
                $stmt->execute(['product_id' => $product['id']]);
                $product_image = $stmt->fetchColumn();
            ?>
                <div class="product-item">
                    <a href="product.php?id=<?php echo htmlspecialchars($product['id']); ?>">
                        <div class="product-image">
                            <?php
                            if ($product_image) {
                                echo '<img src="uploads/products/' . htmlspecialchars($product_image) . '" alt="' . htmlspecialchars($product['name']) . '" class="uploaded-photo">';
                            } else {
                                echo '<p>Image not found in uploads.</p>';
                            }
                            ?>
                        </div>
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p><?php echo htmlspecialchars($product['price']); ?>   DZD</p>

                        <?php if ($product['stock'] == 0): ?>
                            <p style="color: red;">Out of Stock</p>
                        <?php endif; ?>
                    </a>
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

<?php   include'footer.php' ?>
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
</body>
</html>