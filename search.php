<?php
include 'header.php';
include 'db_connect.php';

// Get the search query from URL
$searchQuery = isset($_GET['query']) ? trim($_GET['query']) : '';

// Initialize variables
$products = [];
$error = '';

if (!empty($searchQuery)) {
    try {
        // Prepare the SQL query with wildcards for partial matches
        $sql = "SELECT p.*, c.name as category_name, s.name as subcategory_name, pi.image_url 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN subcategories s ON p.subcategory_id = s.id 
                LEFT JOIN product_images pi ON p.id = pi.product_id 
                WHERE pi.is_primary = 1  -- Only get the primary image
                AND (p.name LIKE :query 
                OR p.description LIKE :query 
                OR c.name LIKE :query 
                OR s.name LIKE :query)";
        
        $stmt = $pdo->prepare($sql);
        $searchTerm = "%{$searchQuery}%";
        $stmt->bindParam(':query', $searchTerm, PDO::PARAM_STR);
        $stmt->execute();
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - <?php echo htmlspecialchars($searchQuery); ?></title>
    <style>
        .search-results {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
            background-color: #f9f9f9;
            border-radius: 10px;
            
        }

        .search-header {
            margin-bottom: 20px;
        }

        .search-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .search-count {
            color: #7f8c8d;
            font-size: 18px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .product-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .product-image img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .product-name {
            font-size: 20px;
            font-weight: bold;
            margin: 15px 0;
            color: #2980b9;
        }

        .product-price {
            color: #27ae60;
            font-size: 22px;
            font-weight: bold;
            margin: 15px 0;
        }

        .product-category {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }

        .view-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s, transform 0.3s;
        }

        .view-button:hover {
            background-color: #2980b9;
            transform: scale(1.05);
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .error-message {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="search-results">
        <div class="search-header">
            <h1>Search Results for "<?php echo htmlspecialchars($searchQuery); ?>"</h1>
            <?php if (!empty($products)): ?>
                <div class="search-count"><?php echo count($products); ?> products found</div>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php elseif (empty($products) && !empty($searchQuery)): ?>
            <div class="no-results">
                <h2>No products found matching your search.</h2>
                <p>Try different keywords or check your spelling.</p>
            </div>
        <?php elseif (!empty($products)): ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
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
                        <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-price"><?php echo number_format($product['price'], 2); ?>   DZD</p>
                        <p class="product-category">
                            <?php 
                            echo htmlspecialchars($product['category_name']);
                            if (!empty($product['subcategory_name'])) {
                                echo " > " . htmlspecialchars($product['subcategory_name']);
                            }
                            ?>
                        </p>
                        <a href="product.php?id=<?php echo $product['id']; ?>" class="view-button">View Details</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>