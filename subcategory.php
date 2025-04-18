<?php
session_start();
include 'db_connect.php'; // Ensure this path is correct
 include 'header.php';

if (isset($_GET['id'])) {
    $subCategoryId = intval($_GET['id']); // Ensure it's an integer

    try {
        // Prepare and execute the SQL statement to get subcategory details
        $stmt = $pdo->prepare("SELECT * FROM subcategories WHERE id = ?");
        $stmt->execute([$subCategoryId]);
        $subCategory = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($subCategory) {
            // Prepare and execute the SQL statement to get products for the subcategory
            $stmt = $pdo->prepare("
                SELECT p.*, pi.image_url 
                FROM products p 
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 
                WHERE p.subcategory_id = ?
            ");
            $stmt->execute([$subCategoryId]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            echo "Subcategory not found.";
            exit;
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        exit;
    }
} else {
    echo "No subcategory ID provided.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($subCategory['name']); ?> - Lokpix</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
    </style>
</head>
<body>
    <h1 style="text-align: center; margin: 20px 0;">Products</h1>
    <main>
        <section class="subcategory">
            


        
            <div class="products-list">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-grid">
                            <div class="product-item">
                                <a href="./product.php?id=<?php echo htmlspecialchars($product['id']); ?>">
                                    <?php
                                    // Use the primary image URL from the product_images table or a default photo
                                    $photo = isset($product['image_url']) ? $product['image_url'] : 'default-photo.jpg'; // Default photo if not available
                                    ?>
                                    <div class="product-image">
                                        <?php
                                        // If the photo is a URL, display it directly; otherwise, assume it's an uploaded file
                                        if (filter_var($photo, FILTER_VALIDATE_URL)) {
                                            echo '<img src="' . htmlspecialchars($photo) . '" alt="Product photo" />';
                                        } else {
                                            echo '<img src="./uploads/products/' . htmlspecialchars($photo) . '" alt="Product photo" />';
                                        }
                                        ?>
                                    </div>
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p><?php echo htmlspecialchars($product['description']); ?></p>
                                    <p class="price"><?php echo htmlspecialchars($product['price']); ?>    DZD</p>
                                    <?php
                                    $stockLevel = intval($product['stock']);
                                    if ($stockLevel <= 0) {
                                        echo '<p class="stock out">Out of Stock</p>';
                                    } elseif ($stockLevel <= 5) {
                                        echo '<span class="stock low">Low Stock ' . $stockLevel . '</span>';
                                    } else {
                                        echo '<span class="stock">In Stock ' . $stockLevel . ' </span>';
                                    }
                                    ?>
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
        </section>
    </main>

    <?php
include 'footer.php';
?>
    
</body>
</html>
