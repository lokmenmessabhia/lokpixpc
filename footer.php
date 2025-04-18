<?php
// footer.php

// Include database connection (update path if necessary)
include 'db_connect.php'; // Ensure this file sets up a PDO instance in $pdo

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

// Fetch categories from the database
try {
    $stmt = $pdo->query("SELECT * FROM categories");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: Unable to fetch categories. " . $e->getMessage();
    $categories = []; // Initialize as empty array in case of error
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

    /* Base Styles */
    body {
        line-height: 1.5;
        font-family: <?= htmlspecialchars($settings['font_family'] ?? "'Poppins', sans-serif") ?>;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    /* Color Variables */
    :root {
        --footer-bg: #FFFFFF;
        --footer-text: #333333;
        --footer-heading: #2c3e50;
        --footer-link: #555555;
        --footer-accent: <?= htmlspecialchars($settings['primary_color'] ?? '#3498db') ?>;
        --footer-accent-hover: <?= htmlspecialchars($settings['accent_color'] ?? '#2980b9') ?>;
        --footer-social-bg: #f8f9fa;
        --footer-shadow: rgba(0, 0, 0, 0.05);
    }

    /* Dark Mode */
    <?php if (($settings['theme_mode'] ?? 'light') === 'dark'): ?>
    :root {
        --footer-bg: #1a1a1a;
        --footer-text: #f8f9fa;
        --footer-heading: #e9ecef;
        --footer-link: #adb5bd;
        --footer-social-bg: #2d2d2d;
        --footer-shadow: rgba(0, 0, 0, 0.2);
    }
    <?php endif; ?>

    /* Footer Container */
    .footer {
        background-color: var(--footer-bg);
        color: var(--footer-text);
        padding: 60px 20px 20px;
        margin-top: 40px;
        box-shadow: 0 -4px 10px var(--footer-shadow);
    }

    .containerr {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .row {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 20px;
    }

    /* Footer Columns */
    .footer-col {
        flex: 1;
        min-width: 200px;
        padding: 10px;
    }

    .footer-col h4 {
        font-size: 18px;
        font-weight: 600;
        color: var(--footer-heading);
        margin-bottom: 20px;
        position: relative;
    }

    .footer-col h4::before {
        content: '';
        position: absolute;
        left: 0;
        bottom: -10px;
        width: 50px;
        height: 2px;
        background-color: var(--footer-accent);
    }

    .footer-col ul {
        list-style: none;
        padding: 0;
    }

    .footer-col ul li {
        margin-bottom: 10px;
    }

    .footer-col ul li a {
        color: var(--footer-link);
        text-decoration: none;
        font-size: 14px;
        font-weight: 400;
        transition: all 0.3s ease;
    }

    .footer-col ul li a:hover {
        color: var(--footer-accent);
        padding-left: 5px;
    }

    /* Social Links */
    .footer-col .social-links {
        display: flex;
        gap: 10px;
    }

    .footer-col .social-links a {
        display: inline-block;
        width: 40px;
        height: 40px;
        background-color: var(--footer-social-bg);
        border-radius: 50%;
        text-align: center;
        line-height: 40px;
        color: var(--footer-link);
        transition: all 0.3s ease;
    }

    .footer-col .social-links a:hover {
        background-color: var(--footer-accent);
        color: #ffffff;
        transform: translateY(-5px);
    }

    /* Logo Section */
    .footer-logo {
        max-width: 150px;
        height: auto;
        margin-bottom: 20px;
    }

    /* Build PC Link */
    .build-pc-link {
        display: inline-block;
        padding: 10px 20px;
        background-color: var(--footer-accent);
        color: #ffffff;
        border-radius: 25px;
        text-decoration: none;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .build-pc-link:hover {
        background-color: var(--footer-accent-hover);
        transform: translateY(-3px);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .footer-col {
            flex: 1 1 45%;
        }

        .footer-logo {
            max-width: 120px;
        }
    }

    @media (max-width: 480px) {
        .footer-col {
            flex: 1 1 100%;
            text-align: center;
        }

        .footer-col h4::before {
            left: 50%;
            transform: translateX(-50%);
        }

        .footer-col .social-links {
            justify-content: center;
        }

        .footer-logo {
            max-width: 100px;
        }
    }
    </style>
   
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<footer class="footer">
    <div class="containerr">
        <div class="row">
            <div class="footer-col">
                <img src="<?= htmlspecialchars($settings['logo'] ?? 'logo (1).png') ?>" alt="<?= htmlspecialchars($settings['site_name'] ?? 'Company') ?> Logo" class="footer-logo">
                <?php if (!empty($settings['footer_description'])): ?>
                    <p style="color: var(--footer-text); font-size: 14px; margin-top: 15px;">
                        <?= htmlspecialchars($settings['footer_description']) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="footer-col">
                <h4><?= htmlspecialchars($settings['site_name'] ?? 'EcoTech') ?></h4>
                <ul>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="privacy-policy.php">Privacy Policy</a></li>
                    <?php if (!empty($settings['footer_links'])): ?>
                        <?php foreach (json_decode($settings['footer_links'], true) ?? [] as $link): ?>
                            <li><a href="<?= htmlspecialchars($link['url']) ?>"><?= htmlspecialchars($link['text']) ?></a></li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
           
            <div class="footer-col">
                <h4>Online Shop</h4>
                <ul>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $category): ?>
                            <li><a href="category.php?id=<?php echo htmlspecialchars($category['id'])?>"><?= htmlspecialchars($category['name']) ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a href="#">No categories available</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Follow Us</h4>
                <div class="social-links">
                    <?php if (!empty($social_links['facebook'])): ?>
                        <a href="<?= htmlspecialchars($social_links['facebook']) ?>" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($social_links['twitter'])): ?>
                        <a href="<?= htmlspecialchars($social_links['twitter']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-x-twitter"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($social_links['instagram'])): ?>
                        <a href="<?= htmlspecialchars($social_links['instagram']) ?>" target="_blank" rel="noopener"><i class="fab fa-instagram"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($social_links['linkedin'])): ?>
                        <a href="<?= htmlspecialchars($social_links['linkedin']) ?>" target="_blank" rel="noopener"><i class="fab fa-linkedin-in"></i></a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($settings['footer_contact'])): ?>
                    <div style="margin-top: 20px; color: var(--footer-text); font-size: 14px;">
                        <?= nl2br(htmlspecialchars($settings['footer_contact'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($settings['footer_bottom_text'])): ?>
            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--footer-link); color: var(--footer-text); font-size: 14px;">
                <?= htmlspecialchars($settings['footer_bottom_text']) ?>
            </div>
        <?php endif; ?>
    </div>
</footer>
<?php if (!empty($settings['footer_scripts'])): ?>
    <?= $settings['footer_scripts'] ?>
<?php endif; ?>
</body>
</html>