<?php
session_start();

include 'db_connect.php';
include 'dash_header.php';
$isAdmin = false; // Default to false
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$_SESSION['email']]);
        $admin = $stmt->fetch();

        if ($admin) {
            $isAdmin = true; // Set true if email exists in the admins table
            $_SESSION['admin_id'] = $admin['id']; // Store admin ID in session
            $_SESSION['admin_role'] = $admin['role']; // Store admin role in session
        }
    } catch (PDOException $e) {
        echo "Error: Unable to verify admin status. " . $e->getMessage();
    }
}

// Check if the user is logged in and is an admin
if (!$isAdmin) {
    header('Location: login.php');
    exit;
}

// Fetch the logged-in admin's details
$stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

// If the admin is not found, destroy the session and redirect to login
if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Success/error message handling
$message = '';
$message_type = '';

// Update settings
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Basic settings
        $site_name = htmlspecialchars($_POST['site_name']);
        $site_description = htmlspecialchars($_POST['site_description']);
        $primary_color = $_POST['primary_color'];
        $secondary_color = $_POST['secondary_color'];
        $accent_color = $_POST['accent_color'];
        $footer_text = htmlspecialchars($_POST['footer_text']);
        $allow_registration = isset($_POST['allow_registration']) ? 1 : 0;
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $font_family = $_POST['font_family'];
        $theme_mode = $_POST['theme_mode'];
        $analytics_id = htmlspecialchars($_POST['analytics_id']);
        $meta_keywords = htmlspecialchars($_POST['meta_keywords']);
        $contact_email = htmlspecialchars($_POST['contact_email']);
        $social_links = json_encode([
            'facebook' => htmlspecialchars($_POST['facebook_url']),
            'twitter' => htmlspecialchars($_POST['twitter_url']),
            'instagram' => htmlspecialchars($_POST['instagram_url']),
            'linkedin' => htmlspecialchars($_POST['linkedin_url'])
        ]);

        // Logo Upload
        if (!empty($_FILES['logo']['name'])) {
            $logo = $_FILES['logo']['name'];
            $logo_tmp = $_FILES['logo']['tmp_name'];
            $logo_ext = strtolower(pathinfo($logo, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'svg'];
            
            if (in_array($logo_ext, $allowed_exts)) {
                $logo_name = 'site_logo_' . time() . '.' . $logo_ext;
                $logo_path = "uploads/" . $logo_name;
                
                if (move_uploaded_file($logo_tmp, $logo_path)) {
                    $stmt = $pdo->prepare("UPDATE site_settings SET logo = :logo");
                    $stmt->execute([':logo' => $logo_path]);
                }
            }
        }

        // Favicon Upload
        if (!empty($_FILES['favicon']['name'])) {
            $favicon = $_FILES['favicon']['name'];
            $favicon_tmp = $_FILES['favicon']['tmp_name'];
            $favicon_ext = strtolower(pathinfo($favicon, PATHINFO_EXTENSION));
            $allowed_exts = ['ico', 'png'];
            
            if (in_array($favicon_ext, $allowed_exts)) {
                $favicon_name = 'favicon_' . time() . '.' . $favicon_ext;
                $favicon_path = "uploads/" . $favicon_name;
                
                if (move_uploaded_file($favicon_tmp, $favicon_path)) {
                    $stmt = $pdo->prepare("UPDATE site_settings SET favicon = :favicon");
                    $stmt->execute([':favicon' => $favicon_path]);
                }
            }
        }

        // Update all settings
        $stmt = $pdo->prepare("UPDATE site_settings 
                           SET site_name = :site_name, 
                               site_description = :site_description,
                               primary_color = :primary_color, 
                               secondary_color = :secondary_color,
                               accent_color = :accent_color,
                               footer_text = :footer_text,
                               allow_registration = :allow_registration,
                               maintenance_mode = :maintenance_mode,
                               font_family = :font_family,
                               theme_mode = :theme_mode,
                               analytics_id = :analytics_id,
                               meta_keywords = :meta_keywords,
                               contact_email = :contact_email,
                               social_links = :social_links,
                               updated_at = NOW()");
        $stmt->execute([
            ':site_name' => $site_name,
            ':site_description' => $site_description,
            ':primary_color' => $primary_color,
            ':secondary_color' => $secondary_color,
            ':accent_color' => $accent_color,
            ':footer_text' => $footer_text,
            ':allow_registration' => $allow_registration,
            ':maintenance_mode' => $maintenance_mode,
            ':font_family' => $font_family,
            ':theme_mode' => $theme_mode,
            ':analytics_id' => $analytics_id,
            ':meta_keywords' => $meta_keywords,
            ':contact_email' => $contact_email,
            ':social_links' => $social_links
        ]);

        $message = "Settings updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating settings: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get current settings
$stmt = $pdo->prepare("SELECT * FROM site_settings LIMIT 1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Parse social links from JSON
$social_links = !empty($settings['social_links']) ? json_decode($settings['social_links'], true) : [
    'facebook' => '',
    'twitter' => '',
    'instagram' => '',
    'linkedin' => ''
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: <?= $settings['primary_color'] ?? '#2563eb' ?>;
            --secondary-color: <?= $settings['secondary_color'] ?? '#475569' ?>;
            --accent-color: <?= $settings['accent_color'] ?? '#f97316' ?>;
            --body-bg: #f9fafb;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border-color: #e2e8f0;
            --success-color: #10b981;
            --error-color: #ef4444;
            --font-family: '<?= $settings['font_family'] ?? 'Montserrat' ?>', sans-serif;
            --transition: all 0.3s ease;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            --shadow: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --border-radius: 12px;
        }

        /* Dark Mode Variables */
        .dark-mode {
            --body-bg: #0f172a;
            --card-bg: #1e293b;
            --text-dark: #f1f5f9;
            --text-light: #cbd5e1;
            --border-color: #334155;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--body-bg);
            color: var(--text-dark);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            transition: var(--transition);
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 20px;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .settings-container {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .settings-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0.75rem;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .form-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .form-section-title i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--card-bg);
            color: var(--text-dark);
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .color-picker {
            height: 45px;
            cursor: pointer;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--border-color);
            transition: var(--transition);
            border-radius: 30px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: var(--transition);
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--primary-color);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
            background-color: var(--secondary-color);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }

        .file-upload-label:hover {
            background-color: var(--text-dark);
        }

        .file-upload-label i {
            margin-right: 0.5rem;
        }

        .file-upload input[type="file"] {
            position: absolute;
            width: 0;
            height: 0;
            opacity: 0;
        }

        .image-preview {
            margin-top: 1rem;
            display: flex;
            align-items: center;
        }

        .image-preview img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #334155;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }

        .nav-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .nav-tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 600;
        }

        .nav-tab.active {
            border-bottom: 3px solid var(--primary-color);
            color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .tooltip {
            position: relative;
            display: inline-block;
            margin-left: 0.5rem;
            color: var(--text-light);
        }

        .tooltip .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: var(--text-dark);
            color: white;
            text-align: center;
            border-radius: 6px;
            padding: 0.5rem;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.875rem;
            font-weight: normal;
        }

        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .nav-tabs {
                flex-wrap: wrap;
            }
            
            .nav-tab {
                padding: 0.5rem 1rem;
            }
        }

        /* Dark mode toggle */
        .theme-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow);
            z-index: 1000;
        }
    </style>
