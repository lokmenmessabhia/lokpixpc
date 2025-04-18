<?php
session_start();
include 'db_connect.php'; // Ensure this path is correct

// Get the category ID from the URL parameter
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0; // Default to 0 if category id is not set

// Debug: Check the retrieved category ID
if ($categoryId === 0) {
    echo "No category ID provided.";
    exit;
}

// Fetch the category name (optional: to display it in the page)
try {
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        echo "Error: Category not found for ID: " . htmlspecialchars($categoryId);
        exit;
    }
} catch (PDOException $e) {
    echo "Error: Unable to fetch category. " . $e->getMessage();
    exit;
}



// Fetch products for the specific category, joining with product_images
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.description, p.price, p.stock, pi.image_url 
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1  -- Only get primary images
        WHERE p.category_id = ?
    ");
    $stmt->execute([$categoryId]); // Use the retrieved category ID
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Check the number of products fetched
   
} catch (PDOException $e) {
    echo "Error: Unable to fetch products. " . $e->getMessage();
    $products = []; // Initialize as empty array in case of error
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EcoTech-<?php echo htmlspecialchars($category['name']); ?> - Products</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"> <!-- Include Poppins font -->
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
        
            color: #333;
        }

        main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .h11 {
            text-align: center;
            margin: 30px 0;
            color: #2c3e50;
            font-size: 2.5em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .products-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
            padding: 20px;
        }

        .product-grid {
            background: white;
            border-radius: 15px;
            padding: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .product-grid:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .product-image {
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            aspect-ratio: 1;
            margin-bottom: 15px;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-grid:hover .product-image img {
            transform: scale(1.1);
        }

        .product-item {
            text-align: center;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .product-item h3 {
            font-size: 1.2em;
            margin: 10px 0;
            color: #2c3e50;
            font-weight: 600;
            line-height: 1.4;
        }

        .product-item p {
            margin: 8px 0;
            color: #666;
            line-height: 1.4;
            font-size: 0.9em;
            /* Limit description to 3 lines */
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-item .price {
            font-size: 1.4em;
            font-weight: 700;
            color: #2c3e50;
            margin: 12px 0;
        }

        .product-item .stock {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
            display: inline-block;
            margin: 8px 0;
        }

        .product-item .stock.low {
            background: #fff3e0;
            color: #e65100;
        }

        .product-item .stock.out {
            background: #ffebee;
            color: #c62828;
        }

        .no-products-message {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            background: #ffebeb;
            color: #dc3545;
            border-radius: 10px;
            border: 1px solid #f5c2c7;
            font-size: 1.1em;
        }

        .product-item a {
            text-decoration: none;
            color: inherit;
        }

        @media (max-width: 768px) {
            .products-list {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
                padding: 10px;
            }

            .h11 {
                font-size: 2em;
                margin: 20px 0;
            }

            .product-item h3 {
                font-size: 1.1em;
            }
            
            .product-item .price {
                font-size: 1.2em;
            }
        }

        .nav-link {
            transition: color 0.3s ease;
        }
        
        .nav-link.active {
            font-weight: 600;
        }
        
        .nav-link:not(.active) {
            color: #95a5a6 !important;
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>
<main><?php
try {
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        echo "<h1 class='error'>Error: Category not found for ID: " . htmlspecialchars($categoryId) . "</h1>";
        exit;
    }
} catch (PDOException $e) {
    echo "<h1 class='error'>Error: Unable to fetch category. " . htmlspecialchars($e->getMessage()) . "</h1>";
    exit;
}

echo "<h1 class='h11' style='font-family: 'Poppins', sans-serif;'>Products in " . htmlspecialchars($category['name']) . "</h1>";?>

    <div class="products-list">
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
                <div class="product-grid">
                    <div class="product-item">
                        <a href="product.php?id=<?php echo $product['id']; ?>" style="text-decoration: none; color: inherit;">
                            <div class="product-image">
                                <?php
                                $photo = isset($product['image_url']) ? $product['image_url'] : 'default-photo.jpg';
                                if (filter_var($photo, FILTER_VALIDATE_URL)) {
                                    echo '<img src="' . htmlspecialchars($photo) . '" alt="' . htmlspecialchars($product['name']) . '" />';
                                } else {
                                    echo '<img src="uploads/products/' . htmlspecialchars($photo) . '" alt="' . htmlspecialchars($product['name']) . '" />';
                                }
                                ?>
                            </div>
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p><?php echo htmlspecialchars($product['description']); ?></p>
                            <div class="price"><?php echo number_format($product['price'], 2); ?>   DZD</div>
                            <?php
                            $stockClass = '';
                            if ($product['stock'] <= 0) {
                                $stockClass = 'out';
                                $stockText = 'Out of Stock';
                            } elseif ($product['stock'] <= 5) {
                                $stockClass = 'low';
                                $stockText = 'Low Stock: ' . $product['stock'];
                            } else {
                                $stockText = 'In Stock: ' . $product['stock'];
                            }
                            ?>
                            <span class="stock <?php echo $stockClass; ?>"><?php echo $stockText; ?></span>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-products-message">
                <p>No products found in this category.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
include 'footer.php';
?>
</body>
</html>
