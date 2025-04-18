<?php
include 'db_connect.php'; // Ensure this path is correct

// Fetch all products
$stmt = $pdo->prepare("SELECT * FROM products");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products List - Lokpix</title>
    <link rel="stylesheet" href="styles.css"> <!-- Ensure this path is correct -->
</head>
<body>
    <?php include 'header.php'; ?>

    <main>
        <div class="product-list-container">
            <h1>Our Products</h1>
            <div class="product-list">
                <?php foreach ($products as $product) : ?>
                    <div class="product-item">
                        <a href="product.php?id=<?php echo $product['id']; ?>">
                            <img src="uploads/<?php echo htmlspecialchars($product['photo']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p>$<?php echo htmlspecialchars($product['price']); ?></p>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2024 Lokpix. All rights reserved.</p>
    </footer>
</body>
</html>