</head>
<body class="<?= $settings['theme_mode'] === 'dark' ? 'dark-mode' : '' ?>">

<div class="container">
    

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="settings-container">
        <h2 class="settings-title">Website Settings</h2>

        <div class="nav-tabs">
            <div class="nav-tab active" data-tab="general">
                <i class="fas fa-cog"></i> General
            </div>
            <div class="nav-tab" data-tab="appearance">
                <i class="fas fa-palette"></i> Appearance
            </div>
            <div class="nav-tab" data-tab="seo">
                <i class="fas fa-search"></i> SEO
            </div>
            <div class="nav-tab" data-tab="social">
                <i class="fas fa-share-alt"></i> Social Media
            </div>
            <div class="nav-tab" data-tab="advanced">
                <i class="fas fa-sliders-h"></i> Advanced
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <!-- General Settings Tab -->
            <div class="tab-content active" id="general">
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-info-circle"></i> Basic Information
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="site_name">Site Name</label>
                            <input type="text" class="form-control" id="site_name" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="site_description">Site Description</label>
                            <textarea class="form-control" id="site_description" name="site_description" rows="3"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="contact_email">Contact Email</label>
                            <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-image"></i> Logo & Favicon
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="logo">Site Logo</label>
                            <div class="file-upload">
                                <label class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i> Choose Logo
                                    <input type="file" id="logo" name="logo" accept="image/png, image/jpeg, image/svg+xml">
                                </label>
                            </div>
                            <div class="image-preview">
                                <?php if (!empty($settings['logo'])): ?>
                                    <img src="<?= htmlspecialchars($settings['logo']) ?>" alt="Current Logo">
                                <?php else: ?>
                                    <p>No logo uploaded</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="favicon">Favicon</label>
                            <div class="file-upload">
                                <label class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i> Choose Favicon
                                    <input type="file" id="favicon" name="favicon" accept="image/x-icon, image/png">
                                </label>
                            </div>
                            <div class="image-preview">
                                <?php if (!empty($settings['favicon'])): ?>
                                    <img src="<?= htmlspecialchars($settings['favicon']) ?>" alt="Current Favicon">
                                <?php else: ?>
                                    <p>No favicon uploaded</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-file-alt"></i> Footer Content
                    </h3>
                    <div class="form-group">
                        <label for="footer_text">Footer Text</label>
                        <textarea class="form-control" id="footer_text" name="footer_text" rows="3"><?= htmlspecialchars($settings['footer_text'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Appearance Settings Tab -->
            <div class="tab-content" id="appearance">
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-paint-brush"></i> Colors
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="primary_color">Primary Color</label>
                            <input type="color" class="form-control color-picker" id="primary_color" name="primary_color" value="<?= htmlspecialchars($settings['primary_color'] ?? '#2563eb') ?>">
                        </div>
                        <div class="form-group">
                            <label for="secondary_color">Secondary Color</label>
                            <input type="color" class="form-control color-picker" id="secondary_color" name="secondary_color" value="<?= htmlspecialchars($settings['secondary_color'] ?? '#475569') ?>">
                        </div>
                        <div class="form-group">
                            <label for="accent_color">Accent Color</label>
                            <input type="color" class="form-control color-picker" id="accent_color" name="accent_color" value="<?= htmlspecialchars($settings['accent_color'] ?? '#f97316') ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-font"></i> Typography
                    </h3>
                    <div class="form-group">
                        <label for="font_family">Font Family</label>
                        <select class="form-control" id="font_family" name="font_family">
                            <option value="Montserrat" <?= ($settings['font_family'] ?? '') == 'Montserrat' ? 'selected' : '' ?>>Montserrat</option>
                            <option value="Roboto" <?= ($settings['font_family'] ?? '') == 'Roboto' ? 'selected' : '' ?>>Roboto</option>
                            <option value="Poppins" <?= ($settings['font_family'] ?? '') == 'Poppins' ? 'selected' : '' ?>>Poppins</option>
                            <option value="Open Sans" <?= ($settings['font_family'] ?? '') == 'Open Sans' ? 'selected' : '' ?>>Open Sans</option>
                            <option value="Raleway" <?= ($settings['font_family'] ?? '') == 'Raleway' ? 'selected' : '' ?>>Raleway</option>
                            <option value="Special Gothic Expanded One" <?= ($settings['font_family'] ?? '') == 'Special Gothic Expanded One' ? 'selected' : '' ?>>Special Gothic Expanded One</option>
                        </select>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-adjust"></i> Theme Mode
                    </h3>
                    <div class="form-group">
                        <label for="theme_mode">Default Theme</label>
                        <select class="form-control" id="theme_mode" name="theme_mode">
                            <option value="light" <?= ($settings['theme_mode'] ?? '') == 'light' ? 'selected' : '' ?>>Light</option>
                            <option value="dark" <?= ($settings['theme_mode'] ?? '') == 'dark' ? 'selected' : '' ?>>Dark</option>
                            <option value="auto" <?= ($settings['theme_mode'] ?? '') == 'auto' ? 'selected' : '' ?>>Auto (follow system)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SEO Settings Tab -->
            <div class="tab-content" id="seo">
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-search"></i> SEO Settings
                    </h3>
                    <div class="form-group">
                        <label for="meta_keywords">Meta Keywords</label>
                        <input type="text" class="form-control" id="meta_keywords" name="meta_keywords" value="<?= htmlspecialchars($settings['meta_keywords'] ?? '') ?>" placeholder="e.g. website, services, products">
                        <small class="form-text">Separate keywords with commas</small>
                    </div>

                    <div class="form-group">
                        <label for="analytics_id">Google Analytics ID</label>
                        <input type="text" class="form-control" id="analytics_id" name="analytics_id" value="<?= htmlspecialchars($settings['analytics_id'] ?? '') ?>" placeholder="e.g. G-XXXXXXXXXX">
                    </div>
                </div>
            </div>

            <!-- Social Media Tab -->
            <div class="tab-content" id="social">
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-share-alt"></i> Social Media Links
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="facebook_url">
                                <i class="fab fa-facebook"></i> Facebook URL
                            </label>
                            <input type="url" class="form-control" id="facebook_url" name="facebook_url" value="<?= htmlspecialchars($social_links['facebook'] ?? '') ?>" placeholder="https://facebook.com/yourpage">
                        </div>
                        <div class="form-group">
                            <label for="twitter_url">
                                <i class="fab fa-twitter"></i> Twitter URL
                            </label>
                            <input type="url" class="form-control" id="twitter_url" name="twitter_url" value="<?= htmlspecialchars($social_links['twitter'] ?? '') ?>" placeholder="https://twitter.com/yourhandle">
                        </div>
                        <div class="form-group">
                            <label for="instagram_url">
                                <i class="fab fa-instagram"></i> Instagram URL
                            </label>
                            <input type="url" class="form-control" id="instagram_url" name="instagram_url" value="<?= htmlspecialchars($social_links['instagram'] ?? '') ?>" placeholder="https://instagram.com/yourhandle">
                        </div>
                        <div class="form-group">
                            <label for="linkedin_url">
                                <i class="fab fa-linkedin"></i> LinkedIn URL
                            </label>
                            <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" value="<?= htmlspecialchars($social_links['linkedin'] ?? '') ?>" placeholder="https://linkedin.com/company/yourcompany">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Settings Tab -->
            <div class="tab-content" id="advanced">
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-shield-alt"></i> Security & Access
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <span>Allow User Registration</span>
                                <div class="tooltip">
                                    <i class="fas fa-question-circle"></i>
                                    <span class="tooltip-text">Enable this to allow new users to register on your website</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="allow_registration" <?= ($settings['allow_registration'] ?? 0) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <span>Maintenance Mode</span>
                                <div class="tooltip">
                                    <i class="fas fa-question-circle"></i>
                                    <span class="tooltip-text">Enable this to put your website in maintenance mode. Only admins can access the site.</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="maintenance_mode" <?= ($settings['maintenance_mode'] ?? 0) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-database"></i> Cache & Performance
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <span>Enable Page Caching</span>
                                <div class="tooltip">
                                    <i class="fas fa-question-circle"></i>
                                    <span class="tooltip-text">Enable this to improve site loading speed by caching pages</span>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_cache" <?= ($settings['enable_cache'] ?? 0) ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </label>
                        </div>
                        <div class="form-group">
                            <label for="cache_lifetime">Cache Lifetime (seconds)</label>
                            <input type="number" class="form-control" id="cache_lifetime" name="cache_lifetime" value="<?= htmlspecialchars($settings['cache_lifetime'] ?? '3600') ?>" min="60" max="86400">
                        </div>
                    </div>
                    <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-history"></i> Backup & Restore
                    </h3>
                    <div class="form-group">
                        <button type="button" id="backup_settings" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Export Settings
                        </button>
                        <div class="file-upload" style="margin-top: 1rem;">
                            <label class="file-upload-label">
                                <i class="fas fa-upload"></i> Import Settings
                                <input type="file" id="import_settings" name="import_settings" accept=".json">
                            </label>
                        </div>
                    </div>
                </div>
                </div>
                
                
                
                
            </div>

            <div class="form-actions">
                <button type="reset" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset Changes
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<button class="theme-toggle" id="themeToggle">
    <i class="fas fa-moon"></i>
</button>

<script>
    // Tab switching functionality
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.nav-tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remove active class from all tabs and contents
                tabs.forEach(tab => tab.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // Add active class to selected tab and content
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // File input preview
        const logoInput = document.getElementById('logo');
        const logoPreview = logoInput.parentElement.parentElement.querySelector('.image-preview');
        
        logoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    logoPreview.innerHTML = `<img src="${e.target.result}" alt="Logo Preview">`;
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        const faviconInput = document.getElementById('favicon');
        const faviconPreview = faviconInput.parentElement.parentElement.querySelector('.image-preview');
        
        faviconInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    faviconPreview.innerHTML = `<img src="${e.target.result}" alt="Favicon Preview">`;
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const themeIcon = themeToggle.querySelector('i');
        
        themeToggle.addEventListener('click', function() {
            body.classList.toggle('dark-mode');
            
            if (body.classList.contains('dark-mode')) {
                themeIcon.className = 'fas fa-sun';
            } else {
                themeIcon.className = 'fas fa-moon';
            }
        });
        
        // Export settings
        document.getElementById('backup_settings').addEventListener('click', function() {
            // Normally you would fetch this from the server via AJAX
            // For demonstration, we'll use a simplified approach
            const settings = <?= json_encode($settings) ?>;
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(settings));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", "site_settings_backup_" + new Date().toISOString().split('T')[0] + ".json");
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
        });
        
        // Import settings
        document.getElementById('import_settings').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const settings = JSON.parse(e.target.result);
                    
                    // Populate form fields with imported settings
                    if (settings.site_name) document.getElementById('site_name').value = settings.site_name;
                    if (settings.site_description) document.getElementById('site_description').value = settings.site_description;
                    if (settings.primary_color) document.getElementById('primary_color').value = settings.primary_color;
                    if (settings.secondary_color) document.getElementById('secondary_color').value = settings.secondary_color;
                    if (settings.accent_color) document.getElementById('accent_color').value = settings.accent_color;
                    if (settings.footer_text) document.getElementById('footer_text').value = settings.footer_text;
                    if (settings.meta_keywords) document.getElementById('meta_keywords').value = settings.meta_keywords;
                    if (settings.analytics_id) document.getElementById('analytics_id').value = settings.analytics_id;
                    if (settings.contact_email) document.getElementById('contact_email').value = settings.contact_email;
                    
                    if (settings.allow_registration) {
                        document.querySelector('input[name="allow_registration"]').checked = Boolean(parseInt(settings.allow_registration));
                    }
                    
                    if (settings.maintenance_mode) {
                        document.querySelector('input[name="maintenance_mode"]').checked = Boolean(parseInt(settings.maintenance_mode));
                    }
                    
                    if (settings.enable_cache) {
                        document.querySelector('input[name="enable_cache"]').checked = Boolean(parseInt(settings.enable_cache));
                    }
                    
                    if (settings.font_family) {
                        document.getElementById('font_family').value = settings.font_family;
                    }
                    
                    if (settings.theme_mode) {
                        document.getElementById('theme_mode').value = settings.theme_mode;
                    }
                    
                    alert('Settings imported successfully!');
                } catch (error) {
                    alert('Error importing settings: ' + error.message);
                }
            };
            reader.readAsText(file);
        });
    });
</script>
</body>
</html>